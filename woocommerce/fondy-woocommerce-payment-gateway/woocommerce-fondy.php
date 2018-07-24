<?php
/*
Plugin Name: WooCommerce - Fondy payment gateway
Plugin URI: https://fondy.eu
Description: Fondy Payment Gateway for WooCommerce.
Version: 2.5.0
Author: Fondy
Author URI: https://fondy.eu/
Domain Path: /
Text Domain: woocommerce-fondy
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
WC requires at least: 2.0.0
WC tested up to: 3.4.3
*/
add_action("init", "woocoo_fondy");
function woocoo_fondy()
{
    load_plugin_textdomain("woocommerce-fondy", false, basename(dirname(__FILE__)));
}

add_action('plugins_loaded', 'woocommerce_fondy_init', 0);
define('IMGDIR', WP_PLUGIN_URL . "/" . plugin_basename(dirname(__FILE__)) . '/assets/img/');

function woocommerce_fondy_init()
{
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    /**
     * Gateway class
     */
    class WC_fondy extends WC_Payment_Gateway
    {
        const ORDER_APPROVED = 'approved';
        const ORDER_DECLINED = 'declined';
        const SIGNATURE_SEPARATOR = '|';
        const ORDER_SEPARATOR = ":";

        public function __construct()
        {
            $this->id = 'fondy';
            $this->method_title = 'Fondy';
            $this->method_description = "Payment gateway";
            $this->has_fields = false;
            $this->init_form_fields();
            $this->init_settings();
            if ($this->settings['showlogo'] == "yes") {
                $this->icon = IMGDIR . 'logo.png';
            }
            $this->liveurl = 'https://api.fondy.eu/api/checkout/redirect/';
            $this->refundurl = 'https://api.fondy.eu/api/reverse/order_id';
            $this->title = $this->settings['title'];
            $this->calendar = $this->settings['calendar'];
            $this->redirect_page_id = $this->settings['redirect_page_id'];
            $this->merchant_id = $this->settings['merchant_id'];
            $this->salt = $this->settings['salt'];
            $this->styles = $this->settings['styles'];
            $this->description = $this->settings['description'];
            $this->page_mode = $this->settings['page_mode'];
            $this->on_checkout_page = $this->settings['on_checkout_page'] ? $this->settings['on_checkout_page'] : false;
            $this->force_lang = $this->settings['force_lang'] ? $this->settings['force_lang'] : false;
            $this->msg['message'] = "";
            $this->msg['class'] = "";
            $this->supports = array(
                'products',
                'refunds'
                //'pre-orders'
            );
            if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
                /* 2.0.0 */
                add_action('woocommerce_api_' . strtolower(get_class($this)), array(
                    $this,
                    'check_fondy_response'
                ));
                add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(
                    $this,
                    'process_admin_options'
                ));
            } else {
                /* 1.6.6 */
                add_action('init', array(&$this, 'check_fondy_response'));
                add_action('woocommerce_update_options_payment_gateways', array(&$this, 'process_admin_options'));
            }
            if (isset($this->on_checkout_page) and $this->on_checkout_page == 'yes') {
                add_filter('woocommerce_order_button_html', array(&$this, 'custom_order_button_html'));
            }
            add_action('woocommerce_receipt_fondy', array(&$this, 'receipt_page'));
            add_action('wp_enqueue_scripts', array($this, 'fondy_checkout_scripts'));
        }

        /**
         * Process checkout func
         */
        function generate_ajax_order_fondy_info()
        {
            check_ajax_referer('fondy-submit-nonce', 'nonce_code');
            wc_maybe_define_constant('WOOCOMMERCE_CHECKOUT', true);
            WC()->checkout()->process_checkout();
            wp_die(0);
        }

        /**
         * Custom button order
         * @param $button
         * @return string
         */
        function custom_order_button_html($button)
        {
            $order_button_text = __('Place order', 'woocommerce');
            $js_event = "fondy_submit_order(event);";
            $button = '<button type="submit" onClick="' . esc_attr($js_event) . '" class="button alt" name="woocommerce_checkout_place_order" id="place_order" value="' . esc_attr($order_button_text) . '" data-value="' . esc_attr($order_button_text) . '" >' . esc_attr($order_button_text) . '</button>';

            return $button;
        }

        /**
         * Enqueue checkout page scripts
         */
        function fondy_checkout_scripts()
        {
            if (is_checkout()) {
                wp_enqueue_style('fondy-checkout', plugin_dir_url(__FILE__) . 'assets/css/fondy_styles.css');
                if (isset($this->on_checkout_page) and $this->on_checkout_page == 'yes') {
                    wp_enqueue_script('fondy_pay_v2', '//unpkg.com/ipsp-js-sdk@1.0.13/dist/checkout.min.js', array('jquery'), null, true);
                    wp_enqueue_script('fondy_pay_v2_woocom', plugin_dir_url(__FILE__) . 'assets/js/fondy.js', array('fondy_pay_v2'), '2.4.9', true);
                    wp_enqueue_script('fondy_pay_v2_card', plugin_dir_url(__FILE__) . 'assets/js/payform.min.js', array('fondy_pay_v2_woocom'), '2.4.9', true);
                    if (isset($this->force_lang) and $this->force_lang == 'yes') {
                        $endpoint = new WC_AJAX();
                        $endpoint = $endpoint::get_endpoint('checkout');
                    } else {
                        $endpoint = admin_url('admin-ajax.php');
                    }
                    wp_localize_script('fondy_pay_v2_woocom', 'fondy_info',
                        array(
                            'url' => $endpoint,
                            'nonce' => wp_create_nonce('fondy-submit-nonce')
                        )
                    );
                } else {
                    wp_enqueue_script('fondy_pay', '//api.fondy.eu/static_common/v1/checkout/ipsp.js', array(), null, false);
                }
            }
        }

        /**
         * Admin fields
         */
        function init_form_fields()
        {
            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('Enable/Disable', 'woocommerce-fondy'),
                    'type' => 'checkbox',
                    'label' => __('Enable Fondy Payment Module.', 'woocommerce-fondy'),
                    'default' => 'no',
                    'description' => 'Show in the Payment List as a payment option'
                ),
                'title' => array(
                    'title' => __('Title:', 'woocommerce-fondy'),
                    'type' => 'text',
                    'default' => __('Fondy Online Payments', 'woocommerce-fondy'),
                    'description' => __('This controls the title which the user sees during checkout.', 'woocommerce-fondy'),
                    'desc_tip' => true
                ),
                'description' => array(
                    'title' => __('Description:', 'woocommerce-fondy'),
                    'type' => 'textarea',
                    'default' => __('Pay securely by Credit or Debit Card or Internet Banking through fondy service.', 'woocommerce-fondy'),
                    'description' => __('This controls the description which the user sees during checkout.', 'woocommerce-fondy'),
                    'desc_tip' => true
                ),
                'merchant_id' => array(
                    'title' => __('Merchant ID', 'woocommerce-fondy'),
                    'type' => 'text',
                    'description' => __('Given to Merchant by fondy'),
                    'desc_tip' => true
                ),
                'salt' => array(
                    'title' => __('Merchant secretkey', 'woocommerce-fondy'),
                    'type' => 'text',
                    'description' => __('Given to Merchant by fondy', 'woocommerce-fondy'),
                    'desc_tip' => true
                ),
                'showlogo' => array(
                    'title' => __('Show Logo', 'woocommerce-fondy'),
                    'type' => 'checkbox',
                    'label' => __('Show the "fondy" logo in the Payment Method section for the user', 'woocommerce-fondy'),
                    'default' => 'yes',
                    'description' => __('Tick to show "fondy" logo', 'woocommerce-fondy'),
                    'desc_tip' => true
                ),
                'calendar' => array(
                    'title' => __('Show calndaer on checkout', 'woocommerce-fondy'),
                    'type' => 'checkbox',
                    'label' => __('Show recurring payment calendar on checkout', 'woocommerce-fondy'),
                    'default' => 'no',
                    'description' => __('Tick to show show recurring payment calendar on checkout', 'woocommerce-fondy'),
                    'desc_tip' => true
                ),
                'on_checkout_page' => array(
                    'title' => __('Enable on checkout page mode', 'woocommerce-fondy'),
                    'type' => 'checkbox',
                    'label' => __('Show payment on checkout page', 'woocommerce-fondy'),
                    'default' => 'no',
                    'description' => __('Show payment on checkout page', 'woocommerce-fondy'),
                    'desc_tip' => true
                ),
                'page_mode' => array(
                    'title' => __('Enable on page mode', 'woocommerce-fondy'),
                    'type' => 'checkbox',
                    'label' => __('Enable on page payment mode', 'woocommerce-fondy'),
                    'default' => 'no',
                    'description' => __('Enable on page mode without redirect', 'woocommerce-fondy'),
                    'desc_tip' => true
                ),
                'force_lang' => array(
                    'title' => __('Enable force detect lang', 'woocommerce-fondy'),
                    'type' => 'checkbox',
                    'label' => __('Enable detecting site lang if it used', 'woocommerce-fondy'),
                    'default' => 'no',
                    'description' => __('Enable detecting site lang if it used', 'woocommerce-fondy'),
                    'desc_tip' => true
                ),
                'redirect_page_id' => array(
                    'title' => __('Return Page', 'woocommerce-fondy'),
                    'type' => 'select',
                    'options' => $this->fondy_get_pages(__('Default order page', 'woocommerce-fondy')),
                    'description' => __('URL of success page', 'woocommerce-fondy'),
                    'desc_tip' => true
                ),
                'styles' => array(
                    'title' => __('Styles chekout page:', 'woocommerce-fondy'),
                    'type' => 'textarea',
                    'default' => __("'html, body' : {'overflow' : 'hidden'}"),
                    'description' => __('You can find example here http://jsfiddle.net/8h65h91t/', 'woocommerce-fondy'),
                    'desc_tip' => true
                )
            );
        }

        /**
         * Admin Panel Options
         **/
        public function admin_options()
        {
            echo '<h3>' . __('Fondy.eu', 'woocommerce-fondy') . '</h3>';
            echo '<p>' . __('Payment gateway', 'woocommerce-fondy') . '</p>';
            echo '<table class="form-table">';
            // Generate the HTML For the settings form.
            $this->generate_settings_html();
            echo '</table>';
        }

        /**
         * CCard fields on generating order
         */
        function payment_fields()
        {
            if ($this->description) {
                echo wpautop(wptexturize($this->description));
            }
            if (isset($this->on_checkout_page) and $this->on_checkout_page == 'yes') {
                ?>
                <form autocomplete="on" class="fondy-ccard" id="checkout_fondy_form">
                    <input type="hidden" name="payment_system" value="card">
                    <div class="f-container">
                        <div class="input-wrapper">
                            <div class="input-label w-1">
                                <?php esc_html_e('Card Number:', 'woocommerce-fondy') ?>
                            </div>
                            <div class="input-field w-1">
                                <input required type="tel" name="card_number" class="input fondy-credit-cart"
                                       id="fondy_ccard"
                                       autocomplete="cc-number"
                                       placeholder="<?php esc_html_e('XXXXXXXXXXXXXXXX', 'woocommerce-fondy') ?>"/>
                                <div id="f_card_sep"></div>
                            </div>
                        </div>
                        <div class="input-wrapper">
                            <div class="input-label w-3-2">
                                <?php esc_html_e('Expiry Date:', 'woocommerce-fondy') ?>
                            </div>
                            <div class="input-label w-4 w-rigth">
                                <?php esc_html_e('CVV2:', 'woocommerce-fondy') ?>
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
                                       placeholder="<?php esc_html_e('XXX', 'woocommerce-fondy') ?>"/>
                            </div>
                        </div>
                        <div style="display: none" class="input-wrapper stack-1">
                            <div class="input-field w-1">
                                <input id="submit_fondy_checkout_form" type="submit" class="button">
                                value="<?php esc_html_e('Pay', 'woocommerce-fondy') ?>"/>
                            </div>
                        </div>
                        <div class="error-wrapper"></div>
                    </div>
                </form>
                <?php
            }
        }

        /**
         * Order page
         * @param $order
         */
        function receipt_page($order)
        {
            echo $this->generate_fondy_form($order);
        }

        /**
         * filter empty var for signature
         * @param $var
         * @return bool
         */
        protected function fondy_filter($var)
        {
            return $var !== '' && $var !== null;
        }

        /**
         * Fondy signature generation
         * @param $data
         * @param $password
         * @param bool $encoded
         * @return string
         */
        protected function getSignature($data, $password, $encoded = true)
        {
            $data = array_filter($data, array($this, 'fondy_filter'));
            ksort($data);

            $str = $password;
            foreach ($data as $k => $v) {
                $str .= self::SIGNATURE_SEPARATOR . $v;
            }
            if ($encoded) {
                return sha1($str);
            } else {
                return $str;
            }
        }

        private function getProductInfo($order_id)
        {
            return __('Order: ', 'woocommerce-fondy') . $order_id;
        }

        /**
         * Generate checkout
         * @param $order_id
         * @return string
         */
        function generate_fondy_form($order_id)
        {
            $order = new WC_Order($order_id);
            $fondy_args = array(
                'order_id' => $order_id . self::ORDER_SEPARATOR . time(),
                'merchant_id' => $this->merchant_id,
                'order_desc' => $this->getProductInfo($order_id),
                'amount' => round($order->get_total() * 100),
                'currency' => get_woocommerce_currency(),
                'server_callback_url' => $this->getCallbackUrl(),
                'response_url' => $this->getCallbackUrl(),
                'lang' => $this->getLanguage(),
                'sender_email' => $this->getEmail($order)
            );
            if ($this->calendar == 'yes') {
                $fondy_args['required_rectoken'] = 'Y';
                $fondy_args['subscription'] = 'Y';
                $fondy_args['subscription_callback_url'] = $this->getCallbackUrl();
            }
            $fondy_args['signature'] = $this->getSignature($fondy_args, $this->salt);

            $out = '';
            if ($this->page_mode == 'no') {
                $fondy_args_array = array();
                foreach ($fondy_args as $key => $value) {
                    $fondy_args_array[] = "<input type='hidden' name='$key' value='$value'/>";
                }
                $out .= '  <form action="' . $this->liveurl . '" method="post" id="fondy_payment_form">
                    ' . implode('', $fondy_args_array) . '
                <input type="submit" id="submit_fondy_payment_form" value="' . __('Pay via Fondy.eu', 'woocommerce-fondy') . '" />';
            } else {
                $url = WC()->session->get('session_token_' . $order_id);
                if (empty($url)) {
                    $url = $this->get_checkout($fondy_args);
                    WC()->session->set('session_token_' . $order_id, $url);
                }
                $out = '
                    <div id="checkout">
                    <div id="checkout_wrapper"></div>
                    </div>';
                $out .= '
			    <script>
			    var checkoutStyles = {
			    		' . $this->styles . '
			    }
			    function checkoutInit(url) {
			    	$ipsp("checkout").scope(function() {
					this.setCheckoutWrapper("#checkout_wrapper");
					this.addCallback(__DEFAULTCALLBACK__);
					this.setCssStyle(checkoutStyles);
					this.action("show", function(data) {
						jQuery("#checkout_loader").remove();
						jQuery("#checkout").show();
					});
					this.action("hide", function(data) {
						jQuery("#checkout").hide();
					});
					this.action("resize", function(data) {
						jQuery("#checkout_wrapper").height(data.height);
						});
					this.loadUrl(url);
				});
				};
				checkoutInit("' . $url . '");
				</script>';
            }

            return $out;
        }

        /**
         * Request to api
         * @param $args
         * @return mixed
         */
        protected function get_checkout($args)
        {
            $conf = array(
                'redirection' => 2,
                'user-agent' => 'CMS Woocommerce',
                'headers' => array("Content-type" => "application/json;charset=UTF-8"),
                'body' => json_encode(array('request' => $args))
            );
            $response = wp_remote_post('https://api.fondy.eu/api/checkout/url/', $conf);

            $result = json_decode($response['body']);
            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code != 200) {
                $error = "Return code is {$response_code}";
                wp_die($error);
            }
            if ($result->response->response_status == 'failure') {
                wp_die($result->response->error_message);
            }
            $url = $result->response->checkout_url;
            return $url;
        }

        /*
         * Getting payment token for js ccrad
         */
        protected function get_token($args)
        {
            $conf = array(
                'redirection' => 2,
                'user-agent' => 'CMS Woocommerce',
                'headers' => array("Content-type" => "application/json;charset=UTF-8"),
                'body' => json_encode(array('request' => $args))
            );
            $response = wp_remote_post('https://api.fondy.eu/api/checkout/token/', $conf);
            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code != 200) {
                $error = "Return code is {$response_code}";
                return array('result' => 'failture', 'messages' => $error);
            }
            $result = json_decode($response['body']);
            if ($result->response->response_status == 'failure') {
                return array('result' => 'failture', 'messages' => $result->response->error_message);
            }
            $token = $result->response->token;
            return array('result' => 'success', 'token' => esc_attr($token));
        }

        /**
         * Process the payment and return the result
         * @param int $order_id
         * @return array|mixed
         */
        function process_payment($order_id)
        {
            $order = new WC_Order($order_id);

            if (version_compare(WOOCOMMERCE_VERSION, '2.1.0', '>=')) {
                /* 2.1.0 */
                $checkout_payment_url = $order->get_checkout_payment_url(true);
            } else {
                /* 2.0.0 */
                $checkout_payment_url = get_permalink(get_option('woocommerce_pay_page_id'));
            }
            if ($this->on_checkout_page == 'yes') {

                $fondy_args = array(
                    'order_id' => $order_id . self::ORDER_SEPARATOR . time(),
                    'merchant_id' => esc_attr($this->merchant_id),
                    'amount' => round($order->get_total() * 100),
                    'order_desc' => $this->getProductInfo($order_id),
                    'currency' => esc_attr(get_woocommerce_currency()),
                    'server_callback_url' => $this->getCallbackUrl(),
                    'response_url' => $this->getCallbackUrl(),
                    'lang' => esc_attr($this->getLanguage()),
                    'sender_email' => esc_attr($this->getEmail($order))
                );

                $fondy_args['signature'] = $this->getSignature($fondy_args, $this->salt);
                $token = WC()->session->get('session_token_' . md5($order_id . '_' . $fondy_args['amount'] . '_' . $fondy_args['currency']));

                if (empty($token)) {
                    $token = $this->get_token($fondy_args);
                    WC()->session->set('session_token_' . md5($order_id . '_' . $fondy_args['amount'] . '_' . $fondy_args['currency']), $token);
                }

                if ($token['result'] === 'success') {
                    return $token;
                } else {
                    wp_send_json($token);
                }
            } else {
                return array(
                    'result' => 'success',
                    'redirect' => add_query_arg('order_pay', $order_id, $checkout_payment_url)
                );
            }
        }

        /**
         * Refunds function
         * @param int $order_id
         * @param null $amount
         * @param string $reason
         * @return WP_Error
         */
        public function process_refund($order_id, $amount = null, $reason = '')
        {
            $order = new WC_Order($order_id);

            if (!$order or !$order->get_transaction_id()) {
                return new WP_Error('error', __('Refund failed: No transaction ID', 'woocommerce'));
            }
            $payment_id = $order->get_transaction_id();
            $data = array(
                'request' => array(
                    'amount' => round($amount * 100),
                    'order_id' => $payment_id,
                    'currency' => $order->get_currency(),
                    'merchant_id' => esc_sql($this->merchant_id),
                    'comment' => esc_attr($reason)
                )
            );
            $data['request']['signature'] = $this->getSignature($data['request'], esc_sql($this->salt));
            try {
                $args = array(
                    'redirection' => 2,
                    'user-agent' => 'CMS Woocommerce',
                    'headers' => array("Content-type" => "application/json;charset=UTF-8"),
                    'body' => json_encode($data)
                );
                $response = wp_remote_post($this->refundurl, $args);
                $fondy_response = json_decode($response['body'], TRUE);
                $fondy_response = $fondy_response['response'];
                if (isset($fondy_response['response_status']) and $fondy_response['response_status'] == 'success') {
                    switch ($fondy_response['reverse_status']) {
                        case 'approved':
                            return true;
                        case 'processing':
                            $order->add_order_note(__('Refund Fondy status: processing', 'woocommerce-fondy'));
                            return true;
                        case 'declined':
                            $order->add_order_note(__('Refund Fondy status: Declined', 'woocommerce-fondy'));
                            return new WP_Error('error', __('Refund Fondy status: Declined', 'woocommerce-fondy'), 'woocommerce-fondy');
                        default:
                            $order->add_order_note(__('Refund Fondy status: Unknown', 'woocommerce-fondy'));
                            return new WP_Error('error', __('Refund Fondy status: Unknown. Try to contact support', 'woocommerce-fondy'), 'woocommerce-fondy');
                    }
                } else {
                    return new WP_Error('error', __($fondy_response['error_code'] . '. ' . $fondy_response['error_message'], 'woocommerce-fondy'));
                }
            } catch (Exception $e) {
                return new WP_Error('error', __($e->getMessage(), 'woocommerce-fondy'));
            }
        }

        /**
         * Answer Url
         * @return string
         */
        private function getCallbackUrl()
        {
            if (isset($this->force_lang) and $this->force_lang == 'yes') {
                $site_url = get_home_url();
            } else {
                $site_url = get_site_url() . "/";
            }

            $redirect_url = ($this->redirect_page_id == "" || $this->redirect_page_id == 0) ? $site_url : get_permalink($this->redirect_page_id);

            //For wooCoomerce 2.0
            return add_query_arg('wc-api', get_class($this), $redirect_url);
        }

        /**
         * Site lang cropped
         * @return string
         */
        private function getLanguage()
        {
            return substr(get_bloginfo('language'), 0, 2);
        }

        /**
         * Order Email
         * @param $order
         * @return string
         */
        private function getEmail($order)
        {
            $current_user = wp_get_current_user();
            $email = $current_user->user_email;

            if (empty($email)) {
                $order_data = $order->get_data();
                $email = $order_data['billing']['email'];
            }

            return $email;
        }

        /**
         * Validation responce
         * @param $response
         * @return bool
         *
         */
        protected function isPaymentValid($response)
        {
            global $woocommerce;

            list($orderId,) = explode(self::ORDER_SEPARATOR, $response['order_id']);
            $order = new WC_Order($orderId);
            $total = round($order->get_total() * 100);
            if ($order === false) {
                return __('An error has occurred during payment. Please contact us to ensure your order has submitted.', 'woocommerce-fondy');
            }
            if ($response['amount'] != $total) {
                return __('Amount incorrect.', 'woocommerce-fondy');
            }
            if ($this->merchant_id != $response['merchant_id']) {
                return __('An error has occurred during payment. Merchant data is incorrect.', 'woocommerce-fondy');
            }

            $responseSignature = $response['signature'];
            if (isset($response['response_signature_string'])) {
                unset($response['response_signature_string']);
            }
            if (isset($response['signature'])) {
                unset($response['signature']);
            }

            if ($this->getSignature($response, $this->salt) != $responseSignature) {
                $order->update_status('failed');
                $order->add_order_note(__('Transaction ERROR: signature is not valid', 'woocommerce-fondy'));

                return __('An error has occurred during payment. Signature is not valid.', 'woocommerce-fondy');
            }

            if ($response['order_status'] == self::ORDER_DECLINED) {
                $errorMessage = __("Thank you for shopping with us. However, the transaction has been declined.", 'woocommerce-fondy');
                $order->add_order_note('Transaction ERROR: order declined<br/>Fondy ID: ' . $response['payment_id']);
                $order->update_status('failed');

                wp_mail($response['sender_email'], 'Order declined', $errorMessage);

                return $errorMessage;
            }

            if ($response['order_status'] == 'expired') {
                $errorMessage = __("Thank you for shopping with us. However, the transaction has been expired.", 'woocommerce-fondy');
                $order->add_order_note('Transaction ERROR: order expired<br/>Fondy ID: ' . $response['payment_id']);
                $order->update_status('cancelled');

                return $errorMessage;
            }

            if ($response['tran_type'] == 'purchase' and $response['order_status'] != self::ORDER_APPROVED) {
                $this->msg['class'] = 'woocommerce-error';
                $this->msg['message'] = __("Thank you for shopping with us. But your payment declined.", 'woocommerce-fondy');
                $order->add_order_note("Fondy order status: " . $response['order_status']);
            }

            if ($response['tran_type'] == 'purchase' and $response['order_status'] == self::ORDER_APPROVED and $total == $response['amount']) {
                $order->payment_complete($response['order_id']);
                $order->add_order_note('Fondy payment successful.<br/>fondy ID: ' . ' (' . $response['payment_id'] . ')');
            } elseif ($total != $response['amount']) {
                $order->add_order_note('Transaction ERROR: amount incorrect<br/>Fondy ID: ' . $response['payment_id']);
                $order->update_status('failed');
            }
            WC()->session->__unset('session_token_' . $orderId);
            WC()->session->__unset('session_token_' . md5($orderId . '_' . $total . '_' . $response['currency']));
            $woocommerce->cart->empty_cart();

            return true;
        }

        /**
         * Response Handler
         */
        function check_fondy_response()
        {
            if (empty($_POST)) {
                $callback = json_decode(file_get_contents("php://input"));
                if (empty($callback)) {
                    die('go away!');
                }
                $_POST = array();
                foreach ($callback as $key => $val) {
                    $_POST[esc_sql($key)] = esc_sql($val);
                }
            }
            list($orderId,) = explode(self::ORDER_SEPARATOR, $_POST['order_id']);
            $order = new WC_Order($orderId);
            $paymentInfo = $this->isPaymentValid($_POST);
            if ($paymentInfo === true and $_POST['order_status'] == 'reversed') {
                $order->add_order_note(__('Refund Fondy status: ' . esc_sql($_POST['order_status']) . ', Refund payment id: ' . esc_sql($_POST['payment_id']), 'woocommerce-fondy'));
                die('Order Reversed');
            }
            if ($paymentInfo === true and !$order->is_paid()) {
                if ($_POST['order_status'] == self::ORDER_APPROVED) {
                    $this->msg['message'] = __("Thank you for shopping with us. Your account has been charged and your transaction is successful.", 'woocommerce-fondy');
                }
                $this->msg['class'] = 'woocommerce-message';
            } else {
                $this->msg['class'] = 'error';
                $this->msg['message'] = $paymentInfo;
                $order->add_order_note("ERROR: " . $paymentInfo);
            }
            if ($this->redirect_page_id == "" || $this->redirect_page_id == 0) {
                $redirect_url = $order->get_checkout_order_received_url();
            } else {
                $redirect_url = get_permalink($this->redirect_page_id);
                if ($this->msg['class'] == 'woocommerce-error' or $this->msg['class'] == 'error') {
                    wc_add_notice($this->msg['message'], 'error');
                } else {
                    wc_add_notice($this->msg['message']);
                }
            }
            wp_redirect($redirect_url);
            exit;
        }

        // get all pages
        function fondy_get_pages($title = false, $indent = true)
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
    }

    /**
     * Add the Gateway to WooCommerce
     **/
    function woocommerce_add_fondy_gateway($methods)
    {
        $methods[] = 'WC_fondy';
        return $methods;
    }

    add_action('wp_ajax_nopriv_generate_ajax_order_fondy_info', array(
        'WC_fondy',
        'generate_ajax_order_fondy_info'
    ), 99);
    add_action('wp_ajax_generate_ajax_order_fondy_info', array(
        'WC_fondy',
        'generate_ajax_order_fondy_info'
    ), 99);
    add_filter('woocommerce_payment_gateways', 'woocommerce_add_fondy_gateway');
}
