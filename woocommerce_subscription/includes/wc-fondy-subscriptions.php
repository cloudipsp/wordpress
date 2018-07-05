<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Fondy_Subscriptions class.
 *
 * @extends WC_Fondy
 */
class WC_Fondy_Subscriptions extends WC_Fondy {

	public function __construct() {
		parent::__construct();
		if ( class_exists( 'WC_Subscriptions_Order' ) ) {
			add_action( 'woocommerce_scheduled_subscription_payment_' . $this->id, array(
				$this,
				'scheduled_subscription_payment'
			), 10, 2 );
		}
	}

	/**
	 * scheduled_subscription_payment function.
	 *
	 * @param $amount_to_charge float The amount to charge.
	 * @param $renewal_order WC_Order A WC_Order object created to record the renewal payment.
	 */
	public function scheduled_subscription_payment( $amount_to_charge, $renewal_order ) {
		$response = $this->process_subscription_payment( $renewal_order, $amount_to_charge );
		if ( is_wp_error( $response ) ) {
			$renewal_order->update_status( 'failed', sprintf( __( 'Payment failed (%s)', 'woocommerce-fondy' ), $response->get_error_message() ) );
		}
	}

	public function process_subscription_payment( $order, $amount = 0 ) {
		if ( $amount === 0 ) {
			$order->payment_complete();

			return true;
		}

		$amount = round( $amount * 100 );

		$customerId = $order->get_customer_id();
		if ( ! $customerId ) {
			return new WP_Error( 'paymenthighway_error', __( 'Customer not found.', 'woocommerce' ) );
		}

		$token = get_user_meta( $customerId, 'fondy_token' );

		if ( is_null( $token ) ) {
			$order->add_order_note( "Token not found." );

			return false;
		}

		if ( $this->checkToken( $token[0] ) ) {
			$order->add_order_note( 'Order amount is: ' . $amount/100 );
			$fondy_args = array(
				'order_id'    => $order->get_order_number() . self::ORDER_SEPARATOR . time(),
				'merchant_id' => $this->merchant_id,
				'amount'      => $amount,
				'rectoken'    => $token[0]['token'],
				'currency'    => get_woocommerce_currency(),
				'order_desc'  => 'recurring payment for: ' . $order->get_order_number()
			);

			$fondy_args['signature'] = $this->getSignature( $fondy_args, $this->salt );
			$result                  = $this->do_subscription_payment( $fondy_args, $order );
			if ( $result['response']['response_status'] == 'failure' ) {
				$order->add_order_note( json_encode($result['response']));
                if(isset($result['response']['error_message']))
                    $order->add_order_note( $result['response']['error_message'] );
                if(isset($result['response']['error_code']))
                    $order->add_order_note( sprintf("Error code: %s",  $result['response']['error_code']) );
				$order->update_status( 'failed', sprintf( __( 'Payment failed (%s)', 'woocommerce-fondy' ), $result['response']['response_status'] ) );
			} else if ( $result['response']['response_status'] == 'success' ) {
				if ( $this->is_subscription_payment_valid( $result['response'], $amount ) !== true ) {
					switch ( $result['response']['order_status'] ):
						case 'approved':
							$order->update_status( 'completed' );
							$order->payment_complete();
							$order->add_order_note( 'Fondy subscription payment successful.<br/>fondy ID: ' . ' (' . $result['response']['payment_id'] . ')' );

							return true;
							break;
						case 'processing':
							$order->add_order_note( 'Transaction ERROR: order in proccesing state<br/>Fondy ID: ' . $result['response']['payment_id'] );
							$order->update_status( 'failed' );

							return false;
							break;
						case 'expired':
							$order->add_order_note( 'Transaction ERROR: order expired<br/>Fondy ID: ' . $result['response']['payment_id'] );
							$order->update_status( 'cancelled' );

							return false;
							break;
						case 'declined':
							$order->add_order_note( 'Transaction ERROR: order declined<br/>Fondy ID: ' . $result['response']['payment_id'] );
							$order->update_status( 'cancelled' );

							return false;
							break;
					endswitch;
				} else {
					$order->add_order_note( "invalid payment" );

					return false;
				}
			}
		} else {
			$order->add_order_note( "Token expired, or token not found." );

			return false;
		}
	}

	protected function is_subscription_payment_valid( $response, $amount ) {
		if ( $response['amount'] != $amount ) {
			return __( 'Amount incorrect.', 'woocommerce-fondy' );
		}
		if ( $this->merchant_id != $response['merchant_id'] ) {
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
			return __( 'An error has occurred during payment. Signature is not valid.', 'woocommerce-fondy' );
		}
	}

	protected function do_subscription_payment( $args ) {
		if ( is_callable( 'curl_init' ) ) {
			$ch = curl_init();
			curl_setopt( $ch, CURLOPT_URL, 'https://api.fondy.eu/api/recurring/' );
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
			curl_setopt( $ch, CURLOPT_HTTPHEADER, array( 'Content-type: application/json' ) );
			curl_setopt( $ch, CURLOPT_POST, true );
			curl_setopt( $ch, CURLOPT_POSTFIELDS, json_encode( array( 'request' => $args ) ) );

			$result   = json_decode( curl_exec( $ch ), true );
			$httpCode = curl_getinfo( $ch, CURLINFO_HTTP_CODE );

			if ( $httpCode != 200 ) {
				echo "Return code is {$httpCode} \n"
				     . curl_error( $ch );
				exit;
			}

			return $result;
		} else {
			wp_die( __( 'Curl error', 'woocommerce-fondy' ), __( 'Error', 'rcp' ), array( 'response' => '500' ) );

		}
	}

	private function checkToken( $token ) {
		if ( $token['payment_id'] !== parent::get_id() ) {
			return false;
		} else {
			return true;
		}
	}

	public function process_payment( $order_id, $must_be_logged_in = false ) {
		if ( $this->is_subscription( $order_id ) ) {
			return parent::process_payment( $order_id, true );
		} else {
			return parent::process_payment( $order_id, $must_be_logged_in );
		}
	}

}