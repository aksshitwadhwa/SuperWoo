(function ($) {
    'use strict';

    var selected = null;
    var search = $('#superwoo-video-product-search');
    var list = $('<div id="superwoo-video-product-results" class="superwoo-video-product-results" role="listbox" hidden></div>');
    search.after(list);

    function escapeHtml(value) {
        return $('<div>').text(value || '').html();
    }

    function sync() {
        var products = [];
        $('#superwoo-video-products li').each(function () {
            products.push({
                product_id: $(this).data('product-id'),
                start: $(this).find('.superwoo-video-start').val() || 0,
                end: $(this).find('.superwoo-video-end').val() || 0
            });
        });
        $('#superwoo-video-products-value').val(JSON.stringify(products));
    }

    function renderResults(results) {
        list.empty();
        if (!results.length) {
            list.prop('hidden', true);
            return;
        }
        $.each(results, function (_, product) {
            $('<button type="button" class="button-link superwoo-video-product-result" role="option"></button>')
                .attr('data-product-id', product.id)
                .text(product.text)
                .appendTo(list);
        });
        list.prop('hidden', false);
    }

    search.on('input', function () {
        var term = $(this).val();
        selected = null;
        if (term.length < 2) {
            renderResults([]);
            return;
        }
        $.get(SuperWooVideoAdmin.ajaxUrl, {
            action: 'superwoo_video_search_products',
            nonce: SuperWooVideoAdmin.nonce,
            term: term
        }, function (response) {
            renderResults(response && response.success ? response.data.results : []);
        });
    });

    $(document).on('click', '.superwoo-video-product-result', function () {
        selected = { id: $(this).data('product-id'), text: $(this).text() };
        search.val(selected.text);
        list.prop('hidden', true);
    });

    $('#superwoo-video-add-product').on('click', function () {
        if (!selected || $('#superwoo-video-products li[data-product-id="' + selected.id + '"]').length) {
            return;
        }
        $('#superwoo-video-products').append(
            '<li data-product-id="' + selected.id + '"><strong>' + escapeHtml(selected.text) +
            '</strong> <label>Start <input type="number" min="0" step="0.1" class="superwoo-video-start" value="0"></label>' +
            ' <label>End <input type="number" min="0" step="0.1" class="superwoo-video-end" value="0"></label>' +
            ' <button type="button" class="button-link-delete superwoo-video-remove-product">Remove</button></li>'
        );
        selected = null;
        search.val('');
        sync();
    });

    $(document).on('click', '[data-superwoo-media-target]', function () {
        var button = $(this);
        var target = $('#' + button.data('superwoo-media-target'));
        var frame = wp.media({
            title: button.data('superwoo-media-type') === 'image' ? 'Choose poster image' : 'Choose video',
            button: { text: 'Use this file' },
            library: { type: button.data('superwoo-media-type') }
        });
        frame.on('select', function () {
            var attachment = frame.state().get('selection').first().toJSON();
            target.val(attachment.id).trigger('change');
        });
        frame.open();
    });

    $(document).on('click', '.superwoo-video-remove-product', function () {
        $(this).closest('li').remove();
        sync();
    }).on('change input', '.superwoo-video-start,.superwoo-video-end', sync);

    $('#superwoo-video-products').sortable({ update: sync });
}(jQuery));
