<?php
/**
 * Admin UI for PIM — Import / Export pages.
 *
 * Submenu: Tools → Import Pack / Export Pack.
 */

if (!defined('ABSPATH')) exit;

class XXDQ_PIM_Admin {

    private static $instance;

    public static function instance() {
        if (!self::$instance) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu',            [$this, 'menu']);
        add_action('admin_post_xxdq_pim_import', [$this, 'handle_import']);
        add_action('admin_post_xxdq_pim_export', [$this, 'handle_export']);
        add_action('admin_post_xxdq_pim_export_single', [$this, 'handle_export_single']);
        add_action('admin_notices',         [$this, 'show_notices']);
        add_filter('post_row_actions',      [$this, 'row_action_single_export'], 10, 2);
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
    }

    public function menu() {
        add_submenu_page(
            'edit.php?post_type=product',
            'Import Pack',
            'Import Pack',
            XXDQ_PIM_CAP,
            'xxdq-pim-import',
            [$this, 'page_import']
        );
        add_submenu_page(
            'edit.php?post_type=product',
            'Export Pack',
            'Export Pack',
            XXDQ_PIM_CAP,
            'xxdq-pim-export',
            [$this, 'page_export']
        );
    }

    public function enqueue($hook) {
        if (strpos($hook, 'xxdq-pim') === false) return;
        wp_enqueue_style('xxdq-pim-admin', XXDQ_PIM_URL . 'assets/admin.css', [], XXDQ_PIM_VERSION);
        wp_enqueue_script('xxdq-pim-admin', XXDQ_PIM_URL . 'assets/admin.js', ['jquery'], XXDQ_PIM_VERSION, true);
    }

    public function row_action_single_export($actions, $post) {
        if ($post->post_type !== 'product') return $actions;
        if (!current_user_can(XXDQ_PIM_CAP)) return $actions;
        $url = wp_nonce_url(
            admin_url('admin-post.php?action=xxdq_pim_export_single&post_id=' . $post->ID),
            'xxdq_pim_export_single_' . $post->ID
        );
        $actions['xxdq_export'] = '<a href="' . esc_url($url) . '">Export Pack</a>';
        return $actions;
    }

    /* ── Page: Import ──────────────────────────────────────── */

    public function page_import() {
        if (!current_user_can(XXDQ_PIM_CAP)) {
            wp_die('Permission denied');
        }
        $last_result = get_transient('xxdq_pim_last_import_result');
        if ($last_result) delete_transient('xxdq_pim_last_import_result');
        ?>
        <div class="wrap xxdq-pim-wrap">
            <h1>Import Product Pack</h1>
            <p class="description">Upload a ZIP pack containing <code>products.csv</code>, <code>images/</code>, <code>pdfs/</code>.</p>

            <?php if ($last_result): ?>
                <?php $this->render_result($last_result); ?>
            <?php endif; ?>

            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" enctype="multipart/form-data" class="xxdq-pim-form">
                <input type="hidden" name="action" value="xxdq_pim_import">
                <?php wp_nonce_field('xxdq_pim_import'); ?>

                <table class="form-table">
                    <tr>
                        <th><label for="pack_file">Pack ZIP</label></th>
                        <td>
                            <input type="file" name="pack_file" id="pack_file" accept=".zip" required>
                            <p class="description">最大 <?php echo size_format(wp_max_upload_size()); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th>模式</th>
                        <td>
                            <label><input type="radio" name="mode" value="upsert" checked> Upsert（推荐，model 存在则更新，不存在则新建）</label><br>
                            <label><input type="radio" name="mode" value="insert"> Insert only（仅新建，跳过已存在）</label><br>
                            <label><input type="radio" name="mode" value="update"> Update only（仅更新已存在）</label>
                        </td>
                    </tr>
                    <tr>
                        <th>选项</th>
                        <td>
                            <label><input type="checkbox" name="dry_run" value="1"> 干运行（不写库，只校验）</label>
                        </td>
                    </tr>
                </table>

                <p>
                    <button type="submit" class="button button-primary">开始导入</button>
                    <a href="<?php echo admin_url('edit.php?post_type=product&page=xxdq-pim-export'); ?>" class="button">导出包</a>
                    <a href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=xxdq_pim_export_single&post_id=0&sample=1'), 'xxdq_pim_export_single_0'); ?>" class="button">下载 CSV 模板</a>
                </p>
            </form>

            <hr>
            <h2>Pack 结构说明</h2>
            <pre class="xxdq-pim-tree">xxdq-products-2026-09-22.zip
├── products.csv        (必填，UTF-8，UTF-8 BOM 可选)
├── images/             (可选)
│   ├── MODEL.jpg       主图（命名 = 产品型号）
│   └── MODEL-tech.jpg  技术图
├── pdfs/               (可选)
│   ├── MODEL-spec.pdf      规格书
│   ├── MODEL-cert.pdf      认证证书
│   └── MODEL-install.pdf   安装说明
└── manifest.json       (可选，包元数据)</pre>
        </div>
        <?php
    }

