<?php

add_action('wp', array('GFFondy', 'maybe_thankyou_page'), 5);

GFForms::include_payment_addon_framework();

class GFFondy extends GFPaymentAddOn
{

    protected $_version = GF_FONDY_VERSION;
    protected $_min_gravityforms_version = '1.9.3';
    protected $_slug = 'fondy-gravityforms-payment';
    protected $_path = 'fondy-gravityforms-payment/fondy.php';
    protected $_full_path = __FILE__;
    protected $_url = 'https://fondy.eu';
    protected $_title = 'Gravity Forms Fondy Add-On';
    protected $_short_title = 'Fondy';
    protected $_supports_callbacks = true;
    private $production_url = 'https://api.fondy.eu/api/checkout/url/';
    private $sandbox_url = 'https://api.fondy.eu/api/checkout/url/';

    // Members plugin integration
    protected $_capabilities = array('gravityforms_fondy', 'gravityforms_fondy_uninstall');

    // Permissions
    protected $_capabilities_settings_page = 'gravityforms_fondy';
    protected $_capabilities_form_settings = 'gravityforms_fondy';
    protected $_capabilities_uninstall = 'gravityforms_fondy_uninstall';

    // Automatic upgrade enabled
    protected $_enable_rg_autoupgrade = true;

    private static $_instance = null;

    public static function get_instance()
    {
        if (self::$_instance == null) {
            self::$_instance = new GFFondy();
        }

        return self::$_instance;
    }

    private function __clone()
    {
    } /* do nothing */

    public function init_frontend()
    {
        parent::init_frontend();

        add_filter('gform_disable_post_creation', array($this, 'delay_post'), 10, 3);
        add_filter('gform_disable_notification', array($this, 'delay_notification'), 10, 4);
    }

    //----- SETTINGS PAGES ----------//

    public function plugin_settings_fields()
    {
        $description = '
			<p style="text-align: left;">' .
            esc_html__('Gravity Forms requires to be create and enabled your Fondy account. Follow the following steps to confirm it is enabled.', 'gravityformsfondy') .
            '</p>
			<ul>
			<li>' . sprintf(esc_html__('Create you free %sFondy account.%s', 'gravityformsfondy'), '<a href="https://portal.fondy.eu/mportal/#/account/registration" target="_blank">', '</a>') . '</li>' .
            '<li>' . sprintf(esc_html__('Go to settings end enter Callback URL: %s', 'gravityformsfondy'), '<strong>' . esc_url(add_query_arg('page', 'gf_fondy_ipn', get_bloginfo('url') . '/')) . '</strong>') . '</li>' .
            '</ul>
				<br/>';

        return array(
            array(
                'title' => '',
                'description' => $description,
                'fields' => array(
                    array(
                        'name' => 'gf_fondy_configured',
                        'label' => esc_html__('Fondy setting', 'gravityformsfondy'),
                        'type' => 'checkbox',
                        'choices' => array(array('label' => esc_html__('Confirm that you have configured your Fondy account in Merchant portal.', 'gravityformsfondy'), 'name' => 'gf_fondy_configured'))
                    ),
                    array(
                        'type' => 'save',
                        'messages' => array(
                            'success' => esc_html__('Settings have been updated.', 'gravityformsfondy')
                        ),
                    ),
                ),
            ),
        );
    }

    public function feed_list_no_item_message()
    {
        $settings = $this->get_plugin_settings();
        if (!rgar($settings, 'gf_fondy_configured')) {
            return sprintf(esc_html__('To get started, please configure your %sFondy Settings%s!', 'gravityformsfondy'), '<a href="' . admin_url('admin.php?page=gf_settings&subview=' . $this->_slug) . '">', '</a>');
        } else {
            return parent::feed_list_no_item_message();
        }
    }

    public function feed_settings_fields()
    {
        $default_settings = parent::feed_settings_fields();

        //--add Fondy Email Address field
        $fields = array(
            array(
                'name' => 'MerchantID',
                'label' => esc_html__('Fondy Merchant ID', 'gravityformsfondy'),
                'type' => 'text',
                'class' => 'medium',
                'required' => true,
                'tooltip' => '<h6>' . esc_html__('Merchant ID from portal', 'gravityformsfondy') . '</h6>'
            ),
            array(
                'name' => 'SecretKey',
                'label' => esc_html__('Fondy secret key ', 'gravityformsfondy'),
                'type' => 'text',
                'class' => 'medium',
                'required' => true,
                'tooltip' => '<h6>' . esc_html__('Fondy secret key from portal', 'gravityformsfondy') . '</h6>'
            ),
            array(
                'name' => 'mode',
                'label' => esc_html__('Mode', 'gravityformsfondy'),
                'type' => 'radio',
                'choices' => array(
                    array('id' => 'gf_fondy_mode_production', 'label' => esc_html__('Production', 'gravityformsfondy'), 'value' => 'production'),
                    array('id' => 'gf_fondy_mode_test', 'label' => esc_html__('Test', 'gravityformsfondy'), 'value' => 'test'),

                ),

                'horizontal' => true,
                'default_value' => 'production',
                'tooltip' => '<h6>' . esc_html__('Mode', 'gravityformsfondy') . '</h6>' . esc_html__('Select Production to receive live payments. Select Test for testing purposes when using the Fondy development sandbox.', 'gravityformsfondy')
            ),
        );

        $default_settings = parent::add_field_after('feedName', $fields, $default_settings);
        //--------------------------------------------------------------------------------------

        //--add donation to transaction type drop down
        $transaction_type = parent::get_field('transactionType', $default_settings);
        $choices = $transaction_type['choices'];
        $add_donation = true;
        foreach ($choices as $choice) {
            //add donation option if it does not already exist
            if ($choice['value'] == 'donation') {
                $add_donation = false;
            }
        }
        if ($add_donation) {
            //add donation transaction type
            $choices[] = array('label' => __('Donations', 'gravityformsfondy'), 'value' => 'donation');
        }
        $transaction_type['choices'] = $choices;
        $default_settings = $this->replace_field('transactionType', $transaction_type, $default_settings);
        //-------------------------------------------------------------------------------------------------

        //--add Return URL, Cancel URL
        $fields = array(
            array(
                'name' => 'ReturnURL',
                'label' => esc_html__('Return URL', 'gravityformsfondy'),
                'type' => 'text',
                'class' => 'medium',
                'required' => false,
                'tooltip' => '<h6>' . esc_html__('Return URL', 'gravityformsfondy') . '</h6>' . esc_html__('Enter the URL the user should be retuned if not set use defaults.', 'gravityformsfondy')
            )
        );

        if ($this->get_setting('delayNotification') || !$this->is_gravityforms_supported('1.9.12')) {
            $fields[] = array(
                'name' => 'notifications',
                'label' => esc_html__('Notifications', 'gravityformsfondy'),
                'type' => 'notifications',
                'tooltip' => '<h6>' . esc_html__('Notifications', 'gravityformsfondy') . '</h6>' . esc_html__("Enable this option if you would like to only send out this form's notifications for the 'Form is submitted' event after payment has been received. Leaving this option disabled will send these notifications immediately after the form is submitted. Notifications which are configured for other events will not be affected by this option.", 'gravityformsfondy')
            );
        }

        //Add post fields if form has a post
        $form = $this->get_current_form();
        if (GFCommon::has_post_field($form['fields'])) {
            $post_settings = array(
                'name' => 'post_checkboxes',
                'label' => esc_html__('Posts', 'gravityformsfondy'),
                'type' => 'checkbox',
                'tooltip' => '<h6>' . esc_html__('Posts', 'gravityformsfondy') . '</h6>' . esc_html__('Enable this option if you would like to only create the post after payment has been received.', 'gravityformsfondy'),
                'choices' => array(
                    array('label' => esc_html__('Create post only when payment is received.', 'gravityformsfondy'), 'name' => 'delayPost'),
                ),
            );

            if ($this->get_setting('transactionType') == 'subscription') {
                $post_settings['choices'][] = array(
                    'label' => esc_html__('Change post status when subscription is canceled.', 'gravityformsfondy'),
                    'name' => 'change_post_status',
                    'onChange' => 'var action = this.checked ? "draft" : ""; jQuery("#update_post_action").val(action);',
                );
            }

            $fields[] = $post_settings;
        }
        $default_settings = $this->remove_field('trial', $default_settings);
        $default_settings = $this->remove_field('conditionalLogic', $default_settings);

        //Adding custom settings for backwards compatibility with hook 'gform_fondy_add_option_group'
        $fields[] = array(
            'name' => 'custom_options',
            'label' => '',
            'type' => 'custom',
        );

        $default_settings = $this->add_field_after('billingInformation', $fields, $default_settings);
        //-----------------------------------------------------------------------------------------

        //--get billing info section and add customer first/last name
        $billing_info = parent::get_field('billingInformation', $default_settings);
        $billing_fields = $billing_info['field_map'];
        $add_first_name = true;
        $add_last_name = true;
        foreach ($billing_fields as $mapping) {
            //add first/last name if it does not already exist in billing fields
            if ($mapping['name'] == 'firstName') {
                $add_first_name = false;
            } else if ($mapping['name'] == 'lastName') {
                $add_last_name = false;
            }
        }

        if ($add_last_name) {
            //add last name
            array_unshift($billing_info['field_map'], array('name' => 'lastName', 'label' => esc_html__('Last Name', 'gravityformsfondy'), 'required' => false));
        }
        if ($add_first_name) {
            array_unshift($billing_info['field_map'], array('name' => 'firstName', 'label' => esc_html__('First Name', 'gravityformsfondy'), 'required' => false));
        }
        $default_settings = parent::replace_field('billingInformation', $billing_info, $default_settings);
        //----------------------------------------------------------------------------------------------------

        //hide default display of setup fee, not used by Fondy Standard
        $default_settings = parent::remove_field('setupFee', $default_settings);
        //-----------------------------------------------------------------------------------------

        /**
         * Filter through the feed settings fields for the Fondy feed
         *
         * @param array $default_settings The Default feed settings
         * @param array $form The Form object to filter through
         */
        return apply_filters('gform_fondy_feed_settings_fields', $default_settings, $form);
    }

