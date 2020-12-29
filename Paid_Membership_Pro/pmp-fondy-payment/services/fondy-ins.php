<?php
// TODO refactor
//in case the file is loaded directly
require_once(dirname(__FILE__) . '/../classes/fondy.lib.php');

/**
 * DEBUG mode
 */
define('FONDY_PMPRO_DEBUG', "log");

//some globals
global $wpdb, $gateway_environment, $fondy_logstr;
$fondy_logstr = "";    //will put debug info here and write to pmpro_fondy_fnlog.txt
$response = '';

if (empty($_POST)) {
    $callback = json_decode(file_get_contents("php://input"));
    if (empty($callback)) {
        pmpro_fondy_fnlog("Empty responce.");
        pmpro_fondy_exit();
    }
    $_POST = array();
    foreach ($callback as $key => $val) {
        $_POST[esc_sql($key)] = esc_sql($val);
    }
}

if (isset($_POST['version'])){
    $base64_data = esc_sql($_POST['data']);
    $signature = esc_sql((string)$_POST['signature']);
    $response = json_decode(base64_decode(esc_sql($_POST['data'])), true)['order'];

    /**
     * Validate Fondy answer
     */
    if (pmpro_fondy_validate_sign($base64_data, $signature, $response, $gateway_environment === 'sandbox') !== true) {
        pmpro_fondy_fnlog("Signature Invalid.");
        pmpro_fondy_exit();
    }
} elseif (!empty($_POST['order_id']) && !empty($_POST['merchant_id'])) { // todo remove this elseif after checkout2 response_url fix
    $merchantID = $gateway_environment === 'sandbox' ?  FondyForm::TEST_MERCHANT_ID : pmpro_getOption("fondy_merchantid");
    if ($merchantID === $_POST['merchant_id']){
        $response = esc_sql($_POST);
    }
}

if (empty($response)) {
    pmpro_fondy_fnlog("Empty response.");
    pmpro_fondy_exit();
}

//assign posted variables to local variables
$amount = (int)$response['amount'];
$actual_amount = (int)$response['actual_amount'];
$subscr_id = (int)$response['product_id'];
$rectoken = esc_sql((string)$response['rectoken']);
$order_id = esc_sql((string)$response['order_id']);
$customer_email = esc_sql((string)$response['sender_email']);
$system_payment_id = (int)$response['payment_id'];
$order_status = esc_sql((string)$response['order_status']);

if ($order_status == FondyForm::ORDER_APPROVED) {

    $morder = new MemberOrder($order_id);
    $morder->getMembershipLevel();

    $morder->getUser();

    if (isset($rectoken) and !empty($rectoken)) {
        $id = $morder->id;
        $rec = $wpdb->query($wpdb->prepare("
            UPDATE `$wpdb->pmpro_membership_orders`
            SET `fondy_token` = %s
            WHERE id = %d",
            array($rectoken, $id)
        ));
    }

    pmpro_fondy_fnlog("ORDER_CREATED: ORDER: " . var_export($morder, true) . "\n---\n");

    if (!empty($morder) && !empty($morder->status) && $morder->status == 'success') {
        $last_subscr_order = new MemberOrder();
        $last_subscr_order->getLastMemberOrderBySubscriptionTransactionID($system_payment_id);
        if (!empty($response['parent_order_id'])) {
            $order_old_id = esc_sql((string)$response['parent_order_id']);
        } else {
            $order_old_id = $order_id;
        }
        $order_info = array(
            'amount' => $amount,
            'actual_amount' => $actual_amount,
            'order_id' => $order_id,
            'payment_id' => $system_payment_id,
        );
        pmpro_fondy_insSaveOrder($order_old_id, $last_subscr_order, $order_info);
        pmpro_fondy_fnlog("Checkout was already processed (" . $morder->code . "). Reccuring this request.");

    } elseif (pmpro_fondy_insChangeMembershipLevel($order_id, $morder, $system_payment_id, $rectoken)) {
        pmpro_fondy_fnlog("Checkout processed (" . $morder->code . ") success!");
    } else {
        pmpro_fondy_fnlog("ERROR: Couldn't change level for order (" . $morder->code . ").");
    }

    if (isset($callback)) // return 200 to callback
        wp_die();

    pmpro_fondy_exit(pmpro_url("confirmation", "?level=" . $morder->membership_level->id));
} else {
    pmpro_fondy_fnlog("ERROR: (" . $order_status . ").");
}


