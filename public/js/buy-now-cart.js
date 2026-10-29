(function () {
    'use strict';

    var preparing = false;
    var replaying = false;

    function showError(button, message) {
        var notice = document.querySelector('[data-superwoo-buy-now-error]');
        if (!notice) {
            notice = document.createElement('div');
            notice.className = 'woocommerce-error';
            notice.setAttribute('data-superwoo-buy-now-error', '');
            notice.setAttribute('role', 'alert');
            button.parentNode.insertBefore(notice, button);
        }
        notice.textContent = message;
    }

    function send(action, fields) {
        var settings = window.SuperWooBuyNowCart;
        var body = new URLSearchParams({ action: action });
        Object.keys(fields || {}).forEach(function (key) {
            body.append(key, fields[key]);
        });
        return window.fetch(settings.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: body.toString()
        }).then(function (response) {
            return response.json().then(function (data) {
                return { status: response.status, data: data };
            });
        });
    }

    function saveCart(retried) {
        var settings = window.SuperWooBuyNowCart;
        return send('superwoo_snapshot_buy_now_cart', { nonce: settings.nonce }).then(function (result) {
            if (result.data && result.data.success && result.data.data && result.data.data.saved) {
                return true;
            }
            if (!retried && result.status === 403 && result.data && result.data.data
                && result.data.data.code === 'superwoo_buy_now_invalid_nonce') {
                return send('superwoo_refresh_buy_now_nonce', {}).then(function (refresh) {
                    if (!refresh.data || !refresh.data.success || !refresh.data.data || !refresh.data.data.nonce) {
                        throw new Error('nonce_refresh_failed');
                    }
                    settings.nonce = refresh.data.data.nonce;
                    return saveCart(true);
                });
            }
            throw new Error('cart_snapshot_failed');
        });
    }

    // Razorpay binds its own click listener to this button. Hold that click
    // until the original WooCommerce cart is safely saved on the server.
    document.addEventListener('click', function (event) {
        var button = event.target && event.target.closest ? event.target.closest('#btn-1cc-pdp') : null;
        if (!button || button.disabled || button.classList.contains('disabled')) {
            return;
        }
        if (replaying) {
            replaying = false;
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        event.stopImmediatePropagation();
        if (preparing) {
            return;
        }
        preparing = true;
        button.setAttribute('aria-busy', 'true');

        if (!window.SuperWooBuyNowCart || !SuperWooBuyNowCart.ajaxUrl || !window.fetch) {
            preparing = false;
            button.removeAttribute('aria-busy');
            showError(button, 'Buy Now could not preserve your cart. Please try again.');
            return;
        }

        saveCart(false).then(function () {
            var notice = document.querySelector('[data-superwoo-buy-now-error]');
            if (notice) {
                notice.remove();
            }
            try {
                window.sessionStorage.setItem('superwoo_buy_now_pending', '1');
            } catch (error) {
                // The server snapshot still protects the original cart.
            }
            preparing = false;
            button.removeAttribute('aria-busy');
            replaying = true;
            button.click();
            replaying = false;
        }).catch(function () {
            preparing = false;
            button.removeAttribute('aria-busy');
            showError(button, SuperWooBuyNowCart.error || 'Buy Now could not preserve your cart. Please try again.');
        });
    }, true);
}());
