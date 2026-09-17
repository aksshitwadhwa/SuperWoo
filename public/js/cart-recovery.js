(function ($) {
    'use strict';
    if (!window.SuperWooRecovery || window.__superwooRecoveryInitialized) { return; }
    window.__superwooRecoveryInitialized = true;

    var timer;
    function value(selector) { return $(selector).first().val() || ''; }
    function capture() {
        $.post(window.SuperWooRecovery.ajaxUrl, {
            action: 'superwoo_recovery_capture', nonce: window.SuperWooRecovery.nonce,
            first_name: value('#billing_first_name'), last_name: value('#billing_last_name'),
            email: value('#billing_email'), phone: value('#billing_phone')
        });
    }
    $(document.body).on('input change', '#billing_first_name, #billing_last_name, #billing_email, #billing_phone', function () {
        window.clearTimeout(timer); timer = window.setTimeout(capture, 900);
    });
}(jQuery));
