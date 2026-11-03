<?php
/**
 * Inquiry form submission handler.
 *
 * Hooks admin-post.php?action=xxdq_inquiry_submit.
 * Writes to wp_options as a simple in-DB inbox (for dev/MVP).
 * Production should integrate with SMTP / CRM.
 */

if (!defined('ABSPATH')) exit;

class XXDQ_Blocks_Inquiry {

    private static $instance;

    public static function instance() {
        if (!self::$instance) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_post_xxdq_inquiry_submit',        [$this, 'handle']);
        add_action('admin_post_nopriv_xxdq_inquiry_submit', [$this, 'handle']);
        add_action('admin_menu', [$this, 'admin_menu']);
    }

    public function admin_menu() {
        add_submenu_page(
            'edit.php?post_type=product',
            '询价单收件箱',
            '询价单收件箱',
            'manage_options',
            'xxdq-inquiry-inbox',
            [$this, 'render_inbox']
        );
    }

    public function render_inbox() {
        if (!current_user_can('manage_options')) wp_die('Permission denied');
        $items = get_option('xxdq_inquiry_inbox', []);
        echo '<div class="wrap"><h1>询价单收件箱</h1>';
        if (empty($items)) {
            echo '<p>暂无询价单。</p>';
        } else {
            echo '<table class="widefat striped"><thead><tr><th>时间</th><th>姓名</th><th>公司</th><th>电话</th><th>邮箱</th><th>产品</th><th>内容</th><th>语言</th></tr></thead><tbody>';
            foreach (array_reverse($items) as $i) {
                $lang = isset($i['lang']) && $i['lang'] === 'en_US' ? '英文 EN' : '中文 ZH';
                echo '<tr><td>' . esc_html($i['time']) . '</td><td>' . esc_html($i['name']) . '</td><td>' . esc_html($i['company'] ?? '') . '</td><td>' . esc_html($i['phone']) . '</td><td>' . esc_html($i['email'] ?? '') . '</td><td>' . esc_html($i['product'] ?? '') . '</td><td>' . esc_html($i['message']) . '</td><td>' . esc_html($lang) . '</td></tr>';
            }
            echo '</tbody></table>';
        }
        echo '</div>';
    }

    public function handle() {
        if (!wp_verify_nonce($_POST['xxdq_inquiry_nonce'] ?? '', 'xxdq_inquiry_submit')) {
            wp_die('Security check failed');
        }
        $name    = sanitize_text_field($_POST['name'] ?? '');
        $phone   = sanitize_text_field($_POST['phone'] ?? '');
        $message = sanitize_textarea_field($_POST['message'] ?? '');
        $company = sanitize_text_field($_POST['company'] ?? '');
        $email   = sanitize_email($_POST['email'] ?? '');
        $product = sanitize_text_field($_POST['product'] ?? '');

        // Which language did the visitor fill the form in?
        $lang = isset($_POST['xxdq_inquiry_lang']) && $_POST['xxdq_inquiry_lang'] === 'en_US'
            ? 'en_US'
            : 'zh_CN';

        if (!$name || !$phone || !$message) {
            wp_die('请填写必填字段（姓名、电话、询价内容）');
        }

        $entry = [
            'time'    => current_time('Y-m-d H:i:s'),
            'name'    => $name,
            'company' => $company,
            'phone'   => $phone,
            'email'   => $email,
            'product' => $product,
            'message' => $message,
            'lang'    => $lang,
        ];

        $items = get_option('xxdq_inquiry_inbox', []);
        $items[] = $entry;
        update_option('xxdq_inquiry_inbox', $items);

        /* Send email notification (best-effort).
         *
         * An English inquiry is still delivered in English — but the
         * subject line is prefixed with 【英文询盘】 so the sales team
         * can triage it at a glance, and the Chinese field labels are
         * kept so a Chinese-speaking colleague can read it without
         * translating. */
        $to   = get_option('admin_email');
        $tag  = ($lang === 'en_US') ? '[英文询盘 EN inquiry]' : '[询价单]';
        $subj = "{$tag} {$name} - {$product}";
        $body = "时间 Time: {$entry['time']}\n"
              . "姓名 Name: {$name}\n"
              . "公司 Company: {$company}\n"
              . "电话 Phone: {$phone}\n"
              . "邮箱 Email: {$email}\n"
              . "产品 Product: {$product}\n"
              . "内容 Message: {$message}\n"
              . "语言 Language: " . ($lang === 'en_US' ? '英文（English）' : '中文（Chinese）');
        @wp_mail($to, $subj, $body);

        // Redirect with success — always land on the canonical inquiry
        // page, never on the legacy /询价单/ Chinese slug (which 404s on
        // the live site because the page's actual slug is `inquiry`).
        $ref = wp_get_referer();
        $default_target = function_exists('XXDQ_Blocks_Render::inquiry_url')
            ? XXDQ_Blocks_Render::inquiry_url()
            : home_url('/inquiry/');
        if (!$ref) $ref = $default_target;
        wp_safe_redirect(add_query_arg('submitted', '1', $ref));
        exit;
    }
}

// Auto-bootstrap if class loaded
XXDQ_Blocks_Inquiry::instance();