    public function supported_billing_intervals()
    {

        $billing_cycles = array(
            'day' => array('label' => esc_html__('day(s)', 'gravityformsfondy'), 'min' => 1, 'max' => 90),
            'week' => array('label' => esc_html__('week(s)', 'gravityformsfondy'), 'min' => 1, 'max' => 52),
            'month' => array('label' => esc_html__('month(s)', 'gravityformsfondy'), 'min' => 1, 'max' => 24),
            'year' => array('label' => esc_html__('year(s)', 'gravityformsfondy'), 'min' => 1, 'max' => 5)
        );

        return $billing_cycles;
    }

    public function field_map_title()
    {
        return esc_html__('Fondy Field', 'gravityformsfondy');
    }

    public function settings_options($field, $echo = true)
    {
        $html = $this->settings_checkbox($field, false);

        //--------------------------------------------------------
        //For backwards compatibility.
        ob_start();
        do_action('gform_fondy_action_fields', $this->get_current_feed(), $this->get_current_form());
        $html .= ob_get_clean();
        //--------------------------------------------------------

        if ($echo) {
            echo $html;
        }

        return $html;
    }

    public function settings_custom($field, $echo = true)
    {

        ob_start();
        ?>
        <div id='gf_fondy_custom_settings'>
            <?php
            do_action('gform_fondy_add_option_group', $this->get_current_feed(), $this->get_current_form());
            ?>
        </div>

        <script type='text/javascript'>
            jQuery(document).ready(function () {
                jQuery('#gf_fondy_custom_settings label.left_header').css('margin-left', '-200px');
            });
        </script>

        <?php

        $html = ob_get_clean();

        if ($echo) {
            echo $html;
        }

        return $html;
    }

    public function settings_notifications($field, $echo = true)
    {
        $checkboxes = array(
            'name' => 'delay_notification',
            'type' => 'checkboxes',
            'onclick' => 'ToggleNotifications();',
            'choices' => array(
                array(
                    'label' => esc_html__("Send notifications for the 'Form is submitted' event only when payment is received.", 'gravityformsfondy'),
                    'name' => 'delayNotification',
                ),
            )
        );

        $html = $this->settings_checkbox($checkboxes, false);

        $html .= $this->settings_hidden(array('name' => 'selectedNotifications', 'id' => 'selectedNotifications'), false);

        $form = $this->get_current_form();
        $has_delayed_notifications = $this->get_setting('delayNotification');
        ob_start();
        ?>
        <ul id="gf_fondy_notification_container"
            style="padding-left:20px; margin-top:10px; <?php echo $has_delayed_notifications ? '' : 'display:none;' ?>">
            <?php
            if (!empty($form) && is_array($form['notifications'])) {
                $selected_notifications = $this->get_setting('selectedNotifications');
                if (!is_array($selected_notifications)) {
                    $selected_notifications = array();
                }

                //$selected_notifications = empty($selected_notifications) ? array() : json_decode($selected_notifications);

                $notifications = GFCommon::get_notifications('form_submission', $form);

                foreach ($notifications as $notification) {
                    ?>
                    <li class="gf_fondy_notification">
                        <input type="checkbox" class="notification_checkbox" value="<?php echo $notification['id'] ?>"
                               onclick="SaveNotifications();" <?php checked(true, in_array($notification['id'], $selected_notifications)) ?> />
                        <label class="inline"
                               for="gf_fondy_selected_notifications"><?php echo $notification['name']; ?></label>
                    </li>
                    <?php
                }
            }
            ?>
        </ul>
        <script type='text/javascript'>
            function SaveNotifications() {
                var notifications = [];
                jQuery('.notification_checkbox').each(function () {
                    if (jQuery(this).is(':checked')) {
                        notifications.push(jQuery(this).val());
                    }
                });
                jQuery('#selectedNotifications').val(jQuery.toJSON(notifications));
            }

            function ToggleNotifications() {

                var container = jQuery('#gf_fondy_notification_container');
                var isChecked = jQuery('#delaynotification').is(':checked');

                if (isChecked) {
                    container.slideDown();
                    jQuery('.gf_fondy_notification input').prop('checked', true);
                }
                else {
                    container.slideUp();
                    jQuery('.gf_fondy_notification input').prop('checked', false);
                }

                SaveNotifications();
            }
        </script>
        <?php

        $html .= ob_get_clean();

        if ($echo) {
            echo $html;
        }

        return $html;
    }

    public function checkbox_input_change_post_status($choice, $attributes, $value, $tooltip)
    {
        $markup = $this->checkbox_input($choice, $attributes, $value, $tooltip);

        $dropdown_field = array(
            'name' => 'update_post_action',
            'choices' => array(
                array('label' => ''),
                array('label' => esc_html__('Mark Post as Draft', 'gravityformsfondy'), 'value' => 'draft'),
                array('label' => esc_html__('Delete Post', 'gravityformsfondy'), 'value' => 'delete'),

            ),
            'onChange' => "var checked = jQuery(this).val() ? 'checked' : false; jQuery('#change_post_status').attr('checked', checked);",
        );
        $markup .= '&nbsp;&nbsp;' . $this->settings_select($dropdown_field, false);

        return $markup;
    }

    /**
     * Prevent the GFPaymentAddOn version of the options field being added to the feed settings.
     *
     * @return bool
     */
    public function option_choices()
    {

        return false;
    }

    public function save_feed_settings($feed_id, $form_id, $settings)
    {

        //--------------------------------------------------------
        //For backwards compatibility
        $feed = $this->get_feed($feed_id);

        //Saving new fields into old field names to maintain backwards compatibility for delayed payments
        $settings['type'] = $settings['transactionType'];

        if (isset($settings['recurringAmount'])) {
            $settings['recurring_amount_field'] = $settings['recurringAmount'];
        }

        $feed['meta'] = $settings;
        $feed = apply_filters('gform_fondy_save_config', $feed);

        //call hook to validate custom settings/meta added using gform_fondy_action_fields or gform_fondy_add_option_group action hooks
        $is_validation_error = apply_filters('gform_fondy_config_validation', false, $feed);
        if ($is_validation_error) {
            //fail save
            return false;
        }

        $settings = $feed['meta'];

        //--------------------------------------------------------

        return parent::save_feed_settings($feed_id, $form_id, $settings);
    }

