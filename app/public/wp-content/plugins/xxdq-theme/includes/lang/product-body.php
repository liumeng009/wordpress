<?php
/**
 * XXDQ i18n — English product body generator.
 *
 * WHY A DEDICATED GENERATOR
 * --------------------------
 * The Chinese product body is a four-paragraph SEO template generated
 * from the catalogue meta (the PIM writes it on import):
 *
 *   1. "<TITLE> — 乐清市耕文电气专业生产<TITLE>（<系列>），源头工厂直供，国标尺寸。
 *      <TITLE>压接牢固、导电稳定、耐腐蚀，是工业接线首选。本产品参数：…"
 *   2. "<TITLE>适用于配电柜、电气控制柜…等多种接线场景，…"
 *   3. "<TITLE>通过CE/RoHS认证，工厂直发，1小时专属报价…"
 *   4. (optional) "<TITLE>批量采购优惠，…24小时内出货…"
 *
 * The existing translator could only translate FRAGMENTS — the Chinese
 * sentence skeletons have no dictionary equivalent, so the English pages
 * ended up with an empty body (post_content = '').
 *
 * This module builds the English body from the SAME template, in
 * English, filling it from the ENGLISH spec values. The result is
 * grammatical English written by a human author, not by a word
 * substitution pass — which is exactly what a foreign buyer needs to
 * read.
 *
 * The body is written as plain paragraphs (no block markup) because the
 * single-product template renders `the_content()` inside its own
 * "Product Details" section.
 *
 * @package XXDQ
 */

if (!defined('ABSPATH')) exit;

class XXDQ_I18n_Product_Body {

    /**
     * The product's English display name.
     *
     * @param int    $en_id English product ID.
     * @param string $title Optional explicit title. Used when the
     *                      English post does not exist yet (during the
     *                      automatic sync) — the English post's title
     *                      is the translated one, not the Chinese one.
     * @return string
     */
    private static function name($en_id, $title = '') {
        if ($title !== '') {
            return trim($title);
        }
        return trim((string) get_the_title($en_id));
    }

    /**
     * Read a spec value in English.
     *
     * Two situations are handled:
     *
     * 1. The English product exists — its `<field>_en` meta holds the
     *    translated value, and the language-neutral fields are copied
     *    from the Chinese side.
     * 2. The English product does not exist yet (during the automatic
     *    sync) — the value is translated from the Chinese meta on the
     *    fly.
     *
     * @param string $field Spec field.
     * @param int    $post_id Post to read.
     * @return string
     */
    private static function spec($field, $post_id, $from_zh = false) {
        /* Reading from the Chinese source — translate on the fly. */
        if ($from_zh) {
            $zh_val = trim((string) get_post_meta($post_id, $field, true));
            if ($zh_val === '') {
                return '';
            }
            $translated = xxdq_translate_spec_value($field, $zh_val);
            return $translated === null ? '' : $translated;
        }

        /* The English value, when it has already been written. */
        $val = trim((string) get_post_meta($post_id, $field . '_en', true));
        if ($val !== '') {
            return $val;
        }

        /* `model` is special: it holds the Chinese TYPE DESCRIPTION
         * rather than a model number, so the English product needs
         * its own `model_en` value. */
        if ($field === 'model') {
            $en_model = trim((string) get_post_meta($post_id, 'model_en', true));
            if ($en_model !== '') {
                return $en_model;
            }
        }

        /* Fall back to the Chinese value, translated. */
        $zh_val = trim((string) get_post_meta($post_id, $field, true));
        if ($zh_val === '') {
            return '';
        }

        $translated = xxdq_translate_spec_value($field, $zh_val);
        return $translated === null ? '' : $translated;
    }

    /**
     * The English product family ("Copper Lug", "Crimp Terminal", …).
     *
     * Taken from the product's English category so the body reads
     * naturally ("part of the Copper Lug range").
     *
     * @param int  $post_id Post to read.
     * @param bool $from_zh Read from the Chinese side and translate.
     * @return string
     */
    private static function family($post_id, $from_zh = false) {
        $terms = wp_get_post_terms($post_id, 'product_category', array('fields' => 'names'));
        if (is_wp_error($terms) || empty($terms)) {
            return '';
        }

        /* The most specific (last) category name. */
        $name = trim((string) end($terms));

        if (!$from_zh) {
            return $name;
        }

        /* Translate the Chinese category name. */
        $translated = xxdq_translate_cat_description($name);
        if (is_string($translated) && !preg_match('/[\x{4e00}-\x{9fff}]/u', $translated)) {
            return trim($translated);
        }
        return $name;
    }

