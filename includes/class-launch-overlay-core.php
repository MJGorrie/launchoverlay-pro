<?php
/**
 * Core singleton – boots sub-systems and owns the settings schema.
 * One codebase; Pro features gated behind LAUNCH_OVERLAY_PRO constant.
 *
 * @package LaunchOverlay
 * @author  Gorrie Technology Group, Inc.
 */

defined( 'ABSPATH' ) || exit;

final class Launch_Overlay_Core {

	/** @var Launch_Overlay_Core|null */
	private static ?Launch_Overlay_Core $instance = null;

	/** @var array Merged plugin settings (global). */
	private array $settings = [];

	private function __construct() {
		$this->settings = wp_parse_args(
			(array) get_option( 'launch_overlay_settings', [] ),
			self::default_settings()
		);

		new Launch_Overlay_Admin();
		new Launch_Overlay_Licence_Settings();
		new Launch_Overlay_Product_Meta();
		new Launch_Overlay_Frontend( $this->settings );

		// Pro: Scheduler and Bulk Rules.
		if ( class_exists( 'Launch_Overlay_Scheduler' ) ) {
			new Launch_Overlay_Scheduler();
		}
		if ( class_exists( 'Launch_Overlay_Bulk_Rules' ) ) {
			new Launch_Overlay_Bulk_Rules();
		}
	}

	public static function instance(): Launch_Overlay_Core {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	// ── Tier helpers ───────────────────────────────────────────────────────────

	public static function is_pro(): bool {
		// Check via Licence class (API-validated key) or manual constant.
		if ( class_exists( 'Launch_Overlay_Licence' ) ) {
			return Launch_Overlay_Licence::is_pro();
		}
		return defined( 'LAUNCH_OVERLAY_PRO' ) && true === LAUNCH_OVERLAY_PRO;
	}

	public static function pro_badge(): string {
		return ' <span class="lo-pro-badge" title="' .
			esc_attr__( 'Available in LaunchOverlay Pro', 'launch-overlay' ) .
			'">⭐ Pro</span>';
	}

	// ── Settings schema ────────────────────────────────────────────────────────

	public static function default_settings(): array {
		return [
			'enabled'             => 'yes',
			'preset_text'         => 'coming_soon',
			'preset_theme'        => 'dark',
			'disable_price'       => 'no',
			'disable_add_to_cart' => 'yes',
			'replace_button_text' => __( 'Coming Soon', 'launch-overlay' ),
			// Lite default: top-right (per GTG spec). Pro unlocks all positions.
			'position'            => 'top-right',
			'opacity'             => 85,
			// Pro-only (ignored in Lite)
			'custom_text'         => '',
			'custom_bg_color'     => '#1a1a1a',
			'custom_text_color'   => '#ffffff',
			'rotation'            => 0,
			'schedule_enabled'    => 'no',
			'schedule_start'      => '',
			'schedule_end'        => '',
		];
	}

	public static function preset_texts(): array {
		return [
			'coming_soon' => __( 'Coming Soon',    'launch-overlay' ),
			'pre_order'   => __( 'Pre-Order',      'launch-overlay' ),
			'launching'   => __( 'Launching Soon', 'launch-overlay' ),
			'sold_out'    => __( 'Sold Out',        'launch-overlay' ),
			'available'   => __( 'Available Soon',  'launch-overlay' ),
		];
	}

	public static function preset_themes(): array {
		return [
			'dark'    => [ 'bg' => '#1a1a1a', 'color' => '#ffffff', 'label' => __( 'Dark',         'launch-overlay' ) ],
			'light'   => [ 'bg' => '#ffffff', 'color' => '#1a1a1a', 'label' => __( 'Light',        'launch-overlay' ) ],
			'primary' => [ 'bg' => '#2271b1', 'color' => '#ffffff', 'label' => __( 'Primary Blue', 'launch-overlay' ) ],
			'success' => [ 'bg' => '#00a32a', 'color' => '#ffffff', 'label' => __( 'Green',        'launch-overlay' ) ],
			'warning' => [ 'bg' => '#dba617', 'color' => '#ffffff', 'label' => __( 'Amber',        'launch-overlay' ) ],
			'danger'  => [ 'bg' => '#d63638', 'color' => '#ffffff', 'label' => __( 'Red',          'launch-overlay' ) ],
		];
	}

	/**
	 * All positions. Pro unlocks everything beyond top-right.
	 */
	public static function positions(): array {
		$pro = self::is_pro();
		return [
			'top-right'    => __( 'Top Right (Default)', 'launch-overlay' ),
			'top-left'     => __( 'Top Left',            'launch-overlay' ) . ( $pro ? '' : self::pro_badge() ),
			'bottom-right' => __( 'Bottom Right',        'launch-overlay' ) . ( $pro ? '' : self::pro_badge() ),
			'bottom-left'  => __( 'Bottom Left',         'launch-overlay' ) . ( $pro ? '' : self::pro_badge() ),
			'center'       => __( 'Centre Band',         'launch-overlay' ) . ( $pro ? '' : self::pro_badge() ),
			'top'          => __( 'Top Band',            'launch-overlay' ) . ( $pro ? '' : self::pro_badge() ),
			'bottom'       => __( 'Bottom Band',         'launch-overlay' ) . ( $pro ? '' : self::pro_badge() ),
		];
	}

	public static function lite_positions(): array {
		return [ 'top-right' ];
	}

	public function get_settings(): array {
		return $this->settings;
	}
}
