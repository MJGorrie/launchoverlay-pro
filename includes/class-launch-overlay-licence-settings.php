<?php
/**
 * Licence Settings – admin UI for key activation inside WooCommerce Settings.
 * @package LaunchOverlay
 */
defined( 'ABSPATH' ) || exit;

class Launch_Overlay_Licence_Settings {

	public function __construct() {
		add_action( 'admin_init',    [ $this, 'handle' ] );
		add_action( 'admin_notices', [ $this, 'notices' ] );
	}

	public function handle(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) return;
		if ( empty( $_POST['lo_lic_action'] ) ) return;
		if ( ! check_admin_referer( 'lo_lic_nonce', 'lo_lic_nonce' ) ) return;

		$action = sanitize_text_field( $_POST['lo_lic_action'] );

		if ( $action === 'activate' ) {
			$key    = sanitize_text_field( $_POST['lo_lic_key'] ?? '' );
			$result = Launch_Overlay_Licence::activate( $key );
			$type   = ! empty( $result['success'] ) ? 'success' : 'error';
			$msg    = ! empty( $result['success'] )
				? '✅ ' . ( $result['message'] ?? 'Activated!' ) . ' Pro features are now unlocked.'
				: '❌ ' . ( $result['message'] ?? 'Activation failed.' );
			set_transient( 'lo_lic_notice', compact( 'type', 'msg' ), 60 );
		}

		if ( $action === 'deactivate' ) {
			Launch_Overlay_Licence::deactivate();
			set_transient( 'lo_lic_notice', [
				'type' => 'updated',
				'msg'  => 'Licence deactivated. Pro features disabled.',
			], 60 );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=wc-settings&tab=launch_overlay' ) );
		exit;
	}

	public function notices(): void {
		$n = get_transient( 'lo_lic_notice' );
		if ( ! $n ) return;
		delete_transient( 'lo_lic_notice' );
		$class = $n['type'] === 'success' ? 'notice-success' : ( $n['type'] === 'error' ? 'notice-error' : 'notice-success' );
		echo '<div class="notice ' . esc_attr( $class ) . ' is-dismissible"><p>' . esc_html( $n['msg'] ) . '</p></div>';
	}

	// ── Render (called from settings page) ────────────────────────────────────

	public static function render(): void {
		$key    = Launch_Overlay_Licence::get_key();
		$is_pro = Launch_Overlay_Licence::is_pro();
		$plan   = Launch_Overlay_Licence::get_plan();
		$cache  = Launch_Overlay_Licence::get_cache();
		$nonce  = wp_create_nonce( 'lo_lic_nonce' );
		$portal = 'https://gorrie.us/portal';

		$plan_labels = [
			'lite'          => 'Lite (Free)',
			'pro_single'    => 'Pro – 1 Site',
			'pro_5site'     => 'Pro – 5 Sites',
			'pro_unlimited' => 'Pro – Unlimited',
		];
		$plan_label = $plan_labels[ $plan ] ?? ucfirst( str_replace( '_', ' ', $plan ) );
		?>
		<tr valign="top"><td colspan="2">
		<div style="max-width:680px;padding:8px 0">

			<?php if ( $is_pro ): ?>
			<div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:16px 20px;margin-bottom:20px;display:flex;align-items:center;gap:14px">
				<span style="font-size:28px">⭐</span>
				<div style="flex:1">
					<strong style="color:#166534;font-size:14px">LaunchOverlay Pro — <?php echo esc_html( $plan_label ) ?></strong><br>
					<span style="font-size:13px;color:#166534">
						Pro features are active on this site.
						<?php if ( ! empty( $cache['data']['expires_at'] ) ): ?>
							Renews <?php echo esc_html( date( 'M j, Y', strtotime( $cache['data']['expires_at'] ) ) ) ?>.
						<?php endif; ?>
					</span>
				</div>
				<a href="<?php echo esc_url( $portal ) ?>" target="_blank" style="font-size:13px;color:#166534;text-decoration:none;border:1px solid #bbf7d0;padding:6px 14px;border-radius:6px">Manage Account →</a>
			</div>
			<?php elseif ( $key ): ?>
			<div style="background:#fefce8;border:1px solid #fde047;border-radius:10px;padding:14px 18px;margin-bottom:20px">
				<strong style="color:#854d0e">⚠️ Licence saved but not verified</strong><br>
				<span style="font-size:13px;color:#854d0e">Try deactivating and re-activating. Or <a href="<?php echo esc_url($portal) ?>" style="color:#854d0e">check your account</a>.</span>
			</div>
			<?php else: ?>
			<div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:14px 18px;margin-bottom:20px">
				<strong style="color:#1e40af">🔑 Enter your licence key to unlock Pro</strong><br>
				<span style="font-size:13px;color:#1e40af">Get your key at <a href="<?php echo esc_url($portal) ?>" target="_blank" style="color:#1e40af">gorrie.us/portal</a></span>
			</div>
			<?php endif; ?>

			<?php if ( ! $is_pro ): ?>
			<form method="POST" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;margin-bottom:12px">
				<input type="hidden" name="lo_lic_nonce"  value="<?php echo esc_attr( $nonce ) ?>">
				<input type="hidden" name="lo_lic_action" value="activate">
				<div style="flex:1;min-width:280px">
					<label style="display:block;font-size:12px;font-weight:600;margin-bottom:6px;color:#374151">Licence Key</label>
					<input type="text" name="lo_lic_key" value="<?php echo esc_attr( $key ) ?>"
						placeholder="LO-XXXX-XXXX-XXXX-XXXX"
						style="width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;font-family:monospace">
				</div>
				<button type="submit" class="button button-primary" style="padding:0 20px;height:37px">
					<?php echo $key ? 'Re-activate' : 'Activate Licence' ?>
				</button>
				<a href="<?php echo esc_url( $portal ) ?>" target="_blank" class="button" style="height:37px;line-height:35px;padding:0 14px">Get a Key</a>
			</form>
			<?php endif; ?>

			<?php if ( $key ): ?>
			<form method="POST" style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
				<input type="hidden" name="lo_lic_nonce"  value="<?php echo esc_attr( $nonce ) ?>">
				<input type="hidden" name="lo_lic_action" value="deactivate">
				<button type="submit" class="button" style="color:#b91c1c;border-color:#fca5a5"
					onclick="return confirm('Deactivate this licence? Pro features will be disabled.')">
					Deactivate Licence
				</button>
				<span style="font-size:12px;color:#6b7280">
					Active key: <code style="background:#f3f4f6;padding:1px 6px;border-radius:4px"><?php echo esc_html( substr($key,0,8) ) ?>••••</code>
					· <a href="<?php echo esc_url( $portal ) ?>" target="_blank" style="color:#6b7280">Manage account</a>
				</span>
			</form>
			<?php endif; ?>

		</div>
		</td></tr>
		<?php
	}
}
