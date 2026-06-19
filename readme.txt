=== LaunchOverlay Pro ===
Plugin URI:           https://gorrie.us/products-page/
Author:               Mark J. Gorrie
Author URI:           https://gorrie.us/
Description:          Pro add-on for LaunchOverlay. Custom banner text, full colour picker, placement options, text rotation, and automatic launch scheduling. Requires LaunchOverlay (free) to be active.
Version:              1.1.0
Requires at least:    6.0
Requires PHP:         7.4
WC requires at least: 7.0
WC tested up to:      9.0
License:              Proprietary

== Description ==

**LaunchOverlay Pro** is the premium add-on for the free LaunchOverlay plugin.

It requires LaunchOverlay (free) to be installed and active, plus a valid Pro license key purchased at https://gorrie.us/products-page/.

= Pro Features =

**Custom Banner Text**
Write your own label per product instead of the preset Coming Soon / Pre-Order / Sold Out text.

**Full RGB / HEX Colour Picker**
Set background colour, text colour, and opacity per product — overriding global defaults for that product only.

**Placement Options**
Position the banner at the Top, Bottom, or any Corner of the product image.

**Optional Text Rotation**
Rotate the banner text for a ribbon/diagonal effect.

**Launch Scheduling**
Set a start and end date for each banner. On the end date the overlay is automatically removed and the product goes live — no manual action needed.

**Auto-Switch to Live**
When the schedule ends, the product overlay is disabled automatically via hourly WP-Cron.

**Bulk Category & Tag Rules**
Apply overlays to all products in a WooCommerce category or with a specific tag without editing each product individually.

**Upcoming Launches Dashboard**
See all scheduled product launches in one place with countdown timers.

= Where to Buy =

Purchase at https://gorrie.us/products-page/

A license key will be emailed to you immediately after purchase.

== Requirements ==

* WordPress 6.0+
* PHP 7.4+
* WooCommerce 7.0+
* LaunchOverlay (free) — must be installed and active
* A valid LaunchOverlay Pro license key from gorrie.us

== Installation ==

1. Purchase LaunchOverlay Pro at https://gorrie.us/products-page/
2. Download the Pro plugin ZIP from the confirmation email or your account dashboard
3. In WordPress admin go to Plugins → Add New → Upload Plugin
4. Upload launchoverlay-pro.zip and click Install Now
5. Activate the plugin
6. Go to WooCommerce → LO Pro → License and enter your license key
7. Click Activate — all Pro features unlock immediately

== Frequently Asked Questions ==

= Where is my license key? =
Your license key is in the purchase confirmation email sent from gorrie.us. Check your spam folder if you cannot find it. The key looks like: XXXX-XXXX-XXXX-XXXX.

= Can I use this on multiple sites? =
License terms depend on the plan purchased. Check your order details at gorrie.us for the number of activations included.

= I am developing locally — can I test without a key? =
Yes. Add `define( 'LO_PRO_DEV_LICENSE', true );` to your wp-config.php to bypass the license check during development. Remove it before deploying to production.

= What happens if my license expires? =
Pro features are disabled. Your free LaunchOverlay settings and overlay data are preserved — nothing is deleted.

== Changelog ==

= 1.1.0 =
* Initial Pro release.
* Custom overlay text per product.
* Per-product HEX colour picker (background, text, opacity).
* Placement options (Top, Bottom, Corners).
* Launch scheduling with auto-remove via WP-Cron.
* Bulk category/tag rules.
* Upcoming launches dashboard.
* Dev-mode bypass (LO_PRO_DEV_LICENSE constant).
* License activation, deactivation, and daily remote re-validation.

== Upgrade Notice ==

= 1.1.0 =
Initial release of LaunchOverlay Pro.