    //------ SENDING TO FONDY -----------//

    public function redirect_url($feed, $submission_data, $form, $entry)
    {

        //Don't process redirect url if request is a Fondy return
        if (!rgempty('gf_fondy_return', $_GET)) {
            return false;
        }
        $data = array();
        //updating lead's payment_status to Processing
        GFAPI::update_entry_property($entry['id'], 'payment_status', 'created');

        //Getting Url (Production or Sandbox)
        $fodny_url = $feed['meta']['mode'] == 'production' ? $this->production_url : $this->sandbox_url;
        if ($feed['meta']['mode'] == 'test') {
            $feed['meta']['MerchantID'] = '1396424';
            $feed['meta']['SecretKey'] = 'test';
        }

        $invoice_id = apply_filters('gform_fondy_invoice', '', $form, $entry);

        $data['order_id'] = empty($invoice_id) ? $entry['id'] . "#" . time() : $invoice_id . "#" . time();
        GFAPI::update_entry_property($entry['id'], 'transaction_id', $data['order_id']);
        $data['order_desc'] = empty($invoice_id) ? __('Invoice ID: ') . $entry['id'] : __('Invoice ID: ') . $invoice_id;
        //Current Currency
        $data['currency'] = rgar($entry, 'currency');

        //Customer fields
        $customer = $this->customer_query_string($feed, $entry);
        $customer['feed_id'] = $form['id'];
        $customer['entry_id'] = $entry['id'] . '#' . wp_hash($entry['id']);

        $data['sender_email'] = isset($customer['email']) ? $customer['email'] : '';

        $data['merchant_data'] = $customer;

        $data['amount'] = round(GFCommon::get_order_total($form, $entry) * 100);
        GFAPI::update_entry_property($entry['id'], 'payment_amount', $data['amount'] / 100);
        $data['merchant_id'] = $feed['meta']['MerchantID'];
        //ReturnURL
        $data['response_url'] = !empty($feed['meta']['ReturnURL']) ? $feed['meta']['ReturnURL'] : $this->return_url($form['id'], $entry['id']);

        //URL that will listen to notifications from Fondy
        $data['server_callback_url'] = get_bloginfo('url') . '/?page=gf_fondy_ipn';

        switch ($feed['meta']['transactionType']) {
            case 'product' :
                //build query string using $submission_data
                $data = $this->get_product_query_string($submission_data, $entry['id'], $data);
                break;

            case 'donation' :
                $data = $this->get_product_query_string($submission_data, $entry['id'], $data);
                break;

            case 'subscription' :
                $data = $this->get_subscription_query_string($feed, $submission_data, $entry['id'], $data);
                break;
        }

        if (!$data) {
            $this->log_debug(__METHOD__ . '(): NOT sending to Fondy: The price is either zero.');
            return '';
        }
        $data['merchant_data'] = json_encode($data['merchant_data']);
        $fields = [
            "version" => "2.0",
            "data" => base64_encode(json_encode(array('order' => $data))),
            "signature" => sha1($feed['meta']['SecretKey'] . '|' . base64_encode(json_encode(array('order' => $data))))
        ];

        $this->log_debug(__METHOD__ . "(): Sending to Fondy: " . json_encode($data));

        $answer = $this->get_pay_url($fields, $fodny_url);

        if ($answer['result'] === true) {
            return $answer['url'];
        } else {
            $this->log_debug(__METHOD__ . "(): Erorr: " . $answer['error']);
            return false;
        }
    }

    private function get_pay_url($fields, $url)
    {
        $request = wp_remote_post($url, array(
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
            $error = '<p>' . __('An unidentified error occurred.', 'fondy') . '</p>';
            $error .= '<p>' . $request->get_error_message() . '</p>';
            return array('result' => false, 'error' => $error);
        } elseif (200 == $code && 'OK' == $message) {
            if (is_string($out)) {
                wp_parse_str($out, $out);
            }
            if (isset($out['response']['error_message'])) {
                $error = '<p>' . __('Error message: ', 'fondy') . ' ' . $out['response']['error_message'] . '</p>';
                $error .= '<p>' . __('Error code: ', 'fondy') . ' ' . $out['response']['error_code'] . '</p>';
                return array('result' => false, 'error' => $error);
            } else {
                $url = json_decode(base64_decode($out['response']['data']), true)['order']['checkout_url'];
                return array('result' => true, 'url' => $url);
            }
        }
        return array('result' => false, 'error' => 'unknown');
    }

    public function get_product_query_string($submission_data, $entry_id, $data)
    {

        if (empty($submission_data)) {
            return false;
        }

        $product = array();
        $name_without_options = '';
        $line_items = rgar($submission_data, 'line_items');
        $discounts = rgar($submission_data, 'discounts');
        $payment_amount = rgar($submission_data, 'payment_amount');
        $discount_amt = 0;
        $product_index = 1;
        //work on products
        if (is_array($line_items)) {
            foreach ($line_items as $item) {
                $product_name = $item['name'];
                $product_id = $item['id'];
                //add product info to querystring
                $product['product_' . $product_index] = $product_name;
                $product_index++;
                $name_without_options .= $product_id . ', ';
            }
        }
        if (strlen(json_encode($product)) > 1020) {
            $product = substr($name_without_options, 0, strlen($name_without_options) - 2);

            //truncating name to maximum allowed size
            if (strlen($product) > 1020) {
                $product = substr($product, 0, 1020) . '...';
            }
        }
        //look for discounts
        if (is_array($discounts)) {
            foreach ($discounts as $discount) {
                $discount_full = abs($discount['unit_price']) * $discount['quantity'];
                $discount_amt += $discount_full;
            }
            if ($discount_amt > 0) {
                $data['amount'] = round(($payment_amount - $discount_amt) * 100);
            }
        }

        $data['product_id'] = json_encode($product);
        //save payment amount to lead meta
        gform_update_meta($entry_id, 'payment_amount', $payment_amount);

        return $payment_amount > 0 ? $data : false;

    }

    public function get_subscription_query_string($feed, $submission_data, $entry_id, $data)
    {

        if (empty($submission_data)) {
            return false;
        }

        $payment_amount = rgar($submission_data, 'payment_amount');
        $line_items = rgar($submission_data, 'line_items');
        $discounts = rgar($submission_data, 'discounts');
        $recurring_amount = rgar($submission_data, 'payment_amount'); //will be field id or the text 'form_total'
        $name_without_options = '';
        $item_name = '';

        //work on products
        if (is_array($line_items)) {
            foreach ($line_items as $item) {
                $product_name = $item['name'];
                $quantity = $item['quantity'];
                $quantity_label = $quantity > 1 ? $quantity . ' ' : '';
                $options = rgar($item, 'options');
                $product_id = $item['id'];
                $is_shipping = rgar($item, 'is_shipping');

                $product_options = '';
                if (!$is_shipping) {
                    if (!empty($options) && is_array($options)) {
                        $product_options = ' (';
                        foreach ($options as $option) {
                            $product_options .= $option['option_name'] . ', ';
                        }
                        $product_options = substr($product_options, 0, strlen($product_options) - 2) . ')';
                    }
                    $item_name .= 'ID: ' . $product_id . ', ' . $quantity_label . $product_name . $product_options . ', ';
                    $name_without_options .= $product_name . ', ';
                }
            }

            //look for discounts to pass in the item_name
            if (is_array($discounts)) {
                foreach ($discounts as $discount) {
                    $product_name = $discount['name'];
                    $quantity = $discount['quantity'];
                    $quantity_label = $quantity > 1 ? $quantity . ' ' : '';
                    $item_name .= $quantity_label . $product_name . ' (), ';
                    $name_without_options .= $product_name . ', ';
                }
            }

            if (!empty($item_name)) {
                $item_name = substr($item_name, 0, strlen($item_name) - 2);
            }

            //if name is larger than max, remove options from it.
            if (strlen($item_name) > 1020) {
                $item_name = substr($name_without_options, 0, strlen($name_without_options) - 2);

                //truncating name to maximum allowed size
                if (strlen($item_name) > 1020) {
                    $item_name = substr($item_name, 0, 1020) . '...';
                }
            }
        }
        $product = $item_name;

        //check for recurring times
        $recurring_times = rgar($feed['meta'], 'recurringTimes') ? rgar($feed['meta'], 'recurringTimes') : '';

        $billing_cycle_number = rgar($feed['meta'], 'billingCycle_length');
        $billing_cycle_type = $this->convert_interval(rgar($feed['meta'], 'billingCycle_unit'), 'text');


        $data['product_id'] = $product;
        $data['recurring_data'] =
            array(
                'start_time' => date('Y-m-d', strtotime('+ ' . intval($billing_cycle_number) . ' ' . $billing_cycle_type)),
                'amount' => round($recurring_amount * 100),
                'every' => intval($billing_cycle_number),
                'period' => $billing_cycle_type,
                'state' => 'y',
                'readonly' => 'y'
            );
        if ($recurring_times) {
            $data['recurring_data']['end_time'] = date('Y-m-d', strtotime('+ ' . (intval($billing_cycle_number * $recurring_times)) . ' ' . 'month'));
        }
        if ($billing_cycle_type == 'year') {
            $data['recurring_data']['start_time'] = date('Y-m-d', strtotime('+ ' . (intval($billing_cycle_number) * 12) . ' ' . 'month'));
            $data['recurring_data']['every'] = intval($billing_cycle_number) * 12;
            $data['recurring_data']['period'] = 'month';
            if ($recurring_times) {
                $data['recurring_data']['end_time'] = date('Y-m-d', strtotime('+ ' . (intval($billing_cycle_number * $recurring_times) * 12) . ' ' . 'month'));
            }
        }
        $data['subscription'] = 'Y';
        $data['subscription_callback_url'] = $data['server_callback_url'];
        $data['merchant_data']['is_subscription'] = true;
        //save payment amount to lead meta
        gform_update_meta($entry_id, 'payment_amount', $payment_amount);

        return $payment_amount > 0 ? $data : false;

    }

    public function customer_query_string($feed, $entry)
    {
        $fields = array();
        foreach ($this->get_customer_fields() as $field) {
            $field_id = $feed['meta'][$field['meta_name']];
            $value = rgar($entry, $field_id);

            if ($field['name'] == 'country') {
                $value = class_exists('GF_Field_Address') ? GF_Fields::get('address')->get_country_code($value) : GFCommon::get_country_code($value);
            } elseif ($field['name'] == 'state') {
                $value = class_exists('GF_Field_Address') ? GF_Fields::get('address')->get_us_state_code($value) : GFCommon::get_us_state_code($value);
            }

            if (!empty($value)) {
                $fields[$field['name']] = $value;
            }
        }

        return $fields;
    }

    public function return_url($form_id, $lead_id)
    {
        $pageURL = GFCommon::is_ssl() ? 'https://' : 'http://';

        $server_port = apply_filters('gform_fondy_return_url_port', $_SERVER['SERVER_PORT']);

        if ($server_port != '80') {
            $pageURL .= $_SERVER['SERVER_NAME'] . ':' . $server_port . $_SERVER['REQUEST_URI'];
        } else {
            $pageURL .= $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];
        }

        $ids_query = "ids={$form_id}|{$lead_id}";
        $ids_query .= '&hash=' . wp_hash($ids_query);

        $url = add_query_arg('gf_fondy_return', base64_encode($ids_query), $pageURL);

        $query = 'gf_fondy_return=' . base64_encode($ids_query);
        /**
         * Filters Fondy's return URL, which is the URL that users will be sent to after completing the payment on Fondy's site.
         * Useful when URL isn't created correctly (could happen on some server configurations using PROXY servers).
         *
         * @since 2.4.5
         *
         * @param string $url The URL to be filtered.
         * @param int $form_id The ID of the form being submitted.
         * @param int $entry_id The ID of the entry that was just created.
         * @param string $query The query string portion of the URL.
         */
        return apply_filters('gform_fondy_return_url', $url, $form_id, $lead_id, $query);

    }

