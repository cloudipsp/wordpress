<?php
//in case the file is loaded directly

if (!defined("ABSPATH")) {
    global $isapage;
    $isapage = true;

    define('WP_USE_THEMES', false);
    require_once(dirname(__FILE__) . '/../../../../wp-load.php');
}
require_once(dirname(__FILE__) . '/../classes/fondy.lib.php');

define('PMPRO_FONDY_DEBUG', false);

if (empty($_POST)) {
    $fap = json_decode(file_get_contents("php://input"));
    if (empty($fap)) {
        die('no response');
    }
    $_POST = array();
    foreach ($fap as $key => $val) {
        $_POST[$key] = $val;
    }
}
$base64_data = $_POST['data'];
$sign = $_POST['signature'];
$_POST = json_decode(base64_decode($_POST['data']), true)['order'];

//some globals
global $wpdb, $gateway_environment, $logstr;
$logstr = "";    //will put debug info here and write to fnlog.txt

//validate?
if (pmpro_fondyValidate($base64_data, $sign) !== true) {
    pmpro_fondyExit();
}

//assign posted variables to local variables
$amount = pmpro_getParam('amount', 'POST');
$signature = pmpro_getParam('signature', 'POST');
$subscr_id = pmpro_getParam('product_id', 'POST');
$rectoken = pmpro_getParam('rectoken', 'POST');
$order_id = explode('#', pmpro_getParam('order_id', 'POST'))[0];
$customer_email = pmpro_getParam('sender_email', 'POST');
$system_payment_id = pmpro_getParam('payment_id', 'POST');
$order_status = pmpro_getParam('order_status', 'POST');

if ($order_status == FondyForm::ORDER_APPROVED) {
    $recurring = pmpro_getParam('rectoken', 'POST');

    $morder = new MemberOrder($order_id);
    $morder->getMembershipLevel();

    $morder->getUser();
    if (isset($_POST['rectoken'])) {
        $id = $morder->id;
        $rec = $wpdb->query("UPDATE `$wpdb->pmpro_membership_orders` SET fondy_token = '" . $_POST['rectoken'] . "' WHERE id = " . $id . "");

    }

    fnlog("ORDER_CREATED: ORDER: " . var_export($morder, true) . "\n---\n");

    if (!empty ($morder) && !empty ($morder->status) && $morder->status == 'success') {
        $last_subscr_order = new MemberOrder();
        $last_subscr_order->getLastMemberOrderBySubscriptionTransactionID($_POST['payment_id']);
        if (isset($_POST['parent_order_id'])) {
            $order_old_id = $_POST['parent_order_id'];
        } else {
            $order_old_id = $_POST['order_id'];
        }
        pmpro_FondyinsSaveOrder($order_old_id, $last_subscr_order, $_POST);
        fnlog("Checkout was already processed (" . $morder->code . "). Reccuring this request.");

    } elseif (pmpro_insChangeMembershipLevel($order_id, $morder, $_POST)) {
        fnlog("Checkout processed (" . $morder->code . ") success!");
    } else {
        fnlog("ERROR: Couldn't change level for order (" . $morder->code . ").");
    }
    pmpro_fondyExit(pmpro_url("confirmation", "?level=" . $morder->membership_level->id));
} else {
    fnlog("ERROR: (" . $order_status . ").");
}


function fnlog($s)
{
    global $logstr;
    $logstr .= "\t" . $s . "\n";
}

function pmpro_fondyExit($redirect = false)
{
    global $logstr;

    $logstr = var_export($_REQUEST, true) . "Logged On: " . date("m/d/Y H:i:s") . "\n" . $logstr . "\n-------------\n";

    //log in file or email?
    if (defined('PMPRO_FONDY_DEBUG') && PMPRO_FONDY_DEBUG === "log") {
        //file
        $loghandle = fopen(dirname(__FILE__) . "/../logs/fondy.txt", "a+");
        fwrite($loghandle, $logstr);
        fclose($loghandle);
    } elseif (defined('PMPRO_FONDY_DEBUG')) {
        //email
        if (strpos(PMPRO_FONDY_DEBUG, "@")) {
            $log_email = PMPRO_FONDY_DEBUG;
        }    //constant defines a specific email address
        else {
            $log_email = get_option("admin_email");
        }

        wp_mail($log_email, get_option("blogname") . " Fondy log", nl2br($logstr));
    }

    if (!empty($redirect)) {
        wp_redirect($redirect);
    }

    exit;
}

function pmpro_fondyValidate($base64_data, $sign)
{

    $settings = array(
        'merchant_id' => pmpro_getOption("fondy_merchantid"),
        'secret_key' => pmpro_getOption("fondy_securitykey"),
    );
    $validated = FondyForm::isPaymentValid($settings, $_POST, $base64_data, $sign);


    if ($validated !== true) {
        return $validated;
    } else {
        return true;
    }

}

