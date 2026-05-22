=== LaunchOverlay – Coming Soon Banner for Products ===
Contributors:      markjgorrie
Tags:              woocommerce, coming soon, pre-order, product overlay, banner
Requires at least: 6.0
Tested up to:      6.9
Requires PHP:      7.4
Stable tag:        1.1.0
WC requires at least: 7.0
WC tested up to:      9.0
License:           GPLv2 or later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

Add "Coming Soon", "Pre-Order", and launch banner overlays to WooCommerce product images. Control purchase availability per product.

== Description ==

**LaunchOverlay** lets you add eye-catching banners to your WooCommerce product images so customers know a product is *Coming Soon*, *Pre-Order*, or *Launching Soon* — before it goes live.

= Free Features =

* 5 preset banner texts (Coming Soon, Pre-Order, Launching Soon, Sold Out, Available Soon)
* 6 colour themes (Dark, Light, Blue, Green, Amber, Red)
* Adjustable opacity (0–100%)
* Fixed centre placement on product images
* Per-product enable/disable
* Per-product banner text override
* Hide price display per product or globally
* Disable Add to Cart with configurable replacement text
* WooCommerce HPOS (custom order tables) compatible
* Accessible markup (aria-hidden on decorative overlay)
* Lightweight — pure CSS, no jQuery dependencies

= Pro Features (coming soon) =

* Custom banner text (free-form)
* Full RGB / HEX colour picker
* Placement options: Top, Bottom, Corners
* Optional text rotation
* Scheduling – set start/end date for automatic activation
* Auto-switch product to "live" after schedule ends

== Installation ==

1. Upload the `launch-overlay` folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** screen.
3. Navigate to **WooCommerce → Settings → LaunchOverlay** to configure global defaults.
4. Edit any product and expand the **🚀 LaunchOverlay Banner** section under the General tab.

== Frequently Asked Questions ==

= Does this work with block themes? =
Yes. The overlay is injected via the `woocommerce_product_get_image` filter which works with both classic and block themes.

= Will it slow down my store? =
No. The plugin enqueues a single minifiable CSS file (~2 KB) only on shop/product pages.

= Where are the Pro features? =
A Pro add-on is in development. Join the mailing list on our website to be notified at launch.

== Screenshots ==

1. Product image with "Coming Soon" overlay on the shop page.
2. Per-product LaunchOverlay settings panel inside the product editor.
3. Global settings page under WooCommerce → Settings → LaunchOverlay.

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
Initial release — no upgrade required.
