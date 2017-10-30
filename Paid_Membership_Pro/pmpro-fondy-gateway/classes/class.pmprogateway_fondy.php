<?php
//load classes init method
add_action( 'init', array( 'PMProGateway_fondy', 'init' ) );
require_once( dirname( __FILE__ ) . "/fondy.lib.php" );

/**
 * PMProGateway_gatewayname Class
 *
 * Handles fondy integration.
 *
 */
class PMProGateway_fondy extends PMProGateway {
	function PMProGateway( $gateway = null ) {
		$this->gateway = $gateway;

		return $this->gateway;
	}

	/**
	 * Run on WP init
	 *
	 * @since 1.8
	 */
	static function init() {
		global $wpdb;
		$result = $wpdb->query( "SELECT fondy_token from `$wpdb->pmpro_membership_orders` LIMIT 1" );
		if ( ! $result ) {
			$wpdb->query( "ALTER TABLE $wpdb->pmpro_membership_orders ADD fondy_token TEXT" );
		}


		//make sure fondy is a gateway option
		add_filter( 'pmpro_gateways', array( 'PMProGateway_fondy', 'pmpro_gateways' ) );

		//add fields to payment settings
		add_filter( 'pmpro_payment_options', array( 'PMProGateway_fondy', 'pmpro_payment_options' ) );
		add_filter( 'pmpro_payment_option_fields', array(
			'PMProGateway_fondy',
			'pmpro_payment_option_fields'
		), 10, 2 );

		//code to add at checkout if fondy is the current gateway
		$gateway = pmpro_getOption( "gateway" );
		$gateway = pmpro_getGateway();
		if ( $gateway == "fondy" ) {
			//add_filter('pmpro_include_billing_address_fields', '__return_false');
			add_filter( 'pmpro_include_payment_information_fields', '__return_false' );
			add_filter( 'pmpro_required_billing_fields', array(
				'PMProGateway_fondy',
				'pmpro_required_billing_fields'
			) );
			add_filter( 'pmpro_checkout_default_submit_button', array(
				'PMProGateway_fondy',
				'pmpro_checkout_default_submit_button'
			) );
			add_filter( 'pmpro_checkout_before_change_membership_level', array(
				'PMProGateway_fondy',
				'pmpro_checkout_before_change_membership_level'
			), 10, 2 );
		}
	}

	/**
	 * Make sure fondy is in the gateways list
	 *
	 * @since 1.8
	 */
	static function pmpro_gateways( $gateways ) {
		if ( empty( $gateways['fondy'] ) ) {
			$gateways['fondy'] = __( 'fondy', 'pmpro' );
		}

		return $gateways;
	}

