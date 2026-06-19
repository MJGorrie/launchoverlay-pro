<?php
/**
 * LO_License — Pro license management.
 *
 * HOW LICENSE KEYS WORK:
 * ──────────────────────
 * When you sell LaunchOverlay Pro (e.g. via Lemon Squeezy, Paddle, WooCommerce,
 * Easy Digital Downloads), your payment platform generates a license key per
 * purchase. You connect it to your own licensing API at API_BASE below.
 *
 * FOR DEVELOPMENT / TESTING — use the constant bypass:
 *   Add this to wp-config.php:
 *   define( 'LO_PRO_DEV_LICENSE', true );
 * This skips the API call and marks Pro as active immediately.
 *
 * FOR PRODUCTION — replace API_BASE with your real licensing server URL.
 * Popular options: Lemon Squeezy, Freemius, EDD Software Licensing,
 * WooCommerce Software Add-On, or a custom Laravel/WP endpoint.
 *
 * @package LaunchOverlayPro
 */

defined( 'ABSPATH' ) || exit;

class LO_License {

	const OPT_KEY    = 'lo_pro_license_key';
	const OPT_STATUS = 'lo_pro_license_status';
	const OPT_DATA   = 'lo_pro_license_data';
	const TRANS_OK   = 'lo_pro_license_valid';

	const VALID    = 'valid';
	const INVALID  = 'invalid';
	const EXPIRED  = 'expired';
	const INACTIVE = 'inactive';
	const EMPTY_K  = 'empty';

	/**
	 * YOUR LICENSE API ENDPOINT.
	 *
	 * Replace this with your real server URL once you set up a licensing system.
	 * The plugin will POST to /validate and /deactivate with:
	 *   license_key, site_url, plugin, version
	 * and expects a JSON response: { "status": "valid", "expires": "...", ... }
	 *
	 * For dev testing, define LO_PRO_DEV_LICENSE in wp-config.php instead.
	 */
	const API_BASE = 'https://api.launchoverlay.com/v1/license';

	const CACHE_TTL = 43200; // 12 hours

	private static $inst = null;

	public static function instance() {
		if ( null === self::$inst ) self::$inst = new self();
		return self::$inst;
	}

	private function __construct() {
		add_action( 'admin_init', array( $this, 'handle_form' ) );

		if ( ! wp_next_scheduled( 'lo_pro_license_check' ) ) {
			wp_schedule_event( time(), 'daily', 'lo_pro_license_check' );
		}
		add_action( 'lo_pro_license_check', array( $this, 'remote_validate' ) );
	}

	// ── Public API ────────────────────────────────────────────────────────────

	/**
	 * Is Pro active?
	 *
	 * Returns true when:
	 *  1. LO_PRO_DEV_LICENSE is defined and true in wp-config.php (dev bypass), OR
	 *  2. A valid license key has been activated via the license form.
	 */
	public static function is_pro() {
		// Development / testing bypass — add to wp-config.php:
		// define( 'LO_PRO_DEV_LICENSE', true );
		if ( defined( 'LO_PRO_DEV_LICENSE' ) && LO_PRO_DEV_LICENSE ) {
			return true;
		}

		$cached = get_transient( self::TRANS_OK );
		if ( false !== $cached ) return 'yes' === $cached;

		$status = get_option( self::OPT_STATUS, self::EMPTY_K );
		$ok     = self::VALID === $status;
		set_transient( self::TRANS_OK, $ok ? 'yes' : 'no', self::CACHE_TTL );
		return $ok;
	}

	public static function get_key_display() {
		$k = get_option( self::OPT_KEY, '' );
		if ( empty( $k ) ) return '';
		if ( strlen( $k ) <= 8 ) return str_repeat( '●', strlen( $k ) );
		return substr( $k, 0, 4 ) . str_repeat( '●', max( 0, strlen( $k ) - 8 ) ) . substr( $k, -4 );
	}

	public static function get_key() { return get_option( self::OPT_KEY, '' ); }

	public static function get_status() {
		if ( defined( 'LO_PRO_DEV_LICENSE' ) && LO_PRO_DEV_LICENSE ) {
			return self::VALID;
		}
		return get_option( self::OPT_STATUS, self::EMPTY_K );
	}

	public static function get_data() {
		if ( defined( 'LO_PRO_DEV_LICENSE' ) && LO_PRO_DEV_LICENSE ) {
			return array(
				'customer_name'  => 'Developer',
				'customer_email' => '',
				'plan'           => 'Pro (Dev Mode)',
				'expires'        => 'Never (dev bypass)',
				'validated_at'   => current_time( 'mysql' ),
			);
		}
		$d = get_option( self::OPT_DATA, array() );
		return is_array( $d ) ? $d : array();
	}

	public static function status_label() {
		if ( defined( 'LO_PRO_DEV_LICENSE' ) && LO_PRO_DEV_LICENSE ) {
			return __( '✓ Active (Dev Mode)', 'launchoverlay-pro' );
		}
		$labels = array(
			self::VALID    => __( '✓ Active',        'launchoverlay-pro' ),
			self::INVALID  => __( '✗ Invalid',       'launchoverlay-pro' ),
			self::EXPIRED  => __( '⏰ Expired',       'launchoverlay-pro' ),
			self::INACTIVE => __( '— Not Activated', 'launchoverlay-pro' ),
			self::EMPTY_K  => __( '— No Key Entered','launchoverlay-pro' ),
		);
		$s = self::get_status();
		return isset( $labels[ $s ] ) ? $labels[ $s ] : ucfirst( $s );
	}

