(function ($) {
    'use strict';

    function config() {
        return window.SuperWooVariationCards || {};
    }

    function decodeJson(value) {
        if (!value || value === 'false') {
            return [];
        }

        if (Array.isArray(value)) {
            return value;
        }

        try {
            return JSON.parse(value);
        } catch (error) {
            try {
                return JSON.parse(value.replace(/&quot;/g, '"'));
            } catch (nestedError) {
                return [];
            }
        }
    }

    function getVariations(form) {
        var $form = $(form);
        var data = $form.data('product_variations');

        if (data) {
            return decodeJson(data);
        }

        return decodeJson(form.getAttribute('data-product_variations'));
    }

    function stripTrailingZeros(value) {
        if (!config().trimZeros) {
            return value;
        }

        return value.replace(/([.,]0+)$/, '');
    }

    function formatPrice(amount) {
        var settings = config();
        var number = parseFloat(amount);

        if (isNaN(number)) {
            return '';
        }

        var decimals = typeof settings.priceDecimals !== 'undefined' ? parseInt(settings.priceDecimals, 10) : 2;
        var decimalSeparator = settings.decimalSeparator || '.';
        var thousandSeparator = settings.thousandSeparator || ',';
        var symbol = settings.currencySymbol || '';
        var position = settings.currencyPosition || 'left';
        var fixed = number.toFixed(decimals);
        var parts = fixed.split('.');
        var integer = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, thousandSeparator);
        var decimal = parts[1] ? decimalSeparator + parts[1] : '';
        var price = stripTrailingZeros(integer + decimal);

        if (position === 'right') {
            return price + symbol;
        }
        if (position === 'right_space') {
            return price + ' ' + symbol;
        }
        if (position === 'left_space') {
            return symbol + ' ' + price;
        }

        return symbol + price;
    }

    function normalizeAttrValue(value) {
        return String(value || '').toLowerCase();
    }

    function variationMatchesOption(variation, attributeName, value, selectedAttributes) {
        var attrs = variation.attributes || {};
        var variationValue = attrs[attributeName] || '';

        if (variationValue && normalizeAttrValue(variationValue) !== normalizeAttrValue(value)) {
            return false;
        }

        for (var name in selectedAttributes) {
            if (!Object.prototype.hasOwnProperty.call(selectedAttributes, name) || name === attributeName) {
                continue;
            }

            if (!selectedAttributes[name]) {
                continue;
            }

            var otherValue = attrs[name] || '';
            if (otherValue && normalizeAttrValue(otherValue) !== normalizeAttrValue(selectedAttributes[name])) {
                return false;
            }
        }

        return true;
    }

    function selectedAttributes(form) {
        var attrs = {};
        $(form).find('select[name^="attribute_"]').each(function () {
            attrs[this.name] = this.value || '';
        });
        return attrs;
    }

    function findBestVariation(variations, attributeName, value, attrs) {
        var matches = variations.filter(function (variation) {
            return variationMatchesOption(variation, attributeName, value, attrs);
        });

        if (!matches.length) {
            return null;
        }

        matches.sort(function (a, b) {
            return parseFloat(a.display_price || 0) - parseFloat(b.display_price || 0);
        });

        return matches[0];
    }

    function priceMarkup(variation) {
        if (!variation) {
            return '<span class="superwoo-variation-card__unavailable">' + (config().i18n && config().i18n.unavailable ? config().i18n.unavailable : 'Unavailable') + '</span>';
        }

        var price = parseFloat(variation.display_price);
        var regular = parseFloat(variation.display_regular_price);
        var current = formatPrice(price);
        var regularText = regular && regular > price ? formatPrice(regular) : '';

        if (!current && variation.price_html) {
            return variation.price_html;
        }

        return '<span class="superwoo-variation-card__price-current">' + current + '</span>' + (regularText ? ' <span class="superwoo-variation-card__price-regular">' + regularText + '</span>' : '');
    }

    function optionLabel(option) {
        return option.textContent || option.innerText || option.value;
    }

    function attributeLabel(select) {
        var row = select.closest('tr');
        var label = row && row.querySelector('th label, td.label label');
        return label ? (label.textContent || '').trim() : '';
    }

    function isPackAttribute(select) {
        return /pack|bundle/i.test((select.name || '') + ' ' + attributeLabel(select));
    }

    function variationDescription(variation) {
        if (!variation || !variation.variation_description) {
            return '';
        }

        var content = document.createElement('div');
        content.innerHTML = variation.variation_description;
        return (content.textContent || content.innerText || '').replace(/\s+/g, ' ').trim();
    }

    function buildCards(form, select, variations) {
        if (select.dataset.superwooCardsReady === '1') {
            return;
        }

        var options = Array.prototype.slice.call(select.options).filter(function (option) {
            return option.value !== '';
        });

        if (!options.length) {
            return;
        }

        var group = document.createElement('div');
        var heading = null;
        group.className = 'superwoo-variation-cards';
        group.setAttribute('data-superwoo-attribute', select.name);
        group.setAttribute('role', 'radiogroup');

        if (isPackAttribute(select)) {
            heading = document.createElement('h3');
            var label = attributeLabel(select);
            heading.className = 'superwoo-variation-cards__heading';
            heading.id = (select.id || select.name.replace(/[^a-z0-9_-]/gi, '-')) + '-pack-heading';
            heading.textContent = config().i18n && config().i18n.choosePack ? config().i18n.choosePack : 'Choose your pack';
            group.setAttribute('aria-labelledby', heading.id);
            if (label) {
                var row = select.closest('tr');
                var sourceLabel = row && row.querySelector('th label, td.label label');
                if (sourceLabel) {
                    sourceLabel.classList.add('screen-reader-text');
                }
                if (row) {
                    row.classList.add('superwoo-variation-pack-row');
                }
            }
        }

        options.forEach(function (option) {
            var card = document.createElement('button');
            var title = document.createElement('span');
            var description = document.createElement('span');
            var price = document.createElement('span');
            card.type = 'button';
            card.className = 'superwoo-variation-card';
            card.dataset.value = option.value;
            card.dataset.attribute = select.name;
            card.setAttribute('role', 'radio');
            card.setAttribute('aria-checked', option.selected ? 'true' : 'false');
            title.className = 'superwoo-variation-card__title';
            title.textContent = optionLabel(option);
            description.className = 'superwoo-variation-card__description';
            price.className = 'superwoo-variation-card__price';
            card.appendChild(title);
            card.appendChild(description);
            card.appendChild(price);

            card.addEventListener('click', function (event) {
                event.preventDefault();
                select.value = option.value;
                select.dispatchEvent(new Event('change', { bubbles: true }));
                $(select).trigger('change');
                updateCards(form, variations);
            });

            group.appendChild(card);
        });

        select.insertAdjacentElement('afterend', group);
        if (heading) {
            group.insertAdjacentElement('beforebegin', heading);
        }
        select.dataset.superwooCardsReady = '1';
    }

    function updateCards(form, variations) {
        var attrs = selectedAttributes(form);

        $(form).find('.superwoo-variation-cards').each(function () {
            var group = this;
            var attributeName = group.getAttribute('data-superwoo-attribute');
            var select = form.querySelector('select[name="' + attributeName + '"]');
            var currentValue = select ? select.value : '';

            $(group).find('.superwoo-variation-card').each(function () {
                var value = this.dataset.value || '';
                var variation = findBestVariation(variations, attributeName, value, attrs);
                var description = this.querySelector('.superwoo-variation-card__description');
                var isSelected = currentValue === value;
                var isAvailable = !!variation && variation.is_in_stock !== false && variation.is_purchasable !== false;

                this.classList.toggle('is-selected', isSelected);
                this.classList.toggle('is-disabled', !isAvailable);
                this.setAttribute('aria-checked', isSelected ? 'true' : 'false');
                this.disabled = !isAvailable;
                if (description) {
                    description.textContent = variationDescription(variation);
                    description.hidden = !description.textContent;
                }
                this.querySelector('.superwoo-variation-card__price').innerHTML = priceMarkup(variation);
            });
        });
    }

    function enhanceForm(form) {
        var variations = getVariations(form);
        var selects = $(form).find('select[name^="attribute_"]').toArray();

        if (!selects.length) {
            return;
        }

        selects.forEach(function (select) {
            buildCards(form, select, variations);
        });

        form.classList.add('superwoo-variation-cards-active');
        updateCards(form, variations);

        $(form).on('woocommerce_variation_has_changed found_variation reset_data change', function () {
            updateCards(form, variations);
        });
    }

    function init() {
        $('form.variations_form').each(function () {
            enhanceForm(this);
        });
    }

    $(init);
    $(document.body).on('wc_variation_form', function (_event, form) {
        if (form) {
            enhanceForm(form);
        } else {
            init();
        }
    });
})(jQuery);
