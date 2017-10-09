<?php
/**
 * Plugin Name: z-fondy
 * Description: fondy payment.
 * Version: 1.0.0
 */
define("RCP_FONDY_DIR", dirname(__FILE__));
//load payment gateway class
function pw_rcp_register_fondy_gateway( $gateways ) {

    $gateways['Fondy'] = array(
        'label'        => 'Fondy',
        'admin_label'  => 'Fondy',
        'class'        => 'RCP_Payment_Gateway_Fondy'
    );
    return $gateways;
}
add_filter( 'rcp_payment_gateways', 'pw_rcp_register_fondy_gateway',0);


add_action('plugins_loaded', 'pw_rcp_register_fondy_mainClass', 1);

function pw_rcp_register_fondy_mainClass()
{
    global $rcp_fondy_options;
    $rcp_fondy_options = get_option( 'rcp_fondy_settings' );
    require_once(RCP_FONDY_DIR . "/fondy/fondy.php");
    require_once(RCP_FONDY_DIR . "/fondy/fondy-admin.php");
}