	/**
	 * Get a list of payment options that the fondy gateway needs/supports.
	 *
	 * @since 1.8
	 */
	static function getGatewayOptions() {
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
	static function pmpro_payment_options( $options ) {
		//get fondy options
		$fondy_options = PMProGateway_fondy::getGatewayOptions();

		//merge with others.
		$options = array_merge( $fondy_options, $options );

		return $options;
	}

	/**
	 * Display fields for fondy options.
	 *
	 * @since 1.8
	 */
	static function pmpro_payment_option_fields( $values, $gateway ) {
		?>
        <tr class="pmpro_settings_divider gateway gateway_fondy"
		    <?php if ( $gateway != "fondy" ) { ?>style="display: none;"<?php } ?>>
            <td colspan="2">
				<?php _e( 'Fondy Settings', 'fondy' ); ?>
            </td>
        </tr>
        <tr class="gateway gateway_fondy" <?php if ( $gateway != "fondy" ) { ?>style="display: none;"<?php } ?>>
            <th scope="row" valign="top">
                <label for="fondy_merchantid"><?php _e( 'Merchant ID', 'fondy' ); ?>:</label>
            </th>
            <td>
                <input type="text" id="fondy_merchantid" name="fondy_merchantid" size="60"
                       value="<?php echo esc_attr( $values['fondy_merchantid'] ) ?>"/>
            </td>
        </tr>
        <tr class="gateway gateway_fondy" <?php if ( $gateway != "fondy" ) { ?>style="display: none;"<?php } ?>>
            <th scope="row" valign="top">
                <label for="fondy_securitykey"><?php _e( 'Secret Key', 'fondy' ); ?>:</label>
            </th>
            <td>
                <textarea id="fondy_securitykey" name="fondy_securitykey" rows="3"
                          cols="80"><?php echo esc_textarea( $values['fondy_securitykey'] ); ?></textarea>
            </td>
        </tr>
		<?php
	}

	static function pmpro_required_billing_fields( $fields ) {
		//unset($fields['bfirstname']);
		//unset($fields['blastname']);
		unset( $fields['baddress1'] );
		unset( $fields['bcity'] );
		unset( $fields['bstate'] );
		unset( $fields['bzipcode'] );
		//unset($fields['bphone']);
		unset( $fields['bemail'] );
		unset( $fields['bcountry'] );
		unset( $fields['CardType'] );
		unset( $fields['AccountNumber'] );
		unset( $fields['ExpirationMonth'] );
		unset( $fields['ExpirationYear'] );
		unset( $fields['CVV'] );

		return $fields;
	}

	/**
	 * Swap in our submit buttons.
	 *
	 * @since 1.8
	 */
	static function pmpro_checkout_default_submit_button( $show ) {
		global $gateway, $pmpro_requirebilling;

		//show our submit buttons
		?>

        <span id="pmpro_fondy_checkout"
		      <?php if ( ( $gateway != "fondy" ) || ! $pmpro_requirebilling ) { ?>style="display: none;"<?php } ?>>
				<input type="hidden" name="submit-checkout" value="1"/>
				<input type="submit" class="pmpro_btn pmpro_btn-submit-checkout"
                       value="<?php if ( $pmpro_requirebilling ) {
					       _e( 'Submit and Check Out', 'pmpro' );
				       } else {
					       _e( 'Submit and Confirm', 'pmpro' );
				       } ?> &raquo;"/>
		</span>
		<?php

		//don't show the default
		return false;
	}

	static function pmpro_checkout_before_change_membership_level( $user_id, $morder ) {
		global $discount_code_id, $wpdb;

		//if no order, no need to pay
		if ( empty( $morder ) ) {
			return;
		}

		$morder->user_id = $user_id;
		$morder->saveOrder();

		//save discount code use
		if ( ! empty( $discount_code_id ) ) {
			$wpdb->query( "INSERT INTO $wpdb->pmpro_discount_codes_uses (code_id, user_id, order_id, timestamp) VALUES('" . $discount_code_id . "', '" . $user_id . "', '" . $morder->id . "', now())" );
		}

		do_action( "pmpro_before_send_to_fondy", $user_id, $morder );

		$morder->Gateway->sendToFondy( $morder );
	}

	function process( &$order ) {

		if ( empty( $order->code ) ) {
			$order->code = $order->getRandomCode();
		}

		//clean up a couple values
		$order->payment_type = "Fondy";
		$order->CardType     = "";
		$order->cardtype     = "";


		$order->status = "review";
		$order->saveOrder();

		return true;
	}

