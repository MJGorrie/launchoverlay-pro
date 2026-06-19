<?php
/**
 * LO_Pro_Admin — Pro plugin settings page.
 * Tabs: License | Bulk Rules | Upcoming Launches
 *
 * @package LaunchOverlayPro
 */

defined( 'ABSPATH' ) || exit;

class LO_Pro_Admin {

	private static $inst = null;

	public static function instance() {
		if ( null === self::$inst ) self::$inst = new self();
		return self::$inst;
	}

	private function __construct() {
		add_action( 'admin_menu',            array( $this, 'register_menu' ) );
		add_action( 'admin_init',            array( $this, 'handle_save' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_notices',         array( 'LO_License', 'maybe_show_notice' ) );
	}

	// ── Menu ─────────────────────────────────────────────────────────────────

	public function register_menu() {
		add_submenu_page(
			'woocommerce',
			__( 'LaunchOverlay Pro', 'launchoverlay-pro' ),
			__( 'LO Pro', 'launchoverlay-pro' ),
			'manage_woocommerce',
			'launchoverlay-pro',
			array( $this, 'page' )
		);
	}

	// ── Assets ───────────────────────────────────────────────────────────────

	public function enqueue( $hook ) {
		if ( 'woocommerce_page_launchoverlay-pro' !== $hook ) return;

		wp_enqueue_style( 'wp-color-picker' );
		// Re-use Lite's admin CSS (loaded via Lite plugin) + Pro additions
		wp_enqueue_style(
			'lo-pro-admin',
			LO_PRO_URL . 'admin/css/lo-pro-admin.css',
			array( 'lo-admin' ),
			LO_PRO_VERSION
		);
		wp_enqueue_script(
			'lo-pro-admin',
			LO_PRO_URL . 'admin/js/lo-pro-admin.js',
			array( 'jquery', 'wp-color-picker' ),
			LO_PRO_VERSION,
			true
		);

		// Pass data to JS for bulk rules builder
		$cats = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) );
		$tags = get_terms( array( 'taxonomy' => 'product_tag', 'hide_empty' => false ) );

		wp_localize_script( 'lo-pro-admin', 'loProData', array(
			'categories'   => is_wp_error( $cats ) ? array() : array_map( function( $t ) {
				return array( 'id' => $t->term_id, 'name' => $t->name );
			}, $cats ),
			'tags'         => is_wp_error( $tags ) ? array() : array_map( function( $t ) {
				return array( 'id' => $t->term_id, 'name' => $t->name );
			}, $tags ),
			'overlayTypes' => defined( 'LAUNCHOVERLAY_VERSION' ) ? LO_Settings::overlay_types() : array(
				'coming_soon' => 'Coming Soon',
				'pre_order'   => 'Pre-Order',
				'sold_out'    => 'Sold Out',
			),
			'strings' => array(
				'removeRule'    => __( 'Remove', 'launchoverlay-pro' ),
				'selectTerm'    => __( '— Select —', 'launchoverlay-pro' ),
				'confirmRemove' => __( 'Remove this rule?', 'launchoverlay-pro' ),
			),
		) );
	}

	// ── Save ─────────────────────────────────────────────────────────────────

	public function handle_save() {
		if ( empty( $_POST['lo_pro_nonce'] ) ) return;
		if ( ! wp_verify_nonce( sanitize_key( $_POST['lo_pro_nonce'] ), 'lo_pro_save' ) ) return;
		if ( ! current_user_can( 'manage_woocommerce' ) ) return;

		if ( isset( $_POST['lo_save_rules'] ) ) {
			$raw = isset( $_POST['lo_rules'] ) && is_array( $_POST['lo_rules'] ) ? $_POST['lo_rules'] : array();
			LO_Pro_Rules::save( $raw );
			add_settings_error( 'lo_pro', 'saved', __( 'Bulk rules saved.', 'launchoverlay-pro' ), 'success' );
		}
	}

