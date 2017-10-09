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
    return $data;
}

function rcp_fondy_admin_page()
{
    global $rcp_fondy_options;
    ?>
    <?php if ($_REQUEST['settings-updated'] ) : ?>
    <div class="updated fade"><p><strong><?php _e( 'Options saved', 'rcp' ); ?></strong></p></div>
    <?php endif; ?>
    <?php if (function_exists('pw_rcp_register_fondy_gateway')) : ?>
    <form method="post" action="options.php" class="rcp_options_form">
        <?php settings_fields('rcp_fondy_settings_group'); ?>
        <div class="tab_content" id="payments">
            <table class="form-table">
                <tr valign="top">
                    <th colspan=2>
                        <h3><?php _e('Fondy Settings', 'rcp'); ?></h3>
                    </th>
                </tr>

                <tr>
                    <th>
                        <label for="rcp_fondy_settings[fondy_merchant_id]"><?php _e('Fondy MID', 'rcp'); ?></label>
                    </th>
                    <td>
                        <input class="regular-text" id="rcp_fondy_settings[fondy_merchant_id]" style="width: 300px;"
                               name="rcp_fondy_settings[fondy_merchant_id]"
                               value="<?php if (isset($rcp_fondy_options['fondy_merchant_id'])) {
                                   echo $rcp_fondy_options['fondy_merchant_id'];
                               } ?>"/>
                        <p class="description"><?php _e('Enter your Merchant ID', 'rcp'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th>
                        <label for="rcp_fondy_settings[fondy_secret]"><?php _e('Fondy Secret Key', 'rcp'); ?></label>
                    </th>
                    <td>
                        <input class="regular-text" id="rcp_fondy_settings[stripe_test_secret]" style="width: 300px;"
                               name="rcp_fondy_settings[fondy_secret]"
                               value="<?php if (isset($rcp_fondy_options['fondy_secret'])) {
                                   echo $rcp_fondy_options['fondy_secret'];
                               } ?>"/>
                        <p class="description"><?php _e('Enter your secret key.', 'rcp'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th>
                        <label for="rcp_fondy_settings[fondy_reccuring]"><?php _e('Fondy Subscription', 'rcp'); ?></label>
                    </th>
                    <td>
                        <input class="checkbox" type="checkbox" id="rcp_fondy_settings[fondy_reccuring]"
                               style="width: 15px;"
                               name="rcp_fondy_settings[fondy_reccuring]"
                               value="1" <?php if (isset($rcp_fondy_options['fondy_reccuring'])) checked('1', $rcp_fondy_options['fondy_reccuring']); ?>/>
                        <p class="description"><?php _e('Enable Subscription', 'rcp'); ?></p>
                    </td>
                </tr>

            </table>
        </div>
        <p class="submit">
            <input type="submit" class="button-primary" value="<?php _e('Save Options', 'rcp'); ?>"/>
        </p>
    </form>
<?php endif; ?>
    <?php
}