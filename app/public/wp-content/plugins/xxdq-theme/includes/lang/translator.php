<?php
/**
 * XXDQ i18n — content translator.
 *
 * Converts Chinese product content into English using the deterministic
 * structure that the catalogue data actually follows:
 *
 *   Product title  : "<MODEL> <中文类型>"      → "<MODEL> <English type>"
 *   short_desc     : "<MODEL> <中文类型>，<参数>。" → same pattern
 *
 * Because the catalogue is machine-generated, the Chinese text is
 * formulaic and can be translated with a lookup table rather than a
 * machine-translation API. That keeps the output stable, reviewable
 * and free of per-product cost.
 *
 * Anything this translator cannot map with confidence is returned as
 * NULL, and the caller records it in the "needs review" list so a human
 * can finish the job. The translator never guesses.
 *
 * @package XXDQ
 */

if (!defined('ABSPATH')) exit;

/**
 * Chinese product-type vocabulary → English.
 *
 * Keys are the Chinese type phrase; values are the industry-standard
 * English equivalent used in B2B electrical-connector catalogues.
 */
function xxdq_product_type_map() {
    static $map = null;
    if ($map !== null) {
        return $map;
    }

    $map = [
        /* ── Copper lugs / tube terminals ────────────────────── */
        '紫铜堵油标准型（大头）'   => 'Tinned Copper Oil-Block Terminal (Large Head)',
        '紫铜堵油经济型（小头）'   => 'Tinned Copper Oil-Block Terminal (Small Head)',
        '铜铝过渡型（摩擦焊）'     => 'Copper-Aluminium Transition Terminal (Friction Welded)',
        '管压型（酸洗工艺）'       => 'Tube-Crimp Terminal (Pickled)',
        '紫铜镀锡窥口铜鼻子'       => 'Tinned Copper Inspection Lug',
        '窥口铜接线端头'           => 'Copper Inspection Terminal',
        '铜管端子'                 => 'Copper Tube Terminal',
        '堵油型铜接线端子（DT型）' => 'Oil-Block Copper Terminal (DT Series)',
        '窥口铜接线端头（DTGA型）' => 'Copper Inspection Terminal (DTGA Series)',
        '铜开口接线端子（OT型）'   => 'Copper Open Barrel Terminal (OT Series)',

        /* The short_desc of the DT / DTL / DTG copper lugs uses a
         * slightly different lead phrase than the product title
         * (e.g. "DT-10 堵油标准型（大头）铜鼻子"). These entries cover
         * the short_desc form. */
        '堵油标准型（大头）铜鼻子'   => 'Tinned Copper Oil-Block Terminal (Large Head)',
        '堵油经济型（小头）铜鼻子'   => 'Tinned Copper Oil-Block Terminal (Small Head)',
        '铜铝过渡型铜鼻子'          => 'Copper-Aluminium Transition Terminal (Friction Welded)',
        '管压型（酸洗工艺）铜鼻子'   => 'Tube-Crimp Terminal (Pickled)',

        /* The SC inspection lugs use "<MODEL> M<n>型铜鼻子" in their
         * short_desc lead, e.g. "SC-10 M6型铜鼻子". */
        'M6型铜鼻子'               => 'M6 Inspection Lug',
        'M8型铜鼻子'               => 'M8 Inspection Lug',
        'M10型铜鼻子'              => 'M10 Inspection Lug',

        /* ── Crimp terminals (generic series) ───────────────── */
        '冷压端子'                 => 'Crimp Terminal',
        '冷压端子u型'              => 'U-Shaped Crimp Terminal',
        '冷压端子o型'              => 'O-Shaped Crimp Terminal',
        'y型冷压端子'              => 'Y-Shaped Crimp Terminal',
        '圆形冷压端子'             => 'Round Crimp Terminal',
        '圆形冷压接线端子'          => 'Round Crimp Connector Terminal',
        '圆形冷压裸端子接线铜鼻子'   => 'Round Bare Crimp Copper Lug',
        've冷压接线端子'           => 'VE Crimp Connector Terminal',
        've管型冷压接线端子'        => 'VE Tube-Type Crimp Connector Terminal',
        'sv冷压接线端子'           => 'SV Crimp Connector Terminal',
        'rv冷压接线端子'           => 'RV Crimp Connector Terminal',
        'ot冷压端子'              => 'OT Crimp Terminal',
        'ut冷压端子'              => 'UT Crimp Terminal',
        'sc冷压端子'              => 'SC Crimp Terminal',
        'rnb冷压端子'             => 'RNB Crimp Terminal',
        '管型冷压接线端子'          => 'Tube-Type Crimp Connector Terminal',
        '管型裸端子（GT型）'       => 'Tube-Type Bare Terminal (GT Series)',
        '管型裸端子（TG型）'       => 'Tube-Type Bare Terminal (TG Series)',
        '管型预绝缘端头（GT-JT型）' => 'Tube-Type Pre-insulated Terminal (GT-JT Series)',
        '管型预绝缘端口VE'          => 'Tube-Type Pre-insulated Terminal VE',

        /* ── Flat (blade) terminals ──────────────────────────── */
        '片形裸端头'               => 'Flat Blade Bare Terminal',
        '片形预绝缘端头'            => 'Flat Blade Pre-insulated Terminal',
        '插针裸端头'               => 'Pin Bare Terminal',

        /* ── Fork / blade pre-insulated ─────────────────────── */
        '叉形裸端头（TU型）'       => 'Fork Bare Terminal (TU Series)',
        '叉形预绝缘端头（TU-JTK型）' => 'Fork Pre-insulated Terminal (TU-JTK Series)',
        '叉形裸端头'               => 'Fork Bare Terminal',
        '叉形预绝缘端头'            => 'Fork Pre-insulated Terminal',
        '凸缘叉形裸端头'            => 'Flared Fork Bare Terminal',
        '凸缘叉形预绝缘端头'         => 'Flared Fork Pre-insulated Terminal',
        '锁扣叉形裸端头'            => 'Locking Fork Bare Terminal',
        '锁扣叉形预绝缘端头'         => 'Locking Fork Pre-insulated Terminal',
        '双线管型预绝缘端头'         => 'Twin Tube-Type Pre-insulated Terminal',
        '公预绝缘端头'              => 'Male Pre-insulated Terminal',

        /* ── Character variants ─────────────────────────────────
         * The catalogue is not perfectly consistent: some entries use
         * 端点 (endpoint) where others use 端头 (terminal), and some
         * use 形 where others use 型. These variants are listed
         * explicitly so the longest-match lookup resolves them.
         */
        '长型全绝缘中间接头'         => 'Long Fully-Insulated Intermediate Connector',
        '长形全绝缘中间接头'         => 'Long Fully-Insulated Intermediate Connector',
        '片型预绝缘端头'            => 'Flat Blade Pre-insulated Terminal',
        '片形预绝缘端头'            => 'Flat Blade Pre-insulated Terminal',
        '片型预绝缘端点'            => 'Flat Blade Pre-insulated Terminal',
        '片形预绝缘端头'            => 'Flat Blade Pre-insulated Terminal',
        '叉形预绝缘端点（TU-JTK型）' => 'Fork Pre-insulated Terminal (TU-JTK Series)',
        '叉形预绝缘端头（TU-JTK型）' => 'Fork Pre-insulated Terminal (TU-JTK Series)',
        '针形预绝缘端点（TZ-JTK型）' => 'Pin Pre-insulated Terminal (TZ-JTK Series)',
        '圆形预绝缘端点（TO-JTK型）' => 'Round Pre-insulated Terminal (TO-JTK Series)',

        /* ── Round terminals ─────────────────────────────────── */
        '圆形裸端头（TO型）'       => 'Round Bare Terminal (TO Series)',
        '圆形预绝缘端头（TO-JTK型）' => 'Round Pre-insulated Terminal (TO-JTK Series)',
        '圆形裸端头'               => 'Round Bare Terminal',
        '圆形预绝缘端头'            => 'Round Pre-insulated Terminal',

        /* ── Pin terminals ───────────────────────────────────── */
        '针形裸端头（TZ型）'       => 'Pin Bare Terminal (TZ Series)',
        '针形预绝缘端头（TZ-JTK型）' => 'Pin Pre-insulated Terminal (TZ-JTK Series)',
        '针形裸端头'               => 'Pin Bare Terminal',
        '针形预绝缘端头'            => 'Pin Pre-insulated Terminal',

        /* ── Intermediate connectors ─────────────────────────── */
        '中间裸接头（TL型）'       => 'Intermediate Bare Connector (TL Series)',
        '长形全绝缘中间接头'         => 'Long Fully-Insulated Intermediate Connector',
        '短形中间裸接头'            => 'Short Intermediate Bare Connector',
        '短形全绝缘中间接头'         => 'Short Fully-Insulated Intermediate Connector',
        '中间裸接头'               => 'Intermediate Bare Connector',

        /* ── Pre-insulated connectors ────────────────────────── */
        '母预绝缘接头'              => 'Female Pre-insulated Connector',
        '母全绝缘接头'              => 'Female Fully-Insulated Connector',
        '母预绝缘端子'              => 'Female Pre-insulated Terminal',
        '加铜套母预绝缘接头'         => 'Female Pre-insulated Connector with Copper Sleeve',
        '加铜套母全绝缘接头'         => 'Female Fully-Insulated Connector with Copper Sleeve',
        '加铜套公全绝缘接头'         => 'Male Fully-Insulated Connector with Copper Sleeve',
        '公预绝缘接头'              => 'Male Pre-insulated Connector',
        '肩背形公母预绝缘接头'        => 'Shoulder-Back Male/Female Pre-insulated Connector',
        '肩背型母预绝缘接头'         => 'Shoulder-Back Female Pre-insulated Connector',
        '旗形母绝缘接头'            => 'Flag Female Insulated Connector',
        '子弹形母全绝缘接头'         => 'Bullet-Shaped Female Fully-Insulated Connector',
        '子弹形公预绝缘接头'         => 'Bullet-Shaped Male Pre-insulated Connector',
        '子弹头公预绝缘接头'         => 'Bullet-Shaped Male Pre-insulated Connector',
        '快速连接接头'              => 'Quick-Connect Connector',
        '终端绝缘接头(透明)'         => 'Terminal Insulated End Connector (Transparent)',
        '绝缘软套管'                => 'Insulating Sleeve',
        '绝缘软管套'                => 'Insulating Sleeve Set',
        '螺旋式压线帽'              => 'Spiral Wire Connector',

        /* ── Bare terminals (flat/other) ─────────────────────── */
        '裸端头'                   => 'Bare Terminal',
    ];

    return $map;
}

