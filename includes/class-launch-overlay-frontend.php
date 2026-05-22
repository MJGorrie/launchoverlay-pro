<?php
/**
 * Frontend – all rendering, purchase control, cache bypass.
 *
 * @package LaunchOverlay
 * @author  Gorrie Technology Group, Inc.
 */

defined( 'ABSPATH' ) || exit;

class Launch_Overlay_Frontend {

	private array $settings;
	private array $cache = [];
	private array $lite_positions;

	public function __construct( array $settings ) {
		$this->settings       = $settings;
		$this->lite_positions = Launch_Overlay_Core::lite_positions();

		if ( 'yes' !== $settings['enabled'] ) return;

		add_action( 'wp_enqueue_scripts',                          [ $this, 'enqueue_assets' ] );
		add_filter( 'woocommerce_product_get_image',               [ $this, 'inject_overlay' ], 20, 5 );
		add_action( 'wp_footer',                                   [ $this, 'maybe_inject_single_overlay_js' ] );
		add_filter( 'woocommerce_get_price_html',                  [ $this, 'maybe_hide_price' ], 20, 2 );
		add_filter( 'woocommerce_variable_price_html',             [ $this, 'maybe_hide_price' ], 20, 2 );
		add_filter( 'woocommerce_variable_sale_price_html',        [ $this, 'maybe_hide_price' ], 20, 2 );
		add_filter( 'woocommerce_is_purchasable',                  [ $this, 'maybe_disable_purchasable' ], 20, 2 );
		add_filter( 'woocommerce_loop_add_to_cart_link',           [ $this, 'maybe_replace_loop_button' ], 20, 2 );
		add_action( 'woocommerce_single_product_summary',          [ $this, 'maybe_replace_single_button' ], 25 );
		add_filter( 'woocommerce_product_single_add_to_cart_text', [ $this, 'filter_add_to_cart_text' ], 20, 2 );

		// Cache bypass.
		add_action( 'template_redirect', [ $this, 'maybe_nocache' ] );
	}

	// ── Assets ────────────────────────────────────────────────────────────────

	public function enqueue_assets(): void {
		if ( ! is_shop() && ! is_product_category() && ! is_product_tag() && ! is_product() && ! is_front_page() ) return;

		wp_enqueue_style( 'launch-overlay', LAUNCH_OVERLAY_URL . 'assets/css/overlay.css', [], LAUNCH_OVERLAY_VERSION );

		$themes  = Launch_Overlay_Core::preset_themes();
		$is_pro  = Launch_Overlay_Core::is_pro();

		// Global colour resolution.
		if ( $is_pro && '' !== trim( $this->settings['custom_bg_color'] ) ) {
			$bg    = $this->settings['custom_bg_color'];
			$color = $this->settings['custom_text_color'];
		} else {
			$theme = $themes[ $this->settings['preset_theme'] ] ?? $themes['dark'];
			$bg    = $theme['bg'];
			$color = $theme['color'];
		}

		$opacity  = round( (int) $this->settings['opacity'] / 100, 2 );
		$rotation = $is_pro ? (int) $this->settings['rotation'] : 0;

		wp_add_inline_style( 'launch-overlay', sprintf(
			':root{--lo-bg:%s;--lo-color:%s;--lo-opacity:%s;--lo-rotation:%sdeg;}',
			esc_attr( $bg ), esc_attr( $color ), $opacity, $rotation
		) );

		if ( is_product() ) {
			wp_enqueue_script( 'launch-overlay-single', LAUNCH_OVERLAY_URL . 'assets/js/overlay-single.js', [], LAUNCH_OVERLAY_VERSION, true );
		}
	}

	// ── Overlay injection (shop/category pages) ───────────────────────────────

	public function inject_overlay( string $image, WC_Product $product, $size, $attr, bool $placeholder ): string {
		$eff = $this->get_effective( $product->get_id() );
		if ( ! $eff['enabled'] ) return $image;

		$position = $this->resolve_position( $eff['position'] );
		$classes  = 'lo-overlay lo-pos-' . esc_attr( $position );
		if ( ! empty( $eff['ribbon_style'] ) ) {
			$classes .= ' lo-ribbon';
		}

		// Per-product colour override (Pro).
		$inline_style = '';
		if ( ! empty( $eff['custom_bg_color'] ) ) {
			$inline_style = sprintf(
				' style="background:%s;--lo-bg:%s;--lo-color:%s;"',
				esc_attr( $eff['custom_bg_color'] ),
				esc_attr( $eff['custom_bg_color'] ),
				esc_attr( $eff['custom_text_color'] ?? '#ffffff' )
			);
		}

		return '<span class="lo-wrap">' . $image . sprintf(
			'<span class="%s" aria-hidden="true"%s><span class="lo-label">%s</span></span>',
			$classes,
			$inline_style,
			esc_html( $eff['banner_text'] )
		) . '</span>';
	}

	// ── Single product JS overlay ─────────────────────────────────────────────

