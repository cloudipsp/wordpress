<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// if uninstall not called from WordPress exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Delete options.
delete_option( 'fondy_woocommerce_version' );
delete_option( 'woocommerce_fondy_settings' );
delete_option( 'woocommerce_fondy_local_methods_settings' );
delete_option( 'woocommerce_fondy_bank_settings' );
delete_option( 'fondy_unique' ); // <3.0.0 option
