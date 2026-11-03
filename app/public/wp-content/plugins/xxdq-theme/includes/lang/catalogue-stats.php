<?php
/**
 * XXDQ — catalogue statistics.
 *
 * WHY THIS EXISTS
 * ---------------
 * The marketing copy used to hardcode the catalogue size ("500+ 规格",
 * "600+ Models"). Those numbers drifted away from reality: the live
 * catalogue holds 297 products across 151 distinct model codes, while
 * the pages still claimed 500+ and 600+. A visitor who counts the
 * products immediately loses trust — and for a B2B site that is the
 * one thing you cannot afford.
 *
 * These helpers compute the numbers from the database so the copy can
 * never drift again. They are cached per request.
 *
 * @package XXDQ
 */

if (!defined('ABSPATH')) exit;

/**
 * The catalogue statistics for the current language.
 *
 * @return array{
 *   products:int,      Number of published products.
 *   models:int,        Number of distinct model codes.
 *   categories:int,    Number of product categories.
 *   models_label:string, "151 Models" / "151 规格".
 *   products_label:string, "297 Products" / "297 规格".
 *   products_kw:string, "297+ Products" / "297+ 规格".
 * }
 */
function xxdq_catalogue_stats() {
    static $stats = null;
    if ($stats !== null) {
        return $stats;
    }

    global $wpdb;

    $is_en = function_exists('xxdq_is_en') && xxdq_is_en();

    /* Published products of the current language. */
    if ($is_en) {
        $products = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} p
             JOIN {$wpdb->postmeta} m ON m.post_id=p.ID AND m.meta_key='_xxdq_lang' AND m.meta_value='en_US'
             WHERE p.post_type='product' AND p.post_status='publish'"
        );
    } else {
        $products = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} m ON m.post_id=p.ID AND m.meta_key='_xxdq_lang'
             WHERE p.post_type='product' AND p.post_status='publish' AND m.post_id IS NULL"
        );
    }

    /* Distinct model codes — parsed from the product title, because the
     * `model` meta holds the Chinese TYPE description rather than a
     * model number on most products. */
    $models = array();
    if ($is_en) {
        $titles = $wpdb->get_col(
            "SELECT p.post_title FROM {$wpdb->posts} p
             JOIN {$wpdb->postmeta} m ON m.post_id=p.ID AND m.meta_key='_xxdq_lang' AND m.meta_value='en_US'
             WHERE p.post_type='product' AND p.post_status='publish'"
        );
    } else {
        $titles = $wpdb->get_col(
            "SELECT p.post_title FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} m ON m.post_id=p.ID AND m.meta_key='_xxdq_lang'
             WHERE p.post_type='product' AND p.post_status='publish' AND m.post_id IS NULL"
        );
    }
    foreach ($titles as $title) {
        if (preg_match('/^([A-Z]{1,5}[-–]?[0-9A-Z\.]*)/u', (string) $title, $mm)) {
            $models[strtoupper($mm[1])] = true;
        }
    }
    $model_count = count($models);

    /* Product categories of the current language. */
    if (function_exists('xxdq_current_lang_term_ids')) {
        $categories = count(xxdq_current_lang_term_ids());
    } else {
        $categories = 0;
    }

    $stats = [
        'products'        => $products,
        'models'          => $model_count,
        'categories'      => $categories,
        'models_label'    => $is_en
            ? sprintf('%d Models', $model_count)
            : sprintf('%d 规格', $model_count),
        'products_label'  => $is_en
            ? sprintf('%d Products', $products)
            : sprintf('%d 规格', $products),
        'products_kw'     => $is_en
            ? sprintf('%d+ Products', $products)
            : sprintf('%d+ 规格', $products),
    ];

    return $stats;
}

/**
 * The catalogue size as it appears in marketing copy.
 *
 * @return string e.g. "297+" / "297+".
 */
function xxdq_catalogue_count() {
    $stats = xxdq_catalogue_stats();
    return (string) $stats['products'];
}

/**
 * The catalogue size with the "+" suffix, for use inside a sentence.
 *
 * @param bool $models Use the model count instead of the product count.
 * @return string
 */
function xxdq_catalogue_kw($models = false) {
    $stats = xxdq_catalogue_stats();
    if ($models) {
        return (string) $stats['models'];
    }
    return (string) $stats['products'];
}
