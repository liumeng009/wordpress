<?php
/**
 * CSV reader/writer for product metadata.
 *
 * Header row is required. Columns map to ACF field keys plus taxonomy columns.
 */

if (!defined('ABSPATH')) exit;

class XXDQ_PIM_CSV {

    /** Column → (target, type). target: 'acf'|'taxonomy:product_category'|'taxonomy:product_series'|'post' */
    public static function column_map() {
        return [
            'model'         => ['target' => 'acf', 'key' => 'model',         'required' => true],
            'wire_range'    => ['target' => 'acf', 'key' => 'wire_range'],
            'hole_size'     => ['target' => 'acf', 'key' => 'hole_size'],
            'material'      => ['target' => 'acf', 'key' => 'material'],
            'finish'        => ['target' => 'acf', 'key' => 'finish'],
            'color_code'    => ['target' => 'acf', 'key' => 'color_code'],
            'voltage'       => ['target' => 'acf', 'key' => 'voltage'],
            'temperature'   => ['target' => 'acf', 'key' => 'temperature'],
            'certification' => ['target' => 'acf', 'key' => 'certification'],
            'package'       => ['target' => 'acf', 'key' => 'package'],
            'short_desc'    => ['target' => 'acf', 'key' => 'short_desc'],
            'category'      => ['target' => 'taxonomy:product_category', 'sep' => '|'],
            'series'        => ['target' => 'taxonomy:product_series',   'sep' => '|'],
            'status'        => ['target' => 'post', 'key' => 'post_status'],
        ];
    }

    /** Parse a CSV file path into array of row-arrays keyed by header. */
    public static function parse_file($path) {
        if (!file_exists($path)) {
            throw new RuntimeException('CSV file not found: ' . $path);
        }
        $fh = fopen($path, 'r');
        if (!$fh) {
            throw new RuntimeException('Cannot open CSV: ' . $path);
        }

        // Read BOM if present
        $bom = fread($fh, 3);
        if ($bom !== "\xEF\xBB\xBF") rewind($fh);

        $header = fgetcsv($fh);
        if (!$header) {
            fclose($fh);
            throw new RuntimeException('CSV is empty or invalid');
        }

        // Normalize header: trim + strip BOM
        $header = array_map(function($h) {
            return trim(preg_replace('/^\xEF\xBB\xBF/', '', $h));
        }, $header);

        $rows = [];
        while (($row = fgetcsv($fh)) !== false) {
            if (count($row) === 1 && trim($row[0]) === '') continue;  // skip blank lines
            if (count($row) !== count($header)) {
                throw new RuntimeException('CSV row column count mismatch: ' . implode(',', $row));
            }
            $rows[] = array_combine($header, $row);
        }
        fclose($fh);
        return $rows;
    }

    /** Convert an associative row to a writeable CSV line (used by exporter). */
    public static function write_row($fh, $row) {
        fputcsv($fh, $row);
    }

    /** Get the canonical header list. */
    public static function header() {
        return array_keys(self::column_map());
    }
}
