<?php
/**
 * Pack Importer: orchestrator that turns a ZIP pack into product rows.
 *
 * Usage:
 *   $result = XXDQ_PIM_Importer::instance()->import($zip_path, [
 *     'mode'        => 'insert' | 'update' | 'upsert',
 *     'dry_run'     => bool,
 *     'on_progress' => function($i, $total, $row) {},
 *   ]);
 */

if (!defined('ABSPATH')) exit;

class XXDQ_PIM_Importer {

    private static $instance;
    private $log = [];

    public static function instance() {
        if (!self::$instance) self::$instance = new self();
        return self::$instance;
    }

    /**
     * Import a pack ZIP.
     *
     * @param string $zip_path
     * @param array  $opts { mode:'upsert'|'insert'|'update', dry_run:bool, callback: callable }
     * @return array { success:int, failed:int, skipped:int, log:[] }
     */
    public function import($zip_path, $opts = []) {
        $defaults = [
            'mode'    => 'upsert',
            'dry_run' => false,
            'on_row'  => null,  // function($index, $total, $row_result)
        ];
        $opts = array_merge($defaults, $opts);

        $this->log = [];

        if (!class_exists('ZipArchive')) {
            return $this->fail('PHP ZipArchive extension is required.');
        }
        if (!file_exists($zip_path)) {
            return $this->fail('File not found: ' . $zip_path);
        }

        $extract_dir = $this->make_temp_dir();
        $zip = new ZipArchive();
        if ($zip->open($zip_path) !== true) {
            $this->cleanup_temp($extract_dir);
            return $this->fail('Cannot open ZIP file (corrupted or password-protected?)');
        }
        $zip->extractTo($extract_dir);
        $zip->close();

        // Find products.csv (case-insensitive, root or one level deep)
        $csv_path = $this->find_file($extract_dir, 'products.csv');
        if (!$csv_path) {
            $this->cleanup_temp($extract_dir);
            return $this->fail('Missing products.csv in pack root.');
        }

        try {
            $rows = XXDQ_PIM_CSV::parse_file($csv_path);
        } catch (Exception $e) {
            $this->cleanup_temp($extract_dir);
            return $this->fail('CSV parse error: ' . $e->getMessage());
        }

        // Build indexes for media lookup
        $images_dir = $this->find_dir($extract_dir, 'images');
        $pdfs_dir   = $this->find_dir($extract_dir, ['pdfs', 'pdf', 'documents', 'docs']);

        $image_index = $images_dir ? XXDQ_PIM_Media::scan_dir($images_dir, ['jpg', 'jpeg', 'png', 'webp', 'gif']) : [];
        $pdf_index   = $pdfs_dir   ? XXDQ_PIM_Media::scan_dir($pdfs_dir,   ['pdf']) : [];

        $stats = ['success' => 0, 'failed' => 0, 'skipped' => 0, 'updated' => 0, 'inserted' => 0];

        foreach ($rows as $i => $row) {
            $result = $this->process_row($row, $image_index, $pdf_index, $opts, $extract_dir);
            $stats[$result['status']] = ($stats[$result['status']] ?? 0) + 1;
            $this->log[] = $result;

            if (is_callable($opts['on_row'])) {
                call_user_func($opts['on_row'], $i + 1, count($rows), $result);
            }
        }

        $this->cleanup_temp($extract_dir);

        return [
            'success'   => $stats['success'] ?? 0,
            'updated'   => $stats['updated'] ?? 0,
            'inserted'  => $stats['inserted'] ?? 0,
            'failed'    => $stats['failed'] ?? 0,
            'skipped'   => $stats['skipped'] ?? 0,
            'dry_run'   => $opts['dry_run'],
            'total'     => count($rows),
            'log'       => $this->log,
        ];
    }