	// ── Page ─────────────────────────────────────────────────────────────────

	public function page() {
		$tab    = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'license';
		$is_pro = LO_License::is_pro();
		$base   = admin_url( 'admin.php?page=launchoverlay-pro' );

		settings_errors( 'lo_pro' );
		?>
		<div class="wrap lo-wrap lo-pro-wrap">

			<!-- ── Header ───────────────────────────────────────────── -->
			<div class="lo-header">
				<div class="lo-header-brand">
					<div class="lo-header-icon">
						<svg width="32" height="32" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
							<rect width="32" height="32" rx="8" fill="#f76707"/>
							<path d="M8 22 L16 8 L24 22 Z" fill="white" opacity="0.9"/>
							<rect x="13" y="17" width="6" height="2" rx="1" fill="white" opacity="0.6"/>
							<circle cx="25" cy="7" r="4" fill="#ffd43b"/>
							<text x="22.5" y="10" font-size="6" font-weight="800" fill="#e25e00" font-family="sans-serif">P</text>
						</svg>
					</div>
					<div>
						<h1 class="lo-header-title">LaunchOverlay <span class="lo-pro-title-badge">Pro</span></h1>
						<p class="lo-header-sub"><?php esc_html_e( 'Advanced product launch controls for WooCommerce.', 'launchoverlay-pro' ); ?></p>
					</div>
				</div>
				<div class="lo-header-right">
					<?php if ( $is_pro ) : ?>
						<span class="lo-badge-pro"><?php esc_html_e( 'License Active', 'launchoverlay-pro' ); ?></span>
					<?php else : ?>
						<span class="lo-badge-inactive"><?php esc_html_e( 'License Inactive', 'launchoverlay-pro' ); ?></span>
					<?php endif; ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=launchoverlay' ) ); ?>" class="lo-btn-secondary">
						← <?php esc_html_e( 'Free Settings', 'launchoverlay-pro' ); ?>
					</a>
				</div>
			</div>

			<!-- ── Tabs ─────────────────────────────────────────────── -->
			<nav class="lo-tabs">
				<a href="<?php echo esc_url( $base . '&tab=license' ); ?>"
				   class="lo-tab <?php echo 'license' === $tab ? 'is-active' : ''; ?>">
					<span class="lo-tab-icon">🔑</span>
					<?php esc_html_e( 'License', 'launchoverlay-pro' ); ?>
				</a>
				<a href="<?php echo esc_url( $base . '&tab=rules' ); ?>"
				   class="lo-tab <?php echo 'rules' === $tab ? 'is-active' : ''; ?> <?php echo ! $is_pro ? 'lo-tab-locked' : ''; ?>">
					<span class="lo-tab-icon">📦</span>
					<?php esc_html_e( 'Bulk Rules', 'launchoverlay-pro' ); ?>
					<?php if ( ! $is_pro ) echo '<span class="lo-tab-lock-icon">🔒</span>'; ?>
				</a>
				<a href="<?php echo esc_url( $base . '&tab=schedule' ); ?>"
				   class="lo-tab <?php echo 'schedule' === $tab ? 'is-active' : ''; ?> <?php echo ! $is_pro ? 'lo-tab-locked' : ''; ?>">
					<span class="lo-tab-icon">📅</span>
					<?php esc_html_e( 'Upcoming Launches', 'launchoverlay-pro' ); ?>
					<?php if ( ! $is_pro ) echo '<span class="lo-tab-lock-icon">🔒</span>'; ?>
				</a>
			</nav>

			<div class="lo-tab-content">
				<?php
				if ( 'license'  === $tab ) $this->tab_license();
				elseif ( 'rules' === $tab ) {
					if ( $is_pro ) $this->tab_rules();
					else           $this->tab_locked( __( 'Bulk Rules', 'launchoverlay-pro' ) );
				} elseif ( 'schedule' === $tab ) {
					if ( $is_pro ) $this->tab_schedule();
					else           $this->tab_locked( __( 'Upcoming Launches', 'launchoverlay-pro' ) );
				}
				?>
			</div>

		</div><!-- .lo-pro-wrap -->
		<?php
	}

