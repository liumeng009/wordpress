/* global wp, jQuery */
(function ($) {
  'use strict';

  // Reused across all pickers on the page.
  var frame;

  $(document).on('click', '.xxdq-cover-image-select', function (e) {
    e.preventDefault();
    var $btn   = $(this);
    var $wrap  = $btn.closest('.xxdq-cover-image-picker');
    var $input = $wrap.find('input[name="xxdq_cover_image"]');
    var $prev  = $wrap.find('.xxdq-cover-image-preview');
    var $rm    = $wrap.find('.xxdq-cover-image-remove');

    if (!frame) {
      frame = wp.media({
        title:    '选择封面图',
        button:   { text: '使用此图' },
        library:  { type: 'image' },
        multiple: false
      });
    }

    // Re-attach select handler every time (cheap, idempotent).
    frame.off('select');
    frame.on('select', function () {
      var att = frame.state().get('selection').first().toJSON();
      var url = (att.sizes && att.sizes.medium && att.sizes.medium.url) || att.url;
      $input.val(att.id);
      $prev.html('<img src="' + url + '" alt="" />');
      $rm.show();
      $btn.text('更换');
    });

    frame.open();
  });

  $(document).on('click', '.xxdq-cover-image-remove', function (e) {
    e.preventDefault();
    var $wrap  = $(this).closest('.xxdq-cover-image-picker');
    var $input = $wrap.find('input[name="xxdq_cover_image"]');
    var $prev  = $wrap.find('.xxdq-cover-image-preview');
    var $sel   = $wrap.find('.xxdq-cover-image-select');
    $input.val('');
    $prev.empty();
    $(this).hide();
    $sel.text('从媒体库选择');
  });
})(jQuery);
