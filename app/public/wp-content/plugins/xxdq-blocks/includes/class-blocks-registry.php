<?php
/**
 * Registry: declares all blocks to ACF and ensures assets are enqueued.
 *
 * Static vs Dynamic:
 *  - Static blocks render the same HTML each time (content from post meta)
 *  - Dynamic blocks always re-render via render_callback (so they pick up product changes)
 */

if (!defined('ABSPATH')) exit;

class XXDQ_Blocks_Registry {

    private static $instance;

    public static function instance() {
        if (!self::$instance) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action('init', [$this, 'register_blocks'], 20);
        add_action('enqueue_block_editor_assets', [$this, 'editor_assets']);
        // Register the custom `xxdq` block category so blocks declared with
        // `'category' => 'xxdq'` actually show up in the inserter. Without
        // this, WP silently drops the block from the inserter (or sticks it
        // in an unused "Uncategorized" group). Filter name is
        // `block_categories_all` since WP 5.8.
        add_filter('block_categories_all', [$this, 'register_category'], 10, 1);
    }

    /**
     * Inject the `xxdq` category into the Gutenberg inserter. Runs late
     * so it can appear at the end of the category list — alongside the
     * built-in "Common" / "Widgets" groups, not buried at the top.
     */
    public function register_category($categories) {
        // Defensive: never add twice (e.g. if some other plugin re-runs
        // registration during page reload).
        foreach ((array) $categories as $cat) {
            if (isset($cat['slug']) && $cat['slug'] === 'xxdq') {
                return $categories;
            }
        }
        $categories[] = [
            'slug'  => 'xxdq',
            'title' => __('XXDQ Blocks', 'xxdq-blocks'),
            'icon'  => null, // null = default layout icon
        ];
        return $categories;
    }

    public function register_blocks() {
        if (!function_exists('register_block_type')) return;

        // ── Static blocks ────────────────────────────────────────
        $this->reg('hero',           'Hero',           'static');
        $this->reg('category-grid',  'Category Grid',  'static');
        $this->reg('why-us',         'Why Us',         'static');
        $this->reg('cta',            'Call to Action', 'static');
        $this->reg('contact-cards',  'Contact Cards',  'static');
        $this->reg('about',          'About',          'static');
        $this->reg('tech-docs',      'Tech Docs',      'static');

        // ── Dynamic blocks ───────────────────────────────────────
        $this->reg('hot-products',     'Hot Products',     'dynamic');
        $this->reg('category-archive', 'Category Archive', 'dynamic');
        $this->reg('series-accordion', 'Series Accordion', 'dynamic');
        $this->reg('product-detail',   'Product Detail',   'dynamic');
        // Selector Tool gets explicit attributes + Chinese keywords so
        // admins can find it by typing "端子" / "选型" / "计算器" in the
        // inserter (English "Selector Tool" alone is too generic for a
        // Chinese-speaking team). The attributes also feed the JS
        // Inspector panel registered in assets/editor.js.
        $this->reg('selector-tool',    '端子选型计算器',    'dynamic', [
            'selectorIntro'      => ['type' => 'string', 'default' => ''],
            'selectorFormula'    => ['type' => 'string', 'default' => ''],
            'selectorInquiryUrl' => ['type' => 'string', 'default' => ''],
        ], ['端子', '选型', '计算器', '端子选型', '冷压端子', '铜鼻子', 'selector', 'terminal', 'recommender']);
        $this->reg('inquiry-form',     'Inquiry Form',     'dynamic');
        $this->reg('pdf-aggregator',   'PDF Aggregator',   'dynamic');
        $this->reg('catalog-hub',      'Catalog Hub',      'dynamic');

        // ── Phase 7 blocks ───────────────────────────────────────
        $this->reg('stats-bar',        'Stats Bar',        'static');
        $this->reg('process-steps',    'Process Steps',    'static');

        // ── Phase 9 blocks ───────────────────────────────────────
        // hero-slider replaces hero on the home page (slider from ACF options).
        $this->reg('hero-slider',        'Hero Slider',         'static');
        $this->reg('application-fields', 'Application Fields',  'static');
        $this->reg('news-section',       'News Section',        'dynamic');
    }

    private function reg($name, $title, $mode, $attributes = [], $keywords = null) {
        $method = 'render_' . str_replace('-', '_', $name);
        $block_name = 'xxdq/' . $name;

        // Default keywords = the legacy `xxdq + mode` pair. Specific
        // blocks (notably the selector tool) pass extra Chinese / English
        // search terms so admins can find them in the inserter without
        // remembering the exact title.
        $merged_keywords = $keywords !== null
            ? array_values(array_unique(array_merge(['xxdq', $mode], $keywords)))
            : ['xxdq', $mode];

        register_block_type($block_name, [
            'api_version'     => 2,
            'title'           => $title,
            'description'     => sprintf('XXDQ %s block', $title),
            'category'        => 'xxdq',
            'icon'            => $mode === 'dynamic' ? 'update' : 'admin-generic',
            'keywords'        => $merged_keywords,
            'textdomain'      => 'xxdq',
            'attributes'      => $attributes,
            'supports'        => [
                'align'           => false,
                'anchor'          => true,
                'customClassName' => true,
                'html'            => false,
                'reusable'        => true,
            ],
            'render_callback' => function ($attributes, $content, $block) use ($method) {
                $post_id = is_object($block) && isset($block->context['postId'])
                    ? (int) $block->context['postId']
                    : get_the_ID();
                XXDQ_Blocks_Render::$current_attrs = is_array($attributes) ? $attributes : [];
                $acf_block = (object) ['name' => str_replace('xxdq/', 'acf/', $block->name)];
                return XXDQ_Blocks_Render::$method($acf_block, $content, false, $post_id);
            },
        ]);
    }

    public function editor_assets() {
        $url = plugin_dir_url(__FILE__);
        // Modern dependency list: `wp-editor` is deprecated, replaced by
        // `wp-block-editor`. We also need `wp-components` (for PanelBody /
        // TextControl), `wp-hooks` (for addFilter), and `wp-i18n` (for __).
        // Without the new deps, the Inspector panel in editor.js silently
        // does nothing because every component it imports is undefined.
        // Bumped to 2.2.0 in case the previous deploy's editor.js was
        // cached in the user's browser — different ver= query string
        // forces a fresh fetch.
        wp_enqueue_style('xxdq-blocks-editor', $url . '../assets/editor.css', [], '2.2.0');
        wp_enqueue_script(
            'xxdq-blocks-editor',
            $url . '../assets/editor.js',
            ['wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-hooks', 'wp-i18n'],
            '2.2.0',
            true
        );
    }
}