	// ── License Tab ──────────────────────────────────────────────────────────

	private function tab_license() {
		$is_pro = LO_License::is_pro();
		$status = LO_License::get_status();
		$key_d  = LO_License::get_key_display();
		$data   = LO_License::get_data();

		$badge_map = array(
			'valid'    => 'lo-license-badge--valid',
			'invalid'  => 'lo-license-badge--invalid',
			'expired'  => 'lo-license-badge--expired',
			'inactive' => 'lo-license-badge--inactive',
			'empty'    => 'lo-license-badge--empty',
		);
		$is_dev   = defined( 'LO_PRO_DEV_LICENSE' ) && LO_PRO_DEV_LICENSE;
		$badge_cls = $badge_map[ $status ] ?? 'lo-license-badge--empty';
		?>
		<?php if ( $is_dev ) : ?>
		<div class="lo-dev-notice">
			<strong>🛠️ <?php esc_html_e( 'Developer Mode Active', 'launchoverlay-pro' ); ?></strong>
			<?php esc_html_e( 'All Pro features are unlocked via the dev bypass constant. To use a real license key, remove', 'launchoverlay-pro' ); ?>
			<code>define( 'LO_PRO_DEV_LICENSE', true );</code>
			<?php esc_html_e( 'from wp-config.php and enter your key below.', 'launchoverlay-pro' ); ?>
		</div>
		<?php endif; ?>

		<div class="lo-grid lo-grid--license">

			<!-- Status card -->
			<div class="lo-card">
				<div class="lo-card-head">
					<span class="lo-card-icon">🔑</span>
					<h2><?php esc_html_e( 'License Status', 'launchoverlay-pro' ); ?></h2>
				</div>
				<div class="lo-card-body">

					<!-- WHERE TO GET YOUR LICENSE KEY -->
					<?php if ( ! $is_dev && ! $is_pro ) : ?>
					<div style="background:#f8f9ff;border:1px solid #c5d0f5;border-radius:8px;padding:14px 16px;margin-bottom:16px;">
						<strong style="display:block;font-size:13px;margin-bottom:6px;">📧 <?php esc_html_e( 'Where is my license key?', 'launchoverlay-pro' ); ?></strong>
						<p style="font-size:12.5px;color:#495057;margin:0 0 8px;">
							<?php esc_html_e( 'Your license key is in the purchase confirmation email sent to you after buying LaunchOverlay Pro. It looks like:', 'launchoverlay-pro' ); ?>
						</p>
						<code style="display:block;background:#fff;border:1px solid #dee2e6;padding:6px 10px;border-radius:4px;font-size:12px;letter-spacing:.08em;margin-bottom:8px;">XXXX-XXXX-XXXX-XXXX-XXXX</code>
						<p style="font-size:12px;color:#6c757d;margin:0;">
							<?php esc_html_e( "Don't have a key yet?", 'launchoverlay-pro' ); ?>
							<a href="https://launchoverlay.com/pro/" target="_blank" rel="noopener" style="color:#3b5bdb;font-weight:600;">
								<?php esc_html_e( 'Purchase LaunchOverlay Pro →', 'launchoverlay-pro' ); ?>
							</a>
							&nbsp;|&nbsp;
							<?php esc_html_e( 'Testing locally?', 'launchoverlay-pro' ); ?>
							<a href="https://launchoverlay.com/docs/dev-mode/" target="_blank" rel="noopener" style="color:#3b5bdb;font-weight:600;">
								<?php esc_html_e( 'Use dev mode →', 'launchoverlay-pro' ); ?>
							</a>
						</p>
					</div>
					<?php endif; ?>

					<div class="lo-license-status-row">
						<span class="lo-license-badge <?php echo esc_attr( $badge_cls ); ?>">
							<?php echo esc_html( LO_License::status_label() ); ?>
						</span>
						<?php if ( $key_d ) : ?>
							<code class="lo-license-key-display"><?php echo esc_html( $key_d ); ?></code>
						<?php endif; ?>
					</div>

					<?php if ( $is_pro && ! empty( $data ) ) : ?>
						<table class="lo-license-info-table">
							<?php
							$rows = array(
								__( 'Licensed To', 'launchoverlay-pro' ) => $data['customer_name']  ?? '',
								__( 'Email',       'launchoverlay-pro' ) => $data['customer_email'] ?? '',
								__( 'Plan',        'launchoverlay-pro' ) => ucfirst( $data['plan'] ?? 'Pro' ),
								__( 'Expires',     'launchoverlay-pro' ) => $data['expires']        ?? '',
								__( 'Last Verified','launchoverlay-pro') => $data['validated_at']   ?? '',
							);
							foreach ( $rows as $label => $val ) :
								if ( empty( $val ) ) continue;
							?>
								<tr>
									<th><?php echo esc_html( $label ); ?></th>
									<td><?php echo esc_html( $val ); ?></td>
								</tr>
							<?php endforeach; ?>
						</table>
					<?php endif; ?>

					<?php if ( $is_pro ) : ?>
						<!-- Deactivate -->
						<form method="post" action="" style="margin-top:18px;">
							<?php wp_nonce_field( 'lo_pro_license', 'lo_pro_license_nonce' ); ?>
							<input type="hidden" name="lo_license_action" value="deactivate" />
							<button type="submit" class="lo-btn-deactivate"
								onclick="return confirm('<?php esc_attr_e( 'Deactivate license on this site?', 'launchoverlay-pro' ); ?>')">
								<?php esc_html_e( 'Deactivate License', 'launchoverlay-pro' ); ?>
							</button>
						</form>
					<?php else : ?>
						<!-- Activate -->
						<form method="post" action="" class="lo-activate-form">
							<?php wp_nonce_field( 'lo_pro_license', 'lo_pro_license_nonce' ); ?>
							<input type="hidden" name="lo_license_action" value="activate" />
							<div class="lo-license-input-wrap">
								<input type="text"
									   name="lo_license_key"
									   class="lo-license-key-input"
									   placeholder="<?php esc_attr_e( 'XXXX-XXXX-XXXX-XXXX', 'launchoverlay-pro' ); ?>"
									   autocomplete="off"
									   spellcheck="false"
									   required />
								<button type="submit" class="lo-btn-primary">
									<?php esc_html_e( 'Activate', 'launchoverlay-pro' ); ?>
								</button>
							</div>
							<p class="lo-field-desc" style="margin-top:8px;">
								<?php esc_html_e( 'Enter the license key from your purchase confirmation email.', 'launchoverlay-pro' ); ?>
							</p>
						</form>
					<?php endif; ?>
				</div>
			</div>

			<!-- Features card -->
			<div class="lo-card lo-card--pro-features">
				<div class="lo-card-head">
					<span class="lo-card-icon">⭐</span>
					<h2><?php esc_html_e( 'Pro Features', 'launchoverlay-pro' ); ?></h2>
				</div>
				<div class="lo-card-body">
					<ul class="lo-pro-feature-checklist">
						<?php
						$features = array(
							__( 'Custom overlay text per product',             'launchoverlay-pro' ),
							__( 'Per-product background color & text color',   'launchoverlay-pro' ),
							__( 'Per-product opacity control',                 'launchoverlay-pro' ),
							__( 'Diagonal ribbon corner style',                'launchoverlay-pro' ),
							__( 'Launch scheduling with auto-remove',          'launchoverlay-pro' ),
							__( 'Bulk overlay rules by category or tag',       'launchoverlay-pro' ),
							__( 'Upcoming launches dashboard',                 'launchoverlay-pro' ),
							__( 'Priority support & updates for 1 year',       'launchoverlay-pro' ),
						);
						foreach ( $features as $f ) :
							$icon = $is_pro ? '✅' : '⭕';
						?>
							<li class="lo-pro-feature-check <?php echo $is_pro ? 'is-active' : ''; ?>">
								<span class="lo-check-icon"><?php echo esc_html( $icon ); ?></span>
								<span><?php echo esc_html( $f ); ?></span>
							</li>
						<?php endforeach; ?>
					</ul>
					<?php if ( ! $is_pro ) : ?>
						<div style="margin-top:16px;">
							<a href="https://launchoverlay.com/pro/?utm_source=pro-license-tab&utm_medium=pro"
							   class="lo-btn-upgrade lo-btn-upgrade--large" target="_blank" rel="noopener">
								⭐ <?php esc_html_e( 'Get LaunchOverlay Pro', 'launchoverlay-pro' ); ?>
							</a>
						</div>
					<?php endif; ?>
				</div>
			</div>

		</div><!-- .lo-grid -->
		<?php
	}

