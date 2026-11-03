<?php
/**
 * XXDQ i18n — technical terminology dictionary.
 *
 * Two kinds of lookup live here:
 *
 *   1. SPEC VALUES — the short, repeated values stored in product ACF
 *      fields (`material`, `finish`, `color_code`). There are only ~20
 *      distinct values across all 308 products, so a dictionary is both
 *      cheap and fully deterministic. Numeric fields (`wire_range`,
 *      `hole_size`, `voltage`) need no translation.
 *
 *   2. CATEGORY NAMES — the 90 product_category terms. Stored as term
 *      meta (`name_en`) rather than here, because each term needs a
 *      per-term description too. This file only holds the *canonical*
 *      English name for a term, used to seed `name_en` on first run.
 *
 * Terminology notes (industry-standard B2B electrical usage):
 *   - 冷压端子   → Crimp Terminal          (not "Cold Press Terminal")
 *   - 铜鼻子     → Copper Lug              (the colloquial Chinese name
 *                                            for a flat copper terminal)
 *   - 预绝缘端子 → Pre-insulated Terminal
 *   - 裸端头     → Bare Terminal
 *   - 中间接头   → Intermediate Connector
 *   - 尼龙       → Nylon (PA66) insulation
 *   - 紫铜       → Copper (T2 grade)
 *
 * @package XXDQ
 */

if (!defined('ABSPATH')) exit;

