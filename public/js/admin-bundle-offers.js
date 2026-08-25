(function ($) {
    'use strict';

    function config() {
        return window.SuperWooOffers || {};
    }

    function initProductSearch() {
        $('.superwoo-product-image-search').each(function () {
            var $select = $(this);

            if ($select.hasClass('select2-hidden-accessible')) {
                $select.selectWoo('destroy');
            }

            $select.selectWoo({
                ajax: {
                    url: config().ajaxUrl,
                    dataType: 'json',
                    delay: 250,
                    data: function (params) {
                        return {
                            action: 'superwoo_json_search_products_and_variations',
                            nonce: config().nonce || '',
                            term: params.term || ''
                        };
                    },
                    processResults: function (data) {
                        return {
                            results: data && data.results ? data.results : []
                        };
                    },
                    cache: true
                },
                allowClear: $select.data('allow_clear') !== false,
                escapeMarkup: function (markup) {
                    return markup;
                },
                minimumInputLength: 1,
                multiple: $select.prop('multiple'),
                placeholder: $select.data('placeholder') || '',
                templateResult: formatProductOption,
                templateSelection: formatProductSelection,
                width: '100%'
            });
        });
    }

    function productImage(data) {
        if (data.image) {
            return data.image;
        }

        if (data.element) {
            return $(data.element).data('image') || '';
        }

        return '';
    }

    function productPrice(data) {
        if (data.price) {
            return data.price;
        }

        if (data.element) {
            return $(data.element).data('price') || '';
        }

        return '';
    }

    function escapeHtml(value) {
        return String(value || '').replace(/[&<>"']/g, function (character) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            }[character];
        });
    }

    function formatProductOption(data) {
        if (data.loading) {
            return data.text;
        }

        var image = productImage(data);
        var price = productPrice(data);
        var imageHtml = image ? '<img src="' + escapeHtml(image) + '" alt="">' : '<span class="superwoo-product-option__placeholder"></span>';
        var priceHtml = price ? '<small>' + escapeHtml(price) + '</small>' : '';

        return '<span class="superwoo-product-option">' + imageHtml + '<span class="superwoo-product-option__meta"><span>' + escapeHtml(data.text) + '</span>' + priceHtml + '</span></span>';
    }

    function formatProductSelection(data) {
        return escapeHtml(data.text || '');
    }

    function setStatus($status, message, isError) {
        $status
            .text(message || '')
            .toggleClass('is-error', !!isError)
            .toggleClass('is-success', !!message && !isError);
    }

    function updateOfferType($card) {
        var type = $card.find('.superwoo-offer-type-select').val();
        $card.attr('data-offer-type', type);
    }

    function updateAppliesTo($card) {
        var appliesTo = $card.find('.superwoo-applies-to-select').val();
        $card.attr('data-applies-to', appliesTo);
    }

    function bindOfferCard($card) {
        $card.find('.superwoo-offer-type-select').off('change.superwoo').on('change.superwoo', function () {
            updateOfferType($card);
        });

        $card.find('.superwoo-applies-to-select').off('change.superwoo').on('change.superwoo', function () {
            updateAppliesTo($card);
        });

        updateOfferType($card);
        updateAppliesTo($card);
    }

    function resetOfferForm($form) {
        $form.get(0).reset();
        $form.find('input[name="pbi_bundle_rule[id]"]').val('');
        $form.find('.superwoo-product-image-search').val(null).trigger('change');
        bindOfferCard($form.find('.superwoo-offer-card').first());
    }

    function responseMessage(response, fallback) {
        return response && response.data && response.data.message ? response.data.message : fallback;
    }

    function upsertOfferRow(rowHtml, offerId) {
        if (!rowHtml || !offerId) {
            return;
        }

        var $rows = $('[data-superwoo-offer-rows]');
        var $existing = $('[data-superwoo-offer-row][data-offer-id="' + offerId + '"]');
        var $row = $(rowHtml);

        if ($existing.length) {
            $existing.replaceWith($row);
        } else {
            $rows.append($row);
        }

        $('[data-superwoo-empty-state]').attr('hidden', true);
    }

    function applyOfferRows(rowsHtml, offersCount) {
        if (typeof rowsHtml !== 'string') {
            return;
        }

        $('[data-superwoo-offer-rows]').html(rowsHtml);

        if (offersCount > 0 || rowsHtml.length) {
            $('[data-superwoo-empty-state]').attr('hidden', true);
        } else {
            $('[data-superwoo-empty-state]').removeAttr('hidden');
        }
    }

    function refreshOfferList() {
        $.post(config().ajaxUrl, {
            action: 'superwoo_get_bundle_offers_list',
            nonce: config().nonce || ''
        }).done(function (response) {
            if (response && response.success && response.data) {
                applyOfferRows(response.data.rowsHtml || '', response.data.offersCount || 0);
            }
        });
    }

    function showEditorHtml(formHtml) {
        var $editor = $('[data-superwoo-inline-editor]');

        $editor.html(formHtml).removeAttr('hidden');
        bindOfferCard($editor.find('.superwoo-offer-card').first());
        initProductSearch();
        $editor.find('input[name="pbi_bundle_rule[title]"]').trigger('focus');
    }

    function loadOfferEditor(offerId) {
        var $editor = $('[data-superwoo-inline-editor]');

        $editor
            .removeAttr('hidden')
            .html('<p class="superwoo-editor-loading">' + (config().i18n && config().i18n.loading ? config().i18n.loading : 'Loading...') + '</p>');

        $.post(config().ajaxUrl, {
            action: 'superwoo_get_bundle_offer_form',
            nonce: config().nonce || '',
            offer_id: offerId || ''
        })
            .done(function (response) {
                if (!response || !response.success || !response.data || !response.data.formHtml) {
                    $editor.html('<div class="superwoo-ajax-status is-error">' + responseMessage(response, 'Could not load the offer editor.') + '</div>');
                    return;
                }

                showEditorHtml(response.data.formHtml);
            })
            .fail(function (xhr) {
                $editor.html('<div class="superwoo-ajax-status is-error">' + responseMessage(xhr.responseJSON || null, 'Could not load the offer editor.') + '</div>');
            });
    }

    function bindOfferForm() {
        var $forms = $('[data-superwoo-offer-form]');

        $forms.each(function () {
            bindOfferCard($(this).find('.superwoo-offer-card').first());
        });

        initProductSearch();

        $(document).off('submit.superwooOffers', '[data-superwoo-offer-form]').on('submit.superwooOffers', '[data-superwoo-offer-form]', function (event) {
            event.preventDefault();
            saveOfferForm($(this));
        });

        $(document).off('click.superwooOffersSave', '[data-superwoo-save-offer]').on('click.superwooOffersSave', '[data-superwoo-save-offer]', function (event) {
            event.preventDefault();
            saveOfferForm($(this).closest('[data-superwoo-offer-form]'));
        });
    }

    function saveOfferForm($form) {
        if (!$form.length || $form.data('superwoo-saving')) {
            return;
        }

        var $button = $form.find('[data-superwoo-save-offer]');
        var $status = $form.find('[data-superwoo-offer-status]');
        var isInline = $form.is('[data-superwoo-inline-offer-form]');
        var wasNew = !$form.find('input[name="pbi_bundle_rule[id]"]').val();
        var data = $form.serialize();

        data += '&action=superwoo_save_bundle_offer&nonce=' + encodeURIComponent(config().nonce || '');

        $form.data('superwoo-saving', true);
        $button.prop('disabled', true).text(config().i18n && config().i18n.saving ? config().i18n.saving : 'Saving...');
        setStatus($status, '', false);

        $.ajax({
            type: 'POST',
            url: config().ajaxUrl,
            data: data
        })
            .done(function (response) {
                if (!response || !response.success) {
                    setStatus($status, responseMessage(response, config().i18n && config().i18n.error ? config().i18n.error : 'Could not save the offer.'), true);
                    return;
                }

                if (response.data && response.data.offerId) {
                    $form.find('input[name="pbi_bundle_rule[id]"]').val(response.data.offerId);
                }

                if (response.data && response.data.rowHtml) {
                    upsertOfferRow(response.data.rowHtml, response.data.offerId);
                }

                if (response.data && typeof response.data.rowsHtml === 'string') {
                    applyOfferRows(response.data.rowsHtml, response.data.offersCount || 1);
                } else {
                    refreshOfferList();
                }

                if (!isInline && response.data && response.data.editUrl && window.history && window.location.href !== response.data.editUrl) {
                    window.history.replaceState({}, '', response.data.editUrl);
                }

                setStatus($status, responseMessage(response, config().i18n && config().i18n.saved ? config().i18n.saved : 'Offer saved.'), false);

                if (isInline && wasNew) {
                    resetOfferForm($form);
                }
            })
            .fail(function (xhr) {
                setStatus($status, responseMessage(xhr.responseJSON || null, config().i18n && config().i18n.error ? config().i18n.error : 'Could not save the offer.'), true);
            })
            .always(function () {
                $form.data('superwoo-saving', false);
                $button.prop('disabled', false).text(config().i18n && config().i18n.save ? config().i18n.save : 'Save Offer');
            });
    }

    function bindOfferList() {
        var $list = $('[data-superwoo-offers-list]');

        if (!$list.length) {
            return;
        }

        $(document).on('click', '[data-superwoo-show-new-offer]', function (event) {
            event.preventDefault();

            loadOfferEditor('');
        });

        $(document).on('click', '[data-superwoo-cancel-new-offer]', function (event) {
            event.preventDefault();

            $('[data-superwoo-inline-editor]').attr('hidden', true);
        });

        $(document).on('click', '[data-superwoo-delete-current-offer]', function (event) {
            event.preventDefault();

            if (!window.confirm(config().i18n && config().i18n.confirm ? config().i18n.confirm : 'Delete this offer?')) {
                return;
            }

            var $form = $(this).closest('[data-superwoo-offer-form]');
            var offerId = $form.find('input[name="pbi_bundle_rule[id]"]').val();
            var $status = $form.find('[data-superwoo-offer-status]');

            if (!offerId) {
                return;
            }

            $.post(config().ajaxUrl, {
                action: 'superwoo_delete_bundle_offer',
                nonce: config().nonce || '',
                offer_id: offerId
            })
                .done(function (response) {
                    if (!response || !response.success) {
                        setStatus($status, responseMessage(response, 'Could not delete the offer.'), true);
                        return;
                    }

                    if (response.data && typeof response.data.rowsHtml === 'string') {
                        applyOfferRows(response.data.rowsHtml, response.data.offersCount || 0);
                    } else {
                        refreshOfferList();
                    }

                    $('[data-superwoo-inline-editor]').attr('hidden', true);
                    setStatus($('[data-superwoo-offers-status]'), responseMessage(response, config().i18n && config().i18n.deleted ? config().i18n.deleted : 'Offer deleted.'), false);
                })
                .fail(function (xhr) {
                    setStatus($status, responseMessage(xhr.responseJSON || null, 'Could not delete the offer.'), true);
                });
        });

        $list.on('click', '[data-superwoo-edit-offer]', function (event) {
            event.preventDefault();

            var offerId = $(this).closest('[data-superwoo-offer-row]').data('offer-id');
            loadOfferEditor(offerId);
        });

        $list.on('click', '[data-superwoo-delete-offer]', function (event) {
            event.preventDefault();

            if (!window.confirm(config().i18n && config().i18n.confirm ? config().i18n.confirm : 'Delete this offer?')) {
                return;
            }

            var $row = $(this).closest('[data-superwoo-offer-row]');
            var $status = $('[data-superwoo-offers-status]');

            $.post(config().ajaxUrl, {
                action: 'superwoo_delete_bundle_offer',
                nonce: config().nonce || '',
                offer_id: $row.data('offer-id')
            })
                .done(function (response) {
                    if (!response || !response.success) {
                        setStatus($status, responseMessage(response, 'Could not delete the offer.'), true);
                        return;
                    }

                    if (response.data && typeof response.data.rowsHtml === 'string') {
                        applyOfferRows(response.data.rowsHtml, response.data.offersCount || 0);
                    } else {
                        $row.fadeOut(140, function () {
                            $(this).remove();

                            if (!$('[data-superwoo-offer-row]').length) {
                                $('[data-superwoo-empty-state]').removeAttr('hidden');
                            }
                        });
                    }
                    setStatus($status, responseMessage(response, config().i18n && config().i18n.deleted ? config().i18n.deleted : 'Offer deleted.'), false);
                })
                .fail(function (xhr) {
                    setStatus($status, responseMessage(xhr.responseJSON || null, 'Could not delete the offer.'), true);
                });
        });

        $list.on('change', '[data-superwoo-toggle-offer]', function () {
            var $toggle = $(this);
            var $row = $toggle.closest('[data-superwoo-offer-row]');
            var $label = $toggle.siblings('span');
            var $status = $('[data-superwoo-offers-status]');
            var enabled = $toggle.is(':checked') ? 1 : 0;

            $.post(config().ajaxUrl, {
                action: 'superwoo_toggle_bundle_offer',
                nonce: config().nonce || '',
                offer_id: $row.data('offer-id'),
                enabled: enabled
            })
                .done(function (response) {
                    if (!response || !response.success) {
                        $toggle.prop('checked', !enabled);
                        setStatus($status, responseMessage(response, 'Could not update the offer.'), true);
                        return;
                    }

                    $label.text(enabled ? 'Enabled' : 'Disabled');
                    if (response.data && typeof response.data.rowsHtml === 'string') {
                        applyOfferRows(response.data.rowsHtml, response.data.offersCount || 0);
                    }
                    setStatus($status, responseMessage(response, 'Offer updated.'), false);
                })
                .fail(function (xhr) {
                    $toggle.prop('checked', !enabled);
                    setStatus($status, responseMessage(xhr.responseJSON || null, 'Could not update the offer.'), true);
                });
        });
    }

    $(function () {
        bindOfferForm();
        bindOfferList();
    });
})(jQuery);
