<?php
/*
Plugin Name: EDD Fondy Payment
Plugin URI: https://fondy.eu
Description: Fondy Payment integration for EDD plugin
Version: 1.0.2
Domain Path: /languages
Text Domain: edd_fondy
Author: FONDY - Unified Payment Platform
Author URI: https://fondy.eu/
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
EDD requires at least: 2.0.0
EDD tested up to: 2.9.24
*/

if (!class_exists('EDD_Fondy')) :

    class EDD_Fondy
    {

        /**
         * @var EDD_Fondy Class
         */
        private static $instance;
        /**
         * @var
         */
        public $file;

        /**
         * @var string
         */
        public $plugin_path;

        /**
         * @var string
         */
        public $plugin_url;
        /**
         * @var string
         */
        public $fondy_url;
        /**
         * @var string
         */
        public $version;

        /**
         * @return EDD_Fondy
         */
        public static function instance()
        {
            if (!isset(self::$instance)) {
                self::$instance = new EDD_Fondy(__FILE__);
            }
            return self::$instance;
        }

        /**
         * EDD_Fondy constructor.
         * @param $file
         */
        private function __construct($file)
        {

            $this->version = '1.0.2';
            $this->file = $file;
            $this->plugin_url = trailingslashit(plugins_url('', $plugin = $file));
            $this->plugin_path = trailingslashit(dirname($file));
            $this->fondy_url = 'https://api.fondy.eu/api/checkout/url/';

            if (!function_exists('json_decode')) {
                if (is_admin())
                    add_action('admin_notices', array(&$this, 'initialization_warning'));
                return;
            }
            /* Hooks */
            if (is_admin())
                add_filter('edd_settings_gateways', array(&$this, 'add_settings_gateways'));

            add_filter('edd_accepted_payment_icons', array(&$this, 'fondy_payment_icon'));
            add_filter('edd_payment_gateways', array(&$this, 'register_gateway'));
            add_action('edd_fondy_cc_form', array(&$this, 'gateway_cc_form'));
            add_action('edd_gateway_fondy', array(&$this, 'process_payment'));
            add_action('init', array(&$this, 'validate_report_back')); // notify from fondy gateway
            add_action('edd_fondy_check', array(&$this, 'process_fondy_notify'));

        }

        /**
         * json and curl extension required
         */
        public function initialization_warning()
        {
            echo '<div id="edd-fondy-warning" class="updated fade"><p><strong>' . sprintf(__('%s PHP library not installed.', 'edd-fondy'), 'JSON') . '</strong> ';
            echo sprintf(__('EDD Fondy Payment Gateway plugin will not function without <a href="%s">PHP JSON functions</a> enabled. Please update your version of WordPress for improved compatibility and/or enable native JSON support for PHP.'), 'http://php.net/manual/book.json.php');
            echo '</p></div>';
        }

        /**
         * true true...
         */
        public static function gateway_cc_form()
        {
            return;
        }

        /**
         * @param $icons
         * @return mixed
         */
        public function fondy_payment_icon($icons)
        {
            $icons[$this->plugin_url . 'assets/images/fondy_logo.png'] = 'Fondy Payment Provider';

            return $icons;
        }

        /**
         * @param array $headers
         * @param array $body
         * @return bool
         */
        private function fondy_post($headers = array(), $body = array())
        {

            $response = wp_remote_post($this->fondy_url,
                array(
                    'redirection' => 5,
                    'httpversion' => '1.0',
                    'user-agent' => 'EDD Fondy' . $this->version . '; WordPress (' . home_url('/') . ')',
                    'timeout' => 15,
                    'body' => $body,
                    'headers' => $headers
                )
            );

            if (!is_wp_error($response) && $response['response']['code'] == 200) {
                return $response['body'];
            } else {
                return false;
            }
        }

        /**
         * @param $gateways
         * @return mixed
         */
        public function register_gateway($gateways)
        {

            $gateways['fondy'] = array('admin_label' => __('Fondy', 'edd_fondy'), 'checkout_label' => __('Fondy Payments', 'edd_fondy'));

            return $gateways;
        }

        /**
         * Settings Gateway
         * @param $settings
         * @return array
         */
        public function add_settings_gateways($settings)
        {

            $edd_fondy_settings = array(

                array(
                    'id' => '_edd_fondy_gateway_settings',
                    'name' => '<strong>' . __('Fondy Payment Gateway Settings', 'edd_fondy') . '</strong>',
                    'desc' => __('Configure the gateway settings', 'edd_fondy'),
                    'type' => 'header'
                ),
                array(
                    'id' => 'edd_fondy_merchant_id',
                    'name' => __('Merchant ID', 'edd_fondy'),
                    'desc' => __('Enter your Merchant ID, you can find it <a href=https://portal.fondy.eu/mportal/#/settings/ target=_blank>here</a>.', 'edd_fondy'),
                    'type' => 'text',
                    'size' => 'regular'
                ),
                array(
                    'id' => 'edd_fondy_secret_key',
                    'name' => __('Secret Key', 'edd_fondy'),
                    'desc' => __('Enter your Secret Key, you can find it <a href=https://portal.fondy.eu/mportal/#/settings/ target=_blank>here</a>.', 'edd_fondy'),
                    'type' => 'password',
                    'size' => 'regular'
                ),
                array(
                    'id' => 'edd_fondy_lang',
                    'name' => __('Language', 'edd_fondy'),
                    'desc' => __('Choose language, leave empty if you want for brower language.', 'edd_fondy'),
                    'type' => 'text',
                    'size' => 'regular'
                )
            );

            return array_merge($settings, $edd_fondy_settings);
        }

        /**
         * @param $purchase_data
         * @return bool
         */
        public function process_payment($purchase_data)
        {
            global $edd_options;

            if (!isset($purchase_data['post_data']['edd-gateway']))
                return false;

            $errors = edd_get_errors();
            $fail = false;
            if (!$errors) {
                if (!$edd_options['currency']) {
                    edd_send_back_to_checkout('?payment-mode=' . $purchase_data['post_data']['edd-gateway']);
                }
                $payment_data = array(
                    'price' => $purchase_data['price'],
                    'date' => $purchase_data['date'],
                    'user_email' => $purchase_data['user_email'],
                    'purchase_key' => $purchase_data['purchase_key'],
                    'currency' => $edd_options['currency'],
                    'downloads' => $purchase_data['downloads'],
                    'user_info' => $purchase_data['user_info'],
                    'cart_details' => $purchase_data['cart_details'],
                    'status' => 'pending'
                );

                $payment = edd_insert_payment($payment_data);

                if (!$payment) {

                    edd_record_gateway_error(__('Payment Error', 'edd_fondy'), sprintf(__('Payment creation failed. Payment data: %s', 'edd_fondy'), json_encode($payment_data)), $payment);
                    edd_send_back_to_checkout('?payment-mode=' . $purchase_data['post_data']['edd-gateway']);

                } else {

                    $return_url = add_query_arg('payment-confirmation', 'fondy', get_permalink($edd_options['success_page']));
                    $listener_url = trailingslashit(home_url()) . '?fondy=notify';
                    $merchant_data = stripslashes_deep(html_entity_decode(wp_strip_all_tags(edd_get_purchase_summary($purchase_data, false)), ENT_COMPAT, 'UTF-8'));
                    $order_desc = sprintf(__('Order #: %s', 'edd_fondy'), $payment);
                    $amount = round($purchase_data['price'] - $purchase_data['tax'], 2) * 100;

                    $fondy_args = array(
                        'merchant_id' => $edd_options['edd_fondy_merchant_id'],
                        'merchant_data' => $merchant_data,
                        'currency' => strtoupper($edd_options['currency']),
                        'amount' => $amount,
                        'order_id' => $payment . '#' . time(),
                        'order_desc' => $order_desc,
                        'response_url' => $listener_url,
                        'server_callback_url' => $listener_url
                    );
                    $fondy_args['signature'] = $this->getSignature($edd_options['edd_fondy_secret_key'], $fondy_args);
                    $headers = array(
                        'Content-Type' => 'application/json'
                    );
                    $response = $this->fondy_post($headers, json_encode(array('request' => $fondy_args)));


                    if ($response == null || $response == 'null')
                        return false;

                    $fondy = json_decode($response);

                    if (!$fondy->response->checkout_url) {
                        edd_record_gateway_error(__('Payment Error', 'edd_fondy'), sprintf(__('Payment failed. Payment data: %s', 'edd_fondy'), $response), $payment);
                        edd_send_back_to_checkout('?payment-mode=' . $purchase_data['post_data']['edd-gateway']);
                        return false;
                    }

                    $payment_id = $fondy->response->payment_id;

                    add_post_meta($payment, '_fondy_payment_id', $payment_id);

                    edd_empty_cart();

                    wp_redirect($fondy->response->checkout_url);
                    exit;

                }

            } else {
                $fail = true;
            }

            if ($fail !== false) {
                edd_send_back_to_checkout('?payment-mode=' . $purchase_data['post_data']['edd-gateway']);
            }

        }

        /**
         * @param $password
         * @param array $params
         * @return string
         */
        private function getSignature($password, $params = array())
        {
            $params = array_filter($params, 'strlen');
            ksort($params);
            $params = array_values($params);
            array_unshift($params, $password);
            $params = join('|', $params);
            return (sha1($params));
        }

        /**
         * Hey hey
         */
        public function validate_report_back()
        {
            global $edd_options;

            // Regular Fondy notify
            if (isset($_GET['fondy']) && $_GET['fondy'] == 'notify') {
                do_action('edd_fondy_check');
            }
        }

        /**
         * @param $url
         * @return bool|mixed|null
         */
        public function check_referer_notify($url)
        {
            if (!(is_string($url) && $url))
                return false;

            if (!function_exists('parse_url'))
                return false;
            try {
                if (version_compare(PHP_VERSION, '5.1.2', '>=')) {
                    $ref = parse_url($url, PHP_URL_HOST);
                } else {
                    $parse_ref = parse_url($url);
                    if ($parse_ref !== false && isset($parse_ref['host']))
                        $ref = $parse_ref['host'];
                }
            } catch (Exception $e) {
                die($e);
            }
            if (empty($ref) || $ref == null) {
                return false;
            } else {
                return $ref;
            }
        }

        private function isPaymentValid($data, $settings)
        {

            if ($settings['merchant_id'] != $data['merchant_id']) {
                return false;
            }
            $responseSignature = $data['signature'];
            if (isset($data['response_signature_string'])) {
                unset($data['response_signature_string']);
            }
            if (isset($data['signature'])) {
                unset($data['signature']);
            }
            if ($this->getSignature($settings['secret_key'], $data) != $responseSignature) {
                return false;
            }

            return true;
        }

        /**
         * Fondy Callback url
         * @return bool
         */
        public function process_fondy_notify()
        {
            global $edd_options;

            if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] != 'POST') {
                return false;
            }

            $settings = array(
                'merchant_id' => $edd_options['edd_fondy_merchant_id'],
                'secret_key' => $edd_options['edd_fondy_secret_key']
            );
            $valid = $this->isPaymentValid($_POST, $settings);

            if (isset($_POST['order_id']) && $valid) {

                $payment_id = $_POST['order_id'] ? explode('#', $_POST['order_id'])[0] : null;
                $order_status = $_POST['order_status'] ? $_POST['order_status'] : null;
                $fondy_post_payment_id = $_POST['payment_id'] ? $_POST['payment_id'] : null;

                $payment_order_payment_id = get_post_meta($payment_id, '_fondy_payment_id', true);
                /*
                $referer = $_SERVER['HTTP_REFERER'];

                $ref = $this->check_referer_notify($referer);

                if (($ref != 'secure-redirect.cloudipsp.com') and ($ref != 'api.fondy.eu')) {
                    return false;
                }
                */

                if (get_post_status($payment_id) == 'complete')
                    return false;

                if (edd_get_payment_gateway($payment_id) != 'fondy')
                    return false;

                if ($fondy_post_payment_id != $payment_order_payment_id)
                    return false;

                /* everything has been verified, update the payment to "complete"*/
                if ($order_status == 'approved') {
                    edd_update_payment_status($payment_id, 'publish');
                    edd_insert_payment_note($payment_id, 'Payment ID: ' . $payment_id);
                    edd_insert_payment_note($payment_id, 'Merchant Order ID: ' . $_POST['order_id']);
                    delete_post_meta($payment_id, '_fondy_payment_id', $payment_id);
                    $return_url = add_query_arg('payment-confirmation', 'fondy', get_permalink($edd_options['success_page']));
                    wp_redirect($return_url);
                    exit;
                } elseif ($order_status == 'declined') {
                    $cancel_url = add_query_arg('payment-cancel', 'fondy', edd_get_failed_transaction_uri());
                    wp_redirect($cancel_url);
                    exit;
                };
            }

        }
    }
