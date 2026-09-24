# XX电气 — WordPress B2B 电气连接件网站

A custom WordPress site for a Chinese B2B electrical connector manufacturer (XX电气).
Built on GeneratePress + ACF + two custom plugins.

---

## 🏭 Site Identity

| Field     | Value                                                                |
|-----------|----------------------------------------------------------------------|
| Site name | XX电气 — 电气连接件源头工厂                                           |
| Tagline   | 铜鼻子、冷压端子、预绝缘端子、接插件 \| 规格齐全·现货直发·源头工厂价 |
| Local URL | http://localhost:10004/                                             |
| Admin     | http://localhost:10004/wp-admin/                                      |

---

## 🔐 Local Dev Credentials

LocalWP dev environment. **Plain-text on purpose** — local-only, no git, no production exposure.

| Service       | URL                              | User  | Password                       |
|---------------|----------------------------------|-------|--------------------------------|
| WordPress     | http://localhost:10004/wp-admin/ | admin | `x4Dc ebxj jEMt Bp0z Apcz BOWg` |
| MySQL (Local) | localhost:3306                   | root  | root                           |
| DB name       | local                            | —     | —                              |

If password has spaces (e.g. pasted from a 1Password generator), enter it as one string — the spaces may be a password-manager artifact, confirm on next login.

## 🧱 Tech Stack

| Component     | Version | Purpose                          |
|---------------|---------|----------------------------------|
| WordPress     | 7.1.1   | CMS                              |
| PHP           | 8.2.27  | Backend                          |
| GeneratePress | 3.5.1   | Base theme (overridden by plugins) |
| ACF (Free)    | 6.3.9   | Custom fields on products        |

Server: LocalWP (Nginx 1.26.1 + PHP-FPM + MariaDB).

---

## 📁 File Structure

```
E:\work\wordPress\
├── README.md                              ← this file
└── app\public\
    ├── readme.html                        ← branded site info page
    └── wp-content\
        ├── plugins\
        │   ├── xxdq-theme\               ← homepage, nav, footer, styles
        │   │   ├── xxdq-theme.php
        │   │   ├── readme.txt
        │   │   └── assets\
        │   │       ├── css\main.css      ← ~30KB
        │   │       └── js\main.js        ← mobile menu + animations
        │   └── xxdq-products\            ← CPT + all inner pages
        │       ├── xxdq-products.php      ← ~1200 lines
        │       └── readme.txt
        └── themes\
            └── generatepress\            ← installed; overridden by plugins
```

---

## 🗂️ Pages (11 — all HTTP 200)

| ID | Title      | Size  | Purpose                                                     |
|----|------------|-------|-------------------------------------------------------------|
| 7  | 首页        | 65KB  | Static front page (custom homepage)                          |
| 8  | 产品中心    | —     | Catalog index link from nav                                  |
| 9  | 铜鼻子      | 74KB  | Copper terminals — 4 series accordion (29 products)           |
| 10 | 冷压端子    | 81KB  | Cold-press — 5 series accordion (39 products)                 |
| 11 | 预绝缘端子  | 61KB  | Insulated — 5 series accordion (23 products)                  |
| 12 | 接插件      | 69KB  | Connectors — 4 series accordion (20 products)                 |
| 13 | 选型工具    | 135KB | Wire-gauge selector with GET-param filtering                 |
| 14 | 技术资料    | 57KB  | Downloads: spec sheets, certs, current table, color chart     |
| 15 | 关于我们    | 54KB  | Company intro, advantages grid, service commitments             |
| 16 | 联系我们    | 53KB  | Contact cards + CTA                                           |
| 17 | 询价单      | 54KB  | RFQ form with nonce validation + success state                 |

---

## Custom Post Type: `product`