    public static function maybe_thankyou_page()
    {
        $instance = self::get_instance();

        if (!$instance->is_gravityforms_supported()) {
            return;
        }

        if ($str = rgget('gf_fondy_return')) {
            $str = base64_decode($str);

            parse_str($str, $query);
            if (wp_hash('ids=' . $query['ids']) == $query['hash']) {
                list($form_id, $lead_id) = explode('|', $query['ids']);

                $form = GFAPI::get_form($form_id);
                $lead = GFAPI::get_entry($lead_id);

                if (!class_exists('GFFormDisplay')) {
                    require_once(GFCommon::get_base_path() . '/form_display.php');
                }

                $confirmation = GFFormDisplay::handle_confirmation($form, $lead, false);

                if (is_array($confirmation) && isset($confirmation['redirect'])) {
                    header("Location: {$confirmation['redirect']}");
                    exit;
                }

                GFFormDisplay::$submission[$form_id] = array('is_confirmation' => true, 'confirmation_message' => $confirmation, 'form' => $form, 'lead' => $lead);
            }
        }
    }

    public function get_customer_fields()
    {
        return array(
            array('name' => 'first_name', 'label' => 'First Name', 'meta_name' => 'billingInformation_firstName'),
            array('name' => 'last_name', 'label' => 'Last Name', 'meta_name' => 'billingInformation_lastName'),
            array('name' => 'email', 'label' => 'Email', 'meta_name' => 'billingInformation_email'),
            array('name' => 'address1', 'label' => 'Address', 'meta_name' => 'billingInformation_address'),
            array('name' => 'address2', 'label' => 'Address 2', 'meta_name' => 'billingInformation_address2'),
            array('name' => 'city', 'label' => 'City', 'meta_name' => 'billingInformation_city'),
            array('name' => 'state', 'label' => 'State', 'meta_name' => 'billingInformation_state'),
            array('name' => 'zip', 'label' => 'Zip', 'meta_name' => 'billingInformation_zip'),
            array('name' => 'country', 'label' => 'Country', 'meta_name' => 'billingInformation_country'),
        );
    }

    public function convert_interval($interval, $to_type)
    {
        if (empty($interval)) {
            return '';
        }

        $new_interval = '';
        if ($to_type == 'text') {
            //convert single char to text
            switch (strtoupper($interval)) {
                case 'D' :
                    $new_interval = 'day';
                    break;
                case 'W' :
                    $new_interval = 'week';
                    break;
                case 'M' :
                    $new_interval = 'month';
                    break;
                case 'Y' :
                    $new_interval = 'year';
                    break;
                default :
                    $new_interval = $interval;
                    break;
            }
        } else {
            //convert text to single char
            switch (strtolower($interval)) {
                case 'day' :
                    $new_interval = 'D';
                    break;
                case 'week' :
                    $new_interval = 'W';
                    break;
                case 'month' :
                    $new_interval = 'M';
                    break;
                case 'year' :
                    $new_interval = 'Y';
                    break;
                default :
                    $new_interval = $interval;
                    break;
            }
        }

        return $new_interval;
    }

    public function delay_post($is_disabled, $form, $entry)
    {

        $feed = $this->get_payment_feed($entry);
        $submission_data = $this->get_submission_data($feed, $form, $entry);

        if (!$feed || empty($submission_data['payment_amount'])) {
            return $is_disabled;
        }

        return !rgempty('delayPost', $feed['meta']);
    }

    public function delay_notification($is_disabled, $notification, $form, $entry)
    {
        if (rgar($notification, 'event') != 'form_submission') {
            return $is_disabled;
        }

        $feed = $this->get_payment_feed($entry);
        $submission_data = $this->get_submission_data($feed, $form, $entry);

        if (!$feed || empty($submission_data['payment_amount'])) {
            return $is_disabled;
        }

        $selected_notifications = is_array(rgar($feed['meta'], 'selectedNotifications')) ? rgar($feed['meta'], 'selectedNotifications') : array();

        return isset($feed['meta']['delayNotification']) && in_array($notification['id'], $selected_notifications) ? true : $is_disabled;
    }


    //------- PROCESSING FONDY Callback) -----------//

