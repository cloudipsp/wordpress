<?php

/**
 * Gateway class
 */
if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
	return;
}

class WC_Fondy extends WC_Payment_Gateway {

	const ORDER_APPROVED = 'approved';
	const ORDER_DECLINED = 'declined';
	const SIGNATURE_SEPARATOR = '|';
	const ORDER_SEPARATOR = ":";

	public function __construct() {
		$this->id                 = 'fondy';
		$this->method_title       = 'Fondy';
		$this->method_description = __( 'Fondy payment', 'woocommerce-fondy' );
		$this->has_fields         = false;
		$this->init_form_fields();
		$this->init_settings();
		if ( $this->get_option('showlogo') == "yes" ) {
			$this->icon = FONDY_BASE_PATH . 'assets/img/logo.png';
		}
		$this->liveurl          = 'https://api.fondy.eu/api/checkout/redirect/';
        $this->title = $this->get_option('title');
        $this->redirect_page_id = $this->get_option('redirect_page_id');
        $this->merchant_id = $this->get_option('merchant_id');
        $this->salt = $this->get_option('salt');
        $this->description = $this->get_option('description');
        $this->page_mode = $this->get_option('page_mode');

        $this->msg['message']   = "";
		$this->msg['class']     = "";
		$this->supports         = array(
			'products',
			'subscriptions',
			'subscription_reactivation',
			'subscription_amount_changes',
			'subscription_suspension'
		);
		if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
			/* 2.0.0 */
			add_action( 'woocommerce_api_' . strtolower( get_class( $this ) ), array(
				$this,
				'check_fondy_response'
			) );
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array(
				$this,
				'process_admin_options'
			) );
		} else {
			/* 1.6.6 */
			add_action( 'init', array( &$this, 'check_fondy_response' ) );
			add_action( 'woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) );
		}

		add_action( 'woocommerce_receipt_fondy', array( &$this, 'receipt_page' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'fondy_checkout_scripts' ) );
	}

	/**
	 * Enqueue checkout page scripts
	 */
	public function fondy_checkout_scripts() {
		if ( is_checkout() ) {
			wp_enqueue_style( 'fondy-checkout', plugin_dir_url( __FILE__ ) . 'assets/css/fondy_styles.css' );
			wp_enqueue_script( 'fondy_pay', '//api.fondy.eu/static_common/v1/checkout/ipsp.js', array(), null, false );
		}
	}

	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'          => array(
				'title'       => __( 'Enable/Disable', 'woocommerce-fondy' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable Fondy Payment Module.', 'woocommerce-fondy' ),
				'default'     => 'no',
				'description' => 'Show in the Payment List as a payment option'
			),
			'title'            => array(
				'title'       => __( 'Title:', 'woocommerce-fondy' ),
				'type'        => 'text',
				'default'     => __( 'Fondy Online Payments', 'woocommerce-fondy' ),
				'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-fondy' ),
				'desc_tip'    => true
			),
			'description'      => array(
				'title'       => __( 'Description:', 'woocommerce-fondy' ),
				'type'        => 'textarea',
				'default'     => __( 'Pay securely by Credit or Debit Card or Internet Banking through fondy service.', 'woocommerce-fondy' ),
				'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce-fondy' ),
				'desc_tip'    => true
			),
			'merchant_id'      => array(
				'title'       => __( 'Merchant ID', 'woocommerce-fondy' ),
				'type'        => 'text',
				'description' => __( 'Given to Merchant by fondy' ),
				'desc_tip'    => true
			),
			'salt'             => array(
				'title'       => __( 'Merchant secretkey', 'woocommerce-fondy' ),
				'type'        => 'text',
				'description' => __( 'Given to Merchant by fondy', 'woocommerce-fondy' ),
				'desc_tip'    => true
			),
			'showlogo'         => array(
				'title'       => __( 'Show Logo', 'woocommerce-fondy' ),
				'type'        => 'checkbox',
				'label'       => __( 'Show the "fondy" logo in the Payment Method section for the user', 'woocommerce-fondy' ),
				'default'     => 'yes',
				'description' => __( 'Tick to show "fondy" logo', 'woocommerce-fondy' ),
				'desc_tip'    => true
			),
			'page_mode'        => array(
				'title'       => __( 'Enable on page mode', 'woocommerce-fondy' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable on page payment mode', 'woocommerce-fondy' ),
				'default'     => 'no',
				'description' => __( 'Enable on page mode without redirect', 'woocommerce-fondy' ),
				'desc_tip'    => true
			),
			'redirect_page_id' => array(
				'title'       => __( 'Return Page', 'woocommerce-fondy' ),
				'type'        => 'select',
				'options'     => $this->fondy_get_pages( 'Select Page' ),
				'description' => __( 'URL of success page', 'woocommerce-fondy' ),
				'desc_tip'    => true
			)
		);
	}

	/**
	 * Admin Panel Options
	 * - Options for bits like 'title' and availability on a country-by-country basis
	 **/
	public function admin_options() {
		echo '<h3>' . __( 'Fondy.eu', 'woocommerce-fondy' ) . '</h3>';
		echo '<p>' . __( 'Payment gateway', 'woocommerce-fondy' ) . '</p>';
		echo '<table class="form-table">';
		// Generate the HTML For the settings form.
		$this->generate_settings_html();
		echo '</table>';
	}

	/**
	 *  There are no payment fields for techpro, but we want to show the description if set.
	 **/
	public function payment_fields() {
		if ( $this->description ) {
			echo wpautop( wptexturize( $this->description ) );
		}
	}

	/**
	 * Receipt Page
	 **/
	public function receipt_page( $order ) {
		echo $this->generate_fondy_form( $order );
	}

	public function get_id() {
		return $this->id;
	}

	protected function fondy_filter( $var ) {
		return $var !== '' && $var !== null;
	}

	protected function getSignature( $data, $password, $encoded = true ) {
		$data = array_filter( $data, array( $this, 'fondy_filter' ) );
		ksort( $data );

		$str = $password;
		foreach ( $data as $k => $v ) {
			$str .= self::SIGNATURE_SEPARATOR . $v;
		}

		if ( $encoded ) {
			return sha1( $str );
		} else {
			return $str;
		}
	}

	private function getProductInfo( $order_id ) {
		return "Order: $order_id";
	}

	/**
	 * Generate payu button link
	 **/
	function generate_fondy_form( $order ) {
		$order      = new WC_Order( $order );
		$order_id   = $order->get_order_number();
		$fondy_args = array(
			'order_id'            => $order_id . self::ORDER_SEPARATOR . time(),
			'merchant_id'         => $this->merchant_id,
			'order_desc'          => $this->getProductInfo( $order_id ),
			'amount'              => round( $order->get_total() * 100 ),
			'currency'            => get_woocommerce_currency(),
			'server_callback_url' => $this->getCallbackUrl(),
			'response_url'        => $this->getCallbackUrl(),
			'lang'                => $this->getLanguage(),
			'sender_email'        => $this->getEmail( $order )
		);
		if ( $this->is_subscription( $order_id ) and $_GET['is_subscription'] ) {
			$fondy_args['required_rectoken'] = 'Y';
		}
		$fondy_args['signature'] = $this->getSignature( $fondy_args, $this->salt );

		$out = '
			<div id="checkout">
			<div id="checkout_wrapper"></div>
			</div>';
		if ( $this->page_mode == 'no' ) {
			foreach ( $fondy_args as $key => $value ) {
				$fondy_args_array[] = "<input type='hidden' name='$key' value='$value'/>";
			}
			$out .= '  <form action="' . $this->liveurl . '" method="post" id="fondy_payment_form">
                    ' . implode( '', $fondy_args_array ) . '
                <input type="submit" id="submit_fondy_payment_form" value="' . __( 'Pay via Fondy.eu', 'woocommerce-fondy' ) . '" />';
		} else {
			$url = $this->get_checkout( $fondy_args );
			$out .= '
			    <script>
			    function checkoutInit(url) {
			    	$ipsp("checkout").scope(function() {
					this.setCheckoutWrapper("#checkout_wrapper");
					this.addCallback(__DEFAULTCALLBACK__);
					this.action("show", function(data) {
						jQuery("#checkout_loader").remove();
						jQuery("#checkout").show();
					});
					this.action("hide", function(data) {
						jQuery("#checkout").hide();
					});
					this.action("resize", function(data) {
						jQuery("#checkout_wrapper").height(data.height);
						});
					this.loadUrl(url);
				});
				}
				checkoutInit("' . $url . '");
				</script>';
		}

		return $out;
	}

	protected function get_checkout( $args ) {
		if ( is_callable( 'curl_init' ) ) {
			$ch = curl_init();
			curl_setopt( $ch, CURLOPT_URL, 'https://api.fondy.eu/api/checkout/url/' );
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
			curl_setopt( $ch, CURLOPT_HTTPHEADER, array( 'Content-type: application/json' ) );
			curl_setopt( $ch, CURLOPT_POST, true );
			curl_setopt( $ch, CURLOPT_POSTFIELDS, json_encode( array( 'request' => $args ) ) );

			$result   = json_decode( curl_exec( $ch ) );
			$httpCode = curl_getinfo( $ch, CURLINFO_HTTP_CODE );

			if ( $httpCode != 200 ) {
				echo "Return code is {$httpCode} \n"
				     . curl_error( $ch );
				exit;
			}
			if ( $result->response->response_status == 'failure' ) {
				echo $result->response->error_message;
				exit;
			}
			$url = $result->response->checkout_url;

			return $url;
		} else {
			echo "Curl not found!";
			die;
		}
	}

	/**
	 * Process the payment and return the result
	 **/
	function process_payment( $order_id, $must_be_logged_in = false ) {
		global $woocommerce;
		if ( $must_be_logged_in && get_current_user_id() === 0 ) {
			wc_add_notice( __( 'You must be logged in.', 'woocommerce-fondy' ), 'error' );

			return array(
				'result'   => 'fail',
				'redirect' => $woocommerce->cart->get_checkout_url()
			);
		}

		$order = new WC_Order( $order_id );
		wc_reduce_stock_levels( $order_id );

		if ( version_compare( WOOCOMMERCE_VERSION, '2.1.0', '>=' ) ) {
			/* 2.1.0 */
			$checkout_payment_url = $order->get_checkout_payment_url( true );
		} else {
			/* 2.0.0 */
			$checkout_payment_url = get_permalink( get_option( 'woocommerce_pay_page_id' ) );
		}

		if ( ! $this->is_subscription( $order_id ) ) {
			$redirect = add_query_arg( 'order_pay', $order_id, $checkout_payment_url );
		} else {
			$redirect = add_query_arg( array(
				'order_pay'       => $order_id,
				'is_subscription' => true
			), $checkout_payment_url );
		}
		$order  = new WC_Order( $order_id );
		$amount = round( $order->get_total() * 100 );
		if ( $this->is_subscription( $order_id ) and (int) $amount === 0 ) {
			$order->payment_complete();
			$order->add_order_note( 'Payment free trial successful' );
			$redirect = $this->get_return_url( $order );
		}

		return array(
			'result'   => 'success',
			'redirect' => $redirect
		);
	}

	private function save_card( $data, $order ) {
		$userid = $order->get_user_id();
		$token  = false;
		if ( $this->isTokenAlreadySaved( $data['rectoken'], $userid ) ) {
			update_user_meta( $userid, 'fondy_token', array(
				'token'      => $data['rectoken'],
				'payment_id' => $this->id
			) );

			return true;
		}
		$token = add_user_meta( $userid, 'fondy_token', array(
			'token'      => $data['rectoken'],
			'payment_id' => $this->id
		) );
		if ( $token ) {
			wc_add_notice( __( 'Card saved.', 'woocommerce-fondy' ) );
		}

		return $token;
	}

	private function isTokenAlreadySaved( $token, $userid ) {
		$tokens = get_user_meta( $userid, 'fondy_token' );
		foreach ( $tokens as $t ) {
			if ( $t['token'] === $token ) {
				return true;
			}
		}

		return false;
	}

	private function getCallbackUrl() {
		$redirect_url = ( $this->redirect_page_id == "" || $this->redirect_page_id == 0 ) ? get_site_url() . "/" : get_permalink( $this->redirect_page_id );

		//For wooCoomerce 2.0
		return add_query_arg( 'wc-api', get_class( $this ), $redirect_url );
	}

	private function getLanguage() {
		return substr( get_bloginfo( 'language' ), 0, 2 );
	}

	private function getEmail( $order ) {
		$current_user = wp_get_current_user();
		$email        = $current_user->user_email;

		if ( empty( $email ) ) {
			$email = $order->billing_email;
		}

		return $email;
	}

	protected function isPaymentValid( $response ) {
		global $woocommerce;

		list( $orderId, ) = explode( self::ORDER_SEPARATOR, $response['order_id'] );
		$order = new WC_Order( $orderId );
		$total = round( $order->get_total() * 100 );
		if ( $order === false ) {
			return __( 'An error has occurred during payment. Please contact us to ensure your order has submitted.', 'woocommerce-fondy' );
		}
		if ( $response['amount'] != round( $order->get_total() * 100 ) ) {
			return __( 'Amount incorrect.', 'woocommerce-fondy' );
		}
		if ( $this->merchant_id != $response['merchant_id'] ) {
			$order->update_status( 'failed' );

			return __( 'An error has occurred during payment. Merchant data is incorrect.', 'woocommerce-fondy' );
		}

		$responseSignature = $response['signature'];
		if ( isset( $response['response_signature_string'] ) ) {
			unset( $response['response_signature_string'] );
		}
		if ( isset( $response['signature'] ) ) {
			unset( $response['signature'] );
		}

		if ( $this->getSignature( $response, $this->salt ) != $responseSignature ) {
			$order->update_status( 'failed' );
			$order->add_order_note( __( 'Transaction ERROR: signature is not valid', 'woocommerce-fondy' ) );

			return __( 'An error has occurred during payment. Signature is not valid.', 'woocommerce-fondy' );
		}

		if ( $response['order_status'] == self::ORDER_DECLINED ) {
			$errorMessage = __( "Thank you for shopping with us. However, the transaction has been declined.", 'woocommerce-fondy' );
			$order->add_order_note( 'Transaction ERROR: order declined<br/>Fondy ID: ' . $response['payment_id'] );
			$order->update_status( 'failed' );

			wp_mail( $response['sender_email'], 'Order declined', $errorMessage );

			return $errorMessage;
		}

		if ( $response['order_status'] == 'expired' ) {
			$errorMessage = __( "Thank you for shopping with us. However, the transaction has been expired.", 'woocommerce-fondy' );
			$order->add_order_note( 'Transaction ERROR: order expired<br/>Fondy ID: ' . $response['payment_id'] );
			$order->update_status( 'cancelled' );

			return $errorMessage;
		}

		if ( $response['order_status'] != self::ORDER_APPROVED ) {
			$this->msg['class']   = 'woocommerce-error';
			$this->msg['message'] = __( "Thank you for shopping with us. But your payment declined.", 'woocommerce-fondy' );
			$order->add_order_note( "Order status:" . $response['order_status'] );
		}

		if ( $response['order_status'] == self::ORDER_APPROVED and $total == $response['amount'] and !$order->is_paid() ) {
			$order->payment_complete();
			$order->add_order_note( 'Fondy payment successful.<br/>fondy ID: ' . ' (' . $response['payment_id'] . ')' );
		} elseif ( $total != $response['amount'] ) {
			$errorMessage = __( "Thank you for shopping with us. However, the transaction has been declined.", 'woocommerce-fondy' );
			$order->add_order_note( 'Transaction ERROR: amount incorrect<br/>Fondy ID: ' . $response['payment_id'] );
			$order->update_status( 'failed' );
		}
		$woocommerce->cart->empty_cart();

		return true;
	}

	function check_fondy_response() {
		global $woocommerce;

		if ( empty( $_POST ) ) {
			$callback = json_decode( file_get_contents( "php://input" ) );
			if ( empty( $callback ) ) {
				die( 'go away!' );
			}
			$_POST = array();
			foreach ( $callback as $key => $val ) {
				$_POST[ esc_sql($key) ] = esc_sql($val);
			}
		}
		list( $orderId, ) = explode( self::ORDER_SEPARATOR, $_POST['order_id'] );
		$order       = new WC_Order( $orderId );
		$paymentInfo = $this->isPaymentValid( $_POST, $order );
		if ( $paymentInfo === true ) {
			if ( $_POST['order_status'] == self::ORDER_APPROVED ) {
				$this->msg['message'] = __( "Thank you for shopping with us. Your account has been charged and your transaction is successful.", 'woocommerce-fondy' );
			}
			$this->msg['class'] = 'woocommerce-message';
		} elseif(!$order->is_paid()) {
			$this->msg['class']   = 'error';
			$this->msg['message'] = $paymentInfo;
			$order->add_order_note( "ERROR: " . $paymentInfo );
		}
		if ( $this->redirect_page_id == "" || $this->redirect_page_id == 0 ) {
			$redirect_url = $this->get_return_url( $order );
		} else {
			$redirect_url = get_permalink( $this->redirect_page_id );
			if ( $this->msg['class'] == 'woocommerce-error' or $this->msg['class'] == 'error' ) {
				wc_add_notice( $this->msg['message'], 'error' );
			} else {
				wc_add_notice( $this->msg['message'] );
			}
		}
		if ( $this->is_subscription( $orderId ) ) {
			if ( ! empty( $_POST['rectoken'] ) ) {
				$this->save_card( $_POST, $order );
			}
		} else {
			$order->add_order_note( 'Transaction Subscription ERROR: no card token' );
		}
		wp_redirect( $redirect_url );
		exit;
	}

	protected function is_subscription( $order_id ) {
		return ( function_exists( 'wcs_order_contains_subscription' ) && ( wcs_order_contains_subscription( $order_id ) || wcs_is_subscription( $order_id ) || wcs_order_contains_renewal( $order_id ) ) );
	}

	// get all pages
	function fondy_get_pages( $title = false, $indent = true ) {
		$wp_pages  = get_pages( 'sort_column=menu_order' );
		$page_list = array();
		if ( $title ) {
			$page_list[] = $title;
		}
		foreach ( $wp_pages as $page ) {
			$prefix = '';
			// show indented child pages?
			if ( $indent ) {
				$has_parent = $page->post_parent;
				while ( $has_parent ) {
					$prefix     .= ' - ';
					$next_page  = get_post( $has_parent );
					$has_parent = $next_page->post_parent;
				}
			}
			// add to page list array array
			$page_list[ $page->ID ] = $prefix . $page->post_title;
		}

		return $page_list;
	}
}