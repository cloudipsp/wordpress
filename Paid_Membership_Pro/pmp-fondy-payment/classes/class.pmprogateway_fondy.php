<?php
//load classes init method

require_once(dirname(__FILE__) . "/fondy.lib.php");

/**
 * PMProGateway_fondy Class
 *
 * Handles fondy integration.
 *
 */

class PMProGateway_fondy extends PMProGateway
{
    /**
     * @var bool
     */
    private $isTestEnv;

    static function install()
    {
        global $wpdb;

        $wpdb->query("ALTER TABLE $wpdb->pmpro_membership_orders ADD fondy_token TEXT");
    }

    static function uninstall()
    {
        global $wpdb;

        $wpdb->query("ALTER TABLE $wpdb->pmpro_membership_orders DROP COLUMN fondy_token");
    }

    /**
     * Run on WP init
     *
     * @since 1.8
     */
    static function init()
    {
        //make sure fondy is a gateway option
        add_filter('pmpro_gateways', array('PMProGateway_fondy', 'pmpro_gateways'));

        //localization
        load_plugin_textdomain( 'pmp-fondy-payment', false, basename(PMPRO_FONDY_DIR).'/languages/' );

        //add plugin setting button
        add_filter('plugin_action_links_' . plugin_basename(PMPRO_FONDY_BASE_FILE),
            array('PMProGateway_fondy', 'plugin_action_links')
        );

        //add plugin doc button
        add_filter( 'plugin_row_meta', array('PMProGateway_fondy', 'plugin_row_meta'), 10, 2);

        //add fields to payment settings
        add_filter('pmpro_payment_options', array('PMProGateway_fondy', 'pmpro_payment_options'));
        add_filter('pmpro_payment_option_fields', array('PMProGateway_fondy', 'pmpro_payment_option_fields'), 10, 2);

        //code to add at checkout if fondy is the current gateway
        if (pmpro_getGateway() == "fondy") {
            //add_filter('pmpro_include_billing_address_fields', '__return_false');
            add_filter('pmpro_include_payment_information_fields', '__return_false');
            add_filter('pmpro_required_billing_fields', array('PMProGateway_fondy', 'pmpro_required_billing_fields'));
            add_filter('pmpro_checkout_default_submit_button', array(
                'PMProGateway_fondy',
                'pmpro_checkout_default_submit_button'
            ));
            add_filter('pmpro_checkout_before_change_membership_level', array(
                'PMProGateway_fondy',
                'pmpro_checkout_before_change_membership_level'
            ), 10, 2);

            // add js to some admin pmp pages
            add_filter('pmpro_payment_option_fields', array('PMProGateway_fondy', 'addFondyAdminPageJS'), 11, 2);
            add_filter('pmpro_membership_level_after_other_settings', array('PMProGateway_fondy', 'addFondyAdminPageJS'));
        }
    }

    /**
     * Make sure fondy is in the gateways list
     *
     * @since 1.8
     */
    static function pmpro_gateways($gateways)
    {
        if (empty($gateways['fondy'])) {
            $gateways['fondy'] = __('Fondy', 'pmp-fondy-payment');
        }

        return $gateways;
    }

    static function pmpro_required_billing_fields($fields)
    {
        //unset($fields['bfirstname']);
        //unset($fields['blastname']);
        unset($fields['baddress1']);
        unset($fields['bcity']);
        unset($fields['bstate']);
        unset($fields['bzipcode']);
        //unset($fields['bphone']);
        unset($fields['bemail']);
        unset($fields['bcountry']);
        unset($fields['CardType']);
        unset($fields['AccountNumber']);
        unset($fields['ExpirationMonth']);
        unset($fields['ExpirationYear']);
        unset($fields['CVV']);

        return $fields;
    }

    /**
     * Get a list of payment options that the fondy gateway needs/supports.
     *
     * @since 1.8
     */
    static function getGatewayOptions()
    {
        $options = array(
            'sslseal',
            'nuclear_HTTPS',
            'gateway_environment',
            'fondy_merchantid',
            'fondy_securitykey',
            'currency',
            'use_ssl',
            'tax_state',
            'tax_rate',
            'accepted_credit_cards'
        );

        return $options;
    }

    /**
     * Set payment options for payment settings page.
     *
     * @since 1.8
     */
    static function pmpro_payment_options($options)
    {
        //get fondy options
        $fondy_options = PMProGateway_fondy::getGatewayOptions();

        //merge with others.
        $options = array_merge($fondy_options, $options);

        return $options;
    }