| ACF Field         | Type     | Example                    |
|-------------------|----------|----------------------------|
| `model`           | text     | "DT-25"                   |
| `wire_range`      | text     | "25mm²"                   |
| `hole_size`       | text     | "M8"                      |
| `material`        | text     | "T2紫铜"                  |
| `finish`          | text     | "镀锡"                    |
| `color_code`      | text     | "蓝色"                    |
| `voltage`         | text     | "600V"                    |
| `temperature`     | text     | "-40~+105°C"              |
| `certification`    | text     | "CE / RoHS"               |
| `package`         | text     | "100只/袋"                |
| `short_desc`      | textarea | marketing blurb            |
| `tech_image`      | image    | engineering drawing (PIM) |
| `attachment`      | file     | datasheet PDF (PIM, ACF Free single file) |

### Taxonomies

| Taxonomy           | Slug          | Terms                                                                         |
|-------------------|---------------|-------------------------------------------------------------------------------|
| `product_category` | product-cat   | 铜鼻子 / 冷压端子 / 预绝缘端子 / 接插件                                       |
| `product_series`   | series        | DT / DTG / SC / OT / E / TE / VE / SV / RV / BV / TV / PV / 插簧 / 子弹头 / 端子排 / 航空插 |

### Seeded Products: 111

| Category         | # | Notes                                              |
|------------------|---|----------------------------------------------------|
| 铜鼻子           | 29| DT/DTG/SC/OT × 8 wire sizes                       |
| 冷压端子         | 39| E/TE/VE × 8 sizes + SV × 9 hole sizes + RV × 9 hole sizes |
| 预绝缘端子       | 23| BV/SV/RV/TV/PV each color-coded (red/blue/yellow)|
| 接插件           | 20| 插簧 5 + 子弹头 6 + UK端子排 6 + GX航空插 4       |

---

## 🎨 Visual Design