	// ── Bulk Rules Tab ───────────────────────────────────────────────────────

	private function tab_rules() {
		$rules = LO_Pro_Rules::get_all();
		$types = defined( 'LAUNCHOVERLAY_VERSION' )
			? LO_Settings::overlay_types()
			: array( 'coming_soon' => 'Coming Soon', 'pre_order' => 'Pre-Order', 'sold_out' => 'Sold Out' );
		?>
		<div class="lo-card lo-card--full">
			<div class="lo-card-head">
				<span class="lo-card-icon">📦</span>
				<h2><?php esc_html_e( 'Bulk Overlay Rules', 'launchoverlay-pro' ); ?></h2>
				<p class="lo-card-head-sub">
					<?php esc_html_e( 'Apply overlays to all products in a category or with a tag. Per-product settings always take priority.', 'launchoverlay-pro' ); ?>
				</p>
			</div>
			<div class="lo-card-body">
				<form method="post" action="">
					<?php wp_nonce_field( 'lo_pro_save', 'lo_pro_nonce' ); ?>

					<div id="lo-rules-container">
						<?php if ( empty( $rules ) ) : ?>
							<div class="lo-empty-rules" id="lo-empty-msg">
								<span class="lo-empty-icon">📋</span>
								<p><?php esc_html_e( 'No bulk rules yet. Click "Add Rule" to create your first rule.', 'launchoverlay-pro' ); ?></p>
							</div>
						<?php endif; ?>
						<?php foreach ( $rules as $i => $rule ) : ?>
							<?php $this->render_rule_row( $i, $rule, $types ); ?>
						<?php endforeach; ?>
					</div>

					<div class="lo-rules-actions">
						<button type="button" id="lo-add-rule" class="lo-btn-secondary">
							+ <?php esc_html_e( 'Add Rule', 'launchoverlay-pro' ); ?>
						</button>
					</div>

					<div class="lo-save-bar">
						<input type="submit" name="lo_save_rules" class="lo-btn-primary"
							value="<?php esc_attr_e( 'Save Rules', 'launchoverlay-pro' ); ?>" />
					</div>
				</form>
			</div>
		</div>

		<!-- JS template for new rule rows -->
		<script type="text/html" id="lo-rule-tmpl">
			<?php $this->render_rule_row( '__IDX__', array(), $types ); ?>
		</script>
		<?php
	}