    /**
     * Display fields for fondy options.
     *
     * @param $values
     * @param $gateway
     */
    static function pmpro_payment_option_fields($values, $gateway)
    {
        include( PMPRO_FONDY_DIR .'/views/payment-option-fields.php' );
    }

    /**
     * Swap in our submit buttons.
     *
     * @since 1.8
     */
    static function pmpro_checkout_default_submit_button($show)
    {
        $text_domain = 'pmpro';

        if (version_compare('1.8.13.6', PMPRO_VERSION, '<=')) {
            $text_domain = 'paid-memberships-pro';
        }

        include( PMPRO_FONDY_DIR .'/views/submit-button.php' );

        //don't show the default
        return false;
    }

    /**
     * @param $user_id
     * @param $morder
     */
    static function pmpro_checkout_before_change_membership_level($user_id, $morder)
    {
        global $discount_code_id, $wpdb;

        //if no order, no need to pay
        if (empty($morder)) {
            return;
        }

        $morder->user_id = $user_id;
        $morder->saveOrder();

        //save discount code use
        if (!empty($discount_code_id)) {
            $wpdb->query("INSERT INTO $wpdb->pmpro_discount_codes_uses (code_id, user_id, order_id, timestamp) VALUES('" . $discount_code_id . "', '" . $user_id . "', '" . $morder->id . "', now())");
        }

        do_action("pmpro_before_send_to_fondy", $user_id, $morder);

        $morder->Gateway->sendToFondy($morder);
    }


    public function __construct($gateway = NULL)
    {
        $this->isTestEnv = pmpro_getOption( "gateway_environment" ) === 'sandbox';

        parent::__construct($gateway);
    }

    /**
     * add plugin setting button
     *
     * @param $links
     * @return mixed
     */
    public function plugin_action_links($links)
    {
        $settings_link = '<a href="'. admin_url('admin.php?page=pmpro-paymentsettings') .'">'. __("Settings") .'</a>';
        array_unshift( $links, $settings_link );

        return $links;
    }

    /**
     * add plugin row buttons
     *
     * @param $links
     * @param $file
     * @return array
     */
    public function plugin_row_meta($links, $file)
    {
        if(strpos($file, basename(PMPRO_FONDY_BASE_FILE)) !== false) {
            $row_links = array(
                '<a href="https://fondy.eu/en/cms/wordpress/wordpress-paid-membership-pro/" title="' . __('View Documentation', 'pmp-fondy-payment') . '">' . __('Docs', 'pmp-fondy-payment') . '</a>',
            );
            $links = array_merge( $links, $row_links );
        }

        return $links;
    }

    /**
     * add js to admin page
     *
     * @since 1.0.6
     */
    public function addFondyAdminPageJS()
    {
        wp_enqueue_script(
            'fondy-pmp',
            plugins_url('assets/js/fondy.js', plugin_basename(PMPRO_FONDY_BASE_FILE)),
            [],
            PMPRO_FONDY_VERSION,
            true
        );

        if (sanitize_text_field($_REQUEST['page']) === 'pmpro-membershiplevels') {
            wp_localize_script('fondy-pmp', 'fondy_param', [
                'trialDescriptionText' => 'Fondy integration currently does not support trial amounts greater than 0.', //todo l10n
            ]);
        }
    }

    /**
     * @param $order
     * @return bool
     */
    public function process(&$order)
    {
        if (empty($order->code)) {
            $order->code = $order->getRandomCode();
        }

        //clean up a couple values
        $order->payment_type = "Fondy";
        $order->CardType = "";
        $order->cardtype = "";


        $order->status = "review";
        $order->saveOrder();

        return true;
    }

