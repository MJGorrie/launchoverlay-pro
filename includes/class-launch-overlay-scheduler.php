<?php
/**
 * Scheduler – auto-activates and deactivates overlays by date.
 * Pro feature only. Runs via WP-Cron hourly.
 *
 * Logic per product:
 *  - schedule_start set, now < start  → overlay ACTIVE (not launched yet)
 *  - schedule_start set, now >= start → check end date
 *  - schedule_end set,  now >= end    → overlay DISABLED (product goes live)
 *  - No dates set                     → overlay follows manual enabled flag
 *
 * @package LaunchOverlay
 * @author  Gorrie Technology Group, Inc.
 */

defined( 'ABSPATH' ) || exit;

class Launch_Overlay_Scheduler {

	const CRON_HOOK  = 'launch_overlay_cron';
	const META_START = '_launch_overlay_schedule_start';
	const META_END   = '_launch_overlay_schedule_end';
	const META_SCHED = '_launch_overlay_schedule_enabled';

	public function __construct() {
		// Register cron schedule.
		add_filter( 'cron_schedules', [ $this, 'add_cron_interval' ] );

		// Hook cron job.
		add_action( self::CRON_HOOK, [ $this, 'run' ] );

		// Schedule on activation (called from main file).
		add_action( 'launch_overlay_activated', [ $this, 'schedule_cron' ] );

		// Clear on deactivation.
		add_action( 'launch_overlay_deactivated', [ $this, 'clear_cron' ] );

		// Ensure cron is scheduled after init (safety net).
		add_action( 'init', [ $this, 'maybe_schedule_cron' ] );
	}

	// ── Cron registration ──────────────────────────────────────────────────────

	public function add_cron_interval( array $schedules ): array {
		$schedules['launch_overlay_hourly'] = [
			'interval' => HOUR_IN_SECONDS,
			'display'  => __( 'Every Hour (LaunchOverlay)', 'launch-overlay' ),
		];
		return $schedules;
	}

	public function maybe_schedule_cron(): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time(), 'launch_overlay_hourly', self::CRON_HOOK );
		}
	}

	public function schedule_cron(): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time(), 'launch_overlay_hourly', self::CRON_HOOK );
		}
	}

	public function clear_cron(): void {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
		}
	}

	// ── Main cron runner ───────────────────────────────────────────────────────

	/**
	 * Check all products with scheduling enabled and update their overlay state.
	 */
	public function run(): void {
		if ( ! Launch_Overlay_Core::is_pro() ) {
			return;
		}

		$now = current_time( 'timestamp' );

		// Query all products that have scheduling turned on.
		$product_ids = get_posts( [
			'post_type'      => 'product',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'meta_query'     => [
				[
					'key'   => self::META_SCHED,
					'value' => 'yes',
				],
			],
		] );

		foreach ( $product_ids as $product_id ) {
			$this->process_product( (int) $product_id, $now );
		}

		// Log last run.
		update_option( 'launch_overlay_scheduler_last_run', current_time( 'mysql' ), false );
	}

	/**
	 * Evaluate schedule for a single product and update overlay enabled flag.
	 *
	 * @param int $product_id
	 * @param int $now  Unix timestamp
	 */
	public function process_product( int $product_id, int $now ): void {
		$start_str = get_post_meta( $product_id, self::META_START, true );
		$end_str   = get_post_meta( $product_id, self::META_END,   true );

		$start = $start_str ? strtotime( $start_str ) : false;
		$end   = $end_str   ? strtotime( $end_str )   : false;

		$currently_enabled = 'yes' === get_post_meta( $product_id, '_launch_overlay_enabled', true );

		// Determine what the state SHOULD be.
		$should_enable = $currently_enabled; // default: no change

		if ( $start && $end ) {
			// Full range: overlay active between start and end.
			$should_enable = ( $now >= $start && $now < $end );
		} elseif ( $start && ! $end ) {
			// Start only: overlay active until start date, then goes live.
			$should_enable = ( $now < $start );
		} elseif ( ! $start && $end ) {
			// End only: overlay active until end date.
			$should_enable = ( $now < $end );
		}

		// Only write if state changed (avoid unnecessary DB writes).
		if ( $should_enable !== $currently_enabled ) {
			update_post_meta( $product_id, '_launch_overlay_enabled', $should_enable ? 'yes' : 'no' );

			// Fire an action so other plugins / logging can respond.
			do_action( 'launch_overlay_product_state_changed', $product_id, $should_enable );

			// If product just went live, clear page cache for it.
			clean_post_cache( $product_id );
			if ( function_exists( 'rocket_clean_post' ) ) {
				rocket_clean_post( $product_id );
			}
		}
	}

	// ── Static helper ─────────────────────────────────────────────────────────

	/**
	 * Check if a product is currently within its scheduled window.
	 * Used by frontend to resolve effective overlay state without waiting for cron.
	 *
	 * @param int $product_id
	 * @return bool|null  true = force enable, false = force disable, null = no schedule
	 */
	public static function get_scheduled_state( int $product_id ): ?bool {
		if ( ! Launch_Overlay_Core::is_pro() ) {
			return null;
		}

		$sched = get_post_meta( $product_id, self::META_SCHED, true );
		if ( 'yes' !== $sched ) {
			return null;
		}

		$now       = current_time( 'timestamp' );
		$start_str = get_post_meta( $product_id, self::META_START, true );
		$end_str   = get_post_meta( $product_id, self::META_END,   true );

		$start = $start_str ? strtotime( $start_str ) : false;
		$end   = $end_str   ? strtotime( $end_str )   : false;

		if ( $start && $end ) {
			return ( $now >= $start && $now < $end );
		} elseif ( $start ) {
			return ( $now < $start );
		} elseif ( $end ) {
			return ( $now < $end );
		}

		return null;
	}
}