    public function callback()
    {
        if (empty($_POST)) {
            $callback = json_decode(file_get_contents("php://input"));
            if (empty($callback)) {
                $this->log_debug("Empty response.");
                wp_die();
            }
            $_POST = array();
            foreach ($callback as $key => $val) {
                $_POST[esc_sql($key)] = esc_sql($val);
            }
        }
        $base64_data = esc_sql($_POST['data']);
        $signature = esc_sql((string)$_POST['signature']);
        $response = json_decode(base64_decode(esc_sql($_POST['data'])), true)['order'];
        $mdata = json_decode($response['merchant_data'], TRUE);
        $feed_id = $mdata['feed_id'];
        if (empty($response)) {
            $this->log_error("Empty response.");
            wp_die();
        }
        if (!$this->is_gravityforms_supported()) {
            return false;
        }

        $this->log_debug(__METHOD__ . '(): Fondy request received. Starting to process => ' . print_r($_POST, true));


        if (empty($signature)) {
            $this->log_error(__METHOD__ . '(): request does not have a sign. Aborting.');
            return false;
        }
        $entry = $this->get_entry($mdata['entry_id']);


        $feed = $this->get_payment_feed($entry);

        if (!$feed) {
            $this->log_error(__METHOD__ . '(): request does not have a feed. Aborting.');
            return false;
        }

        $settings = array(
            'merchant_id' => $feed['meta']['MerchantID'],
            'secret_key' => $feed['meta']['SecretKey'],
        );

        $is_verified = $this->verify_fondy_request($settings, $response, $base64_data, $signature);

        if (is_wp_error($is_verified)) {
            $this->log_error(__METHOD__ . '(): Fondy verification failed with an error. Aborting with a 500 error so that FODNY is resent.');
            return new WP_Error('FondyVerificationError', 'There was an error when verifying the Fondy message with', array('status_header' => 500));
        } elseif (!$is_verified) {
            $this->log_error(__METHOD__ . '(): Fondy request could not be verified. Aborting.');
            return false;
        }

        $this->log_debug(__METHOD__ . '(): Fondy message successfully verified');


        if (!$entry) {
            $this->log_error(__METHOD__ . '(): Entry could not be found. Aborting.');

            return false;
        }
        $this->log_debug(__METHOD__ . '(): Entry has been found => ' . print_r($entry, true));

        if ($entry['status'] == 'spam') {
            $this->log_error(__METHOD__ . '(): Entry is marked as spam. Aborting.');

            return false;
        }


        //Ignore messages from forms that are no longer configured with the Fondy add-on
        if (!$feed || !rgar($feed, 'is_active')) {
            $this->log_error(__METHOD__ . "(): Form no longer is configured. Form ID: {$entry['form_id']}. Aborting.");

            return false;
        }
        $this->log_debug(__METHOD__ . "(): Form {$entry['form_id']} is properly configured.");

        $this->log_debug(__METHOD__ . '(): Processing payment...');

        $action = $this->process_f($feed, $entry, $response, $mdata);
        $this->log_debug(__METHOD__ . '(): payment processing complete.');

        if (rgempty('entry_id', $action)) {
            return false;
        }

        return $action;

    }

    public function get_payment_feed($entry, $form = false)
    {

        $feed = parent::get_payment_feed($entry, $form);

        if (empty($feed) && !empty($entry['id'])) {
            //looking for feed created by legacy versions
            $feed = $this->get_fondy_feed_by_entry($entry['id']);
        }

        $feed = apply_filters('gform_fondy_get_payment_feed', $feed, $entry, $form ? $form : GFAPI::get_form($entry['form_id']));

        return $feed;
    }

    private function get_fondy_feed_by_entry($entry_id)
    {

        $feed_id = gform_get_meta($entry_id, 'fondy_feed_id');
        $feed = $this->get_feed($feed_id);

        return !empty($feed) ? $feed : false;
    }

    public function post_callback($callback_action, $callback_result)
    {
        if (is_wp_error($callback_action) || !$callback_action) {
            return false;
        }
        if (empty($_POST)) {
            $callback = json_decode(file_get_contents("php://input"));
            if (empty($callback)) {
                $this->log_debug("Empty response.");
                wp_die();
            }
            $_POST = array();
            foreach ($callback as $key => $val) {
                $_POST[esc_sql($key)] = esc_sql($val);
            }
        }
        $base64_data = esc_sql($_POST['data']);
        $signature = esc_sql((string)$_POST['signature']);
        $response = json_decode(base64_decode(esc_sql($_POST['data'])), true)['order'];
        $entry = GFAPI::get_entry($callback_action['entry_id']);
        $feed = $this->get_payment_feed($entry);
        $transaction_id = rgar($callback_action, 'transaction_id');
        $amount = rgar($callback_action, 'amount');
        $subscriber_id = rgar($callback_action, 'subscription_id');
        $pending_reason = $response['order_status'];
        $reason = 'callback';
        $status = $response['order_status'];
        $txn_type = $response['order_id'];
        $parent_txn_id = $response['parent_order_id'];

        $settings = array(
            'merchant_id' => $feed['meta']['MerchantID'],
            'secret_key' => $feed['meta']['SecretKey'],
        );

        $is_verified = $this->verify_fondy_request($settings, $response, $base64_data, $signature);

        if (is_wp_error($is_verified)) {
            $this->log_error(__METHOD__ . '(): Fondy verification failed with an error. Aborting with a 500 error so that FODNY is resent.');
            return new WP_Error('FondyVerificationError', 'There was an error when verifying the Fondy message with', array('status_header' => 500));
        } elseif (!$is_verified) {
            $this->log_error(__METHOD__ . '(): Fondy request could not be verified. Aborting.');
            return false;
        }
        //run gform_fondy_fulfillment only in certain conditions
        if (rgar($callback_action, 'ready_to_fulfill') && !rgar($callback_action, 'abort_callback')) {
            $this->fulfill_order($entry, $transaction_id, $amount, $feed);
        } else {
            if (rgar($callback_action, 'abort_callback')) {
                $this->log_debug(__METHOD__ . '(): Callback processing was aborted. Not fulfilling entry.');
            } else {
                $this->log_debug(__METHOD__ . '(): Entry is already fulfilled or not ready to be fulfilled, not running gform_fondy_fulfillment hook.');
            }
        }

        do_action('gform_post_payment_status', $feed, $entry, $status, $transaction_id, $subscriber_id, $amount, $pending_reason, $reason);
        if (has_filter('gform_post_payment_status')) {
            $this->log_debug(__METHOD__ . '(): Executing functions hooked to gform_post_payment_status.');
        }

        do_action('gform_fondy_ipn_' . $txn_type, $entry, $feed, $status, $txn_type, $transaction_id, $parent_txn_id, $subscriber_id, $amount, $pending_reason, $reason);
        if (has_filter('gform_fondy_ipn_' . $txn_type)) {
            $this->log_debug(__METHOD__ . "(): Executing functions hooked to gform_fondy_ipn_{$txn_type}.");
        }

        do_action('gform_fondy_post_ipn', $_POST, $entry, $feed, false);
        if (has_filter('gform_fondy_post_ipn')) {
            $this->log_debug(__METHOD__ . '(): Executing functions hooked to gform_fondy_post_ipn.');
        }
    }

    private function verify_fondy_request($fondySettings, $response, $base64_data, $sign)
    {
        if ($fondySettings['merchant_id'] != $response['merchant_id']) {
            return new WP_Error('FondyVerificationError', 'An error has occurred during payment. Merchant data is incorrect.');
        }
        if (isset($response['response_signature_string'])) {
            unset($response['response_signature_string']);
        }
        if (isset($response['signature'])) {
            unset($response['signature']);
        }
        if ($sign != sha1($fondySettings['secret_key'] . '|' . $base64_data)) {
            return new WP_Error('FondyVerificationError', 'An error has occurred during payment. Signature is not valid.');
        }
        return true;
    }

