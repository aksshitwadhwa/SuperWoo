(function () {
    'use strict';

    function setHeight(item, open) {
        var answer = item.querySelector('.superwoo-faq-answer');
        var button = item.querySelector('.superwoo-faq-question');

        item.classList.toggle('is-open', open);
        button.setAttribute('aria-expanded', open ? 'true' : 'false');
        answer.style.maxHeight = open ? answer.scrollHeight + 'px' : '0';
    }

    document.addEventListener('click', function (event) {
        var button = event.target.closest('.superwoo-faq-question');
        if (!button) {
            return;
        }

        var item = button.closest('.superwoo-faq-item');
        var accordion = button.closest('.superwoo-faqs-accordion');
        var isOpen = item.classList.contains('is-open');

        accordion.querySelectorAll('.superwoo-faq-item.is-open').forEach(function (openItem) {
            if (openItem !== item) {
                setHeight(openItem, false);
            }
        });

        setHeight(item, !isOpen);
    });

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.superwoo-faq-item.is-open').forEach(function (item) {
            setHeight(item, true);
        });
    });
})();