    /**
     * @param MemberOrder $order
     */
    public function sendToFondy(&$order)
    {
        global $pmpro_currency;

        //taxes on initial amount
        $initial_payment = $order->InitialPayment;
        $initial_payment_tax = $order->getTaxForPrice($initial_payment);
        $initial_payment = round((float)$initial_payment + (float)$initial_payment_tax, 2);

        if (empty($order->code))
            $order->code = $order->getRandomCode();

        $fondy_args = array(
            'merchant_data' => json_encode(array(
                'name' => $order->billing->name,
                'phone' => $order->billing->phone
            )),
            'product_id' => $order->membership_id,
            'order_id' => $order->code . FondyForm::ORDER_SEPARATOR . time(),
            'merchant_id' => $this->isTestEnv ? FondyForm::TEST_MERCHANT_ID : pmpro_getOption("fondy_merchantid"),
            'order_desc' => substr($order->membership_level->name . " at " . get_bloginfo("name"), 0, 127),
            'amount' => round($initial_payment * 100),
            'currency' => $pmpro_currency,
            'response_url' => admin_url("admin-ajax.php") . "?action=fondy-ins",
            'server_callback_url' => admin_url("admin-ajax.php") . "?action=fondy-ins",
            'sender_email' => $order->Email,
            'verification' => $order->InitialPayment === 0.0 ? 'Y' : 'N',
        );

        if (pmpro_isLevelRecurring($order->membership_level)) {
            $fondy_args['required_rectoken'] = 'Y';
            $fondy_args['recurring_data'] = $this->getRecurringData($order);
            $fondy_args['subscription'] = 'Y';
            $fondy_args['subscription_callback_url'] = admin_url("admin-ajax.php") . "?action=fondy-ins";

            //filter order before subscription. use with care.
            $order = apply_filters("pmpro_subscribe_order", $order, $this);
            $order->subscription_transaction_id = $order->code;
        }

        $order->status = "pending";
        $order->payment_transaction_id = $order->code;
        //update order
        $order->saveOrder();

        $response = $this->sendRequest($fondy_args);
        $body = wp_remote_retrieve_body($response);
        $code = wp_remote_retrieve_response_code($response);
        $message = wp_remote_retrieve_response_message($response);
        $out = json_decode($body, true);

        if ($code === 200 && $message === 'OK') {
            if (is_string($out)) {
                wp_parse_str($out, $out);
            }

            if (isset($out['response']['error_message'])) {
                $error = '<p>' . __('Error message: ', 'pmp-fondy-payment') . ' ' . $out['response']['error_message'] . '</p>';
                $error .= '<p>' . __('Error code: ', 'pmp-fondy-payment') . ' ' . $out['response']['error_code'] . '</p>';

                wp_die($error, __('Error'), array('response' => '401'));
            } else {
                $url = json_decode(base64_decode($out['response']['data']), true)['order']['checkout_url'];
                wp_redirect($url);
                exit;
            }
        }

        exit; //mb add error handler
    }

    /**
     * @param MemberOrder $order
     * @return array
     */
    private function getRecurringData($order)
    {
        $every = intval($order->BillingFrequency);
        $period = strtolower($order->BillingPeriod);
        $startTS = strtotime('+ ' . $every . ' ' . $period);

        if ($order->BillingPeriod === 'Year'){ // fondy doesn't have 'year' period
            $every *= 12;
            $period = 'month';
            $startTS = strtotime('+ 1 month');
        }

        $recurringData =  array(
            'start_time' => date('Y-m-d', $startTS),
            'amount' => round($order->PaymentAmount * 100),
            'every' => $every,
            'period' => $period,
            'state' => 'shown_readonly',
            'readonly' => 'Y'
        );

        if (!empty($order->TotalBillingCycles)){
            $recurringData["quantity"] = intval($order->TotalBillingCycles);
        }

        if (pmpro_isLevelTrial($order->membership_level)){
            $trialPeriod = strtolower($order->TrialBillingPeriod);
            $trialQuantity = intval($order->TrialBillingCycles);

            if ($order->TrialBillingPeriod === 'Year'){
                $trialPeriod = 'month';
                $trialQuantity *= 12;
            }

            //$recurringData["trial_amount"] = $order->TrialAmount; // w8 api realisation
            $recurringData["trial_period"] = $trialPeriod;
            $recurringData["trial_quantity"] = $trialQuantity;
        }

        return $recurringData;
    }

    /**
     * @param array $args
     * @return array|WP_Error
     */
    private function sendRequest($args)
    {
        $secretKey = $this->isTestEnv ? FondyForm::TEST_MERCHANT_KEY : pmpro_getOption("fondy_securitykey");

        $fields = [
            "version" => "2.0",
            "data" => base64_encode(json_encode(array('order' => $args))),
            "signature" => FondyForm::getSignature($args, $secretKey)
        ];

        $response = wp_remote_post(FondyForm::API_CHECKOUT_URL, array(
                'headers' => array('Content-Type' => 'application/json'),
                'timeout' => 45,
                'method' => 'POST',
                'sslverify' => true,
                'httpversion' => '1.1',
                'body' => json_encode(array('request' => $fields))
            )
        );

        if (is_wp_error($response)) {
            $error = '<p>' . __('An unidentified error occurred.', 'pmp-fondy-payment') . '</p>';
            $error .= '<p>' . $response->get_error_message() . '</p>';

            wp_die($error, __('Error'), array('response' => '401'));
        }

        return $response;
    }
}
