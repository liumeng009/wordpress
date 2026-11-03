# XX电气 — WordPress B2B 电气连接件网站

A custom WordPress site for a Chinese B2B electrical connector manufacturer (XX电气).
Built on GeneratePress + ACF + three custom plugins.

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
| WordPress     | http://localhost:10004/wp-admin/ | admin | `890511CBa` |
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
        │   ├── xxdq-products\            ← legacy CPT renderer (deprecated)
        │   │   ├── xxdq-products.php      ← ~1200 lines (read-only ref)
        │   │   └── readme.txt
        │   ├── xxdq-blocks\              ← active renderer — Gutenberg blocks + CPT + options
        │   │   ├── xxdq-blocks.php
        │   │   ├── includes\
        │   │   │   ├── class-blocks-render.php   ← all render_callbacks
        │   │   │   ├── cpts.php                  ← product / news registration
        │   │   │   └── …
        │   │   ├── assets\
        │   │   │   ├── js\selector-tool.js       ← Path A/B power-mode calculator
        │   │   │   ├── css\selector-tool.css
        │   │   │   └── …
        │   │   └── readme.txt
        │   └── xxdq-pim\                 ← Product Pack Importer — ZIP import/export
        │       ├── xxdq-pim.php
        │       └── tools\build-sample-pack.php
        └── themes\
            └── generatepress\            ← installed; overridden by plugins