	// ── Form handling ─────────────────────────────────────────────────────────

	public function handle_form() {
		if ( empty( $_POST['lo_pro_license_nonce'] ) ) return;
		if ( ! wp_verify_nonce( sanitize_key( $_POST['lo_pro_license_nonce'] ), 'lo_pro_license' ) ) return;
		if ( ! current_user_can( 'manage_woocommerce' ) ) return;

		$action = isset( $_POST['lo_license_action'] ) ? sanitize_key( $_POST['lo_license_action'] ) : '';

		if ( 'activate' === $action ) {
			$key = isset( $_POST['lo_license_key'] ) ? sanitize_text_field( wp_unslash( $_POST['lo_license_key'] ) ) : '';
			$this->do_activate( $key );
		} elseif ( 'deactivate' === $action ) {
			$this->do_deactivate();
		}
	}

	private function do_activate( $key ) {
		if ( empty( $key ) ) {
			$this->set_notice( __( 'Please enter your license key.', 'launchoverlay-pro' ), 'error' );
			return;
		}

		update_option( self::OPT_KEY, $key );
		$result = $this->remote_validate( $key );

		if ( is_wp_error( $result ) ) {
			// API unreachable — give benefit of the doubt for 24h
			$this->set_notice(
				__( 'Could not reach the license server. Please try again. If the problem persists, contact support.', 'launchoverlay-pro' ),
				'error'
			);
		} elseif ( self::VALID === ( $result['status'] ?? '' ) ) {
			$this->set_notice( __( '🎉 Pro license activated! All Pro features are now unlocked.', 'launchoverlay-pro' ), 'success' );
		} elseif ( self::EXPIRED === ( $result['status'] ?? '' ) ) {
			$this->set_notice( __( 'Your license has expired. Please renew at launchoverlay.com/pro/', 'launchoverlay-pro' ), 'warning' );
		} else {
			$msg = $result['message'] ?? __( 'Invalid license key. Please check your purchase email and try again.', 'launchoverlay-pro' );
			$this->set_notice( $msg, 'error' );
		}
	}

	private function do_deactivate() {
		$key = self::get_key();
		if ( ! empty( $key ) ) {
			wp_remote_post( self::API_BASE . '/deactivate', array(
				'timeout' => 8,
				'body'    => array(
					'license_key' => $key,
					'site_url'    => home_url(),
					'plugin'      => 'launchoverlay-pro',
				),
			) );
		}
		update_option( self::OPT_STATUS, self::INACTIVE );
		delete_transient( self::TRANS_OK );
		$this->set_notice( __( 'License deactivated. Pro features have been disabled.', 'launchoverlay-pro' ), 'info' );
	}

	// ── Remote validation ─────────────────────────────────────────────────────

	public function remote_validate( $key = null ) {
		if ( null === $key ) $key = self::get_key();
		if ( empty( $key ) ) {
			update_option( self::OPT_STATUS, self::EMPTY_K );
			delete_transient( self::TRANS_OK );
			return array( 'status' => self::EMPTY_K );
		}

		$resp = wp_remote_post( self::API_BASE . '/validate', array(
			'timeout' => 15,
			'body'    => array(
				'license_key' => $key,
				'site_url'    => home_url(),
				'plugin'      => 'launchoverlay-pro',
				'version'     => LO_PRO_VERSION,
			),
		) );

		if ( is_wp_error( $resp ) ) return $resp;

		$code = (int) wp_remote_retrieve_response_code( $resp );
		$body = json_decode( wp_remote_retrieve_body( $resp ), true );

		if ( 200 !== $code || ! is_array( $body ) ) {
			return new WP_Error( 'api_error', __( 'License server returned an unexpected response.', 'launchoverlay-pro' ) );
		}

		$status = sanitize_key( $body['status'] ?? self::INVALID );
		update_option( self::OPT_STATUS, $status );
		update_option( self::OPT_DATA, array(
			'customer_name'    => sanitize_text_field( $body['customer_name']    ?? '' ),
			'customer_email'   => sanitize_email(      $body['customer_email']   ?? '' ),
			'plan'             => sanitize_text_field( $body['plan']             ?? 'pro' ),
			'expires'          => sanitize_text_field( $body['expires']          ?? '' ),
			'activations_left' => absint(              $body['activations_left'] ?? 0 ),
			'validated_at'     => current_time( 'mysql' ),
		) );
		delete_transient( self::TRANS_OK );
		return $body;
	}

	// ── Notices ───────────────────────────────────────────────────────────────

	private function set_notice( $msg, $type = 'info' ) {
		set_transient( 'lo_pro_notice', compact( 'msg', 'type' ), 60 );
	}

	public static function maybe_show_notice() {
		$n = get_transient( 'lo_pro_notice' );
		if ( ! $n ) return;
		delete_transient( 'lo_pro_notice' );
		$map = array(
			'success' => 'notice-success',
			'error'   => 'notice-error',
			'warning' => 'notice-warning',
			'info'    => 'notice-info',
		);
		$cls = $map[ $n['type'] ] ?? 'notice-info';
		printf( '<div class="notice %s is-dismissible"><p>%s</p></div>', esc_attr( $cls ), esc_html( $n['msg'] ) );
	}
}
