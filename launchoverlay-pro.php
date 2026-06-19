<?php
/**
 * Plugin Name:       LaunchOverlay Pro
 * Plugin URI:        https://launchoverlay.com/pro/
 * Description:       Pro extension for LaunchOverlay — custom overlay text, per-product colors, diagonal ribbon, launch scheduling, and bulk category/tag rules. Requires LaunchOverlay (free) to be installed and active.
 * Version:           1.1.1
 * Author:            Mark J. Gorrie
 * Author URI:        https://gorrie.us/
 * License:           Proprietary
 * Text Domain:       launchoverlay-pro
 * Domain Path:       /languages
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * WC requires at least: 6.0
 * WC tested up to:   9.0
 *
 * @package LaunchOverlayPro
 */

defined( 'ABSPATH' ) || exit;

// ── Constants ────────────────────────────────────────────────────────────────
define( 'LO_PRO_VERSION',     '1.1.1' );
define( 'LO_PRO_FILE',        __FILE__ );
define( 'LO_PRO_DIR',         plugin_dir_path( __FILE__ ) );
define( 'LO_PRO_URL',         plugin_dir_url( __FILE__ ) );
define( 'LO_PRO_BASE',        plugin_basename( __FILE__ ) );

// Signal to Lite that Pro is active
define( 'LAUNCHOVERLAY_PRO_ACTIVE', true );

// ── HPOS compatibility ────────────────────────────────────────────────────────
add_action( 'before_woocommerce_init', function () {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
			'custom_order_tables', __FILE__, true
		);
	}
} );

// ── Boot (after Lite has booted at priority 5) ───────────────────────────────
add_action( 'plugins_loaded', 'lo_pro_boot', 10 );

function lo_pro_boot() {

	// Lite must be active
	if ( ! defined( 'LAUNCHOVERLAY_VERSION' ) ) {
		add_action( 'admin_notices', 'lo_pro_lite_missing_notice' );
		return;
	}

	// WooCommerce must be active
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	load_plugin_textdomain( 'launchoverlay-pro', false, dirname( LO_PRO_BASE ) . '/languages' );

	require_once LO_PRO_DIR . 'includes/class-lo-license.php';
	require_once LO_PRO_DIR . 'includes/class-lo-pro-overlay.php';
	require_once LO_PRO_DIR . 'includes/class-lo-pro-product-meta.php';
	require_once LO_PRO_DIR . 'includes/class-lo-pro-rules.php';
	require_once LO_PRO_DIR . 'includes/class-lo-pro-scheduler.php';
	require_once LO_PRO_DIR . 'admin/class-lo-pro-admin.php';

	LO_License::instance();
	LO_Pro_Product_Meta::instance();
	LO_Pro_Rules::instance();
	LO_Pro_Scheduler::instance();
	LO_Pro_Admin::instance();

	// Only boot the overlay extension if license is valid
	if ( LO_License::is_pro() ) {
		LO_Pro_Overlay::instance();
	}
}

function lo_pro_lite_missing_notice() {
	printf(
		'<div class="notice notice-error"><p>%s</p></div>',
		sprintf(
			/* translators: %s plugin link */
			esc_html__( 'LaunchOverlay Pro requires %s to be installed and active.', 'launchoverlay-pro' ),
			'<a href="' . esc_url( admin_url( 'plugin-install.php?s=launchoverlay&tab=search&type=term' ) ) . '">LaunchOverlay (free)</a>'
		)
	);
}

// ── Plugin action links ──────────────────────────────────────────────────────
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), function ( $links ) {
	array_unshift( $links,
		'<a href="' . admin_url( 'admin.php?page=launchoverlay-pro' ) . '">' . __( 'Settings', 'launchoverlay-pro' ) . '</a>'
	);
	return $links;
} );

// ── Activation ───────────────────────────────────────────────────────────────
register_activation_hook( __FILE__, function () {
	if ( ! defined( 'LAUNCHOVERLAY_VERSION' ) ) {
		deactivate_plugins( LO_PRO_BASE );
		wp_die(
			esc_html__( 'LaunchOverlay Pro requires the free LaunchOverlay plugin to be installed and activated first.', 'launchoverlay-pro' ),
			esc_html__( 'Plugin Activation Error', 'launchoverlay-pro' ),
			array( 'back_link' => true )
		);
	}
} );

// ── Deactivation ─────────────────────────────────────────────────────────────
register_deactivation_hook( __FILE__, function () {
	wp_clear_scheduled_hook( 'lo_pro_license_check' );
	wp_clear_scheduled_hook( 'lo_pro_scheduler_run' );
} );
