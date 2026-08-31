(function () {
    'use strict';

    function initialize(form) {
        if (form.dataset.superwooAutoApplyReady === '1') {
            return;
        }

        form.dataset.superwooAutoApplyReady = '1';
        form.classList.add('is-auto-apply');

        var shell = form.closest('[data-superwoo-filter-shell]');
        var toggle = shell && shell.querySelector('[data-superwoo-filter-toggle]');
        var openStateKey = 'superwooFiltersOpen:' + window.location.pathname;
        if (shell && toggle) {
            shell.classList.add('is-mobile-ready');
            try {
                if ('1' === window.sessionStorage.getItem(openStateKey)) {
                    shell.classList.add('is-open');
                    toggle.setAttribute('aria-expanded', 'true');
                    window.sessionStorage.removeItem(openStateKey);
                }
            } catch (error) {}
            toggle.addEventListener('click', function () {
                var open = shell.classList.toggle('is-open');
                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            });
            shell.addEventListener('keydown', function (event) {
                if ('Escape' === event.key && shell.classList.contains('is-open')) {
                    shell.classList.remove('is-open');
                    toggle.setAttribute('aria-expanded', 'false');
                    toggle.focus();
                }
            });
        }

        var timer = null;
        var submitting = false;

        function apply(delay) {
            window.clearTimeout(timer);
            timer = window.setTimeout(function () {
                if (submitting || document.body.classList.contains('elementor-editor-active')) {
                    return;
                }

                submitting = true;
                form.classList.add('is-applying');
                form.setAttribute('aria-busy', 'true');
                if (shell && shell.classList.contains('is-open')) {
                    try { window.sessionStorage.setItem(openStateKey, '1'); } catch (error) {}
                }

                if (typeof form.requestSubmit === 'function') {
                    form.requestSubmit();
                } else {
                    form.submit();
                }
            }, delay || 0);
        }

        form.addEventListener('change', function (event) {
            if (!event.target.matches('input, select')) {
                return;
            }
            apply(0);
        });

        form.addEventListener('input', function (event) {
            if (event.target.matches('input[type="search"]')) {
                apply(550);
            } else if (event.target.matches('input[type="number"]')) {
                apply(700);
            }
        });

        form.addEventListener('keydown', function (event) {
            if ('Enter' === event.key && event.target.matches('input[type="search"], input[type="number"]')) {
                event.preventDefault();
                apply(0);
            }
        });
    }

    function initializeAll(root) {
        (root || document).querySelectorAll('[data-superwoo-auto-apply]').forEach(initialize);
    }

    if ('loading' === document.readyState) {
        document.addEventListener('DOMContentLoaded', function () { initializeAll(document); });
    } else {
        initializeAll(document);
    }

    window.addEventListener('elementor/frontend/init', function () {
        initializeAll(document);
        if (window.elementorFrontend && window.elementorFrontend.hooks) {
            window.elementorFrontend.hooks.addAction('frontend/element_ready/superwoo-shop-filters.default', function ($scope) {
                initializeAll($scope[0]);
            });
        }
    });
}());
