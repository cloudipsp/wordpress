<?php

abstract class WC_Fondy_Payment_Gateway extends WC_Payment_Gateway
{
    const ORDER_APPROVED = 'approved';
    const ORDER_DECLINED = 'declined';
    const ORDER_EXPIRED = 'expired';
    const ORDER_PROCESSING = 'processing';
    const ORDER_CREATED = 'created';
    const ORDER_REVERSED = 'reversed';
    const ORDER_SEPARATOR = "_";
    const META_NAME_FONDY_ORDER_ID = '_fondy_order_id';

    public $test_mode;
    public $merchant_id;
    public $secret_key;
    public $integration_type;
    public $completed_order_status;
    public $expired_order_status;
    public $declined_order_status;
    public $redirect_page_id;

    /**
     * WC_Fondy_Payment_Gateway constructor.
     */
    public function __construct()
    {
        if ($this->test_mode) {
            $this->merchant_id = WC_Fondy_API::TEST_MERCHANT_ID;
            $this->secret_key = WC_Fondy_API::TEST_MERCHANT_SECRET_KEY;
        }

        WC_Fondy_API::setMerchantID($this->merchant_id);
        WC_Fondy_API::setSecretKey($this->secret_key);

        // callback handler
        add_action('woocommerce_api_' . strtolower(get_class($this)), [$this, 'callbackHandler']);

        // todo mb thankyoupage change order status or clear cart
//        add_action('woocommerce_before_thankyou', [$this, '']);

        // This action hook saves the settings
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);

        if ($this->integration_type === 'embedded') {
            add_action('wp_enqueue_scripts', [$this, 'includeEmbeddedAssets']);
            add_action('woocommerce_receipt_' . $this->id, [$this, 'receipt_page']);
        }

