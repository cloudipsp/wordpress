<?php
/*
Plugin Name: WooCommerce - Oplata Money
Plugin URI: http://...
Description: Oplata Money Payment Gateway for WooCommerce.
Version: 1.0
Author: oplata.com
Author URI: http://oplata.com/
License: GNU General Public License v3.0
License URI: http://www.gnu.org/licenses/gpl-3.0.html
*/

add_action('plugins_loaded', 'woocommerce_oplata_init', 0);
define('IMGDIR', WP_PLUGIN_URL . "/" . plugin_basename(dirname(__FILE__)) . '/assets/img/');

function woocommerce_oplata_init()
{
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    if (isset($_GET['msg']) && !empty($_GET['msg'])) {
        add_action('the_content', 'showOplataMessage');
    }
    function showOplataMessage($content)
    {
        return '<div class="' . htmlentities($_GET['type']) . '">' . htmlentities(urldecode($_GET['msg'])) . '</div>' . $content;
    }

    /**
     * Gateway class
     */
    class WC_oplata extends WC_Payment_Gateway
    {
        const ORDER_APPROVED = 'approved';
        const ORDER_DECLINED = 'declined';

        const SIGNATURE_SEPARATOR = '|';

        const ORDER_SEPARATOR = ":";

        protected static $responseFields = array('rrn',
            'masked_card',
            'sender_cell_phone',
            'response_status',
            'currency',
            'fee',
            'reversal_amount',
            'settlement_amount',
            'actual_amount',
            'order_status',
            'response_description',
            'order_time',
            'actual_currency',
            'order_id',
            'tran_type',
            'eci',
            'settlement_date',
            'payment_system',
            'approval_code',
            'merchant_id',
            'settlement_currency',
            'payment_id',
            'sender_account',
            'card_bin',
            'response_code',
            'card_type',
            'amount',
            'sender_email');


        public function __construct()
        {
            $this->id = 'oplata';
            $this->method_title = 'Oplata';
            $this->method_description = "Payment gateway";
            $this->has_fields = false;
            $this->init_form_fields();
            $this->init_settings();
            if ($this->settings['showlogo'] == "yes") {
                $this->icon = IMGDIR . 'logo.png';
            }
            $this->title = $this->settings['title'];
            $this->redirect_page_id = $this->settings['redirect_page_id'];

            $this->liveurl = 'https://api.oplata.com/api/checkout/redirect/';
            $this->merchant_id = $this->settings['merchant_id'];
            $this->salt = $this->settings['salt'];
            $this->description = $this->settings['description'];

            $this->msg['message'] = "";
            $this->msg['class'] = "";

            add_action('init', array(&$this,
                                     'check_oplata_response'));
            //update for woocommerce >2.0
            add_action('woocommerce_api_' . strtolower(get_class($this)), array($this,
                                                                                'check_oplata_response'));

            if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
                /* 2.0.0 */
                add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(&$this,
                                                                                             'process_admin_options'));
            } else {
                /* 1.6.6 */
                add_action('woocommerce_update_options_payment_gateways', array(&$this,
                                                                                'process_admin_options'));
            }

            add_action('woocommerce_receipt_oplata', array(&$this,
                                                           'receipt_page'));
        }

        function init_form_fields()
        {
            $this->form_fields = array('enabled' => array('title' => __('Enable/Disable', 'kdc'),
                                                          'type' => 'checkbox',
                                                          'label' => __('Enable Oplata Payment Module.', 'kdc'),
                                                          'default' => 'no',
                                                          'description' => 'Show in the Payment List as a payment option'),
                                       'title' => array('title' => __('Title:', 'kdc'),
                                                        'type' => 'text',
                                                        'default' => __('Online Payments', 'kdc'),
                                                        'description' => __('This controls the title which the user sees during checkout.', 'kdc'),
                                                        'desc_tip' => true),
                                       'description' => array('title' => __('Description:', 'kdc'),
                                                              'type' => 'textarea',
                                                              'default' => __('Pay securely by Credit or Debit Card or Internet Banking through Oplata.com service.', 'kdc'),
                                                              'description' => __('This controls the description which the user sees during checkout.', 'kdc'),
                                                              'desc_tip' => true),
                                       'merchant_id' => array('title' => __('Merchant KEY', 'kdc'),
                                                              'type' => 'text',
                                                              'description' => __('Given to Merchant by Oplata.com'),
                                                              'desc_tip' => true),
                                       'salt' => array('title' => __('Merchant SALT', 'kdc'),
                                                       'type' => 'text',
                                                       'description' => __('Given to Merchant by Oplata.com', 'kdc'),
                                                       'desc_tip' => true),
                                       'showlogo' => array('title' => __('Show Logo', 'kdc'),
                                                           'type' => 'checkbox',
                                                           'label' => __('Show the "Oplata.com" logo in the Payment Method section for the user', 'kdc'),
                                                           'default' => 'yes',
                                                           'description' => __('Tick to show "Oplata.com" logo'),
                                                           'desc_tip' => true),
                                       'redirect_page_id' => array('title' => __('Return Page'),
                                                                   'type' => 'select',
                                                                   'options' => $this->oplata_get_pages('Select Page'),
                                                                   'description' => __('URL of success page', 'kdc'),
                                                                   'desc_tip' => true));
        }

        /**
         * Admin Panel Options
         * - Options for bits like 'title' and availability on a country-by-country basis
         **/
        public function admin_options()
        {
            echo '<h3>' . __('Oplata.com', 'kdc') . '</h3>';
            echo '<p>' . __('Payment gateway') . '</p>';
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
            echo '<p>' . __('Thank you for your order, please click the button below to pay with Oplata.', 'kdc') . '</p>';
            echo $this->generate_oplata_form($order);
        }

        protected function getSignature($data, $password, $encoded = true)
        {
            $data = array_filter($data, function($var) {
                return $var !== '' && $var !== null;
            });
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
        function generate_oplata_form($order_id)
        {
            $order = new WC_Order($order_id);

            $oplata_args = array('order_id' => $order_id . self::ORDER_SEPARATOR . time(),
                                 'merchant_id' => $this->merchant_id,
                                 'order_desc' => $this->getProductInfo($order_id),
                                 'amount' => $this->getAmount($order),
                                 'currency' => get_woocommerce_currency(),
                                 'server_callback_url' => $this->getCallbackUrl(),
                                 'response_url' => $this->getCallbackUrl(),
                                 'lang' => $this->getLanguage(),
                                 'sender_email' => $this->getEmail($order));

            $oplata_args['signature'] = $this->getSignature($oplata_args, $this->salt);


            $oplata_args_array = array();
            foreach ($oplata_args as $key => $value) {
                $oplata_args_array[] = "<input type='hidden' name='$key' value='$value'/>";
            }

            return '	<form action="' . $this->liveurl . '" method="post" id="oplata_payment_form">
  				' . implode('', $oplata_args_array) . '
				<input type="submit" class="button-alt" id="submit_oplata_payment_form" value="' . __('Pay via Oplata.com', 'kdc') . '" /> <a class="button cancel" href="' . $order->get_cancel_order_url() . '">' . __('Cancel order &amp; restore cart', 'kdc') . '</a>
					<script type="text/javascript">
					jQuery(function(){
					jQuery("body").block({
						message: "' . __('Thank you for your order. We are now redirecting you to Payment Gateway to make payment.', 'kdc') . '",
						overlayCSS: {
							background		: "#fff",
							opacity			: 0.6
						},
						css: {
							padding			: 20,
							textAlign		: "center",
							color			: "#555",
							border			: "3px solid #aaa",
							backgroundColor	: "#fff",
							cursor			: "wait",
							lineHeight		: "32px"
						}
					});
					jQuery("#submit_oplata_payment_form").click();
					});
					</script>
				</form>';
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
                         'redirect' => add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, $checkout_payment_url)));
        }

        private function getCallbackUrl()
        {
            $redirect_url = ($this->redirect_page_id == "" || $this->redirect_page_id == 0) ? get_site_url() . "/" : get_permalink($this->redirect_page_id);
            //For wooCoomerce 2.0
            return add_query_arg('wc-api', get_class($this), $redirect_url);
        }

        private function getAmount($order)
        {
            $localeInfo = localeconv();
            return strpos("{$order->order_total}", $localeInfo['decimal_point'])
                ? str_replace($localeInfo['decimal_point'], "", "{$order->order_total}")
                : "{$order->order_total}00";
        }

        private function getLanguage()
        {
            return substr(get_bloginfo ( 'language' ), 0, 2);
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
                return 'An error has occurred during payment. Please contact us to ensure your order has submitted.';
            }

            if ($this->merchant_id != $response['merchant_id']) {
                $order->update_status('failed');
                return 'An error has occurred during payment. Merchant data is incorrect.';
            }

            if ($response['order_status'] == self::ORDER_DECLINED) {
                $errorMessage = "Thank you for shopping with us. However, the transaction has been declined.";
                $order->add_order_note('Transaction ERROR: order declined<br/>Oplata.com ID: '.$_REQUEST['payment_id']);
                $order->update_status('failed');

                wp_mail($_REQUEST['sender_email'], 'Order declined', $errorMessage);

                return $errorMessage;
            }

            $responseSignature = $response['signature'];
            foreach ($response as $k => $v) {
                if (!in_array($k, self::$responseFields)) {
                    unset($response[$k]);
                }
            }

            if ($this->getSignature($response, $this->salt) != $responseSignature) {
                $order->update_status('failed');
                $order->add_order_note('Transaction ERROR: signature is not valid');
                return 'An error has occurred during payment. Signature is not valid.';
            }

            if ($response['order_status'] != self::ORDER_APPROVED) {
                $this->msg['class'] = 'woocommerce-error';
                $this->msg['message'] = "Thank you for shopping with us. Your payment is processing. We will inform you about results.";
                $order->update_status('processing');
                $order->add_order_note("Order status: {$response['order_status']}");
            }


            if ($response['order_status'] == self::ORDER_APPROVED) {
                $order->update_status('completed');
                $order->payment_complete();
                $order->add_order_note('Oplata.com payment successful.<br/>Oplata.com ID: ' . ' (' . $_REQUEST['payment_id'] . ')');
            }

            $woocommerce->cart->empty_cart();

            return true;
        }

        function check_oplata_response()
        {
            $paymentInfo = $this->isPaymentValid($_REQUEST);
            if ($paymentInfo === true) {
                $this->msg['message'] = "Thank you for shopping with us. Your account has been charged and your transaction is successful.";
                $this->msg['class'] = 'woocommerce-message';
            } else {
                $this->msg['class'] = 'error';
                $this->msg['message'] = $paymentInfo;
            }

            $redirect_url = ($this->redirect_page_id == "" || $this->redirect_page_id == 0) ? get_site_url() . "/" : get_permalink($this->redirect_page_id);
            //For wooCoomerce 2.0
            $redirect_url = add_query_arg(array('msg' => urlencode($this->msg['message']),
                                                'type' => $this->msg['class']), $redirect_url);

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
        function oplata_get_pages($title = false, $indent = true)
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
    function woocommerce_add_oplata_gateway($methods)
    {
        $methods[] = 'WC_oplata';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'woocommerce_add_oplata_gateway');
}