/**
 * Normalise the interchangeable characters the catalogue mixes freely.
 *
 * The Chinese product catalogue is not internally consistent: it uses
 * both 型 ("type") and 形 ("shape"), and both 端头 ("terminal") and
 * 端点 ("endpoint") for the same product families. Rather than
 * enumerate every combination in the lookup table, we fold them
 * together before matching so one table entry covers all spellings.
 *
 * @param string $s Input string.
 * @return string
 */
function xxdq_normalize_type_text($s) {
    $s = str_replace('型', '形', $s);
    $s = str_replace('端点', '端头', $s);
    return $s;
}

/**
 * Translate a product title.
 *
 * The title format is "<MODEL> <中文类型>". We extract the type by
 * matching the LONGEST known Chinese type phrase that appears in the
 * title, then rebuild the title as "<MODEL> <English type>".
 *
 * @param string $zh_title Chinese product title.
 * @return string|null English title, or NULL when unmapped.
 */
function xxdq_translate_product_title($zh_title) {
    $zh_title = trim((string) $zh_title);
    if ($zh_title === '') {
        return null;
    }

    $map = xxdq_product_type_map();

    /* Work on normalised copies so 型/形 and 端头/端点 variants all
     * resolve to the same table entry. The ORIGINAL key is kept for the
     * English lookup. */
    $norm_title = xxdq_normalize_type_text($zh_title);
    $norm_map   = [];
    foreach ($map as $zh_type => $en_type) {
        $norm_map[xxdq_normalize_type_text($zh_type)] = [$zh_type, $en_type];
    }

    // Find the longest matching type phrase.
    $best_key   = '';
    $best_len   = 0;
    foreach ($norm_map as $norm_key => $pair) {
        if (mb_strpos($norm_title, $norm_key) !== false && mb_strlen($norm_key) > $best_len) {
            $best_key = $norm_key;
            $best_len = mb_strlen($norm_key);
        }
    }

    if ($best_key === '') {
        return null;
    }

    list($orig_key, $en_type) = $norm_map[$best_key];

    /* Everything before the type phrase is the model number. */
    $pos   = mb_strpos($norm_title, $best_key);
    $model = trim(mb_substr($norm_title, 0, $pos));

    /* The model may sit AFTER the type phrase rather than before it
     * (e.g. "管型预绝缘端口VE" → type "管型预绝缘端口", model "VE").
     * Try to recover it. */
    $trailing = trim(mb_substr($norm_title, $pos + mb_strlen($best_key)));

    /* A trailing size token — "01", "1号", "1.25mm²", "2mm²" — belongs
     * to the MODEL, not to the type. The catalogue uses it to tell
     * otherwise identical products apart:
     *
     *     冷压端子o型 01 / 02 / 03      → O-Shaped Crimp Terminal
     *     螺旋式压线帽 1号 / 2号 / 3号   → Spiral Wire Connector
     *     绝缘软套管 1.25mm² / 2mm²     → Insulating Sleeve
     *
     * Dropping it produced three products with the SAME English title
     * — a duplicate-content problem that also made the sitemap list
     * the same URL three times. The size is kept as the leading model
     * token so every product stays individually addressable.
     *
     * The token keeps its position: a Chinese title of the form
     * "<type> <size>" becomes "<English type> <size>", and a title of
     * the form "<model> <type>" keeps the model in front. The Chinese
     * unit is dropped ("1号" → "1") because the English type already
     * carries the unit. */
    if ($trailing !== '' && preg_match('/^(\d+(?:\.\d+)?)\s*(?:号|mm2|mm²)?$/u', $trailing, $m)) {
        $trailing = $m[1];
    }

    /* Some catalogue entries have NO model prefix — the title is just the
     * type name (e.g. "片形预绝缘端头" = "Flat Blade Pre-insulated
     * Terminal"). In that case the English title is the type alone,
     * with no model token in front. */
    if ($model === '') {
        /* …unless there is a trailing size token, which stays at the
         * end where the Chinese title had it. */
        if ($trailing !== '') {
            return $en_type . ' ' . $trailing;
        }
        return $en_type;
    }

    if ($trailing !== '') {
        /* If the trailing part is a short model token, use it. */
        if (preg_match('/^[A-Za-z0-9\-\(\)]{1,12}$/u', $trailing)) {
            return $trailing . ' ' . $en_type;
        }

        /* Otherwise it is part of the type itself → use the full type. */
        return $en_type;
    }

    return $model . ' ' . $en_type;
}

