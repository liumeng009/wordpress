/**
 * XXDQ Blocks — editor-side enhancements.
 *
 * v2.2.0 — diagnostic + dual registration.
 *
 * Two independent registration paths are wired up so the Selector Tool
 * block shows up in the inserter even when something in the deployment
 * pipeline silently drops the server-side `register_block_type` call:
 *
 *   PATH A — server registration is propagating correctly:
 *     The block already has a registered client-side definition.
 *     `getBlockType` returns it. The `BlockEdit` filter below adds
 *     the SEO Inspector panel to the default SSR preview.
 *
 *   PATH B — server registration is missing or out of sync:
 *     `getBlockType` returns null. We call `registerBlockType`
 *     ourselves with a `widgets`-category fallback so the block
 *     shows up under the always-visible "Widgets" inserter tab, and
 *     the `edit` function embeds the SEO Inspector directly.
 *
 *   EITHER WAY, after a hard refresh the user can find the block by
 *   typing "端子" / "选型" / "计算器" / "Selector Tool" — same
 *   keywords either path.
 *
 * A `console.warn('[XXDQ] …')` line is printed on every load so the
 * admin can verify in DevTools whether the script ran and which path
 * it took. If the warn is missing, the file isn't being enqueued
 * (deployment or caching problem); if the warn is present but the
 * block still doesn't appear, the inserter is filtered by something
 * downstream.
 */