	/**
	 * Render a single rule row.
	 */
	private function render_rule_row( $i, $rule, $types ) {
		$enabled    = $rule['enabled']          ?? 'yes';
		$rule_type  = $rule['rule_type']         ?? 'category';
		$term_id    = $rule['term_id']           ?? '';
		$ov_type    = $rule['overlay_type']      ?? 'coming_soon';
		$hide_btn   = $rule['hide_add_to_cart']  ?? 'no';
		$hide_price = $rule['hide_price']        ?? 'no';
		$custom_msg = $rule['custom_message']    ?? '';
		?>
		<div class="lo-rule-row" data-index="<?php echo esc_attr( $i ); ?>">
			<div class="lo-rule-header">
				<div class="lo-rule-header-left">
					<label class="lo-switch lo-switch--sm">
						<input type="checkbox" name="lo_rules[<?php echo esc_attr( $i ); ?>][enabled]"
							value="yes" <?php checked( $enabled, 'yes' ); ?> />
						<span class="lo-switch-track"><span class="lo-switch-thumb"></span></span>
					</label>
					<span class="lo-rule-label">
						<?php echo esc_html__( 'Rule', 'launchoverlay-pro' ) . ' #' . ( is_numeric( $i ) ? (int)$i + 1 : '?' ); ?>
					</span>
				</div>
				<button type="button" class="lo-remove-rule" title="<?php esc_attr_e( 'Remove rule', 'launchoverlay-pro' ); ?>">
					✕ <?php esc_html_e( 'Remove', 'launchoverlay-pro' ); ?>
				</button>
			</div>

			<div class="lo-rule-body">
				<div class="lo-rule-field">
					<label><?php esc_html_e( 'Match By', 'launchoverlay-pro' ); ?></label>
					<select name="lo_rules[<?php echo esc_attr( $i ); ?>][rule_type]"
							class="lo-select lo-rule-type-select" data-index="<?php echo esc_attr( $i ); ?>">
						<option value="category" <?php selected( $rule_type, 'category' ); ?>><?php esc_html_e( 'Category', 'launchoverlay-pro' ); ?></option>
						<option value="tag"      <?php selected( $rule_type, 'tag' ); ?>><?php esc_html_e( 'Tag', 'launchoverlay-pro' ); ?></option>
					</select>
				</div>

				<div class="lo-rule-field">
					<label><?php esc_html_e( 'Select Term', 'launchoverlay-pro' ); ?></label>
					<select name="lo_rules[<?php echo esc_attr( $i ); ?>][term_id]"
							class="lo-select lo-term-select" data-index="<?php echo esc_attr( $i ); ?>"
							data-selected="<?php echo esc_attr( $term_id ); ?>">
						<option value=""><?php esc_html_e( '— Select —', 'launchoverlay-pro' ); ?></option>
					</select>
				</div>

				<div class="lo-rule-field">
					<label><?php esc_html_e( 'Overlay Type', 'launchoverlay-pro' ); ?></label>
					<select name="lo_rules[<?php echo esc_attr( $i ); ?>][overlay_type]" class="lo-select">
						<?php foreach ( $types as $k => $v ) : ?>
							<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $ov_type, $k ); ?>>
								<?php echo esc_html( $v ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="lo-rule-field lo-rule-field--checks">
					<label><?php esc_html_e( 'Purchase Control', 'launchoverlay-pro' ); ?></label>
					<div class="lo-rule-checks">
						<label class="lo-check-label">
							<input type="checkbox" name="lo_rules[<?php echo esc_attr( $i ); ?>][hide_add_to_cart]"
								value="yes" <?php checked( $hide_btn, 'yes' ); ?> />
							<?php esc_html_e( 'Disable Add to Cart', 'launchoverlay-pro' ); ?>
						</label>
						<label class="lo-check-label">
							<input type="checkbox" name="lo_rules[<?php echo esc_attr( $i ); ?>][hide_price]"
								value="yes" <?php checked( $hide_price, 'yes' ); ?> />
							<?php esc_html_e( 'Hide Price', 'launchoverlay-pro' ); ?>
						</label>
					</div>
				</div>

				<div class="lo-rule-field lo-rule-field--wide">
					<label><?php esc_html_e( 'Replacement Message', 'launchoverlay-pro' ); ?></label>
					<input type="text"
						name="lo_rules[<?php echo esc_attr( $i ); ?>][custom_message]"
						value="<?php echo esc_attr( $custom_msg ); ?>"
						class="lo-text-input lo-rule-msg-input"
						placeholder="<?php esc_attr_e( 'e.g. Join the waitlist to be notified', 'launchoverlay-pro' ); ?>" />
				</div>
			</div>
		</div>
		<?php
	}