    /* ── Page: Export ──────────────────────────────────────── */

    public function page_export() {
        if (!current_user_can(XXDQ_PIM_CAP)) {
            wp_die('Permission denied');
        }
        $categories = get_terms(['taxonomy' => 'product_category', 'hide_empty' => false]);
        ?>
        <div class="wrap xxdq-pim-wrap">
            <h1>Export Product Pack</h1>

            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" class="xxdq-pim-form">
                <input type="hidden" name="action" value="xxdq_pim_export">
                <?php wp_nonce_field('xxdq_pim_export'); ?>

                <table class="form-table">
                    <tr>
                        <th>分类筛选</th>
                        <td>
                            <select name="category[]" multiple size="8" style="min-width:320px">
                                <option value="">— 全部 —</option>
                                <?php foreach ($categories as $c): ?>
                                    <option value="<?= esc_attr($c->term_id) ?>"><?= esc_html($c->name) ?> (<?= $c->count ?>)</option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">支持选择二级子分类精确筛选。</p>
                        </td>
                    </tr>
                    <tr>
                        <th>格式</th>
                        <td>
                            <label><input type="radio" name="format" value="pack" checked> ZIP Pack（含图+PDF）</label><br>
                            <label><input type="radio" name="format" value="csv"> 仅 CSV</label><br>
                            <label><input type="radio" name="format" value="json"> JSON</label>
                        </td>
                    </tr>
                    <tr>
                        <th>媒体</th>
                        <td>
                            <label><input type="checkbox" name="include_media" value="1" checked> 包含图片和 PDF</label>
                        </td>
                    </tr>
                </table>

                <p>
                    <button type="submit" class="button button-primary">生成并下载</button>
                </p>
            </form>
        </div>
        <?php
    }

    /* ── Handlers ──────────────────────────────────────────── */

    public function handle_import() {
        if (!current_user_can(XXDQ_PIM_CAP)) wp_die('Permission denied');
        check_admin_referer('xxdq_pim_import');

        if (empty($_FILES['pack_file']['tmp_name'])) {
            $this->notice('error', 'No file uploaded.');
            return;
        }
        $file = $_FILES['pack_file'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $this->notice('error', 'Upload error: ' . $file['error']);
            return;
        }

        $mode = $_POST['mode'] ?? 'upsert';
        $dry_run = !empty($_POST['dry_run']);

        $result = XXDQ_PIM_Importer::instance()->import($file['tmp_name'], [
            'mode'    => $mode,
            'dry_run' => $dry_run,
        ]);

        set_transient('xxdq_pim_last_import_result', $result, MINUTE_IN_SECONDS * 5);
        wp_safe_redirect(admin_url('edit.php?post_type=product&page=xxdq-pim-import'));
        exit;
    }

