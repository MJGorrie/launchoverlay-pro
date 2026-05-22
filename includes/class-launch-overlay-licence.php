<?php
/**
 * Licence – validates key against gorrie.us API.
 * Unlocks Pro when valid Pro key is active.
 *
 * @package LaunchOverlay
 * @author  Gorrie Technology Group, Inc.
 */
defined( 'ABSPATH' ) || exit;

class Launch_Overlay_Licence {

	const API_URL      = 'https://gorrie.us/portal/license-api.php';
	const PLUGIN_SLUG  = 'launchoverlay';
	const OPT_KEY      = 'lo_licence_key';
	const OPT_CACHE    = 'lo_licence_cache';
	const CACHE_HOURS  = 24;
	const CRON_HOOK    = 'lo_licence_recheck';

	public function __construct() {
		add_action( self::CRON_HOOK, [ $this, 'run_recheck' ] );
	}

	// ── Is Pro? ───────────────────────────────────────────────────────────────

	public static function is_pro(): bool {
		if ( defined( 'LAUNCH_OVERLAY_PRO' ) && LAUNCH_OVERLAY_PRO === true ) {
			return true;
		}
		$cache = get_option( self::OPT_CACHE, [] );
		if ( ! empty( $cache['expires'] ) && $cache['expires'] > time() ) {
			return ! empty( $cache['is_pro'] );
		}
		// Expired cache — schedule recheck, use last known value
		self::schedule_recheck();
		return ! empty( $cache['is_pro'] );
	}

	// ── Activate ──────────────────────────────────────────────────────────────

	public static function activate( string $key ): array {
		$key = strtoupper( trim( $key ) );
		if ( ! $key ) return [ 'success' => false, 'message' => 'Please enter a licence key.' ];

		$result = self::api_call( 'activate', $key );
		if ( ! empty( $result['success'] ) ) {
			$lic    = $result['license'] ?? [];
			$is_pro = isset( $lic['plan'] ) && $lic['plan'] !== 'lite';
			update_option( self::OPT_KEY, $key );
			self::write_cache( $is_pro, $lic['plan'] ?? 'lite', $lic );
		}
		return $result;
	}

	// ── Deactivate ────────────────────────────────────────────────────────────

	public static function deactivate(): array {
		$key = self::get_key();
		if ( $key ) {
			self::api_call( 'deactivate', $key );
		}
		delete_option( self::OPT_KEY );
		delete_option( self::OPT_CACHE );
		wp_clear_scheduled_hook( self::CRON_HOOK );
		return [ 'success' => true, 'message' => 'Licence deactivated.' ];
	}

	// ── Background recheck ────────────────────────────────────────────────────

	public static function schedule_recheck(): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_single_event( time() + 300, self::CRON_HOOK );
		}
	}

	public function run_recheck(): void {
		$key = self::get_key();
		if ( ! $key ) return;
		$result = self::api_call( 'check', $key );
		$lic    = $result['license'] ?? [];
		$is_pro = ! empty( $result['success'] ) && isset( $lic['plan'] ) && $lic['plan'] !== 'lite';
		self::write_cache( $is_pro, $lic['plan'] ?? 'lite', $lic );
	}

	// ── Helpers ───────────────────────────────────────────────────────────────

	private static function api_call( string $action, string $key ): array {
		$response = wp_remote_post( self::API_URL, [
			'timeout' => 15,
			'headers' => [ 'Content-Type' => 'application/json' ],
			'body'    => wp_json_encode( [
				'action'      => $action,
				'license_key' => $key,
				'domain'      => home_url(),
				'plugin_slug' => self::PLUGIN_SLUG,
			] ),
		] );

		if ( is_wp_error( $response ) ) {
			return [ 'success' => false, 'message' => $response->get_error_message() ];
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		return is_array( $body ) ? $body : [ 'success' => false, 'message' => 'Invalid API response.' ];
	}

	private static function write_cache( bool $is_pro, string $plan, array $data ): void {
		update_option( self::OPT_CACHE, [
			'is_pro'  => $is_pro,
			'plan'    => $plan,
			'expires' => time() + ( self::CACHE_HOURS * HOUR_IN_SECONDS ),
			'data'    => $data,
		] );
	}

	public static function get_key(): string   { return (string) get_option( self::OPT_KEY, '' ); }
	public static function get_plan(): string  { return (string) ( get_option( self::OPT_CACHE, [] )['plan'] ?? 'lite' ); }
	public static function get_cache(): array  { return (array)  get_option( self::OPT_CACHE, [] ); }
}