	// ── Schedule Tab ─────────────────────────────────────────────────────────

	private function tab_schedule() {
		$upcoming = LO_Pro_Scheduler::get_upcoming( 20 );
		?>
		<div class="lo-card lo-card--full">
			<div class="lo-card-head">
				<span class="lo-card-icon">📅</span>
				<h2><?php esc_html_e( 'Upcoming Launches', 'launchoverlay-pro' ); ?></h2>
				<p class="lo-card-head-sub">
					<?php esc_html_e( 'Products with auto-remove overlays scheduled for future dates.', 'launchoverlay-pro' ); ?>
				</p>
			</div>
			<div class="lo-card-body">
				<?php if ( empty( $upcoming ) ) : ?>
					<div class="lo-empty-rules">
						<span class="lo-empty-icon">🗓️</span>
						<p><?php esc_html_e( 'No upcoming launches. Set a Launch Date on a product and enable Auto-Remove Overlay to schedule it here.', 'launchoverlay-pro' ); ?></p>
						<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=product' ) ); ?>" class="lo-btn-secondary">
							<?php esc_html_e( 'View Products', 'launchoverlay-pro' ); ?>
						</a>
					</div>
				<?php else : ?>
					<table class="lo-schedule-table widefat">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Product', 'launchoverlay-pro' ); ?></th>
								<th><?php esc_html_e( 'Launch Date', 'launchoverlay-pro' ); ?></th>
								<th><?php esc_html_e( 'Countdown', 'launchoverlay-pro' ); ?></th>
								<th><?php esc_html_e( 'Actions', 'launchoverlay-pro' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $upcoming as $row ) : ?>
								<tr>
									<td>
										<strong><?php echo esc_html( $row['title'] ); ?></strong>
										<br/><span class="lo-schedule-pid">#<?php echo esc_html( $row['pid'] ); ?></span>
									</td>
									<td>
										<span class="lo-schedule-date"><?php echo esc_html( $row['date_fmt'] ); ?></span>
									</td>
									<td>
										<span class="lo-schedule-countdown"><?php echo esc_html( $row['remaining'] ); ?></span>
									</td>
									<td>
										<a href="<?php echo esc_url( $row['edit_url'] ); ?>" class="lo-btn-secondary lo-btn-sm">
											<?php esc_html_e( 'Edit Product', 'launchoverlay-pro' ); ?>
										</a>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					<p class="lo-schedule-note">
						<?php
						printf(
							/* translators: %s: cron interval */
							esc_html__( 'Overlays are checked and removed %s.', 'launchoverlay-pro' ),
							'<strong>' . esc_html__( 'hourly via WP-Cron', 'launchoverlay-pro' ) . '</strong>'
						);
						?>
					</p>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	// ── Locked gate ──────────────────────────────────────────────────────────

	private function tab_locked( $feature ) {
		?>
		<div class="lo-card lo-card--full lo-pro-gate-card">
			<div class="lo-pro-gate-panel lo-pro-gate-panel--large">
				<span class="lo-gate-icon" style="font-size:42px;">🔒</span>
				<h2><?php echo esc_html( $feature ); ?> — <?php esc_html_e( 'License Required', 'launchoverlay-pro' ); ?></h2>
				<p><?php esc_html_e( 'Activate your Pro license to unlock this feature.', 'launchoverlay-pro' ); ?></p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=launchoverlay-pro&tab=license' ) ); ?>" class="lo-btn-primary">
					<?php esc_html_e( 'Activate License', 'launchoverlay-pro' ); ?>
				</a>
			</div>
		</div>
		<?php
	}
}
