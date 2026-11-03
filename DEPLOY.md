# DEPLOY.md — Pre/Post Deploy Checklist

Last updated: **2026-11-03**

This site ships via **All-in-One WP Migration** (`.wpress` export from local → import on production).
The export bundles the WP database + `app/public/wp-content/{plugins,themes,uploads}` — that is the **entire** deployable surface.

> **Multilingual (2026-11-03)**: the Chinese site lives at `/` and the English site at `/en/`.
> Both are the SAME WordPress install and the SAME database — the English pages are parallel
> `wp_posts` rows / `wp_terms` rows linked via the `_xxdq_lang` / `_xxdq_zh_id` / `xxdq_zh_term_id`
> meta. A single AIO export therefore carries BOTH languages. There is no second install, no
> second database, and no separate deploy step for `/en/`.

---

## ✅ Pre-deploy (local) — already done 2026-09-27

### Webroot security
- [x] Removed `xxdq-products/tools/import-zsbef-catalog.php` — hardcoded `?key=xxdq-import-2026` URL-accessible bulk importer
- [x] Removed `xxdq-products/tools/migrate-2-level-category.php` — one-shot category migration, no auth
- [x] Removed `xxdq-products/tools/diag-media.php` — debug tool, no longer needed
- [x] Removed `xxdq-products/tools/flush-rewrites.php` — one-shot flush, no longer needed
- [x] Removed `xxdq-products/tools/read-option.php` — unauthenticated WP option reader
- [x] Removed `xxdq-products/tools/_flush-error.log` — debug log residue
- [x] Removed `xxdq-products/data/zsbef-catalog.php` — bulk-import data file

### Workspace cleanup
- [x] Moved 71 dev/debug files + 3 directories (`pdf_pages/`, `tmp-unsplash-probe/`, `scripts/`) into `_dev/`
- [x] Deleted `$null` empty artifact
- [x] Updated `.gitignore` to keep `_dev/` out of git + ignore new root-level junk

### Theme / plugin code
- [x] Logo image now rendered via `wp_get_attachment_image_src()` with inline style (no more nested `<a>` from `the_custom_logo()`)
- [x] Logo + brand text + tagline in nav, alt text is full Chinese company name
- [x] No hardcoded `localhost` or `127.0.0.1` references in any plugin/theme file
- [x] No `var_dump`/`print_r` in production-facing theme code

### Multilingual (English site under `/en/`)
- [x] Language switcher in the nav bar (`.xxp-nav__lang`) and in the footer (`.xxdq-footer__lang`)
      — the label always shows the TARGET language ("English" on the Chinese site, "中文" on the English site)
- [x] Switcher swaps the `/en/` prefix of the CURRENT path, so `/product/copper-lug/` ⇄ `/en/product/copper-lug/`
- [x] `<link rel="alternate" hreflang="…">` emitted on every page (zh-Hans / en / x-default)
- [x] `xxdq_company_contact()` is the single source of truth for phone / email / address / hours
      — footer, `xxdq/contact-cards` block and the LocalBusiness JSON-LD all read from it
- [x] English term links resolve to the public `/en/product-category/<parent>/<child>/` form
      (no `-2` slugs leaking into the Chinese sitemap)
- [x] No CJK characters leak into any `/en/` page (only the "中文" switcher label, by design)

---

## 🚀 Deploy

1. **Export locally** with All-in-One WP Migration → Backup → creates `localhost-YYYYMMDD-HHMMSS-XXXX.wpress`
2. **Upload to production** via AIO WP Migration → Import (or use the URL/FTP-from-file mode if file > upload limit)
3. **After import succeeds**, do the post-deploy cleanup below **before** going live

---

## 🧹 Post-deploy (production) — DO THESE after the import

These stay in the export by design (the plugin needs to be there to perform the import), but **must be removed** the moment the import finishes — both are exploitable:

```bash
# On the production server, via SSH or SFTP
rm -rf /var/www/html/wp-content/plugins/all-in-one-wp-migration
rm -rf /var/www/html/wp-content/ai1wm-backups
```

Also deactivate Yoast SEO (`wordpress-seo/`) if you don't want it on the production site — it shows untracked in git but is shipped inside the `.wpress`.

### Verify these in `wp-admin` of the production site

