(function ($) {
    'use strict';

    var lastFocus = null;
    var triggerObserver = null;
    var triggerSyncTimer = null;
    var stickyBuyNowTimer = null;
    var productCartVisibilityObserver = null;
    var observedProductCartForm = null;
    var storeApiNonce = '';
    var currentOfferState = window.SuperWooCart && SuperWooCart.offerState ? SuperWooCart.offerState : { discounts: {}, gifts: {} };
    var productAddInFlight = false;
    var lastProductAddKey = '';
    var lastProductAddAt = 0;

    function shell() {
        return $('[data-superwoo-cart-shell]').first();
    }

    function drawer() {
        return shell().find('.superwoo-cart-drawer').first();
    }

    function isDashboardUrl() {
        var path = window.location && window.location.pathname ? window.location.pathname.toLowerCase() : '';

        path = path.replace(/^\/+|\/+$/g, '');

        return path === 'dashboard' || path.indexOf('dashboard/') === 0 || path.indexOf('/dashboard/') !== -1;
    }

    function suppressDashboardBottomNav() {
        if (!(window.SuperWooCart && SuperWooCart.dashboardPage) && !isDashboardUrl()) {
            return false;
        }

        $('body')
            .addClass('superwoo-dashboard-page')
            .removeClass('superwoo-has-mobile-bottom-nav superwoo-mobile-search-open');

        $('[data-superwoo-mobile-bottom-nav], .superwoo-mobile-bottom-nav, [data-superwoo-mobile-search-sheet], .superwoo-mobile-search-sheet').remove();

        return true;
    }

    function announce(message) {
        shell().find('.superwoo-cart-live').text(message || '');
    }

    function offerStateItems(state, type) {
        return state && state[type] ? state[type] : {};
    }

    function firstNewOfferMessage(previousState, nextState) {
        var types = ['gifts', 'discounts'];
        var found = '';

        $.each(types, function (_index, type) {
            var previousItems = offerStateItems(previousState, type);
            var nextItems = offerStateItems(nextState, type);

            $.each(nextItems, function (key, item) {
                if (!previousItems[key] && item && item.message) {
                    found = item.message;
                    return false;
                }
            });

            return !found;
        });

        return found;
    }

    function showOfferEvents(events) {
        var i;

        if (!$.isArray(events)) {
            return false;
        }

        for (i = 0; i < events.length; i += 1) {
            if (events[i] && events[i].message) {
                return true;
            }
        }

        return false;
    }

    function syncOfferState(nextState, suppressToast) {
        var message;

        if (!nextState) {
            return;
        }

        message = firstNewOfferMessage(currentOfferState, nextState);
        currentOfferState = nextState;

        if (window.SuperWooCart) {
            SuperWooCart.offerState = nextState;
        }

        return !!message && !suppressToast;
    }

    function scrollContainer() {
        return shell().find('[data-superwoo-cart-scroll]').first();
    }

    function resolvedHash(element) {
        var href = $(element).attr('href') || '';

        if (!href) {
            return '';
        }

        if (href.charAt(0) === '#') {
            return href;
        }

        try {
            return new URL(href, window.location.href).hash;
        } catch (error) {
            return /#superwoo-cart(?:$|[?&])/.test(href) ? '#superwoo-cart' : '';
        }
    }

    function isCartTriggerLink(element) {
        return $(element).is('[data-superwoo-cart-trigger]') || resolvedHash(element) === '#superwoo-cart';
    }

    function closestCartTrigger(target) {
        if (!target || target === document) {
            return null;
        }

        if (target.closest) {
            return target.closest('a[href="#superwoo-cart"], a[href$="#superwoo-cart"], [data-superwoo-cart-trigger]');
        }

        return $(target).closest('a[href], [data-superwoo-cart-trigger]').get(0);
    }

    function currentCartCount() {
        var count = parseInt(window.SuperWooCart && SuperWooCart.cartCount, 10);

        if (isNaN(count)) {
            count = parseInt($('.superwoo-cart-drawer__header .superwoo-cart-count, .superwoo-cart-button .superwoo-cart-count, .superwoo-elementor-cart-count').first().text(), 10);
        }

        return isNaN(count) ? 0 : count;
    }

    function setCartCount(count) {
        var nextCount = parseInt(count, 10);

        if (isNaN(nextCount)) {
            nextCount = 0;
        }

        if (window.SuperWooCart) {
            SuperWooCart.cartCount = nextCount;
        }
        $('.superwoo-cart-count').text(nextCount);
    }

    function cartTriggerLinks() {
        return $('a[href], [data-superwoo-cart-trigger]').filter(function () {
            return isCartTriggerLink(this);
        });
    }

    function cartTriggerIcon() {
        return '<svg class="superwoo-elementor-cart-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M2 3h2.2l2.2 11.2a2 2 0 0 0 2 1.6h8.9a2 2 0 0 0 2-1.6L21 7H5"/><circle cx="9" cy="20" r="1"/><circle cx="18" cy="20" r="1"/></svg>';
    }

    function syncCartTriggerBadges() {
        cartTriggerLinks().each(function () {
            var $trigger = $(this);
            var label = (window.SuperWooCart && SuperWooCart.i18n && SuperWooCart.i18n.cartItems) ? SuperWooCart.i18n.cartItems : 'Cart items';
            var $icon = $trigger.children('.elementor-icon').first();

            $trigger.addClass('superwoo-elementor-cart-trigger');

            if (!$trigger.find('.superwoo-elementor-cart-icon').length) {
                if ($icon.length) {
                    $icon.empty().append(cartTriggerIcon());
                } else {
                    $trigger.prepend(cartTriggerIcon());
                }
            }

            $trigger.children('.superwoo-cart-count:not(.superwoo-elementor-cart-count)').remove();

            if (!$trigger.children('.superwoo-elementor-cart-count').length) {
                $trigger.append('<span class="superwoo-cart-count superwoo-elementor-cart-count" aria-label="' + label + '">0</span>');
            }
        });

        setCartCount(currentCartCount());
    }

    function scheduleTriggerSync() {
        window.clearTimeout(triggerSyncTimer);
        triggerSyncTimer = window.setTimeout(syncCartTriggerBadges, 50);
    }

    function startTriggerObserver() {
        if (triggerObserver || !window.MutationObserver || !document.body) {
            return;
        }

        triggerObserver = new MutationObserver(scheduleTriggerSync);
        triggerObserver.observe(document.body, {
            attributes: true,
            attributeFilter: ['href', 'data-superwoo-cart-trigger'],
            childList: true,
            subtree: true
        });
    }

    function fetchCartCount() {
        if (!window.SuperWooCart || !SuperWooCart.cartApiUrl || !window.fetch) {
            return;
        }

        window.fetch(SuperWooCart.cartApiUrl, {
            credentials: 'same-origin'
        })
            .then(function (response) {
                captureStoreApiTokens(response);
                return response.ok ? response.json() : null;
            })
            .then(function (cart) {
                if (cart && typeof cart.items_count !== 'undefined') {
                    setCartCount(cart.items_count);
                }
            })
            .catch(function () {});
    }

    function captureStoreApiTokens(response) {
        if (!response || !response.headers) {
            return;
        }

        storeApiNonce = response.headers.get('Nonce') || storeApiNonce;
    }

    function refreshDrawerAfterStoreApiChange() {
        return post('superwoo_refresh_cart_drawer', {}, null, {
            refreshWooFragments: true
        });
    }

    function mutateWooCommerceCart(endpoint, payload) {
        var headers = {
            'Content-Type': 'application/json'
        };

        if (!window.SuperWooCart || !SuperWooCart.cartApiUrl || !window.fetch) {
            return $.Deferred().reject().promise();
        }

        if (!storeApiNonce) {
            return $.Deferred(function (deferred) {
                window.fetch(SuperWooCart.cartApiUrl, { credentials: 'same-origin' })
                    .then(function (response) {
                        captureStoreApiTokens(response);
                        if (!response.ok || !storeApiNonce) {
                            throw new Error('WooCommerce cart session is unavailable.');
                        }
                        return mutateWooCommerceCart(endpoint, payload);
                    })
                    .then(deferred.resolve)
                    .catch(deferred.reject);
            }).promise();
        }

        if (storeApiNonce) {
            headers.Nonce = storeApiNonce;
        }

        return $.Deferred(function (deferred) {
            window.fetch(SuperWooCart.cartApiUrl + '/' + endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                headers: headers,
                body: JSON.stringify(payload)
            })
                .then(function (response) {
                    captureStoreApiTokens(response);
                    if (!response.ok) {
                        throw new Error('WooCommerce cart update failed.');
                    }
                    return response.json();
                })
                .then(deferred.resolve)
                .catch(deferred.reject);
        }).promise();
    }

    function syncDrawerLayout() {
        var $drawer = drawer();
        var $inner = $drawer.find('.superwoo-cart-drawer__inner').first();
        var headerHeight = $drawer.find('.superwoo-cart-drawer__header').outerHeight() || 64;
        var footerHeight = $drawer.find('.superwoo-cart-drawer__footer').outerHeight() || 112;

        if (!$inner.length) {
            return;
        }

        $inner.css({
            '--superwoo-header-offset': headerHeight + 'px',
            '--superwoo-footer-offset': footerHeight + 'px'
        });
    }



    function openCart() {
        var $shell = shell();
        if (!$shell.length) {
            return;
        }

        lastFocus = document.activeElement;
        $shell.addClass('is-open').attr('aria-hidden', 'false');
        $('body').addClass('superwoo-cart-open');
        syncDrawerLayout();
        window.setTimeout(function () {
            syncDrawerLayout();
            drawer().trigger('focus');
        }, 20);
    }

    function closeCart() {
        var $shell = shell();
        $shell.removeClass('is-open').attr('aria-hidden', 'true');
        $('body').removeClass('superwoo-cart-open');

        if (lastFocus && typeof lastFocus.focus === 'function') {
            lastFocus.focus();
        }
    }

    function mobileSearchSheet() {
        return $('[data-superwoo-mobile-search-sheet]').first();
    }

    function focusMobileSearchInput() {
        var $input = $('[data-superwoo-mobile-search-input]').first();

        if ($input.length) {
            $input.trigger('focus');
        }
    }

    function openMobileSearch() {
        var $sheet;

        $sheet = mobileSearchSheet();
        $sheet.addClass('is-open').attr('aria-hidden', 'false');
        $('body').addClass('superwoo-mobile-search-open');
        window.setTimeout(focusMobileSearchInput, 80);
    }

    function closeMobileSearch() {
        mobileSearchSheet().removeClass('is-open').attr('aria-hidden', 'true');
        $('body').removeClass('superwoo-mobile-search-open');
    }

    function replaceFragments(fragments) {
        if (!fragments) {
            return;
        }

        var drawerInner = fragments['.superwoo-cart-drawer__inner'] || null;

        $.each(fragments, function (selector, html) {
            if (selector === '.superwoo-cart-drawer__inner') {
                return;
            }

            $(selector).replaceWith(html);
        });

        if (drawerInner) {
            var $drawer = drawer();
            var $inner = $drawer.find('.superwoo-cart-drawer__inner').first();
            var $replacement = $(drawerInner).filter('.superwoo-cart-drawer__inner').first();

            if ($replacement.length) {
                if ($inner.length) {
                    $inner.replaceWith($replacement);
                } else {
                    $drawer.html($replacement);
                }
            } else if ($inner.length) {
                $inner.html(drawerInner);
            } else {
                $drawer.html('<div class="superwoo-cart-drawer__inner">' + drawerInner + '</div>');
            }
        }

        syncDrawerLayout();
        syncCartTriggerBadges();
        $(document.body).trigger('wc_fragments_refreshed');
    }

    function post(action, data, $item, options) {
        options = options || {};
        data = data || {};
        if (action) {
            data.action = action;
        }
        data.nonce = SuperWooCart.nonce;
        data.security = SuperWooCart.nonce;
        data._ = Date.now();

        if ($item && $item.length) {
            $item.addClass('is-loading');
        }

        announce(SuperWooCart.i18n.updating);

        return $.ajax({
            url: options.url || SuperWooCart.ajaxUrl,
            method: 'POST',
            data: data,
            cache: false
        })
            .done(function (response) {
                if (response && response.success && response.data) {
                    replaceFragments(response.data.fragments);
                    syncOfferState(response.data.offerState, showOfferEvents(response.data.offerEvents));
                    if (typeof response.data.count !== 'undefined') {
                        setCartCount(response.data.count);
                    }
                    if (options.refreshWooFragments) {
                        $(document.body).trigger('wc_fragment_refresh');
                    }
                    if (options.openCart) {
                        openCart();
                    }
                    announce('');
                } else {
                    if (window.console && response && response.data && response.data.message) {
                        window.console.warn('SuperWoo cart:', response.data.message);
                    }
                    announce(SuperWooCart.i18n.error);
                }
            })
            .fail(function (xhr) {
                if (window.console && xhr && xhr.responseText) {
                    window.console.warn('SuperWoo cart request failed:', xhr.responseText);
                }
                announce(SuperWooCart.i18n.error);
            })
            .always(function () {
                if ($item && $item.length) {
                    $item.removeClass('is-loading');
                }
            });
    }

    function wcPost(endpoint, data, $item, options) {
        if (!window.SuperWooCart || !SuperWooCart.wcAjaxUrl) {
            return $.Deferred().reject().promise();
        }

        options = options || {};
        options.url = SuperWooCart.wcAjaxUrl.replace('%%endpoint%%', endpoint);
        return post('', data, $item, options);
    }

    function magicCheckoutConfig() {
        return (window.SuperWooCart && SuperWooCart.magicCheckout) ? SuperWooCart.magicCheckout : {};
    }

    function browserDateTime() {
        var date = new Date();
        var days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        var months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
        var hour = date.getHours();
        var hourLabel = (hour < 10 ? '0' + hour : hour) + '-' + (hour + 1 < 10 ? '0' + (hour + 1) : hour + 1);

        return [hourLabel, days[date.getDay()], months[date.getMonth()]];
    }

    function setPaymentButtonState($button, isLoading) {
        $button.data('superwoo-paying', !!isLoading)
            .prop('disabled', !!isLoading)
            .toggleClass('is-loading', !!isLoading);
    }

    function ensureMagicCheckoutScript() {
        var config = magicCheckoutConfig();

        if (window.Razorpay && typeof window.Razorpay === 'function') {
            return $.Deferred().resolve().promise();
        }

        if (!config.checkoutScriptUrl) {
            return $.Deferred().reject().promise();
        }

        return $.ajax({
            url: config.checkoutScriptUrl,
            dataType: 'script',
            cache: true
        });
    }

    function buildMagicCheckoutBody() {
        var config = magicCheckoutConfig();
        var body = {};

        body.nonce = config.restNonce || body.nonce || '';
        body.siteurl = config.siteUrl || window.location.origin;
        body.blogname = config.blogName || document.title;
        body.cookies = config.cookies || {};
        body.requestData = config.requestData || {};
        body.version = config.version || '';
        body.currency = config.selectedCurrency || '';
        body.dateTime = browserDateTime();

        if (window.accessToken) {
            body.token = window.accessToken;
        }

        return body;
    }

    function saveMagicAbandonedCart(orderId) {
        var config = magicCheckoutConfig();

        if (!orderId || !config.abandonedCartApi) {
            return;
        }

        $.ajax({
            url: config.abandonedCartApi,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ order_id: orderId })
        });
    }

    function openMagicCheckout(data, $button) {
        var checkout;

        checkout = new Razorpay($.extend(true, {}, data, {
            modal: {
                ondismiss: function () {
                    saveMagicAbandonedCart(data.order_id);
                    setPaymentButtonState($button, false);
                    announce('');
                }
            }
        }));

        checkout.open();
    }

    function startDirectPayment($button) {
        var config = magicCheckoutConfig();

        if (!$button.length || $button.data('superwoo-paying')) {
            return;
        }

        if (!config.enabled || !config.orderApi) {
            announce(SuperWooCart.i18n.magicMissing || SuperWooCart.i18n.paymentError || SuperWooCart.i18n.error);
            return;
        }

        setPaymentButtonState($button, true);
        announce(SuperWooCart.i18n.redirecting || SuperWooCart.i18n.updating);

        ensureMagicCheckoutScript()
            .then(function () {
                return $.ajax({
                    url: config.orderApi,
                    method: 'POST',
                    contentType: 'application/json',
                    headers: {
                        'X-WP-Nonce': config.restNonce || ''
                    },
                    data: JSON.stringify(buildMagicCheckoutBody()),
                    cache: false
                });
            })
            .done(function (data) {
                try {
                    openMagicCheckout(data, $button);
                } catch (error) {
                    if (window.console) {
                        window.console.warn('SuperWoo Magic Checkout failed:', error);
                    }
                    announce(SuperWooCart.i18n.paymentError || SuperWooCart.i18n.error);
                    setPaymentButtonState($button, false);
                }
            })
            .fail(function (xhr) {
                if (window.console && xhr && xhr.responseText) {
                    window.console.warn('SuperWoo Magic Checkout request failed:', xhr.responseText);
                }
                announce(SuperWooCart.i18n.paymentError || SuperWooCart.i18n.error);
                setPaymentButtonState($button, false);
            });
    }

    function serializeForm($form, $button) {
        var data = {};
        var $quantity = $form.find('input.qty[name="quantity"]:enabled, input[name="quantity"]:enabled').first();
        var quantity = 1;

        $.each($form.serializeArray(), function (_index, field) {
            data[field.name] = field.value;
        });

        if ($button && $button.length && $button.attr('name')) {
            data[$button.attr('name')] = $button.val();
        }

        if (!data.product_id) {
            data.product_id = $form.find('input[name="product_id"]').val() || $form.find('[name="add-to-cart"]').val() || $button.val() || 0;
        }

        quantity = parseInt($quantity.length ? $quantity.val() : (data.quantity || 1), 10) || 1;
        data.quantity = Math.max(1, quantity);

        return data;
    }

    function productAddKey(data) {
        var parts = [
            data.product_id || 0,
            data.variation_id || 0,
            data.quantity || 1
        ];

        $.each(data, function (name, value) {
            if (name.indexOf('attribute_') === 0) {
                parts.push(name + '=' + value);
            }
        });

        return parts.join('|');
    }

    function productAddActionId(key) {
        var now = Date.now();
        var action = window.SuperWooCart && SuperWooCart._superwooProductAddAction;

        if (action && action.key === key && now - action.at < 2500) {
            return action.id;
        }

        action = {
            at: now,
            id: 'sw-' + now + '-' + Math.random().toString(36).slice(2, 12),
            key: key
        };

        if (window.SuperWooCart) {
            SuperWooCart._superwooProductAddAction = action;
        }

        return action.id;
    }

    function shouldBlockDuplicateProductAdd($form, key) {
        var now = Date.now();
        var formKey = $form.data('superwoo-last-add-key') || '';
        var formAt = parseInt($form.data('superwoo-last-add-at'), 10) || 0;

        if (productAddInFlight || $form.data('superwoo-submitting')) {
            return true;
        }

        if (key && key === lastProductAddKey && now - lastProductAddAt < 1400) {
            return true;
        }

        if (key && key === formKey && now - formAt < 1400) {
            return true;
        }

        return false;
    }

    function rememberProductAdd($form, key) {
        lastProductAddKey = key;
        lastProductAddAt = Date.now();
        $form.data('superwoo-last-add-key', key);
        $form.data('superwoo-last-add-at', lastProductAddAt);
    }

    function canAjaxSubmitProductForm($form) {
        if (!$form.length || !$form.closest('.product').length) {
            return false;
        }

        if ($form.hasClass('grouped_form') || $form.find('.group_table').length) {
            return false;
        }

        if ($form.closest('.product').find('.product_type_external').length) {
            return false;
        }

        return true;
    }

    function setProductButtonPending($button, isPending) {
        var node;
        var rect;
        var oldStyles;

        if (!$button || !$button.length) {
            return;
        }

        node = $button.get(0);
        oldStyles = $button.data('superwoo-old-product-button-style');

        if (isPending) {
            if (!oldStyles) {
                rect = node.getBoundingClientRect ? node.getBoundingClientRect() : null;
                oldStyles = {
                    ariaDisabled: $button.attr('aria-disabled') || '',
                    html: $button.is('input') ? '' : $button.html(),
                    pointerEvents: node.style.pointerEvents || '',
                    value: $button.is('input') ? $button.val() : '',
                    width: node.style.width || '',
                    minWidth: node.style.minWidth || '',
                    maxWidth: node.style.maxWidth || '',
                    flex: node.style.flex || '',
                    flexBasis: node.style.flexBasis || ''
                };

                $button.data('superwoo-old-product-button-style', oldStyles);

                if (rect && rect.width > 0 && !isMobileProductView()) {
                    node.style.setProperty('width', rect.width + 'px', 'important');
                    node.style.setProperty('min-width', rect.width + 'px', 'important');
                    node.style.setProperty('max-width', rect.width + 'px', 'important');
                    node.style.setProperty('flex', '0 0 ' + rect.width + 'px', 'important');
                    node.style.setProperty('flex-basis', rect.width + 'px', 'important');
                }
            }

            $button.attr('aria-disabled', 'true').addClass('superwoo-product-adding');
            if ($button.is('input')) {
                $button.val('');
            } else {
                $button.html('<span class="superwoo-product-spinner" aria-hidden="true"></span>');
            }
            node.style.setProperty('pointer-events', 'none', 'important');
            return;
        }

        $button.removeClass('superwoo-product-adding');

        if (oldStyles) {
            if (oldStyles.ariaDisabled) {
                $button.attr('aria-disabled', oldStyles.ariaDisabled);
            } else {
                $button.removeAttr('aria-disabled');
            }

            node.style.pointerEvents = oldStyles.pointerEvents;
            if ($button.is('input')) {
                $button.val(oldStyles.value);
            } else {
                $button.html(oldStyles.html);
            }
            node.style.width = oldStyles.width;
            node.style.minWidth = oldStyles.minWidth;
            node.style.maxWidth = oldStyles.maxWidth;
            node.style.flex = oldStyles.flex;
            node.style.flexBasis = oldStyles.flexBasis;
            $button.removeData('superwoo-old-product-button-style');
        } else {
            node.style.removeProperty('pointer-events');
        }
    }

    function isMobileProductView() {
        return document.body && document.body.classList.contains('single-product') && window.matchMedia('(max-width: 767px)').matches;
    }

    function productCartForm() {
        return $('body.single-product form.cart').first();
    }

    function buyNowSelectors() {
        return [
            '.buy-now',
            '.buy_now',
            '.single_buy_now_button',
            '.single-buynow-button',
            '.wd-buy-now-btn',
            '.woodmart-buy-now-btn',
            '.xoo-wsc-ft-btn-checkout',
            'button[name="buy_now"]',
            'button[name="buy-now"]',
            'button[value="buy_now"]',
            'button[value="buy-now"]',
            'a[href*="buy-now"]'
        ];
    }

    function isBuyNowControl(element) {
        var label;

        if (!element || !element.matches) {
            return false;
        }

        if (element.matches(buyNowSelectors().join(','))) {
            return true;
        }

        if (!/^(button|a|input)$/i.test(element.tagName)) {
            return false;
        }

        label = ($(element).is('input') ? $(element).val() : $(element).text()) || '';
        return /^buy\s*now\b/i.test($.trim(label));
    }

    function isNativeAddToCartControl(element) {
        var $element = $(element);
        var label;

        if (!element || !element.matches || isBuyNowControl(element)) {
            return false;
        }

        if ($element.is('[data-superwoo-sticky-add-to-cart], [data-superwoo-mobile-buy-now] *')) {
            return false;
        }

        if ($element.is('button[name="add-to-cart"], input[name="add-to-cart"], button.single_add_to_cart_button')) {
            return true;
        }

        label = ($element.is('input') ? $element.val() : $element.text()) || '';
        return /^add\s*to\s*cart\b/i.test($.trim(label));
    }

    function forceMobileBuyNowLayout(element) {
        var parent;

        if (!element || !element.style) {
            return;
        }

        element.style.setProperty('box-sizing', 'border-box', 'important');
        element.style.setProperty('display', 'flex', 'important');
        element.style.setProperty('float', 'none', 'important');
        element.style.setProperty('justify-content', 'center', 'important');
        element.style.setProperty('margin', '0 0 10px', 'important');
        element.style.setProperty('max-width', '100%', 'important');
        element.style.setProperty('min-width', '0', 'important');
        element.style.setProperty('text-align', 'center', 'important');
        element.style.setProperty('width', '100%', 'important');

        parent = element.parentElement;
        if (parent && parent.style && !(parent.matches && parent.matches('form.cart'))) {
            parent.style.setProperty('box-sizing', 'border-box', 'important');
            parent.style.setProperty('display', 'block', 'important');
            parent.style.setProperty('float', 'none', 'important');
            parent.style.setProperty('margin', '0 0 10px', 'important');
            parent.style.setProperty('max-width', '100%', 'important');
            parent.style.setProperty('width', '100%', 'important');
        }
    }

    function forceInlineCartGrid($form) {
        var form = $form && $form.length ? $form.get(0) : null;
        var $layout;
        var $variationWrap;
        var $quantity;
        var $addToCart;
        var $buyNow;
        var isVariationForm;

        if (!form || !isMobileProductView()) {
            return;
        }

        isVariationForm = $form.hasClass('variations_form');

        form.style.setProperty('box-sizing', 'border-box', 'important');
        form.style.setProperty('float', 'none', 'important');
        form.style.setProperty('margin', '0 0 10px', 'important');
        form.style.setProperty('max-width', '100%', 'important');
        form.style.setProperty('width', '100%', 'important');

        if (isVariationForm) {
            form.style.setProperty('display', 'block', 'important');
            form.style.removeProperty('gap');
            form.style.removeProperty('grid-auto-flow');
            form.style.removeProperty('grid-template-columns');
        } else {
            form.style.setProperty('display', 'grid', 'important');
            form.style.setProperty('gap', '10px', 'important');
            form.style.setProperty('grid-auto-flow', 'row', 'important');
            form.style.setProperty('grid-template-columns', 'calc(30% - 5px) calc(70% - 5px)', 'important');
        }

        $layout = $form.find('.woocommerce-variation-add-to-cart').first();
        if ($layout.length) {
            $layout.get(0).style.setProperty('display', 'grid', 'important');
            $layout.get(0).style.setProperty('gap', '10px', 'important');
            $layout.get(0).style.setProperty('grid-auto-flow', 'row', 'important');
            $layout.get(0).style.setProperty('grid-template-columns', 'calc(30% - 5px) calc(70% - 5px)', 'important');
            $layout.get(0).style.setProperty('width', '100%', 'important');
        }

        $variationWrap = $form.find('.single_variation_wrap').first();
        if ($variationWrap.length) {
            $variationWrap.get(0).style.setProperty('display', 'block', 'important');
            $variationWrap.get(0).style.setProperty('margin', '0', 'important');
            $variationWrap.get(0).style.setProperty('max-width', '100%', 'important');
            $variationWrap.get(0).style.setProperty('width', '100%', 'important');
            $variationWrap.get(0).style.removeProperty('gap');
            $variationWrap.get(0).style.removeProperty('grid-auto-flow');
            $variationWrap.get(0).style.removeProperty('grid-template-columns');
        }

        $quantity = $form.find('.quantity').first();
        if ($quantity.length) {
            $quantity.get(0).style.setProperty('align-items', 'stretch', 'important');
            $quantity.get(0).style.setProperty('display', 'grid', 'important');
            $quantity.get(0).style.setProperty('grid-column', '1', 'important');
            $quantity.get(0).style.setProperty('grid-row', '1', 'important');
            $quantity.get(0).style.setProperty('grid-template-columns', '1fr 1fr 1fr', 'important');
            $quantity.get(0).style.setProperty('margin', '0', 'important');
            $quantity.get(0).style.setProperty('min-width', '0', 'important');
            $quantity.get(0).style.setProperty('overflow', 'visible', 'important');
            $quantity.get(0).style.setProperty('width', '100%', 'important');

            $quantity.find('.qty-btn, button, input.qty, input[name="quantity"]').each(function () {
                this.style.setProperty('align-items', 'center', 'important');
                this.style.setProperty('box-sizing', 'border-box', 'important');
                this.style.setProperty('display', 'inline-flex', 'important');
                this.style.setProperty('justify-content', 'center', 'important');
                this.style.setProperty('margin', '0', 'important');
                this.style.setProperty('min-width', '0', 'important');
                this.style.setProperty('opacity', '1', 'important');
                this.style.setProperty('padding-left', '0', 'important');
                this.style.setProperty('padding-right', '0', 'important');
                this.style.setProperty('visibility', 'visible', 'important');
                this.style.setProperty('width', '100%', 'important');
            });
        }

        $addToCart = nativeAddToCartButton($form);
        if ($addToCart.length) {
            $addToCart.get(0).style.setProperty('display', 'inline-flex', 'important');
            $addToCart.get(0).style.setProperty('grid-column', '2', 'important');
            $addToCart.get(0).style.setProperty('grid-row', '1', 'important');
            $addToCart.get(0).style.setProperty('justify-content', 'center', 'important');
            $addToCart.get(0).style.setProperty('margin', '0', 'important');
            $addToCart.get(0).style.setProperty('width', '100%', 'important');
        }

        $buyNow = $form.find('.superwoo-native-buy-now-mobile').first();
        if ($buyNow.length) {
            $buyNow.get(0).style.setProperty('display', 'flex', 'important');
            $buyNow.get(0).style.setProperty('grid-column', '1 / -1', 'important');
            $buyNow.get(0).style.setProperty('grid-row', '2', 'important');
            $buyNow.get(0).style.setProperty('justify-content', 'center', 'important');
            $buyNow.get(0).style.setProperty('margin', '0', 'important');
            $buyNow.get(0).style.setProperty('width', '100%', 'important');
        }
    }

    function auditMobileProductButtons($form) {
        var $scope = $form.closest('.product, .summary, .elementor-widget-woocommerce-product-add-to-cart, .elementor-section, .elementor-container').first();
        var $controls = $form.find('button, input[type="submit"], input[type="button"], a');

        if ($scope.length) {
            $controls = $controls.add($scope.find('button, input[type="submit"], input[type="button"], a'));
        }

        $controls.each(function () {
            var $control = $(this);

            $control.removeClass('superwoo-native-add-to-cart-mobile superwoo-native-buy-now-mobile');
            $control.parent().removeClass('superwoo-native-buy-now-wrap-mobile');

            if (isBuyNowControl(this)) {
                $control.addClass('superwoo-native-buy-now-mobile');
                if (!$control.parent().is('form.cart')) {
                    $control.parent().addClass('superwoo-native-buy-now-wrap-mobile');
                }
                this.style.removeProperty('visibility');
                this.style.removeProperty('opacity');
                forceMobileBuyNowLayout(this);
                return;
            }

            if (isNativeAddToCartControl(this)) {
                $control.addClass('superwoo-native-add-to-cart-mobile');
            }
        });

        forceInlineCartGrid($form);
    }

    function nativeAddToCartButton($form) {
        var $classified = $form.find('.superwoo-native-add-to-cart-mobile').first();

        if ($classified.length) {
            return $classified;
        }

        return $form.find('button.single_add_to_cart_button[type="submit"], button.single_add_to_cart_button, button[name="add-to-cart"], input[name="add-to-cart"]').filter(function () {
            return isNativeAddToCartControl(this);
        }).first();
    }

    function stickyBuyNowShell() {
        var $sticky = $('[data-superwoo-mobile-buy-now]').first();

        if ($sticky.length) {
            if (!$sticky.find('[data-superwoo-mobile-qty-input]').length) {
                $sticky.prepend('<div class="superwoo-mobile-buy-now__qty" aria-label="Choose quantity"><button type="button" data-superwoo-mobile-qty-minus aria-label="Decrease quantity">−</button><input type="number" min="1" step="1" value="1" data-superwoo-mobile-qty-input aria-label="Quantity"><button type="button" data-superwoo-mobile-qty-plus aria-label="Increase quantity">+</button></div>');
            }
            $sticky.find('[data-superwoo-sticky-buy-now]').attr('data-superwoo-sticky-add-to-cart', '').removeAttr('data-superwoo-sticky-buy-now');
            return $sticky;
        }

        $sticky = $('<div class="superwoo-mobile-buy-now" data-superwoo-mobile-buy-now><div class="superwoo-mobile-buy-now__qty" aria-label="Choose quantity"><button type="button" data-superwoo-mobile-qty-minus aria-label="Decrease quantity">−</button><input type="number" min="1" step="1" value="1" data-superwoo-mobile-qty-input aria-label="Quantity"><button type="button" data-superwoo-mobile-qty-plus aria-label="Increase quantity">+</button></div><button type="button" class="superwoo-mobile-buy-now__button" data-superwoo-sticky-add-to-cart>ADD TO CART</button></div>');
        $('body').append($sticky);
        return $sticky;
    }

    function productQuantityInput($form) {
        return $form.find('input.qty[name="quantity"], input[name="quantity"]').first();
    }

    function syncStickyQuantity($form, $sticky) {
        var $formQty = productQuantityInput($form);
        var $stickyQty = $sticky.find('[data-superwoo-mobile-qty-input]').first();
        var value = parseInt($formQty.val(), 10) || parseInt($stickyQty.val(), 10) || 1;

        value = Math.max(1, value);
        $stickyQty.val(value);
        if ($formQty.length) {
            $formQty.val(value).trigger('change');
        }
    }

    function setMobileProductBarState(showSticky) {
        var $body = $('body');

        if (!isMobileProductView()) {
            $body.removeClass('superwoo-mobile-product-sticky-active');
            return;
        }

        $body.toggleClass('superwoo-mobile-product-sticky-active', !!showSticky);
    }

    function observeOriginalProductCart($form) {
        var form = $form.get(0);
        var addButton = nativeAddToCartButton($form).get(0);
        var target = addButton || form;

        if (!target || !window.IntersectionObserver) {
            setMobileProductBarState(false);
            return;
        }

        if (observedProductCartForm === target && productCartVisibilityObserver) {
            return;
        }

        if (productCartVisibilityObserver) {
            productCartVisibilityObserver.disconnect();
        }

        // The form can include variation selectors and other content. Observe
        // the real native add control so the state changes immediately after
        // the original Add to Cart section has scrolled above the viewport.
        observedProductCartForm = target;
        if (target.getBoundingClientRect().bottom <= 0) {
            $form.data('superwoo-cart-form-seen', true);
        }

        productCartVisibilityObserver = new IntersectionObserver(function (entries) {
            var entry = entries[0];
            var hasBeenVisible;

            if (!entry || !isMobileProductView()) {
                setMobileProductBarState(false);
                return;
            }

            hasBeenVisible = $form.data('superwoo-cart-form-seen') === true;
            if (entry.isIntersecting) {
                $form.data('superwoo-cart-form-seen', true);
                setMobileProductBarState(false);
                return;
            }

            setMobileProductBarState(hasBeenVisible && entry.boundingClientRect.bottom <= 0);
        }, {
            threshold: 0
        });

        productCartVisibilityObserver.observe(target);
    }

    function setStickyQuantity(value) {
        var $form = productCartForm();
        var $sticky = stickyBuyNowShell();
        var next = Math.max(1, parseInt(value, 10) || 1);

        $sticky.find('[data-superwoo-mobile-qty-input]').val(next);
        productQuantityInput($form).val(next).trigger('change');
    }

    function syncStickyBuyNow() {
        var $form = productCartForm();
        var $existingSticky = $('[data-superwoo-mobile-buy-now]').first();
        var $sticky;
        var $button;
        var $addToCart;
        var disabled = false;

        // This is display-only. Do not require SuperWoo's former custom-AJAX
        // eligibility before showing a sticky control for a native WC form.
        if (!isMobileProductView() || !$form.length || !nativeAddToCartButton($form).length) {
            $existingSticky.hide();
            $('body').removeClass('superwoo-has-mobile-buy-now superwoo-mobile-product-sticky-active');
            if (productCartVisibilityObserver) {
                productCartVisibilityObserver.disconnect();
                productCartVisibilityObserver = null;
                observedProductCartForm = null;
            }
            return;
        }

        auditMobileProductButtons($form);
        $sticky = stickyBuyNowShell();
        $button = $sticky.find('[data-superwoo-sticky-add-to-cart]').first();
        $addToCart = nativeAddToCartButton($form);

        if ($addToCart.length) {
            disabled = $addToCart.is(':disabled') || $addToCart.hasClass('disabled') || $addToCart.attr('aria-disabled') === 'true';
        }

        syncStickyQuantity($form, $sticky);
        syncStickyButtonColors($button, $addToCart);
        forceInlineCartGrid($form);
        $button.text('ADD TO CART').prop('disabled', disabled).toggleClass('is-disabled', disabled);
        $sticky.css('display', '');
        $('body').addClass('superwoo-has-mobile-buy-now');
        observeOriginalProductCart($form);
    }

    function syncStickyButtonColors($button, $source) {
        var source = $source && $source.length ? $source.get(0) : null;
        var sticky = $button && $button.length ? $button.get(0) : null;
        var style;
        var background;
        var color;

        if (!source || !sticky || !window.getComputedStyle) {
            return;
        }

        style = window.getComputedStyle(source);
        background = style.backgroundColor;
        color = style.color;

        if (background && background !== 'transparent' && !/^rgba\(\s*0\s*,\s*0\s*,\s*0\s*,\s*0\s*\)$/i.test(background)) {
            sticky.style.setProperty('--superwoo-mobile-atc-bg', background);
        }

        if (color) {
            sticky.style.setProperty('--superwoo-mobile-atc-text', color);
        }
    }

    function scheduleStickyBuyNowSync() {
        window.clearTimeout(stickyBuyNowTimer);
        stickyBuyNowTimer = window.setTimeout(syncStickyBuyNow, 60);
    }

    function clickStickyBuyNow(event) {
        var $form = productCartForm();
        var $addToCart;
        var form;
        var submitValue;
        var submitField;

        event.preventDefault();
        event.stopPropagation();

        if (!$form.length) {
            return;
        }

        auditMobileProductButtons($form);
        $addToCart = nativeAddToCartButton($form);

        if (!$addToCart.length) {
            return;
        }

        form = $form.get(0);
        submitValue = $addToCart.val() || $addToCart.attr('value') || $form.find('[name="add-to-cart"]').first().val() || $form.find('[name="product_id"]').first().val();

        // Submit exactly one standard WooCommerce form request.  requestSubmit
        // and a programmatic button click both run theme/extension handlers;
        // those are the only mobile-specific path left that can perform a
        // second add. Native form submission bypasses those handlers while
        // retaining WooCommerce's form data (quantity, variation and options).
        if (!form || !submitValue || !window.HTMLFormElement || !window.HTMLFormElement.prototype.submit) {
            return;
        }

        submitField = document.createElement('input');
        submitField.type = 'hidden';
        submitField.name = 'add-to-cart';
        submitField.value = submitValue;
        form.appendChild(submitField);

        try {
            window.sessionStorage.setItem('superwoo-open-cart-after-native-product-submit', '1');
        } catch (storageError) {
            // Storage access is optional; the WooCommerce request still runs.
        }

        window.HTMLFormElement.prototype.submit.call(form);
    }

    function updateItem($item, quantity) {
        var nextQuantity = Math.max(1, parseInt(quantity, 10) || 1);
        var key = $item.attr('data-cart-item-key');
        $item.find('[data-superwoo-qty-input]').val(nextQuantity);
        $item.addClass('is-loading');

        wcPost('superwoo_update_cart_item', {
            cart_item_key: key,
            quantity: nextQuantity
        }, $item, { refreshWooFragments: true });
    }

    function removeItem($item) {
        var key = $item.attr('data-cart-item-key');
        if (!key) {
            return;
        }

        $item.addClass('is-loading');

        wcPost('superwoo_remove_cart_item', {
            cart_item_key: key
        }, $item, { refreshWooFragments: true });
    }

    $(document).on('click', '[data-superwoo-open-cart]', function (event) {
        event.preventDefault();
        openCart();
    });

    $(document).on('click', '[data-superwoo-open-mobile-search]', function (event) {
        event.preventDefault();
        openMobileSearch();
    });

    $(document).on('click', '[data-superwoo-close-mobile-search]', function (event) {
        event.preventDefault();
        closeMobileSearch();
    });

    $(document).on('click', 'a[href], [data-superwoo-cart-trigger]', function (event) {
        if (!isCartTriggerLink(this)) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        openCart();
    });

    document.addEventListener('click', function (event) {
        var trigger = closestCartTrigger(event.target);

        if (!trigger || !isCartTriggerLink(trigger)) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        openCart();
    }, true);

    $(document).on('click', '[data-superwoo-close-cart]', function (event) {
        event.preventDefault();
        closeCart();
    });

    $(document).on('keydown', function (event) {
        if (event.key === 'Escape' && shell().hasClass('is-open')) {
            closeCart();
        }
        if (event.key === 'Escape' && mobileSearchSheet().hasClass('is-open')) {
            closeMobileSearch();
        }
    });

    document.addEventListener('wheel', function (event) {
        var $shell = shell();
        if (!$shell.hasClass('is-open')) {
            return;
        }

        var drawerNode = drawer().get(0);
        var scroller = scrollContainer().get(0);
        if (!drawerNode || !scroller || !drawerNode.contains(event.target)) {
            return;
        }

        scroller.scrollTop += event.deltaY;
        event.preventDefault();
        event.stopPropagation();
    }, { passive: false, capture: true });

    var touchStartY = 0;
    document.addEventListener('touchstart', function (event) {
        if (!shell().hasClass('is-open') || !drawer().get(0) || !drawer().get(0).contains(event.target)) {
            return;
        }

        touchStartY = event.touches[0].clientY;
    }, { passive: true, capture: true });

    document.addEventListener('touchmove', function (event) {
        var drawerNode = drawer().get(0);
        var scroller = scrollContainer().get(0);
        if (!shell().hasClass('is-open') || !drawerNode || !scroller || !drawerNode.contains(event.target)) {
            return;
        }

        var nextY = event.touches[0].clientY;
        scroller.scrollTop += touchStartY - nextY;
        touchStartY = nextY;
        event.preventDefault();
        event.stopPropagation();
    }, { passive: false, capture: true });

    $(document).on('click', '[data-superwoo-qty-minus]', function (event) {
        event.preventDefault();
        event.stopPropagation();
        event.stopImmediatePropagation();
        var $item = $(this).closest('.superwoo-cart-item');
        var $input = $item.find('[data-superwoo-qty-input]');
        var current = parseInt($input.val(), 10) || 1;

        if (current <= 1) {
            $input.val(1);
            return;
        }

        updateItem($item, current - 1);
    });

    $(document).on('click', '[data-superwoo-qty-plus]', function (event) {
        event.preventDefault();
        event.stopPropagation();
        event.stopImmediatePropagation();
        var $item = $(this).closest('.superwoo-cart-item');
        var $input = $item.find('[data-superwoo-qty-input]');
        updateItem($item, (parseInt($input.val(), 10) || 0) + 1);
    });

    $(document).on('change', '[data-superwoo-qty-input]', function (event) {
        event.preventDefault();
        event.stopPropagation();
        var $item = $(this).closest('.superwoo-cart-item');
        updateItem($item, $(this).val());
    });

    $(document).on('click', '[data-superwoo-remove-item]', function (event) {
        event.preventDefault();
        event.stopPropagation();
        event.stopImmediatePropagation();
        var $item = $(this).closest('.superwoo-cart-item');
        removeItem($item);
    });

    $(document).on('click', '[data-superwoo-pay-now]', function (event) {
        event.preventDefault();
        event.stopPropagation();
        event.stopImmediatePropagation();
        startDirectPayment($(this));
    });

    $(document).on('click', '[data-superwoo-sticky-add-to-cart]', clickStickyBuyNow);
    $(document).on('click', '[data-superwoo-mobile-qty-minus]', function (event) {
        var current = parseInt(stickyBuyNowShell().find('[data-superwoo-mobile-qty-input]').val(), 10) || 1;

        event.preventDefault();
        setStickyQuantity(current - 1);
    });
    $(document).on('click', '[data-superwoo-mobile-qty-plus]', function (event) {
        var current = parseInt(stickyBuyNowShell().find('[data-superwoo-mobile-qty-input]').val(), 10) || 1;

        event.preventDefault();
        setStickyQuantity(current + 1);
    });
    $(document).on('change input', '[data-superwoo-mobile-qty-input]', function () {
        setStickyQuantity($(this).val());
    });
    $(document).on('change input', 'body.single-product form.cart input.qty[name="quantity"], body.single-product form.cart input[name="quantity"]', scheduleStickyBuyNowSync);

    $(document.body).on('found_variation reset_data woocommerce_variation_has_changed', scheduleStickyBuyNowSync);

    $(document).on('click', '[data-superwoo-add-cross-sell]', function () {
        post('superwoo_add_cross_sell', {
            product_id: $(this).data('superwoo-add-cross-sell')
        }, $(this).closest('.superwoo-cross-sell'), {
            openCart: true,
            refreshWooFragments: false
        });
    });

    $(document.body).on('added_to_cart', function () {
        window.setTimeout(function () {
            syncCartTriggerBadges();
            fetchCartCount();
        }, 100);

        // WooCommerce has already completed the add at this point. On product
        // pages the drawer should reflect that successful native cart action.
        if (SuperWooCart.autoOpen || $('body').hasClass('single-product')) {
            openCart();
        }
    });

    $(document.body).on('wc_fragments_loaded wc_fragments_refreshed removed_from_cart updated_cart_totals', function () {
        window.setTimeout(function () {
            syncCartTriggerBadges();
            fetchCartCount();
        }, 20);
    });

    $(window).on('hashchange.superwooCart', function () {
        if (window.location.hash === '#superwoo-cart') {
            openCart();
        }
    });

    $(function () {
        if (suppressDashboardBottomNav()) {
            return;
        }

        if (!mobileSearchSheet().hasClass('is-open')) {
            $('body').removeClass('superwoo-mobile-search-open');
        }

        syncCartTriggerBadges();
        startTriggerObserver();
        fetchCartCount();
        syncStickyBuyNow();
        // Some product builders finish rendering the form after DOM-ready.
        // A bounded retry keeps the observer lightweight while still finding it.
        window.setTimeout(syncStickyBuyNow, 350);
        window.setTimeout(syncStickyBuyNow, 1200);

        try {
            if (window.sessionStorage.getItem('superwoo-open-cart-after-native-product-submit') === '1') {
                window.sessionStorage.removeItem('superwoo-open-cart-after-native-product-submit');
                window.setTimeout(openCart, 220);
            }
        } catch (storageError) {
            // Storage access is optional.
        }

        if (window.location.hash === '#superwoo-cart') {
            window.setTimeout(openCart, 80);
        }
    });


    $(window).on('resize.superwooCart orientationchange.superwooCart', function () {
        scheduleStickyBuyNowSync();

        if (shell().hasClass('is-open')) {
            syncDrawerLayout();
        }
    });


    window.SuperWooOpenCart = openCart;
})(jQuery);
