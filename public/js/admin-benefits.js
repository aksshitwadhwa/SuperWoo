(function ($) {
    'use strict';

    $(document).on('click', '#superwoo-term-select-icon', function (event) {
        event.preventDefault();

        var frame = wp.media({
            title: 'Select Benefit Icon',
            multiple: false,
            library: { type: 'image' }
        });

        frame.on('select', function () {
            var attachment = frame.state().get('selection').first().toJSON();
            var thumb = attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url;

            $('#superwoo-term-icon-id').val(attachment.id);
            $('.superwoo-term-icon-preview img').attr('src', thumb).show();
            $('#superwoo-term-remove-icon').show();
        });

        frame.open();
    });

    $(document).on('click', '#superwoo-term-remove-icon', function (event) {
        event.preventDefault();
        $('#superwoo-term-icon-id').val('');
        $('.superwoo-term-icon-preview img').attr('src', '').hide();
        $(this).hide();
    });

    $(document).on('click', '#superwoo-copy-benefits-shortcode', function () {
        var text = $('#superwoo-benefits-shortcode').text();
        var button = $(this);
        var original = button.text();

        if (!navigator.clipboard) {
            return;
        }

        navigator.clipboard.writeText(text).then(function () {
            button.text('Copied');
            window.setTimeout(function () {
                button.text(original);
            }, 1400);
        });
    });
})(jQuery);
