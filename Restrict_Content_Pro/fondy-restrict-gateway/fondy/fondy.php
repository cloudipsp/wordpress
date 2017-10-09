<?php

class RCP_Payment_Gateway_Fondy extends RCP_Payment_Gateway {
    private $api_endpoint;
    /**
     * Get things going
     *
     * @since 2.1
     */
    public function init() {
        global $rcp_options;
        $this->supports[]  = 'one-time';
        $this->supports[]  = 'recurring';
        $this->supports[]  = 'fees';
        $this->api_endpoint = 'https://api.fondy.eu/api/checkout/url/';
        if( ! class_exists( 'Fondy_API' ) ) {
            require_once RCP_FONDY_DIR . '/fondy/fondy.inc.php';
        }
    }
    /**
     * Process registration
     *
     * @since 2.1
     */
    public function process_signup() {
        global $rcp_options;
        if( $this->auto_renew ) {
            $amount = $this->initial_amount;
        } else {
            $amount = $this->initial_amount;
        }
        $member = new RCP_Member( $this->user_id );

        if($amount == 0) {
            if($this->payment->fees != 0) {
                $amount = $this->payment->fees;
            }else {
                $error = '<p>' . __('Ошибка для смены тарифа свяжитесь с администратором', 'rcp') . '</p>';
                wp_die($error, __('Error', 'rcp'), array('response' => '401'));
            }
        }

        $fondy_args = array(
            'order_id' => $this->payment->id . '#' . $member->ID,
            'merchant_id' => $rcp_options['fondy_merchant_id'],
            'order_desc' => $this->subscription_name,
            'amount' => round($amount*100),
            'merchant_data' => json_encode(array(
                'subtotal' => $this->amount,
                'discount_amount' => $this->payment->discount_amount,
                'subscription_key' => $this->payment->subscription_key,
                'signup_fee' =>$this->signup_fee
             )),
            'currency' =>  $this->currency,
            'server_callback_url' => add_query_arg( 'listener', 'fondy', home_url( 'index.php' ) ),
            'response_url' => get_permalink( $rcp_options['registration_page'] ),
            'sender_email' => $member->user_email
        );

        if ($rcp_options['fondy_reccuring'] == true and $this->auto_renew){
            $fondy_args['subscription'] = 'Y';
            $fondy_args['subscription_callback_url'] = add_query_arg( 'listener', 'fondy', home_url( 'index.php' ) );
        }else{
            $fondy_args['subscription'] = 'N';
        }

        $fondy_args['signature'] = Fondy_API::getSignature($fondy_args, $rcp_options['fondy_secret']);

        $request = wp_remote_post( $this->api_endpoint, array( 'timeout' => 45, 'sslverify' => false, 'httpversion' => '1.1', 'body' => $fondy_args ) );
        $body    = wp_remote_retrieve_body( $request );
        $code    = wp_remote_retrieve_response_code( $request );
        $message = wp_remote_retrieve_response_message( $request );

        if( is_wp_error( $request ) ) {

            $error = '<p>' . __( 'An unidentified error occurred.', 'rcp' ) . '</p>';
            $error .= '<p>' . $request->get_error_message() . '</p>';

            wp_die( $error, __( 'Error', 'rcp' ), array( 'response' => '401' ) );

        } elseif ( 200 == $code && 'OK' == $message ) {

            if (is_string($body)) {
                wp_parse_str($body, $body);
            }
            if ($body['response_status'] == 'failure') {

                $error = '<p>' . __('Error message: ', 'rcp') . ' ' . $body['error_message'] . '</p>';
                $error .= '<p>' . __('Error code: ', 'rcp') . ' ' . $body['error_code'] . '</p>';

                wp_die($error, __('Error', 'rcp'), array('response' => '401'));

            } else {
                wp_redirect($body['checkout_url']);
                exit;

            }
        }
    }
    /**
     * Add credit card form
     *
     * @since 2.1
     * @return string
     */
    public function fields() {
       return __( 'Вы будете перенаправлены на страницу оплаты', 'rcp' );
    }
    /**
     * Validate additional fields during registration submission
     *
     * @since 2.1
     */
    public function validate_fields() {
        return true;
    }
    /**
     * Process webhooks
     *
     * @since 2.1
     */
    public function process_webhooks() {

        if( ! isset( $_GET['listener'] ) ||  $_GET['listener'] != 'fondy' ) {
            return;
        }
        global $rcp_options;
        global $rcp_payments_db;
        rcp_log( 'Starting to process Fondy webhook.' );

        if (empty($_POST)) {
            $callback = json_decode(file_get_contents("php://input"));
            if (empty($callback)) {
                die('go away!');
            }
            $_POST = array();
            foreach ($callback as $key => $val) {
                $_POST[$key] = $val;
            }
        }

        $posted  = apply_filters('rcp_ipn_post', $_POST );
        $fondySettings = array(
            'mid' => $rcp_options['fondy_merchant_id'],
            'secret_key' => $rcp_options['fondy_secret']
        );
        $paymentInfo = Fondy_API::isPaymentValid($fondySettings,$posted);
        if($paymentInfo === true){

            $user_id = explode('#', $posted['order_id'])[1];
            $trans_id = explode('#', $posted['order_id'])[0];
            if( empty( $user_id ) && ! empty( $posted['sender_email'] ) ) {
                $user    = get_user_by( 'email', $posted['sender_email'] );
                $user_id = $user ? $user->ID : false;
            }

            $member = new RCP_Member( $user_id );

            if( ! $member || ! $member->ID > 0 ) {
                rcp_log( 'Fondy - member ID not found.' );

                die( 'no member found' );
            }

            $subscription_id = $member->get_pending_subscription_id();

            if( empty( $subscription_id ) ) {
                $subscription_id = $member->get_subscription_id();
            }
            if( ! $subscription_id ) {
                rcp_log( 'Fondy - no subscription ID for member.' );
                die( 'no subscription for member found' );
            }

            if( ! $subscription_level = rcp_get_subscription_details( $subscription_id ) ) {
                rcp_log( 'Fondy - no subscription level found.' );
                die( 'no subscription level found' );
            }
            $data = (json_decode(stripslashes($posted['merchant_data']), TRUE));
            $amount = $posted['amount']/100;
            if ($data['signup_fee']){
                $fee = $data['signup_fee'];
            }else{
                $fee = 0;
            }
            if ($data['discount_amount']){
                $discount = $data['discount_amount'];
            }else{
                $discount = 0;
            }
            if ($data['subtotal']){
                $subtotal = $data['subtotal'];
            }else{
                $subtotal = 0;
            }
            $payment_data = array(
                'date'             => date( 'Y-m-d H:i:s', strtotime( $posted['order_time'] ) ),
                'subscription'     => $subscription_level->name,
                'payment_type'     => 'single',
                'subscription_key' => $data['subscription_key'],
                'amount'           => $amount,
                'fees'              => $amount,
                'subtotal'         => $subtotal,
                'discount_amount'  => $discount,
                'user_id'          => $user_id,
                'gateway'          => 'Fondy',
                'transaction_id'   => $trans_id,
                'status'           => 'complete'
            );
            $rcp_payments       = new RCP_Payments();


            $payment_f = $rcp_payments_db->get_payment( absint( $trans_id ) );

            rcp_log( sprintf( 'Processing Fondy. Payment status: %s', $posted['order_status'] ) );
            if ( $payment_f->amount != $amount) {
                rcp_log('Amoun is incorrect or order is updated');
                die;
            }

            switch ( strtolower( $posted['order_status'] ) ) :
                case 'approved' :

                    if( $member->just_upgraded() && $member->can_cancel() ) {
                        $cancelled = $member->cancel_payment_profile( false );
                        if( $cancelled ) {

                            $member->set_payment_profile_id( '' );

                        }
                    }

                    if ( empty( $payment_data['transaction_id'] ) || $rcp_payments->payment_exists( $payment_data['transaction_id'] ) ) {
                        rcp_log( sprintf( 'Not inserting Fondy web_accept payment. Transaction ID not given or payment already exists. ID: %s', $payment_data['transaction_id'] ) );
                        $rcp_payments->update($payment_data['transaction_id'], $payment_data);
                    } elseif(!empty($posted['rectoken'])) {
                        $member->set_payment_profile_id( $posted['rectoken'] );
                        $pending_payment_id = $member->get_pending_payment_id();
                        if ( ! empty( $pending_payment_id ) ) {

                            $payment_id = $pending_payment_id;
                            $member->set_recurring( true );

                            // This activates the membership.
                            $rcp_payments->update( $pending_payment_id, $payment_data );

                        } else {

                            $payment_id = $rcp_payments->insert( $payment_data );

                            $expiration = date( 'Y-m-d 23:59:59', strtotime( $posted['next_payment_date'] ) );
                            $member->renew( $member->is_recurring(), 'active', $expiration );

                        }
                        do_action( 'rcp_webhook_recurring_payment_processed', $member, $payment_id, $this );
                        do_action( 'rcp_gateway_payment_processed', $member, $payment_id, $this );
                    }else{
                        $payment_id = $rcp_payments->insert( $payment_data );
                        do_action( 'rcp_webhook_recurring_payment_processed', $member, $payment_id, $this );
                        do_action( 'rcp_gateway_payment_processed', $member, $payment_id, $this );
                    }
                    break;

                case 'declined' :
                case 'expired' :
                    break;

            endswitch;
        }else{

            rcp_log( 'Error: ' . $paymentInfo );
            die;
        }
        die;
    }
}