function pmpro_fondy_fnlog($s)
{
    global $fondy_logstr;
    $fondy_logstr .= "\t" . $s . "\n";
}

function pmpro_fondy_exit($redirect = false)
{
    global $fondy_logstr;

    $fondy_logstr = var_export($_REQUEST, true) . "Logged On: " . date("m/d/Y H:i:s") . "\n" . $fondy_logstr . "\n-------------\n";

    //log in file or email?
    if (defined('FONDY_PMPRO_DEBUG') && FONDY_PMPRO_DEBUG === "log") {
        //file
        $loghandle = fopen(dirname(__FILE__) . "/../logs/fondy.txt", "a+");
        fwrite($loghandle, $fondy_logstr);
        fclose($loghandle);
    } elseif (defined('FONDY_PMPRO_DEBUG')) {
        //email
        if (strpos(FONDY_PMPRO_DEBUG, "@")) {
            $log_email = FONDY_PMPRO_DEBUG;
        }    //constant defines a specific email address
        else {
            $log_email = get_option("admin_email");
        }

        wp_mail($log_email, get_option("blogname") . " Fondy log", nl2br($fondy_logstr));
    }

    if (!empty($redirect)) {
        wp_redirect($redirect);
    }

    exit;
}

function pmpro_fondy_validate_sign($base64_data, $sign, $response = array(), $isTestEnv = false)
{
    $settings = array(
        'merchant_id' => $isTestEnv ? FondyForm::TEST_MERCHANT_ID : pmpro_getOption("fondy_merchantid"),
        'secret_key' => $isTestEnv ? FondyForm::TEST_MERCHANT_KEY : pmpro_getOption("fondy_securitykey"),
    );

    return FondyForm::isPaymentValid($settings, $response, $base64_data, $sign);
}

function pmpro_fondy_insChangeMembershipLevel($txn_id, &$morder, $payment_id, $rectoken)
{
    $recurring = $rectoken;
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
        pmpro_fondy_fnlog($pmpro_error);
    }

    if (pmpro_changeMembershipLevel($custom_level, $morder->user_id) !== false) {
        //update order status and transaction ids
        $morder->status = "success";
        $morder->payment_transaction_id = $txn_id;
        if ($recurring) {
            $morder->subscription_transaction_id = $payment_id;
        } else {
            $morder->subscription_transaction_id = '';
        }
        $morder->saveOrder();

        //add discount code use
        if (!empty($discount_code) && !empty($use_discount_code)) {
            $wpdb->query("INSERT INTO $wpdb->pmpro_discount_codes_uses (code_id, user_id, order_id, timestamp) VALUES('" . esc_sql($discount_code_id) . "', '" . esc_sql($morder->user_id) . "', '" . esc_sql($morder->id) . "', '" . current_time('mysql') . "')");
        }


        //hook
        do_action("pmpro_after_checkout", $morder->user_id, $morder);

        //setup some values for the emails
        if (!empty($morder)) {
            $invoice = new MemberOrder($morder->id);
        } else {
            $invoice = null;
        }

        pmpro_fondy_fnlog("CHANGEMEMBERSHIPLEVEL: ORDER: " . var_export($morder, true) . "\n---\n");

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

function pmpro_fondy_insSaveOrder($txn_id, $last_order, $order_info = array())
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

        if (isset($order_info['amount'])) {
            $morder->subtotal = $order_info['amount'] / 100;
        } elseif (isset($order_info['actual_amount'])) {
            $morder->total = (!empty($order_info['amount']) ? $order_info['amount'] / 100 : 0);
        }

        $morder->payment_transaction_id = $order_info['order_id'];
        $morder->subscription_transaction_id = $order_info['payment_id'];

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

        pmpro_fondy_fnlog("New order (" . $morder->code . ") created.");
        do_action('pmpro_subscription_payment_completed', $morder);

        return $morder->code;
    } else {
        pmpro_fondy_fnlog("Duplicate Transaction ID: " . $txn_id);
        return false;
    }
}