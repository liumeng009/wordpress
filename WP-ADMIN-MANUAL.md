# wp-admin 后台手动操作手册（SEO 必做清单）

> **目标**：完成所有纯 API 改不了的 Yoast SEO 设置，让百度收录提速 5 倍。
> **预计时间**：75 分钟
> **进入方式**：浏览器访问 https://dz1dz.cn/wp-admin → 用 "admin" 账号登录（密码 = 应用密码一样的那串 `ebqY NXDY rzL4 dkpe f2XP uIrz`）

---

## 第 1 步：每页 SEO title + meta description（30 分钟）

### 1.1 全局模板设置（必做，1 次永久）
**目的**：所有未单独设置的页面自动套用模板。

- 进入 `SEO → Search Appearance → 常规`
- **Title Separator**：改为 ` - `(默认 `-` 也可,但空格更好看)
- **首页 Title**：清空（用我已更新的 "乐清市耕文电气有限公司 - 电气连接件源头工厂"）
- **首页 Meta Description**：清空（已更新）

### 1.2 每页单独设置（10 分钟/页）

按这个顺序，逐页操作：

**Page 1: 首页 (id=7)**
- 编辑页面 → 滚到 Yoast SEO 面板
- **SEO Title** (关键词触发): `铜鼻子_冷压端子_接线端子源头工厂 - 乐清市耕文电气有限公司`
- **Meta description**: `乐清耕文电气专业生产铜鼻子(DT/SC系列)、冷压端子(BV/E/RNB系列)、预绝缘端子、接插件等600+规格,500+现货,24小时快发。CE/RoHS/ISO9001认证,源头工厂价,欢迎询价。`
- **Focus keyphrase**: `铜鼻子` 第一个
- **评估** 要求所有绿灯

对剩下 10 个 page 重复同样的流程。**全部 SEO 配置已存于 `E:\work\wordPress\seo-pages-config.json`**，可逐页复制粘贴。

---

## 第 2 步：启用 sitemap（2 分钟，必做）

`SEO → 通用 → 站点特性`

- ✅ 启用 XML sitemap
- ✅ News sitemap（如果想）
- ✅ 用户 sitemap（可选）
- 点击 `保存`

**sitemap URL**: https://dz1dz.cn/sitemap_index.xml
提交到 https://ziyuan.baidu.com → 链接提交

---

## 第 3 步：站点语言 + 时区（1 分钟）

`Settings → General`

- **Site Language**: 简体中文(或英文 — 影响 Schema og:locale)
- **Timezone**: Asia/Shanghai
- **Save Changes**

---

## 第 4 步：百度站长平台（5 分钟）

1. 访问 https://ziyuan.baidu.com
2. 添加站点 → `dz1dz.cn`
3. 验证方式选 **HTML 标签** 或 **文件验证**
4. 获取 HTML meta 代码片段
5. WordPress 后台 → 主题相关 → header.php 添加：
   ```html
   <meta name="baidu-site-verification" content="你的验证码" />
   ```
6. 等 1-2 天审核
7. 审核通过 → 提交 sitemap
   - 链接提交 → sitemap 提交 → https://dz1dz.cn/sitemap_index.xml
   - 链接提交 → 普通收录(API) → 推送重要 URL 列表

---

## 第 5 步：主动推送 URL 到百度（5 分钟）

关键 URL 列表（必须主动推送）：

```
# 1. 首页
https://dz1dz.cn/

# 2. 5 个核心产品系列
https://dz1dz.cn/product/tong-bi-zi/   # 铜鼻子
https://dz1dz.cn/product/leng-ya-duan-zi/   # 冷压端子
https://dz1dz.cn/product/yu-jue-yuan-duan-zi/   # 预绝缘端子
https://dz1dz.cn/product/jie-cha-jian/   # 接插件
https://dz1dz.cn/product/chan-pin-zhong-xin/   # 产品中心

# 3. 5 篇 SEO 文章
https://dz1dz.cn/news/tong-bi-zi-he-leng-ya-duan-zi-de-qu-bie/
https://dz1dz.cn/news/tong-bi-zi-gui-ge-xing-hao-dui-zhao-biao/
https://dz1dz.cn/news/leng-ya-duan-zi-zen-yao-xuan-xing/
https://dz1dz.cn/news/leng-ya-duan-zi-xuan-xing/
https://dz1dz.cn/news/le-qing-geng-wen-dian-qi-tong-guo-iso9001-fu-shen/
https://dz1dz.cn/news/2026-xin-pin-fa-bu-hui-yuan-man-cheng-gong/

# 4. 关于、联系、询价
https://dz1dz.cn/about/
https://dz1dz.cn/contact/
https://dz1dz.cn/inquiry/

总计: 14 个 URL
```

