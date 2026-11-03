<?php
/**
 * ACF Options Page for site-wide settings (Phase 9).
 *
 * - Registers a single "网站设置" options page under slug `xxdq-site-settings`.
 * - Declares the local field group used by the home-page blocks and footer.
 * - Loaded conditionally (only if ACF is active) from xxdq-blocks.php.
 *
 * Note: We use `acf/init` so ACF Pro 5.6+ is required (free ACF does not
 * include `acf_add_local_field_group` in older versions, but all our
 * installations run ACF Pro 6.x which exposes it).
 */

if (!defined('ABSPATH')) exit;

add_action('acf/init', 'xxdq_register_options_page');
add_action('acf/init', 'xxdq_register_options_fields');

/**
 * Register the parent + child options pages.
 * Uses the legacy `acf_add_options_page()` API which is available in
 * ACF Pro 5.x and 6.x without any compatibility shim.
 */
function xxdq_register_options_page() {
    if (!function_exists('acf_add_options_page')) return;

    acf_add_options_page([
        'page_title'    => '网站设置',
        'menu_title'    => '网站设置',
        'menu_slug'     => 'xxdq-site-settings',
        'capability'    => 'manage_options',
        'icon_url'      => 'dashicons-admin-settings',
        'position'      => 59,
        'redirect'      => false,
        'autoload'      => true,
    ]);
}

/**
 * Declare all field groups attached to the options page.
 * Each group is keyed to its options-page slug via `'options_page' => 'xxdq-site-settings'`.
 */
