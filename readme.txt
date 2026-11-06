=== Rug Pad Addon for WooCommerce ===
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPL-2.0-or-later

Offers a custom-size rug pad on the Cart and Checkout pages, priced per square foot.

== Installation ==

1. Upload the `rug-pad-addon` folder to `/wp-content/plugins/` (or upload the zip via Plugins > Add New > Upload).
2. Activate it. A hidden "Rug Pad" product is created automatically.
3. Go to WooCommerce > Rug Pad Addon to set the price per square foot (default 2.00), heading, button label and where to show the section.
4. Optional: edit the "Rug Pad" product to set its featured image and short description (shown in the section). Leave its price at 0 - it is calculated automatically.

== How pricing works ==

* Each side is entered in feet and inches. Any extra inch rounds up to the next whole foot (11 ft 1 in and 11 ft 8 in both bill as 12 ft).
* Area = billed length x billed width. Price = area x price per square foot.
* Example: 9 ft x 11 ft = 99 sq ft x 2.00 = 198.00.
* The price is always recalculated on the server; the browser value is display only.

== Order details ==

Each pad is its own order line with: Length, Width, Billed size, Total area, Price per sq ft and Pad total, shown on the order screen, order emails and My Account. Raw values are also stored as hidden meta (_rpa_*) for reports.

== Placement ==

Works automatically with the classic cart/checkout and the Cart/Checkout blocks. For custom layouts use the shortcode [rug_pad_addon].
