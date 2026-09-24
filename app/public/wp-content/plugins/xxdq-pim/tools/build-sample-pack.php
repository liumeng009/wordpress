<?php
/**
 * CLI helper to generate a sample pack ZIP.
 *
 * Usage: place this in the WordPress root or run via wp-cli eval-file.
 *
 *   wp eval-file app/public/wp-content/plugins/xxdq-pim/tools/build-sample-pack.php
 *
 * Writes:
 *   wp-content/uploads/xxdq-pim-samples/sample-pack.zip
 */

if (!defined('ABSPATH') && !defined('WP_CLI')) {
    // When not running under WP, attempt to bootstrap.
    $wp_load = __DIR__ . '/../../../../../../wp-load.php';
    if (file_exists($wp_load)) require $wp_load;
    else {
        // Find wp-load.php by walking up
        $dir = __DIR__;
        for ($i = 0; $i < 8; $i++) {
            $dir = dirname($dir);
            if (file_exists($dir . '/wp-load.php')) { require $dir . '/wp-load.php'; break; }
        }
    }
}

if (!class_exists('ZipArchive')) {
    fwrite(STDERR, "ZipArchive extension required\n");
    exit(1);
}

$upload = wp_upload_dir();
$out_dir = $upload['basedir'] . '/xxdq-pim-samples';
wp_mkdir_p($out_dir);

$zip_path = $out_dir . '/sample-pack.zip';
@unlink($zip_path);

$zip = new ZipArchive();
$zip->open($zip_path, ZipArchive::CREATE);

// 1. Sample CSV (10 rows)
$csv_rows = [
    ['model','wire_range','hole_size','material','finish','color_code','voltage','temperature','certification','package','short_desc','category','series','status'],
    ['DT-25','25mm²','M8','T2紫铜','镀锡','','600V','-40~+105°C','CE / RoHS','100只/袋','DT 系列标准型铜鼻子，紫铜材质，导电性能优异。','铜鼻子','DT','publish'],
    ['DT-35','35mm²','M10','T2紫铜','镀锡','','600V','-40~+105°C','CE / RoHS','100只/袋','DT-35 铜鼻子，适合较大电流接线场景。','铜鼻子','DT','publish'],
    ['DTG-50','50mm²','M10','T2紫铜','镀锡','','600V','-40~+105°C','CE / RoHS','50只/袋','DTG 系列管式铜鼻子。','铜鼻子','DTG','publish'],
    ['E2510','2.5mm²','M5','紫铜','镀锡','','600V','-40~+105°C','CE / RoHS','100只/袋','E 系列冷压端子。','冷压端子','E','publish'],
    ['E7508','0.75mm²','M4','紫铜','镀锡','','600V','-40~+105°C','CE / RoHS','100只/袋','E 系列 0.75mm² 冷压端子。','冷压端子','E','publish'],
    ['BV1.5','0.5-1.5mm²','M4','紫铜','镀锡','红','600V','-40~+105°C','CE / RoHS','100只/袋','BV 红色预绝缘端子。','预绝缘端子','BV','publish'],
    ['SV2.5','1.5-2.5mm²','M5','紫铜','镀锡','蓝','600V','-40~+105°C','CE / RoHS','100只/袋','SV 蓝色预绝缘端子。','预绝缘端子','SV','publish'],
    ['RV5.5','4-6mm²','M6','紫铜','镀锡','黄','600V','-40~+105°C','CE / RoHS','100只/袋','RV 黄色预绝缘端子。','预绝缘端子','RV','publish'],
    ['SC-10','10mm²','M8','黄铜','镀镍','','250V','-40~+85°C','RoHS','50只/袋','SC 子弹型接插件。','接插件','SC','publish'],
    ['OT-25','25mm²','M8','紫铜','镀锡','','600V','-40~+105°C','CE / RoHS','100只/袋','OT 开口型铜鼻子。','铜鼻子','OT','publish'],
];

$csv_str = '';
foreach ($csv_rows as $row) {
    $csv_str .= implode(',', array_map(function($v) {
        return strpos($v, ',') !== false ? '"' . str_replace('"', '""', $v) . '"' : $v;
    }, $row)) . "\n";
}
$zip->addFromString('products.csv', "\xEF\xBB\xBF" . $csv_str); // BOM for Excel

// 2. Sample images (1x1 PNG placeholders, one per product)
$png_1x1 = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');
$gif_1x1 = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');

foreach (['DT-25','DT-35','DTG-50','E2510','E7508','BV1.5','SV2.5','RV5.5','SC-10','OT-25'] as $model) {
    // Main image
    $zip->addFromString("images/{$model}.png", $png_1x1);
    // Tech image
    $zip->addFromString("images/{$model}-tech.png", $png_1x1);
}

// 3. Sample PDFs (minimal valid PDF)
$pdf_minimal = "%PDF-1.4\n1 0 obj <</Type /Catalog /Pages 2 0 R>> endobj\n2 0 obj <</Type /Pages /Kids [3 0 R] /Count 1>> endobj\n3 0 obj <</Type /Page /Parent 2 0 R /Resources <</Font <</F1 4 0 R>>>> /MediaBox [0 0 612 792] /Contents 5 0 R>> endobj\n4 0 obj <</Type /Font /Subtype /Type1 /BaseFont /Helvetica>> endobj\n5 0 obj <</Length 44>> stream\nBT /F1 12 Tf 100 700 Td (Sample Datasheet) Tj ET\nendstream endobj\nxref\n0 6\n0000000000 65535 f\n0000000010 00000 n\n0000000053 00000 n\n0000000097 00000 n\n0000000165 00000 n\n0000000220 00000 n\ntrailer <</Size 6 /Root 1 0 R>>\nstartxref\n308\n%%EOF";

foreach (['DT-25','DT-35','DTG-50','E2510','E7508','BV1.5','SV2.5','RV5.5','SC-10','OT-25'] as $model) {
    $zip->addFromString("pdfs/{$model}-spec.pdf", $pdf_minimal);
}

// 4. Manifest
$manifest = json_encode([
    'version' => '1.0',
    'schema'  => 'xxdq/v1',
    'note'    => 'Sample pack for testing xxdq-pim import.',
    'products'=> count($csv_rows) - 1,
    'images'  => 20,
    'pdfs'    => 10,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
$zip->addFromString('manifest.json', $manifest);

// 5. README
$readme = "# Sample Pack — xxdq-pim test data\n\nUpload this ZIP via WP Admin → Products → Import Pack to test the importer.\n\nStructure:\n* products.csv — 10 products (DT/E/BV/SV/RV/SC/OT series)\n* images/ — 20 PNG placeholders (10 main + 10 tech)\n* pdfs/   — 10 minimal PDFs (one per product)\n\nTry dry-run mode first to see what would happen.\n";
$zip->addFromString('README.txt', $readme);

$zip->close();

echo "Sample pack written: {$zip_path}\n";
echo "Size: " . size_format(filesize($zip_path)) . "\n";
echo "Contains: " . (count($csv_rows) - 1) . " products, 20 images, 10 PDFs.\n";
