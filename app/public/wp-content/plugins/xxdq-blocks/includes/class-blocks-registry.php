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
        $this->reg('selector-tool',    'Selector Tool',    'dynamic');
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

    private function reg($name, $title, $mode) {
        $method = 'render_' . str_replace('-', '_', $name);
        $block_name = 'xxdq/' . $name;

        register_block_type($block_name, [
            'api_version'     => 2,
            'title'           => $title,
            'description'     => sprintf('XXDQ %s block', $title),
            'category'        => 'xxdq',
            'icon'            => $mode === 'dynamic' ? 'update' : 'admin-generic',
            'keywords'        => ['xxdq', $mode],
            'textdomain'      => 'xxdq',
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
        wp_enqueue_style('xxdq-blocks-editor', $url . '../assets/editor.css', [], '2.0.0');
        wp_enqueue_script('xxdq-blocks-editor', $url . '../assets/editor.js', ['wp-blocks', 'wp-element', 'wp-editor'], '2.0.0', true);
    }
}
