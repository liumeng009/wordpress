<?php
/**
 * Plugin Name: XXDQ PIM — Product Pack Import/Export
 * Description: Bulk product information management. Import/export products + images + PDFs as a single ZIP pack. All pages auto-sync via CPT.
 * Version: 1.0.0
 * Author: XXDQ
 * Requires Plugins: xxdq-products
 * Text Domain: xxdq-pim
 */

if (!defined('ABSPATH')) exit;

/* ── Constants ─────────────────────────────────────────────── */
define('XXDQ_PIM_DIR', plugin_dir_path(__FILE__));
define('XXDQ_PIM_URL', plugin_dir_url(__FILE__));
define('XXDQ_PIM_VERSION', '1.0.0');
define('XXDQ_PIM_CAP', 'manage_options');

/* ── Bootstrap ─────────────────────────────────────────────── */
require_once XXDQ_PIM_DIR . 'includes/class-pim-importer.php';
require_once XXDQ_PIM_DIR . 'includes/class-pim-exporter.php';
require_once XXDQ_PIM_DIR . 'includes/class-pim-csv.php';
require_once XXDQ_PIM_DIR . 'includes/class-pim-media.php';
require_once XXDQ_PIM_DIR . 'includes/class-pim-admin.php';
/* ACF field groups are registered in xxdq-theme plugin (reliable load order).
   Include the file only for the PDF-render helper class: */
require_once XXDQ_PIM_DIR . 'includes/class-pim-render.php';

XXDQ_PIM_Admin::instance();
XXDQ_PIM_Render::instance();
