<?php
	//in case the file is loaded directly
	
	if ( ! defined( "ABSPATH" ) ) {
		global $isapage;
		$isapage = true;
		
		define( 'WP_USE_THEMES', false );
		require_once( dirname( __FILE__ ) . '/../../../../wp-load.php' );
	}
	require_once( dirname( __FILE__ ) . '/../classes/fondy.lib.php' );
	
	define('PMPRO_FONDY_DEBUG', false);

if (empty($_POST))
{
	$fap = json_decode(file_get_contents("php://input"));
	$_POST=array();
	foreach($fap as $key=>$val)
	{
		$_POST[$key] =  $val ;
	}
}
	//some globals
	global $wpdb, $gateway_environment, $logstr;
	$logstr = "";    //will put debug info here and write to fnlog.txt
	
	//validate?
	if ( ! pmpro_fondyValidate() ) {
		//validation failed
		pmpro_fondyExit();
	}
	
	//assign posted variables to local variables
	$amount = pmpro_getParam( 'amount', 'POST' );
	$signature = pmpro_getParam( 'signature', 'POST' );
	$subscr_id = pmpro_getParam( 'product_id', 'POST' );
	$rectoken = pmpro_getParam( 'rectoken', 'POST' );
	$order_id = explode('#',pmpro_getParam( 'order_id', 'POST' ))[0];
	$customer_email = pmpro_getParam( 'sender_email', 'POST' );
	$system_payment_id = pmpro_getParam( 'payment_id', 'POST' );
	$order_status = pmpro_getParam( 'order_status', 'POST' );
	
	//$name = explode('=', $_POST['merchant_data']);
	
	if ($order_status === FondyForm::ORDER_APPROVED)
	{
		
		$morder = new MemberOrder( $order_id );
		$morder->getMembershipLevel();
		
		if($morder->total != $amount/100){
			fnlog( "Amount is incorrect" );
			exit;
		}
		//print_r($amount);die;
		$morder->getUser();
		if (isset($_POST['rectoken'])){
		$id = $morder->id;
		$rec = $wpdb->query("UPDATE `$wpdb->pmpro_membership_orders` SET fondy_token = '". $_POST['rectoken'] ."' WHERE id = ". $id ."");	
		//print_r ($rec);die;
		}
		
		//print_r ($morder->id); die;
		fnlog("ORDER_CREATED: ORDER: " . var_export($morder, true) . "\n---\n");
		
		if( ! empty ( $morder ) && ! empty ( $morder->status ) && $morder->status === 'success' ) {
			fnlog( "Checkout was already processed (" . $morder->code . "). Ignoring this request." );
			
		}
		elseif (pmpro_insChangeMembershipLevel( $order_id, $morder ) ) {	
			fnlog( "Checkout processed (" . $morder->code . ") success!" );
		}
		else {
			fnlog( "ERROR: Couldn't change level for order (" . $morder->code . ")." );
		}
		//echo 1;
		pmpro_fondyExit(pmpro_url("confirmation", "?level=" . $morder->membership_level->id));
		}else{
		fnlog( "ERROR: (" . $order_status . ")." );
	}
	
	
	
	function fnlog( $s ) {
		global $logstr;
		$logstr .= "\t" . $s . "\n";
	}
	
	function pmpro_fondyExit($redirect = false)
	{
		global $logstr;
		//echo $logstr;
		
		$logstr = var_export($_REQUEST, true) . "Logged On: " . date("m/d/Y H:i:s") . "\n" . $logstr . "\n-------------\n";
		
		//log in file or email?
		if(defined('PMPRO_FONDY_DEBUG') && PMPRO_FONDY_DEBUG === "log")
		{
			//file
			$loghandle = fopen(dirname(__FILE__) . "/../logs/fondy.txt", "a+");
			fwrite($loghandle, $logstr);
			fclose($loghandle);
		}
		elseif(defined('PMPRO_FONDY_DEBUG'))
		{
			//email
			if(strpos(PMPRO_FONDY_DEBUG, "@"))
			$log_email = PMPRO_FONDY_DEBUG;	//constant defines a specific email address
			else
			$log_email = get_option("admin_email");
			
			wp_mail($log_email, get_option("blogname") . " Fondy log", nl2br($logstr));
		}
		
		if(!empty($redirect))
		wp_redirect($redirect);
		
		exit;
	}
	
	function pmpro_fondyValidate() {
		
		$settings = array(
		'merchant_id' => pmpro_getOption("fondy_merchantid"),
		'secret_key' => pmpro_getOption("fondy_securitykey"),
		);
		$validated = FondyForm::isPaymentValid($settings, $_POST);	
		
		
		if ($validated != true){
			return $validated;
			}else{
			return true;
		}
		
	}
	function pmpro_insChangeMembershipLevel($txn_id, &$morder)
	{
		
		$recurring = pmpro_getParam( 'rectoken', 'POST' );
		
		//filter for level
		$morder->membership_level = apply_filters("pmpro_inshandler_level", $morder->membership_level, $morder->user_id);
		
		//set the start date to current_time('mysql') but allow filters (documented in preheaders/checkout.php)
		$startdate = apply_filters("pmpro_checkout_start_date", "'" . current_time('mysql') . "'", $morder->user_id, $morder->membership_level);
		
		//fix expiration date
		if(!empty($morder->membership_level->expiration_number))
		{
			
			$enddate = "'" . date("Y-m-d", strtotime("+ " . $morder->membership_level->expiration_number . " " . $morder->membership_level->expiration_period, current_time("timestamp"))) . "'";
		}
		else
		{
			
			$enddate = "NULL";
		}
		
		//filter the enddate (documented in preheaders/checkout.php)
		$enddate = apply_filters("pmpro_checkout_end_date", $enddate, $morder->user_id, $morder->membership_level, $startdate);
		
		//get discount code
		$morder->getDiscountCode();
		if(!empty($morder->discount_code))
		{
			//update membership level
			$morder->getMembershipLevel(true);
			$discount_code_id = $morder->discount_code->id;
		}
		else
		$discount_code_id = "";
		
		
		
		//custom level to change user to
		$custom_level = array(
		'user_id' => $morder->user_id,
		'membership_id' => $morder->membership_level->id,
		'code_id' => $discount_code_id,
		'initial_payment' => $morder->membership_level->initial_payment,
		'billing_amount' => $morder->membership_level->billing_amount,
		'cycle_number' => $morder->membership_level->cycle_number,
		'cycle_period' => $morder->membership_level->cycle_period,
		'billing_limit' => $morder->membership_level->billing_limit,
		'trial_amount' => $morder->membership_level->trial_amount,
		'trial_limit' => $morder->membership_level->trial_limit,
		'startdate' => $startdate,
		'enddate' => $enddate);
		
		global $pmpro_error;
		if(!empty($pmpro_error))
		{
			//echo $pmpro_error;
			fnlog($pmpro_error);
		}
		if( pmpro_changeMembershipLevel($custom_level, $morder->user_id) !== false ) {
			//update order status and transaction ids
			$morder->status = "success";
			$morder->payment_transaction_id = $txn_id;
			if( !$recurring )
			$morder->subscription_transaction_id = $txn_id;
			else
			$morder->subscription_transaction_id = '';
			$morder->saveOrder();
			
			//add discount code use
			if(!empty($discount_code) && !empty($use_discount_code))
			{
				$wpdb->query("INSERT INTO $wpdb->pmpro_discount_codes_uses (code_id, user_id, order_id, timestamp) VALUES('" . $discount_code_id . "', '" . $morder->user_id . "', '" . $morder->id . "', '" . current_time('mysql') . "')");
			}
			
			
			//hook
			do_action("pmpro_after_checkout", $morder->user_id);
				//print_r ($morder); die;
			//setup some values for the emails
			if(!empty($morder))
			
			$invoice = new MemberOrder($morder->id);
			
			else
			$invoice = NULL;
			
			fnlog("CHANGEMEMBERSHIPLEVEL: ORDER: " . var_export($morder, true) . "\n---\n");
			
			$user = get_userdata($morder->user_id);
			
			if(empty($user))
			return false;
			
			$user->membership_level = $morder->membership_level;		//make sure they have the right level info
			
			//send email to member
			$pmproemail = new PMProEmail();
			$pmproemail->sendCheckoutEmail($user, $invoice);
			
			//send email to admin
			$pmproemail = new PMProEmail();
			$pmproemail->sendCheckoutAdminEmail($user, $invoice);
			
			return true;
		}
		else
		return false;
		
		
		
		
	}
	