    private function process_f($config, $entry, $response, $mdata)
    {
        $this->log_debug(__METHOD__ . "(): Payment status: {$response['order_status']} - full response:" . json_encode($response));
        $action = array();
        $amount = $response['amount'] / 100;
        switch (strtolower($response['order_status'])) {
            case 'created' :
                $action['id'] = $response['payment_id'] . '_' . $response['order_status'];
                $action['type'] = 'add_pending_payment';
                $action['transaction_id'] = $response['order_id'];
                $action['entry_id'] = $entry['id'];
                $action['amount'] = $amount;
                $action['entry_id'] = $entry['id'];
                $amount_formatted = GFCommon::to_money($action['amount'], $entry['currency']);
                $action['note'] = sprintf(__('Payment created. Amount: %s. Transaction ID: %s. Reason: %s', 'gravityformsfondy'), $amount_formatted, $action['transaction_id'], $this->get_reason($response['order_status']));
                return $action;
                break;
            case 'processing' :
                $action['id'] = $response['payment_id'] . '_' . $response['order_status'];
                $action['type'] = 'add_pending_payment';
                $action['transaction_id'] = $response['order_id'];
                $action['entry_id'] = $entry['id'];
                $action['amount'] = $amount;
                $action['entry_id'] = $entry['id'];
                $amount_formatted = GFCommon::to_money($action['amount'], $entry['currency']);
                $action['note'] = sprintf(__('Payment is pending. Amount: %s. Transaction ID: %s. Reason: %s', 'gravityformsfondy'), $amount_formatted, $action['transaction_id'], $this->get_reason($response['order_status']));
                return $action;
                break;
            case 'declined' :
                $action['id'] = $response['payment_id'] . '_' . $response['order_status'];
                $action['type'] = 'fail_payment';
                $action['transaction_id'] = $response['order_id'];
                $action['entry_id'] = $entry['id'];
                $action['amount'] = $amount;
                if (isset($mdata['is_subscription']) and $mdata['is_subscription'] == true) {
                    $action['type'] = 'fail_subscription_payment';
                    $action['subscription_id'] = $response['order_id'];
                } elseif (!empty($response['parent_order_id'])) {
                    $action['type'] = 'cancel_subscription';
                    $action['subscription_id'] = $response['parent_order_id'];
                }
                GFPaymentAddOn::add_note($entry['id'], sprintf(__('Payment has been declined. Transaction ID: %s. Reason: %s', 'gravityformsfondy'), $response['order_id'], $this->get_reason($response['order_status'])));
                return $action;
                break;
            case 'approved' :
                //creates transaction
                $action['id'] = $response['payment_id'] . '_' . $response['order_status'];
                $action['type'] = 'complete_payment';
                $action['transaction_id'] = $response['order_id'];
                $action['amount'] = $amount;
                $action['entry_id'] = $entry['id'];
                $action['payment_date'] = gmdate('y-m-d H:i:s');
                $action['payment_method'] = 'Fondy';
                $action['ready_to_fulfill'] = !$entry['is_fulfilled'] ? true : false;
                if (!$this->is_valid_initial_payment_amount($entry['id'], $amount)) {
                    //create note and transaction
                    $this->log_debug(__METHOD__ . '(): Payment amount does not match product price. Entry will not be marked as Approved.');
                    GFPaymentAddOn::add_note($entry['id'], sprintf(__('Payment amount (%s) does not match product price. Entry will not be marked as Approved. Transaction ID: %s', 'gravityformsfondy'), GFCommon::to_money($amount, $entry['currency']), $response['order_id']));
                    GFPaymentAddOn::insert_transaction($entry['id'], 'payment', $response['order_id'], $amount);
                    $action['abort_callback'] = true;
                }
                if (isset($mdata['is_subscription']) and $mdata['is_subscription'] == true and !$response['parent_order_id']) {
                    $action['type'] = 'create_subscription';
                    $action['subscription_id'] = $response['order_id'];
                } elseif (!empty($response['parent_order_id'])) {
                    $action['type'] = 'add_subscription_payment';
                    $action['subscription_id'] = $response['order_id'];
                    $action['note'] = sprintf(esc_html__('Subscription has been paid. Amount: R%s. Subscription Id: %s', 'gravityforms'), $amount, $response['parent_order_id']);
                }
                return $action;
                break;
            case 'reversed' :
                $action['id'] = $response['payment_id'] . '_' . $response['order_status'];
                $action['type'] = 'refund_payment';
                $action['transaction_id'] = $response['order_id'];
                $action['entry_id'] = $entry['id'];
                $action['amount'] = $amount;
                //creates transaction
                $this->log_debug(__METHOD__ . '(): Processing reversal.');
                GFAPI::update_entry_property($entry['id'], 'payment_status', 'Refunded');
                GFPaymentAddOn::add_note($entry['id'], sprintf(__('Payment has been reversed. Transaction ID: %s. Reason: %s', 'gravityformsfondy'), $response['order_id'], $this->get_reason($response['order_status'])));
                GFPaymentAddOn::insert_transaction($entry['id'], 'refund', $action['transaction_id'], $action['amount']);
                break;
            case 'expired' :
                $action['id'] = $response['payment_id'] . '_' . $response['order_status'];
                $action['type'] = 'fail_payment';
                $action['transaction_id'] = $response['order_id'];
                $action['entry_id'] = $entry['id'];
                $action['amount'] = $amount;
                if (isset($mdata['is_subscription']) and $mdata['is_subscription'] == true) {
                    $action['type'] = 'fail_subscription_payment';
                    $action['subscription_id'] = $response['order_id'];
                } elseif (!empty($response['parent_order_id'])) {
                    $action['type'] = 'expire_subscription';
                    $action['subscription_id'] = $response['parent_order_id'];
                }
                GFPaymentAddOn::add_note($entry['id'], sprintf(__('Payment has been expired. Transaction ID: %s. Reason: %s', 'gravityformsfondy'), $response['order_id'], $this->get_reason($response['order_status'])));
                return $action;
                break;
        }

    }

    public function get_entry($custom_field)
    {

        list($entry_id, $hash) = explode('#', $custom_field);
        $hash_matches = wp_hash($entry_id) == $hash;

        //allow the user to do some other kind of validation of the hash
        $hash_matches = apply_filters('gform_fondy_hash_matches', $hash_matches, $entry_id, $hash, $custom_field);

        //Validates that Entry Id wasn't tampered with
        if (!rgpost('test_ipn') && !$hash_matches) {
            $this->log_error(__METHOD__ . "(): Entry ID verification failed. Hash does not match. Custom field: {$custom_field}. Aborting.");

            return false;
        }

        $this->log_debug(__METHOD__ . "(): FODNY message has a valid custom field: {$custom_field}");

        $entry = GFAPI::get_entry($entry_id);

        if (is_wp_error($entry)) {
            $this->log_error(__METHOD__ . '(): ' . $entry->get_error_message());

            return false;
        }

        return $entry;
    }


    public function cancel_subscription($entry, $feed, $note = null)
    {

        parent::cancel_subscription($entry, $feed, $note);

        $this->modify_post(rgar($entry, 'post_id'), rgars($feed, 'meta/update_post_action'));

        return true;
    }

    public function modify_post($post_id, $action)
    {

        $result = false;

        if (!$post_id) {
            return $result;
        }

        switch ($action) {
            case 'draft':
                $post = get_post($post_id);
                $post->post_status = 'draft';
                $result = wp_update_post($post);
                $this->log_debug(__METHOD__ . "(): Set post (#{$post_id}) status to \"draft\".");
                break;
            case 'delete':
                $result = wp_delete_post($post_id);
                $this->log_debug(__METHOD__ . "(): Deleted post (#{$post_id}).");
                break;
        }

        return $result;
    }

    private function get_reason($code)
    {

        switch (strtolower($code)) {
            case 'created':
                return esc_html__('Order has been created, but the customer has not entered payment details yet; merchant must continue to request the status of the order', 'gravityformsfondy');
            case 'processing':
                return esc_html__('Order is still in processing by payment gateway; merchant must continue to request the status of the order', 'gravityformsfondy');
            case 'declined':
                return esc_html__('Order is declined by FONDY payment gateway or by bank or by external payment system', 'gravityformsfondy');
            case 'approved':
                return esc_html__('Order completed successfully, funds are hold on the payer’s account and soon will be credited of the merchant; merchant can provide the service or ship goods.', 'gravityformsfondy');
            case 'expired':
                return esc_html__('A reversal has occurred on this transaction due to your customer triggering a money-back guarantee.', 'gravityformsfondy');
            case 'reversed':
                return esc_html__('A reversal has occurred on this transaction because you have given the customer a refund.', 'gravityformsfondy');
            default:
                return empty($code) ? esc_html__('Reason has not been specified. For more information, contact Fondy Customer Service.', 'gravityformsfondy') : $code;
        }
    }