endif;

/**
 * Throw an error if Easy Digital Download is not installed.
 *
 * @since 0.2
 */
function fondy_missing_edd()
{
    echo '<div class="error"><p>' . sprintf(__('Please %sinstall &amp; activate Easy Digital Downloads%s to allow this plugin to work.'), '<a href="' . admin_url('plugin-install.php?tab=search&type=term&s=easy+digital+downloads&plugin-search-input=Search+Plugins') . '">', '</a>') . '</p></div>';
}

/**
 * Check wp version
 */
function fondy_error_wordpress_version()
{
    echo '<div class="error"><p>' . __('Please upgrade WordPress to the latest version to allow WordPress and this plugin to work properly.', 'edd_fondy') . '</p></div>';
}

/**
 * @return EDD_Fondy
 */
function edd_fondy()
{
    return EDD_Fondy::instance();
}

function edd_fondy_text()
{
    load_plugin_textdomain("edd_fondy", false, basename(dirname(__FILE__)) . '/languages');
}

add_action("init", "edd_fondy_text");
/**
 *  load plugin
 */
function edd_fondy_init()
{
    global $wp_version;

    if (!version_compare($wp_version, '3.4', '>=')) {
        add_action('all_admin_notices', 'fondy_error_wordpress_version');
    } else if (class_exists('Easy_Digital_Downloads')) {
        edd_fondy();
    } else {
        add_action('all_admin_notices', 'fondy_missing_edd');
    }
}

add_action('plugins_loaded', 'edd_fondy_init', 20);