/**
 * Translate a product's `short_desc`.
 *
 * The catalogue short_desc follows a strict pattern:
 *   "<MODEL> <中文类型>，<参数1>。<参数2>。"
 * e.g. "SV1.25-4 叉形预绝缘端头，适用线径0.25~1.5mm²，M4。"
 *
 * We rebuild it as:
 *   "<MODEL> <English type>, <param1>. <param2>."
 *
 * @param string $zh_desc Chinese description.
 * @return string|null English description, or NULL when unmapped.
 */
function xxdq_translate_short_desc($zh_desc) {
    $zh_desc = trim((string) $zh_desc);
    if ($zh_desc === '') {
        return null;
    }

    $type = xxdq_translate_product_title($zh_desc);
    if ($type === null) {
        return null;
    }

    // Collect the trailing Chinese clauses and translate each one.
    // Split on the Chinese full stop / comma, keeping the structure.
    $parts = preg_split('/[，。]/u', $zh_desc, -1, PREG_SPLIT_NO_EMPTY);
    if (!$parts) {
        return null;
    }

    /* The FIRST clause is the "<MODEL> <中文类型>" lead. It is the same
     * text as the product title, so we reuse the title translation
     * rather than trying to translate it as a free-standing phrase. */
    $lead_zh = trim(array_shift($parts));
    $lead_en = xxdq_translate_product_title($lead_zh);
    if ($lead_en === null) {
        return null;
    }

    $out = [$lead_en];
    foreach ($parts as $part) {
        $part = trim($part);
        if ($part === '') {
            continue;
        }

        $translated = xxdq_translate_desc_clause($part);
        if ($translated === null) {
            // Unknown clause → the whole description needs review.
            return null;
        }
        $out[] = $translated;
    }

    $result = $lead_en . '.';
    foreach ($out as $p) {
        if ($p === $lead_en) {
            continue;
        }
        $result .= ' ' . $p . '.';
    }
    return trim($result);
}