    public function is_callback_valid()
    {
        if (rgget('page') != 'gf_fondy_ipn') {
            return false;
        }

        return true;
    }


    //------- AJAX FUNCTIONS ------------------//

    public function init_ajax()
    {

        parent::init_ajax();

        add_action('wp_ajax_gf_dismiss_fondy_menu', array($this, 'ajax_dismiss_menu'));

    }

    //------- ADMIN FUNCTIONS/HOOKS -----------//

    public function init_admin()
    {

        parent::init_admin();

        //add actions to allow the payment status to be modified
        add_action('gform_payment_status', array($this, 'admin_edit_payment_status'), 3, 3);
        add_action('gform_payment_date', array($this, 'admin_edit_payment_date'), 3, 3);
        add_action('gform_payment_transaction_id', array($this, 'admin_edit_payment_transaction_id'), 3, 3);
        add_action('gform_payment_amount', array($this, 'admin_edit_payment_amount'), 3, 3);
        add_action('gform_after_update_entry', array($this, 'admin_update_payment'), 4, 2);
        add_filter('gform_addon_navigation', array($this, 'maybe_create_menu'));
    }

    /**
     * Add supported notification events.
     *
     * @param array $form The form currently being processed.
     *
     * @return array
     */
    public function supported_notification_events($form)
    {
        if (!$this->has_feed($form['id'])) {
            return false;
        }

        return array(
            'complete_payment' => esc_html__('Payment Completed', 'gravityformsfondy'),
            'refund_payment' => esc_html__('Payment Refunded', 'gravityformsfondy'),
            'fail_payment' => esc_html__('Payment Failed', 'gravityformsfondy'),
            'add_pending_payment' => esc_html__('Payment Pending', 'gravityformsfondy'),
            'create_subscription' => esc_html__('Subscription Created', 'gravityformsfondy'),
            'cancel_subscription' => esc_html__('Subscription Canceled', 'gravityformsfondy'),
            'expire_subscription' => esc_html__('Subscription Expired', 'gravityformsfondy'),
            'add_subscription_payment' => esc_html__('Subscription Payment Added', 'gravityformsfondy'),
            'fail_subscription_payment' => esc_html__('Subscription Payment Failed', 'gravityformsfondy'),
        );
    }

    public function maybe_create_menu($menus)
    {
        $current_user = wp_get_current_user();
        $dismiss_fondy_menu = get_metadata('user', $current_user->ID, 'dismiss_fondy_menu', true);
        if ($dismiss_fondy_menu != '1') {
            $menus[] = array('name' => $this->_slug, 'label' => $this->get_short_title(), 'callback' => array($this, 'temporary_plugin_page'), 'permission' => $this->_capabilities_form_settings);
        }

        return $menus;
    }

    public function ajax_dismiss_menu()
    {

        $current_user = wp_get_current_user();
        update_metadata('user', $current_user->ID, 'dismiss_fondy_menu', '1');
    }

    public function temporary_plugin_page()
    {
        $current_user = wp_get_current_user();
        ?>
        <script type="text/javascript">
            function dismissMenu() {
                jQuery('#gf_spinner').show();
                jQuery.post(ajaxurl, {
                        action: "gf_dismiss_fondy_menu"
                    },
                    function (response) {
                        document.location.href = '?page=gf_edit_forms';
                        jQuery('#gf_spinner').hide();
                    }
                );

            }
        </script>

        <div class="wrap about-wrap">
            <h1><?php _e('Fondy Add-On v1.0', 'gravityformsfondy') ?></h1>
            <div
                class="about-text"><?php esc_html_e('Thank you! Fondy simple integration.', 'gravityformsfondy') ?></div>
            <div class="changelog">
                <hr/>
                <div class="feature-section col two-col">
                    <div class="col-1">
                        <p><?php esc_html_e('Fondy Feeds are now accessed via the Fondy sub-menu within the Form Settings for the Form you would like to integrate Fondy with.', 'gravityformsfondy') ?></p>
                    </div>
                    <div class="col-2 last-feature">
                        <img src="https://fondy.eu/wp-content/themes/Fondy_EU/img/fondy-logo.svg">
                    </div>
                </div>

                <hr/>

                <form method="post" id="dismiss_menu_form" style="margin-top: 20px;">
                    <input type="checkbox" name="dismiss_fondy_menu" value="1" onclick="dismissMenu();">
                    <label><?php _e('I understand, dismiss this message!', 'gravityformsfondy') ?></label>
                    <img id="gf_spinner" src="<?php echo GFCommon::get_base_url() . '/images/spinner.gif' ?>"
                         alt="<?php _e('Please wait...', 'gravityformsfondy') ?>" style="display:none;"/>
                </form>

            </div>
        </div>
        <?php
    }

    public function admin_edit_payment_status($payment_status, $form, $entry)
    {

        if ($this->payment_details_editing_disabled($entry)) {
            return $payment_status;
        }

        //create drop down for payment status
        $payment_string = gform_tooltip('fondy_edit_payment_status', '', true);
        $payment_string .= '<select id="payment_status" name="payment_status">';
        $payment_string .= '<option value="' . $payment_status . '" selected>' . $payment_status . '</option>';
        $payment_string .= '<option value="Paid">Paid</option>';
        $payment_string .= '</select>';
        return $payment_string;
    }

    public function admin_edit_payment_date($payment_date, $form, $entry)
    {
        if ($this->payment_details_editing_disabled($entry)) {
            return $payment_date;
        }

        $payment_date = $entry['payment_date'];
        if (empty($payment_date)) {
            $payment_date = gmdate('y-m-d H:i:s');
        }

        $input = '<input type="text" id="payment_date" name="payment_date" value="' . $payment_date . '">';

        return $input;
    }

    public function admin_edit_payment_transaction_id($transaction_id, $form, $entry)
    {
        if ($this->payment_details_editing_disabled($entry)) {
            return $transaction_id;
        }

        $input = '<input type="text" id="fondy_transaction_id" name="fondy_transaction_id" value="' . $transaction_id . '">';

        return $input;
    }

    public function admin_edit_payment_amount($payment_amount, $form, $entry)
    {
        if ($this->payment_details_editing_disabled($entry)) {
            return $payment_amount;
        }

        if (empty($payment_amount)) {
            $payment_amount = GFCommon::get_order_total($form, $entry);
        }

        $input = '<input type="text" id="payment_amount" name="payment_amount" class="gform_currency" value="' . $payment_amount . '">';

        return $input;
    }

    public function admin_update_payment($form, $entry_id)
    {
        check_admin_referer('gforms_save_entry', 'gforms_save_entry');

        //update payment information in admin, need to use this function so the lead data is updated before displayed in the sidebar info section
        $entry = GFFormsModel::get_lead($entry_id);

        if ($this->payment_details_editing_disabled($entry, 'update')) {
            return;
        }

        //get payment fields to update
        $payment_status = rgpost('payment_status');
        //when updating, payment status may not be editable, if no value in post, set to lead payment status
        if (empty($payment_status)) {
            $payment_status = $entry['payment_status'];
        }

        $payment_amount = GFCommon::to_number(rgpost('payment_amount'));
        $payment_transaction = rgpost('fondy_transaction_id');
        $payment_date = rgpost('payment_date');

        $status_unchanged = $entry['payment_status'] == $payment_status;
        $amount_unchanged = $entry['payment_amount'] == $payment_amount;
        $id_unchanged = $entry['transaction_id'] == $payment_transaction;
        $date_unchanged = $entry['payment_date'] == $payment_date;

        if ($status_unchanged && $amount_unchanged && $id_unchanged && $date_unchanged) {
            return;
        }

        if (empty($payment_date)) {
            $payment_date = gmdate('y-m-d H:i:s');
        } else {
            //format date entered by user
            $payment_date = date('Y-m-d H:i:s', strtotime($payment_date));
        }

        global $current_user;
        $user_id = 0;
        $user_name = 'System';
        if ($current_user && $user_data = get_userdata($current_user->ID)) {
            $user_id = $current_user->ID;
            $user_name = $user_data->display_name;
        }

        $entry['payment_status'] = $payment_status;
        $entry['payment_amount'] = $payment_amount;
        $entry['payment_date'] = $payment_date;
        $entry['transaction_id'] = $payment_transaction;

        // if payment status does not equal approved/paid or the lead has already been fulfilled, do not continue with fulfillment
        if (($payment_status == 'approved' || $payment_status == 'Paid') && !$entry['is_fulfilled']) {
            $action['id'] = $payment_transaction;
            $action['type'] = 'complete_payment';
            $action['transaction_id'] = $payment_transaction;
            $action['amount'] = $payment_amount;
            $action['entry_id'] = $entry['id'];

            $this->complete_payment($entry, $action);
            $this->fulfill_order($entry, $payment_transaction, $payment_amount);
        }
        //update lead, add a note
        GFAPI::update_entry($entry);
        GFFormsModel::add_note($entry['id'], $user_id, $user_name, sprintf(esc_html__('Payment information was manually updated. Status: %s. Amount: %s. Transaction ID: %s. Date: %s', 'gravityformsfondy'), $entry['payment_status'], GFCommon::to_money($entry['payment_amount'], $entry['currency']), $payment_transaction, $entry['payment_date']));
    }

