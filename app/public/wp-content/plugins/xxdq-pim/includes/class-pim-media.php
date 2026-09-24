<?php
/**
 * Media handling: attach images and PDFs to products.
 *
 * Filename convention (filename without extension = product model):
 *   MODEL.jpg           → featured image (post thumbnail)
 *   MODEL-tech.jpg      → ACF tech_image field
 *   MODEL-spec.pdf      → ACF attachment field (priority order: spec > cert > install)
 *   MODEL-cert.pdf
 *   MODEL-install.pdf
 *
 * Matching is case-insensitive. Non-matching files are skipped with a warning.
 */

if (!defined('ABSPATH')) exit;

class XXDQ_PIM_Media {

    /** Scan a directory for files keyed by lowercase model + suffix. */
    public static function scan_dir($dir, $extensions) {
        if (!is_dir($dir)) return [];
        $out = [];
        $exts = array_map('strtolower', $extensions);
        $iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS));
        foreach ($iter as $file) {
            if (!$file->isFile()) continue;
            $ext = strtolower($file->getExtension());
            if (!in_array($ext, $exts, true)) continue;
            $base = pathinfo($file->getFilename(), PATHINFO_FILENAME);  // e.g. "DT-25-tech"
            $out[$base] = $file->getPathname();
        }
        return $out;
    }

    /**
     * Find files for a given model.
     *
     * @return array {
     *   'main' => 'path/to/DT-25.jpg' | null,
     *   'tech' => 'path/to/DT-25-tech.jpg' | null,
     *   'pdfs' => ['spec' => 'path', 'cert' => 'path', ...]  // bare filenames allowed too
     * }
     */
    public static function find_for_model($model, $image_index, $pdf_index) {
        $model_norm = strtolower(trim($model));
        $result = ['main' => null, 'tech' => null, 'pdfs' => []];

        foreach ($image_index as $base => $path) {
            $base_lower = strtolower($base);
            if ($base_lower === $model_norm) {
                $result['main'] = $path;
            } elseif ($base_lower === $model_norm . '-tech') {
                $result['tech'] = $path;
            }
        }

        foreach ($pdf_index as $base => $path) {
            $base_lower = strtolower($base);
            // Matches: DT-25, DT-25-spec, DT-25-cert, DT-25-install, etc.
            if ($base_lower === $model_norm) {
                $result['pdfs']['main'] = $path;
                continue;
            }
            if (strpos($base_lower, $model_norm . '-') === 0) {
                $suffix = substr($base_lower, strlen($model_norm) + 1);
                $result['pdfs'][$suffix] = $path;
            }
        }
        return $result;
    }

    /** Pick the "primary" PDF — spec > cert > install > main > first available. */
    public static function pick_primary_pdf($pdfs) {
        foreach (['spec', 'cert', 'install', 'manual', 'datasheet', 'main'] as $pref) {
            if (!empty($pdfs[$pref])) return $pdfs[$pref];
        }
        $first = reset($pdfs);
        return $first ?: null;
    }

    /**
     * Sideload an image file into WP Media Library and return attachment ID.
     *
     * @param string $file_path  absolute path
     * @param int    $parent_post_id  attach as featured if > 0
     * @param string $title
     * @return int attachment ID, or 0 on failure
     */
    public static function sideload_image($file_path, $parent_post_id = 0, $title = '') {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $upload = wp_upload_dir();
        if (!empty($upload['error'])) {
            return new WP_Error('upload_dir_error', $upload['error']);
        }

        $filename = basename($file_path);
        $target = $upload['path'] . '/' . wp_unique_filename($upload['path'], $filename);

        if (!copy($file_path, $target)) {
            return new WP_Error('copy_failed', "Failed to copy {$file_path}");
        }

        $wp_filetype = wp_check_filetype($target);
        $attachment = [
            'guid'           => $upload['url'] . '/' . basename($target),
            'post_mime_type' => $wp_filetype['type'],
            'post_title'     => $title ?: pathinfo($filename, PATHINFO_FILENAME),
            'post_content'   => '',
            'post_status'    => 'inherit',
        ];
        $attach_id = wp_insert_attachment($attachment, $target, $parent_post_id);
        if (is_wp_error($attach_id)) return $attach_id;

        $attach_data = wp_generate_attachment_metadata($attach_id, $target);
        wp_update_attachment_metadata($attach_id, $attach_data);

        return $attach_id;
    }

    /** Same as sideload_image but for any file type (used for PDFs). */
    public static function sideload_file($file_path, $parent_post_id = 0, $title = '') {
        // wp_handle_sideload handles all file types via sideload_image; reuse it.
        return self::sideload_image($file_path, $parent_post_id, $title);
    }
}
