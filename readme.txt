=== Rug Pad Addon for WooCommerce ===
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPL-2.0-or-later

Adds a "Need a rugpad? Click Here" link to the Cart and Checkout pages. Customers enter a size and the pad is priced per square foot.

== Installation ==

1. Upload the `rug-pad-addon` folder to `/wp-content/plugins/` (or upload the zip via Plugins > Add New > Upload) and activate it.
2. Go to WooCommerce > Rug Pad Addon and choose the existing simple product(s) that are rug pads. Nothing is created automatically.
3. Set the price per square foot (default 2.00), the link text and where it appears (cart, checkout, empty cart).
4. Recommended: set the chosen product's catalog visibility to "Hidden". Keep it a physical product (not Virtual) so shipping is calculated. Weight, shipping class, tax class and stock are taken from the product itself.

== How pricing works ==

* Each side is entered in feet and inches. Any extra inch rounds up to the next whole foot (11 ft 1 in and 11 ft 8 in both bill as 12 ft).
* Area = billed length x billed width. Price = area x price per square foot.
* Example: 9 ft x 11 ft = 99 sq ft x 2.00 = 198.00.
* The price is always recalculated on the server; the browser value is display only.
* The pad is a normal physical product, so shipping and tax are calculated by WooCommerce as usual.

== Customer flow ==

* "Need a rugpad? Click Here" opens the size form (product image, description, length and width, live area and total).
* If several rug pad products are selected, the customer chooses one first.
* The link also shows on an empty cart, so a rug pad can be bought on its own.
* Pads can be added several times with different sizes. Each is its own cart/order line.

== Order details ==

Each pad line shows Length, Width, Billed size, Total area, Price per sq ft and Pad total on the order screen, emails and My Account. Raw values are stored as hidden meta (_rpa_*) for reports.

== Placement ==

Works automatically with the classic cart/checkout and the Cart/Checkout blocks. For custom layouts use the shortcode [rug_pad_addon].

== Upgrading from 1.0.0 ==

1.0.0 auto-created a virtual product named "Rug Pad". 1.1.0 no longer does. You can select that product in settings (untick Virtual first so shipping applies) or delete it.
