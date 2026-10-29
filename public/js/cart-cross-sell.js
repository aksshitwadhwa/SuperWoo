/* Cart recommendation navigation is independent of the main cart bundle.
 * A previously cached bundle may already own its one-time initialization guard.
 */
(function () {
    'use strict';

    if (window.__superwooCrossSellNavigationInitialized) {
        return;
    }
    window.__superwooCrossSellNavigationInitialized = true;

    function sync(section) {
        var row = section.querySelector('.superwoo-cross-sells__row');
        var controls = section.querySelector('.superwoo-cross-sells__controls');
        var previous;
        var next;
        var max;

        if (!row || !controls || !row.clientWidth) {
            return;
        }

        previous = controls.querySelector('[data-superwoo-cross-sell-nav="previous"]');
        next = controls.querySelector('[data-superwoo-cross-sell-nav="next"]');
        if (!previous || !next) {
            return;
        }

        max = Math.max(0, row.scrollWidth - row.clientWidth);
        controls.hidden = max < 2;
        // Keep these as enabled buttons. A stale cart bundle can otherwise set
        // disabled before the drawer reaches its final width, swallowing clicks.
        if (previous.disabled) {
            previous.disabled = false;
        }
        if (next.disabled) {
            next.disabled = false;
        }
        previous.setAttribute('aria-disabled', row.scrollLeft <= 1 ? 'true' : 'false');
        next.setAttribute('aria-disabled', row.scrollLeft >= max - 1 ? 'true' : 'false');
    }

    function syncAll() {
        document.querySelectorAll('.superwoo-cross-sells').forEach(sync);
    }

    function move(row, direction) {
        var cards = row.querySelectorAll('.superwoo-cross-sell');
        var step;
        var max = Math.max(0, row.scrollWidth - row.clientWidth);
        var target;

        if (cards.length < 2 || max < 2) {
            return;
        }
        step = cards[1].offsetLeft - cards[0].offsetLeft;
        if (step <= 0) {
            step = cards[0].getBoundingClientRect().width;
        }
        if (step <= 0) {
            return;
        }

        // Advance one card from the nearest card boundary. The final step is
        // clamped so the last card remains fully visible.
        target = (Math.round(row.scrollLeft / step) + direction) * step;
        row.scrollLeft = Math.max(0, Math.min(max, target));
    }

    // Window capture runs before document/theme listeners, including those in
    // older cached versions of the cart bundle. Handle only our own arrows.
    window.addEventListener('click', function (event) {
        var button = event.target.closest && event.target.closest('[data-superwoo-cross-sell-nav]');
        var section = button && button.closest('.superwoo-cross-sells');
        var row = section && section.querySelector('.superwoo-cross-sells__row');

        if (!row) {
            return;
        }
        event.preventDefault();
        event.stopPropagation();
        move(row, button.getAttribute('data-superwoo-cross-sell-nav') === 'previous' ? -1 : 1);
        sync(section);
    }, true);

    document.addEventListener('scroll', function (event) {
        var row = event.target;
        var section = row && row.closest && row.closest('.superwoo-cross-sells');
        if (section && row.matches('.superwoo-cross-sells__row')) {
            sync(section);
        }
    }, true);

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', syncAll);
    } else {
        syncAll();
    }
    window.addEventListener('resize', syncAll);

    // AJAX can replace the entire recommendation section. Watch the drawer,
    // not a particular row, so replacement requires no re-binding.
    function observe() {
        var shell = document.querySelector('[data-superwoo-cart-shell]');
        var observer;
        if (!shell || !window.MutationObserver) {
            return;
        }
        observer = new MutationObserver(function (records) {
            var needsSync = records.some(function (record) {
                return record.type === 'childList' || record.attributeName === 'disabled';
            });
            if (needsSync) {
                syncAll();
            }
        });
        observer.observe(shell, { childList: true, subtree: true, attributes: true, attributeFilter: ['disabled'] });
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', observe);
    } else {
        observe();
    }
}());
