'use strict';

document.addEventListener("DOMContentLoaded", () => {
    /**
     * add membershiplevel trial settings description
     *
     * @see paid-memberships-pro/adminpages/membershiplevels.php:522
     * @since 1.0.6
     */
    //
    if (pagenow.includes('pmpro-membershiplevels') && fondy_param !== "undefined") {
        let trialDescriptionBlock = document.createElement('p'),
            trialDescriptionText = document.createElement('strong');

        trialDescriptionBlock.classList.add('description');
        trialDescriptionText.innerText = fondy_param.trialDescriptionText;
        trialDescriptionBlock.appendChild(trialDescriptionText);
        document.querySelector('.trial_info.recurring_info td').appendChild(trialDescriptionBlock);
    }

    /**
     * reinit gateway currency and tax settings
     *
     * @see paid-memberships-pro/paymentsettings.php:190
     * @since 1.0.5
     */
    if (pagenow.includes('pmpro-paymentsettings')) {
        let gatewaySelect = document.getElementById('gateway');

        if (gatewaySelect != null && gatewaySelect.value === 'fondy' && typeof pmpro_changeGateway === "function") {
            pmpro_changeGateway('fondy');
        }
    }
});