<?php
/**
 * XXDQ i18n — Chinese (zh_CN) string catalog.
 *
 * This file is the SOURCE OF TRUTH for every piece of front-end copy on
 * the Chinese site. Strings are copied verbatim from the templates they
 * replaced, so switching a template to use `xxdq_t()` must not change a
 * single byte of the Chinese output.
 *
 * Keys are dot-namespaced by the area they belong to.
 *
 * @package XXDQ
 */

if (!defined('ABSPATH')) exit;

return [
    /* ── Navigation ───────────────────────────────────────────── */
    'nav.home'            => '首页',
    'nav.products'        => '产品中心',
    'nav.calculator'      => '铜鼻子选型计算器',
    'nav.news'            => '新闻中心',
    'nav.about'           => '关于我们',
    'nav.contact'         => '联系我们',
    'nav.inquiry'         => '立即询价',
    'nav.browse_all'      => '浏览全部产品 ›',
    'nav.menu'            => '菜单',

    /* ── Brand ────────────────────────────────────────────────── */
    'brand.name'          => '乐清市耕文电气有限公司',
    'brand.tagline'       => '电气连接件源头工厂',
    'brand.short'         => '乐清耕文电气',
    'brand.alt'           => '乐清市耕文电气有限公司 - 电气连接件源头工厂',
    'brand.mark'          => '耕文',

    /* ── Footer ───────────────────────────────────────────────── */
    'footer.quick_links'  => '快速导航',
    'footer.main_products'=> '主营产品',
    'footer.contact'      => '联系方式',
    'footer.slogan'       => '电气连接件源头工厂 · CE/RoHS/ISO9001 认证',
    'footer.no_links'     => '暂无链接',
    'footer.copyright'    => '© %s 乐清市耕文电气有限公司 版权所有',
    'footer.address'      => '地址',
    'footer.phone'        => '电话',
    'footer.email'        => '邮箱',
    'footer.wechat'       => '微信',
    'footer.hours'        => '工作时间',
    'footer.hours_value'  => '周一至周六 8:30-17:30',

    /* ── Breadcrumb ───────────────────────────────────────────── */
    'crumb.home'          => '首页',

    /* ── Product single ───────────────────────────────────────── */
    'product.model_label' => '型号',
    'product.wire_range'  => '适用线径',
    'product.hole_size'   => '螺丝孔径',
    'product.material'    => '材质',
    'product.finish'      => '表面处理',
    'product.color_code'  => '绝缘颜色',
    'product.voltage'     => '额定电压',
    'product.temperature' => '工作温度',
    'product.certification' => '认证',
    'product.package'     => '包装方式',
    'product.short_desc'  => '简介',
    'product.specs_title' => '规格参数',
    'product.tech_title'  => '技术图 / 尺寸图',
    'product.detail_title'=> '产品详情',
    'product.no_image'    => '暂无图片',
    'product.image_alt'   => '图 %d',
    'product.inquiry_now' => '立即询价',
    'product.download'    => '📄 下载技术手册',
    'product.prev'        => '← 上一个产品',
    'product.next'        => '下一个产品 →',
    'product.first'       => '已经是第一个了',
    'product.last'        => '已经是最后一个了',
    'product.back'        => '← 返回%s',
    'product.back_to'     => '产品中心',

    /* ── Product archive ──────────────────────────────────────── */
    'archive.sidebar_title'=> '产品中心',
    'archive.all_products'=> '全部产品',
    'archive.select_hint' => '选择产品分类,或浏览全部',
    'archive.empty'       => '暂无产品。',
    'archive.no_subcats'  => '该分类下暂无二级分类。',
    'archive.other_models'=> '其他型号',
    'archive.model_count' => '%d 个型号',
    'archive.view_product'=> '查看产品 ›',

    /* ── Inquiry form ─────────────────────────────────────────── */
    'inquiry.title'       => '询价单',
    'inquiry.success'     => '提交成功!',
    'inquiry.success_msg' => '我们已收到您的询价,1 小时内会有专人与您联系。',
    'inquiry.company'     => '公司名称',
    'inquiry.name'        => '联系人',
    'inquiry.phone'       => '电话',
    'inquiry.email'       => '邮箱',
    'inquiry.model'       => '产品型号',
    'inquiry.model_ph'    => '如 DT-16',
    'inquiry.message'     => '需求描述',
    'inquiry.message_ph'  => '请描述您需要的产品型号、数量、交期等',
    'inquiry.submit'      => '提交询价',
];