    /** Process a single CSV row. */
    private function process_row($row, $image_index, $pdf_index, $opts, $extract_dir) {
        $model = trim($row['model'] ?? '');
        if ($model === '') {
            return $this->row_result('failed', '', 'Missing model column');
        }

        // Resolve mode-specific action
        $existing = $this->find_existing_product($model);
        if ($existing && $opts['mode'] === 'insert') {
            return $this->row_result('skipped', $model, "Already exists (mode=insert); post_id={$existing->ID}");
        }
        if (!$existing && $opts['mode'] === 'update') {
            return $this->row_result('skipped', $model, 'Not found (mode=update)');
        }

        $action = $existing ? 'updated' : 'inserted';
        $post_id = $existing ? $existing->ID : null;

        // Validate ACF map fields
        $col_map = XXDQ_PIM_CSV::column_map();

        // Handle taxonomy resolution (create terms if missing)
        $tax_input = [];
        if (!empty($row['category'])) {
            $tax_input['product_category'] = $this->ensure_terms(
                $row['category'], 'product_category'
            );
        }
        if (!empty($row['series'])) {
            $tax_input['product_series'] = $this->ensure_terms(
                $row['series'], 'product_series'
            );
        }

        if ($opts['dry_run']) {
            return $this->row_result(
                'success',
                $model,
                "[dry-run] Would {$action} with " . count($image_index) . " images, " . count($pdf_index) . " PDFs indexed",
                $post_id,
                $action
            );
        }

        // Build post payload
        $post_args = [
            'post_type'    => 'product',
            'post_title'   => $model,
            'post_name'    => sanitize_title($model),
            'post_status'  => in_array($row['status'] ?? '', ['publish', 'draft', 'pending'], true) ? $row['status'] : 'publish',
            'post_content' => '',
        ];
        if ($post_id) {
            $post_args['ID'] = $post_id;
            $post_id = wp_update_post($post_args, true);
        } else {
            $post_id = wp_insert_post($post_args, true);
        }
        if (is_wp_error($post_id)) {
            return $this->row_result('failed', $model, 'wp_insert_post: ' . $post_id->get_error_message());
        }

        // Write ACF fields
        foreach ($col_map as $col => $def) {
            if ($def['target'] !== 'acf') continue;
            $val = $row[$col] ?? '';
            update_field($def['key'], $val, $post_id);
        }

        // Write taxonomies
        foreach ($tax_input as $tax => $term_ids) {
            wp_set_object_terms($post_id, $term_ids, $tax);
        }

        // Media: images + PDFs
        // File naming convention:
        //   MODEL.jpg       → 实物图 (real_image) + sets featured image for back-compat
        //   MODEL-tech.jpg  → 描述图 (tech_image)
        //   MODEL-spec.pdf  → 资料 PDF (attachment)
        $media = XXDQ_PIM_Media::find_for_model($model, $image_index, $pdf_index);

        if ($media['main']) {
            $real_id = XXDQ_PIM_Media::sideload_image($media['main'], $post_id, $model);
            if (!is_wp_error($real_id)) {
                // 实物图 → used by product center card preview
                update_field('real_image', $real_id, $post_id);
                // Also set featured image for back-compat with anything reading WP thumbnail
                set_post_thumbnail($post_id, $real_id);
            }
        }
        if ($media['tech']) {
            $tech_id = XXDQ_PIM_Media::sideload_image($media['tech'], $post_id, "{$model}-tech");
            if (!is_wp_error($tech_id)) {
                // 描述图 → used on detail page (spec sheet / manual screenshot)
                update_field('tech_image', $tech_id, $post_id);
            }
        }
        if (!empty($media['pdfs'])) {
            $primary_pdf = XXDQ_PIM_Media::pick_primary_pdf($media['pdfs']);
            if ($primary_pdf) {
                $pdf_id = XXDQ_PIM_Media::sideload_file($primary_pdf, $post_id, pathinfo($primary_pdf, PATHINFO_FILENAME));
                if (!is_wp_error($pdf_id)) {
                    update_field('attachment', $pdf_id, $post_id);
                }
            }
        }

        return $this->row_result('success', $model, "{$action} post_id={$post_id}", $post_id, $action);
    }

    /* ── Helpers ───────────────────────────────────────────── */

    private function find_existing_product($model) {
        $existing = get_posts([
            'post_type'      => 'product',
            'name'           => sanitize_title($model),
            'posts_per_page' => 1,
            'post_status'    => 'any',
            'fields'         => 'ids',
        ]);
        if ($existing) {
            $post = get_post($existing[0]);
            return $post;
        }
        // Fallback by ACF model field (handles if slug differs)
        $existing = get_posts([
            'post_type'      => 'product',
            'posts_per_page' => 1,
            'post_status'    => 'any',
            'fields'         => 'ids',
            'meta_query'     => [
                ['key' => 'model', 'value' => $model, 'compare' => '='],
            ],
        ]);
        if ($existing) return get_post($existing[0]);
        return null;
    }

    private function ensure_terms($raw_value, $taxonomy) {
        $names = array_filter(array_map('trim', explode('|', $raw_value)));
        $ids = [];
        foreach ($names as $name) {
            $term = term_exists($name, $taxonomy);
            if (!$term) {
                $term = wp_insert_term($name, $taxonomy);
            }
            if (!is_wp_error($term)) {
                $ids[] = is_array($term) ? $term['term_id'] : $term;
            }
        }
        return $ids;
    }

    private function row_result($status, $model, $message, $post_id = 0, $action = '') {
        return [
            'status'  => $status,
            'model'   => $model,
            'message' => $message,
            'post_id' => $post_id,
            'action'  => $action,
        ];
    }

    private function fail($msg) {
        return [
            'success'  => 0, 'failed' => 1, 'skipped' => 0,
            'updated'  => 0, 'inserted' => 0,
            'log'      => [['status' => 'failed', 'model' => '', 'message' => $msg]],
        ];
    }

    private function make_temp_dir() {
        $upload = wp_upload_dir();
        $base = $upload['basedir'] . '/xxdq-pim-temp';
        wp_mkdir_p($base);
        $dir = $base . '/' . uniqid('pack_', true);
        wp_mkdir_p($dir);
        return $dir;
    }

    private function cleanup_temp($dir) {
        if (!is_dir($dir)) return;
        $iter = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iter as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        return rmdir($dir);
    }

    private function find_file($root, $filename) {
        // Case-insensitive: try direct then scandir
        if (file_exists($root . '/' . $filename)) return $root . '/' . $filename;
        $entries = @scandir($root);
        if (!$entries) return null;
        foreach ($entries as $entry) {
            if (strcasecmp($entry, $filename) === 0) {
                $candidate = $root . '/' . $entry;
                if (is_file($candidate)) return $candidate;
            }
            // One level deep
            $sub = $root . '/' . $entry;
            if (is_dir($sub) && !in_array($entry, ['.', '..'])) {
                $inner = $this->find_file($sub, $filename);
                if ($inner) return $inner;
            }
        }
        return null;
    }

    private function find_dir($root, $name) {
        if (is_string($name)) $name = [$name];
        $entries = @scandir($root);
        if (!$entries) return null;
        foreach ($entries as $entry) {
            $sub = $root . '/' . $entry;
            if (!is_dir($sub)) continue;
            foreach ($name as $n) {
                if (strcasecmp($entry, $n) === 0) return $sub;
            }
            // One level deep
            $inner = $this->find_dir($sub, $name);
            if ($inner) return $inner;
        }
        return null;
    }
}
