(function (root, factory) {
    'use strict';

    var api = factory();
    if (typeof module === 'object' && module.exports) {
        module.exports = api;
    }
    if (root && root.document) {
        api.initialize(root);
    }
}(typeof window !== 'undefined' ? window : null, function () {
    'use strict';

    var PIXEL_ID_PATTERN = /^[0-9]{1,32}$/;
    var CURRENCY_PATTERN = /^[A-Z]{3}$/;
    var DEDUPE_WINDOW_MS = 1500;

    function safeConfig(win) {
        try {
            var config = win && win.SuperWooMetaTrackingConfig;
            return config && typeof config === 'object' ? config : {};
        } catch (_error) {
            return {};
        }
    }

    function flagEnabled(value) {
        return value === true || value === 1 || value === '1' || value === 'true';
    }

    function pixelRuntimePresent(win) {
        try {
            return !!win && typeof win.fbq === 'function';
        } catch (_error) {
            return false;
        }
    }

    function validPixelId(value) {
        return typeof value === 'string' && PIXEL_ID_PATTERN.test(value);
    }

    function normalizePayload(candidate, doc, priceFormat) {
        if (!candidate || typeof candidate !== 'object') {
            return null;
        }
        var currency = typeof candidate.currency === 'string' ? candidate.currency.toUpperCase() : '';
        var value = Number(candidate.value);
        var numItems = Number(candidate.num_items);
        if (!CURRENCY_PATTERN.test(currency) || !Number.isFinite(value) || value < 0 || !Number.isFinite(numItems) || numItems <= 0) {
            return null;
        }
        if (!Array.isArray(candidate.content_ids) || candidate.content_ids.length < 1) {
            return null;
        }
        var contentIds = candidate.content_ids.filter(function (id) {
            return (typeof id === 'string' || typeof id === 'number') && /^[0-9]+$/.test(String(id));
        }).map(String);
        if (!contentIds.length) {
            return null;
        }
        var payload = {
            currency: currency,
            value: value,
            content_ids: contentIds,
            content_type: 'product',
            num_items: numItems
        };
        // Classic checkout refreshes its visible total after shipping/address
        // updates. Read that displayed WooCommerce value at selection time.
        // If a total is present but cannot be parsed using Woo's separators,
        // fail closed instead of sending the older page-load snapshot.
        try {
            var totalNode = doc && typeof doc.querySelector === 'function'
                ? doc.querySelector('.order-total .woocommerce-Price-amount, .order-total .amount')
                : null;
            if (totalNode) {
                var raw = String(totalNode.textContent || '').trim();
                var decimalSeparator = priceFormat && typeof priceFormat.decimalSeparator === 'string' ? priceFormat.decimalSeparator : '.';
                var thousandSeparator = priceFormat && typeof priceFormat.thousandSeparator === 'string' ? priceFormat.thousandSeparator : ',';
                if (!decimalSeparator || raw.indexOf(decimalSeparator) !== raw.lastIndexOf(decimalSeparator)) {
                    return null;
                }
                if (raw.indexOf('-') !== -1) {
                    return null;
                }
                if (thousandSeparator) {
                    raw = raw.split(thousandSeparator).join('');
                }
                if (decimalSeparator !== '.') {
                    raw = raw.split(decimalSeparator).join('.');
                }
                raw = raw.replace(/[^0-9.]/g, '');
                if (!/^\d+(?:\.\d+)?$/.test(raw)) {
                    return null;
                }
                var displayedValue = Number(raw);
                if (!Number.isFinite(displayedValue)) {
                    return null;
                }
                payload.value = displayedValue;
            }
        } catch (_error) {
            return null;
        }
        return payload;
    }

    function detectCheckoutType(win) {
        try {
            var doc = win.document;
            if (!doc || !doc.body) {
                return 'unknown';
            }
            if (doc.querySelector('.wp-block-woocommerce-checkout, .wc-block-checkout')) {
                return 'blocks';
            }
            if (doc.body.classList && doc.body.classList.contains('woocommerce-checkout')) {
                return 'classic';
            }
        } catch (_error) {
            return 'unknown';
        }
        return 'unknown';
    }

    function ClassicCheckoutObserver(doc) {
        this.document = doc;
        this.listener = null;
    }

    ClassicCheckoutObserver.prototype.start = function (callback) {
        var doc = this.document;
        if (!doc || !doc.body || !doc.body.classList || !doc.body.classList.contains('woocommerce-checkout') || typeof callback !== 'function') {
            return false;
        }
        if (this.listener) {
            return true;
        }
        this.listener = function (event) {
            try {
                var target = event && event.target;
                // Require a genuine user event. Scripted events and initial page
                // state never qualify as a customer payment-method selection.
                if (!event || event.isTrusted !== true || !target || !target.matches || !target.matches('input[name="payment_method"]') || target.checked !== true || typeof target.value !== 'string' || !target.value) {
                    return;
                }
                callback({ checkoutType: 'classic', paymentMethod: target.value });
            } catch (_error) {
                // Tracking errors must not affect WooCommerce checkout.
            }
        };
        try {
            doc.addEventListener('change', this.listener, false);
            return true;
        } catch (_error) {
            this.listener = null;
            return false;
        }
    };

    ClassicCheckoutObserver.prototype.stop = function () {
        try {
            if (this.listener && this.document) {
                this.document.removeEventListener('change', this.listener, false);
            }
        } catch (_error) {
            // Best-effort cleanup only.
        }
        this.listener = null;
    };

    function DiagnosticState(win, enabled, initial) {
        this.win = win;
        this.enabled = flagEnabled(enabled);
        this.value = initial;
        this.publish();
    }

    DiagnosticState.prototype.set = function (key, value) {
        this.value[key] = value;
        this.publish();
    };

    DiagnosticState.prototype.publish = function () {
        if (!this.enabled) {
            return;
        }
        try {
            Object.defineProperty(this.win, 'SuperWooMetaTrackingDiagnostics', {
                configurable: true,
                value: Object.freeze(Object.assign({}, this.value))
            });
            if (this.win.console && typeof this.win.console.info === 'function') {
                this.win.console.info('SuperWoo Meta Tracking', {
                    status: this.value.status,
                    consentState: this.value.consentState,
                    checkoutAdapter: this.value.checkoutAdapter,
                    eventDispatchAllowed: this.value.eventDispatchAllowed,
                    pixelRuntimePresent: this.value.pixelRuntimePresent,
                    razorpayStatus: this.value.razorpayStatus
                });
            }
        } catch (_error) {
            // Diagnostics are optional and must never interrupt checkout.
        }
    };

    function initialize(win) {
        var config = safeConfig(win);
        if (!win || !win.document || !flagEnabled(config.enabled)) {
            return null;
        }

        var consentState = ['granted', 'denied', 'unknown'].indexOf(config.consentState) !== -1 ? config.consentState : 'unknown';
        var checkoutType = detectCheckoutType(win);
        var pixelId = validPixelId(config.primaryPixelId) ? config.primaryPixelId : '';
        var diag = new DiagnosticState(win, config.diagnostics, {
            enabled: true,
            status: 'blocked_tracking_disabled',
            pixelRuntimePresent: pixelRuntimePresent(win),
            consentState: consentState,
            checkoutAdapter: 'unsupported',
            eventDispatchAllowed: false,
            killSwitch: flagEnabled(config.killSwitch),
            razorpayStatus: 'unsupported'
        });

        if (flagEnabled(config.killSwitch)) {
            diag.set('status', 'blocked_kill_switch');
            return { diagnostics: diag.value, observer: null };
        }
        if (consentState !== 'granted') {
            diag.set('status', 'blocked_consent');
            return { diagnostics: diag.value, observer: null };
        }
        if (!pixelId) {
            diag.set('status', 'blocked_pixel_missing');
            return { diagnostics: diag.value, observer: null };
        }
        if (checkoutType !== 'classic') {
            diag.set('status', 'blocked_unsupported_checkout');
            return { diagnostics: diag.value, observer: null };
        }

        diag.set('checkoutAdapter', 'classic');
        diag.set('eventDispatchAllowed', true);
        var observer = new ClassicCheckoutObserver(win.document);
        var lastEventKey = '';
        var lastEventTime = 0;

        var started = observer.start(function (candidate) {
            try {
                diag.set('status', 'candidate_observed');
                // Re-check all gates at dispatch time. This is deliberately
                // independent of WooCommerce's payment execution path.
                if (!flagEnabled(config.enabled)) {
                    diag.set('status', 'blocked_tracking_disabled');
                    return;
                }
                if (flagEnabled(config.killSwitch)) {
                    diag.set('status', 'blocked_kill_switch');
                    return;
                }
                if (consentState !== 'granted') {
                    diag.set('status', 'blocked_consent');
                    return;
                }
                if (!validPixelId(config.primaryPixelId)) {
                    diag.set('status', 'blocked_pixel_missing');
                    return;
                }
                if (candidate.checkoutType !== 'classic') {
                    diag.set('status', 'blocked_unsupported_checkout');
                    return;
                }
                if (!pixelRuntimePresent(win)) {
                    diag.set('pixelRuntimePresent', false);
                    diag.set('status', 'blocked_fbq_missing');
                    return;
                }
                diag.set('pixelRuntimePresent', true);

                var payload = normalizePayload(config.commercePayload, win.document, config.priceFormat);
                if (!payload) {
                    diag.set('status', 'blocked_payload_invalid');
                    return;
                }
                var eventKey = candidate.paymentMethod + '|' + JSON.stringify(payload);
                var now = Date.now();
                if (eventKey === lastEventKey && now - lastEventTime < DEDUPE_WINDOW_MS) {
                    diag.set('status', 'duplicate_suppressed');
                    return;
                }
                lastEventKey = eventKey;
                lastEventTime = now;

                diag.set('status', 'dispatch_attempted');
                // Target only the explicitly configured Pixel. Never broadcast.
                win.fbq('trackSingle', config.primaryPixelId, 'AddPaymentInfo', payload);
                diag.set('status', 'dispatch_succeeded');
            } catch (_error) {
                diag.set('status', 'dispatch_failed');
            }
        });

        if (!started) {
            diag.set('checkoutAdapter', 'unsupported');
            diag.set('eventDispatchAllowed', false);
            diag.set('status', 'blocked_unsupported_checkout');
            observer = null;
        }

        return { diagnostics: diag.value, observer: observer };
    }

    return {
        ClassicCheckoutObserver: ClassicCheckoutObserver,
        detectCheckoutType: detectCheckoutType,
        initialize: initialize,
        normalizePayload: normalizePayload,
        pixelRuntimePresent: pixelRuntimePresent,
        validPixelId: validPixelId
    };
}));