    /**
     * Build the English body paragraphs.
     *
     * @param int    $en_id English product ID.
     * @param string $title Optional explicit English title.
     * @param int    $zh_id Optional Chinese source ID. When given, the
     *                     spec values are read from the Chinese post
     *                     and translated on the fly — this is the case
     *                     during the automatic sync, when the English
     *                     post does not exist yet.
     * @return string Paragraphs separated by a blank line.
     */
    public static function build($en_id, $title = '', $zh_id = 0) {
        $name = self::name($en_id, $title);
        if ($name === '') {
            return '';
        }

        /* Where the spec values come from. */
        $src = $zh_id ? $zh_id : $en_id;
        $from_zh = (bool) $zh_id;

        $model     = self::spec('model', $src, $from_zh);
        $wire      = self::spec('wire_range', $src, $from_zh);
        $hole      = self::spec('hole_size', $src, $from_zh);
        $material  = self::spec('material', $src, $from_zh);
        $finish    = self::spec('finish', $src, $from_zh);
        $color     = self::spec('color_code', $src, $from_zh);
        $voltage   = self::spec('voltage', $src, $from_zh);
        $temp      = self::spec('temperature', $src, $from_zh);
        $cert      = self::spec('certification', $src, $from_zh);
        $package   = self::spec('package', $src, $from_zh);
        $family    = self::family($src, $from_zh);

        /* ── Paragraph 1: what it is ───────────────────────── */
        $p1 = sprintf(
            '%s is manufactured directly by Gengwen Electric, a source factory in Yueqing, China specialising in electrical connectors and terminals. '
            . 'As part of our %s range, it is produced to national-standard dimensions and supplied straight from the factory, with no trading margin in between. '
            . 'The %s is engineered for a secure crimp, stable conductivity and long-term corrosion resistance, which makes it a dependable choice for industrial wiring.',
            $name,
            $family !== '' ? $family : 'electrical connection',
            $name
        );

        /* The parameter clause — only the specs we actually have. */
        $params = array();
        if ($model !== '')   { $params[] = 'Model: ' . $model; }
        if ($wire !== '')    { $params[] = 'Wire range: ' . $wire; }
        if ($hole !== '')    { $params[] = 'Stud size: ' . $hole; }
        if ($material !== '') { $params[] = 'Material: ' . $material; }
        if ($finish !== '')   { $params[] = 'Surface finish: ' . $finish; }
        if ($color !== '')    { $params[] = 'Insulation colour: ' . $color; }
        if ($voltage !== '')  { $params[] = 'Rated voltage: ' . $voltage; }
        if ($temp !== '')     { $params[] = 'Operating temperature: ' . $temp; }
        if ($cert !== '')     { $params[] = 'Certification: ' . $cert; }
        if ($package !== '')  { $params[] = 'Packing: ' . $package; }

        if (!empty($params)) {
            $p1 .= ' Specifications: ' . implode('; ', $params) . '.';
        }

        /* ── Paragraph 2: where it is used ─────────────────── */
        $p2 = sprintf(
            'The %s is widely used in distribution cabinets, electrical control panels, power distribution wiring, automotive harnesses, home appliances and automation equipment. '
            . 'It fits standard M3–M16 studs, and the most common specifications are kept in stock for immediate dispatch, so you can order the size you need without waiting for a production run.',
            $name
        );

        /* ── Paragraph 3: certification & commercial terms ─── */
        $p3 = sprintf(
            'All %s are tested for CE / RoHS compliance and ship with the full test documentation. '
            . 'Orders are dispatched directly from the factory, we reply to enquiries within one hour, and non-standard specifications can be made to order. '
            . 'As a direct manufacturer, Gengwen Electric can quote on OEM and ODM projects as well as single-box orders — contact us with your drawing or your required specification for a tailored quotation.',
            $name
        );

        $paras = array($p1, $p2, $p3);

        return implode("\n\n", $paras);
    }

    /**
     * Write the English body onto the English product.
     *
     * @param int $en_id English product ID.
     * @return bool True when the body was written.
     */
    public static function apply($en_id) {
        $body = self::build($en_id);
        if ($body === '') {
            return false;
        }

        $post = get_post($en_id);
        if (!$post || $post->post_content === $body) {
            return false;
        }

        wp_update_post(array(
            'ID'           => $en_id,
            'post_content' => $body,
        ));

        return true;
    }
}