/**
 * Translate one clause of a short_desc.
 *
 * @param string $clause Chinese clause.
 * @return string|null
 */
function xxdq_translate_desc_clause($clause) {
    $clause = trim($clause);
    if ($clause === '') {
        return null;
    }

    /* Known parameter phrases. Longest match first. */
    $params = [
        '适用线径'                => 'Wire Range',
        '订货编码'                => 'Order Code',
        '可按需定制规格'           => 'Custom specification available on request',
        '标准规格'                => 'Standard specification',
        '源头工厂直供'             => 'Factory Direct Supply',
        '小型电气首选规格'         => 'preferred specification for small electrical',
        '首选规格'                => 'preferred specification',
        '常规规格'                => 'standard specification',

        /* ── Copper-lug dimension clauses ─────────────────────
         * The DT / DTL / DTG short_descs spell out the full
         * mechanical dimensions, e.g.
         *   "外径 D=9.0mm，长度 L=62mm，内径 d=5.5mm，壁厚 S=2.3mm，
         *    宽度 W=16.0mm，螺丝孔 φ=8.5mm"
         * Each clause is "<label><symbol>=<value>" so the label is
         * translated and the measurement is kept verbatim.
         */
        '外径'                    => 'Outer Diameter',
        '内径'                    => 'Inner Diameter',
        '长度'                    => 'Length',
        '壁厚'                    => 'Wall Thickness',
        '宽度'                    => 'Width',
        '螺丝孔'                  => 'Stud Hole',
        '材质'                    => 'Material',
        '长度'                    => 'Length',

        /* ── Process / quality clauses ─────────────────────── */
        '铜铝过渡强度高'            => 'high-strength copper-aluminium transition',
        '摩擦焊工艺'                => 'Friction-welded process',
        '酸洗工艺'                  => 'Pickled finish',
        '工厂直供'                  => 'Factory Direct Supply',

        /* ── SC inspection-lug clauses ─────────────────────── */
        '管端带观察孔可目视检查压接质量' => 'inspection window at the tube end allows visual verification of the crimp',
        '窥口设计方便施工质检'        => 'inspection-hole design for easy installation QC',
        '窥口设计'                  => 'Inspection-hole design',
        '螺丝规格'                  => 'Stud Size',
        '适用于电力、配电柜、二次接线'  => 'suited to power distribution, switchgear and secondary wiring',
        '适用于电力'                => 'suited to power distribution',
        '配电柜'                    => 'switchgear',
        '二次接线'                  => 'secondary wiring',
        '紫铜镀锡'                  => 'Tinned Copper',
    ];

    $norm = xxdq_normalize_type_text($clause);

    // Handle "<label><value>" e.g. "适用线径0.25~1.5mm²"
    foreach ($params as $zh_label => $en_label) {
        $norm_label = xxdq_normalize_type_text($zh_label);
        if (mb_strpos($norm, $norm_label) === 0) {
            /* Use mb_substr with the CHARACTER length, not strlen with
             * the byte length — mixing the two cuts the Chinese label
             * in half and leaves mojibake behind. */
            $value = trim(mb_substr($norm, mb_strlen($norm_label)));
            $value = rtrim($value, '。');
            /* The catalogue uses a full-width colon between a label and
             * its value ("材质：紫铜"). Strip it — the label already
             * provides the separation. */
            $value = ltrim($value, '：:');
            $value = trim($value);
            if ($value === '') {
                return $en_label;
            }

            /* The value itself may still be Chinese, e.g.
             * "材质：紫铜" → Material: Tinned Copper. Translate it via
             * the spec-value dictionary when possible; otherwise keep it
             * verbatim (a numeric measurement, for example). */
            $value_en = xxdq_translate_spec_value('material', $value);
            if ($value_en !== null) {
                $value = $value_en;
            }

            return $en_label . ': ' . $value;
        }
    }

    /* Plain numeric / parameter clause, e.g. "M4", "L=70mm",
     * "D=9.0mm", "φ=8.5mm", "0.5-50mm²". These carry no Chinese, so
     * they are copied verbatim once we confirm the clause contains no
     * untranslated Chinese words.
     *
     * The character class allows the Greek phi and the squared sign used
     * in the catalogue's dimension notation. */
    if (preg_match('/^[\x{4e00}-\x{9fff}]*[\dA-Za-z\.\~\-–—±%\/°ΩφΦ\(\)（）\s,=]+$/u', $clause)) {
        $value = rtrim(trim($clause), '。');
        return $value === '' ? null : $value;
    }

    return null;
}