- **Palette**: Navy blue (#1565C0) + orange (#F57C00) + neutral grays
- **Fonts**: Noto Sans SC + Roboto (Google Fonts)
- **Nav**: Fixed 64px header, 产品中心 dropdown, mobile burger
- **Responsive**: 1024 / 900 / 768 / 640 / 480 px breakpoints
- **Imagery**: Auto-generated SVG per product at `/?xxdq_img=MODEL&cat=SLUG`
- **Pages**: Accordion series cards, sticky filter bar, spec table, contact cards, RFQ form

---

## 🔌 REST API

```
GET /wp-json/wp/v2/product             # list (paginated)
GET /wp-json/wp/v2/product?slug=dt-25
GET /wp-json/wp/v2/product_category
GET /wp-json/wp/v2/product_series
GET /wp-json/wp/v2/pages
```

---

## 🛠 Maintenance

### Flush rewrite rules (after CPT changes)
Visit: Settings → Permalinks → Save (or re-save permalink structure)

### Re-run product seeder (regenerate products)
Recreate `mu-plugins/xxdq-seed.php` and visit `/?xxdq_seed=run`.

### Adding products manually
1. Admin → Products → Add New
2. Title = model name
3. Right sidebar: select 产品分类 + 系列
4. Fill ACF fields below editor
5. Publish → URL: `/product/{model-slug}/`

---

## 📦 Product Pack Importer (xxdq-pim)

Bulk import/export products with images and PDFs as a single ZIP.

### Pack structure
```
xxdq-products-YYYY-MM-DD.zip
├── products.csv          (UTF-8, BOM optional)
├── images/               (optional)
│   ├── MODEL.jpg         main image (filename = model)
│   └── MODEL-tech.jpg    tech image
├── pdfs/                 (optional)
│   ├── MODEL-spec.pdf    datasheet
│   ├── MODEL-cert.pdf    certificate
│   └── MODEL-install.pdf installation
└── manifest.json         (optional metadata)
```

### CSV columns (header row required)
```
model, wire_range, hole_size, material, finish, color_code,
voltage, temperature, certification, package, short_desc,
category, series, status
```
- `category` / `series` accept pipe-separated multi-values: `铜鼻子|冷压端子`
- Missing terms are auto-created on import.

### Admin pages
- Products → **Import Pack** (upload ZIP, choose upsert/insert/update, optional dry-run)
- Products → **Export Pack** (filter by category/series, choose format, download ZIP)
- Products list → **hover row → Export Pack** (single product)

### Generate a sample pack
```bash
wp eval-file app/public/wp-content/plugins/xxdq-pim/tools/build-sample-pack.php
```
Writes a 10-product sample ZIP to `wp-content/uploads/xxdq-pim-samples/sample-pack.zip`.

### Auto-sync (no manual work needed)
After import, all pages auto-update because every page queries the DB on each request (no caching layer).
- New product → appears in category page series accordion
- New image → replaces SVG fallback on product card
- New PDF → appears on product detail AND on 技术资料 page (dynamic aggregation)

---

## 🧱 ACF Blocks (xxdq-blocks)

Gutenberg blocks for the page-builder. Two categories:

### Static blocks (content stored on page)
| Block                | Purpose                                        |
|----------------------|------------------------------------------------|
| `xxdq/hero`          | hero banner (eyebrow / title / sub / CTA)      |
| `xxdq/category-grid` | 4-card grid linking to product categories      |
| `xxdq/why-us`        | advantages grid (icon + title + desc)          |
| `xxdq/cta`           | call-to-action banner                          |
| `xxdq/contact-cards` | contact info cards (phone / email / wechat / address) |
| `xxdq/about`         | about-us content block                         |
| `xxdq/tech-docs`     | tech documentation cards                       |

### Dynamic blocks (re-query DB on each render)
| Block                  | Purpose                                            |
|------------------------|----------------------------------------------------|
| `xxdq/hot-products`    | random 8 products (homepage section)               |
| `xxdq/category-archive`| single category page (hero + series accordion)     |
| `xxdq/series-accordion`| series + product table within category             |
| `xxdq/product-detail`  | single product page (image + spec + PDF + related) |
| `xxdq/selector-tool`   | wire-gauge selector (GET-param filtering)          |
| `xxdq/inquiry-form`    | RFQ form with nonce + email + DB inbox             |
| `xxdq/pdf-aggregator`  | dynamic PDF list grouped by category               |

### Migration strategy (legacy → blocks)

The legacy `xxdq-products.php` renders pages via `the_content` filter overrides (e.g. `if (get_the_title() === '铜鼻子') return xxdq_render_category_page(...);`).

The new `xxdq-blocks` plugin provides equivalent blocks for **migration**:

### Block → page mapping (Phase 5: only manual editing)

All page rendering now goes through the Gutenberg editor using xxdq/* blocks.
Legacy hardcoded filters from xxdq-products.php and xxdq-theme.php have been **removed**.

Block registration uses **native WordPress** `register_block_type()` (NOT ACF Pro blocks), so the plugin works with the free "Secure Custom Fields" plugin (which doesn't have `acf_register_block_type()`).

| Page                | Blocks to add in editor                          |
|---------------------|--------------------------------------------------|
| 首页                | hero (immersive, stats embedded at bottom) → category-grid → hot-products → process-steps → why-us → cta |
| 铜鼻子 / 冷压端子 / 预绝缘端子 / 接插件 | category-archive (set category)            |
| 选型工具             | selector-tool                                     |
| 技术资料             | pdf-aggregator + tech-docs                        |
| 关于我们             | about                                             |
| 联系我们             | contact-cards                                     |
| 询价单               | inquiry-form                                      |

### How block values flow (no ACF Pro needed)

1. **Static block defaults** — defined in PHP render functions (e.g. hero title = "铜鼻子 / 冷压端子 / ...")
2. **Block JSON attributes** — embedded in the block comment when saved in editor; values take priority over defaults
3. **ACF field groups** — declared via `acf_add_local_field_group()` (free SCF supports this). Sidebar editor for block fields requires ACF Pro; until then, edit values by:
   - Editing block JSON attributes directly in code editor, OR
   - Setting post meta values manually via REST API / WP Admin → Custom Fields panel

The render wrapper extracts block attributes into `XXDQ_Blocks_Render::$current_attrs` so render functions can read them via `XXDQ_Blocks_Render::val($field, $post_id, $default)`.

### How to edit any page now

1. **激活 `xxdq-blocks` 插件**（如果还没激活）
2. WP Admin → Pages → 点击要编辑的页面
3. 在 Gutenberg 编辑器中点击 ➕ 添加块 → 搜索 `xxdq/` → 选择需要的块
4. 设置块参数 → 发布
5. 前台立刻看到（无缓存层，块每次请求重新渲染）

### Auto-sync (dynamic blocks always re-query DB)

Dynamic blocks render via `render_callback` on **every page load**, so:
- PIM import → blocks pick up new products automatically
- ACF field change → block output updates next page load
- No cache layer = no manual cache-bust needed

---

## 📞 Contact Placeholders


Replace these before going live:

| Field    | Current value     |
|----------|-------------------|
| Phone    | 400-XXX-XXXX      |
| Email    | sales@xxdq.com    |
| WeChat   | XXDQ123456        |
| Address  | 浙江省乐清市柳市镇…|
| ICP      | 沪ICP备XXXXXXXX号   |

---

## 🧾 Versions

### Phase 9: 8-section homepage redesign (planned)

Goal: rebuild the homepage to match the zsbef.com reference (8 sections), with **fully admin-managed content** via a new "网站设置" ACF options page.

#### Final block sequence

```
1.  Header (nav + CTA)
2.  Hero Slider          ← NEW (ACF repeater)
3.  Category Grid        ← already dynamic from taxonomy
4.  Why Us (advantages)  ← content via options page
5.  Application Fields   ← NEW (ACF repeater)
6.  News Section         ← NEW (query from CPT)
7.  Footer (3 columns)   ← EXPANDED
8.  Copyright bar
```

#### New files / changes

| Change | File | Purpose |
|---|---|---|
| **NEW** | `xxdq-blocks/blocks/hero-slider/` | Hero slider block — ACF repeater (image + title + subtitle + button) |
| **NEW** | `xxdq-blocks/blocks/application-fields/` | Application industries block — icon + title + desc (ACF repeater) |
| **NEW** | `xxdq-blocks/blocks/news-section/` | News preview block — queries latest `news` CPT posts |
| **MODIFY** | `xxdq-blocks/class-blocks-render.php` | Add 3 render functions; keep category-grid dynamic |
| **MODIFY** | `xxdq-theme/xxdq-theme.php` | Expand footer to 3 columns (company / quick links / main products) |
| **NEW** | `xxdq-blocks/cpts/news.php` | Register `news` CPT (separate from `product`) |
| **MODIFY** | `xxdq-blocks/assets/css/main.css` | Add styles for new blocks (slider, app fields, news) |
| **MODIFY** | `xxdq-blocks/assets/js/main.js` | Add slider auto-play + manual indicators |
| **NEW** | `xxdq-blocks/admin/options-page.php` | ACF options page "网站设置" (Banner, hero, company info, ICP) |
| **MODIFY** | `xxdq-blocks/xxdq-blocks.php` | Include new files + register options page |
| **MODIFY** | WP Admin homepage (ID=7) | Replace block sequence with 8-section layout |

#### Admin UI (new "网站设置" page)

| Tab | Fields |
|---|---|
| Banner | Repeater: image / title / subtitle / button text / button link (max 5) |
| 英雄区副标题 | Text + textarea (subtitle, description) |
| 公司信息 | Name / address / phone / email / wechat |
| 备案信息 | ICP number |
| 应用领域 | Repeater: icon / title / description (max 8) |
| 新闻设置 | Display count (default 3), "more" link |

#### Risk + mitigation

| Risk | Mitigation |
|---|---|
| No git history | Backup `xxdq-blocks` + `xxdq-theme` to `.refactor-backup-20260924-XXXX/` before editing |
| Mobile responsiveness | Each new block has explicit `@media (max-width:768px)` rules |
| News CPT slug collision | Use `news` slug (not `post`); flush rewrite rules after registration |
| ACF repeater max | Set `max: 5` for Banner / `max: 8` for Application fields to avoid runaway fields |

#### How to edit after this phase

| Task | Where | Difficulty |
|---|---|---|
| Change banner image | Admin → 网站设置 → Banner | Easy (upload image) |
| Update company phone/address | Admin → 网站设置 → 公司信息 | Easy (text field) |
| Add a news post | Admin → 新闻 → 写文章 | Easy (like writing a blog post) |
| Add a product category | Admin → 产品分类 → 新增 | Easy (already supported in Phase 6) |
| Update ICP number | Admin → 网站设置 → 备案信息 | Easy |

---

| Date       | Change                                                                    |
|------------|---------------------------------------------------------------------------|
| 2026-09-23 | **Phase 8: immersive full-bleed hero redesign** — `hero_style=immersive` preset: 100vh full-bleed dark hero with factory photo + multi-layer dark CSS overlay, stats strip (2亿/800+/3000+/12000㎡) embedded inside hero at bottom as `.xxdq-hero__stats-strip`; `.xxdq-hero--immersive` CSS with radial gradient + grid pattern overlay; dark semi-transparent nav (`rgba(11,11,13,.88)` + `backdrop-filter: blur`); count-up animation extended to hero stats strip; GeneratePress page meta set to `no-sidebar` + `full-width-content` (kills Search/Recent Posts widgets); `render_hero()` detects immersive+photo mode and emits `.xxdq-block-hero__bg` div; hero_bg read via `self::val()` (block attrs) with CDN fallback; removed standalone `xxdq/stats-bar` block from homepage; new block sequence: hero(immersive) → category-grid → hot-products → process-steps → why-us → cta. |
| 2026-09-22 | Phase 1: homepage, nav, footer, theme, site config                       |
| 2026-09-22 | Phase 2: product CPT, 111 placeholders, category list + detail pages      |
| 2026-09-22 | Phase 3: 选型工具, 询价单, 关于我们, 联系我们, 技术资料, readme updates   |
| 2026-09-22 | Phase 4: xxdq-pim plugin — ZIP pack import/export, ACF tech_image/attachment fields, dynamic PDF aggregation on 技术资料 page, real images with SVG fallback |
| 2026-09-22 | Phase 5: xxdq-blocks plugin — 7 static + 7 dynamic ACF blocks, migration guide, inquiry form DB inbox |
| 2026-09-23 | **Phase 7: industrial dark homepage redesign** — added 2 new blocks (`xxdq/stats-bar`, `xxdq/process-steps`), enhanced `xxdq/hero` with `hero_style` (industrial-dark preset), `hero_visual` (photo vs gradient), `hero_slogan_variant` (full-chain / source-factory / cert-quality / spec-expert presets), secondary CTA; created `home-industrial.css` (radial gradients, grid mask, dark surface, amber accents) + `home-industrial.js` (count-up animation + IntersectionObserver entrance + hero typewriter); new homepage block sequence: hero → stats-bar → category-grid → hot-products → process-steps → why-us → cta; `body.xxdq-home-industrial` class auto-applied on front page. |
| 2026-09-22 | **Phase 6: product categories made fully dynamic** — replaced hardcoded `['铜鼻子'=>'copper', ...]` slug/icon/color maps with per-term meta (`xxdq_cat_meta()`); added admin UI on Add/Edit Category page (URL slug + emoji icon + color picker); JS-injected the same fields into the post-edit sidebar widget (WP has no PHP hook for it); made `xxdq/category-grid` block auto-populate from taxonomy when manual cards are empty; selector lookup now matches term_id / WP slug / custom slug / name |

---

---

## 🧾 Versions

Goal: let admins **add/rename/delete product categories from WP Admin** without touching code.

### What was hardcoded before

```php
$cat_slug_map = ['铜鼻子'=>'copper','冷压端子'=>'cold','预绝缘端子'=>'insulated','接插件'=>'connector'];
$cat_icon_map = ['铜鼻子'=>'🔩','冷压端子'=>'⚡','预绝缘端子'=>'🔌','接插件'=>'🔗'];
$cat_color_map = ['copper'=>'#B71C1C','cold'=>'#1565C0','insulated'=>'#1B5E20','connector'=>'#4A148C'];
```

These blocked the addition of new categories (the new category would silently fall through to `default`/`📦`/blue).

### What was added

| File | Change |
|---|---|
| `xxdq-products.php` section 1.5 | `xxdq_cat_meta($term, $key)` helper — reads term meta with legacy fallback; add/edit category form fields (slug/icon/color); `created_product_category` + `edited_product_category` save handlers with nonce |
| `xxdq-products.php` admin_footer | JS-injected fields + nonce into the sidebar "Add new category" widget on post-edit pages (`post.php`, `post-new.php`) — WP core hardcodes that widget with no PHP hook |
| `xxdq-products.php` | `xxdq_render_catalog_page`: `hide_empty => false`; empty categories render a placeholder card "暂无产品,即将上线" |
| `xxdq-products.php` selector | Lookup now tries term_id / WP auto-slug / custom `cat_slug` / Chinese name (Chinese WP slugs are unreliable) |
| `xxdq-blocks/class-blocks-render.php` | 4 hardcoded `$cat_slug_map` instances replaced with `xxdq_cat_meta()` |
| `xxdq-blocks/class-blocks-render.php` `render_category_grid` | **Auto-generate cards from `product_category` taxonomy** when ACF repeater is empty — this is why a new category now appears on the product center page automatically |
| `xxdq-blocks/class-blocks-render.php` `render_selector_tool` | `hide_empty => false` for consistency |

### How to add a new category now

1. **In sidebar (when editing a product)**: the "添加新分类" form now has 3 extra fields (URL slug, emoji icon, color picker). Fill them inline.
2. **Or standalone**: Admin → Posts → Categories (filtered by product post type) → Add New Category. Same 3 fields appear on the Add/Edit form.
3. Assign at least one product for it to show on the **product catalog section**; the product center card and selector dropdown always show it (empty cards display "暂无产品,即将上线").

### Behavior matrix

| Page / Block | New category shows automatically? |
|---|---|
| Product center (`xxdq/category-grid`) | ✓ if ACF cards empty; otherwise manual |
| Selector (`xxdq/selector-tool`) | ✓ always (shows in dropdown) |
| Category list pages (`xxdq/category-archive`) | ✓ always (it's the term's archive) |
| Catalog tables (`xxdq-products.php`) | ✗ hidden if 0 products (intentional — empty tables are noise) |

---

## 🔌 MCP Tools Available

Two MCP servers are configured at the Cursor client level (persist across sessions):

| Server | Key tools | Use for |
|---|---|---|
| `cursor-ide-browser` | `browser_navigate`, `browser_snapshot`, `browser_take_screenshot`, `browser_click`, `browser_type`, `browser_fill`, `browser_press_key`, `browser_tabs`, `browser_cdp`, … | Frontend verification — visit any URL on `http://localhost:10004/` to confirm changes (catalog page, selector, admin screens). `browser_snapshot` returns an accessibility tree (better than screenshot for picking click targets). |
| `cursor-app-control` | `move_agent_to_root`, `create_project`, `cursor_dialog` (rules), `rename_chat`, `open_resource`, `open_automation`, `install_plugin` | IDE workspace management + persistent user rules via `cursor_dialog` (item=`rule`). |

**Tips**:
- For "did the fix work?" questions, navigate to the relevant page and snapshot it instead of asking the user to refresh and report back.
- If a browser tool returns "not available", call `GetMcpTools` once — transient errors resolve on retry.
- Use `SembleSearch` / `SembleFindRelated` for semantic code exploration when exact paths are unknown; pair with `Grep` for exact matches.

---

## 🧾 Versions

### Backward compatibility

All 4 legacy categories (铜鼻子/冷压端子/预绝缘端子/接插件) keep their original slug/icon/color **automatically** — `xxdq_cat_meta()` falls back to a hardcoded default map when term meta is empty, so existing sites need no manual setup. Admins can override per-category by editing the category and filling the new fields.
