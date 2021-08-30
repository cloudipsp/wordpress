<?php

trait Fondy_Embedded
{
    public $embedded = true;

    public function includeEmbeddedAssets()
    {
        // we need JS only on cart/checkout pages
        // if our payment gateway is disabled, we do not have to enqueue JS too
        if ('no' === $this->enabled || (!is_cart() && !is_checkout_pay_page())) {
            return;
        }

        wp_enqueue_style('fondy-vue-css', 'https://pay.fondy.eu/latest/checkout-vue/checkout.css', null, WC_FONDY_VERSION);
        wp_enqueue_script('fondy-vue-js', 'https://pay.fondy.eu/latest/checkout-vue/checkout.js', null, WC_FONDY_VERSION);

        wp_register_script('fondy-init', plugins_url('assets/js/fondy_embedded.js', WC_FONDY_BASE_FILE), ['fondy-vue-js'], WC_FONDY_VERSION);
        wp_enqueue_style('fondy-embedded', plugins_url('assets/css/fondy_embedded.css', WC_FONDY_BASE_FILE), ['storefront-woocommerce-style', 'fondy-vue-css'], WC_FONDY_VERSION);
    }

    public function receipt_page($order_id)
    {
        $order = wc_get_order($order_id);

        try {
            $paymentArguments = [
                'options' => $this->getPaymentOptions(),
                'params' => ['token' => $this->getCheckoutToken($order)],
            ];
        } catch (Exception $e) {
//            wc_add_notice( $e->getMessage(), 'error' ); wc_print_notices();
            wp_die($e->getMessage());
        }

        wp_enqueue_script('fondy-init');
        wp_localize_script('fondy-init', 'FondyPaymentArguments', $paymentArguments);

        echo '<div id="fondy-checkout-container"></div>';
    }
}