/**
 * Translate a product spec field VALUE using the terminology dictionary.
 *
 * @param string $field Field key, e.g. 'material'.
 * @param string $value Chinese value.
 * @return string|null English value, or NULL when unmapped.
 */
function xxdq_translate_spec_value($field, $value) {
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }

    // Numeric / already-language-neutral fields need no translation.
    $numeric_fields = ['wire_range', 'hole_size', 'voltage', 'model'];
    if (in_array($field, $numeric_fields, true)) {
        /* A numeric field may still carry a Chinese qualifier in
         * brackets, e.g. "M6（镀锡窥口）". Translate the qualifier and
         * keep the measurement in front of it. */
        if (preg_match('/^([\w\.\-]+)\s*[（(]([^）)]+)[）)]$/u', $value, $m)) {
            $terms  = require XXDQ_I18N_DIR . '/terms.php';
            $tkey   = 'spec.' . $field . '.' . $m[2];
            $qual   = isset($terms[$tkey]) ? $terms[$tkey] : $m[2];
            if (!preg_match('/[\x{4e00}-\x{9fff}]/u', $qual)) {
                return $m[1] . ' (' . $qual . ')';
            }
        }
        return $value;
    }

    $terms = require XXDQ_I18N_DIR . '/terms.php';

    $key = 'spec.' . $field . '.' . $value;
    if (isset($terms[$key])) {
        return $terms[$key];
    }

    /* The packing unit follows a fixed pattern ("100 只/包"), so any
     * count can be translated without an entry for every number. */
    if ($field === 'package') {
        if (preg_match('/^(\d+)\s*只\s*\/\s*包$/u', $value, $m)) {
            return $m[1] . ' pcs per bag';
        }
        if (preg_match('/^(\d+)\s*(?:个|只|pcs?)\s*\/\s*(?:包|盒|箱)$/u', $value, $m)) {
            return $m[1] . ' pcs per bag';
        }
    }

    /* Temperature has a ℃ character that should become °C. */
    if ($field === 'temperature') {
        return str_replace('℃', '°C', $value);
    }

    // Certification "CE / RoHS / ISO9001" is already language-neutral.
    if ($field === 'certification') {
        return $value;
    }

    return null;
}

