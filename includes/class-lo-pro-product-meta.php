<?php
/**
 * LO_Pro_Product_Meta — adds Pro fields directly inside the LaunchOverlay product panel.
 *
 * Outputs fields directly into the panel (no JS template injection — WooCommerce
 * strips <script> tags from product data panels in some versions).
 *
 * @package LaunchOverlayPro
 */

defined( 'ABSPATH' ) || exit;

class LO_Pro_Product_Meta {

	const PFX = '_lo_pro_';

	private static $inst = null;

	public static function instance() {
		if ( null === self::$inst ) {
			self::$inst = new self();
		}
		return self::$inst;
	}

	private function __construct() {
		// Priority 20 so we render AFTER Lite's panel (priority default = 10)
		add_action( 'woocommerce_product_data_panels', array( $this, 'render' ), 20 );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	// ── Assets ───────────────────────────────────────────────────────────────

	public function enqueue( $hook ) {
		if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
			return;
		}
		if ( 'product' !== get_post_type() ) {
			return;
		}

		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script(
			'lo-pro-product',
			LO_PRO_URL . 'admin/js/lo-pro-product.js',
			array( 'jquery', 'wp-color-picker' ),
			LO_PRO_VERSION,
			true
		);
	}

	// ── Render ────────────────────────────────────────────────────────────────

