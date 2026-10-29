(function () {
    'use strict';

    // Razorpay binds Buy Now on the product page. Its order API switches to a
    // temporary cart; mark this tab so the normal cart drawer can request the
    // saved cart when the shopper returns from the checkout modal.
    document.addEventListener('click', function (event) {
        var button = event.target && event.target.closest ? event.target.closest('#btn-1cc-pdp') : null;
        if (!button || button.disabled || button.classList.contains('disabled')) {
            return;
        }
        try {
            window.sessionStorage.setItem('superwoo_buy_now_pending', '1');
        } catch (error) {
            // The server-side snapshot still restores on the next page load.
        }
    }, true);
}());