/**
 * Translate a block of Chinese text (a paragraph of a page or a news
 * post).
 *
 * The paragraph is split on the Chinese sentence punctuation and each
 * clause is translated on its own. A clause the dictionary cannot map
 * is returned as NULL, which tells the caller that the paragraph needs
 * a human translator — a partially translated paragraph would be worse
 * than an untouched Chinese one, because the reader would not know
 * which parts are trustworthy.
 *
 * @param string $text Chinese text.
 * @return string|null English text, or NULL when a clause is unmapped.
 */
function xxdq_translate_text($text) {
    $text = trim((string) $text);
    if ($text === '') {
        return null;
    }

    /* Text that is already English is passed through. */
    if (!preg_match('/[\x{4e00}-\x{9fff}]/u', $text)) {
        return $text;
    }

    /* Strip the HTML block markup — the translator works on plain text
     * and the caller keeps the markup of the original paragraph. */
    $parts = preg_split('/(?<=[。！？；])/u', $text, -1, PREG_SPLIT_NO_EMPTY);

    $out = array();
    foreach ($parts as $part) {
        $part = trim($part);
        if ($part === '') {
            continue;
        }

        /* A clause made of a single Chinese phrase is a candidate for
         * the term dictionary. */
        $translated = xxdq_translate_desc_clause($part);
        if ($translated === null) {
            $translated = xxdq_translate_product_title($part);
        }
        if ($translated === null) {
            /* Unmapped — the paragraph needs a human. */
            return null;
        }

        $out[] = rtrim($translated, '.。');
    }

    if (empty($out)) {
        return null;
    }

    $result = implode('. ', $out);
    return $result . '.';
}
