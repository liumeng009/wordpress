/*
 * XXDQ Blocks — debug snippet.
 * 临时把这段粘到 wp-admin 的浏览器 Console 里运行,
 * 会打印出区块是否真正注册、分类是否可见,以及编辑器内
 * 已加载的脚本版本。
 */
(function () {
  var out = { wp: !!window.wp, plugins: [] };
  if (!window.wp || !window.wp.blocks) {
    console.error('[XXDQ Debug] wp.blocks 不可用 — 当前不在 WP 后台?');
    return out;
  }
  var B = window.wp.blocks;
  out.allCategories = B.getCategories().map(function (c) { return c.slug; });
  out.hasXxdqCategory = out.allCategories.indexOf('xxdq') !== -1;
  out.hasWidgetsCategory = out.allCategories.indexOf('widgets') !== -1;
  out.selectorBlock = (B.getBlockType && B.getBlockType('xxdq/selector-tool')) || null;
  if (out.selectorBlock) {
    out.selectorBlockTitle = out.selectorBlock.title;
    out.selectorBlockCategory = out.selectorBlock.category;
    out.selectorBlockKeywords = out.selectorBlock.keywords;
  } else {
    console.error('[XXDQ Debug] ✗ 区块 xxdq/selector-tool 未注册');
  }
  var enq = (window._wpEditorLoader && window._wpEditorLoader.scripts) || [];
  out.editorScriptEnqueued = enq.filter(function (s) { return /editor\.js/.test(s); });
  if (window.console && console.table) console.table(out);
  else console.log(out);
  return out;
})();