- **Settings → Permalinks** → just visit the page once (saves permalinks + flushes rewrites for the product CPT taxonomies)
- **Settings → Reading → Your homepage displays** → "A static page" → "首页" (page id 7 in the imported DB)
- **Appearance → Customize → Site Identity** → confirm logo URL isn't pointing back to `http://localhost:10004/...`
- **Settings → General → Site Title / Tagline** → confirm Chinese title isn't corrupted by encoding mismatch on the import
- **Settings → General → WordPress Address (URL) / Site Address (URL)** → MUST be the production domain, not `http://localhost:10004`

### Smoke test on production

```bash
# Replace $DOMAIN with the real production hostname
DOMAIN="example.com"
for u in / /product/ /?product_category=copper-terminals /?p=200; do
  curl -s -o /dev/null -w "%{http_code}  $DOMAIN$u\n" "https://$DOMAIN$u"
done
```

All four should return `200`. If `?product_category=copper-terminals` returns `404`, the permalink rewrite hasn't flushed — revisit Settings → Permalinks.

### Smoke test the English site on production

The `/en/` rewrite rule is registered by the `xxdq-theme` plugin, so it only exists after the
import. Test every route — a `404` here means the rewrite rules were never flushed.

```bash
DOMAIN="example.com"
for u in /en/ /en/about-us/ /en/contact-us/ /en/inquiry/ /en/product/ /en/news/ \
         /en/cable-lug-calculator/ /en/product-category/copper/ /en/product/dt-25/; do
  curl -s -o /dev/null -w "%{http_code}  $DOMAIN$u\n" "https://$DOMAIN$u"
done
```

All nine should return `200`.

**Critical**: if `/en/` returns 404 (or the Chinese home page), visit **Settings → Permalinks**
and hit Save — that is what regenerates the `^en(?:/(.*))?/?$` rewrite rule.

### Verify the language switcher + hreflang on production

```bash
DOMAIN="example.com"
curl -s "https://$DOMAIN/"    | grep -o 'xxp-nav__lang.\{0,300\}' | head -1
curl -s "https://$DOMAIN/en/" | grep -o 'hreflang="[^"]*"' | sort -u
```

Expected on `/en/`: `hreflang="en"`, `hreflang="zh-Hans"`, `hreflang="x-default"`.

### Verify no `-2` slugs leaked into the Chinese sitemap

```bash
DOMAIN="example.com"
curl -s "https://$DOMAIN/product_category-sitemap.xml" | grep -c 'copper-2'
```

Must be `0`. A non-zero count means the English parallel terms are polluting the Chinese
sitemap with duplicate, indexable URLs.

---

## 🔒 Production `.htaccess` hardening (recommended)

The default WP `.htaccess` works, but consider adding on top of it (above `# BEGIN WordPress`):

```apache
# Deny PHP execution in uploads
<Directory "wp-content/uploads">
  <FilesMatch "\.(php|php3|php4|php5|phtml|pl|py|jsp|asp|sh|cgi)$">
    Require all denied
  </FilesMatch>
</Directory>

# Deny access to debug files
<FilesMatch "(^#.*#|\.(bak|config|dist|fla|inc|ini|log|psd|sh|sql|sw[op])|~)$">
  Require all denied
</FilesMatch>
```

---

## 📋 Final file inventory

After this cleanup, the tracked/committed surface is:

```
app/public/wp-content/
├── plugins/
│   ├── advanced-custom-fields/    (ignored, ACF Pro not yet)
│   ├── wordpress-seo/             (untracked, decide if you want Yoast on prod)
│   ├── all-in-one-wp-migration/   (untracked, REMOVE ON PROD after import)
│   ├── akismet/                   (ignored)
│   ├── xxdq-theme/                (modified: logo + nav + i18n layer)
│   │   └── includes/
│   │       ├── i18n.php           ← language detection, translations, contact info, hreflang
│   │       └── lang/              ← router.php · content.php · translator.php · cat-desc.php
│   ├── xxdq-products/             (modified: templates + flush-rewrites now in _dev)
│   ├── xxdq-pim/                  (modified)
│   └── xxdq-blocks/               (modified)
├── mu-plugins/
│   └── xxdq-seo/20-schema.php     ← LocalBusiness JSON-LD (localised for /en/)
└── ai1wm-backups/                 (untracked, REMOVE ON PROD)
```
