<?php
/**
 * XXDQ i18n — slug redirects.
 *
 * The slug-collision repair (tools/repair-slug-collisions.php) renames
 * product slugs so that every product has its own public URL. The old
 * URLs must keep working, otherwise:
 *
 *   - the link equity Google already collected on /product/dt-25/ is lost
    10| *   - inbound links from partner sites and printed catalogues break
 *   - the old URL keeps competing with the new one in the SERPs
 *
 * The redirect table lives in the `xxdq_slug_redirects` option, which
 * the repair script writes. It is read here on `template_redirect`.
 *
 * STORAGE FORMAT
 * --------------
 * The table is keyed by the REQUEST PATH (always with a leading slash,
 * with or without a trailing slash) and the value is the TARGET PATH.
 * Both sides are stored as paths, never as absolute URLs, because:
 *
 *   - the site URL changes between local, staging and production, so an
 *     absolute URL stored in the database would point at localhost once
 *     the site is deployed,
 *   - matching is done on the request path, which is what the visitor
 *     actually asked for.
 *
 * The lookup therefore normalises BOTH the stored keys and the incoming
 * request path to a canonical form (leading slash, no trailing slash
 * except for the root) before comparing them.
 *
 * @package XXDQ
 */

if (!defined('ABSPATH')) exit;

class XXDQ_I18n_Redirects {

    /**
    20|     * The redirect table, normalised to path => path.
     *
     * Legacy rows (written before this normalisation, keyed by an
     * absolute URL) are converted on the fly so that an existing
     * production database does not need a migration.
     *
     * @return array Map of old path => new path.
     */
    public static function map() {
        $raw = get_option('xxdq_slug_redirects', array());
        if (!is_array($raw)) {
            return array();
        }

        $map = array();
        foreach ($raw as $from => $to) {
            $from = self::to_path($from);
            $to   = self::to_path($to);
            if ($from === '' || $to === '' || $from === $to) {
                continue;
            }
            $map[$from] = $to;
        }

        return $map;
    }

    /**
    50|     * Reduce a URL or path to its canonical path form.
     *
     * "http://site.com/product/dt-25/"  →  "/product/dt-25"
     * "/product/dt-25/"                 →  "/product/dt-25"
     * "/"                               →  "/"
     *
     * @param string $url URL or path.
     * @return string Canonical path, or '' when nothing usable is left.
     */
    private static function to_path($url) {
        $url = trim((string) $url);
        if ($url === '') {
            return '';
        }

        /* Strip scheme + host if an absolute URL was stored. */
        if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $url)) {
            $parts = parse_url($url);
            if (!is_array($parts) || !isset($parts['path'])) {
                return '';
            }
            $url = $parts['path'];
        }

        /* Drop any query string / fragment. */
        $url = preg_replace('/[?#].*$/', '', $url);

        $url = '/' . ltrim($url, '/');
        if ($url !== '/') {
            $url = rtrim($url, '/');
        }

        return $url === '' ? '/' : $url;
    }

    /**
    85|     * Issue the redirect when the request matches a retired path.
     */
    public static function handle() {
        if (is_admin() || wp_doing_ajax()) {
            return;
        }

        $uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '';
        if ($uri === '') {
            return;
        }

        $path = parse_url($uri, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            return;
        }

        $path = self::to_path($path);

        $map = self::map();
        if (empty($map)) {
            return;
        }

        if (isset($map[$path])) {
            self::redirect($map[$path]);
        }
    }

    /**
   115|     * Send the 301 and stop.
     *
     * @param string $target Target path (relative to the site root).
     */
    private static function redirect($target) {
        $target = (string) $target;

        /* A relative target is resolved against the site root. */
        if (strpos($target, 'http') !== 0) {
            $target = home_url($target);
        }

        /* Never redirect a URL to itself. */
        $current = home_url(isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '/');
        if ($current === $target || rtrim($current, '/') === rtrim($target, '/')) {
            return;
        }

        wp_safe_redirect($target, 301);
        exit;
    }
}

add_action('template_redirect', array('XXDQ_I18n_Redirects', 'handle'), 5);
