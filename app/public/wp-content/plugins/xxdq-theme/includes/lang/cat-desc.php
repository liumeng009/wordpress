<?php
/**
 * XXDQ i18n — category description phrase dictionary.
 *
 * The Chinese product_category descriptions are short marketing
 * sentences built from a fixed vocabulary. Translating them phrase by
 * phrase (rather than word by word) produces readable English instead
 * of the word-salad the naive per-character replacement produced.
 *
 * The dictionary is ordered longest-phrase-first at build time, so a
 * longer phrase always wins over its own substrings.
 *
 * @package XXDQ
 */

if (!defined('ABSPATH')) exit;

/**
 * The Chinese → English phrase dictionary for category descriptions.
 *
 * @return array<string,string>
 */
function xxdq_cat_desc_phrases() {
    static $phrases = null;
    if ($phrases !== null) {
        return $phrases;
    }

    $phrases = [
        /* ── Longer, more specific phrases first ─────────────── */
        '工厂直发支持非标定制'   => 'factory direct supply, custom manufacturing available',
        '工厂直发支持非标定做'   => 'factory direct supply, custom manufacturing available',
        '工厂直发支持来图定做'   => 'factory direct supply, made-to-draw orders welcome',
        '工厂直发支持批量定做'   => 'factory direct supply, bulk orders welcome',
        '工厂现货直发'           => 'factory stock, shipped direct',
        '工厂直发'               => 'factory direct supply',
        '标准规格现货'           => 'standard specification, in stock',
        '规格现货'               => 'specification, in stock',
        '现货'                   => 'in stock',
        '工厂现货'               => 'factory stock',
        '现货直发'               => 'in stock, shipped direct',
        '24 小时出货'            => '24-hour dispatch',
        '小时出货'               => 'hour dispatch',

        '适用装配间隙大场景'     => 'suited to wide assembly-gap applications',
        '适用装配间隙适中场景'   => 'suited to medium assembly-gap applications',
        '适配装配间隙大场景'     => 'suited to wide assembly-gap applications',
        '装配间隙大场景'         => 'wide assembly-gap applications',
        '装配间隙适中场景'       => 'medium assembly-gap applications',
        '空间紧凑场景'           => 'space-constrained installations',
        '狭小空间侧向连接场景'   => 'side-entry connections in confined spaces',
        '多线束分线场景'         => 'multi-harness branching applications',
        '两根电缆对接场景'       => 'two-cable jointing applications',
        '设备引线连接场景'       => 'equipment lead-wire connections',
        '电缆对接场景'           => 'cable-to-cable jointing',
        '线束对接场景'           => 'harness-to-harness jointing',
        '维护检修场景'           => 'maintenance and service work',
        '振动场景'               => 'vibration-prone environments',
        '大电流场景'             => 'high-current applications',
        '大电流接线场景'         => 'high-current wiring applications',
        '小批量维护场景'         => 'small-batch maintenance work',
        '小批量维护首选'         => 'first choice for small-batch maintenance',
        '批量维护'               => 'bulk maintenance',
        '修复现场接线使用'       => 'on-site wiring repair',
        '端头'                   => 'end',

        '适配螺钉紧固场景'       => 'suited to screw fastening',
        '适配螺钉'               => 'suited to screws',
        '适配焊接'               => 'suited to soldering',
        '适配焊接/插针连接'      => 'suited to soldering / pin connections',
        '适配焊接/插针'          => 'suited to soldering / pins',
        '适配电缆对接'           => 'suited to cable-to-cable jointing',
        '适配电缆对接场景'       => 'suited to cable-to-cable jointing',
        '适配子弹形母接头'       => 'mates with bullet-shaped female connectors',
        '适配 C45 系列插针连接'  => 'mates with C45 series pins',
        '适配 M3–M5 螺钉'        => 'fits M3–M5 screws',
        '适配 M3–M10 螺栓'       => 'fits M3–M10 bolts',
        '适配 M3–M12 螺钉分档'   => 'fits M3–M12 screws, size-coded',
        '适配 M5–M16 螺栓'       => 'fits M5–M16 bolts',
        '适配'                   => 'suited to',
        '延长'                   => 'extension',
        '认证'                   => 'certified',
        '90 度弯角'             => '90-degree angled',
        '度弯角'                 => 'degree angled',
        '批量维护'               => 'bulk maintenance',

        '螺丝直接紧固无需液压钳' => 'screw fastening — no hydraulic crimper needed',
        '螺丝紧固型'             => 'screw fastening type',
        '螺钉压接'               => 'screw crimping',
        '螺钉分档'               => 'screw size coding',
        '螺栓孔径'               => 'bolt hole size',
        '螺栓'                   => 'bolts',
        '螺钉'                   => 'screws',
        '螺丝'                   => 'screws',

        '紫铜堵油标准型'         => 'tinned copper oil-block standard type',
        '紫铜堵油/管压型'        => 'tinned copper oil-block / tube-crimp type',
        '紫铜堵油型'             => 'tinned copper oil-block type',
        '紫铜堵油'               => 'tinned copper oil-block',
        '国标堵油标准型'         => 'national-standard oil-block type',
        '国标堵油标准'           => 'national-standard oil-block',
        '堵油标准型'             => 'oil-block standard type',
        '堵油型'                 => 'oil-block type',
        '堵油'                   => 'oil-block',
        '管压型'                 => 'tube-crimp type',
        '管压'                   => 'tube-crimp',
        '窥口型'                 => 'inspection-hole type',
        '窥口'                   => 'inspection hole',
        '开口型'                 => 'open-barrel type',
        '开口'                   => 'open barrel',
        '标准型'                 => 'standard type',
        '标准多种结构'           => 'standard and other constructions',
        '标准'                   => 'standard',

        '紫铜管型端子'           => 'copper tube terminals',
        '紫铜管端子'             => 'copper tube terminals',
        '紫铜管'                 => 'copper tube',
        '紫铜材质'               => 'copper material',
        '紫铜镀锡'               => 'tinned copper',
        '镀锡紫铜'               => 'tinned copper',
        '镀锡'                   => 'tinned',
        '紫铜'                   => 'copper',

        '铜管压接型'             => 'tube-crimp type',
        '铜管压接'               => 'tube crimping',
        '铜管端子'               => 'copper tube terminals',
        '铜鼻子'                 => 'copper lugs',
        '铜端子'                 => 'copper terminals',

        '母全绝缘接头'           => 'female fully-insulated connectors',
        '公全绝缘接头'           => 'male fully-insulated connectors',
        '母预绝缘接头'           => 'female pre-insulated connectors',
        '公预绝缘接头'           => 'female pre-insulated connectors',
        '母接头配套'             => 'mates with the female connector',
        '公接头配套'             => 'mates with the male connector',
        '公母对接'               => 'male-to-female jointing',
        '公母'                   => 'male / female',
        '母'                     => 'female',
        '公'                     => 'male',
        '子弹形母全绝缘接头'     => 'bullet-shaped female fully-insulated connectors',
        '子弹形母接头'           => 'bullet-shaped female connectors',
        '子弹形公'               => 'bullet-shaped male',
        '子弹形'                 => 'bullet-shaped',
        '肩背形公母预绝缘接头'   => 'shoulder-back male/female pre-insulated connectors',
        '肩背形'                 => 'shoulder-back',
        '旗形母绝缘接头'         => 'flag-shaped female insulated connectors',
        '旗形'                   => 'flag-shaped',
        '长形全绝缘中间接头'     => 'long fully-insulated intermediate connectors',
        '短形全绝缘中间接头'     => 'short fully-insulated intermediate connectors',
        '短形中间裸接头'         => 'short intermediate bare connectors',
        '长形全绝缘'             => 'long fully-insulated',
        '全绝缘'                 => 'fully-insulated',
        '中间裸接头'             => 'intermediate bare connectors',
        '中间接头'               => 'intermediate connectors',
        '快速连接接头'           => 'quick-connect connectors',
        '终端绝缘接头'           => 'terminal insulated end connectors',
        '透明外壳'               => 'transparent housing',
        '螺旋式压线帽'           => 'twist-on wire connectors',
        '压线帽'                 => 'wire connectors',

        '双层绝缘'               => 'double-layer insulation',
        '双重绝缘'               => 'double insulation',
        '尼龙 66 绝缘'           => 'nylon 66 insulation',
        '尼龙绝缘'               => 'nylon insulation',
        '尼龙'                   => 'nylon',
        '绝缘包覆'               => 'insulation sleeving',
        '绝缘软套管'             => 'insulating sleeves',
        '软套管'                 => 'sleeves',
        '绝缘'                   => 'insulation',
        '阻燃'                   => 'flame retardant',
        '耐温'                   => 'rated for',
        '全尼龙'                 => 'all-nylon',
        '热缩'                   => 'heat-shrink',

        '叉形'                   => 'fork',
        '凸缘叉形'               => 'flared fork',
        '锁扣叉形'               => 'locking fork',
        '圆形'                   => 'round',
        '圆形预绝缘'             => 'round pre-insulated',
        '圆形裸端子'             => 'round bare terminals',
        '针形'                   => 'pin',
        '片形'                   => 'flat blade',
        '管型'                   => 'tube-type',
        '管'                     => 'tube',
        '凸缘'                   => 'flared',
        '锁扣'                   => 'locking',

        '插针连接'               => 'pin connection',
        '插针焊接'               => 'pin soldering',
        '插针'                   => 'pins',
        '窥口铜'                 => 'copper inspection',
        '电缆对接'               => 'cable jointing',
        '线束对接'               => 'harness jointing',
        '线束分线'               => 'harness branching',
        '适用'                   => 'suitable',
        '分线适用'               => 'suitable for branching',
        '电缆标识'               => 'cable marking',
        '线缆保护'               => 'cable protection',
        '电源连接'               => 'power connections',
        '信号控制通用'           => 'signal and control applications',
        '接地'                   => 'earthing',
        '电池组'                 => 'battery packs',
        '电机'                   => 'motor applications',
        '汽车线束'               => 'automotive wiring harnesses',
        '汽车线束通用'           => 'universal for automotive harnesses',
        '电力二次接线'           => 'secondary power wiring',
        '电力接线'               => 'power wiring',
        '电力工程'               => 'power engineering',
        '大电流接线'             => 'high-current wiring',
        '配电柜二次接线常用'     => 'common for secondary distribution-board wiring',
        '配电柜'                 => 'distribution boards',
        '开关柜'                 => 'switchgear cabinets',
        '电气控制柜常用'         => 'common in electrical control cabinets',
        '控制柜常用'             => 'common in control cabinets',
        '控制柜'                 => 'control cabinets',
        '电气控制柜'             => 'electrical control cabinets',
        '电气'                   => 'electrical',
        '电力'                   => 'power',
        '家电连接常用'           => 'common for household appliance wiring',
        '家电'                   => 'household appliances',
        '自动化接线优选'         => 'preferred for automated wiring',
        '自动化'                 => 'automation',
        '变压器'                 => 'transformers',
        'PLC 接线优选'           => 'preferred for PLC wiring',
        '接线优选'               => 'preferred for wiring',
        '电力工程首选规格'       => 'preferred specification for power engineering',
        '首选规格'               => 'preferred specification',
        '首选'                   => 'first choice',
        '优选'                   => 'preferred',

        '可选'                   => 'available',
        '可按需定制'             => 'customisable to order',
        '可按米切割'             => 'sold by the metre',
        '支持非标定制'           => 'custom manufacturing supported',
        '支持'                   => 'supports',
        '支持 OEM/ODM 定制'      => 'OEM / ODM customisation supported',
        '定制'                   => 'customisation',
        '对标'                   => 'matches',
        '配套'                   => 'mates with',
        '通用规格现货'           => 'standard specification, in stock',
        '通用规格'               => 'standard specification',
        '通用'                   => 'general purpose',
        '规格齐全'               => 'full range available',
        '规格齐全现货'           => 'full range available, in stock',
        '规格'                   => 'specification',
        '多规格可选'             => 'multiple specifications available',
        '多种结构'               => 'multiple constructions',
        '多种接线方式'           => 'multiple wiring methods',
        '标准多种结构'           => 'standard and other constructions',
        '两种结构'               => 'two constructions',
        '全规格'                 => 'full range',
        '全覆盖'                 => 'full coverage',
        '覆盖'                   => 'covers',
        '齐全'                   => 'complete range',
        '多种'                   => 'multiple',
        '多'                     => 'multi',

        '大电流'                 => 'high-current',
        '大'                     => 'large',
        '小线径'                 => 'small wire range',
        '小'                     => 'small',
        '线径'                   => 'wire range',
        '导线'                   => 'conductors',
        '国标尺寸'               => 'national-standard dimensions',
        '国标'                   => 'national standard',
        'T2'                     => 'T2',
        'mm²'                    => 'mm²',
        '°C'                     => '°C',

        '线束'                   => 'harness',
        '端子'                   => 'terminals',
        '冷压端子'               => 'crimp terminals',
        '冷压端头'               => 'crimp terminals',
        '预绝缘端子'             => 'pre-insulated terminals',
        '预绝缘端头'             => 'pre-insulated terminals',
        '预绝缘接头'             => 'pre-insulated connectors',
        '预绝缘'                 => 'pre-insulated',
        '冷压'                   => 'crimp',
        '裸端头'                 => 'bare terminals',
        '裸端'                   => 'bare',
        '接线端子'               => 'connector terminals',
        '轨道式接线端子'         => 'rail-mount connector terminals',
        '轨道式'                 => 'rail-mount',
        '连接器'                 => 'connectors',
        '接头'                   => 'connectors',

        '加铜套款导电更稳定'     => 'copper sleeve version conducts more reliably',
        '加铜套'                 => 'copper sleeve',
        '导电更稳定'             => 'more stable conductivity',
        '导电稳定'               => 'stable conductivity',
        '镀层均匀'               => 'even plating',
        '防松脱'                 => 'anti-loosening',
        '防错装'                 => 'prevents mismating',
        '可视装配防错接'         => 'visible assembly prevents mis-connection',
        '装配便捷可见'           => 'quick to assemble and inspect',
        '管端带观察孔'           => 'inspection hole at the tube end',
        '免压接便捷连接'         => 'crimp-free, easy connection',
        '即压即用'               => 'crimp and go',
        '即插即用'               => 'plug and play',
        '即插即拔'               => 'quick plug-and-unplug',
        '需二次绝缘处理'         => 'requires secondary insulation',
        '二次绝缘处理'           => 'secondary insulation treatment',
        '弹性连接多种结构'       => 'spring connection, multiple constructions',
        '可按线径分色'           => 'colour-coded by wire range',
        '按线径分色'             => 'colour-coded by wire range',
        '按线径分档'             => 'size-coded by wire range',
        '黄按线径分色'           => 'yellow, colour-coded by wire range',
        '黄分色'                 => 'yellow colour coding',
        '红/蓝/黄分色'           => 'red / blue / yellow colour coding',
        '红/蓝/黄按线径分色'     => 'red / blue / yellow, colour-coded by wire range',
        '红色预绝缘'             => 'red pre-insulated',
        '蓝色'                   => 'blue',
        '红色'                   => 'red',
        '黄色'                   => 'yellow',
        '分色'                   => 'colour coding',
        '全系列'                 => 'full range',
        '系列'                   => 'series',
        '型'                     => 'type',
        '结构'                   => 'construction',
        '及'                     => 'and',
        '与'                     => 'and',
        '、'                     => ', ',
        '，'                     => ', ',
        '。'                     => '.',
        '（'                     => ' (',
        '）'                     => ') ',
    ];

    return $phrases;
}

