<?php
/*
Plugin Name: WooCommerce - Fondy payment gateway
Plugin URI: https://fondy.eu
Description: Fondy Payment Gateway for WooCommerce.
Version: 2.6.10
Author: FONDY - Unified Payment Platform
Author URI: https://fondy.eu/
Domain Path: /languages
Text Domain: fondy-woocommerce-payment-gateway
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
WC requires at least: 2.5.0
WC tested up to: 4.7.1
*/

defined( 'ABSPATH' ) or exit;
define( 'FONDY_BASE_PATH' ,  plugin_dir_url( __FILE__ ) );
if ( ! class_exists( 'WC_PaymentFondy' ) ) :
    class WC_PaymentFondy {
        private $subscription_support_enabled = false;
        private static $instance;

        /**
         * @return WC_PaymentFondy
         */
        public static function get_instance() {
            if ( null === self::$instance ) {
                self::$instance = new self();
            }

            return self::$instance;
        }

        /**
         * WC_PaymentFondy constructor.
         */
        protected function __construct() {
            add_action( 'plugins_loaded', array( $this, 'init' ) );
        }

        /**
         * init fondy
         */
        public function init() {
            if ( self::check_environment() ) {
                return;
            }
            add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_action_links' ) );
            $this->init_fondy();
        }

        /**
         * init fondy
         */
        public function init_fondy() {
            require_once( dirname( __FILE__ ) . '/includes/class-wc-fondy-gateway.php' );
            load_plugin_textdomain( "fondy-woocommerce-payment-gateway", false, basename( dirname( __FILE__ )) . '/languages' );
            add_filter( 'woocommerce_payment_gateways', array( $this, 'woocommerce_add_fondy_gateway' ) );
            add_action('wp_ajax_nopriv_generate_ajax_order_fondy_info', array('WC_fondy', 'generate_ajax_order_fondy_info' ), 99);
            add_action('wp_ajax_generate_ajax_order_fondy_info', array('WC_fondy', 'generate_ajax_order_fondy_info'), 99);
            if ( class_exists( 'WC_Subscriptions_Order' ) && function_exists( 'wcs_create_renewal_order' ) ) {
                $this->subscription_support_enabled = true;
                require_once( dirname( __FILE__ ) . '/includes/wc-fondy-subscriptions.php' );
            }
        }

        /**
         * Checks the environment for compatibility problems.  Returns a string with the first incompatibility
         * found or false if the environment has no problems.
         */
        static function check_environment() {
            if ( version_compare( phpversion(), '5.4.0', '<' ) ) {
                $message = __( ' The minimum PHP version required for Fondy is %1$s. You are running %2$s.', 'woocommerce-fondy' );

                return sprintf( $message, '5.4.0', phpversion() );
            }

            if ( ! defined( 'WC_VERSION' ) ) {
                return __( 'WooCommerce needs to be activated.', 'woocommerce-fondy' );
            }

            if ( version_compare( WC_VERSION, '3.0.0', '<' ) ) {
                $message = __( 'The minimum WooCommerce version required for Fondy is %1$s. You are running %2$s.', 'woocommerce-fondy' );

                return sprintf( $message, '2.0.0', WC_VERSION );
            }

            return false;
        }
        public function plugin_action_links( $links ) {
            $plugin_links = array(
                '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=fondy' ) . '">' . __( 'Settings', 'woocommerce-fondy' ) . '</a>',
            );

            return array_merge( $plugin_links, $links );
        }

        /**
         * Add the Gateway to WooCommerce
         * @param $methods
         * @return array
         */
        public function woocommerce_add_fondy_gateway( $methods ) {
            if ( $this->subscription_support_enabled ) {
                $methods[] = 'WC_Fondy_Subscriptions';
            } else {
                $methods[] = 'WC_fondy';
            }
            return $methods;
        }
    }

    $GLOBALS['wc_fondy'] = WC_PaymentFondy::get_instance();
endif;