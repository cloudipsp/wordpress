<?php
/**
 * Plugin Name: WooCommerce - Fondy payment gateway
 * Plugin URI: https://fondy.io/gb/plugins/woocommerce/
 * Description: Fondy Payment Gateway for WooCommerce.
 * Author: Fondy
 * Author URI: https://fondy.io
 * Version: 3.0.3
 * Text Domain: fondy-woocommerce-payment-gateway
 * Domain Path: /languages
 * Tested up to: 5.8
 * WC tested up to: 5.6
 * WC requires at least: 3.0
 *
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) {
    exit;
}

// Make sure WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) return;

define("WC_FONDY_DIR", dirname(__FILE__));
define("WC_FONDY_BASE_FILE", __FILE__);
define('WC_FONDY_VERSION', '3.0.3');
define('WC_FONDY_MIN_PHP_VER', '5.6.0');
define('WC_FONDY_MIN_WC_VER', '3.0');

add_action('plugins_loaded', 'woocommerce_gateway_fondy');

if ( ! class_exists( 'WC_Fondy' ) ) {
    class WC_Fondy
    {
        private static $instance = null;

        /**
         * gets the instance via lazy initialization (created on first usage)
         */
        public static function getInstance()
        {
            if (static::$instance === null) {
                static::$instance = new static();
            }

            return static::$instance;
        }

        private function __construct()
        {
            if (!$this->isAcceptableEnv())
                return;

            require_once dirname(__FILE__) . '/includes/class-wc-fondy-api.php';

            require_once dirname(__FILE__) . '/includes/integration-types/Fondy_Embedded.php';
            require_once dirname(__FILE__) . '/includes/integration-types/Fondy_Hosted.php';
            require_once dirname(__FILE__) . '/includes/integration-types/Fondy_Seamless.php';

            require_once dirname(__FILE__) . '/includes/abstract-wc-fondy-payment-gateway.php';
            require_once dirname(__FILE__) . '/includes/payment-methods/class-wc-gateway-fondy-card.php';
            require_once dirname(__FILE__) . '/includes/payment-methods/class-wc-gateway-fondy-bank.php';
            require_once dirname(__FILE__) . '/includes/payment-methods/class-wc-gateway-fondy-localmethods.php';

            require_once dirname(__FILE__) . '/includes/compat/class-wc-fondy-pre-orders-compat.php';
            require_once dirname(__FILE__) . '/includes/compat/class-wc-fondy-subscriptions-compat.php';

            // This action hook registers our PHP class as a WooCommerce payment gateway
            add_filter('woocommerce_payment_gateways', [$this, 'add_gateways']);
            // localization
            load_plugin_textdomain('fondy-woocommerce-payment-gateway', false, basename(WC_FONDY_DIR) . '/languages/');
            // add plugin setting button
            add_filter('plugin_action_links_' . plugin_basename(__FILE__), [$this, 'plugin_action_links']);

            $this->updateSettings();
        }

        public function add_gateways($gateways)
        {
            $gateways[] = 'WC_Gateway_Fondy_Card';
            $gateways[] = 'WC_Gateway_Fondy_Bank';
            $gateways[] = 'WC_Gateway_Fondy_LocalMethods';
            return $gateways;
        }

        /**
         * render setting button in wp plugins list
         *
         * @param $links
         * @return array|string[]
         */
        public function plugin_action_links($links)
        {
            $plugin_links = [
                sprintf(
                    '<a href="%1$s">%2$s</a>',
                    admin_url('admin.php?page=wc-settings&tab=checkout&section=fondy'),
                    __('Settings', 'fondy-woocommerce-payment-gateway')
                ),
            ];

            return array_merge($plugin_links, $links);
        }

        /**
         * migrate old settings
         */
        public function updateSettings()
        {
            if (version_compare(get_option('fondy_woocommerce_version'), WC_FONDY_VERSION, '<')) {
                update_option('fondy_woocommerce_version', WC_FONDY_VERSION);
                $settings = maybe_unserialize(get_option('woocommerce_fondy_settings', []));

                if (isset($settings['salt'])) {
                    $settings['secret_key'] = $settings['salt'];
                    unset($settings['salt']);
                }

                if (isset($settings['default_order_status'])){
                    $settings['completed_order_status'] = $settings['default_order_status'];
                    unset($settings['default_order_status']);
                }

                if (isset($settings['payment_type'])) {
                    switch ($settings['payment_type']) {
                        case 'page_mode':
                            $settings['integration_type'] = 'embedded';
                            break;
                        case 'on_checkout_page':
                            $settings['integration_type'] = 'seamless';
                            break;
                        default:
                            $settings['integration_type'] = 'hosted';
                    }
                    unset($settings['payment_type']);
                }

                unset($settings['calendar']);
                unset($settings['page_mode_instant']);
                unset($settings['on_checkout_page']);
                unset($settings['force_lang']);

                update_option('woocommerce_fondy_settings', $settings);
            }
        }

        /**
         * check env
         *
         * @return bool
         */
        public function isAcceptableEnv()
        {
            if (version_compare(WC_VERSION, WC_FONDY_MIN_WC_VER, '<')) {
                add_action('admin_notices', [$this, 'woocommerce_fondy_wc_not_supported_notice']);
                return false;
            }

            if (version_compare(phpversion(), WC_FONDY_MIN_PHP_VER, '<')) {
                add_action('admin_notices', [$this, 'woocommerce_fondy_php_not_supported_notice']);

                return false;
            }

            return true;
        }

        public function woocommerce_fondy_wc_not_supported_notice()
        {
            /* translators: 1) required WC version 2) current WC version */
            $message = sprintf(__('Payment Gateway Fondy requires WooCommerce %1$s or greater to be installed and active. WooCommerce %2$s is no longer supported.', 'fondy-woocommerce-payment-gateway'), WC_FONDY_MIN_WC_VER, WC_VERSION);
            echo '<div class="notice notice-error is-dismissible"> <p>' . $message . '</p></div>';
        }

        public function woocommerce_fondy_php_not_supported_notice()
        {
            /* translators: 1) required PHP version 2) current PHP version */
            $message = sprintf(__('The minimum PHP version required for Fondy Payment Gateway is %1$s. You are running %2$s.', 'fondy-woocommerce-payment-gateway'), WC_FONDY_MIN_PHP_VER, phpversion());
            echo '<div class="notice notice-error is-dismissible"> <p>' . $message . '</p></div>';
        }

        /**
         * prevent from being unserialized (which would create a second instance of it)
         */
        public function __wakeup()
        {
            throw new Exception("Cannot unserialize singleton");
        }
    }
}

function woocommerce_gateway_fondy() {
    return WC_Fondy::getInstance();
}