function xxdq_register_options_fields() {
    if (!function_exists('acf_add_local_field_group')) return;

    /* ── 1. Banner / Hero slider settings ──────────────────────── */
    acf_add_local_field_group([
        'key'      => 'group_xxdq_banner',
        'title'    => '首页轮播 Banner',
        'fields'   => [
            [
                'key'   => 'field_xxdq_hero_banner',
                'label' => '首页轮播',
                'name'  => 'hero_banner',
                'type'  => 'repeater',
                'instructions' => '首页 Hero 轮播图片,最多 5 张,首张为默认显示。',
                'required' => 0,
                'min'     => 0,
                'max'     => 5,
                'layout'  => 'row',
                'button_label' => '添加轮播图',
                'sub_fields' => [
                    [
                        'key'   => 'field_xxdq_hb_image',
                        'label' => '背景图片',
                        'name'  => 'image',
                        'type'  => 'image',
                        'return_format' => 'url',
                        'preview_size' => 'medium',
                        'library' => 'all',
                    ],
                    [
                        'key'   => 'field_xxdq_hb_title',
                        'label' => '标题',
                        'name'  => 'title',
                        'type'  => 'text',
                    ],
                    [
                        'key'   => 'field_xxdq_hb_subtitle',
                        'label' => '副标题',
                        'name'  => 'subtitle',
                        'type'  => 'textarea',
                        'rows'  => 2,
                        'new_lines' => '',
                    ],
                    [
                        'key'   => 'field_xxdq_hb_btn_text',
                        'label' => '按钮文字',
                        'name'  => 'button_text',
                        'type'  => 'text',
                    ],
                    [
                        'key'   => 'field_xxdq_hb_btn_url',
                        'label' => '按钮链接',
                        'name'  => 'button_url',
                        'type'  => 'url',
                    ],
                ],
            ],
        ],
        'location' => [
            [
                [
                    'param'    => 'options_page',
                    'operator' => '==',
                    'value'    => 'xxdq-site-settings',
                ],
            ],
        ],
        'menu_order' => 0,
    ]);

    /* ── 2. Hero text settings (eyebrow / subtitle / ctas) ────── */
    acf_add_local_field_group([
        'key'      => 'group_xxdq_hero_text',
        'title'    => 'Hero 文本设置',
        'fields'   => [
            [
                'key' => 'field_xxdq_hero_eyebrow',
                'label' => 'Hero 顶部标签',
                'name'  => 'hero_eyebrow',
                'type'  => 'text',
                'default_value' => '源头工厂 · 全产业链 · 现货直发',
            ],
            [
                'key' => 'field_xxdq_hero_subtitle',
                'label' => 'Hero 副标题',
                'name'  => 'hero_subtitle',
                'type'  => 'textarea',
                'rows'  => 3,
                'default_value' => '铜鼻子 · 冷压端子 · 预绝缘端子 · 接插件 · 500+ 规格 · CE/RoHS 认证 · 24小时出货',
            ],
            [
                'key' => 'field_xxdq_hero_cta_text',
                'label' => '主按钮文字',
                'name'  => 'hero_cta_text',
                'type'  => 'text',
                'default_value' => '立即询价',
            ],
            [
                'key' => 'field_xxdq_hero_cta_url',
                'label' => '主按钮链接',
                'name'  => 'hero_cta_url',
                'type'  => 'url',
                'default_value' => '/询价单/',
            ],
            [
                'key' => 'field_xxdq_hero_sec_cta_text',
                'label' => '次按钮文字',
                'name'  => 'hero_secondary_cta_text',
                'type'  => 'text',
                'default_value' => '浏览产品',
            ],
            [
                'key' => 'field_xxdq_hero_sec_cta_url',
                'label' => '次按钮链接',
                'name'  => 'hero_secondary_cta_url',
                'type'  => 'url',
                'default_value' => '/product/',
            ],
        ],
        'location' => [
            [
                [
                    'param'    => 'options_page',
                    'operator' => '==',
                    'value'    => 'xxdq-site-settings',
                ],
            ],
        ],
        'menu_order' => 1,
    ]);

    /* ── 3. Why Us (the existing 4-item block now reads from here) ── */
    acf_add_local_field_group([
        'key'      => 'group_xxdq_why_us',
        'title'    => '为什么选择我们',
        'fields'   => [
            [
                'key' => 'field_xxdq_why_items',
                'label' => '为什么选择我们条目',
                'name'  => 'why_us_items',
                'type'  => 'repeater',
                'instructions' => '展示在"为什么选择我们"区块的卡片,最多 6 张。',
                'min' => 0,
                'max' => 6,
                'layout' => 'row',
                'button_label' => '添加条目',
                'sub_fields' => [
                    [
                        'key' => 'field_xxdq_wu_icon',
                        'label' => '图标(Emoji 或文本)',
                        'name'  => 'icon',
                        'type'  => 'text',
                    ],
                    [
                        'key' => 'field_xxdq_wu_title',
                        'label' => '标题',
                        'name'  => 'title',
                        'type'  => 'text',
                    ],
                    [
                        'key' => 'field_xxdq_wu_desc',
                        'label' => '描述',
                        'name'  => 'description',
                        'type'  => 'textarea',
                        'rows'  => 2,
                    ],
                ],
            ],
        ],
        'location' => [
            [
                [
                    'param'    => 'options_page',
                    'operator' => '==',
                    'value'    => 'xxdq-site-settings',
                ],
            ],
        ],
        'menu_order' => 2,
    ]);

    /* ── 4. Application Fields (NEW) ─────────────────────────── */
    acf_add_local_field_group([
        'key'      => 'group_xxdq_app_fields',
        'title'    => '应用领域',
        'fields'   => [
            [
                'key' => 'field_xxdq_app_fields',
                'label' => '应用领域列表',
                'name'  => 'application_fields',
                'type'  => 'repeater',
                'instructions' => '首页"应用领域"区块的内容,最多 8 项。',
                'min' => 0,
                'max' => 8,
                'layout' => 'row',
                'button_label' => '添加领域',
                'sub_fields' => [
                    [
                        'key' => 'field_xxdq_af_icon',
                        'label' => '图标',
                        'name'  => 'icon',
                        'type'  => 'text',
                    ],
                    [
                        'key' => 'field_xxdq_af_title',
                        'label' => '标题',
                        'name'  => 'title',
                        'type'  => 'text',
                    ],
                    [
                        'key' => 'field_xxdq_af_desc',
                        'label' => '描述',
                        'name'  => 'description',
                        'type'  => 'textarea',
                        'rows'  => 2,
                    ],
                ],
            ],
        ],
        'location' => [
            [
                [
                    'param'    => 'options_page',
                    'operator' => '==',
                    'value'    => 'xxdq-site-settings',
                ],
            ],
        ],
        'menu_order' => 3,
    ]);

    /* ── 5. News section settings ───────────────────────────── */
    acf_add_local_field_group([
        'key'      => 'group_xxdq_news_settings',
        'title'    => '新闻区块设置',
        'fields'   => [
            [
                'key' => 'field_xxdq_news_count',
                'label' => '首页显示新闻数量',
                'name'  => 'news_count',
                'type'  => 'number',
                'default_value' => 3,
                'min' => 1,
                'max' => 20,
                'step' => 1,
            ],
            [
                'key' => 'field_xxdq_news_more_link',
                'label' => '查看更多链接',
                'name'  => 'news_more_link',
                'type'  => 'url',
                'default_value' => '/news/',
            ],
        ],
        'location' => [
            [
                [
                    'param'    => 'options_page',
                    'operator' => '==',
                    'value'    => 'xxdq-site-settings',
                ],
            ],
        ],
        'menu_order' => 4,
    ]);

    /* ── 6. Company info (used by Contact block + footer) ───── */
    acf_add_local_field_group([
        'key'      => 'group_xxdq_company_info',
        'title'    => '公司信息',
        'fields'   => [
            [
                'key' => 'field_xxdq_company_name',
                'label' => '公司名称',
                'name'  => 'company_name',
                'type'  => 'text',
                'default_value' => '乐清市耕文电气有限公司',
            ],
            [
                'key' => 'field_xxdq_company_intro',
                'label' => '公司简介',
                'name'  => 'company_intro',
                'type'  => 'textarea',
                'rows'  => 4,
            ],
            [
                'key' => 'field_xxdq_address',
                'label' => '地址',
                'name'  => 'address',
                'type'  => 'text',
            ],
            [
                'key' => 'field_xxdq_phone',
                'label' => '电话',
                'name'  => 'phone',
                'type'  => 'text',
            ],
            [
                'key' => 'field_xxdq_email',
                'label' => '邮箱',
                'name'  => 'email',
                'type'  => 'email',
            ],
            [
                'key' => 'field_xxdq_wechat',
                'label' => '微信',
                'name'  => 'wechat',
                'type'  => 'text',
            ],
            [
                'key' => 'field_xxdq_work_hours',
                'label' => '工作时间',
                'name'  => 'work_hours',
                'type'  => 'text',
            ],
        ],
        'location' => [
            [
                [
                    'param'    => 'options_page',
                    'operator' => '==',
                    'value'    => 'xxdq-site-settings',
                ],
            ],
        ],
        'menu_order' => 5,
    ]);

    /* ── 7. Footer settings ─────────────────────────────────── */
    acf_add_local_field_group([
        'key'      => 'group_xxdq_footer_settings',
        'title'    => '页脚设置',
        'fields'   => [
            [
                'key' => 'field_xxdq_footer_brand_intro',
                'label' => '品牌简介',
                'name'  => 'footer_brand_intro',
                'type'  => 'textarea',
                'rows'  => 4,
            ],
            [
                'key' => 'field_xxdq_footer_quick_links',
                'label' => '快捷链接',
                'name'  => 'footer_quick_links',
                'type'  => 'repeater',
                'min' => 0,
                'max' => 12,
                'layout' => 'row',
                'button_label' => '添加链接',
                'sub_fields' => [
                    [
                        'key' => 'field_xxdq_fql_label',
                        'label' => '标签',
                        'name'  => 'label',
                        'type'  => 'text',
                    ],
                    [
                        'key' => 'field_xxdq_fql_url',
                        'label' => '链接',
                        'name'  => 'url',
                        'type'  => 'url',
                    ],
                ],
            ],
            [
                'key' => 'field_xxdq_footer_main_products',
                'label' => '主营产品(纯文本/HTML)',
                'name'  => 'footer_main_products',
                'type'  => 'textarea',
                'rows'  => 3,
            ],
        ],
        'location' => [
            [
                [
                    'param'    => 'options_page',
                    'operator' => '==',
                    'value'    => 'xxdq-site-settings',
                ],
            ],
        ],
        'menu_order' => 6,
    ]);

    /* ── 8. Copyright / ICP ──────────────────────────────────── */
    acf_add_local_field_group([
        'key'      => 'group_xxdq_copyright',
        'title'    => '版权 / ICP',
        'fields'   => [
            [
                'key' => 'field_xxdq_copyright_text',
                'label' => '版权文字',
                'name'  => 'copyright_text',
                'type'  => 'text',
                'default_value' => '© 2026 乐清市耕文电气有限公司 版权所有',
            ],
            [
                'key' => 'field_xxdq_icp_number',
                'label' => 'ICP 备案号',
                'name'  => 'icp_number',
                'type'  => 'text',
            ],
        ],
        'location' => [
            [
                [
                    'param'    => 'options_page',
                    'operator' => '==',
                    'value'    => 'xxdq-site-settings',
                ],
            ],
        ],
        'menu_order' => 7,
    ]);

    /* ── 9. SEO / Schema organization fields ─────────────────── */
    /*
     * 与 20-schema.php 字段名一一对应 (company_*)。如果这里为空,
     * schema 会回退到 group_xxdq_company_info 里的 address/phone/email/work_hours。
     */
    acf_add_local_field_group([
        'key'      => 'group_xxdq_seo_org',
        'title'    => 'SEO / Schema 公司信息',
        'fields'   => [
            [
                'key'   => 'field_xxdq_company_phone',
                'label' => '联系电话 (含国际区号, +86-...)',
                'name'  => 'company_phone',
                'type'  => 'text',
                'instructions' => 'JSON-LD 的 Organization.telephone / LocalBusiness.telephone',
            ],
            [
                'key'   => 'field_xxdq_company_email',
                'label' => '公司邮箱',
                'name'  => 'company_email',
                'type'  => 'email',
                'instructions' => 'JSON-LD 的 Organization.email',
            ],
            [
                'key'   => 'field_xxdq_company_address',
                'label' => '公司地址',
                'name'  => 'company_address',
                'type'  => 'text',
                'instructions' => 'JSON-LD 的 PostalAddress.streetAddress',
            ],
            [
                'key'   => 'field_xxdq_company_hours',
                'label' => '营业时间',
                'name'  => 'company_hours',
                'type'  => 'text',
                'default_value' => '周一至周六 8:30-17:30',
                'instructions' => 'JSON-LD 的 OpeningHoursSpecification.opens/closes',
            ],
            [
                'key'   => 'field_xxdq_company_logo',
                'label' => '公司 Logo',
                'name'  => 'company_logo',
                'type'  => 'image',
                'return_format' => 'url',
                'preview_size' => 'medium',
                'instructions' => 'Organization.schema.org/logo, 推荐方形或横向 PNG/SVG。',
            ],
            [
                'key'   => 'field_xxdq_default_og_image',
                'label' => '默认 OG/Twitter 分享图 (1200×630)',
                'name'  => 'default_og_image',
                'type'  => 'image',
                'return_format' => 'url',
                'preview_size' => 'medium',
                'instructions' => '没有特色图时使用。建议 1200×630 JPG/PNG。',
            ],
        ],
        'location' => [
            [
                [
                    'param'    => 'options_page',
                    'operator' => '==',
                    'value'    => 'xxdq-site-settings',
                ],
            ],
        ],
        'menu_order' => 8,
    ]);

    /* ── 10. Selector Tool (terminal recommender) SEO content ──
     * Per-page field group. SEO文案 (intro / formula / FAQs / 询价链接)
     * 在使用 selector-tool 区块的页面后台可编辑；不填则前端用代码内嵌的默认值。 */
    acf_add_local_field_group([
        'key'      => 'group_xxdq_selector_tool',
        'title'    => '端子选型工具 (SEO 文案)',
        'fields'   => [
            [
                'key'   => 'field_xxdq_selector_intro',
                'label' => '使用说明',
                'name'  => 'selector_intro',
                'type'  => 'wysiwyg',
                'media_upload' => 0,
                'rows'  => 6,
                'instructions' => '显示在工具下方的介绍段（HTML 可用）。为空时使用代码内嵌的默认文案。',
            ],
            [
                'key'   => 'field_xxdq_selector_formula',
                'label' => '计算公式',
                'name'  => 'selector_formula',
                'type'  => 'text',
                'instructions' => '一行等式，会以等宽字体显示。',
            ],
            [
                'key'   => 'field_xxdq_selector_faqs',
                'label' => '常见问题 (FAQ)',
                'name'  => 'selector_faqs',
                'type'  => 'repeater',
                'min'   => 0,
                'max'   => 20,
                'layout'  => 'row',
                'button_label' => '添加一条 FAQ',
                'sub_fields' => [
                    [
                        'key'   => 'field_xxdq_selector_faq_q',
                        'label' => '问题',
                        'name'  => 'q',
                        'type'  => 'text',
                    ],
                    [
                        'key'   => 'field_xxdq_selector_faq_a',
                        'label' => '答案',
                        'name'  => 'a',
                        'type'  => 'textarea',
                        'rows'  => 3,
                    ],
                ],
            ],
            [
                'key'   => 'field_xxdq_selector_inquiry_url',
                'label' => '询价页 URL',
                'name'  => 'selector_inquiry_url',
                'type'  => 'url',
                'instructions' => '「立即询价」按钮的跳转目标。带 ?model=xxx 参数，会被询价表单自动预填产品型号。',
            ],
        ],
        'location' => [
            [
                [
                    'param'    => 'post_type',
                    'operator' => '==',
                    'value'    => 'page',
                ],
            ],
        ],
        'menu_order' => 0,
    ]);
}
