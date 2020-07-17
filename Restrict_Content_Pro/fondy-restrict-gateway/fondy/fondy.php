<?php
if (!class_exists('Restrict_Content_Pro')) {
    return;
}

class RCP_Payment_Gateway_Fondy extends RCP_Payment_Gateway
{
    private $api_endpoint;

    /**
     * Get things going
     *
     * @since 2.1
     */
    public function init()
    {
        global $rcp_options;
        $this->supports[] = 'one-time';
        $this->supports[] = 'recurring';
        $this->supports[] = 'fees';
        $this->supports[] = 'trial';
        $this->api_endpoint = 'https://api.fondy.eu/api/checkout/url/';

        if (!class_exists('Fondy_API')) {
            require_once RCP_FONDY_DIR . '/fondy/fondy.inc.php';
        }
    }

    /**
     * Process registration
     *
     * @since 2.1
     */
    public function process_signup()
    {
        global $rcp_options;
        global $rcp_fondy_options;
        global $rcp_payments_db;

        if ($this->auto_renew) {
            $amount = $this->initial_amount;
        } else {
            $amount = $this->initial_amount;
        }

        $member = new RCP_Member($this->user_id);

        if ($this->is_trial()) {
            $amount = 1;
        }
        /**
         * Cancel existing subscription if the member just upgraded to another one.
         */
        if ($member->just_upgraded() && $member->can_cancel()) {
            $cancelled = $member->cancel_payment_profile(false);
        }

        if ($amount == 0) {
            if ($this->payment->fees != 0) {
                $amount = $this->payment->fees;
            } else {
                $error = '<p>' . __('Ошибка, для смены тарифа свяжитесь с администратором', 'fondy_rcp') . '</p>';
                wp_die($error, __('Error', 'fondy_rcp'), array('response' => '401'));
            }
        }

        $return = add_query_arg(array('rcp-confirm' => 'fondy', 'membership_id' => $this->membership->get_id()), get_permalink($rcp_options['registration_page']));
        if (isset($this->return_url)) {
            $return = $this->return_url;
        }

        $fondy_args = array(
            'order_id' => $this->payment->id . '#' . $member->ID . '#' . time(),
            'merchant_id' => $rcp_fondy_options['fondy_merchant_id'],
            'order_desc' => $this->subscription_name,
            'amount' => round($amount * 100),
            'merchant_data' => json_encode(array(
                'subtotal' => $this->amount,
                'discount_amount' => $this->payment->discount_amount,
                'subscription_key' => $this->payment->subscription_key,
                'signup_fee' => $this->signup_fee
            )),
            'currency' => $this->currency,
            'server_callback_url' => add_query_arg('listener', 'fondy', home_url('index.php')),
            'response_url' => $return,
            'sender_email' => $member->user_email,
            'verification' => $this->is_trial() ? 'y' : 'n'
        );

        if ($rcp_fondy_options['fondy_reccuring'] == true and ($this->auto_renew || $this->is_trial())) {
            $fondy_args['recurring_data'] = array(
                'start_time' => date('Y-m-d', strtotime($this->is_trial() ? $this->subscription_start_date : '+ ' . $this->subscription_data['length'] . ' ' . $this->subscription_data['length_unit'])),
                'amount' => round($this->subscription_data['recurring_price'] * 100),
                'every' => intval($this->subscription_data['length']),
                'period' => $this->subscription_data['length_unit'],
                'state' => 'y',
                'readonly' => 'y'
            );
            if ($this->subscription_data['length_unit'] == 'year') {
                $fondy_args['recurring_data']['start_time'] = date('Y-m-d', strtotime('+ ' . (intval($this->subscription_data['length']) * 12) . ' ' . 'month'));
                $fondy_args['recurring_data']['every'] = intval($this->subscription_data['length']) * 12;
                $fondy_args['recurring_data']['period'] = 'month';
            }
            $fondy_args['subscription'] = 'Y';
            $fondy_args['subscription_callback_url'] = add_query_arg('listener', 'fondy', home_url('index.php'));
        } else {
            $fondy_args['subscription'] = 'N';
        }

        $fields = [
            "version" => "2.0",
            "data" => base64_encode(json_encode(array('order' => $fondy_args))),
            "signature" => sha1($rcp_fondy_options['fondy_secret'] . '|' . base64_encode(json_encode(array('order' => $fondy_args))))
        ];

        $request = wp_remote_post($this->api_endpoint, array(
                'headers' => array('Content-Type' => 'application/json'),
                'timeout' => 45,
                'method' => 'POST',
                'sslverify' => true,
                'httpversion' => '1.1',
                'body' => json_encode(array('request' => $fields))
            )
        );
        $body = wp_remote_retrieve_body($request);
        $code = wp_remote_retrieve_response_code($request);
        $message = wp_remote_retrieve_response_message($request);
        $out = json_decode($body, true);


        if (is_wp_error($request)) {

            $error = '<p>' . __('An unidentified error occurred.', 'fondy_rcp') . '</p>';
            $error .= '<p>' . $request->get_error_message() . '</p>';

            wp_die($error, __('Error', 'fondy_rcp'), array('response' => '401'));

        } elseif (200 == $code && 'OK' == $message) {

            if (is_string($out)) {
                wp_parse_str($out, $out);
            }
            if (isset($out['response']['error_message'])) {

                $error = '<p>' . __('Error message: ', 'fondy_rcp') . ' ' . $out['response']['error_message'] . '</p>';
                $error .= '<p>' . __('Error code: ', 'fondy_rcp') . ' ' . $out['response']['error_message'] . '</p>';

                wp_die($error, __('Error', 'fondy_rcp'), array('response' => '401'));

            } else {
                $url = json_decode(base64_decode($out['response']['data']), true)['order']['checkout_url'];
                wp_redirect($url);
                exit;

            }
        }
    }

