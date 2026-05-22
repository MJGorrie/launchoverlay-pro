<?php
/**
 * Plugin Name:       LaunchOverlay – Coming Soon Banner for Products
 * Plugin URI:        https://gorrie.us/products
 * Description:       Add "Coming Soon", "Pre-Order", and launch banner overlays to WooCommerce product images. Control purchase availability per product. Free (Lite) and Pro tiers in one codebase.
 * Version:           1.1.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Gorrie Technology Group, Inc.
 * Author URI:        https://gorrie.us
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       launch-overlay
 * Domain Path:       /languages
 * WC requires at least: 7.0
 * WC tested up to:   9.0
 *
 * @package LaunchOverlay
 */

defined( 'ABSPATH' ) || exit;

// ── Constants ──────────────────────────────────────────────────────────────────
define( 'LAUNCH_OVERLAY_VERSION',     '1.1.0' );
define( 'LAUNCH_OVERLAY_FILE',        __FILE__ );
define( 'LAUNCH_OVERLAY_PATH',        plugin_dir_path( __FILE__ ) );
define( 'LAUNCH_OVERLAY_URL',         plugin_dir_url( __FILE__ ) );
define( 'LAUNCH_OVERLAY_BASENAME',    plugin_basename( __FILE__ ) );

// ── Tier detection ─────────────────────────────────────────────────────────────
// Set LAUNCH_OVERLAY_PRO to true via a separate Pro licence file/add-on.
// One codebase; Pro features are gated behind this constant.
if ( ! defined( 'LAUNCH_OVERLAY_PRO' ) ) {
	define( 'LAUNCH_OVERLAY_PRO', false );
}

// ── Declare WooCommerce HPOS compatibility ─────────────────────────────────────
add_action( 'before_woocommerce_init', function () {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
			'custom_order_tables',
			__FILE__,
			true
		);
	}
} );

// ── Cache bypass for overlaid products ─────────────────────────────────────────
// Sends a no-cache header on single product pages that have an active overlay.
// Works with WP Rocket, W3 Total Cache, LiteSpeed Cache, and most caching plugins.
add_action( 'template_redirect', 'launch_overlay_maybe_nocache' );

function launch_overlay_maybe_nocache(): void {
	if ( ! is_product() ) {
		return;
	}

	$product_id = get_queried_object_id();
	$enabled    = get_post_meta( $product_id, '_launch_overlay_enabled', true );

	if ( 'yes' !== $enabled ) {
		return;
	}

	// Tell all caching layers this page must not be served from cache.
	nocache_headers();

	// WP Rocket specific.
	if ( function_exists( 'rocket_clean_post' ) ) {
		// Remove this product from WP Rocket's cache on the fly.
		do_action( 'rocket_buffer', '' );
	}

	// LiteSpeed Cache.
	do_action( 'litespeed_control_set_nocache', 'LaunchOverlay: product has active overlay' );

	// WP Super Cache.
	if ( ! defined( 'DONOTCACHEPAGE' ) ) {
		define( 'DONOTCACHEPAGE', true );
	}

	// W3 Total Cache.
	if ( ! defined( 'DONOTCACHEOBJECT' ) ) {
		define( 'DONOTCACHEOBJECT', true );
	}
}

// ── Boot ───────────────────────────────────────────────────────────────────────
add_action( 'plugins_loaded', 'launch_overlay_init', 10 );

/**
 * Initialise the plugin after all plugins have loaded.
 */
function launch_overlay_init() {
	// Hard dependency: WooCommerce must be active.
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'launch_overlay_wc_missing_notice' );
		return;
	}

	// Load translation files.
	load_plugin_textdomain(
		'launch-overlay',
		false,
		dirname( LAUNCH_OVERLAY_BASENAME ) . '/languages'
	);

	// Load core classes.
	require_once LAUNCH_OVERLAY_PATH . 'includes/class-launch-overlay-core.php';
	require_once LAUNCH_OVERLAY_PATH . 'includes/class-launch-overlay-admin.php';
	require_once LAUNCH_OVERLAY_PATH . 'includes/class-launch-overlay-product-meta.php';
	require_once LAUNCH_OVERLAY_PATH . 'includes/class-launch-overlay-frontend.php';
	require_once LAUNCH_OVERLAY_PATH . 'includes/class-launch-overlay-scheduler.php';
	require_once LAUNCH_OVERLAY_PATH . 'includes/class-launch-overlay-bulk-rules.php';
	require_once LAUNCH_OVERLAY_PATH . 'includes/class-launch-overlay-licence.php';
	require_once LAUNCH_OVERLAY_PATH . 'includes/class-launch-overlay-licence-settings.php';

	// Instantiate.
	new Launch_Overlay_Licence();
	Launch_Overlay_Core::instance();
}

/**
 * Admin notice when WooCommerce is not active.
 */
function launch_overlay_wc_missing_notice() {
	echo '<div class="notice notice-error"><p>' .
		sprintf(
			/* translators: %s: WooCommerce link */
			esc_html__( 'LaunchOverlay requires %s to be installed and activated.', 'launch-overlay' ),
			'<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>'
		) .
	'</p></div>';
}

// ── Activation / Deactivation hooks ────────────────────────────────────────────
register_activation_hook( __FILE__,   'launch_overlay_activate' );
register_deactivation_hook( __FILE__, 'launch_overlay_deactivate' );

function launch_overlay_activate() {
	// Activation fires before plugins_loaded so classes are not loaded yet.
	// Require core directly to access default_settings().
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-launch-overlay-core.php';

	// Save default options on first activation only.
	if ( false === get_option( 'launch_overlay_settings' ) ) {
		add_option( 'launch_overlay_settings', Launch_Overlay_Core::default_settings() );
	}
	flush_rewrite_rules();
}

function launch_overlay_deactivate() {
	flush_rewrite_rules();
}


if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists('is_plugin_active') ) {
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
}

add_action('admin_notices', function () {
    if ( ! is_plugin_active('launch-overlay-lite/launch-overlay.php') ) {
        echo '<div class="notice notice-error"><p><strong>LaunchOverlay Pro</strong> requires the LaunchOverlay Lite plugin to be installed and active.</p></div>';
    }
});
