<?php
/*
Plugin Name: Restrict Content Pro - Fondy payment gateway
Plugin URI: https://fondy.eu
Description: Fondy Payment Gateway for Restrict Content.
Version: 1.0.3
Author: Dmitriy Miroshnikov
Author URI: https://fondy.eu/
Domain Path: /languages
Text Domain: fondy_rcp
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/
define("RCP_FONDY_DIR", dirname(__FILE__));
//load payment gateway class
add_filter('rcp_payment_gateways', 'pw_rcp_register_fondy_gateway', 1);
add_action('plugins_loaded', 'pw_rcp_register_fondy_mainClass', 99);

function pw_rcp_register_fondy_gateway($gateways)
{
    $gateways['Fondy'] = array(
        'label' => 'Fondy',
        'admin_label' => 'Fondy',
        'class' => 'RCP_Payment_Gateway_Fondy'
    );
    return $gateways;
}

function pw_rcp_register_fondy_mainClass()
{
    global $rcp_fondy_options;
    $rcp_fondy_options = get_option('rcp_fondy_settings');
    require_once(RCP_FONDY_DIR . "/fondy/fondy.php");
    require_once(RCP_FONDY_DIR . "/fondy/fondy-admin.php");
}



