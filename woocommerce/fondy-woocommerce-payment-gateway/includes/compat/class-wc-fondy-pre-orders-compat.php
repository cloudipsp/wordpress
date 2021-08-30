<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Compatibility class for Pre-Orders.
 */
class WC_Fondy_Pre_Orders_Compat
{
    const META_NAME_FONDY_ORDER_PREAUTH = '_fondy_order_preauth'; //todo

    private $paymentGateway;

    public function __construct($gateway)
    {
        $this->paymentGateway = $gateway;
        add_action('wc_pre_orders_process_pre_order_completion_payment_' . $this->paymentGateway->id, [$this, 'process_pre_order_payments']);
        add_action('wc_gateway_fondy_admin_options', [$this, 'getPreOrdersNotice']);
        add_filter('wc_gateway_fondy_payment_params', [$this, 'getPreOrdersPaymentParams'], 10, 2);
    }

    /**
     * Process a pre-order payment when the pre-order is released.
     *
     * @param WC_Order $order
     *
     * @return void
     */
    public function process_pre_order_payments($order)
    {
        try {
            $capture = WC_Fondy_API::capture([
                'order_id' => $order->get_meta(WC_Fondy_Payment_Gateway::META_NAME_FONDY_ORDER_ID),
                'currency' => esc_attr(get_woocommerce_currency()),
                'amount' => (int)round($order->get_total() * 100),
            ]);

            if ($capture->capture_status === 'captured') {
                $order->add_order_note(__('Fondy capture successful.', 'fondy-woocommerce-payment-gateway'));
                $order->payment_complete();
            } else {
                throw new Exception('Transaction: ' . $capture->response_status . '  <br/> ' . $capture->error_message . '<br>Request_id: ' . $capture->request_id);
            }
        } catch (Exception $e) {
            /* translators: error message */
            $order->update_status('failed', sprintf(__('Pre-order payment for order failed. Reason: %s', 'fondy-woocommerce-payment-gateway'), $e->getMessage()));
        }
    }

    public function getPreOrdersPaymentParams($params, $order)
    {
        if (WC_Pre_Orders_Order::order_contains_pre_order($order))
            $params['preauth'] = 'Y';

        return $params;
    }

    public function getPreOrdersNotice()
    {
        $message = __('Note: transactions by using Pre-Orders must be finished in 7 days term or it will be auto-captured.', 'fondy-woocommerce-payment-gateway');
        echo '<div class="notice notice-info is-dismissible"><p>' . $message . '</p></div>';
    }
}

