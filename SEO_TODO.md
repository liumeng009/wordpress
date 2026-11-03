# SEO 待办 — 耕文电气网站

**记录时间**: 2026-09-24 23:04 (周四)
**计划开工**: 2026-09-25 (周五)

---

## 当前现状(基线扫描)

| 项 | 状态 | 备注 |
|---|---|---|
| `<title>` | ✅ | 完整含关键词 |
| `<h1>` | ✅ | 每页唯一 |
| 图片 alt | ✅ | 16/16 全有 |
| `<meta description>` | ❌ | 缺失 |
| `<link rel=canonical>` | ❌ | 缺失 |
| `<meta name=robots>` | ❌ | 缺失 |
| Open Graph / Twitter Cards | ❌ | 缺失 |
| JSON-LD 结构化数据 | ❌ | 缺失 |
| `/wp-sitemap.xml` | ❌ | 报 XML 解析错(未启用) |
| `/robots.txt` | ❌ | 不存在 |
| SEO 插件 | ❌ | 未装 Yoast / Rank Math / AIOSEO |
| `<html lang>` | ❌ | en-US,应为 zh-CN |

---

## 任务清单(按优先级)

### P0 — 必做(预计 2h)

- [ ] **1. 装 SEO 插件**:在 **Rank Math**(首选,免费版功能够用)与 **Yoast SEO** 之间选一个 → 装上、激活、走 onboarding wizard
- [ ] **2. 配插件基础**:
  - 站点类型:Small Business
  - 标题/描述模板(首页/产品/新闻/普通页)
  - 启用 sitemap(`/sitemap_index.xml`)
  - 启用 robots.txt(写入 `Disallow: /wp-admin/` + sitemap URL)
  - 启用 Schema Markup(默认 Article/WebPage)
- [ ] **3. 修 `<html lang>`**:Rank Math 自动改,确认输出 `zh-CN`
- [ ] **4. 配社交分享**:
  - 设置默认 og:image(品牌分享卡 1200×630)
  - 填 OG locale = `zh_CN`
  - 绑 Twitter handle(如有)
- [ ] **5. 验证**:
  - `/sitemap_index.xml` 返回 200
  - `/robots.txt` 返回 200 含 sitemap
  - View Page Source → 看到 meta description + canonical + og:*

### P1 — 这周内(预计 1–2 天)

- [ ] **6. Product Schema**:每个产品详情页输出 JSON-LD(name / image / sku / brand / description / offers)
- [ ] **7. Article Schema**:每篇新闻配 Article schema(datePublished / author / image)
- [ ] **8. BreadcrumbList Schema**:所有有面包屑的页(首页/产品/新闻/产品详情/新闻详情)
- [ ] **9. Organization Schema**:首页 footer(公司名/地址/电话/邮箱/营业时间/logo)
- [ ] **10. 内链结构优化**:
  - 每个产品详情页加 4–6 个"相关产品"
  - 每篇新闻文末加 1–2 个相关产品
  - 每个分类页交叉链其他分类
- [ ] **11. URL slug 规范化**:
  - 25 个产品 slug 改为拼音或型号(如 `tg-jt`, `dt-ga`)
  - 加 301 重定向(老 URL → 新 URL)

### P2 — 上线后持续(预估 1–2 周)

- [ ] **12. 内容深度**:每个产品补 ≥ 300 字正文(规格/场景/工艺/认证)
- [ ] **13. 长尾内容**:写 5–10 篇技术博客
  - "如何选铜鼻子规格"
  - "冷压端子压接工艺"
  - "CE/RoHS 认证流程"
  - "预绝缘端子 vs 裸端头怎么选"
  - "工厂质检流程"
- [ ] **14. FAQ 模块**:在每个产品页加 FAQ(同时拿 FAQPage schema 富文本)
- [ ] **15. 性能优化**:
  - 图片全转 WebP/AVIF(当前 hero 图 200–400KB → 50–80KB)
  - 装缓存插件(LiteSpeed Cache 或 WP Super Cache)
  - 接 CDN(Cloudflare 免费版)
  - hero 图加 `width` `height` 属性,加 preload
- [ ] **16. 移动端验证**:Chrome DevTools + PageSpeed Insights 测试
- [ ] **17. 本地 SEO**:
  - Google Business Profile(海外)
  - 百度地图标注(国内)
  - footer 加完整地址 + 营业时间 + 联系人 → 用 `LocalBusiness` schema 包

---

## 验收清单(完成所有 P0+P1 后过一遍)

- [ ] Google Search Console 上线并提交 sitemap
- [ ] Bing Webmaster Tools 提交 sitemap
- [ ] Schema Markup Validator(https://validator.schema.org/) 全过
- [ ] PageSpeed Insights(桌面 ≥ 85,移动 ≥ 70)
- [ ] Facebook Sharing Debugger / Twitter Card Validator → 看到卡片预览
- [ ] site:yourdomain.com → 收录首页 + 至少 3 个产品页

---

## 备注

- 不用今天做,完整时间块留给明天(预计 P0 = 2h,P1 = 1.5 天)
- 选 Rank Math 的话,JSON-LD 大部分自动出,只需在产品 CPT 注册时勾上 Product schema
- 不选插件的话,纯手写 JSON-LD 也能做,代码量约 200 行 PHP(给 mu-plugins/seo-schema.php)