```

---

## 🗂️ Pages (12 — all HTTP 200)

| ID | Title      | Size  | Purpose                                                     |
|----|------------|-------|-------------------------------------------------------------|
| 7  | 首页        | 65KB  | Static front page (custom homepage)                          |
| 8  | 产品中心    | —     | Catalog index link from nav                                  |
| 9  | 铜鼻子      | 74KB  | Copper terminals — 4 series accordion (29 products)           |
| 10 | 冷压端子    | 81KB  | Cold-press — 5 series accordion (39 products)                 |
| 11 | 预绝缘端子  | 61KB  | Insulated — 5 series accordion (23 products)                  |
| 12 | 接插件      | 69KB  | Connectors — 4 series accordion (20 products)                 |
| 13 | 在线工具    | 142KB | 端子选型计算器 (Path A: 功率→规格 · Path B: 截面→反算功率) + 通用工具聚合页 |
| 14 | 技术资料    | 57KB  | Downloads: spec sheets, certs, current table, color chart     |
| 15 | 关于我们    | 54KB  | Company intro, advantages grid, service commitments             |
| 16 | 联系我们    | 53KB  | Contact cards + CTA                                           |
| 17 | 询价单      | 54KB  | RFQ form with nonce validation + success state                 |
| (CPT) | 新闻中心  | —     | `news` CPT archive (`/news/`) — 行业资讯                       |

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
| `xxdq/selector-tool`   | 端子选型计算器 — 4 系列端子 (DT/SC/RV/DTL),Path A (功率驱动) + Path B (截面驱动) 双模式,7 修正系数组合 |
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
| 选型工具(在线工具)   | selector-tool                                     |
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

## ⚡ Selector Tool — Power-Mode Calculator

The `xxdq/selector-tool` block implements the **full spec-driven design** (data tables, dual-path calculation, two-table result layout). Implementation matches the upstream specification document in every section: §一 (data), §二 (factors), §三 (form), §四 (logic), §五 (output shape), §六 (result UI), §八 (SEO).

### Data tables (spec §一–§二)

| Constant | Shape | Source |
|---|---|---|
| `TERMINAL_DB` | `{DT, SC, RV, DTL: [{area, model, hole, mold?, baseAmp}]}` | hardcoded in JS |
| `TEMP_K` | `[{maxTemp, k}]` 25→60°C, step 5°C | hardcoded in JS |
| `INSTALL_K` | `{air: 1.00, pipe: 0.85, buried: 0.80}` | hardcoded in JS |
| `MATERIAL_K` | `{copper: 1.00, aluminum: 0.78}` | hardcoded in JS |
| `DTL_K` | `0.85` (DTL type penalty) | hardcoded in JS |
| `DEFAULT_POWER_FACTOR` | `0.85` | hardcoded in JS |
| `VOLTAGE_OPTIONS` | `[220, 380, 660]` | hardcoded in JS |
| `PHASE_OPTIONS` | `{single: {label, factor: 1}, three: {label, factor: √3}}` | hardcoded in JS |

### Form fields (spec §三 — 9 fields, in order)

| # | Field | Type | Default | Required |
|---|---|---|---|---|
| 1 | 导线截面 | select (mm²) | — | yes |
| 2 | 导线材质 | select (copper / aluminum) | copper | yes |
| 3 | 环境温度 (℃) | number | 25 | yes |
| 4 | 安装方式 | select (air / pipe / buried) | air | yes |
| 5 | 端子类型 | select (DT / SC / RV / DTL) | DT | yes |
| 6 | 系统电压 (V) | select (220 / 380 / 660) | 380 | yes |
| 7 | 相数 | select (单相 / 三相) | 三相 | yes |
| 8 | 负载功率 (kW) | number | 留空 | **no (path switch)** |
| 9 | 功率因数 (cosφ) | number (0–1) | 0.85 | no |

> **Removed from v1:** the `连接类型 (pure / transition)` field — DTL type is now selected directly via `端子类型`, and the 0.85 transition penalty is derived from `type === 'DTL'` rather than a separate UI field.

### Calculation paths (spec §四)

#### Path A — power-driven (power > 0)

1. Validate: `voltage > 0` and `powerFactor ∈ (0, 1]`
2. Compute target current: `I_target = P × 1000 / (U × powerFactor)` (single) or `/ (√3 × U × powerFactor)` (three)
3. Lookup: `findRowByBaseAmp(type, I_target)` — smallest spec whose `baseAmp ≥ I_target`
4. Correct: `K = kTemp × kInstall × kMat` (DTL type uses `DTL_K = 0.85`); `actualAmp = round(baseAmp × K)`
5. Warning: `I_target > actualAmp` → set `warning = '当前选型载流量不足，建议升级规格或降低负载。'`
6. Output: `targetCurrent = I_target`, `calculatedPower = P`

#### Path B — area-driven (power blank)

1. Lookup: `findRow(type, area)` — exact match wins, else next size up
2. Correct: same `K` and `actualAmp` formula as Path A
3. Back-calc: `P_max = √(phaseFactor) × U × actualAmp × powerFactor / 1000`
4. Output: `targetCurrent = null`, `calculatedPower = P_max` (informational, no warning)

### Output shape (spec §五)

```js
{
  success: true,
  data: {
    model: 'DT-25',           // 端子型号
    hole: 8.5,                // 螺栓孔径 (mm)
    mold: 14,                 // 压接模具 (mm, null for DTL)
    baseAmp: 105,             // 基准载流量 (A)
    actualAmp: 89,            // 修正后载流量 (A)
    targetCurrent: 85,        // 目标电流 (A, null for Path B)
    calculatedPower: 55.2,    // 负载功率 (kW, user input or back-calc)
    voltage: 380, phase: 'three', powerFactor: 0.85,
    type: 'DT', material: 'copper', area: 25, temp: 25, install: 'air',
    factors: { temp: 0.85, install: 1.00, material: 1.00 },
    warning: null,            // null | message string
  },
}
```

### Result UI (spec §六) — two side-by-side tables

**端子选型结果** (6 rows): 推荐型号 · 螺栓孔径 · 压接模具 · 基准载流量 · 修正后载流量 · 选型结论 (`✅ 满足` / `⚠️ 载流量不足`)

**功率信息** (6 rows): 系统电压 · 相数 · 功率因数 · 负载功率 · 目标电流 · 修正后载流量

Plus: warning strip (only on Path A failure), 修正系数明细 (3 factors), and 3 CTAs (立即询价 / 查看该型号产品 / 重新选型).

### Why two paths (not one) — by spec

| Concern | Path A | Path B |
|---------|--------|--------|
| Input semantic | "What terminal for my 10kW load?" | "What load can my 10mm² cable carry?" |
| Lookup axis | `baseAmp` (current requirement) | `area` (cable cross-section) |
| Output semantic | target current + sufficiency | max sustainable power (informational) |
| Warning fires | yes (when `actualAmp < targetCurrent`) | never |
| Validation needs | `voltage > 0`, `powerFactor ∈ (0, 1]` | only area in-range |
| `targetCurrent` | int (rounded I_target) | null |
| `calculatedPower` | user input echoed | back-calc from corrected ampacity |

The shared bits (kTemp / kInstall / kMat, `common` echo fields) are factored into a single top-of-`calculate()` prelude, reused via `Object.assign({}, common, ...)`.

### Files

| File | Lines | Role |
|------|-------|------|
| `xxdq-blocks/assets/selector-tool.js`     | ~525   | `TERMINAL_DB` + 3 factor tables + 2 helpers + `calculate()` Path A/B switch + `renderResult()` two-table render |
| `xxdq-blocks/assets/selector-tool.css`    | ~410   | Two-table grid (side-by-side on desktop, stacked on ≤768px) + conclusion badge (`is-fail` modifier) + warning strip + responsive |
| `xxdq-blocks/includes/class-blocks-render.php` `render_selector_tool` | ~200 | 9-field form (per spec §三 order) + two `<table>` blocks + `SoftwareApplication` + `FAQPage` Schema.org JSON-LD |

### Test matrix (verified in browser MCP)

| # | Scenario | Inputs | Result |
|---|---|---|---|
| 1 | **Path B (default)** | 10mm² · 铜 · 25°C · 空气中 · DT · 380V · 三相 · 功率留空 | DT-10 · baseAmp 60 · actualAmp 60 · 反算 33.6 kW · ✅ 满足 |
| 2 | **Path A (满足)** | 同上 + 5 kW | I_target 8.9 A · DT-10 满足 · ✅ |
| 3 | **Path A (警告)** | 10mm² · 铜 · 60°C · 220V · 单相 · 10 kW · cosφ 0.85 | I_target 53 A · DT-10 实际 39 A · 60×0.65=39<53 · ⚠️ 载流量不足 |

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

## 🧾 Versions

### 2026-10-10 (v2) — Spec-aligned refactor of selector-tool

**目标**:把 v1 的 Path A/B 功率选型模块**完整对齐**上游 spec 文档(§一～§八),移除 v1 自身的临时扩展(连接类型字段、`powerMode`/`ampSufficient` 内部标记、`transition` 材质键等),让实现与文档一一对应。

#### 1. 数据表层 — spec §一/§二

| 改动 | 文件 | 说明 |
|---|---|---|
| 删除 `MATERIAL_K.transition` | `selector-tool.js` | DTL 过渡损耗 0.85 改为 `DTL_K` 独立常量,按 `type === 'DTL'` 触发 |
| 4 个常量集中定义 | `selector-tool.js` L40-50 | `DEFAULT_POWER_FACTOR` / `VOLTAGE_OPTIONS` / `PHASE_OPTIONS` 与 spec 字段完全一致 |

#### 2. 表单字段 — spec §三 顺序

| 改动 | 文件 | 说明 |
|---|---|---|
| 移除「连接类型」字段 | `class-blocks-render.php` | DTL 由「端子类型」直接选,场景已自洽 |
| 字段顺序重排 | `class-blocks-render.php` | 按 spec: 截面 → 材质 → 温度 → 安装方式 → 端子类型 → 电压 → 相数 → 功率 → 功率因数 |
| `name="phases"` → `name="phase"` | `class-blocks-render.php` | spec §三 使用单数 |
| `name="cosphi"` → `name="powerFactor"` | `class-blocks-render.php` | spec §三 用 `powerFactor` |
| H1 | `class-blocks-render.php` | "端子选型计算器" → "铜鼻子/冷压端子选型计算器" |

#### 3. 计算逻辑 — spec §四

| 改动 | 文件 | 说明 |
|---|---|---|
| `calculate(input)` 接收新字段集 | `selector-tool.js` | 移除 `connection`,新增 `phase` + `powerFactor` 命名 |
| `currentFromPower` / `powerFromCurrent` 参数名 | `selector-tool.js` | `phases` → `phase`,`cosphi` → `powerFactor` |
| Path A output 字段 | `selector-tool.js` | 新增 `targetCurrent` (int A),`calculatedPower` (用户输入回显),`warning` (string \| null) |
| Path B output 字段 | `selector-tool.js` | `targetCurrent = null`,`calculatedPower` 反算值,`warning = null` |
| 移除 `powerMode` / `ampSufficient` | `selector-tool.js` | 改用 `targetCurrent !== null` 判断路径;`warning` 直接表达风险 |
| `kMat` 推导逻辑 | `selector-tool.js` | `type === 'DTL' ? DTL_K : MATERIAL_K[material]` |

#### 4. 结果区 — spec §六 两块独立表

| 改动 | 文件 | 说明 |
|---|---|---|
| 移除 `.xxdq-selector__power-info` + `.xxdq-selector__grid` 混合布局 | `selector-tool.php` + `selector-tool.css` | 改为 `.xxdq-selector__tables` 双列 grid,内含两块独立 `<table>` |
| 端子选型结果表(6 行) | `selector-tool.php` | 推荐型号 / 螺栓孔径 / 压接模具 / 基准载流量 / 修正后载流量 / 选型结论 |
| 功率信息表(6 行) | `selector-tool.php` | 系统电压 / 相数 / 功率因数 / 负载功率 / 目标电流 / 修正后载流量 |
| 选型结论 badge | `selector-tool.css` | `.xxdq-selector__terminal-conclusion` + `is-fail` modifier,图标 ✅ / ⚠️ |
| 警告条 | `selector-tool.css` | `.xxdq-selector__warning` 维持 `is-visible` toggle,通过 JS 显隐 |

#### 5. 测试矩阵(浏览器 MCP 实测)

| # | 场景 | 输入 | 结果 |
|---|---|---|---|
| 1 | Path B 默认 | 10mm² · 铜 · 25°C · 380V · 三相 · 功率留空 | DT-10 · baseAmp 60 · actualAmp 60 · 反算 33.6 kW · ✅ 满足 |
| 2 | Path A 满足 | 同上 + 5 kW | I_target 8.9 A · DT-10 ✅ |
| 3 | Path A 警告 | 10mm² · 60°C · 220V · 单相 · 10 kW | I_target 53 A · actualAmp 39 A · ⚠️ 触发红色警告条 |

#### 6. 与 v1 的关键差异

| 维度 | v1 | v2 (本次) |
|---|---|---|
| 字段数 | 10(含连接类型) | 9(移除连接类型) |
| 结果区 | 单 7 行表 + 4 stat 卡片 | **两块独立表**(端子 + 功率) |
| 内部状态字段 | `powerMode` + `ampSufficient` | 仅 `targetCurrent !== null` 判断 |
| MATERIAL_K | 3 键含 `transition` | 2 键(纯材质),DTL 用独立 `DTL_K` |
| 结论 UI | 表格最后一行 | 独立 `<span>` badge(✅ / ⚠️) |
| 警告触发 | 修正 < 目标 | 同上(但通过 `warning` 字段统一,不再用 `--fail` class) |

#### 1. 页面重命名 + nav 接入

| 改动 | 文件 | 说明 |
|---|---|---|
| DB update | `wp_posts` ID=13 | `post_title='选型工具' → '在线工具'`,`post_name='online-tools'`,清 `_wp_trash_meta_*` |
| 主题 nav | `xxdq-theme/xxdq-theme.php` L200 | 在"产品中心"和"新闻中心"之间加 `<a href="/online-tools/">在线工具</a>`,带 `active` 高亮 |
| 主题 footer | `xxdq-theme/xxdq-theme.php` L242 | `quick_links` 数组加对应条目 |
| 模板 lede | `xxdq-blocks/templates/page.php` | `$defaults` 加 `'online-tools'` slug 默认文案 |

#### 2. 功率选型模块 — 三文件协同

**`xxdq-blocks/assets/selector-tool.js`**:
- 新增 helper `findRowByBaseAmp(type, targetAmp)` — 按 baseAmp 找最小满足规格(forward engineering)
- 新增 helper `currentFromPower(powerKw, voltage, phases, cosphi)` — `I = P×1000 / (U×cosφ)` 或 `/ (√3·U·cosφ)`
- 新增 helper `powerFromCurrent(amp, voltage, phases, cosphi)` — 反算最大持续功率
- `calculate()` 顶部抽出**共享 prelude**:三个修正系数 + `common` echo 对象
- `calculate()` 拆为 **Path A**(power > 0)和 **Path B**(power 留空)两条独立 return 路径,通过 `powerMode: 'given' | 'derived'` 标记
- `renderResult()` 按 `d.powerMode` 分两段写,共享 4 行公共单元格,从 `powerCell` 起分叉
- 警告条 `xxdq-selector__warning` 按 Path A 满足性显隐,加 `xxdq-selector__pv--conclusion--fail` 红字 modifier

**`xxdq-blocks/assets/selector-tool.css`**:
- 新增 `.xxdq-selector__power-info` / `.xxdq-selector__power-table` — 7 行功率信息表样式
- 新增 `.xxdq-selector__warning` — 警告条(amber 底)
- `.xxdq-selector__pv--conclusion--fail` — 红色 modifier 叠加,不另起组件

**`xxdq-blocks/includes/class-blocks-render.php` `render_selector_tool`**:
- 表单新增 4 字段:`系统电压`(220/380/660)、`相数`(单相/三相)、`负载功率 kW`(选填,留空走 Path B)、`功率因数 cosφ`(默认 0.85)
- 结果区在原 stat 卡片后接 7 行 `.xxdq-selector__power-table` DOM
- 新增警告条 DOM(Path A 不满足时显示)
- `seo_formula` 默认文案补全功率公式(单相/三相 电流 + 反算功率)
- FAQ 默认数组新增 2 条(关于功率选型、修正系数)

#### 3. 测试矩阵(浏览器 MCP 实测通过)

| 场景 | 输入 | 关键结果 |
|---|---|---|
| Path B(基准) | 10mm² · 铜 · 25°C · 380V · 三相 · 不填功率 | DT-10,baseAmp=60 A,actualAmp=60 A,反算 33.6 kW,无警告 |
| Path A(满足) | 同上 + 5 kW | I_target=8.9 A,DT-10 满足,无警告 |
| Path A(警告) | 10mm² · 铜 · 60°C · 220V · 单相 · 10 kW | I_target=53.5 A,DT-10 baseAmp=60 A 达标,但 60×0.65=39 A < 53.5,触发红色警告 |

#### 4. 待跟进项

| 项 | 状态 |
|---|---|
| 草稿 "端子选型计算器" (ID=1591,空内容) | 暂未清理 |
| 顶部 "立即询价" CTA | 维持原位,未把"在线工具"做成高亮 CTA |
| Path A 完全超规格警告(目标 > 基准) | 走 error 通道(已有文案),未单独触发红色警告条 |
| 功率字段 UX 联动 | 当前两字段都必填,但 JS 自动走 Path A 忽略 area |

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

---

## 🧾 Versions

### 2026-09-29 — 百度搜索收录推送准备

**目标**：让百度搜索引擎发现并收录 https://dz1dz.cn 上的全部页面。

#### 1. URL 清单导出

| 项目 | 数量 |
|---|---|
| 首页 + 11 个一级产品分类 | 11 |
| 子分类（product_category） | 79 |
| 产品详情页（product CPT） | 308 |
| 新闻（news CPT） | 2 |
| 静态页面 | 6 |
| **合计有效 URL** | **417** |
| 文件大小 | 20.5 KB |
| 域名替换 | `localhost:10004` → `https://dz1dz.cn` |
| 中文 slug | 自动 URL 编码（如 `%e8%af%a2%e4%bb%b7%5%8d%95`） |
| 输出文件 | `E:\work\wordPress\urls-manual-push.txt` |

