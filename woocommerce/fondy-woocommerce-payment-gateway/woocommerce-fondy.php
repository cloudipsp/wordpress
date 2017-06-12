<?php
/*
Plugin Name: WooCommerce - Fondy payment gateway
Plugin URI: https://fondy.eu
Description: Fondy Payment Gateway for WooCommerce.
Version: 1.0
Author: DM
Author URI: https://fondy.eu/
Domain Path: /
Text Domain: woocommerce-fondy
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/
add_action("init", "fondy");
function fondy()
{
    load_plugin_textdomain("woocommerce-fondy", false, basename(dirname(__FILE__)));
}

add_action('plugins_loaded', 'woocommerce_fondy_init', 0);
define('IMGDIR', WP_PLUGIN_URL . "/" . plugin_basename(dirname(__FILE__)) . '/assets/img/');

function woocommerce_fondy_init()
{

    //load_plugin_textdomain("woocommerce_fondy", false, basename(dirname(__FILE__)));
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    if (isset($_GET['msg']) && !empty($_GET['msg'])) {
        add_action('the_content', 'showfondyMessage');
    }
    function showfondyMessage($content)
    {
        return '<div class="' . wp_kses_post($_GET['type']) . '">' . wp_kses_post($_GET['msg']) . '</div>' . $content;
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
            $this->title = $this->settings['title'];
            $this->redirect_page_id = $this->settings['redirect_page_id'];
            $this->merchant_id = $this->settings['merchant_id'];
            $this->salt = $this->settings['salt'];
            $this->styles = $this->settings['styles'];
            $this->description = $this->settings['description'];
            $this->page_mode = $this->settings['page_mode'];
            $this->msg['message'] = "";
            $this->msg['class'] = "";

            if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
                /* 2.0.0 */
                add_action('woocommerce_api_' . strtolower(get_class($this)), array($this, 'check_fondy_response'));
                add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            } else {
                /* 1.6.6 */
                add_action('init', array(&$this, 'check_fondy_response'));
                add_action('woocommerce_update_options_payment_gateways', array(&$this, 'process_admin_options'));
            }

            add_action('woocommerce_receipt_fondy', array(&$this, 'receipt_page'));
            add_action('wp_enqueue_scripts', array($this, 'fondy_checkout_scripts'));
        }

        /**
         * Enqueue checkout page scripts
         */
        function fondy_checkout_scripts()
        {
            if (is_checkout()) {
                wp_enqueue_style('fondy-checkout', plugin_dir_url(__FILE__) . 'assets/css/fondy_styles.css');
                wp_enqueue_script('fondy_pay', '//api.fondy.eu/static_common/v1/checkout/ipsp.js', array(), null, false);
            }
        }

        function init_form_fields()
        {
            $this->form_fields = array('enabled' => array('title' => __('Enable/Disable', 'woocommerce-fondy'),
                'type' => 'checkbox',
                'label' => __('Enable Fondy Payment Module.', 'woocommerce-fondy'),
                'default' => 'no',
                'description' => 'Show in the Payment List as a payment option'),
                'title' => array('title' => __('Title:', 'woocommerce-fondy'),
                    'type' => 'text',
                    'default' => __('Fondy Online Payments', 'woocommerce-fondy'),
                    'description' => __('This controls the title which the user sees during checkout.', 'woocommerce-fondy'),
                    'desc_tip' => true),
                'description' => array('title' => __('Description:', 'woocommerce-fondy'),
                    'type' => 'textarea',
                    'default' => __('Pay securely by Credit or Debit Card or Internet Banking through fondy service.', 'woocommerce-fondy'),
                    'description' => __('This controls the description which the user sees during checkout.', 'woocommerce-fondy'),
                    'desc_tip' => true),
                'merchant_id' => array('title' => __('Merchant KEY', 'woocommerce-fondy'),
                    'type' => 'text',
                    'description' => __('Given to Merchant by fondy'),
                    'desc_tip' => true),
                'salt' => array('title' => __('Merchant secretkey', 'woocommerce-fondy'),
                    'type' => 'text',
                    'description' => __('Given to Merchant by fondy', 'woocommerce-fondy'),
                    'desc_tip' => true),
                'showlogo' => array('title' => __('Show Logo', 'woocommerce-fondy'),
                    'type' => 'checkbox',
                    'label' => __('Show the "fondy" logo in the Payment Method section for the user', 'woocommerce-fondy'),
                    'default' => 'yes',
                    'description' => __('Tick to show "fondy" logo', 'woocommerce-fondy'),
                    'desc_tip' => true),
                'page_mode' => array('title' => __('Enable on page mode', 'woocommerce-fondy'),
                    'type' => 'checkbox',
                    'label' => __('Enable on page payment mode', 'woocommerce-fondy'),
                    'default' => 'no',
                    'description' => __('Enable on page mode without redirect', 'woocommerce-fondy'),
                    'desc_tip' => true),
                'redirect_page_id' => array('title' => __('Return Page', 'woocommerce-fondy'),
                    'type' => 'select',
                    'options' => $this->fondy_get_pages('Select Page'),
                    'description' => __('URL of success page', 'woocommerce-fondy'),
                    'desc_tip' => true),
                'styles' => array('title' => __('Styles chekout page:', 'woocommerce-fondy'),
                    'type' => 'textarea',
                    'default' => __("'html , body' : {
														  'overflow' : 'hidden'
														   },"),
                    'description' => __('You can find example here http://jsfiddle.net/8h65h91t/', 'woocommerce-fondy'),
                    'desc_tip' => true));
        }

        /**
         * Admin Panel Options
         * - Options for bits like 'title' and availability on a country-by-country basis
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
         *  There are no payment fields for techpro, but we want to show the description if set.
         **/
        function payment_fields()
        {
            if ($this->description) {
                echo wpautop(wptexturize($this->description));
            }
        }

        /**
         * Receipt Page
         **/
        function receipt_page($order)
        {
            //echo '<p>' . __('Thank you for your order, please click the button below to pay with fondy.', 'woocommerce-fondy') . '</p>';
            echo $this->generate_fondy_form($order);
        }

        protected function fondy_filter($var)
        {
            return $var !== '' && $var !== null;
        }

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
            return "Order: $order_id";
        }

        /**
         * Generate payu button link
         **/
        function generate_fondy_form($order_id)
        {
            $order = new WC_Order($order_id);

            $fondy_args = array(
				'order_id' => $order_id . self::ORDER_SEPARATOR . time(),
                'merchant_id' => $this->merchant_id,
                'order_desc' => $this->getProductInfo($order_id),
                'amount' => round($order->order_total*100),
                'currency' => get_woocommerce_currency(),
                'server_callback_url' => $this->getCallbackUrl(),
                'response_url' => $this->getCallbackUrl(),
                'lang' => $this->getLanguage(),
                'sender_email' => $this->getEmail($order));
			$fondy_args['signature'] =  $this->getSignature($fondy_args, $this->salt);
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, 'https://api.fondy.eu/api/checkout/url/');
			curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
			curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: application/json'));
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(array('request'=>$fondy_args)));
			$result = json_decode(curl_exec($ch));
			if ($result->response->response_status == 'failure'){
				echo $result->response->error_message;
				exit;
			}
            $out = '
			<div id="checkout">
			<div id="checkout_wrapper"></div>
			</div>';
            if ($this->page_mode == 'no') {

                $fondy_args['amount'] = round($order->order_total * 100);
                $fondy_args['signature'] = $this->getSignature($fondy_args, $this->salt);
                foreach ($fondy_args as $key => $value) {
                    $fondy_args_array[] = "<input type='hidden' name='$key' value='$value'/>";
                }
                $out .= '  <form action="' . $this->liveurl . '" method="post" id="fondy_payment_form">
                    ' . implode('', $fondy_args_array) . '
                <input type="submit" id="submit_fondy_payment_form" value="' . __('Pay via Fondy.eu', 'woocommerce-fondy') . '" />';
            } else {

                $fondy_args['amount'] = $order->order_total;
                $fondy_args['signature'] = $this->getSignature($fondy_args, $this->salt);
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
				checkoutInit("' . $result->response->checkout_url . '");
				</script>';
            }
            return $out;
        }

        /**
         * Process the payment and return the result
         **/
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
            return array('result' => 'success',
                'redirect' => add_query_arg('order_pay', $order->id, $checkout_payment_url));
        }

        private function getCallbackUrl()
        {
            $redirect_url = ($this->redirect_page_id == "" || $this->redirect_page_id == 0) ? get_site_url() . "/" : get_permalink($this->redirect_page_id);
            //For wooCoomerce 2.0
            return add_query_arg('wc-api', get_class($this), $redirect_url);
        }

        private function getLanguage()
        {
            return substr(get_bloginfo('language'), 0, 2);
        }

        private function getEmail($order)
        {
            $current_user = wp_get_current_user();
            $email = $current_user->user_email;

            if (empty($email)) {
                $email = $order->billing_email;
            }

            return $email;
        }

        protected function isPaymentValid($response)
        {
            global $woocommerce;

            list($orderId,) = explode(self::ORDER_SEPARATOR, $response['order_id']);
            $order = new WC_Order($orderId);
            if ($order === FALSE) {
                return __('An error has occurred during payment. Please contact us to ensure your order has submitted.', 'woocommerce-fondy');
            }

            if ($this->merchant_id != $response['merchant_id']) {
                $order->update_status('failed');
                return __('An error has occurred during payment. Merchant data is incorrect.', 'woocommerce-fondy');
            }

            if ($response['order_status'] == self::ORDER_DECLINED) {
                $errorMessage = __("Thank you for shopping with us. However, the transaction has been declined.", 'woocommerce-fondy');
                $order->add_order_note('Transaction ERROR: order declined<br/>Fondy ID: ' . $_REQUEST['payment_id']);
                $order->update_status('failed');

                wp_mail($_REQUEST['sender_email'], 'Order declined', $errorMessage);

                return $errorMessage;
            }
            $responseSignature = $_POST['signature'];
            if (isset($_POST['response_signature_string'])) {
                unset($_POST['response_signature_string']);
            }
            if (isset($_POST['signature'])) {
                unset($_POST['signature']);
            }
            if ($this->getSignature($_POST, $this->salt) != $responseSignature) {
                $order->update_status('failed');
                $order->add_order_note(__('Transaction ERROR: signature is not valid', 'woocommerce-fondy'));
                return __('An error has occurred during payment. Signature is not valid.', 'woocommerce-fondy');
            }

            if ($response['order_status'] != self::ORDER_APPROVED) {
                $this->msg['class'] = 'woocommerce-error';
                $this->msg['message'] = __("Thank you for shopping with us. But your payment declined.", 'woocommerce-fondy');
                $order->update_status('processing');
                $order->add_order_note("Order status:" . $response['order_status']);
            }

            if ($response['order_status'] == self::ORDER_APPROVED) {
                $order->update_status('completed');
                $order->payment_complete();
                $order->add_order_note('Fondy payment successful.<br/>fondy ID: ' . ' (' . $_REQUEST['payment_id'] . ')');
            }

            $woocommerce->cart->empty_cart();

            return true;
        }

        function check_fondy_response()
        {
			global $woocommerce;
            if (empty($_REQUEST)) {
                $callback = json_decode(file_get_contents("php://input"));
                $_REQUEST = array();
                foreach ($callback as $key => $val) {
                    $_REQUEST[$key] = $val;
                }
            }
            $paymentInfo = $this->isPaymentValid($_REQUEST);
            if ($paymentInfo == true) {
                if ($_REQUEST['order_status'] == self::ORDER_APPROVED) {
                    $this->msg['message'] = __("Thank you for shopping with us. Your account has been charged and your transaction is successful.", 'woocommerce-fondy');
                }
                $this->msg['class'] = 'woocommerce-message';
            } else {
                $this->msg['class'] = 'error';
                $this->msg['message'] = $paymentInfo;
            }
			

            list($orderId,) = explode(self::ORDER_SEPARATOR, $_REQUEST['order_id']);
            $order = new WC_Order($orderId);
			if ($this->redirect_page_id == "" || $this->redirect_page_id == 0){
				$redirect_url = $this->get_return_url( $order );			
			}else{
				$redirect_url = get_permalink($this->redirect_page_id);          
				$redirect_url = add_query_arg(array('msg' => urlencode($this->msg['message']),
                'type' => $this->msg['class']), $redirect_url);
			}
            wp_redirect($redirect_url);
            exit;
        }

        /*
        //Removed For WooCommerce 2.0
        function showMessage($content){
            return '<div class="box '.$this->msg['class'].'">'.$this->msg['message'].'</div>'.$content;
        }
        */

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
                        $next_page = get_page($has_parent);
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

    add_filter('woocommerce_payment_gateways', 'woocommerce_add_fondy_gateway');
}
