<?php
/*
	Loading a service?
*/
/*
	Note: The applydiscountcode goes through the site_url() instead of admin-ajax to avoid HTTP/HTTPS issues.
*/
function pmpro_wp_ajax_fondy_ins()
{
    require_once(dirname(__FILE__) . "/fondy-ins.php");
    exit;
}

add_action('wp_ajax_nopriv_fondy-ins', 'pmpro_wp_ajax_fondy_ins');
add_action('wp_ajax_fondy-ins', 'pmpro_wp_ajax_fondy_ins');