/**
 * Translate a Chinese category description into English.
 *
 * Longer phrases are applied first so that a specific phrase always
 * wins over its own substrings.
 *
 * @param string $zh Chinese description.
 * @return string English description (may be empty).
 */
function xxdq_translate_cat_description($zh) {
    $zh = trim((string) $zh);
    if ($zh === '') {
        return '';
    }

    /* Strip embedded HTML (some category descriptions carry an image). */
    $plain = trim(preg_replace('/<[^>]*>/u', '', $zh));
    if ($plain === '') {
        return '';
    }

    if (!preg_match('/[\x{4e00}-\x{9fff}]/u', $plain)) {
        return $plain;
    }

    $phrases = xxdq_cat_desc_phrases();

    /* Longest first. */
    uksort($phrases, function ($a, $b) {
        return mb_strlen($b) - mb_strlen($a);
    });

    $out = $plain;
    foreach ($phrases as $zh_p => $en_p) {
        if (mb_strpos($out, $zh_p) === false) {
            continue;
        }
        /* Surround the replacement with spaces so that a Chinese phrase
         * sitting between two Latin runs does not glue them together.
         * Without this "T 系列紫铜管端子" became "T seriescopper tube
         * terminals" — two words fused into one. The spaces are tidied
         * up again further down. */
        $out = str_replace($zh_p, ' ' . $en_p . ' ', $out);
    }

    /* Tidy up. */
    $out = preg_replace('/\s*,\s*,+/', ', ', $out);
    $out = preg_replace('/,\s*\./', '.', $out);
    $out = preg_replace('/\s*\.\s*\./', '.', $out);
    $out = preg_replace('/\(\s*\)/', '', $out);
    $out = preg_replace('/[ \t]+/u', ' ', $out);
    $out = preg_replace('/\s+([,.;:)])/u', '$1', $out);
    $out = preg_replace('/([(])\s+/u', '$1', $out);
    $out = preg_replace('/\s*([,;])\s*/u', ', ', $out);
    $out = preg_replace('/\s{2,}/u', ' ', (string) $out);
    $out = trim((string) $out);

    /* Any Chinese left over means the dictionary had a gap — flag it so
     * the review log can pick it up. */
    if (preg_match('/[\x{4e00}-\x{9fff}]/u', $out)) {
        return '';
    }

    return ucfirst($out);
}
