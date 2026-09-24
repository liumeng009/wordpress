<?php
/**
 * ACF field group additions for product PIM.
 *
 * Adds:
 *  - real_image:   ACF image (single) — 实物图, for product card preview
 *  - tech_image:   ACF image (single) — 描述图/技术图, spec sheet snapshot
 *  - attachment:   ACF file (single PDF, ACF Free fallback)
 *
 * Resolution order for product card images:  实物图 → 描述图 → 特色图 → SVG 占位
 */

if (!defined('ABSPATH')) exit;

class XXDQ_PIM_ACF_Fields {

    private static $instance;

    public static function instance() {
        if (!self::$instance) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        // Use plugins_loaded + 1 so we run AFTER all plugins (incl. ACF) are ready,
        // but we also guard on function_exists inside register_groups().
        add_action('plugins_loaded', [$this, 'register_groups'], 20);
    }

    public function register_groups() {
        if (!function_exists('acf_add_local_field_group')) {
            // Log for debugging
            error_log('[XXDQ PIM] acf_add_local_field_group not available yet.');
            return;
        }

        acf_add_local_field_group([
            'key'      => 'group_product_media',
            'title'    => '产品媒体（由 PIM 管理）',
            'fields'   => [
                [
                    'key'          => 'field_real_image',
                    'name'         => 'real_image',
                    'label'        => '实物图（主图）',
                    'type'         => 'image',
                    'return_format'=> 'id',
                    'preview_size' => 'medium',
                    'library'      => 'all',
                    'instructions' => '产品的真实照片（白底/场景图）。显示在产品中心卡片与详情页。可通过 PIM Pack 上传 (命名: MODEL.jpg)',
                ],
                [
                    'key'          => 'field_tech_image',
                    'name'         => 'tech_image',
                    'label'        => '描述图（技术规格/手册截图）',
                    'type'         => 'image',
                    'return_format'=> 'id',
                    'preview_size' => 'medium',
                    'library'      => 'all',
                    'instructions' => '技术尺寸图 / 手册详细截图（带参数标注）。可在详情页展示。可通过 PIM Pack 上传 (命名: MODEL-spec.jpg)',
                ],
                [
                    'key'          => 'field_extra_image_2',
                    'name'         => 'image_2',
                    'label'        => '细节图 2',
                    'type'         => 'image',
                    'return_format'=> 'id',
                    'preview_size' => 'medium',
                    'library'      => 'all',
                    'instructions' => '可选：细节特写、应用场景、对比图等。留空则不显示在画廊中。命名建议: MODEL-2.jpg',
                ],
                [
                    'key'          => 'field_extra_image_3',
                    'name'         => 'image_3',
                    'label'        => '细节图 3',
                    'type'         => 'image',
                    'return_format'=> 'id',
                    'preview_size' => 'medium',
                    'library'      => 'all',
                    'instructions' => '可选。命名建议: MODEL-3.jpg',
                ],
                [
                    'key'          => 'field_extra_image_4',
                    'name'         => 'image_4',
                    'label'        => '细节图 4',
                    'type'         => 'image',
                    'return_format'=> 'id',
                    'preview_size' => 'medium',
                    'library'      => 'all',
                    'instructions' => '可选。命名建议: MODEL-4.jpg',
                ],
                [
                    'key'          => 'field_attachment',
                    'name'         => 'attachment',
                    'label'        => '资料 PDF',
                    'type'         => 'file',
                    'return_format'=> 'array',
                    'library'      => 'all',
                    'instructions' => '规格书 / 认证 / 安装说明 (命名: MODEL-spec.pdf / MODEL-cert.pdf / MODEL-install.pdf)。Free 版仅 1 个,Pro 版可升级到 repeater 多文件。',
                ],
            ],
            'location' => [[['param' => 'post_type', 'operator' => '==', 'value' => 'product']]],
            'position' => 'normal',
            'style'    => 'default',
        ]);
    }
}
