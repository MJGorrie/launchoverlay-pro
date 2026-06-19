<?php
/**
 * LaunchOverlay Pro — Uninstall
 * Runs when the plugin is deleted from WordPress.
 *
 * @package LaunchOverlayPro
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// License data
delete_option( 'lo_pro_license_key' );
delete_option( 'lo_pro_license_status' );
delete_option( 'lo_pro_license_data' );
delete_transient( 'lo_pro_license_valid' );
delete_transient( 'lo_pro_notice' );
delete_transient( 'lo_pro_scheduler_last_run' );

// Bulk rules
delete_option( 'lo_pro_bulk_rules' );

// Per-product Pro meta
$pro_meta_keys = array(
	'_lo_pro_custom_text',
	'_lo_pro_bg_color',
	'_lo_pro_text_color',
	'_lo_pro_opacity',
	'_lo_pro_style',
	'_lo_pro_launch_date',
	'_lo_pro_auto_launch',
);

foreach ( $pro_meta_keys as $k ) {
	delete_post_meta_by_key( $k );
}

// Cron events
wp_clear_scheduled_hook( 'lo_pro_license_check' );
wp_clear_scheduled_hook( 'lo_pro_scheduler_run' );