function pmpro_insChangeMembershipLevel($txn_id, &$morder, $data)
{
    $recurring = pmpro_getParam('rectoken', 'POST');
    //filter for level
    $morder->membership_level = apply_filters("pmpro_inshandler_level", $morder->membership_level, $morder->user_id);

    //set the start date to current_time('mysql') but allow filters (documented in preheaders/checkout.php)
    $startdate = apply_filters("pmpro_checkout_start_date", "'" . current_time('mysql') . "'", $morder->user_id, $morder->membership_level);

    //fix expiration date
    if (!empty($morder->membership_level->expiration_number)) {

        $enddate = "'" . date("Y-m-d", strtotime("+ " . $morder->membership_level->expiration_number . " " . $morder->membership_level->expiration_period, current_time("timestamp"))) . "'";
    } else {

        $enddate = "NULL";
    }

    //filter the enddate (documented in preheaders/checkout.php)
    $enddate = apply_filters("pmpro_checkout_end_date", $enddate, $morder->user_id, $morder->membership_level, $startdate);

    //get discount code
    $morder->getDiscountCode();
    if (!empty($morder->discount_code)) {
        //update membership level
        $morder->getMembershipLevel(true);
        $discount_code_id = $morder->discount_code->id;
    } else {
        $discount_code_id = "";
    }


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
        'enddate' => $enddate
    );

    global $pmpro_error, $wpdb;
    if (!empty($pmpro_error)) {
        fnlog($pmpro_error);
    }

    if (pmpro_changeMembershipLevel($custom_level, $morder->user_id) !== false) {
        //update order status and transaction ids
        $morder->status = "success";
        $morder->payment_transaction_id = $txn_id;
        if ($recurring) {
            $morder->subscription_transaction_id = $data['payment_id'];
        } else {
            $morder->subscription_transaction_id = '';
        }
        $morder->saveOrder();

        //add discount code use
        if (!empty($discount_code) && !empty($use_discount_code)) {
            $wpdb->query("INSERT INTO $wpdb->pmpro_discount_codes_uses (code_id, user_id, order_id, timestamp) VALUES('" . $discount_code_id . "', '" . $morder->user_id . "', '" . $morder->id . "', '" . current_time('mysql') . "')");
        }


        //hook
        do_action("pmpro_after_checkout", $morder->user_id);

        //setup some values for the emails
        if (!empty($morder)) {
            $invoice = new MemberOrder($morder->id);
        } else {
            $invoice = null;
        }

        fnlog("CHANGEMEMBERSHIPLEVEL: ORDER: " . var_export($morder, true) . "\n---\n");

        $user = get_userdata($morder->user_id);

        if (empty($user)) {
            return false;
        }

        $user->membership_level = $morder->membership_level;        //make sure they have the right level info

        //send email to member
        $pmproemail = new PMProEmail();
        $pmproemail->sendCheckoutEmail($user, $invoice);

        //send email to admin
        $pmproemail->sendCheckoutAdminEmail($user, $invoice);

        return true;
    } else {
        return false;
    }
}

function pmpro_FondyinsSaveOrder($txn_id, $last_order, $data)
{
    global $wpdb;

    //check that txn_id has not been previously processed
    $old_txn = $wpdb->get_var("SELECT payment_transaction_id FROM $wpdb->pmpro_membership_orders WHERE payment_transaction_id = '" . $txn_id . "' LIMIT 1");

    if (empty($old_txn)) {

        //save order
        $user_id = $last_order->user_id;
        $user = get_userdata($user_id);
        $user->membership_level = pmpro_getMembershipLevelForUser($user_id);
        $morder = new MemberOrder();
        $morder->user_id = $last_order->user_id;
        $morder->membership_id = $last_order->membership_id;

        if (isset($_POST['amount'])) {
            $morder->subtotal = $_POST['amount'] / 100;
        } elseif (isset($_POST['actual_amount'])) {
            $morder->total = (!empty($_POST['amount']) ? $_POST['amount'] / 100 : 0);
        }

        $morder->payment_transaction_id = $_POST['order_id'];
        $morder->subscription_transaction_id = $_POST['payment_id'];

        $morder->gateway = $last_order->gateway;
        $morder->gateway_environment = $last_order->gateway_environment;
        $morder->billing = new stdClass();

        $morder->billing->street = $last_order->billing->street;
        $morder->billing->city = $last_order->billing->city;
        $morder->billing->state = $last_order->billing->state;
        $morder->billing->zip = $last_order->billing->zip;
        $morder->billing->country = $last_order->billing->country;
        $morder->billing->phone = $last_order->billing->phone;
        $morder->status = "success";
        $morder->payment_type = "Fondy";
        //save
        $morder->saveOrder();
        $morder->getMemberOrderByID($morder->id);

        //email the user their invoice
        $pmproemail = new PMProEmail();
        $pmproemail->sendInvoiceEmail(get_userdata($last_order->user_id), $morder);

        fnlog("New order (" . $morder->code . ") created.");
        do_action('pmpro_subscription_payment_completed', $morder);

        return $morder->code;
    } else {
        fnlog("Duplicate Transaction ID: " . $txn_id);

        return false;
    }
}