	/**
	 * Output Pro fields directly inside the #lo_product_panel div.
	 * We use a JS snippet to move them inside the existing panel because
	 * woocommerce_product_data_panels renders all panels at the same level.
	 */
	public function render() {
		global $post;
		$pid    = $post->ID;
		$is_pro = LO_License::is_pro();

		$custom_text = get_post_meta( $pid, self::PFX . 'custom_text',  true );
		$bg_color    = get_post_meta( $pid, self::PFX . 'bg_color',     true );
		$text_color  = get_post_meta( $pid, self::PFX . 'text_color',   true );
		$opacity     = get_post_meta( $pid, self::PFX . 'opacity',      true );
		$style       = get_post_meta( $pid, self::PFX . 'style',        true );
		$launch_date = get_post_meta( $pid, self::PFX . 'launch_date',  true );
		$auto_launch = get_post_meta( $pid, self::PFX . 'auto_launch',  true );
		$opacity     = ( '' !== $opacity ) ? (float) $opacity : 1.0;

		// Render a hidden div; JS will move its content into the Lite panel
		?>
		<div id="lo-pro-extra" style="display:none;">

			<?php if ( ! $is_pro ) : ?>
			<!-- Pro locked notice -->
			<div class="lo-pro-gate-panel">
				<span class="lo-gate-icon">🔒</span>
				<div class="lo-pro-gate-panel-text">
					<strong><?php esc_html_e( 'Pro License Required', 'launchoverlay-pro' ); ?></strong>
					<p><?php esc_html_e( 'Activate your Pro license to unlock custom text, per-product colors, ribbon style, and launch scheduling.', 'launchoverlay-pro' ); ?></p>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=launchoverlay-pro&tab=license' ) ); ?>"
					   class="lo-upsell-btn">
						<?php esc_html_e( 'Activate License', 'launchoverlay-pro' ); ?> &rarr;
					</a>
				</div>
			</div>

			<?php else : ?>
			<!-- Pro settings header -->
			<div class="lo-pro-section-header">
				<span class="lo-pro-section-icon">⭐</span>
				<span><?php esc_html_e( 'Pro Settings', 'launchoverlay-pro' ); ?></span>
			</div>

			<div class="options_group lo-options-group">

				<p class="form-field lo-field">
					<label class="lo-label" for="lo_pro_custom_text">
						<?php esc_html_e( 'Custom Overlay Text', 'launchoverlay-pro' ); ?>
					</label>
					<input type="text"
						   id="lo_pro_custom_text"
						   name="lo_pro_custom_text"
						   value="<?php echo esc_attr( $custom_text ); ?>"
						   class="lo-text-input"
						   placeholder="<?php esc_attr_e( 'Leave blank to use preset label', 'launchoverlay-pro' ); ?>" />
					<span class="lo-field-desc"><?php esc_html_e( 'Replaces Coming Soon / Pre-Order / Sold Out with your own wording.', 'launchoverlay-pro' ); ?></span>
				</p>

				<p class="form-field lo-field">
					<label class="lo-label" for="lo_pro_bg_color">
						<?php esc_html_e( 'Overlay Background', 'launchoverlay-pro' ); ?>
					</label>
					<input type="text"
						   id="lo_pro_bg_color"
						   name="lo_pro_bg_color"
						   value="<?php echo esc_attr( $bg_color ); ?>"
						   class="lo-pro-color" />
					<span class="lo-field-desc"><?php esc_html_e( 'Overrides global colour for this product only.', 'launchoverlay-pro' ); ?></span>
				</p>

				<p class="form-field lo-field">
					<label class="lo-label" for="lo_pro_text_color">
						<?php esc_html_e( 'Overlay Text Color', 'launchoverlay-pro' ); ?>
					</label>
					<input type="text"
						   id="lo_pro_text_color"
						   name="lo_pro_text_color"
						   value="<?php echo esc_attr( $text_color ); ?>"
						   class="lo-pro-color" />
				</p>

				<p class="form-field lo-field lo-field--range">
					<label class="lo-label" for="lo_pro_opacity">
						<?php esc_html_e( 'Opacity', 'launchoverlay-pro' ); ?>
					</label>
					<span class="lo-range-wrap">
						<input type="range"
							   id="lo_pro_opacity"
							   name="lo_pro_opacity"
							   min="0.1" max="1" step="0.05"
							   value="<?php echo esc_attr( number_format( $opacity, 2 ) ); ?>"
							   class="lo-range" />
						<output id="lo_pro_opacity_out" class="lo-range-out">
							<?php echo esc_html( number_format( $opacity, 2 ) ); ?>
						</output>
					</span>
				</p>

				<p class="form-field lo-field">
					<label class="lo-label" for="lo_pro_style">
						<?php esc_html_e( 'Style Override', 'launchoverlay-pro' ); ?>
					</label>
					<select id="lo_pro_style" name="lo_pro_style" class="lo-select">
						<option value=""><?php esc_html_e( '— Use Global Style —', 'launchoverlay-pro' ); ?></option>
						<option value="banner" <?php selected( $style, 'banner' ); ?>><?php esc_html_e( 'Banner', 'launchoverlay-pro' ); ?></option>
						<option value="badge"  <?php selected( $style, 'badge'  ); ?>><?php esc_html_e( 'Badge / Pill', 'launchoverlay-pro' ); ?></option>
						<option value="ribbon" <?php selected( $style, 'ribbon' ); ?>><?php esc_html_e( 'Diagonal Ribbon', 'launchoverlay-pro' ); ?></option>
					</select>
					<span class="lo-field-desc"><?php esc_html_e( 'Ribbon drapes diagonally across the product image corner.', 'launchoverlay-pro' ); ?></span>
				</p>

			</div>

			<!-- Scheduling header -->
			<div class="lo-pro-section-header">
				<span class="lo-pro-section-icon">📅</span>
				<span><?php esc_html_e( 'Launch Scheduling', 'launchoverlay-pro' ); ?></span>
			</div>

			<div class="options_group lo-options-group">

				<p class="form-field lo-field">
					<label class="lo-label" for="lo_pro_launch_date">
						<?php esc_html_e( 'Launch Date & Time', 'launchoverlay-pro' ); ?>
					</label>
					<input type="datetime-local"
						   id="lo_pro_launch_date"
						   name="lo_pro_launch_date"
						   value="<?php echo esc_attr( $launch_date ); ?>"
						   class="lo-text-input" />
					<span class="lo-field-desc">
						<?php esc_html_e( 'Timezone:', 'launchoverlay-pro' ); ?>
						<strong><?php echo esc_html( wp_timezone_string() ); ?></strong>
					</span>
				</p>

				<p class="form-field lo-field">
					<label class="lo-label"><?php esc_html_e( 'Auto-Remove Overlay', 'launchoverlay-pro' ); ?></label>
					<label class="lo-toggle" for="lo_pro_auto_launch">
						<input type="checkbox"
							   id="lo_pro_auto_launch"
							   name="lo_pro_auto_launch"
							   value="yes"
							   <?php checked( $auto_launch, 'yes' ); ?> />
						<span class="lo-toggle-track"><span class="lo-toggle-thumb"></span></span>
						<span class="lo-toggle-desc"><?php esc_html_e( 'Automatically disable overlay when launch date arrives.', 'launchoverlay-pro' ); ?></span>
					</label>
				</p>

			</div>
			<?php endif; ?>

		</div><!-- #lo-pro-extra -->

		<script>
		/* Move Pro extra fields into the Lite LaunchOverlay panel */
		jQuery( function( $ ) {
			var $panel = $( '#lo_product_panel' );
			var $extra = $( '#lo-pro-extra' );
			if ( ! $panel.length || ! $extra.length ) return;

			// Remove any previous injection (page cached)
			$panel.find( '.lo-pro-gate-panel, .lo-pro-section-header, #lo-pro-extra' ).remove();

			// Append content of #lo-pro-extra into the panel before the footer
			var $footer = $panel.find( '.lo-panel-footer' );
			var $content = $extra.children();

			if ( $footer.length ) {
				$footer.before( $content );
			} else {
				$panel.append( $content );
			}

			$extra.remove();

			// Init color pickers on the newly inserted fields
			if ( $.fn.wpColorPicker ) {
				$panel.find( '.lo-pro-color' ).each( function() {
					if ( ! $( this ).closest( '.wp-picker-container' ).length ) {
						$( this ).wpColorPicker( {
							defaultColor: '',
							palettes: [ '#3b5bdb','#f76707','#343a40','#2f9e44','#c92a2a','#ffffff','#000000' ],
						} );
					}
				} );
			}

			// Range slider live update
			$panel.on( 'input', '#lo_pro_opacity', function() {
				$( '#lo_pro_opacity_out' ).text( parseFloat( this.value ).toFixed( 2 ) );
			} );
		} );
		</script>
		<?php
	}

