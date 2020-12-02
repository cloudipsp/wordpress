<?php /**
 * @var string $gateway
 * @var array $values
*/ ?>
<tr class="pmpro_settings_divider gateway gateway_fondy"
    <?php if ($gateway != "fondy") { ?>style="display: none;"<?php } ?>>
    <td colspan="2">
        <?php _e('Fondy Settings', 'pmp-fondy-payment'); ?>
    </td>
</tr>
<tr class="gateway gateway_fondy" <?php if ($gateway != "fondy") { ?>style="display: none;"<?php } ?>>
    <th scope="row" valign="top">
        <label for="fondy_merchantid"><?php _e('Merchant ID', 'pmp-fondy-payment'); ?>:</label>
    </th>
    <td>
        <input type="text" id="fondy_merchantid" name="fondy_merchantid" size="60"
               value="<?php echo esc_attr($values['fondy_merchantid']) ?>"/>
    </td>
</tr>
<tr class="gateway gateway_fondy" <?php if ($gateway != "fondy") { ?>style="display: none;"<?php } ?>>
    <th scope="row" valign="top">
        <label for="fondy_securitykey"><?php _e('Payment key', 'pmp-fondy-payment'); ?>:</label>
    </th>
    <td>
        <textarea id="fondy_securitykey" name="fondy_securitykey" rows="3"
                  cols="80"><?php echo esc_textarea($values['fondy_securitykey']); ?></textarea>
    </td>
</tr>