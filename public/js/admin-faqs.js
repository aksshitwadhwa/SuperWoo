(function ($) {
    'use strict';

    function bindRow($row) {
        $row.find('.superwoo-remove-faq-row').off('click.superwoo').on('click.superwoo', function (event) {
            event.preventDefault();
            $(this).closest('.superwoo-faq-row').remove();
        });
    }

    $(function () {
        var rowIndex = $('#superwoo-faq-rows .superwoo-faq-row').length;

        $('#superwoo-faq-rows .superwoo-faq-row').each(function () {
            bindRow($(this));
        });

        $('#superwoo-add-faq-row').on('click', function (event) {
            event.preventDefault();

            var html = $('#superwoo-faq-row-template').html().replace(/__INDEX__/g, rowIndex);
            var $row = $(html);

            $('#superwoo-faq-rows').append($row);
            bindRow($row);
            rowIndex += 1;
        });
    });
})(jQuery);