(function () {
  'use strict';

  // Bail hard if the editor globals aren't loaded. The user is
  // probably on the public site, not /wp-admin — that's fine.
  if (!window.wp || !window.wp.hooks) return;

  var hooks        = window.wp.hooks;
  var blocks       = window.wp.blocks;
  var element      = window.wp.element;
  var blockEditor  = window.wp.blockEditor || window.wp.editor;
  var components   = window.wp.components;
  var i18n         = window.wp.i18n || { __: function (s) { return s; } };

  try {
    console.warn(
      '[XXDQ] editor.js v2.2.0 loaded — wp=' + !!window.wp +
      ' hooks=' + !!(hooks && hooks.addFilter) +
      ' element=' + !!element +
      ' blockEditor=' + !!blockEditor +
      ' components=' + !!components
    );
  } catch (e) { /* console may be unavailable in some iframe contexts */ }

  // ── 1. Category registration (JS-side fallback) ────────────
  // The PHP `block_categories_all` filter is the primary path, but
  // some themes / plugins overwrite the inserter category list
  // AFTER our filter runs and silently strip unknown slugs. This
  // JS-side registration runs at editor boot and is hard to undo.
  if (blocks && typeof blocks.setCategories === 'function') {
    try {
      var existing = typeof blocks.getCategories === 'function' ? blocks.getCategories() : [];
      var hasXxdq  = existing.some(function (c) { return c && c.slug === 'xxdq'; });
      if (!hasXxdq) {
        blocks.setCategories([
          { slug: 'xxdq', title: i18n.__('XXDQ Blocks', 'xxdq-blocks'), icon: 'admin-customizer' }
        ].concat(existing));
        console.warn('[XXDQ] registered "xxdq" block category via setCategories()');
      }
    } catch (e) {
      console.warn('[XXDQ] setCategories failed: ' + e.message);
    }
  }

  if (!element || !blockEditor || !components) {
    console.warn('[XXDQ] missing required globals, aborting block registration');
    return;
  }
  if (typeof hooks.addFilter !== 'function') {
    console.warn('[XXDQ] wp.hooks.addFilter missing, aborting');
    return;
  }

  var el           = element.createElement;
  var Fragment     = element.Fragment;
  var InspectorCtl = blockEditor.InspectorControls;
  var PanelBody    = components.PanelBody;
  var TextControl  = components.TextControl;
  var TextareaCtl  = components.TextareaControl;
  var Notice       = components.Notice;

  var BLOCK_NAME = 'xxdq/selector-tool';

  // ── 2a. SEO field configuration (shared by both paths) ────
  var SEO_FIELDS = [
    {
      key: 'selectorIntro',
      control: 'textarea',
      label: i18n.__('使用说明 (HTML 可用)', 'xxdq-blocks'),
    },
    {
      key: 'selectorFormula',
      control: 'text',
      label: i18n.__('计算公式 (一行等式)', 'xxdq-blocks'),
    },
    {
      key: 'selectorInquiryUrl',
      control: 'url',
      label: i18n.__('询价页 URL', 'xxdq-blocks'),
      help:  i18n.__('「立即询价」按钮的跳转目标。', 'xxdq-blocks'),
    },
  ];

  function buildInspectorFields(attrs, setAttrs) {
    var out = [];
    SEO_FIELDS.forEach(function (field, idx) {
      var current = attrs[field.key] || '';
      var onChange = function (v) {
        // Persist exactly what the user typed, including empty
        // string — the PHP render callback treats '' as "fall
        // through to ACF / default" so admins can intentionally
        // clear an inherited value here.
        var patch = {};
        patch[field.key] = v || '';
        setAttrs(patch);
      };
      if (field.control === 'textarea' && TextareaCtl) {
        out.push(el(TextareaCtl, {
          key: field.key, label: field.label, value: current, onChange: onChange,
        }));
      } else if (TextControl) {
        out.push(el(TextControl, {
          key: field.key, label: field.label, help: field.help || '',
          type: field.control === 'url' ? 'url' : 'text',
          value: current, onChange: onChange,
        }));
      }
    });
    return out;
  }

  function buildInspector(attrs, setAttrs) {
    if (!InspectorCtl || !PanelBody) return null;
    var children = [];
    if (Notice) {
      children.push(el(Notice,
        { key: 'hint', status: 'info', isDismissible: false },
        '不填则使用代码内嵌的默认文案。FAQ 请在页面右侧「端子选型工具 (SEO 文案)」面板编辑。'
      ));
    }
    children = children.concat(buildInspectorFields(attrs, setAttrs));
    return el(InspectorCtl, { key: 'xxdq-selector-inspector' },
      el(PanelBody,
        { key: 'panel', title: i18n.__('端子选型工具 — SEO 文案', 'xxdq-blocks'), initialOpen: true },
        children
      )
    );
  }

  // ── 2b. PATH B — client-side registerBlockType fallback ───
  // Only fires if the block isn't already registered (i.e. the
  // server-side `register_block_type` call didn't propagate to the
  // editor). Tries `widgets` first (always-visible tab) and falls
  // back to the `xxdq` category we just registered.
  var alreadyRegistered = blocks.getBlockType && blocks.getBlockType(BLOCK_NAME);
  if (!alreadyRegistered) {
    try {
      var category = blocks.getCategory('xxdq') ? 'xxdq' : 'widgets';
      blocks.registerBlockType(BLOCK_NAME, {
        title:       i18n.__('端子选型计算器', 'xxdq-blocks'),
        category:    category,
        icon:        'update',
        keywords:    [
          '端子', '选型', '计算器', '端子选型', '冷压端子', '铜鼻子',
          'selector', 'terminal', 'recommender', 'selector tool',
        ],
        description: i18n.__('根据导线规格、材质和工况,推荐铜鼻子 / 冷压端子 / 预绝缘端子 / 铜铝过渡端子型号', 'xxdq-blocks'),
        attributes: {
          selectorIntro:      { type: 'string', default: '' },
          selectorFormula:    { type: 'string', default: '' },
          selectorInquiryUrl: { type: 'string', default: '' },
        },
        supports: { html: false, reusable: true, anchor: true },
        // Server-rendered → save returns null. WP uses the PHP
        // render_callback to produce the actual HTML.
        save: function () { return null; },
        // Edit preview: a simple placeholder so admins see the
        // block exists. The Inspector is layered on top via
        // the `BlockEdit` filter below (it runs for every block,
        // including this one).
        edit: function (props) {
          return el(Fragment, {},
            el('div',
              {
                key: 'preview',
                className: 'wp-block-xxdq-selector-tool-editor-preview',
                style: {
                  padding: '16px',
                  border: '1px dashed #ccc',
                  borderRadius: '4px',
                  background: '#f8f9fa',
                  color: '#555',
                  fontSize: '14px',
                },
              },
              '⚡ 端子选型计算器 — 在前端自动渲染。',
              el('br'),
              el('small', { style: { color: '#999' } },
                '右侧「SEO 文案」面板可编辑使用说明 / 公式 / 询价链接。')
            )
          );
        },
      });
      console.warn('[XXDQ] registered "' + BLOCK_NAME + '" client-side (PATH B) in category="' + category + '"');
    } catch (e) {
      console.warn('[XXDQ] registerBlockType failed: ' + e.message);
    }
  } else {
    console.warn('[XXDQ] block already registered (PATH A), only adding Inspector via filter');
  }

  // ── 2c. BlockEdit filter — Inspector for the block ───────
  // Runs for EVERY block on the page. We only act on our block.
  // `BlockEdit` (the closure arg) is the wrapped component that
  // currently renders the block; we render it as-is and add the
  // InspectorControls alongside as a sibling.
  hooks.addFilter('editor.BlockEdit', 'xxdq/selector-tool-inspector', function (BlockEdit) {
    return function (props) {
      if (props.name !== BLOCK_NAME) {
        return el(BlockEdit, props);
      }
      var inspector = buildInspector(props.attributes || {}, props.setAttributes);
      return el(Fragment, {},
        el(BlockEdit, props),
        inspector
      );
    };
  });
})();
