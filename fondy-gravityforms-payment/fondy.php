<?php
/*
Plugin Name: Fondy Payment Gravity Forms Add-on
Plugin URI: https://fondy.eu
Description: Integrates Gravity Forms with Fondy.
Version: 1.0
Author: DM
Author URI: https://fondy.eu
License: GPL-2.0+
Text Domain: gravityformsfondy
Domain Path: /languages
*/


define( 'GF_FONDY_VERSION', '1.0' );

add_action( 'gform_loaded', array( 'GF_Fondy_Bootstrap', 'load' ), 5 );

class GF_Fondy_Bootstrap {

	public static function load() {

		if ( ! method_exists( 'GFForms', 'include_payment_addon_framework' ) ) {
			return;
		}

		require_once( 'class-gf-fondy.php' );

		GFAddOn::register( 'GFFondy' );
	}
}

function gf_fondy() {
	return GFFondy::get_instance();
}
