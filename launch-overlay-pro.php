<?php
/**
 * Plugin Name:       LaunchOverlay Pro
 * Plugin URI:        https://gorrie.us/products
 * Description:       Premium add-on for LaunchOverlay Lite. Adds Pro functionality when the Lite/core plugin is active.
 * Version:           1.1.1
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Gorrie Technology Group, Inc.
 * Author URI:        https://gorrie.us
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       launch-overlay-pro
 * Domain Path:       /languages
 * Requires Plugins:  launch-overlay-lite
 *
 * @package LaunchOverlayPro
 */

defined( 'ABSPATH' ) || exit;

define( 'LAUNCH_OVERLAY_PRO_VERSION', '1.1.1' );
define( 'LAUNCH_OVERLAY_PRO_FILE', __FILE__ );
define( 'LAUNCH_OVERLAY_PRO_PATH', plugin_dir_path( __FILE__ ) );
define( 'LAUNCH_OVERLAY_PRO_URL', plugin_dir_url( __FILE__ ) );
define( 'LAUNCH_OVERLAY_PRO_BASENAME', plugin_basename( __FILE__ ) );

/**
 * This is the unlock flag consumed by LaunchOverlay Lite/core.
 * It is defined immediately, before the plugins_loaded hook, so the Lite plugin can see it while booting.
 */
if ( ! defined( 'LAUNCH_OVERLAY_PRO' ) ) {
	define( 'LAUNCH_OVERLAY_PRO', true );
}

/**
 * Check whether the Lite/core plugin is available.
 *
 * @return bool
 */
function launch_overlay_pro_has_lite(): bool {
	return defined( 'LAUNCH_OVERLAY_VERSION' ) || class_exists( 'Launch_Overlay_Core' );
}

/**
 * Admin notice when Lite is missing.
 *
 * @return void
 */
function launch_overlay_pro_lite_missing_notice(): void {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	echo '<div class="notice notice-error"><p>';
	echo esc_html__( 'LaunchOverlay Pro requires LaunchOverlay Lite to be installed and active.', 'launch-overlay-pro' );
	echo '</p></div>';
}

/**
 * Safe activation check. Do not fatal if Lite is unavailable; show a clear notice instead.
 *
 * @return void
 */
function launch_overlay_pro_activate(): void {
	if ( ! function_exists( 'is_plugin_active' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	$known_lite_paths = array(
		'launch-overlay-lite/launch-overlay.php',
		'launch-overlay/launch-overlay.php',
		'launchoverlay-lite/launch-overlay.php',
	);

	foreach ( $known_lite_paths as $lite_path ) {
		if ( is_plugin_active( $lite_path ) ) {
			return;
		}
	}

	set_transient( 'launch_overlay_pro_lite_missing', true, 60 );
}
register_activation_hook( __FILE__, 'launch_overlay_pro_activate' );

/**
 * Clear scheduled Pro events on deactivation.
 *
 * @return void
 */
function launch_overlay_pro_deactivate(): void {
	wp_clear_scheduled_hook( 'launch_overlay_cron' );
}
register_deactivation_hook( __FILE__, 'launch_overlay_pro_deactivate' );

/**
 * Boot Pro add-on pieces after Lite has had a chance to load.
 *
 * Current compatibility behavior:
 * - If the current Lite build already defines Pro classes, this add-on does not redeclare them.
 * - If a future clean Lite build omits those classes, this add-on loads and starts them.
 *
 * @return void
 */
function launch_overlay_pro_boot(): void {
	if ( ! launch_overlay_pro_has_lite() ) {
		add_action( 'admin_notices', 'launch_overlay_pro_lite_missing_notice' );
		return;
	}

	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	if ( ! class_exists( 'Launch_Overlay_Scheduler' ) ) {
		require_once LAUNCH_OVERLAY_PRO_PATH . 'includes/class-launch-overlay-scheduler.php';
	}

	if ( ! class_exists( 'Launch_Overlay_Bulk_Rules' ) ) {
		require_once LAUNCH_OVERLAY_PRO_PATH . 'includes/class-launch-overlay-bulk-rules.php';
	}

	// If Lite did not instantiate these classes, instantiate them here.
	if ( class_exists( 'Launch_Overlay_Scheduler' ) && ! did_action( 'launch_overlay_pro_scheduler_loaded' ) ) {
		new Launch_Overlay_Scheduler();
		do_action( 'launch_overlay_pro_scheduler_loaded' );
	}

	if ( class_exists( 'Launch_Overlay_Bulk_Rules' ) && ! did_action( 'launch_overlay_pro_bulk_rules_loaded' ) ) {
		new Launch_Overlay_Bulk_Rules();
		do_action( 'launch_overlay_pro_bulk_rules_loaded' );
	}
}
add_action( 'plugins_loaded', 'launch_overlay_pro_boot', 20 );

/**
 * Show one-time activation warning if needed.
 *
 * @return void
 */
function launch_overlay_pro_activation_notice(): void {
	if ( get_transient( 'launch_overlay_pro_lite_missing' ) ) {
		delete_transient( 'launch_overlay_pro_lite_missing' );
		launch_overlay_pro_lite_missing_notice();
	}
}
add_action( 'admin_notices', 'launch_overlay_pro_activation_notice' );
