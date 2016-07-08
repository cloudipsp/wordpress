<?php
/*
Plugin Name: Fondy Gateway for Paid Memberships Pro
Description: Fondy Gateway for Paid Memberships Pro
Version: 0.1
*/

define("PMPRO_FONDY_DIR", dirname(__FILE__));

//load payment gateway class
require_once(PMPRO_FONDY_DIR . "/classes/class.pmprogateway_fondy.php");
require_once(PMPRO_FONDY_DIR . "/services/services.php");