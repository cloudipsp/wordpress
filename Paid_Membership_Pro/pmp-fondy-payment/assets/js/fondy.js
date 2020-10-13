// reinitCurrencyAndTaxSettings
document.addEventListener("DOMContentLoaded", () => {
    let gatewaySelect = document.getElementById('gateway');
    if (gatewaySelect != null && gatewaySelect.value === 'fondy' && typeof pmpro_changeGateway == "function") {
        pmpro_changeGateway('fondy');
    }
});