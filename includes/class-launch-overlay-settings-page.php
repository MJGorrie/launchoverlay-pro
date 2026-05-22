<?php
/**
 * WooCommerce Settings API – LaunchOverlay Tab.
 * Lite and Pro fields in one class; Pro fields are gated.
 *
 * @package LaunchOverlay
 * @author  Gorrie Technology Group, Inc.
 */

defined( 'ABSPATH' ) || exit;

class Launch_Overlay_Settings_Page extends WC_Settings_Page {

	public function __construct() {
		$this->id    = 'launch_overlay';
		add_action( 'woocommerce_admin_field_launch_overlay_licence_render', [ $this, 'render_licence_field' ] );
		$this->label = __( 'LaunchOverlay', 'launch-overlay' );
		parent::__construct();
		add_action( 'admin_head', [ $this, 'inline_admin_css' ] );
	}

	/**
	 * Inline admin styles for Pro badge and notices.
	 * Only loads on WooCommerce settings screens.
	 */
	public function inline_admin_css(): void {
		$screen = get_current_screen();
		if ( ! $screen || false === strpos( $screen->id, 'wc-settings' ) ) {
			return;
		}
		echo '<style>
			.lo-pro-badge{
				display:inline-block;background:#f0b429;color:#1a1a1a;
				font-size:10px;font-weight:700;padding:2px 6px;
				border-radius:3px;vertical-align:middle;margin-left:6px;
				text-transform:uppercase;letter-spacing:.03em;
			}
			.lo-lite-badge{
				display:inline-block;background:#ccc;color:#444;
				font-size:10px;font-weight:700;padding:2px 6px;
				border-radius:3px;vertical-align:middle;margin-left:6px;
				text-transform:uppercase;
			}
			.lo-pro-notice{
				background:#fff8e5;border-left:4px solid #f0b429;
				padding:8px 12px;margin:6px 0 0;font-size:13px;
				border-radius:0 3px 3px 0;
			}
			.lo-pro-fields-disabled input,
			.lo-pro-fields-disabled select,
			.lo-pro-fields-disabled textarea{
				opacity:.45;pointer-events:none;
			}
		</style>';
	}