Yoast SEO 已自动生成 4 个子 sitemap：

- `page-sitemap.xml` — 6 条
- `product-sitemap.xml` — 309 条
- `news-sitemap.xml` — 3 条
- `product_category-sitemap.xml` — 90 条

索引文件：`https://dz1dz.cn/sitemap_index.xml`（提交一条 = 百度抓全站）

#### 2. 百度站长平台账号信息

| 项 | 值 |
|---|---|
| 域名 | https://dz1dz.cn |
| 验证状态 | 已验证（站点所有权确认） |
| API 类型 | **快速收录 API**（`http://data.zz.baidu.com/urls`） |
| 配额 | **10 条 / 天** |
| Token | `GvXmmaQLmf4XUCID` ⚠️ 聊天历史已暴露，建议回收重置 |

注意：截图页面显示"今日剩余可推送 URL: 6 条"，确认是**快速收录**配额，而非普通收录的 1 万条/天。

#### 3. 推送脚本 — `E:\work\wordPress\baidu-push.ps1`

**功能**：

- 读取 `urls-manual-push.txt`，跳过注释行
- 自动去除 UTF-8 BOM（百度 API parser 把 BOM 视为 URL 一部分，会判 `not_valid`）
- 加载 `baidu-pushed.txt` 状态文件，自动跳过已推送 URL（避免浪费配额）
- 按 10 条/天封顶推送，避免触发百度 400 限速
- 单条 + 2 秒间隔（百度快速收录有秒级限流保护）
- 输出 success / fail / remain 实时反馈

