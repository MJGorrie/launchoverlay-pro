<?php
/**
 * Per-product meta box – all Free and Pro fields.
 *
 * Free fields:  enabled, preset_text, override_purchase,
 *               disable_price, disable_add_to_cart, replace_button_text
 *
 * Pro fields:   custom_text, custom_bg_color, custom_text_color,
 *               schedule_enabled, schedule_start, schedule_end,
 *               ribbon_style
 *
 * @package LaunchOverlay
 * @author  Gorrie Technology Group, Inc.
 */

defined( 'ABSPATH' ) || exit;

class Launch_Overlay_Product_Meta {

	const META_PREFIX = '_launch_overlay_';

	public function __construct() {
		add_action( 'woocommerce_product_options_general_product_data', [ $this, 'render_meta_box_fields' ] );
		add_action( 'woocommerce_process_product_meta',                 [ $this, 'save_meta_box_fields' ] );
		add_action( 'woocommerce_rest_insert_product_object',           [ $this, 'save_from_rest' ], 10, 3 );
		add_action( 'admin_enqueue_scripts',                            [ $this, 'enqueue_color_picker' ] );
	}

	public function enqueue_color_picker( string $hook ): void {
		if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
			return;
		}
		if ( Launch_Overlay_Core::is_pro() ) {
			wp_enqueue_style( 'wp-color-picker' );
			wp_enqueue_script( 'wp-color-picker' );
		}
	}

	// ── Render ────────────────────────────────────────────────────────────────

	public function render_meta_box_fields(): void {
		global $post;
		$pid    = $post->ID;
		$is_pro = Launch_Overlay_Core::is_pro();
		$p      = self::META_PREFIX;

		$enabled  = get_post_meta( $pid, $p . 'enabled',          true );
		$text_key = get_post_meta( $pid, $p . 'preset_text',      true );
		$override = get_post_meta( $pid, $p . 'override_purchase', true );

		// Pro values.
		$custom_text     = get_post_meta( $pid, $p . 'custom_text',      true );
		$custom_bg       = get_post_meta( $pid, $p . 'custom_bg_color',  true ) ?: '#1a1a1a';
		$custom_color    = get_post_meta( $pid, $p . 'custom_text_color', true ) ?: '#ffffff';
		$ribbon_style    = get_post_meta( $pid, $p . 'ribbon_style',     true );
		$sched_enabled   = get_post_meta( $pid, $p . 'schedule_enabled', true );
		$sched_start     = get_post_meta( $pid, $p . 'schedule_start',   true );
		$sched_end       = get_post_meta( $pid, $p . 'schedule_end',     true );

		echo '<div class="options_group launch-overlay-product-options">';
		echo '<h4 style="padding-left:12px;margin:12px 0 6px;font-size:13px;">'
			. esc_html__( '🚀 LaunchOverlay Banner', 'launch-overlay' )
			. ( $is_pro ? ' <span style="background:#f0b429;color:#1a1a1a;font-size:10px;font-weight:700;padding:1px 5px;border-radius:3px;vertical-align:middle;">PRO</span>' : '' )
		. '</h4>';

		// ── Enable ────────────────────────────────────────────────────────────
		woocommerce_wp_checkbox( [
			'id'          => $p . 'enabled',
			'label'       => __( 'Enable Overlay Banner', 'launch-overlay' ),
			'description' => __( 'Show a launch overlay banner on this product\'s image.', 'launch-overlay' ),
			'value'       => $enabled,
		] );

		// ── Preset text ───────────────────────────────────────────────────────
		$text_options = [ '' => __( '— Use global default —', 'launch-overlay' ) ];
		foreach ( Launch_Overlay_Core::preset_texts() as $key => $label ) {
			$text_options[ $key ] = $label;
		}
		woocommerce_wp_select( [
			'id'          => $p . 'preset_text',
			'label'       => __( 'Banner Text', 'launch-overlay' ),
			'description' => __( 'Override the global default banner text for this product.', 'launch-overlay' ),
			'options'     => $text_options,
			'value'       => $text_key,
		] );

		// ── Pro: Custom text ──────────────────────────────────────────────────
		if ( $is_pro ) {
			woocommerce_wp_text_input( [
				'id'          => $p . 'custom_text',
				'label'       => __( 'Custom Banner Text (Pro)', 'launch-overlay' ),
				'description' => __( 'Type any text. Overrides the preset above when set.', 'launch-overlay' ),
				'placeholder' => __( 'e.g. Available March 2025', 'launch-overlay' ),
				'value'       => $custom_text,
			] );
		}

		// ── Pro: Colours ──────────────────────────────────────────────────────
		if ( $is_pro ) {
			woocommerce_wp_text_input( [
				'id'                => $p . 'custom_bg_color',
				'label'             => __( 'Banner Background Colour (Pro)', 'launch-overlay' ),
				'description'       => __( 'Custom background colour for this product\'s banner.', 'launch-overlay' ),
				'value'             => $custom_bg,
				'class'             => 'lo-color-picker',
				'custom_attributes' => [ 'data-default-color' => '#1a1a1a' ],
			] );
			woocommerce_wp_text_input( [
				'id'                => $p . 'custom_text_color',
				'label'             => __( 'Banner Text Colour (Pro)', 'launch-overlay' ),
				'description'       => __( 'Custom text colour for this product\'s banner.', 'launch-overlay' ),
				'value'             => $custom_color,
				'class'             => 'lo-color-picker',
				'custom_attributes' => [ 'data-default-color' => '#ffffff' ],
			] );
		}

		// ── Ribbon style (FREE) ──────────────────────────────────────────────
		woocommerce_wp_checkbox( [
			'id'          => $p . 'ribbon_style',
			'label'       => __( 'Diagonal Ribbon Style', 'launch-overlay' ),
			'description' => __( 'Display as a diagonal corner ribbon instead of a flat banner.', 'launch-overlay' ),
			'value'       => $ribbon_style,
		] );

		// ── Override purchase controls ────────────────────────────────────────
		woocommerce_wp_checkbox( [
			'id'          => $p . 'override_purchase',
			'label'       => __( 'Override Purchase Controls', 'launch-overlay' ),
			'description' => __( 'Use per-product purchase settings instead of the global defaults.', 'launch-overlay' ),
			'value'       => $override,
		] );

		woocommerce_wp_checkbox( [
			'id'    => $p . 'disable_price',
			'label' => __( 'Disable Price Display', 'launch-overlay' ),
			'value' => get_post_meta( $pid, $p . 'disable_price', true ),
		] );

		woocommerce_wp_checkbox( [
			'id'    => $p . 'disable_add_to_cart',
			'label' => __( 'Disable Add to Cart', 'launch-overlay' ),
			'value' => get_post_meta( $pid, $p . 'disable_add_to_cart', true ),
		] );

		woocommerce_wp_text_input( [
			'id'          => $p . 'replace_button_text',
			'label'       => __( 'Button Replacement Text', 'launch-overlay' ),
			'placeholder' => __( 'Coming Soon', 'launch-overlay' ),
			'value'       => get_post_meta( $pid, $p . 'replace_button_text', true ),
		] );

		// ── Button colours ────────────────────────────────────────────────────
		$btn_bg  = get_post_meta( $pid, $p . 'button_bg_color',   true ) ?: '#1a1a1a';
		$btn_txt = get_post_meta( $pid, $p . 'button_text_color', true ) ?: '#ffffff';
		woocommerce_wp_text_input( [
			'id'                => $p . 'button_bg_color',
			'label'             => __( 'Button Background Colour', 'launch-overlay' ),
			'description'       => __( 'Background colour of the replacement button.', 'launch-overlay' ),
			'value'             => $btn_bg,
			'class'             => 'lo-color-picker',
			'custom_attributes' => [ 'data-default-color' => '#1a1a1a' ],
		] );
		woocommerce_wp_text_input( [
			'id'                => $p . 'button_text_color',
			'label'             => __( 'Button Text Colour', 'launch-overlay' ),
			'description'       => __( 'Text colour of the replacement button.', 'launch-overlay' ),
			'value'             => $btn_txt,
			'class'             => 'lo-color-picker',
			'custom_attributes' => [ 'data-default-color' => '#ffffff' ],
		] );

		// ── Pro: Scheduling ───────────────────────────────────────────────────
		if ( $is_pro ) {
			echo '<div class="lo-schedule-wrap" style="border-top:1px solid #eee;margin-top:8px;padding-top:8px;">';
			echo '<p style="padding-left:12px;font-weight:600;font-size:12px;color:#555;margin:6px 0;">'
				. esc_html__( '⏰ Scheduling (Pro)', 'launch-overlay' ) . '</p>';

			woocommerce_wp_checkbox( [
				'id'          => $p . 'schedule_enabled',
				'label'       => __( 'Enable Scheduling', 'launch-overlay' ),
				'description' => __( 'Automatically control overlay visibility by date.', 'launch-overlay' ),
				'value'       => $sched_enabled,
			] );

			woocommerce_wp_text_input( [
				'id'                => $p . 'schedule_start',
				'label'             => __( 'Overlay Start Date', 'launch-overlay' ),
				'description'       => __( 'Overlay activates from this date. Leave blank to start immediately.', 'launch-overlay' ),
				'value'             => $sched_start,
				'type'              => 'date',
				'custom_attributes' => [],
			] );

			woocommerce_wp_text_input( [
				'id'          => $p . 'schedule_end',
				'label'       => __( 'Go Live Date', 'launch-overlay' ),
				'description' => __( 'Overlay auto-removes and product goes live on this date.', 'launch-overlay' ),
				'value'       => $sched_end,
				'type'        => 'date',
			] );

			echo '</div>';
		}

		echo '</div>'; // .launch-overlay-product-options

		// ── Admin JS ──────────────────────────────────────────────────────────
		?>
		<script>
		( function( $ ) {
			var $wrap    = $( '.launch-overlay-product-options' );
			var pfx      = '<?php echo esc_js( $p ); ?>';
			var $enable  = $wrap.find( '#' + pfx + 'enabled' );
			var $over    = $wrap.find( '#' + pfx + 'override_purchase' );
			var $sched   = $wrap.find( '#' + pfx + 'schedule_enabled' );

			var overrideFields = [
				pfx + 'disable_price',
				pfx + 'disable_add_to_cart',
				pfx + 'replace_button_text',
			];

			var schedFields = [
				pfx + 'schedule_start',
				pfx + 'schedule_end',
			];

			function toggle() {
				var isEnabled = $enable.is( ':checked' );
				var isOverride = $over.is( ':checked' );
				var isSched = $sched.length && $sched.is( ':checked' );

				// Show/hide all fields when overlay is enabled.
				$wrap.find( '#' + pfx + 'preset_text, #' + pfx + 'ribbon_style, #' + pfx + 'override_purchase' )
					.closest( 'p.form-field' ).toggle( isEnabled );

				$wrap.find( '.lo-schedule-wrap' ).toggle( isEnabled );

				// Override purchase fields.
				$.each( overrideFields, function( _, id ) {
					$wrap.find( '#' + id ).closest( 'p.form-field' ).toggle( isEnabled && isOverride );
				} );

				// Schedule date fields.
				$.each( schedFields, function( _, id ) {
					$wrap.find( '#' + id ).closest( 'p.form-field' ).toggle( isEnabled && isSched );
				} );
			}

			$enable.on( 'change', toggle );
			$over.on( 'change', toggle );
			if ( $sched.length ) $sched.on( 'change', toggle );
			toggle();

			<?php if ( $is_pro ) : ?>
			// Init WP colour pickers.
			$( '.lo-color-picker' ).wpColorPicker();
			<?php endif; ?>
		} )( jQuery );
		</script>
		<?php
	}

	// ── Save ──────────────────────────────────────────────────────────────────

	public function save_meta_box_fields( int $product_id ): void {
		$this->save_fields( $product_id, $_POST ); // phpcs:ignore WordPress.Security.NonceVerification
	}

	public function save_from_rest( WC_Product $product, WP_REST_Request $request, bool $creating ): void {
		$meta = $request->get_param( 'meta_data' );
		if ( ! is_array( $meta ) ) return;
		$data = [];
		foreach ( $meta as $item ) {
			if ( str_starts_with( $item['key'], self::META_PREFIX ) ) {
				$data[ $item['key'] ] = $item['value'];
			}
		}
		$this->save_fields( $product->get_id(), $data );
	}

	private function save_fields( int $product_id, array $data ): void {
		$p = self::META_PREFIX;

		// Checkboxes.
		$checkboxes = [ 'enabled', 'override_purchase', 'disable_price', 'disable_add_to_cart', 'ribbon_style', 'schedule_enabled' ];
		foreach ( $checkboxes as $key ) {
			update_post_meta( $product_id, $p . $key, isset( $data[ $p . $key ] ) ? 'yes' : 'no' );
		}

		// Preset text (allow-list).
		$valid = array_keys( Launch_Overlay_Core::preset_texts() );
		$tk    = $data[ $p . 'preset_text' ] ?? '';
		update_post_meta( $product_id, $p . 'preset_text', in_array( $tk, $valid, true ) ? $tk : '' );

		// Text inputs.
		$text_fields = [ 'replace_button_text', 'custom_text' ];
		foreach ( $text_fields as $key ) {
			update_post_meta( $product_id, $p . $key, sanitize_text_field( $data[ $p . $key ] ?? '' ) );
		}

		// Hex colours (Pro).
		$hex_fields = [ 'custom_bg_color', 'custom_text_color', 'button_bg_color', 'button_text_color' ];
		foreach ( $hex_fields as $key ) {
			$hex = sanitize_hex_color( $data[ $p . $key ] ?? '' );
			update_post_meta( $product_id, $p . $key, $hex ?: '' );
		}

		// Dates (Pro).
		$date_fields = [ 'schedule_start', 'schedule_end' ];
		foreach ( $date_fields as $key ) {
			$date = sanitize_text_field( $data[ $p . $key ] ?? '' );
			// Validate date format YYYY-MM-DD.
			if ( $date && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
				$date = '';
			}
			update_post_meta( $product_id, $p . $key, $date );
		}
	}

	// ── Effective settings ────────────────────────────────────────────────────

	/**
	 * Resolve final effective settings for a product.
	 * Priority: per-product → bulk rule → global defaults.
	 *
	 * @param int   $product_id
	 * @param array $global_settings
	 * @return array
	 */
	public static function get_effective_settings( int $product_id, array $global_settings ): array {
		$p      = self::META_PREFIX;
		$is_pro = Launch_Overlay_Core::is_pro();

		// ── 1. Check scheduling (Pro) ─────────────────────────────────────────
		if ( $is_pro && class_exists( 'Launch_Overlay_Scheduler' ) ) {
			$scheduled_state = Launch_Overlay_Scheduler::get_scheduled_state( $product_id );
			if ( $scheduled_state === false ) {
				// Schedule says: overlay OFF (product is live).
				return [ 'enabled' => false ];
			}
		}

		// ── 2. Check per-product enabled flag ─────────────────────────────────
		$enabled = get_post_meta( $product_id, $p . 'enabled', true );

		// ── 3. Check bulk rules (Pro) — only if per-product not explicitly set ─
		$bulk_rule = null;
		if ( $is_pro && class_exists( 'Launch_Overlay_Bulk_Rules' ) ) {
			$bulk_rule = Launch_Overlay_Bulk_Rules::get_matching_rule( $product_id );
		}

		// If per-product is not enabled AND no bulk rule matches → skip.
		if ( 'yes' !== $enabled && null === $bulk_rule ) {
			return [ 'enabled' => false ];
		}

		// ── 4. Resolve banner text ────────────────────────────────────────────
		$preset_texts = Launch_Overlay_Core::preset_texts();
		$banner_text  = '';

		// Per-product custom text (Pro, highest priority).
		if ( $is_pro ) {
			$custom = trim( get_post_meta( $product_id, $p . 'custom_text', true ) );
			if ( '' !== $custom ) {
				$banner_text = $custom;
			}
		}

		// Per-product preset text.
		if ( '' === $banner_text ) {
			$pk = get_post_meta( $product_id, $p . 'preset_text', true );
			if ( $pk && isset( $preset_texts[ $pk ] ) ) {
				$banner_text = $preset_texts[ $pk ];
			}
		}

		// Bulk rule text.
		if ( '' === $banner_text && $bulk_rule ) {
			$bk = $bulk_rule['preset_text'] ?? 'coming_soon';
			$banner_text = $preset_texts[ $bk ] ?? $preset_texts['coming_soon'];
		}

		// Global default.
		if ( '' === $banner_text ) {
			$gk = $global_settings['preset_text'] ?? 'coming_soon';
			$banner_text = $preset_texts[ $gk ] ?? $preset_texts['coming_soon'];
		}

		// ── 5. Resolve colours (Pro per-product → global) ─────────────────────
		$bg_color   = null;
		$text_color = null;
		if ( $is_pro ) {
			$pbg = sanitize_hex_color( get_post_meta( $product_id, $p . 'custom_bg_color',   true ) );
			$ptx = sanitize_hex_color( get_post_meta( $product_id, $p . 'custom_text_color', true ) );
			if ( $pbg ) $bg_color   = $pbg;
			if ( $ptx ) $text_color = $ptx;
		}

		// ── 6. Resolve purchase controls ──────────────────────────────────────
		$override = 'yes' === get_post_meta( $product_id, $p . 'override_purchase', true );

		if ( $override ) {
			$disable_price       = 'yes' === get_post_meta( $product_id, $p . 'disable_price',       true );
			$disable_add_to_cart = 'yes' === get_post_meta( $product_id, $p . 'disable_add_to_cart', true );
			$replace_btn         = get_post_meta( $product_id, $p . 'replace_button_text', true );
			if ( '' === $replace_btn ) $replace_btn = $global_settings['replace_button_text'];
		} elseif ( $bulk_rule ) {
			$disable_price       = 'yes' === ( $bulk_rule['disable_price'] ?? 'no' );
			$disable_add_to_cart = 'yes' === ( $bulk_rule['disable_add_to_cart'] ?? 'yes' );
			$replace_btn         = $bulk_rule['replace_button_text'] ?? $global_settings['replace_button_text'];
		} else {
			$disable_price       = 'yes' === $global_settings['disable_price'];
			$disable_add_to_cart = 'yes' === $global_settings['disable_add_to_cart'];
			$replace_btn         = $global_settings['replace_button_text'];
		}

		// ── 7. Ribbon style (Free) ──────────────────────────────────────────────
		$ribbon = 'yes' === get_post_meta( $product_id, $p . 'ribbon_style', true );

		return [
			'enabled'             => true,
			'banner_text'         => $banner_text,
			'theme'               => $bulk_rule['preset_theme'] ?? $global_settings['preset_theme'],
			'opacity'             => (int) $global_settings['opacity'],
			'position'            => $global_settings['position'],
			'custom_bg_color'     => $bg_color,
			'custom_text_color'   => $text_color,
			'ribbon_style'        => $ribbon,
			'disable_price'       => $disable_price,
			'disable_add_to_cart' => $disable_add_to_cart,
			'replace_button_text' => $replace_btn,
			'button_bg_color'     => get_post_meta( $product_id, $p . 'button_bg_color',   true ) ?: '#1a1a1a',
			'button_text_color'   => get_post_meta( $product_id, $p . 'button_text_color', true ) ?: '#ffffff',
		];
	}
}
