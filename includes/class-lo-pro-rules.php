<?php
/**
 * LO_Pro_Rules — category & tag bulk overlay rules.
 *
 * Called by LO_Cart_Control and LO_Overlay in Lite via class_exists check.
 *
 * @package LaunchOverlayPro
 */

defined( 'ABSPATH' ) || exit;

class LO_Pro_Rules {

	const OPT_RULES = 'lo_pro_bulk_rules';

	private static $inst  = null;
	private static $cache = array(); // runtime match cache

	public static function instance() {
		if ( null === self::$inst ) self::$inst = new self();
		return self::$inst;
	}

	private function __construct() {}

	// ── Public API ────────────────────────────────────────────────────────────

	/**
	 * Match a product against bulk rules.
	 * Per-product settings in Lite take priority — this is the fallback.
	 *
	 * @param  int $pid Product ID.
	 * @return array|false  Overlay data array, or false.
	 */
	public static function match( $pid ) {
		if ( ! LO_License::is_pro() ) return false;

		if ( array_key_exists( $pid, self::$cache ) ) return self::$cache[ $pid ];

		$rules = self::get_all();
		if ( empty( $rules ) ) {
			self::$cache[ $pid ] = false;
			return false;
		}

		foreach ( $rules as $rule ) {
			if ( empty( $rule['enabled'] ) || 'yes' !== $rule['enabled'] ) continue;

			$matched = false;
			if ( 'category' === $rule['rule_type'] && ! empty( $rule['term_id'] ) ) {
				$matched = has_term( (int) $rule['term_id'], 'product_cat', $pid );
			} elseif ( 'tag' === $rule['rule_type'] && ! empty( $rule['term_id'] ) ) {
				$matched = has_term( (int) $rule['term_id'], 'product_tag', $pid );
			}

			if ( $matched ) {
				$data = array(
					'type'             => sanitize_key( $rule['overlay_type'] ),
					'hide_add_to_cart' => ( 'yes' === ( $rule['hide_add_to_cart'] ?? 'no' ) ),
					'hide_price'       => ( 'yes' === ( $rule['hide_price']       ?? 'no' ) ),
					'custom_message'   => sanitize_text_field( $rule['custom_message'] ?? '' ),
					'from_bulk'        => true,
				);
				self::$cache[ $pid ] = $data;
				return $data;
			}
		}

		self::$cache[ $pid ] = false;
		return false;
	}

	/** @return array All saved rules */
	public static function get_all() {
		$rules = get_option( self::OPT_RULES, array() );
		return is_array( $rules ) ? $rules : array();
	}

	/** @param array $raw Raw POST rules array */
	public static function save( array $raw ) {
		if ( ! LO_License::is_pro() ) return;

		$clean = array();
		foreach ( $raw as $r ) {
			if ( empty( $r['overlay_type'] ) ) continue;
			$clean[] = array(
				'enabled'          => isset( $r['enabled'] ) ? 'yes' : 'no',
				'rule_type'        => in_array( $r['rule_type'] ?? '', array( 'category', 'tag' ), true ) ? $r['rule_type'] : 'category',
				'term_id'          => absint( $r['term_id'] ?? 0 ),
				'overlay_type'     => sanitize_key( $r['overlay_type'] ),
				'hide_add_to_cart' => isset( $r['hide_add_to_cart'] ) ? 'yes' : 'no',
				'hide_price'       => isset( $r['hide_price'] )       ? 'yes' : 'no',
				'custom_message'   => sanitize_text_field( $r['custom_message'] ?? '' ),
			);
		}
		update_option( self::OPT_RULES, $clean );
		self::$cache = array(); // clear runtime cache
	}

	/** Clear runtime match cache (useful after saving rules) */
	public static function clear_cache() {
		self::$cache = array();
	}
}