**操作**：百度站长 → 链接提交 → 普通收录 → 粘贴 → 提交

---

## 第 6 步：robots.txt 检查（2 分钟）

`SEO → Tools → File Editor`

确保 robots.txt 包含：
```
User-agent: *
Allow: /
Sitemap: https://dz1dz.cn/sitemap_index.xml
```

---

## 第 7 步：Schema Settings（5 分钟）

`SEO → Search Appearance → Schema`

- Site type: **Organization**
- Site name: `乐清市耕文电气有限公司`
- Logo: 1435（已绑定）
- Default Image: 选一张产品图

---

## 第 8 步：核心公司信息 Schema（2 分钟）

`SEO → Search Appearance → Social Profiles`

- Facebook: (留空)
- Twitter: (留空)
- 其他: 留空

---

## 第 9 步：手动设置 focus keyword（每页每篇）

我已经在 `seo-pages-config.json` 给每个 page 准备好 focus keyword 后台参考：

| 页面 | Focus keyphrase |
|---|---|
| 首页 | 铜鼻子 |
| 产品中心 | 接线端子 |
| 铜鼻子 | 铜鼻子 |
| 冷压端子 | 冷压端子 |
| 预绝缘端子 | 预绝缘端子 |
| 接插件 | 接插件 |
| 选型工具 | 端子选型 |
| 新闻中心 | 新闻中心 |
| 关于我们 | 乐清耕文电气 |
| 联系我们 | 温州铜鼻子厂家 |
| 询价单 | 在线询价 |

---

## 第 10 步：百度爱企查 + 爱采购（可选，但推荐）

- **百度爱企查** https://aiqicha.baidu.com：注册/认领乐清市耕文电气有限公司 → 工业品信息补全
- **百度爱采购** https://b2b.baidu.com：申请工业品大类入驻 → 上传 SKU（自动同步 200+ 产品）
- **百度地图** https://lbsyun.baidu.com：添加公司地理位置标注

---

## 检查清单（做完后逐条打钩）

- [ ] Site Title 设置好的
- [ ] Site Description 设置好的
- [ ] XML Sitemap 启用
- [ ] 11 个 page 的 Yoast SEO title/meta/keywords 已填
- [ ] 5 篇 news 文章的 Yoast SEO 已填
- [ ] Site Language = 中文 / Timezone = Asia/Shanghai
- [ ] robots.txt 已确认
- [ ] 百度站长验证通过
- [ ] sitemap 已提交
- [ ] 14 个核心 URL 已主动推送
- [ ] Schema 设置 OK
- [ ] 百度爱企查 / 爱采购已认领

---

## 完成时间估算

| 步骤 | 时间 |
|---|---|
| 1 全局模板 | 2 分 |
| 1.2 11 页 Yoast 设置 | 25 分 |
| 2 sitemap | 2 分 |
| 3 语言时区 | 1 分 |
| 4 百度站长 | 5 分 |
| 5 推送 URL | 5 分 |
| 6 robots.txt | 2 分 |
| 7 Schema | 5 分 |
| 8-9 社交 + focus keyword | 5 分 |
| 10 爱企查/爱采购 | 10 分 |
| **总计** | **~62 分钟** |

---

## 如果遇到问题

- 不能登录 wp-admin → 检查 cookie 设置 + 清缓存
- Yoast 面板没显示 → 检查 wp-admin 用户是 administrator 角色
- 百度站长验证失败 → HTML meta 必须在 `head` 内,不能是 `body`

---

**完成后告诉我结果，我可以继续帮你做：**
1. 提交 xml sitemap 后,百度抓取诊断
2. Google Search Console 验证（如果需要海外流量）
3. 持续 SEO 文章发布（每周 1-2 篇）
4. 监控关键词排名 (top 50 词)
