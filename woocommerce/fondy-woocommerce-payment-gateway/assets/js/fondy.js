function f_block($node) {
    if (!f_is_blocked($node)) {
        $node.addClass('processing').block({
            message: null,
            overlayCSS: {
                background: '#fff',
                opacity: 0.6
            }
        });
    }
}

function f_is_blocked($node) {
    return $node.is('.processing') || $node.parents('.processing').length;
}

function f_unblock($node) {
    $node.removeClass('processing').unblock();
}

function nextInput(input, event) {
    clearTimeout(input.keydownIdle);
    input.keydownIdle = setTimeout(function (field, list, index, code, length) {
        list = Array.prototype.slice.call(input.form.elements);
        index = list.indexOf(input);
        length = Number(input.value.length);
        if (length === 0 && event.keyCode === 8) {
            field = list[--index];
        } else if (length === Number(input.getAttribute('maxlength'))) {
            field = list[++index];
        }
        if (field) {
            field.focus();
            if ('setSelectionRange' in field) {
                if (field === document.activeElement) {
                    field.setSelectionRange(0, field.value.length)
                }
            }
        }
    });
}

if (jQuery("#billing_email").length) {
    jQuery("#billing_email").on("input", function () {
        jQuery("input[name='sender_email']").val(this.value)
    });
}

function fondy_submit_order(event) {
    if (jQuery('#payment_method_fondy').is(':checked')) {
        var evt = event || window.event;
        evt.preventDefault();
        var checkout = jQuery('form[name=checkout]');
        if (!checkout.length && jQuery('.checkout.woocommerce-checkout').length) {
            checkout = jQuery('.checkout.woocommerce-checkout');
        } else {
            jQuery("#checkout_fondy_form").find(".error-wrapper").html('Invalid checkout form, please disable on checkout method and try another.')
        }

        f_clean_error();
        var fondy_ccard = jQuery('#fondy_ccard');
        var fondy_expiry_month = jQuery('#fondy_expiry_month');
        var fondy_expiry_year = jQuery('#fondy_expiry_year');
        var fondy_cvv2 = jQuery('#fondy_cvv2');
        if (!f_valid_credit_card(fondy_ccard.val())) {
            return fondy_error(fondy_ccard);
        }
        if (!f_valid_month(fondy_expiry_month.val())) {
            return fondy_error(fondy_expiry_month);
        }
        if (!f_valid_year(fondy_expiry_year.val())) {
            return fondy_error(fondy_expiry_year);
        }
        if (!f_valid_cvv2(fondy_cvv2.val())) {
            return fondy_error(fondy_cvv2);
        }
        jQuery("#checkout_fondy_form").find(".error-wrapper").hide();
        var f_o_data = checkout.serialize() + '&' + jQuery.param({
            'action': 'generate_ajax_order_fondy_info',
            'nonce_code': fondy_info.nonce
        });
        f_block(jQuery('#checkout_fondy_form'));
        f_block(jQuery('#place_order'));
        jQuery.post(fondy_info.url, f_o_data, function (response) {
            if (response.result === 'success') {
                if (!jQuery("input[name='token']").length) {
                    jQuery("#checkout_fondy_form").append('<input type="hidden" name="token" value=' + response.token + '>');
                }
                var Params = {
                    "payment_system": "card",
                    "token": response.token,
                    "card_number": fondy_ccard.val(),
                    "expiry_date": fondy_expiry_month.val() + fondy_expiry_year.val(),
                    "cvv2": fondy_cvv2.val()
                };
                $checkout('Api').scope(function () {
                    this.request('api.checkout.form', 'request', Params).done(function (model) {
                        model.sendResponse();
                        fondy_post_to_url(model.attr('order').response_url, model.attr('order').order_data, 'post');
                    }).fail(function (model) {
                        f_unblock(jQuery('#checkout_fondy_form'));
                        var code = model.attr('error').code ? model.attr('error').code : '';
                        jQuery("#checkout_fondy_form").find(".error-wrapper").html(code + '. ' + model.attr('error').message).show();
                    });
                });
            } else {
                f_unblock(jQuery('#checkout_fondy_form'));
                jQuery("#checkout_fondy_form").find(".error-wrapper").html(response.messages).show();
            }
        });
    }
}

function f_clean_error() {
    jQuery('#fondy_ccard').removeAttr("style");
    jQuery('#fondy_expiry_month').removeAttr("style");
    jQuery('#fondy_expiry_year').removeAttr("style");
    jQuery('#fondy_cvv2').removeAttr("style");
}

function fondy_error(element) {
    element.css('color', 'red');
    element.css('border-color', 'red');
}

function f_valid_cvv2(value) {
    var maxLength = 3;
    if (typeof value !== 'string') {
        return false;
    }
    if (value === '') {
        return false;
    }
    if (!/^\d*$/.test(value)) {
        return false;
    }
    if (value.length < maxLength) {
        return false;
    }
    if (value.length > maxLength) {
        return false;
    }

    return true;
}

function f_valid_year(value) {
    var currentFirstTwo, currentYear, firstTwo, len, twoDigitYear, valid, isCurrentYear;
    maxElapsedYear = 30;
    if (typeof value !== 'string') {
        return false;
    }
    if (value.replace(/\s/g, '') === '') {
        return false;
    }
    if (!/^\d*$/.test(value)) {
        return false;
    }
    len = value.length;
    if (len < 2) {
        return false;
    }
    currentYear = new Date().getFullYear();
    if (len === 3) {
        firstTwo = value.slice(0, 2);
        currentFirstTwo = String(currentYear).slice(0, 2);
        return false;
    }

    if (len > 4) {
        return verification(false, false);
    }

    value = parseInt(value, 10);
    twoDigitYear = Number(String(currentYear).substr(2, 2));

    if (len === 2) {
        isCurrentYear = twoDigitYear === value;
        valid = value >= twoDigitYear && value <= twoDigitYear + maxElapsedYear;
    } else if (len === 4) {
        isCurrentYear = currentYear === value;
        valid = value >= currentYear && value <= currentYear + maxElapsedYear;
    }

    return valid;
}

function f_valid_month(value) {
    var month, result;
    if (typeof value !== 'string') {
        return false;
    }
    if (value.replace(/\s/g, '') === '' || value === '0') {
        return false;
    }
    if (!/^\d*$/.test(value)) {
        return false;
    }

    month = parseInt(value, 10);

    if (isNaN(value)) {
        return false;
    }
    result = month > 0 && month < 13;
    return result;
}

function f_valid_credit_card(value) {
    if (/[^0-9-\s]+/.test(value)) return false;
    if (value === '') return false;
    var sum = 0;
    var alt = false;
    var i = value.length - 1;
    var num;

    while (i >= 0) {
        num = parseInt(value.charAt(i), 10);
        if (alt) {
            num *= 2;
            if (num > 9) {
                num = (num % 10) + 1;
            }
        }
        alt = !alt;
        sum += num;
        i--;
    }

    return sum % 10 === 0;
}

function fondy_post_to_url(path, params, method) {
    method = method || "post";

    var form = document.createElement("form");
    form.setAttribute("method", method);
    form.setAttribute("action", path);

    for (var key in params) {
        if (params.hasOwnProperty(key)) {
            var hiddenField = document.createElement("input");
            hiddenField.setAttribute("type", "hidden");
            hiddenField.setAttribute("name", key);
            hiddenField.setAttribute("value", params[key]);

            form.appendChild(hiddenField);
        }
    }

    document.body.appendChild(form);
    form.submit();
}