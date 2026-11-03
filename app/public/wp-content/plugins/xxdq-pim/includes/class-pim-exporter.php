<?php
/**
 * Pack Exporter: bundle products + media into a downloadable ZIP.
 *
 * Two entry points:
 *  - bulk($filter_args): stream a ZIP of many products
 *  - single($post_id):    stream a ZIP of one product
 */

if (!defined('ABSPATH')) exit;

class XXDQ_PIM_Exporter {

    /** @var ZipArchive */
    private $zip;
    private $zip_path;

    /**
     * Bulk export. Filters match WP_Query args + tax_query.
     *
     * @param array $args {
     *   'category' => int|array|null,    // term_id(s) in product_category
     *   'series'   => int|array|null,
     *   'status'   => 'publish'|array,
     *   'include_media' => bool,         // true = include images + PDFs (heavier)
     *   'format'   => 'pack'|'csv'|'json',
     * }
     * @return string Path to generated ZIP (caller should send file then delete).
     */
    public function bulk($args = []) {
        $defaults = [
            'category'       => null,
            'series'         => null,
            'status'         => 'publish',
            'include_media'  => true,
            'format'         => 'pack',
        ];
        $args = array_merge($defaults, $args);

        $query_args = [
            'post_type'      => 'product',
            'post_status'    => $args['status'],
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ];
        $tax_query = [];
        if ($args['category']) {
            $tax_query[] = [
                'taxonomy' => 'product_category',
                'field'    => 'term_id',
                'terms'    => (array) $args['category'],
            ];
        }
        /* product_series removed: sub-filtering now done via product_category sub-terms. */
        if ($tax_query) {
            $query_args['tax_query'] = $tax_query;
        }

        $products = get_posts($query_args);

        $this->init_zip();
        $this->write_manifest($products, $args);

        if ($args['format'] === 'pack' || $args['format'] === 'csv') {
            $this->write_csv($products);
        }
        if ($args['format'] === 'json') {
            $this->write_json($products);
        }

        if ($args['include_media'] && $args['format'] === 'pack') {
            $this->write_media($products);
        }

        $this->zip->close();
        return $this->zip_path;
    }

    /** Single product export. */
    public function single($post_id) {
        return $this->bulk([
            'post__in'       => [$post_id],
            'include_media'  => true,
            'format'         => 'pack',
        ]);
    }

    /* ── Internals ─────────────────────────────────────────── */

    private function init_zip() {
        $upload = wp_upload_dir();
        $base = $upload['basedir'] . '/xxdq-pim-exports';
        wp_mkdir_p($base);
        $this->zip_path = $base . '/xxdq-pack-' . date('Ymd-His') . '-' . wp_generate_password(6, false) . '.zip';

        $this->zip = new ZipArchive();
        $this->zip->open($this->zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    }

    private function write_manifest($products, $args) {
        $manifest = [
            'version'       => '1.0',
            'schema'        => 'xxdq/v1',
            'site'          => home_url(),
            'exported_at'   => current_time('c'),
            'product_count' => count($products),
            'filter'        => [
                'category' => $args['category'],
                'series'   => $args['series'],
                'status'   => $args['status'],
            ],
        ];
        $this->zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    private function write_csv($products) {
        $header = XXDQ_PIM_CSV::header();
        $fh = fopen('php://temp', 'r+');
        fputcsv($fh, $header);

        foreach ($products as $p) {
            $row = [];
            $cats = wp_get_post_terms($p->ID, 'product_category', ['fields' => 'names']);
            $series = wp_get_post_terms($p->ID, 'product_series', ['fields' => 'names']);

            foreach ($header as $col) {
                $map = XXDQ_PIM_CSV::column_map()[$col];
                switch ($map['target']) {
                    case 'acf':
                        $row[] = (string) get_field($map['key'], $p->ID);
                        break;
                    case 'taxonomy:product_category':
                        $row[] = implode('|', $cats);
                        break;
                    case 'taxonomy:product_series':
                        $row[] = implode('|', $series);
                        break;
                    case 'post':
                        $row[] = $p->post_status;
                        break;
                }
            }
            fputcsv($fh, $row);
        }
        rewind($fh);
        $this->zip->addFromString('products.csv', stream_get_contents($fh));
        fclose($fh);
    }

    private function write_json($products) {
        $out = [];
        foreach ($products as $p) {
            $out[] = $this->product_to_array($p);
        }
        $this->zip->addFromString('products.json', json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    private function write_media($products) {
        $image_zip_added = false;
        $pdf_zip_added = false;

        foreach ($products as $p) {
            $model = get_field('model', $p->ID) ?: $p->post_title;

            // Featured image
            if (has_post_thumbnail($p->ID)) {
                $thumb_path = get_attached_file(get_post_thumbnail_id($p->ID));
                if ($thumb_path && file_exists($thumb_path)) {
                    $this->zip->addFile($thumb_path, 'images/' . sanitize_file_name(pathinfo($thumb_path, PATHINFO_FILENAME) . '-' . $model . '.' . pathinfo($thumb_path, PATHINFO_EXTENSION)));
                    $image_zip_added = true;
                }
            }
            // Tech image
            $tech = get_field('tech_image', $p->ID);
            if ($tech && !empty($tech['ID'])) {
                $tech_path = get_attached_file($tech['ID']);
                if ($tech_path && file_exists($tech_path)) {
                    $this->zip->addFile($tech_path, 'images/' . sanitize_file_name($model . '-tech.' . pathinfo($tech_path, PATHINFO_EXTENSION)));
                    $image_zip_added = true;
                }
            }
            // PDF attachment
            $att = get_field('attachment', $p->ID);
            if ($att && !empty($att['ID'])) {
                $att_path = get_attached_file($att['ID']);
                if ($att_path && file_exists($att_path)) {
                    $this->zip->addFile($att_path, 'pdfs/' . sanitize_file_name($model . '-spec.' . pathinfo($att_path, PATHINFO_EXTENSION)));
                    $pdf_zip_added = true;
                }
            }
        }

        // Add README explaining folder purpose
        $readme = "# Pack Structure\n\nimages/ — product photos named as MODEL.jpg or MODEL-tech.jpg\npdfs/   — datasheets named as MODEL-spec.pdf / MODEL-cert.pdf / MODEL-install.pdf\n\nRe-import this pack from WP Admin → Products → Import Pack to restore.";
        $this->zip->addFromString('README.txt', $readme);
    }

    private function product_to_array($post) {
        $acf_keys = ['model', 'wire_range', 'hole_size', 'material', 'finish', 'color_code', 'voltage', 'temperature', 'certification', 'package', 'short_desc'];
        $row = [
            'ID'      => $post->ID,
            'title'   => $post->post_title,
            'status'  => $post->post_status,
            'slug'    => $post->post_name,
        ];
        foreach ($acf_keys as $k) {
            $row[$k] = get_field($k, $post->ID);
        }
        $row['categories'] = wp_get_post_terms($post->ID, 'product_category', ['fields' => 'names']);
        /* series removed — sub-grouping now comes from hierarchical product_category. */
        $row['image_url']  = get_the_post_thumbnail_url($post->ID, 'full');
        $row['tech_image_url'] = wp_get_attachment_image_url(get_field('tech_image', $post->ID), 'full');
        $att = get_field('attachment', $post->ID);
        $row['pdf_url'] = $att ? wp_get_attachment_url($att['ID'] ?? 0) : null;
        return $row;
    }
}
