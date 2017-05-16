<?php
	//load classes init method
	add_action('init', array('PMProGateway_fondy', 'init'));
require_once(dirname(__FILE__) . "/fondy.lib.php");
	/**
	 * PMProGateway_gatewayname Class
	 *
	 * Handles fondy integration.
	 *
	 */
	class PMProGateway_fondy extends PMProGateway
	{
		function PMProGateway($gateway = NULL)
		{
			$this->gateway = $gateway;
			return $this->gateway;
		}										

		/**
		 * Run on WP init
		 *
		 * @since 1.8
		 */
		static function init()
		{
			global $wpdb;
			$result = $wpdb->query("SELECT fondy_token from `$wpdb->pmpro_membership_orders` LIMIT 1");	
			if(!$result){
			$wpdb->query("ALTER TABLE $wpdb->pmpro_membership_orders ADD fondy_token TEXT");	
			}
			
		
			//make sure fondy is a gateway option
			add_filter('pmpro_gateways', array('PMProGateway_fondy', 'pmpro_gateways'));

			//add fields to payment settings
			add_filter('pmpro_payment_options', array('PMProGateway_fondy', 'pmpro_payment_options'));
			add_filter('pmpro_payment_option_fields', array('PMProGateway_fondy', 'pmpro_payment_option_fields'), 10, 2);

			//add some fields to edit user page (Updates)
			add_action('pmpro_after_membership_level_profile_fields', array('PMProGateway_fondy', 'user_profile_fields'));
			add_action('profile_update', array('PMProGateway_fondy', 'user_profile_fields_save'));

			//updates cron
			add_action('pmpro_activation', array('PMProGateway_fondy', 'pmpro_activation'));
			add_action('pmpro_deactivation', array('PMProGateway_fondy', 'pmpro_deactivation'));
			add_action('pmpro_cron_fondy_subscription_updates', array('PMProGateway_fondy', 'pmpro_cron_fondy_subscription_updates'));

			//code to add at checkout if fondy is the current gateway
			$gateway = pmpro_getOption("gateway");
			$gateway = pmpro_getGateway();
			if($gateway == "fondy")
			{				
				//add_filter('pmpro_include_billing_address_fields', '__return_false');
				add_filter('pmpro_include_payment_information_fields', '__return_false');
				add_filter('pmpro_required_billing_fields', array('PMProGateway_fondy', 'pmpro_required_billing_fields'));
				add_filter('pmpro_checkout_default_submit_button', array('PMProGateway_fondy', 'pmpro_checkout_default_submit_button'));
				add_filter('pmpro_checkout_before_change_membership_level', array('PMProGateway_fondy', 'pmpro_checkout_before_change_membership_level'), 10, 2);
			}
		}

		/**
		 * Make sure fondy is in the gateways list
		 *
		 * @since 1.8
		 */
		static function pmpro_gateways($gateways)
		{
			if(empty($gateways['fondy']))
				$gateways['fondy'] = __('fondy', 'pmpro');

			return $gateways;
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
		 * @since 1.8
		 */
		static function pmpro_payment_option_fields($values, $gateway)
		{
		?>
		<tr class="pmpro_settings_divider gateway gateway_fondy" <?php if($gateway != "fondy") { ?>style="display: none;"<?php } ?>>
			<td colspan="2">
				<?php _e('Fondy Settings', 'fondy'); ?>
			</td>
		</tr>
		<tr class="gateway gateway_fondy" <?php if($gateway != "fondy") { ?>style="display: none;"<?php } ?>>
			<th scope="row" valign="top">
				<label for="fondy_merchantid"><?php _e('Merchant ID', 'fondy');?>:</label>
			</th>
			<td>
				<input type="text" id="fondy_merchantid" name="fondy_merchantid" size="60" value="<?php echo esc_attr($values['fondy_merchantid'])?>" />
			</td>
		</tr>
		<tr class="gateway gateway_fondy" <?php if($gateway != "fondy") { ?>style="display: none;"<?php } ?>>
			<th scope="row" valign="top">
				<label for="fondy_securitykey"><?php _e('Secret Key', 'fondy');?>:</label>
			</th>
			<td>
				<textarea id="fondy_securitykey" name="fondy_securitykey" rows="3" cols="80"><?php echo esc_textarea($values['fondy_securitykey']);?></textarea>					
			</td>
		</tr>
		<?php
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
		 * Swap in our submit buttons.
		 *
		 * @since 1.8
		 */
		static function pmpro_checkout_default_submit_button($show)
		{
			global $gateway, $pmpro_requirebilling;
			
			//show our submit buttons
		?>
		
		<span id="pmpro_fondy_checkout" <?php if(($gateway != "fondy") || !$pmpro_requirebilling) { ?>style="display: none;"<?php } ?>>
				<input type="hidden" name="submit-checkout" value="1" />		
				<input type="submit" class="pmpro_btn pmpro_btn-submit-checkout" value="<?php if($pmpro_requirebilling) { _e('Submit and Check Out', 'pmpro'); } else { _e('Submit and Confirm', 'pmpro');}?> &raquo;" />	
		</span>
			<?php
		
			//don't show the default
			return false;
		}
		static function pmpro_checkout_before_change_membership_level($user_id, $morder)
		{
			global $discount_code_id, $wpdb;
			
			//if no order, no need to pay
			if(empty($morder))
			return;
		
			$morder->user_id = $user_id;				
			$morder->saveOrder();
			
			//save discount code use
			if(!empty($discount_code_id))
			$wpdb->query("INSERT INTO $wpdb->pmpro_discount_codes_uses (code_id, user_id, order_id, timestamp) VALUES('" . $discount_code_id . "', '" . $user_id . "', '" . $morder->id . "', now())");	
			
			do_action("pmpro_before_send_to_fondy", $user_id, $morder);
			
			$morder->Gateway->sendToFondy($morder);
		}
		function process(&$order)
		{		
		
			if(empty($order->code))
			$order->code = $order->getRandomCode();			
			//print_r ($order);die;
			//clean up a couple values
			$order->payment_type = "Fondy";
			$order->CardType = "";
			$order->cardtype = "";

			
			$order->status = "review";														
			$order->saveOrder();
			
			return true;			
		}
		
		function sendToFondy(&$order)
		{	
			global $pmpro_currency;						
			global $wpdb;
			
			//taxes on initial amount
			$initial_payment = $order->InitialPayment;
			$initial_payment_tax = $order->getTaxForPrice($initial_payment);
			$initial_payment = round((float)$initial_payment + (float)$initial_payment_tax, 2);
			
			$fields = array(
			'merchant_data' => json_encode(array ('name' => $order->billing->name , 'phone' => $order->billing->phone)),
			'product_id' => $order->membership_id,
			'subscription_callback_url' => admin_url("admin-ajax.php") . "?action=fondy-ins",
			'order_id' => $order->code . FondyForm::ORDER_SEPARATOR . time(),
			'merchant_id' => pmpro_getOption("fondy_merchantid"),
			'order_desc' => substr($order->membership_level->name . " at " . get_bloginfo("name"), 0, 127),
			'amount' => round($initial_payment*100),
			'currency' => $pmpro_currency,
			'server_callback_url' => admin_url("admin-ajax.php") . "?action=fondy-ins",
			'response_url' => admin_url("admin-ajax.php") . "?action=fondy-ins",
			'sender_email' => $order->Email,
			'required_rectoken' => 'Y',
			'subscription' => 'Y'
			);
			$last_subscr_order = new MemberOrder();
			$url = 'https://api.fondy.eu/api/checkout/url/';
			$last = new MemberOrder($last_subscr_order->getLastMemberOrder($order->user_id, $status = 'success', $membership_id = NULL, $gateway = NULL, $gateway_environment = NULL));
			if (isset($last->user_id) && isset($last->code)){
				$result = $wpdb->get_row("SELECT fondy_token from `$wpdb->pmpro_membership_orders` WHERE user_id='". $last->user_id ."' AND code='". $last->code ."'");	
				if (isset($result->fondy_token)){
					$fields['rectoken'] = $result->fondy_token;
					unset($fields['subscription_callback_url']);
					unset($fields['required_rectoken']);
					unset($fields['subscription']);
				    unset($fields['response_url']);
					$url = 'https://api.fondy.eu/api/recurring/';
					
				}
			}	
			$fields['signature'] = FondyForm::getSignature($fields, pmpro_getOption("fondy_securitykey"));
		
			
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
			curl_setopt($ch, CURLOPT_POSTFIELDS,json_encode(array("request"=>$fields)));
			$result = curl_exec($ch);
			if (curl_errno($ch)) {
				print curl_error($ch);
			} else {
				curl_close($ch);
			}
			$fondy_url = json_decode($result)->response;
			if (isset($fondy_url->order_status) and $fondy_url->order_status == 'approved'){
				$order_id = explode('#',$fondy_url->order_id)[0];
				$morder = new MemberOrder( $order_id );
				$ch = curl_init();
				curl_setopt($ch, CURLOPT_URL, admin_url("admin-ajax.php") . "?action=fondy-ins");
				curl_setopt($ch, CURLOPT_POST, true);
				curl_setopt($ch, CURLOPT_POSTFIELDS,json_decode($result,true)['response']);
				$result = curl_exec($ch);
				curl_close($ch);
				wp_redirect(pmpro_url("confirmation", "?level=" . $morder->membership_level->id));
				exit;
			}
			if (isset($fondy_url->checkout_url)){
				wp_redirect($fondy_url->checkout_url);
			}else{
				echo $result;
			}
			
			exit;
		}
		
		
	}