	/**
	 * Return all settings fields.
	 * IMPORTANT: Every section that starts with type=title MUST end with type=sectionend.
	 */
	public function get_settings( $current_section = '' ): array {
		$is_pro = Launch_Overlay_Core::is_pro();

		$preset_text_options = [];
		foreach ( Launch_Overlay_Core::preset_texts() as $k => $v ) {
			$preset_text_options[ $k ] = $v;
		}

		$preset_theme_options = [];
		foreach ( Launch_Overlay_Core::preset_themes() as $k => $t ) {
			$preset_theme_options[ $k ] = $t['label'];
		}

		$position_options = Launch_Overlay_Core::positions();

		$settings = [

			// ── Licence ─────────────────────────────────────────────────────────
			[
				'title' => __( 'Licence & Pro Activation', 'launch-overlay' ),
				'type'  => 'title',
				'desc'  => '',
				'id'    => 'launch_overlay_section_licence',
			],
			[
				'type' => 'launch_overlay_licence_render',
				'id'   => 'launch_overlay_licence_field',
			],
			[
				'type' => 'sectionend',
				'id'   => 'launch_overlay_section_licence_end',
			],

			// ═══════════════════════════════════════════════════════════════════
			// SECTION 1 – General
			// ═══════════════════════════════════════════════════════════════════
			[
				'title' => __( 'LaunchOverlay Settings', 'launch-overlay' ),
				'type'  => 'title',
				// Use desc for the tier badge — title is esc_html()'d by WooCommerce.
				'desc'  => $is_pro
					? '<span class="lo-pro-badge">Pro</span>'
					: '<span class="lo-lite-badge">Lite</span><br><small style="color:#666">' . esc_html__( 'Configure default overlay behaviour. Products can override individually.', 'launch-overlay' ) . '</small>',
				'id'    => 'launch_overlay_section_general',
			],
			[
				'title'   => __( 'Enable Plugin', 'launch-overlay' ),
				'desc'    => __( 'Globally enable LaunchOverlay on this store.', 'launch-overlay' ),
				'id'      => 'launch_overlay_settings[enabled]',
				'type'    => 'checkbox',
				'default' => 'yes',
			],
			[
				'type' => 'sectionend',
				'id'   => 'launch_overlay_section_general_end',
			],

			// ═══════════════════════════════════════════════════════════════════
			// SECTION 2 – Overlay Appearance
			// ═══════════════════════════════════════════════════════════════════
			[
				'title' => __( 'Overlay Appearance', 'launch-overlay' ),
				'type'  => 'title',
				'desc'  => __( 'Choose the default look of the banner overlay.', 'launch-overlay' ),
				'id'    => 'launch_overlay_section_appearance',
			],
			[
				'title'   => __( 'Default Banner Text', 'launch-overlay' ),
				'desc'    => __( 'Preset text shown on the overlay banner.', 'launch-overlay' ),
				'id'      => 'launch_overlay_settings[preset_text]',
				'type'    => 'select',
				'options' => $preset_text_options,
				'default' => 'coming_soon',
				'css'     => 'min-width:200px;',
			],
			[
				'title'   => __( 'Colour Theme', 'launch-overlay' ),
				'desc'    => __( 'Preset colour theme for the banner.', 'launch-overlay' )
							. ( $is_pro ? '' : ' ' . __( 'Custom colours available in Pro.', 'launch-overlay' ) ),
				'id'      => 'launch_overlay_settings[preset_theme]',
				'type'    => 'select',
				'options' => $preset_theme_options,
				'default' => 'dark',
				'css'     => 'min-width:200px;',
			],
			[
				'title'             => __( 'Opacity (%)', 'launch-overlay' ),
				'desc'              => __( 'Banner background opacity: 0 (transparent) – 100 (solid).', 'launch-overlay' ),
				'id'                => 'launch_overlay_settings[opacity]',
				'type'              => 'number',
				'default'           => 85,
				'custom_attributes' => [ 'min' => 0, 'max' => 100, 'step' => 5 ],
				'css'               => 'width:80px;',
			],
			[
				'type' => 'sectionend',
				'id'   => 'launch_overlay_section_appearance_end',
			],

			// ═══════════════════════════════════════════════════════════════════
			// SECTION 3 – Placement
			// ═══════════════════════════════════════════════════════════════════
			[
				'title' => __( 'Placement', 'launch-overlay' ),
				'type'  => 'title',
				'desc'  => $is_pro
					? __( 'Choose where the banner appears on the product image.', 'launch-overlay' )
					: __( 'Default placement is Top Right. Upgrade to Pro for full placement control.', 'launch-overlay' ),
				'id'    => 'launch_overlay_section_placement',
			],
			[
				'title'             => __( 'Banner Position', 'launch-overlay' ),
				'id'                => 'launch_overlay_settings[position]',
				'type'              => 'select',
				'options'           => $position_options,
				'default'           => 'top-right',
				'css'               => 'min-width:220px;',
				'desc'              => $is_pro
					? ''
					: __( 'Top Right is the only position available in Lite.', 'launch-overlay' ),
				'custom_attributes' => $is_pro ? [] : [ 'disabled' => 'disabled' ],
			],
			[
				'type' => 'sectionend',
				'id'   => 'launch_overlay_section_placement_end',
			],

			// ═══════════════════════════════════════════════════════════════════
			// SECTION 4 – Purchase Control
			// ═══════════════════════════════════════════════════════════════════
			[
				'title' => __( 'Purchase Control', 'launch-overlay' ),
				'type'  => 'title',
				'desc'  => __( 'Default behaviour for products with an active overlay.', 'launch-overlay' ),
				'id'    => 'launch_overlay_section_purchase',
			],
			[
				'title'   => __( 'Disable Price Display', 'launch-overlay' ),
				'desc'    => __( 'Hide the product price on shop/product pages.', 'launch-overlay' ),
				'id'      => 'launch_overlay_settings[disable_price]',
				'type'    => 'checkbox',
				'default' => 'no',
			],
			[
				'title'   => __( 'Disable Add to Cart', 'launch-overlay' ),
				'desc'    => __( 'Remove the Add to Cart button for overlaid products.', 'launch-overlay' ),
				'id'      => 'launch_overlay_settings[disable_add_to_cart]',
				'type'    => 'checkbox',
				'default' => 'yes',
			],
			[
				'title'   => __( 'Replacement Button Text', 'launch-overlay' ),
				'desc'    => __( 'Text shown instead of Add to Cart. Leave blank to hide entirely.', 'launch-overlay' ),
				'id'      => 'launch_overlay_settings[replace_button_text]',
				'type'    => 'text',
				'default' => __( 'Coming Soon', 'launch-overlay' ),
				'css'     => 'min-width:220px;',
			],
			[
				'type' => 'sectionend',
				'id'   => 'launch_overlay_section_purchase_end',
			],

			// ═══════════════════════════════════════════════════════════════════
			// SECTION 5 – Pro: Custom Text & Colours
			// ═══════════════════════════════════════════════════════════════════
			[
				'title' => __( 'Pro: Custom Text & Colours', 'launch-overlay' ),
				'type'  => 'title',
				// Badge goes in desc, NOT title — WooCommerce esc_html()'s the title field.
				'desc'  => $is_pro
					? __( 'Override preset text and colours with custom values.', 'launch-overlay' )
					: '<span class="lo-pro-badge">Pro</span> <span class="lo-pro-notice">' .
					  esc_html__( 'Upgrade to LaunchOverlay Pro to unlock custom text, full colour picker, rotation, and scheduling.', 'launch-overlay' ) .
					  '</span>',
				'id'    => 'launch_overlay_section_pro',
			],
			[
				'title'             => __( 'Custom Banner Text', 'launch-overlay' ),
				'desc'              => __( 'Overrides preset text when set. Leave blank to use preset.', 'launch-overlay' ),
				'id'                => 'launch_overlay_settings[custom_text]',
				'type'              => 'text',
				'default'           => '',
				'css'               => 'min-width:220px;',
				'custom_attributes' => $is_pro ? [] : [ 'disabled' => 'disabled', 'placeholder' => 'Available in Pro' ],
			],
			[
				'title'             => __( 'Custom Background Colour', 'launch-overlay' ),
				'desc'              => __( 'Hex colour for the banner background.', 'launch-overlay' ),
				'id'                => 'launch_overlay_settings[custom_bg_color]',
				'type'              => $is_pro ? 'color' : 'text',
				'default'           => '#1a1a1a',
				'custom_attributes' => $is_pro ? [] : [ 'disabled' => 'disabled', 'placeholder' => 'Available in Pro' ],
			],
			[
				'title'             => __( 'Custom Text Colour', 'launch-overlay' ),
				'desc'              => __( 'Hex colour for the banner text.', 'launch-overlay' ),
				'id'                => 'launch_overlay_settings[custom_text_color]',
				'type'              => $is_pro ? 'color' : 'text',
				'default'           => '#ffffff',
				'custom_attributes' => $is_pro ? [] : [ 'disabled' => 'disabled', 'placeholder' => 'Available in Pro' ],
			],
			[
				'title'             => __( 'Rotation (degrees)', 'launch-overlay' ),
				'desc'              => __( 'Rotate the banner label. E.g. -45 for diagonal.', 'launch-overlay' ),
				'id'                => 'launch_overlay_settings[rotation]',
				'type'              => 'number',
				'default'           => 0,
				'custom_attributes' => array_merge(
					[ 'min' => -90, 'max' => 90, 'step' => 5 ],
					$is_pro ? [] : [ 'disabled' => 'disabled' ]
				),
				'css'               => 'width:80px;',
			],
			[
				'type' => 'sectionend',
				'id'   => 'launch_overlay_section_pro_end',
			],

			// ═══════════════════════════════════════════════════════════════════
			// SECTION 6 – Pro: Scheduling
			// ═══════════════════════════════════════════════════════════════════
			[
				'title' => __( 'Pro: Scheduling', 'launch-overlay' ),
				'type'  => 'title',
				'desc'  => $is_pro
					? __( 'Automatically activate and deactivate overlays by date.', 'launch-overlay' )
					: '<span class="lo-pro-badge">Pro</span> <span class="lo-pro-notice">' .
					  esc_html__( 'Set start/end dates to auto-switch products from Coming Soon to Live.', 'launch-overlay' ) .
					  '</span>',
				'id'    => 'launch_overlay_section_scheduling',
			],
			[
				'title'             => __( 'Enable Scheduling', 'launch-overlay' ),
				'desc'              => __( 'Use start/end dates to control overlay visibility.', 'launch-overlay' ),
				'id'                => 'launch_overlay_settings[schedule_enabled]',
				'type'              => 'checkbox',
				'default'           => 'no',
				'custom_attributes' => $is_pro ? [] : [ 'disabled' => 'disabled' ],
			],
			[
				'title'             => __( 'Start Date', 'launch-overlay' ),
				'desc'              => __( 'Overlay activates on this date.', 'launch-overlay' ),
				'id'                => 'launch_overlay_settings[schedule_start]',
				'type'              => 'date',
				'default'           => '',
				'custom_attributes' => $is_pro ? [] : [ 'disabled' => 'disabled' ],
			],
			[
				'title'             => __( 'End Date', 'launch-overlay' ),
				'desc'              => __( 'Overlay auto-deactivates and product goes live after this date.', 'launch-overlay' ),
				'id'                => 'launch_overlay_settings[schedule_end]',
				'type'              => 'date',
				'default'           => '',
				'custom_attributes' => $is_pro ? [] : [ 'disabled' => 'disabled' ],
			],
			[
				'type' => 'sectionend',
				'id'   => 'launch_overlay_section_scheduling_end',
			],

		];

		return apply_filters( 'launch_overlay_settings', $settings );
	}

	/**
	 * Save – enforce Lite position constraint before handing off to WooCommerce.
	 */
	public function render_licence_field(): void {
		if ( class_exists( 'Launch_Overlay_Licence_Settings' ) ) {
			echo '<table class="form-table">';
			Launch_Overlay_Licence_Settings::render();
			echo '</table>';
		}
	}

	public function save() {
		if ( ! Launch_Overlay_Core::is_pro() ) {
			// phpcs:ignore WordPress.Security.NonceVerification
			$_POST['launch_overlay_settings']['position'] = 'top-right';
		}
		parent::save();
		do_action( 'launch_overlay_settings_saved' );
	}
}
