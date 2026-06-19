<?php
/**
 * LO_Pro_Scheduler — auto-removes product overlays when launch date arrives.
 *
 * Runs hourly via WP-Cron.
 *
 * @package LaunchOverlayPro
 */

defined( 'ABSPATH' ) || exit;

class LO_Pro_Scheduler {

	private static $inst = null;

	public static function instance() {
		if ( null === self::$inst ) self::$inst = new self();
		return self::$inst;
	}

	private function __construct() {
		// Register cron hook
		add_action( 'lo_pro_scheduler_run', array( $this, 'run' ) );

		// Schedule if not already scheduled
		if ( ! wp_next_scheduled( 'lo_pro_scheduler_run' ) ) {
			wp_schedule_event( time(), 'hourly', 'lo_pro_scheduler_run' );
		}

		// Allow manual trigger from admin (e.g. saving settings page)
		add_action( 'admin_init', array( $this, 'maybe_manual_run' ) );
	}

	/**
	 * Main scheduler run — queries all products with auto_launch=yes
	 * and disables the overlay if the launch date has passed.
	 */
	public function run() {
		if ( ! LO_License::is_pro() ) return;

		$ids = $this->get_scheduled_product_ids();
		if ( empty( $ids ) ) return;

		$now = current_time( 'timestamp' );

		foreach ( $ids as $pid ) {
			$launch_date = get_post_meta( $pid, '_lo_pro_launch_date', true );
			if ( empty( $launch_date ) ) {
				// No date set — clear the auto_launch flag
				update_post_meta( $pid, '_lo_pro_auto_launch', 'no' );
				continue;
			}

			$launch_ts = strtotime( $launch_date );
			if ( false === $launch_ts ) continue;

			if ( $now >= $launch_ts ) {
				// Launch date has passed — disable the overlay
				update_post_meta( $pid, '_lo_enabled', 'no' );
				// Prevent re-processing
				update_post_meta( $pid, '_lo_pro_auto_launch', 'no' );

				/**
				 * Fires after a product's overlay is auto-removed by the scheduler.
				 *
				 * @param int $pid Product ID.
				 */
				do_action( 'lo_pro_product_launched', $pid );
			}
		}
	}

	/**
	 * Manual trigger: runs on admin page load once per hour at most.
	 */
	public function maybe_manual_run() {
		$last = get_transient( 'lo_pro_scheduler_last_run' );
		if ( $last ) return;

		$this->run();
		set_transient( 'lo_pro_scheduler_last_run', 1, HOUR_IN_SECONDS );
	}

	/**
	 * Get product IDs that have auto-launch enabled.
	 *
	 * @return int[]
	 */
	private function get_scheduled_product_ids() {
		return get_posts( array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => array(
				array(
					'key'     => '_lo_pro_auto_launch',
					'value'   => 'yes',
					'compare' => '=',
				),
			),
		) );
	}

	/**
	 * Return upcoming scheduled launches for display in admin.
	 *
	 * @param  int $limit Maximum rows to return.
	 * @return array[]    Array of [ pid, title, launch_date, time_remaining ]
	 */
	public static function get_upcoming( $limit = 10 ) {
		if ( ! LO_License::is_pro() ) return array();

		$ids = get_posts( array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => $limit * 3, // fetch extra, we'll filter
			'fields'         => 'ids',
			'meta_query'     => array(
				array(
					'key'     => '_lo_pro_auto_launch',
					'value'   => 'yes',
					'compare' => '=',
				),
				array(
					'key'     => '_lo_pro_launch_date',
					'value'   => '',
					'compare' => '!=',
				),
			),
		) );

		$rows = array();
		$now  = current_time( 'timestamp' );

		foreach ( $ids as $pid ) {
			$date = get_post_meta( $pid, '_lo_pro_launch_date', true );
			$ts   = $date ? strtotime( $date ) : false;
			if ( ! $ts || $ts <= $now ) continue; // skip passed or invalid

			$diff = $ts - $now;
			if ( $diff < DAY_IN_SECONDS ) {
				$remaining = human_time_diff( $now, $ts ) . ' ' . __( 'remaining', 'launchoverlay-pro' );
			} else {
				$remaining = date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $ts );
			}

			$rows[] = array(
				'pid'       => $pid,
				'title'     => get_the_title( $pid ),
				'edit_url'  => get_edit_post_link( $pid ),
				'date_raw'  => $date,
				'date_fmt'  => date_i18n( get_option( 'date_format' ) . ' @ ' . get_option( 'time_format' ), $ts ),
				'remaining' => $remaining,
				'ts'        => $ts,
			);

			if ( count( $rows ) >= $limit ) break;
		}

		usort( $rows, function ( $a, $b ) { return $a['ts'] - $b['ts']; } );
		return $rows;
	}
}
