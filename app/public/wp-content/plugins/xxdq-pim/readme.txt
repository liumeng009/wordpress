=== XXDQ PIM ===
Contributors: xxdq
Tags: product, import, export, csv, pack
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later

Bulk product information management via ZIP packs.

== Description ==

Import/export products with images and PDFs as a single ZIP pack.

Pack structure:
* `products.csv` — product metadata (model + 10 ACF fields + category + series)
* `images/` — main image (MODEL.jpg), tech image (MODEL-tech.jpg)
* `pdfs/` — datasheets (MODEL-spec.pdf, MODEL-cert.pdf, MODEL-install.pdf)
* `manifest.json` — optional pack metadata

== Changelog ==

= 1.0.0 =
* Initial release.
