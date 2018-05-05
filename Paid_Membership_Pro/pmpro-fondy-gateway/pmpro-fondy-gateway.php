<?php
/*
Plugin Name: PmP Fondy Payment
Plugin URI: https://fondy.eu
Description: Fondy Gateway for Paid Memberships Pro
Version: 1.0
Domain Path: /
Text Domain: fondy
Author: Dmitriy Miroshnikov
Author URI: https://fondy.eu/
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/
define("PMPRO_FONDY_DIR", dirname(__FILE__));

//load payment gateway class
require_once(PMPRO_FONDY_DIR . "/classes/class.pmprogateway_fondy.php");
require_once(PMPRO_FONDY_DIR . "/services/services.php");