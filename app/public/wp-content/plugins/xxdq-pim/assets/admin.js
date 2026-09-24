(function ($) {
    $(function () {
        // Confirm destructive actions
        $('form.xxdq-pim-form').on('submit', function () {
            var $btn = $(this).find('button[type=submit]');
            $btn.prop('disabled', true).text('处理中…');
        });
    });
})(jQuery);
