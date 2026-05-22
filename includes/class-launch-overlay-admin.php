<?php
/**
 * Admin settings page and WooCommerce Settings API integration.
 *
 * @package LaunchOverlay
 */

defined( 'ABSPATH' ) || exit;

class Launch_Overlay_Admin {

	public function __construct() {
		// Add a top-level settings page under WooCommerce.
		add_filter( 'woocommerce_get_settings_pages', [ $this, 'register_settings_page' ] );

		// Action links on the plugins screen.
		add_filter(
			'plugin_action_links_' . LAUNCH_OVERLAY_BASENAME,
			[ $this, 'plugin_action_links' ]
		);
	}

	// ── WooCommerce settings page ──────────────────────────────────────────────

	/**
	 * Register our custom WooCommerce settings tab.
	 */
	public function register_settings_page( array $pages ): array {
		require_once LAUNCH_OVERLAY_PATH . 'includes/class-launch-overlay-settings-page.php';
		$pages[] = new Launch_Overlay_Settings_Page();
		return $pages;
	}

	// ── Plugin row action links ────────────────────────────────────────────────

	public function plugin_action_links( array $links ): array {
		$settings_url = admin_url( 'admin.php?page=wc-settings&tab=launch_overlay' );
		array_unshift(
			$links,
			sprintf(
				'<a href="%s">%s</a>',
				esc_url( $settings_url ),
				esc_html__( 'Settings', 'launch-overlay' )
			)
		);
		return $links;
	}
}
