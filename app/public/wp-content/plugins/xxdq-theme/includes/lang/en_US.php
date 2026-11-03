<?php
/**
 * XXDQ i18n — English (en_US) string catalog.
 *
 * Terminology follows common B2B electrical-connector usage:
 *   - 冷压端子   → Crimp Terminal (not "Cold Press Terminal")
 *   - 预绝缘端子 → Pre-insulated Terminal
 *   - 铜鼻子     → Copper Lug
 *   - 裸端头     → Bare Terminal
 *   - 中间接头   → Intermediate Connector
 *   - 端头       → Terminal / End Cap depending on the series
 *
 * Any key missing from this file falls back to the Chinese value (see
 * XXDQ_I18n::catalog()), so the English site renders readable content
 * even before a key has been translated.
 *
 * @package XXDQ
 */

if (!defined('ABSPATH')) exit;

return [
    /* ── Navigation ───────────────────────────────────────────── */
    'nav.home'            => 'Home',
    'nav.products'        => 'Products',
    'nav.calculator'      => 'Cable Lug Calculator',
    'nav.news'            => 'News',
    'nav.about'           => 'About Us',
    'nav.contact'         => 'Contact Us',
    'nav.inquiry'         => 'Get a Quote',
    'nav.browse_all'      => 'Browse All Products ›',
    'nav.menu'            => 'Menu',

    /* ── Brand ────────────────────────────────────────────────── */
    'brand.name'          => 'Gengwen Electric',
    'brand.tagline'       => 'Electrical Connector Manufacturer',
    'brand.short'         => 'Gengwen Electric',
    'brand.alt'           => 'Gengwen Electric - Electrical Connector Manufacturer',
    'brand.mark'          => 'Gengwen',

    /* ── Footer ───────────────────────────────────────────────── */
    'footer.quick_links'  => 'Quick Links',
    'footer.main_products'=> 'Main Products',
    'footer.contact'      => 'Contact Info',
    'footer.slogan'       => 'Electrical Connector Manufacturer · CE/RoHS/ISO9001 Certified',
    'footer.no_links'     => 'No links yet',
    'footer.copyright'    => '© %s Gengwen Electric Co., Ltd. All Rights Reserved',
    'footer.address'      => 'Address',
    'footer.phone'        => 'Phone',
    'footer.email'        => 'Email',
    'footer.wechat'       => 'WeChat',
    'footer.hours'        => 'Business Hours',
    'footer.hours_value'  => 'Mon – Sat, 8:30 – 17:30',

    /* ── Breadcrumb ───────────────────────────────────────────── */
    'crumb.home'          => 'Home',

    /* ── Product single ───────────────────────────────────────── */
    'product.model_label' => 'Model',
    'product.wire_range'  => 'Wire Range',
    'product.hole_size'   => 'Stud Size',
    'product.material'    => 'Material',
    'product.finish'      => 'Surface Finish',
    'product.color_code'  => 'Insulation Color',
    'product.voltage'     => 'Rated Voltage',
    'product.temperature' => 'Operating Temperature',
    'product.certification' => 'Certification',
    'product.package'     => 'Packing',
    'product.short_desc'  => 'Description',
    'product.specs_title' => 'Specifications',
    'product.tech_title'  => 'Technical / Dimension Drawing',
    'product.detail_title'=> 'Product Details',
    'product.no_image'    => 'No image available',
    'product.image_alt'   => 'Image %d',
    'product.inquiry_now' => 'Get a Quote',
    'product.download'    => '📄 Download Datasheet',
    'product.prev'        => '← Previous Product',
    'product.next'        => 'Next Product →',
    'product.first'       => 'This is the first product',
    'product.last'        => 'This is the last product',
    'product.back'        => '← Back to %s',
    'product.back_to'     => 'Products',

    /* ── Product archive ──────────────────────────────────────── */
    'archive.sidebar_title'=> 'Products',
    'archive.all_products'=> 'All Products',
    'archive.select_hint' => 'Select a category, or browse everything',
    'archive.empty'       => 'No products found.',
    'archive.no_subcats'  => 'No sub-categories in this category.',
    'archive.other_models'=> 'Other Models',
    'archive.model_count' => '%d models',
    'archive.view_product'=> 'View Product ›',

    /* ── Inquiry form ─────────────────────────────────────────── */
    'inquiry.title'       => 'Inquiry',
    'inquiry.success'     => 'Submitted successfully!',
    'inquiry.success_msg' => 'We have received your inquiry and will contact you within 1 hour.',
    'inquiry.company'     => 'Company',
    'inquiry.name'        => 'Contact Name',
    'inquiry.phone'       => 'Phone / WhatsApp',
    'inquiry.email'       => 'Email',
    'inquiry.model'       => 'Product Model',
    'inquiry.model_ph'    => 'e.g. DT-16',
    'inquiry.message'     => 'Requirement Details',
    'inquiry.message_ph'  => 'Please describe the model, quantity and required delivery date',
    'inquiry.submit'      => 'Submit Inquiry',
];