    public function handle_export() {
        if (!current_user_can(XXDQ_PIM_CAP)) wp_die('Permission denied');
        check_admin_referer('xxdq_pim_export');

        $args = [
            'category'      => $_POST['category'] ?? null,
            'series'        => $_POST['series'] ?? null,
            'format'        => $_POST['format'] ?? 'pack',
            'include_media' => !empty($_POST['include_media']),
        ];
        $path = XXDQ_PIM_Exporter::bulk($args);
        if (!$path || !file_exists($path)) wp_die('Export failed');

        $this->stream_download($path, basename($path));
        // Cleanup: stream then delete (file is already saved by user)
        @unlink($path);
        exit;
    }

    public function handle_export_single() {
        if (!current_user_can(XXDQ_PIM_CAP)) wp_die('Permission denied');
        $post_id = (int) ($_GET['post_id'] ?? 0);

        // CSV template download
        if (isset($_GET['sample'])) {
            check_admin_referer('xxdq_pim_export_single_0');
            $this->stream_csv_template();
            exit;
        }
        if (!$post_id) wp_die('Missing post_id');
        check_admin_referer('xxdq_pim_export_single_' . $post_id);

        $path = XXDQ_PIM_Exporter::single($post_id);
        $model = get_field('model', $post_id) ?: get_the_title($post_id);
        $this->stream_download($path, sanitize_title($model) . '.zip');
        @unlink($path);
        exit;
    }

    /* ── Helpers ───────────────────────────────────────────── */

    private function stream_download($path, $name) {
        if (ob_get_level()) ob_end_clean();
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: no-cache');
        readfile($path);
    }

    private function stream_csv_template() {
        if (ob_get_level()) ob_end_clean();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="products-template.csv"');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // BOM
        fputcsv($out, XXDQ_PIM_CSV::header());
        // Example row
        fputcsv($out, [
            'DT-25', '25mm²', 'M8', 'T2紫铜', '镀锡', '',
            '600V', '-40~+105°C', 'CE / RoHS', '100只/袋',
            'DT 系列铜鼻子，标准型镀锡。', '铜鼻子', 'DT',
            'publish',
        ]);
        fclose($out);
    }

    private function notice($type, $msg) {
        set_transient('xxdq_pim_notice', ['type' => $type, 'msg' => $msg], MINUTE_IN_SECONDS);
    }

    public function show_notices() {
        $n = get_transient('xxdq_pim_notice');
        if (!$n) return;
        delete_transient('xxdq_pim_notice');
        echo '<div class="notice notice-' . esc_attr($n['type']) . ' is-dismissible"><p>' . esc_html($n['msg']) . '</p></div>';
    }

    private function render_result($r) {
        ?>
        <div class="xxdq-pim-result notice notice-<?= $r['failed'] > 0 ? 'warning' : 'success' ?>">
            <h3>导入结果</h3>
            <p>
                <strong>总计：</strong> <?= $r['total'] ?? 0 ?> 行 · 
                <strong>成功：</strong> <?= $r['success'] ?? 0 ?> · 
                <strong>已新增：</strong> <?= $r['inserted'] ?? 0 ?> · 
                <strong>已更新：</strong> <?= $r['updated'] ?? 0 ?> · 
                <strong>失败：</strong> <?= $r['failed'] ?? 0 ?> · 
                <strong>跳过：</strong> <?= $r['skipped'] ?? 0 ?>
                <?php if (!empty($r['dry_run'])): ?> · <em style="color:#d00">干运行，未写入数据库</em><?php endif; ?>
            </p>
            <?php if (!empty($r['log'])): ?>
                <details>
                    <summary>详细日志 (<?= count($r['log']) ?> 条)</summary>
                    <table class="widefat striped" style="margin-top:8px">
                        <thead><tr><th>状态</th><th>型号</th><th>信息</th></tr></thead>
                        <tbody>
                        <?php foreach ($r['log'] as $row): ?>
                            <tr>
                                <td><code><?= esc_html($row['status']) ?></code></td>
                                <td><?= esc_html($row['model'] ?: '—') ?></td>
                                <td><?= esc_html($row['message']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </details>
            <?php endif; ?>
        </div>
        <?php
    }
}