	function sendToFondy( &$order ) {
		global $pmpro_currency;
		global $wpdb;

		//taxes on initial amount
		$initial_payment     = $order->InitialPayment;
		$initial_payment_tax = $order->getTaxForPrice( $initial_payment );
		$initial_payment     = round( (float) $initial_payment + (float) $initial_payment_tax, 2 );

		switch ( $order->BillingPeriod ) {
			case 'Day':
				$period = 'day';
				break;
			case 'Week':
				$period = 'week';
				break;
			case 'Month':
				$period = 'month';
				break;
			case 'Year':
				$period = 'month';
				break;
		}
		$recurringDiscount = true;
		if ( ! empty ( $order->discount_code ) ) {
			//check to see whether or not it is a recurring discount code
			if ( isset( $order->TotalBillingCycles ) ) {
				$recurringDiscount = true;
			} else {
				$recurringDiscount = false;
			}
		}
		$fondy_args = array(
			'merchant_data'       => json_encode( array(
				'name'  => $order->billing->name,
				'phone' => $order->billing->phone
			) ),
			'product_id'          => $order->membership_id,
			'order_id'            => $order->code . FondyForm::ORDER_SEPARATOR . time(),
			'merchant_id'         => pmpro_getOption( "fondy_merchantid" ),
			'order_desc'          => substr( $order->membership_level->name . " at " . get_bloginfo( "name" ), 0, 127 ),
			'amount'              => round( $initial_payment * 100 ),
			'currency'            => $pmpro_currency,
			'response_url'        => admin_url( "admin-ajax.php" ) . "?action=fondy-ins",
			'server_callback_url' => admin_url( "admin-ajax.php" ) . "?action=fondy-ins",
			'sender_email'        => $order->Email

		);
		if ( ! empty( $period ) && ! empty( $recurringDiscount ) ) {
			$fondy_args['required_rectoken'] = 'Y';
			$fondy_args['recurring_data'] =
				array(
					'start_time' => date( 'Y-m-d', strtotime( '+ ' . intval( $order->BillingFrequency ) . ' ' . $period ) ),
					'amount'     => $order->PaymentAmount * 100,
					'every'      => intval( $order->BillingFrequency ),
					'period'     => $period,
					'state'      => 'y',
					'readonly'   => 'y'
				);
			if ( $order->BillingPeriod == 'Year' ) {
				$fondy_args['recurring_data']['start_time'] = date( 'Y-m-d', strtotime( '+ ' . ( intval( $order->BillingFrequency ) * 12 ) . ' ' . 'month' ) );
				$fondy_args['recurring_data']['every']      = intval( $order->BillingFrequency ) * 12;
				$fondy_args['recurring_data']['period']     = 'month';
			}
			$fondy_args['subscription']              = 'Y';
			$fondy_args['subscription_callback_url'] = admin_url( "admin-ajax.php" ) . "?action=fondy-ins";
			if ( empty( $order->code ) ) {
				$order->code = $order->getRandomCode();
			}
			//filter order before subscription. use with care.
			$order = apply_filters( "pmpro_subscribe_order", $order, $this );
			//taxes on the amount
			$amount     = $order->PaymentAmount;
			$amount_tax = $order->getTaxForPrice( $amount );

			$order->status                      = "pending";
			$order->payment_transaction_id      = $order->code;
			$order->subscription_transaction_id = $order->code;
			//update order
			$order->saveOrder();
		}
		$url = 'https://api.fondy.eu/api/checkout/url/';

		$fields = [
			"version"   => "2.0",
			"data"      => base64_encode( json_encode( array( 'order' => $fondy_args ) ) ),
			"signature" => sha1( pmpro_getOption( "fondy_securitykey" ) . '|' . base64_encode( json_encode( array( 'order' => $fondy_args ) ) ) )
		];

		$request = wp_remote_post( $url, array(
				'headers'     => array( 'Content-Type' => 'application/json' ),
				'timeout'     => 45,
				'method'      => 'POST',
				'sslverify'   => true,
				'httpversion' => '1.1',
				'body'        => json_encode( array( 'request' => $fields ) )
			)
		);
		$body    = wp_remote_retrieve_body( $request );
		$code    = wp_remote_retrieve_response_code( $request );
		$message = wp_remote_retrieve_response_message( $request );
		$out     = json_decode( $body, true );


		if ( is_wp_error( $request ) ) {

			$error = '<p>' . __( 'An unidentified error occurred.', 'fondy' ) . '</p>';
			$error .= '<p>' . $request->get_error_message() . '</p>';

			wp_die( $error, __( 'Error', 'fondy' ), array( 'response' => '401' ) );

		} elseif ( 200 == $code && 'OK' == $message ) {

			if ( is_string( $out ) ) {
				wp_parse_str( $out, $out );
			}
			if ( isset( $out['response']['error_message'] ) ) {

				$error = '<p>' . __( 'Error message: ', 'fondy' ) . ' ' . $out['response']['error_message'] . '</p>';
				$error .= '<p>' . __( 'Error code: ', 'fondy' ) . ' ' . $out['response']['error_message'] . '</p>';

				wp_die( $error, __( 'Error', 'rcp' ), array( 'response' => '401' ) );

			} else {
				$url = json_decode( base64_decode( $out['response']['data'] ), true )['order']['checkout_url'];
				wp_redirect( $url );
				exit;

			}
		}


		exit;
	}


}