    public function fulfill_order(&$entry, $transaction_id, $amount, $feed = null)
    {

        if (!$feed) {
            $feed = $this->get_payment_feed($entry);
        }

        $form = GFFormsModel::get_form_meta($entry['form_id']);
        if (rgars($feed, 'meta/delayPost')) {
            $this->log_debug(__METHOD__ . '(): Creating post.');
            $entry['post_id'] = GFFormsModel::create_post($form, $entry);
            $this->log_debug(__METHOD__ . '(): Post created.');
        }

        if (rgars($feed, 'meta/delayNotification')) {
            //sending delayed notifications
            $notifications = $this->get_notifications_to_send($form, $feed);
            GFCommon::send_notifications($notifications, $form, $entry, true, 'form_submission');
        }

        do_action('gform_fondy_fulfillment', $entry, $feed, $transaction_id, $amount);
        if (has_filter('gform_fondy_fulfillment')) {
            $this->log_debug(__METHOD__ . '(): Executing functions hooked to gform_fondy_fulfillment.');
        }

    }

    /**
     * Retrieve the IDs of the notifications to be sent.
     *
     * @param array $form The form which created the entry being processed.
     * @param array $feed The feed which processed the entry.
     *
     * @return array
     */
    public function get_notifications_to_send($form, $feed)
    {
        $notifications_to_send = array();
        $selected_notifications = rgars($feed, 'meta/selectedNotifications');

        if (is_array($selected_notifications)) {
            // Make sure that the notifications being sent belong to the form submission event, just in case the notification event was changed after the feed was configured.
            foreach ($form['notifications'] as $notification) {
                if (rgar($notification, 'event') != 'form_submission' || !in_array($notification['id'], $selected_notifications)) {
                    continue;
                }

                $notifications_to_send[] = $notification['id'];
            }
        }

        return $notifications_to_send;
    }

    private function is_valid_initial_payment_amount($entry_id, $amount_paid)
    {

        //get amount initially sent to fondy
        $amount_sent = gform_get_meta($entry_id, 'payment_amount');
        if (empty($amount_sent)) {
            return true;
        }

        $epsilon = 0.00001;
        $is_equal = abs(floatval($amount_paid) - floatval($amount_sent)) < $epsilon;
        $is_greater = floatval($amount_paid) > floatval($amount_sent);

        //initial payment is valid if it is equal to or greater than product/subscription amount
        if ($is_equal || $is_greater) {
            return true;
        }

        return false;

    }

    public function fondy_fulfillment($entry, $fondy_config, $transaction_id, $amount)
    {
        //no need to do anything for fondy when it runs this function, ignore
        return false;
    }

    /**
     * Editing of the payment details should only be possible if the entry was processed by Fondy, if the payment status is Pending or Processing, and the transaction was not a subscription.
     *
     * @param array $entry The current entry
     * @param string $action The entry detail page action, edit or update.
     *
     * @return bool
     */
    public function payment_details_editing_disabled($entry, $action = 'edit')
    {
        if (!$this->is_payment_gateway($entry['id'])) {
            // Entry was not processed by this add-on, don't allow editing.
            return true;
        }

        $payment_status = rgar($entry, 'payment_status');
        if ($payment_status == 'approved' || $payment_status == 'Paid' || rgar($entry, 'transaction_type') == 2) {
            // Editing not allowed for this entries transaction type or payment status.
            return true;
        }

        if ($action == 'edit' && rgpost('screen_mode') == 'edit') {
            // Editing is allowed for this entry.
            return false;
        }

        if ($action == 'update' && rgpost('screen_mode') == 'view' && rgpost('action') == 'update') {
            // Updating the payment details for this entry is allowed.
            return false;
        }

        // In all other cases editing is not allowed.

        return true;
    }

    /**
     *
     * Transform data when upgrading from legacy fondy.
     *
     * @param $previous_version
     */
    public function upgrade($previous_version)
    {

        if (empty($previous_version)) {
            $previous_version = get_option('gf_fondy_version');
        }

        if (empty($previous_version)) {
            update_option('gform_fondy_sslverify', true);
        }

    }

    public function uninstall()
    {
        parent::uninstall();
        delete_option('gform_fondy_sslverify');
    }

    public static function get_entry_table_name()
    {
        return version_compare(self::get_gravityforms_db_version(), '2.3-dev-1', '<') ? GFFormsModel::get_lead_table_name() : GFFormsModel::get_entry_table_name();
    }

    public static function get_entry_meta_table_name()
    {
        return version_compare(self::get_gravityforms_db_version(), '2.3-dev-1', '<') ? GFFormsModel::get_lead_meta_table_name() : GFFormsModel::get_entry_meta_table_name();
    }

    public static function get_gravityforms_db_version()
    {

        if (method_exists('GFFormsModel', 'get_database_version')) {
            $db_version = GFFormsModel::get_database_version();
        } else {
            $db_version = GFForms::$version;
        }

        return $db_version;
    }

    //------ FOR BACKWARDS COMPATIBILITY ----------------------//

    public function update_feed_id($old_feed_id, $new_feed_id)
    {
        global $wpdb;
        $entry_meta_table = self::get_entry_meta_table_name();
        $sql = $wpdb->prepare("UPDATE {$entry_meta_table} SET meta_value=%s WHERE meta_key='fondy_feed_id' AND meta_value=%s", $new_feed_id, $old_feed_id);
        $wpdb->query($sql);
    }

    public function add_legacy_meta($new_meta, $old_feed)
    {

        $known_meta_keys = array(
            'email', 'mode', 'type', 'style', 'continue_text', 'cancel_url', 'disable_note', 'disable_shipping', 'recurring_amount_field', 'recurring_times',
            'recurring_retry', 'billing_cycle_number', 'billing_cycle_type', 'delay_post',
            'update_post_action', 'delay_notifications', 'selected_notifications', 'fondy_conditional_enabled', 'fondy_conditional_field_id',
            'fondy_conditional_operator', 'fondy_conditional_value', 'customer_fields',
        );

        foreach ($old_feed['meta'] as $key => $value) {
            if (!in_array($key, $known_meta_keys)) {
                $new_meta[$key] = $value;
            }
        }

        return $new_meta;
    }

    //This function kept static for backwards compatibility
    public static function get_config_by_entry($entry)
    {

        $fondy = GFFondy::get_instance();

        $feed = $fondy->get_payment_feed($entry);

        if (empty($feed)) {
            return false;
        }

        return $feed['addon_slug'] == $fondy->_slug ? $feed : false;
    }

    //This function kept static for backwards compatibility
    //This needs to be here until all add-ons are on the framework, otherwise they look for this function
    public static function get_config($form_id)
    {

        $fondy = GFFondy::get_instance();
        $feed = $fondy->get_feeds($form_id);

        if (!$feed) {
            return false;
        }

        return $feed[0]; //only one feed per form is supported (left for backwards compatibility)
    }

    //------------------------------------------------------


}