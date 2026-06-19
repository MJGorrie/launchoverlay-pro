<?php
/**
 * LO_Pro_Overlay — hooks into Lite's overlay renderer to apply Pro features.
 *
 * Called by LO_Overlay::render() via class_exists('LO_Pro_Overlay') checks.
 *
 * @package LaunchOverlayPro
 */

defined( 'ABSPATH' ) || exit;

class LO_Pro_Overlay {

	const PFX = '_lo_pro_';

	private static $inst = null;

	public static function instance() {
		if ( null === self::$inst ) self::$inst = new self();
		return self::$inst;
	}

	private function __construct() {
		// Enqueue Pro-specific front-end CSS (ribbon style, etc.)
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ), 20 );
	}

	public function enqueue() {
		if ( ! is_woocommerce() && ! is_shop() && ! is_product_category() && ! is_product() ) return;
		wp_enqueue_style(
			'lo-pro-public',
			LO_PRO_URL . 'public/css/lo-pro-public.css',
			array( 'launchoverlay' ),
			LO_PRO_VERSION
		);
	}

	// ── Hooks called by Lite renderer ─────────────────────────────────────────

	/**
	 * Get overlay label — custom text overrides preset if set.
	 *
	 * @param int    $pid     Product ID.
	 * @param string $type    Overlay type key.
	 * @param string $default Default label from Lite.
	 * @return string
	 */
	public static function get_label( $pid, $type, $default ) {
		$custom = get_post_meta( $pid, self::PFX . 'custom_text', true );
		return ! empty( $custom ) ? $custom : $default;
	}

	/**
	 * Get style — per-product override or fallback to global.
	 *
	 * @param int    $pid     Product ID.
	 * @param string $global  Global style from settings.
	 * @return string
	 */
	public static function get_style( $pid, $global ) {
		$override = get_post_meta( $pid, self::PFX . 'style', true );
		return ! empty( $override ) ? $override : $global;
	}

	/**
	 * Get inline style string for per-product colours / opacity.
	 *
	 * @param int $pid Product ID.
	 * @return string
	 */
	public static function get_inline_style( $pid ) {
		$bg      = get_post_meta( $pid, self::PFX . 'bg_color',   true );
		$txt     = get_post_meta( $pid, self::PFX . 'text_color', true );
		$opacity = (float) get_post_meta( $pid, self::PFX . 'opacity', true );

		$styles = array();

		if ( ! empty( $bg ) ) {
			if ( $opacity > 0 && $opacity < 1 ) {
				$rgb = self::hex_to_rgb( $bg );
				if ( $rgb ) {
					$styles[] = sprintf( 'background-color:rgba(%s,%.2f)', $rgb, $opacity );
				} else {
					$styles[] = 'background-color:' . $bg;
				}
			} else {
				$styles[] = 'background-color:' . $bg;
			}
		}

		if ( ! empty( $txt ) ) {
			$styles[] = 'color:' . $txt;
		}

		return implode( ';', $styles );
	}

	// ── Utility ───────────────────────────────────────────────────────────────

	/** @return string|false "R,G,B" or false */
	private static function hex_to_rgb( $hex ) {
		$hex = ltrim( $hex, '#' );
		if ( 6 !== strlen( $hex ) ) return false;
		return implode( ',', array_map( 'hexdec', str_split( $hex, 2 ) ) );
	}
}
