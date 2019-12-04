<?php
/*
Plugin Name: WooCommerce - Fondy subscription payment gateway
Plugin URI: https://fondy.eu/en/
Description: Fondy subscription payment gateway for WooCommerce.
Version: 1.0
Author: DM
Author URI: https://fondy.eu/en/
Domain Path: /
Text Domain: woocommerce-fondy
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/
defined( 'ABSPATH' ) or exit;
define( 'FONDY_BASE_PATH' ,  plugin_dir_url( __FILE__ ) );
if ( ! class_exists( 'WC_PaymentFondy' ) ) :
	class WC_PaymentFondy {
		private $subscription_support_enabled = false;
		private static $instance;

		public static function get_instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		protected function __construct() {
			add_action( 'plugins_loaded', array( $this, 'init' ) );
		}

		public function init() {
			if ( self::check_environment() ) {
				return;
			}
			add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_action_links' ) );
			$this->init_fondy();
		}

		public function init_fondy() {
			require_once( dirname( __FILE__ ) . '/includes/class-wc-gateway-fondy.php' );
			load_plugin_textdomain( "woocommerce-fondy", false, basename( dirname( __FILE__ ) ) );
			add_filter( 'woocommerce_payment_gateways', array( $this, 'woocommerce_add_fondy_gateway' ) );

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
		 **/
		public function woocommerce_add_fondy_gateway( $methods ) {
			if ( $this->subscription_support_enabled ) {
				$methods[] = 'WC_Fondy_Subscriptions';
			} else {
				$methods[] = 'WC_Fondy';
			}

			return $methods;
		}
	}

	$GLOBALS['wc_fondy'] = WC_PaymentFondy::get_instance();
endif;