	public function maybe_inject_single_overlay_js(): void {
		if ( ! is_product() ) return;
		global $product;
		if ( ! $product instanceof WC_Product ) return;

		$eff = $this->get_effective( $product->get_id() );
		if ( ! $eff['enabled'] ) return;

		$classes = 'lo-overlay lo-pos-' . $this->resolve_position( $eff['position'] );
		if ( ! empty( $eff['ribbon_style'] ) ) $classes .= ' lo-ribbon';

		$config = [
			'text'            => $eff['banner_text'],
			'position'        => $this->resolve_position( $eff['position'] ),
			'classes'         => $classes,
			'customBg'        => $eff['custom_bg_color'] ?? '',
			'customTextColor' => $eff['custom_text_color'] ?? '',
		];

		wp_add_inline_script( 'launch-overlay-single', 'window.loSingleConfig = ' . wp_json_encode( $config ) . ';', 'before' );
	}

	// ── Price ─────────────────────────────────────────────────────────────────

	public function maybe_hide_price( string $price_html, WC_Product $product ): string {
		$eff = $this->get_effective( $product->get_id() );
		return ( $eff['enabled'] && $eff['disable_price'] ) ? '' : $price_html;
	}

	// ── Purchasability ────────────────────────────────────────────────────────

	public function maybe_disable_purchasable( bool $purchasable, WC_Product $product ): bool {
		$eff = $this->get_effective( $product->get_id() );
		return ( $eff['enabled'] && $eff['disable_add_to_cart'] ) ? false : $purchasable;
	}

	// ── Loop button ───────────────────────────────────────────────────────────

	public function maybe_replace_loop_button( string $link, WC_Product $product ): string {
		$eff = $this->get_effective( $product->get_id() );
		if ( ! $eff['enabled'] || ! $eff['disable_add_to_cart'] ) return $link;
		$text   = trim( $eff['replace_button_text'] );
		if ( '' === $text ) return '';
		$bg     = esc_attr( $eff['button_bg_color']   ?? '#1a1a1a' );
		$color  = esc_attr( $eff['button_text_color'] ?? '#ffffff' );
		$style  = "background:{$bg};color:{$color};border-color:{$bg};";
		return sprintf(
			'<span class="lo-cta button disabled" aria-disabled="true" style="%s">%s</span>',
			$style,
			esc_html( $text )
		);
	}

	// ── Single product button ─────────────────────────────────────────────────

	public function maybe_replace_single_button(): void {
		global $product;
		if ( ! $product instanceof WC_Product ) return;
		$eff = $this->get_effective( $product->get_id() );
		if ( ! $eff['enabled'] || ! $eff['disable_add_to_cart'] ) return;

		remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30 );
		remove_action( 'woocommerce_simple_add_to_cart',   'woocommerce_simple_add_to_cart',   30 );
		remove_action( 'woocommerce_grouped_add_to_cart',  'woocommerce_grouped_add_to_cart',  30 );
		remove_action( 'woocommerce_variable_add_to_cart', 'woocommerce_variable_add_to_cart', 30 );
		remove_action( 'woocommerce_external_add_to_cart', 'woocommerce_external_add_to_cart', 30 );

		$text  = trim( $eff['replace_button_text'] );
		if ( '' !== $text ) {
			$bg    = esc_attr( $eff['button_bg_color']   ?? '#1a1a1a' );
			$color = esc_attr( $eff['button_text_color'] ?? '#ffffff' );
			$style = "background:{$bg};color:{$color};border-color:{$bg};";
			echo '<div class="lo-single-cta"><span class="lo-cta button disabled" aria-disabled="true" style="' . $style . '">'
				. esc_html( $text ) . '</span></div>';
		}
	}

	public function filter_add_to_cart_text( string $text, WC_Product $product ): string {
		$eff = $this->get_effective( $product->get_id() );
		if ( $eff['enabled'] && $eff['disable_add_to_cart'] && '' !== trim( $eff['replace_button_text'] ) ) {
			return $eff['replace_button_text'];
		}
		return $text;
	}

	// ── Cache bypass ──────────────────────────────────────────────────────────

	public function maybe_nocache(): void {
		if ( ! is_product() ) return;
		$pid = get_queried_object_id();
		if ( 'yes' !== get_post_meta( $pid, '_launch_overlay_enabled', true ) ) return;

		nocache_headers();
		do_action( 'litespeed_control_set_nocache', 'LaunchOverlay active overlay' );
		if ( ! defined( 'DONOTCACHEPAGE' ) )   define( 'DONOTCACHEPAGE',   true );
		if ( ! defined( 'DONOTCACHEOBJECT' ) ) define( 'DONOTCACHEOBJECT', true );
	}

	// ── Helpers ───────────────────────────────────────────────────────────────

	private function resolve_position( string $pos ): string {
		if ( ! Launch_Overlay_Core::is_pro() && ! in_array( $pos, $this->lite_positions, true ) ) {
			return 'top-right';
		}
		return $pos;
	}

	private function get_effective( int $product_id ): array {
		if ( ! isset( $this->cache[ $product_id ] ) ) {
			$this->cache[ $product_id ] = Launch_Overlay_Product_Meta::get_effective_settings( $product_id, $this->settings );
		}
		return $this->cache[ $product_id ];
	}
}
