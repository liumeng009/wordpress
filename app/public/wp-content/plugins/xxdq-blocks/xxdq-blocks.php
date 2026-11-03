<?php
/**
 * Plugin Name: XXDQ Blocks — Reusable ACF Blocks
 * Description: Page-builder blocks for XXDQ site. Replaces hardcoded shortcode-based page templates with Gutenberg blocks that admins can rearrange.
 * Version: 2.2.0
 * Author: XXDQ
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: xxdq-blocks
 */

if (!defined('ABSPATH')) exit;

define('XXDQ_BLOCKS_DIR', plugin_dir_path(__FILE__));
define('XXDQ_BLOCKS_URL', plugin_dir_url(__FILE__));
define('XXDQ_BLOCKS_VERSION', '2.0.1');

require_once XXDQ_BLOCKS_DIR . 'includes/class-blocks-registry.php';
require_once XXDQ_BLOCKS_DIR . 'includes/class-blocks-render.php';
require_once XXDQ_BLOCKS_DIR . 'includes/class-blocks-inquiry.php';
require_once XXDQ_BLOCKS_DIR . 'includes/class-blocks-term-meta.php';

// Phase 9: register News CPT (only useful if WP is at least 5.0)
require_once XXDQ_BLOCKS_DIR . 'includes/cpts.php';

// Phase 9: ACF Options Page + admin-managed fields (Phase 9 site-wide
// content like hero slider, app fields, news settings, company info,
// footer, copyright). Only loaded when ACF is active so non-ACF installs
// keep working.
if (function_exists('acf_add_local_field_group')) {
    require_once XXDQ_BLOCKS_DIR . 'admin/options-page.php';
}

XXDQ_Blocks_Registry::instance();
XXDQ_Blocks_Render::instance();
XXDQ_Blocks_Inquiry::instance();
