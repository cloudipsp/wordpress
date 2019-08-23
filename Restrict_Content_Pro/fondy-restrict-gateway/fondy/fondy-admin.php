<?php
add_action('admin_menu', 'rcp_fondy_admin_menu');
function rcp_fondy_register_settings()
{
    // create whitelist of options
    register_setting('rcp_fondy_settings_group', 'rcp_fondy_settings', 'rcp_fondy_sanitize_settings');
}

add_action('admin_init', 'rcp_fondy_register_settings');
function rcp_fondy_admin_menu()
{
    add_menu_page('Fondy RCP payment', 'Fondy RCP payment', 'manage_options', '/fondy-admin-page.php', 'rcp_fondy_admin_page', 'dashicons-admin-settings', 6);
}

function rcp_fondy_sanitize_settings($data)
{
    if (empty($data['fondy_merchant_id']) or !is_numeric($data['fondy_merchant_id'])) {
        add_settings_error(
            'fondy-rcp',
            esc_attr('settings_updated'),
            __('Merchant ID incorrect', 'fondy_rcp'),
            'error'
        );
        return false;
    }
    if (empty($data['fondy_secret'])) {
        add_settings_error(
            'fondy-rcp',
            esc_attr('settings_updated'),
            __('Secret key can\'t be empty!', 'fondy_rcp'),
            'error'
        );
        return false;
    }
    add_settings_error(
        'myUniqueIdentifier',
        esc_attr('settings_updated'),
        __('Successfully saved', 'fondy_rcp'),
        'updated'
    );
    if(!isset($data['fondy_reccuring']))
        $data['fondy_reccuring'] = false;
    $return = array(
        'fondy_merchant_id' => (int)$data['fondy_merchant_id'],
        'fondy_secret' => (string)$data['fondy_secret'],
        'fondy_reccuring' => (bool)$data['fondy_reccuring']
    );
    return $return;
}

function rcp_fondy_admin_page()
{
    global $rcp_fondy_options;
    ?>
    <?php if (function_exists('pw_rcp_register_fondy_gateway')) : ?>
    <?php settings_errors(); ?>
    <form method="post" action="options.php" class="rcp_options_form">
        <?php settings_fields('rcp_fondy_settings_group'); ?>
        <div class="tab_content" id="payments">
            <table class="form-table">
                <tr valign="top">
                    <th colspan=2>
                        <h3><?php _e('Fondy Settings', 'fondy_rcp'); ?></h3>
                    </th>
                </tr>

                <tr>
                    <th>
                        <label
                                for="rcp_fondy_settings[fondy_merchant_id]"><?php _e('Fondy MID', 'fondy_rcp'); ?></label>
                    </th>
                    <td>
                        <input class="regular-text" id="rcp_fondy_settings[fondy_merchant_id]" style="width: 300px;"
                               name="rcp_fondy_settings[fondy_merchant_id]"
                               value="<?php if (isset($rcp_fondy_options['fondy_merchant_id'])) {
                                   echo esc_attr($rcp_fondy_options['fondy_merchant_id']);
                               } ?>"/>
                        <p class="description"><?php _e('Enter your Merchant ID', 'fondy_rcp'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th>
                        <label
                                for="rcp_fondy_settings[fondy_secret]"><?php _e('Fondy Secret Key', 'fondy_rcp'); ?></label>
                    </th>
                    <td>
                        <input class="regular-text" id="rcp_fondy_settings[stripe_test_secret]" style="width: 300px;"
                               name="rcp_fondy_settings[fondy_secret]"
                               value="<?php if (isset($rcp_fondy_options['fondy_secret'])) {
                                   echo esc_attr($rcp_fondy_options['fondy_secret']);
                               } ?>"/>
                        <p class="description"><?php _e('Enter your secret key.', 'fondy_rcp'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th>
                        <label
                                for="rcp_fondy_settings[fondy_reccuring]"><?php _e('Fondy Subscription', 'fondy_rcp'); ?></label>
                    </th>
                    <td>
                        <input class="checkbox" type="checkbox" id="rcp_fondy_settings[fondy_reccuring]"
                               style="width: 15px;"
                               name="rcp_fondy_settings[fondy_reccuring]"
                               value="1" <?php if (isset($rcp_fondy_options['fondy_reccuring'])) checked('1', $rcp_fondy_options['fondy_reccuring']); ?>/>
                        <p class="description"><?php _e('Enable Subscription', 'fondy_rcp'); ?></p>
                    </td>
                </tr>

            </table>
        </div>
        <p class="submit">
            <input type="submit" class="button-primary" value="<?php _e('Save Options', 'fondy_rcp'); ?>"/>
        </p>
    </form>
<?php
endif;
}