<?php

trait Fondy_Seamless
{
    public $seamless = true;

    public function includeSeamlessAssets()
    {
        // we need JS only on cart/checkout pages
        // if our payment gateway is disabled, we do not have to enqueue JS too
        if ('no' === $this->enabled || (!is_cart() && !is_checkout())) {
            return;
        }

        wp_enqueue_style('fondy-checkout', plugins_url('assets/css/fondy_seamless_old.css', WC_FONDY_BASE_FILE));
        wp_enqueue_script('fondy_pay_v2', 'https://unpkg.com/ipsp-js-sdk@latest/dist/checkout.min.js', ['jquery'], WC_FONDY_VERSION, true);
        wp_enqueue_script('fondy_pay_v2_woocom', plugins_url('assets/js/fondy_seamless.js', WC_FONDY_BASE_FILE), ['fondy_pay_v2'], WC_FONDY_VERSION, true);
        wp_enqueue_script('fondy_pay_v2_card', plugins_url('assets/js/payform.min.js', WC_FONDY_BASE_FILE), ['fondy_pay_v2_woocom'], WC_FONDY_VERSION, true);

        wp_localize_script('fondy_pay_v2_woocom', 'fondy_info',
            [
                'url' => WC_AJAX::get_endpoint('checkout'),
                'nonce' => wp_create_nonce('fondy-submit-nonce')
            ]
        );
    }

    public function payment_fields()
    {
        if ($this->integration_type === 'seamless') {
            ?>
            <form autocomplete="on" class="fondy-ccard" id="checkout_fondy_form">
            <input type="hidden" name="payment_system" value="card">
            <div class="f-container">
                <div class="input-wrapper">
                    <div class="input-label w-1">
                        <?php esc_html_e('Card Number:', 'fondy-woocommerce-payment-gateway') ?>
                    </div>
                    <div class="input-field w-1">
                        <input required type="tel" name="card_number" class="input fondy-credit-cart"
                               id="fondy_ccard"
                               autocomplete="cc-number"
                               placeholder="<?php esc_html_e('XXXXXXXXXXXXXXXX', 'fondy-woocommerce-payment-gateway') ?>"/>
                        <div id="f_card_sep"></div>
                    </div>
                </div>
                <div class="input-wrapper">
                    <div class="input-label w-3-2">
                        <?php esc_html_e('Expiry Date:', 'fondy-woocommerce-payment-gateway') ?>
                    </div>
                    <div class="input-label w-4 w-rigth">
                        <?php esc_html_e('CVV2:', 'fondy-woocommerce-payment-gateway') ?>
                    </div>
                    <div class="input-field w-4">
                        <input required type="tel" name="expiry_month" id="fondy_expiry_month"
                               onkeydown="nextInput(this,event)" class="input"
                               maxlength="2" placeholder="MM"/>
                    </div>
                    <div class="input-field w-4">
                        <input required type="tel" name="expiry_year" id="fondy_expiry_year"
                               onkeydown="nextInput(this,event)" class="input"
                               maxlength="2" placeholder="YY"/>
                    </div>
                    <div class="input-field w-4 w-rigth">
                        <input autocomplete="off" required type="tel" name="cvv2" id="fondy_cvv2"
                               onkeydown="nextInput(this,event)"
                               class="input"
                               placeholder="<?php esc_html_e('XXX', 'fondy-woocommerce-payment-gateway') ?>"/>
                    </div>
                </div>
                <div style="display: none" class="input-wrapper stack-1">
                    <div class="input-field w-1">
                        <input id="submit_fondy_checkout_form" type="submit" class="button"
                               value="<?php esc_html_e('Pay', 'fondy-woocommerce-payment-gateway') ?>"/>
                    </div>
                </div>
                <div class="error-wrapper"></div>
            </div>
            </form>
            <?php
        } else parent::payment_fields();
    }

    /**
     * Custom button order
     * @param $button
     * @return string
     */
    public function custom_order_button_html($button)
    {
        $order_button_text = __('Place order', 'fondy-woocommerce-payment-gateway');
        $js_event = "fondy_submit_order(event);";
        $button = '<button type="submit" onClick="' . esc_attr($js_event) . '" class="button alt" name="woocommerce_checkout_place_order" id="place_order" value="' . esc_attr($order_button_text) . '" data-value="' . esc_attr($order_button_text) . '" >' . esc_attr($order_button_text) . '</button>';

        return $button;
    }

    /**
     * Process checkout func
     */
    public function generate_ajax_order_fondy_info()
    {
        check_ajax_referer('fondy-submit-nonce', 'nonce_code');
        wc_maybe_define_constant('WOOCOMMERCE_CHECKOUT', true);
        WC()->checkout()->process_checkout();
        wp_die(0);
    }
}