    /**
     * Add credit card form
     *
     * @return string
     * @since 2.1
     */
    public function fields()
    {
        $currency = get_option('rcp_settings')['currency'] ?? 'USD';

        return ($_POST['level_has_trial']) === 'true' ? sprintf(__('Вы будете перенаправлены на страницу оплаты для привязки карты<br>При этом будет удержана сумма 1 %s с последующим возвращением', 'fondy_rcp'), $currency) : __('Вы будете перенаправлены на страницу оплаты', 'fondy_rcp');
    }

    /**
     * Validate additional fields during registration submission
     *
     * @since 2.1
     */
    public function validate_fields()
    {
        return true;
    }

    /**
     * Process webhooks
     *
     * @since 2.1
     */
    public function process_webhooks()
    {
        if (!isset($_GET['listener']) || $_GET['listener'] != 'fondy') {
            return;
        }

        global $rcp_fondy_options;

        rcp_log('Starting to process Fondy webhook.');

        if (empty($_POST)) {
            $callback = json_decode(file_get_contents("php://input"));

            if (empty($callback)) {
                die('go away!');
            }

            $_POST = [];

            foreach ($callback as $key => $val) {
                $_POST[esc_sql($key)] = esc_sql($val);
            }
        }

        $posted = apply_filters('rcp_ipn_post', $_POST);
        $sign = $posted['signature'];

        if (isset($posted['data'])) { // new protocol
            $base64_data = $posted['data'];
            $posted = json_decode(base64_decode($posted['data']), true)['order'];
        }

        $fondySettings = array(
            'mid' => $rcp_fondy_options['fondy_merchant_id'],
            'secret_key' => $rcp_fondy_options['fondy_secret']
        );
        $paymentInfo = Fondy_API::isPaymentValid($fondySettings, $posted, $base64_data ?? '', $sign);

        if ($paymentInfo === true) {
            $exploded = explode('#', $posted['parent_order_id'] ?: $posted['order_id']);
            $user_id = $exploded[1];

            if (empty($user_id) && !empty($posted['sender_email'])) {
                $user = get_user_by('email', $posted['sender_email']);
                $user_id = $user ? $user->ID : false;
            }

            $member = new RCP_Member($user_id);

            if (!$member || !$member->ID > 0) {
                rcp_log('Fondy - member ID not found.');

                die('no member found');
            }

            $subscription_id = $member->get_pending_subscription_id();

            if (empty($subscription_id)) {
                $subscription_id = $member->get_subscription_id();
            }
            if (!$subscription_id) {
                rcp_log('Fondy - no subscription ID for member.');
                die('no subscription for member found');
            }

            if (!$subscription_level = rcp_get_subscription_details($subscription_id)) {
                rcp_log('Fondy - no subscription level found.');
                die('no subscription level found');
            }
            $data = (json_decode(stripslashes($posted['merchant_data']), true));
            $amount = $posted['amount'] / 100;
            if ($data['signup_fee']) {
                $fee = $data['signup_fee'];
            } else {
                $fee = 0;
            }
            if ($data['discount_amount']) {
                $discount = $data['discount_amount'];
            } else {
                $discount = 0;
            }
            if ($data['subtotal']) {
                $subtotal = $data['subtotal'];
            } else {
                $subtotal = 0;
            }

            $payment_data = array(
                'date' => date('Y-m-d H:i:s', strtotime($posted['order_time'])),
                'subscription' => $subscription_level->name,
                'payment_type' => 'Fondy Credit Card',
                'subscription_key' => $member->get_subscription_key(),
                'amount' => $member->is_trialing() ? 0 : $amount,
                'fees' => $amount,
                'subtotal' => $subtotal,
                'discount_amount' => $discount,
                'user_id' => $user_id,
                'gateway' => 'Fondy',
                'transaction_id' => $posted['payment_id'] ?? '',
                'status' => 'complete'
            );
            $rcp_payments = new RCP_Payments();

            rcp_log(sprintf('Processing Fondy. Payment status: %s', $posted['order_status']));

            switch (strtolower($posted['order_status'])) :
                case 'approved':
                    if ($member->just_upgraded() && $member->can_cancel()) {
                        $cancelled = $member->cancel_payment_profile(false);

                        if ($cancelled) {
                            $member->set_payment_profile_id('');
                        }
                    }

                    if ($pending_id = $member->get_pending_payment_id()) { // has pending payment, just update
                        $rcp_payments->update($pending_id, $payment_data);

                        do_action('rcp_gateway_payment_processed', $member, $pending_id, $this);
                    } elseif (isset($posted['parent_order_id']) && !$rcp_payments->payment_exists($payment_data['transaction_id'])) { // recurring and payment already exists
                        $payment_data['transaction_type'] = 'renewal';
                        $payment_id = $rcp_payments->insert($payment_data);

                        $member->renew(true);

                        do_action('rcp_webhook_recurring_payment_processed', $member, $payment_id, $this);
                        do_action('rcp_gateway_payment_processed', $member, $payment_id, $this);
                    }

                    break;
                case 'declined' :
                    rcp_log('Processing Fondy declined webhook.');
                    $member->cancel();
                    die('payment declined');
                case 'expired' :
                    rcp_log('Processing Fondy expired webhook.');

                    $member->set_status('expired');

                    $member->set_expiration_date(date('Y-m-d H:i:s', strtotime($posted['order_time'])));

                    $member->add_note(__('Subscription expired in Fondy', 'fondy_rcp'));

                    die('member expired');
                    break;
            endswitch;

        } else {
            rcp_log('Error: ' . $paymentInfo);
            die;
        }
        die;
    }
}