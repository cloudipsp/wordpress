<?php
/** @var string $text_domain */
global $gateway, $pmpro_requirebilling;
?>
<span id="pmpro_fondy_checkout"
      style="<?php echo ($gateway != "fondy" || !$pmpro_requirebilling) ? "display: none;" : '' ?>">
    <input type="hidden" name="submit-checkout" value="1"/>
    <input type="submit" class="pmpro_btn pmpro_btn-submit-checkout"
           value="<?php _e($pmpro_requirebilling ? 'Submit and Check Out' : 'Submit and Confirm', $text_domain) ?> &raquo;"/>
</span>