	// ── Save ─────────────────────────────────────────────────────────────────

	public function save( $pid ) {
		if ( ! LO_License::is_pro() ) {
			return;
		}

		// Verify this is a product save (not another post type)
		if ( 'product' !== get_post_type( $pid ) ) {
			return;
		}

		$text_fields = array(
			'lo_pro_custom_text' => self::PFX . 'custom_text',
			'lo_pro_bg_color'    => self::PFX . 'bg_color',
			'lo_pro_text_color'  => self::PFX . 'text_color',
			'lo_pro_style'       => self::PFX . 'style',
			'lo_pro_launch_date' => self::PFX . 'launch_date',
		);

		foreach ( $text_fields as $post_key => $meta_key ) {
			$val = isset( $_POST[ $post_key ] )
				? sanitize_text_field( wp_unslash( $_POST[ $post_key ] ) )
				: '';
			update_post_meta( $pid, $meta_key, $val );
		}

		// Opacity
		$opacity = isset( $_POST['lo_pro_opacity'] ) ? (float) $_POST['lo_pro_opacity'] : 1.0;
		$opacity = max( 0.1, min( 1.0, $opacity ) );
		update_post_meta( $pid, self::PFX . 'opacity', (string) round( $opacity, 2 ) );

		// Auto-launch checkbox
		$auto = isset( $_POST['lo_pro_auto_launch'] ) ? 'yes' : 'no';
		update_post_meta( $pid, self::PFX . 'auto_launch', $auto );
	}
}