return [
    /* ── Material values ──────────────────────────────────────── */
    'spec.material.PVC'                        => 'PVC',
    'spec.material.尼龙'                        => 'Nylon',
    'spec.material.透明尼龙'                     => 'Transparent Nylon',
    'spec.material.紫铜'                        => 'Copper',
    'spec.material.T2 紫铜'                     => 'T2 Copper',
    'spec.material.紫铜镀锡'                     => 'Tinned Copper',
    'spec.material.镀锡紫铜管'                   => 'Tinned Copper Tube',
    'spec.material.尼龙+紫铜镀锡'                => 'Nylon + Tinned Copper',
    'spec.material.尼龙+紫铜镀锡+铜套'            => 'Nylon + Tinned Copper + Copper Sleeve',
    'spec.material.铜+铝摩擦焊'                  => 'Copper + Aluminium Friction Welded',
    'spec.material.黄铜'                        => 'Brass',

    /* ── Finish values ────────────────────────────────────────── */
    'spec.finish.酸洗'                          => 'Pickled',
    'spec.finish.镀锡'                          => 'Tinned',

    /* ── Insulation colour values ─────────────────────────────── */
    'spec.color_code.红'                        => 'Red',
    'spec.color_code.红/黑'                     => 'Red / Black',

    /* ── Packing values ─────────────────────────────────────────
     * The packing unit is part of the buyer's decision — "100 pcs per
     * bag" is what a purchasing department orders against. */
    'spec.package.100 只/包'                    => '100 pcs per bag',
    'spec.package.100只/包'                     => '100 pcs per bag',
    'spec.package.1000 只/包'                   => '1000 pcs per bag',
    'spec.package.1000只/包'                    => '1000 pcs per bag',
    'spec.package.500 只/包'                    => '500 pcs per bag',
    'spec.package.500只/包'                     => '500 pcs per bag',
    'spec.package.50 只/包'                     => '50 pcs per bag',
    'spec.package.50只/包'                      => '50 pcs per bag',

    /* ── Stud-hole qualifiers ─────────────────────────────────
     * Some inspection lugs spell the tinned inspection hole out as a
     * bracket suffix on the stud size, e.g. "M6（镀锡窥口）". */
    'spec.hole_size.镀锡窥口'                    => 'Tinned Inspection Hole',

    /* ── Product category names ───────────────────────────────── */
    'cat.铜鼻子'                => 'Copper Lug',
    'cat.冷压端子'              => 'Crimp Terminal',
    'cat.预绝缘端子'            => 'Pre-insulated Terminal',
    'cat.预绝缘端头（尼龙）'    => 'Pre-insulated Terminal (Nylon)',
    'cat.裸端头'                => 'Bare Terminal',
    'cat.预绝缘接头'            => 'Pre-insulated Connector',
    'cat.预绝缘接头（尼龙）'    => 'Pre-insulated Connector (Nylon)',
    'cat.中间接头'              => 'Intermediate Connector',
    'cat.铜管端子'              => 'Copper Tube Terminal',
    'cat.连接器'                => 'Connector',
    'cat.绝缘软套管'            => 'Insulating Sleeve',
    'cat.快速连接接头'          => 'Quick Connector',

    /* ── Term types ───────────────────────────────────────────── */
    'cat_type.冷压端子'         => 'Crimp Terminals',
    'cat_type.预绝缘端子'       => 'Pre-insulated Terminals',
    'cat_type.连接器'           => 'Connectors',

    /* ── Series-prefixed category names ────────────────────────
     * Many categories are named "<MODEL prefix><中文类型>", e.g.
     * "BNYF长形全绝缘中间接头". The English name is the type name with
     * the model prefix kept, e.g. "BNYF Long Fully-Insulated
     * Intermediate Connector".
     */
    'cat.螺旋式压线帽'            => 'Spiral Wire Connector',
    'cat.终端绝缘接头（透明）'     => 'Terminal Insulated End Connector (Transparent)',
    'cat.通用绝缘软套管'           => 'Universal Insulating Sleeve',
    'cat.BNYF长形全绝缘中间接头'    => 'BNYF Long Fully-Insulated Intermediate Connector',
    'cat.BN中间裸接头'            => 'BN Intermediate Bare Connector',
    'cat.PNT短形中间裸接头'        => 'PNT Short Intermediate Bare Connector',
    'cat.BV长形全绝缘中间接头'      => 'BV Long Fully-Insulated Intermediate Connector',
    'cat.PVT短形全绝缘中间接头'     => 'PVT Short Fully-Insulated Intermediate Connector',
    'cat.SC窥口铜接线端头'          => 'SC Copper Inspection Terminal',
    'cat.DT铜管端子'              => 'DT Copper Tube Terminal',
    'cat.T铜管端子'               => 'T Copper Tube Terminal',
    'cat.C45插针裸端头'            => 'C45 Pin Bare Terminal',
    'cat.BEF铜管端子'             => 'BEF Copper Tube Terminal',
    'cat.FDFD母全绝缘接头'          => 'FDFD Female Fully-Insulated Connector',
    'cat.FDD母预绝缘接头'          => 'FDD Female Pre-insulated Connector',
    'cat.MDD公预绝缘接头'          => 'MDD Male Pre-insulated Connector',
    'cat.MPD子弹形公预绝缘接头'     => 'MPD Bullet-Shaped Male Pre-insulated Connector',
    'cat.FRD子弹形母全绝缘接头'     => 'FRD Bullet-Shaped Female Fully-Insulated Connector',
    'cat.PBDD肩背形公母预绝缘接头'  => 'PBDD Shoulder-Back Male/Female Pre-insulated Connector',
    'cat.FDNY母预绝缘接头'         => 'FDNY Female Pre-insulated Connector',
    'cat.MDNY公预绝缘接头'         => 'MDNY Male Pre-insulated Connector',
    'cat.FLDNY旗形母绝缘接头'       => 'FLDNY Flag Female Insulated Connector',
    'cat.FDNYD加铜套母预绝缘接头'   => 'FDNYD Female Pre-insulated Connector with Copper Sleeve',
    'cat.MDFNYD加铜套公全绝缘接头'  => 'MDFNYD Male Fully-Insulated Connector with Copper Sleeve',
    'cat.FSNB凸缘叉形裸端头'        => 'FSNB Flared Fork Bare Terminal',
    'cat.LSNB锁扣叉形裸端头'        => 'LSNB Locking Fork Bare Terminal',
    'cat.PTNB针形裸端头'           => 'PTNB Pin Bare Terminal',
    'cat.SNB叉形裸端头'            => 'SNB Fork Bare Terminal',
    'cat.RNB圆形裸端头'            => 'RNB Round Bare Terminal',
    'cat.RNB-90D圆形裸端头'        => 'RNB-90D Round Bare Terminal',
    'cat.DBN片形裸端头'            => 'DBN Flat Blade Bare Terminal',
    'cat.DBN-90D片形裸端头'        => 'DBN-90D Flat Blade Bare Terminal',
    'cat.PTN针形裸端头'           => 'PTN Pin Bare Terminal',
    'cat.SNY叉形预绝缘端头'         => 'SNY Fork Pre-insulated Terminal',
    'cat.RNY圆形预绝缘端头'         => 'RNY Round Pre-insulated Terminal',
    'cat.DBNY片形预绝缘端头'        => 'DBNY Flat Blade Pre-insulated Terminal',
    'cat.PTNY针形预绝缘端头'        => 'PTNY Pin Pre-insulated Terminal',
    'cat.FSNY凸缘叉形预绝缘端头'     => 'FSNY Flared Fork Pre-insulated Terminal',
    'cat.LSNY锁扣叉形预绝缘端头'     => 'LSNY Locking Fork Pre-insulated Terminal',
    'cat.DBV片形预绝缘端头'         => 'DBV Flat Blade Pre-insulated Terminal',
    'cat.PTV针形预绝缘端头'         => 'PTV Pin Pre-insulated Terminal',
    'cat.FSV凸缘叉形预绝缘端头'      => 'FSV Flared Fork Pre-insulated Terminal',
    'cat.LSV锁扣叉形预绝缘端头'      => 'LSV Locking Fork Pre-insulated Terminal',
    'cat.SV叉形预绝缘端头'          => 'SV Fork Pre-insulated Terminal',
    'cat.RV圆形预绝缘端头'          => 'RV Round Pre-insulated Terminal',
    'cat.VE管型预绝缘端头'          => 'VE Tube-Type Pre-insulated Terminal',

    /* ── Generic crimp-series categories ────────────────────── */
    'cat.圆形冷压端子'            => 'Round Crimp Terminal',
    'cat.圆形冷压接线端子'         => 'Round Crimp Connector Terminal',
    'cat.圆形冷压裸端子接线铜鼻子'  => 'Round Bare Crimp Copper Lug',
    'cat.冷压端子o型'             => 'O-Shaped Crimp Terminal',
    'cat.冷压端子u型'             => 'U-Shaped Crimp Terminal',
    'cat.y型冷压端子'             => 'Y-Shaped Crimp Terminal',
    'cat.sv冷压接线端子'          => 'SV Crimp Connector Terminal',
    'cat.ve管型冷压接线端子'       => 'VE Tube-Type Crimp Connector Terminal',
    'cat.ve冷压接线端子'          => 'VE Crimp Connector Terminal',
    'cat.rv冷压接线端子'          => 'RV Crimp Connector Terminal',
    'cat.rnb冷压端子'             => 'RNB Crimp Terminal',
    'cat.ot冷压端子'              => 'OT Crimp Terminal',
    'cat.ut冷压端子'              => 'UT Crimp Terminal',
    'cat.sc冷压端子'              => 'SC Crimp Terminal',
    'cat.管型冷压接线端子'          => 'Tube-Type Crimp Connector Terminal',
    'cat.管型冷压端子'             => 'Tube-Type Crimp Terminal',

    /* ── Copper lug categories (size- and series-specific) ───── */
    'cat.dt铜鼻子'               => 'DT Copper Lug',
    'cat.dt国标铜鼻子'            => 'DT National Standard Copper Lug',
    'cat.jg国标铜鼻子'            => 'JG National Standard Copper Lug',
    'cat.jg铜鼻子'               => 'JG Copper Lug',
    'cat.ot铜鼻子'               => 'OT Copper Lug',
    'cat.sc铜鼻子'               => 'SC Copper Lug',
    'cat.铜鼻子dt25'             => 'Copper Lug DT25',
    'cat.10平方铜鼻子'            => '10 mm² Copper Lug',
    'cat.16平方铜鼻子'            => '16 mm² Copper Lug',
    'cat.25平方铜鼻子'            => '25 mm² Copper Lug',
    'cat.35平方铜鼻子'            => '35 mm² Copper Lug',
    'cat.50平方铜鼻子'            => '50 mm² Copper Lug',
    'cat.70平方铜鼻子'            => '70 mm² Copper Lug',
    'cat.95平方铜鼻子'            => '95 mm² Copper Lug',
    'cat.120平方铜鼻子'           => '120 mm² Copper Lug',
    'cat.240铜鼻子'              => '240 mm² Copper Lug',
];