        if ($this->integration_type === 'seamless') {
            add_action('wp_enqueue_scripts', [$this, 'includeSeamlessAssets']);
            add_filter('woocommerce_order_button_html', [$this, 'custom_order_button_html']);
            add_action('wp_ajax_nopriv_generate_ajax_order_fondy_info', [$this, 'generate_ajax_order_fondy_info'], 99);
            add_action('wp_ajax_generate_ajax_order_fondy_info', [$this, 'generate_ajax_order_fondy_info'], 99);
        }
    }

    /**
     * Process Payment.
     * Run after submit order button.
     *
     * @param int $order_id
     * @return array
     */
    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);
        $processResult = ['result' => 'success', 'redirect' => ''];

        try {
            if ($this->integration_type === 'embedded') {
                $processResult['redirect'] = $order->get_checkout_payment_url(true);
            } elseif ($this->integration_type === 'seamless') {
                $processResult['token'] = $this->getCheckoutToken($order);
            } else {
                $paymentParams = $this->getPaymentParams($order);
                $processResult['redirect'] = WC_Fondy_API::getCheckoutUrl($paymentParams);
            }
        } catch (Exception $e) {
            wc_add_notice($e->getMessage(), 'error');
            $processResult['result'] = 'fail';
        }

        // in prev version we are use session to save redirect_url
        return apply_filters('wc_gateway_fondy_process_payment_complete', $processResult, $order);
    }

    /**
     * Fondy payment parameters
     *
     * @param WC_Order $order
     * @return mixed|void
     * @since 3.0.0
     */
    public function getPaymentParams($order)
    {
        $params = [
            'order_id' => $this->createFondyOrderID($order),
            'order_desc' => __('Order №: ', 'fondy-woocommerce-payment-gateway') . $order->get_id(),
            'amount' => (int)round($order->get_total() * 100),
            'currency' => get_woocommerce_currency(),
            'lang' => $this->getLanguage(),
            'sender_email' => $this->getEmail($order),
            'response_url' => $this->getResponseUrl($order),
            'server_callback_url' => $this->getCallbackUrl(),
            'reservation_data' => $this->getReservationData($order),
        ];

        return apply_filters('wc_gateway_fondy_payment_params', $params, $order);
    }

    /**
     * Generate unique fondy order id
     * and save it to order meta.
     *
     * @param $order
     * @return string
     * @since 3.0.0
     */
    public function createFondyOrderID($order)
    {
        $fondyOrderID = $order->get_id() . self::ORDER_SEPARATOR . time();
        $order->update_meta_data(self::META_NAME_FONDY_ORDER_ID, $fondyOrderID);
        $order->save();

        return $fondyOrderID;
    }

    /**
     * Extracts fondy order if from order meta
     *
     * @param WC_Order $order
     * @return mixed
     * @since 3.0.0
     */
    public function getFondyOrderID($order)
    {
        return $order->get_meta(self::META_NAME_FONDY_ORDER_ID);
    }

    /**
     * Return custom or default order thank-you page url
     *
     * @param WC_Order $order
     * @return false|string|WP_Error
     * @since 3.0.0
     */
    public function getResponseUrl($order)
    {
        return $this->redirect_page_id ? get_permalink($this->redirect_page_id) : $this->get_return_url($order);
    }

    /**
     * Gets the transaction URL linked to Fondy merchant portal dashboard.
     *
     * @param WC_Order $order
     * @return string
     * @since 3.0.0
     */
    public function get_transaction_url($order)
    {
        $this->view_transaction_url = 'https://portal.fondy.eu/mportal/#/payments/order/%s';
        return parent::get_transaction_url($order);
    }

    /**
     * get checkout token
     * cache it to session
     *
     * @param $order
     * @return array|string
     * @throws Exception
     */
    public function getCheckoutToken($order)
    {
        $orderID = $order->get_id();
        $amount = (int)round($order->get_total() * 100);
        $currency = get_woocommerce_currency();
        $sessionTokenKey = 'session_token_' . md5($this->merchant_id . '_' . $orderID . '_' . $amount . '_' . $currency);
        $checkoutToken = WC()->session->get($sessionTokenKey);

        if (empty($checkoutToken)) {
            $paymentParams = $this->getPaymentParams($order);
            $checkoutToken = WC_Fondy_API::getCheckoutToken($paymentParams);
            WC()->session->set($sessionTokenKey, $checkoutToken);
        }

        return $checkoutToken;
    }

    /**
     * remove checkoutToken cache from session
     *
     * @param $paymentParams
     * @param $orderID
     */
    public function clearCache($paymentParams, $orderID)
    {
        WC()->session->__unset('session_token_' . md5($this->merchant_id . '_' . $orderID . '_' . $paymentParams['amount'] . '_' . $paymentParams['currency']));
    }

    /**
     * Fondy widget options
     *
     * @return array
     * @since 3.0.0
     */
    public function getPaymentOptions()
    {
        return [
            'full_screen' => false,
            'button' => true,
            'email' => true,
            'show_menu_first' => false,
        ];
    }

    /**
     * Site lang cropped
     *
     * @return string
     */
    public function getLanguage()
    {
        return substr(get_bloginfo('language'), 0, 2);
    }

    /**
     * Order Email
     *
     * @param WC_Order $order
     * @return string
     */
    public function getEmail($order)
    {
        $current_user = wp_get_current_user();
        $email = $current_user->user_email;

        if (empty($email)) {
            $order_data = $order->get_data();
            $email = $order_data['billing']['email'];
        }

        return $email;
    }

    public function getCallbackUrl()
    {
        return wc_get_endpoint_url('wc-api', strtolower(get_class($this)), get_site_url());
    }

    /**
     * Fondy antifraud parameters
     *
     * @param WC_Order $order
     * @return string
     * @since 3.0.0
     */
    public function getReservationData($order)
    {
        $orderData = $order->get_data();
        $orderDataBilling = $orderData['billing'];

        $reservationData = [
            'customer_zip' => $orderDataBilling['postcode'],
            'customer_name' => $orderDataBilling['first_name'] . ' ' . $orderDataBilling['last_name'],
            'customer_address' => $orderDataBilling['address_1'] . ' ' . $orderDataBilling['city'],
            'customer_state' => $orderDataBilling['state'],
            'customer_country' => $orderDataBilling['country'],
            'phonemobile' => $orderDataBilling['phone'],
            'account' => $orderDataBilling['email'],
            'cms_name' => 'Wordpress',
            'cms_version' => get_bloginfo('version'),
            'cms_plugin_version' => WC_FONDY_VERSION . ' (Woocommerce ' . WC_VERSION . ')',
            'shop_domain' => get_site_url(),
            'path' => isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '',
            'products' => $this->getReservationDataProducts($order->get_items())
        ];

        return base64_encode(json_encode($reservationData));
    }

    /**
     * data to create fiscal check
     *
     * @param $orderItemsProducts
     * @return array
     */
    public function getReservationDataProducts($orderItemsProducts)
    {
        $reservationDataProducts = [];

        try {
            /** @var WC_Order_Item_Product $orderProduct */
            foreach ($orderItemsProducts as $orderProduct) {
                $reservationDataProducts[] = [
                    'id' => $orderProduct->get_product_id(),
                    'name' => $orderProduct->get_name(),
                    'price' => $orderProduct->get_product()->get_price(),
                    'total_amount' => $orderProduct->get_total(),
                    'quantity' => $orderProduct->get_quantity(),
                ];
            }
        } catch (Exception $e) {
            $reservationDataProducts['error'] = $e->getMessage();
        }

        return $reservationDataProducts;
    }


    /**
     * @return array
     */
    public function getIntegrationTypes()
    {
        $integration_types = [];

        if (isset($this->embedded)) {
            $integration_types['embedded'] = __('Embedded', 'fondy-woocommerce-payment-gateway');
        }

        if (isset($this->hosted)) {
            $integration_types['hosted'] = __('Hosted', 'fondy-woocommerce-payment-gateway');
        }

        if (isset($this->seamless)) {
            $integration_types['seamless'] = __('Seamless', 'fondy-woocommerce-payment-gateway');
        }

        return $integration_types;
    }

    /**
     * @param bool $title
     * @param bool $indent
     * @return array
     */
    public function fondy_get_pages($title = false, $indent = true)
    {
        $wp_pages = get_pages('sort_column=menu_order');
        $page_list = array();
        if ($title) {
            $page_list[] = $title;
        }
        foreach ($wp_pages as $page) {
            $prefix = '';
            // show indented child pages?
            if ($indent) {
                $has_parent = $page->post_parent;
                while ($has_parent) {
                    $prefix .= ' - ';
                    $next_page = get_post($has_parent);
                    $has_parent = $next_page->post_parent;
                }
            }
            // add to page list array array
            $page_list[$page->ID] = $prefix . $page->post_title;
        }

        return $page_list;
    }

    /**
     * Getting all available woocommerce order statuses
     *
     * @return array
     */
    public function getPaymentOrderStatuses()
    {
        $order_statuses = function_exists('wc_get_order_statuses') ? wc_get_order_statuses() : [];
        $statuses = [
            'default' => __('Default status', 'fondy-woocommerce-payment-gateway')
        ];
        if ($order_statuses) {
            foreach ($order_statuses as $k => $v) {
                $statuses[str_replace('wc-', '', $k)] = $v;
            }
        }
        return $statuses;
    }

    /**
     * Fondy callback handler
     *
     * @since 3.0.0
     */
    public function callbackHandler()
    {
        try {
            $requestBody = !empty($_POST) ? $_POST : json_decode(file_get_contents('php://input'), true);
            WC_Fondy_API::validateRequest($requestBody);

            if (!empty($requestBody['reversal_amount']) || $requestBody['tran_type'] === 'reverse') // todo MB add refund complete note
                exit; // just ignore reverse callback

            // order switch status process
            $orderID = strstr($requestBody['order_id'], self::ORDER_SEPARATOR, true);
            $order = wc_get_order($orderID);
            $this->clearCache($requestBody, $orderID); // remove checkoutToken if exist

            do_action('wc_gateway_fondy_receive_valid_callback', $requestBody, $order);

            switch ($requestBody['order_status']) {
                case self::ORDER_APPROVED: //we recive with this status in 3 type transaction callback - purchase, capture and partial reverse
                    $this->fondyPaymentComplete($order, $requestBody['payment_id']);
                    break;
                case self::ORDER_CREATED:
                case self::ORDER_PROCESSING:
                    // we can receive processing status when Issuer bank declined payment. Mb add note.
                    // in default WC set pending status to order
                    break;
                case self::ORDER_DECLINED:
                    $newOrderStatus = $this->declined_order_status != 'default' ? $this->declined_order_status : 'failed';
                    /* translators: 1) fondy order status 2) fondy order id */
                    $orderNote = sprintf(__('Transaction ERROR: order %1$s<br/>Fondy ID: %2$s', 'fondy-woocommerce-payment-gateway'), $requestBody['order_status'], $requestBody['payment_id']);
                    $order->update_status($newOrderStatus, $orderNote);
                    break;
                case self::ORDER_EXPIRED:
                    $newOrderStatus = $this->expired_order_status != 'default' ? $this->expired_order_status : 'cancelled';
                    $orderNote = sprintf(__('Transaction ERROR: order %1$s<br/>Fondy ID: %2$s', 'fondy-woocommerce-payment-gateway'), $requestBody['order_status'], $requestBody['payment_id']);
                    $order->update_status($newOrderStatus, $orderNote);
                    break;
                default:
                    throw new Exception (__('Unhandled fondy order status', 'fondy-woocommerce-payment-gateway'));
            }
        } catch (Exception $e) {
            if (!empty($order))
                $order->update_status('failed', $e->getMessage());
            wp_send_json(['error' => $e->getMessage()], 400);
        }

        status_header(200);
        exit;
    }

    /**
     * Fondy payment complete process
     *
     * @param WC_Order $order
     * @param $transactionID
     * @since 3.0.0
     */
    public function fondyPaymentComplete($order, $transactionID)
    {
        if (!$order->is_paid()) {
            $order->payment_complete($transactionID);
            /* translators: fondy order id */
            $orderNote = sprintf(__('Fondy payment successful.<br/>Fondy ID: %1$s<br/>', 'fondy-woocommerce-payment-gateway'), $transactionID);

            if ($this->completed_order_status != 'default') {
                WC()->cart->empty_cart();
                $order->update_status($this->completed_order_status, $orderNote);
            } else $order->add_order_note($orderNote);
        }
    }
}