**用法**：

```powershell
cd E:\work\wordPress
powershell -ExecutionPolicy Bypass -File .\baidu-push.ps1
```

**进度文件**：

- `urls-manual-push.txt` — 417 条 URL 源
- `baidu-pushed.txt` — 已成功推送记录（脚本自动追加）
- `baidu-push-state-YYYY-MM-DD.txt` — 当日推送计数

#### 4. 今日推送记录（9/10 条配额）

| # | URL | 结果 |
|---|---|---|
| 1 | `https://dz1dz.cn/` | ✓ success=1 |
| 2 | `/product-category/copper/` | ✓ success=1 |
| 3 | `/product-category/cold/` | ✓ success=1 |
| 4 | `/product-category/bare-terminals/` | ✓ success=1 |
| 5 | `/product-category/copper-tube-terminals/` | ✓ success=1 |
| 6~9 | `/product-category/pre-insulated-terminals/` 等 | ⚠ HTTP 400 — 百度秒级限速触发（不是 URL 不通；debug 阶段单条间隔 200ms 太短） |

**根因**：百度快速收录 API 有「秒级限速」，请求间隔 < 1 秒即触发 400。**与 URL 合法性、token 有效性、域名解析均无关**。

**解决方案**：脚本已加 `Start-Sleep -Seconds 2` 单条间隔；明日重跑自动从第 6 条继续。

#### 5. 后续节奏

```
剩余待推：408 条
每日配额：10 条
预计完成：41 天
```

**可选加速**：若切到普通收录 API（`&type=daily` ），配额升为 1 万条/天，1 秒推完所有 408 条。但需在百度站长后台确认账号是否开通此权限。

#### 6. 上线前 checklist（SEO 角度）

- [ ] 百度站长平台 sitemap 提交 — 推送 `https://dz1dz.cn/sitemap_index.xml`
- [ ] 重置 token（`GvXmmaQLmf4XUCID` 已在公开聊天暴露）
- [ ] `robots.txt` 允许 Baiduspider 全站抓取
- [ ] 首页 TDK（title / description / keywords）确认
- [ ] 11 个一级分类页 TDK 已写
- [ ] 79 个子分类页 TDK 模板
- [ ] 站内链接闭环（首页 → 分类 → 产品 → 询价单）
- [ ] 已部署 HTTPS（✓ dz1dz.cn 已生效）
- [ ] 网站备案号 + 公安备案号 已加页脚
- [ ] 实际联系信息（电话/微信/邮箱）已替换占位符
- [ ] ICP 已替换占位符
- [ ] Google Search Console 同步提交 sitemap（作为辅助）
