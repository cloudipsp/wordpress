function f_block(e) {
    f_is_blocked(e) || e.addClass("processing").block({message: null, overlayCSS: {background: "#fff", opacity: .6}})
}
function f_is_blocked(e) {
    return e.is(".processing") || e.parents(".processing").length
}
function f_unblock(e) {
    e.removeClass("processing").unblock()
}
function nextInput(e, r) {
    clearTimeout(e.keydownIdle), e.keydownIdle = setTimeout(function (t, o, n, a, c) {
        n = (o = Array.prototype.slice.call(e.form.elements)).indexOf(e), 0 === (c = Number(e.value.length)) && 8 === r.keyCode ? t = o[--n] : c === Number(e.getAttribute("maxlength")) && (t = o[++n]), t && (t.focus(), "setSelectionRange" in t && t === document.activeElement && t.setSelectionRange(0, t.value.length))
    })
}
function fondy_submit_order(e) {
    if (jQuery("#payment_method_fondy").is(":checked")) {
        (e || window.event).preventDefault();
        var r = jQuery("form[name=checkout]");
        !r.length && jQuery(".checkout.woocommerce-checkout").length ? r = jQuery(".checkout.woocommerce-checkout") : jQuery("#checkout_fondy_form").find(".error-wrapper").html("Invalid checkout form, please disable on checkout method and try another."), f_clean_error();
        var t = jQuery("#fondy_ccard"), o = jQuery("#fondy_expiry_month"), n = jQuery("#fondy_expiry_year"), a = jQuery("#fondy_cvv2");
        if (console.log(payform.parseCardType(t.val())), console.log(f_valid_credit_card(t.val())), !f_valid_credit_card(t.val()))return fondy_error(t);
        if (!f_valid_month(o.val()))return fondy_error(o);
        if (!f_valid_year(n.val()))return fondy_error(n);
        if (!f_valid_cvv2(a.val()))return fondy_error(a);
        jQuery("#checkout_fondy_form").find(".error-wrapper").hide();
        var c = r.serialize() + "&" + jQuery.param({
                action: "generate_ajax_order_fondy_info",
                nonce_code: fondy_info.nonce
            });
        f_block(jQuery("#checkout_fondy_form")), f_block(jQuery("#place_order")), jQuery.post(fondy_info.url, c, function (e) {
            if ("success" === e.result) {
                jQuery("input[name='token']").length || jQuery("#checkout_fondy_form").append('<input type="hidden" name="token" value=' + e.token + ">");
                var r = {
                    payment_system: "card",
                    token: e.token,
                    card_number: t.val(),
                    expiry_date: o.val() + n.val(),
                    cvv2: a.val()
                };
                $checkout("Api").scope(function () {
                    this.request("api.checkout.form", "request", r).done(function (e) {
                        e.sendResponse(), fondy_post_to_url(e.attr("order").response_url, e.attr("order").order_data, "post")
                    }).fail(function (e) {
                        f_unblock(jQuery("#checkout_fondy_form"));
                        f_unblock(jQuery("#place_order"));
                        var r = e.attr("error").code ? e.attr("error").code : "";
                        jQuery("#checkout_fondy_form").find(".error-wrapper").html(r + ". " + e.attr("error").message).show()
                    })
                })
            } else f_unblock(jQuery("#place_order")), f_unblock(jQuery("#checkout_fondy_form")), jQuery("#checkout_fondy_form").find(".error-wrapper").html(e.messages).show()
        })
    }
}
function f_clean_error() {
    jQuery("#fondy_ccard").removeAttr("style"), jQuery("#fondy_expiry_month").removeAttr("style"), jQuery("#fondy_expiry_year").removeAttr("style"), jQuery("#fondy_cvv2").removeAttr("style")
}
function fondy_error(e) {
    e.css("color", "red"), e.css("border-color", "red")
}
function f_valid_cvv2(e) {
    return "string" == typeof e && ("" !== e && (!!/^\d*$/.test(e) && (!(e.length < 3) && !(e.length > 3))))
}
function f_valid_year(e) {
    var r, t, o, n;
    return maxElapsedYear = 30, "string" == typeof e && ("" !== e.replace(/\s/g, "") && (!!/^\d*$/.test(e) && (!((t = e.length) < 2) && (r = (new Date).getFullYear(), 3 === t ? (e.slice(0, 2), String(r).slice(0, 2), !1) : t > 4 ? verification(!1, !1) : (e = parseInt(e, 10), o = Number(String(r).substr(2, 2)), 2 === t ? (o === e, n = e >= o && e <= o + maxElapsedYear) : 4 === t && (r === e, n = e >= r && e <= r + maxElapsedYear), n)))))
}
function f_valid_month(e) {
    var r;
    return "string" == typeof e && ("" !== e.replace(/\s/g, "") && "0" !== e && (!!/^\d*$/.test(e) && (r = parseInt(e, 10), !isNaN(e) && (r > 0 && r < 13))))
}
function f_valid_credit_card(e) {
    return payform.validateCardNumber(e)
}
function fondy_post_to_url(e, r, t) {
    t = t || "post";
    var o = document.createElement("form");
    o.setAttribute("method", t), o.setAttribute("action", e);
    for (var n in r)if (r.hasOwnProperty(n)) {
        var a = document.createElement("input");
        a.setAttribute("type", "hidden"), a.setAttribute("name", n), a.setAttribute("value", r[n]), o.appendChild(a)
    }
    document.body.appendChild(o), o.submit()
}
function fondy_init_card() {
    var e = document.getElementById("fondy_ccard"), r = document.getElementById("fondy_expiry_month"), t = document.getElementById("fondy_expiry_year"), o = document.getElementById("fondy_cvv2");
    payform.cardNumberInput(e), payform.numericInput(r), payform.numericInput(t), payform.cvcInput(o)
}
jQuery("#billing_email").length && jQuery("#billing_email").on("input", function () {
    jQuery("input[name='sender_email']").val(this.value)
}), jQuery(document).ajaxComplete(function () {
    jQuery("#fondy_ccard").length && fondy_init_card()
});
jQuery(document).ajaxComplete(function () {
    jQuery("#fondy_ccard").keydown(function () {
        if (payform.validateCardNumber(this.value)) {
            jQuery("#f_card_sep").addClass(payform.parseCardType(this.value));
        } else {
            jQuery("#f_card_sep").removeAttr('class');
        }
    });
});
