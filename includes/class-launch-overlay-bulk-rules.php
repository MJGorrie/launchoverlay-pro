<?php
/**
 * Bulk Rules – apply overlay settings to all products in a category or tag.
 * Pro feature only.
 *
 * Rules are stored as an array of rule objects in a single option:
 * [
 *   {
 *     id:           "rule_abc123",
 *     type:         "category" | "tag",
 *     term_ids:     [12, 34],
 *     preset_text:  "coming_soon",
 *     preset_theme: "dark",
 *     disable_price: "yes"|"no",
 *     disable_add_to_cart: "yes"|"no",
 *     replace_button_text: "Coming Soon",
 *     priority:     10,   // lower = checked first
 *   },
 *   ...
 * ]
 *
 * Per-product settings always override bulk rules.
 *
 * @package LaunchOverlay
 * @author  Gorrie Technology Group, Inc.
 */

defined( 'ABSPATH' ) || exit;

class Launch_Overlay_Bulk_Rules {

	const OPTION_KEY = 'launch_overlay_bulk_rules';

	public function __construct() {
		// Admin page for managing rules.
		add_action( 'admin_menu',          [ $this, 'add_admin_page' ] );
		add_action( 'admin_init',          [ $this, 'handle_save' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
	}

	// ── Admin page ─────────────────────────────────────────────────────────────

	public function add_admin_page(): void {
		add_submenu_page(
			'woocommerce',
			__( 'LaunchOverlay – Bulk Rules', 'launch-overlay' ),
			__( 'Launch Bulk Rules', 'launch-overlay' ),
			'manage_woocommerce',
			'launch-overlay-bulk-rules',
			[ $this, 'render_admin_page' ]
		);
	}

	public function enqueue_admin_assets( string $hook ): void {
		if ( false === strpos( $hook, 'launch-overlay-bulk-rules' ) ) {
			return;
		}
		// Select2 (already loaded by WooCommerce).
		wp_enqueue_script( 'select2' );
		wp_enqueue_style( 'select2' );
	}

	public function render_admin_page(): void {
		if ( ! Launch_Overlay_Core::is_pro() ) {
			echo '<div class="wrap"><h1>' . esc_html__( 'LaunchOverlay – Bulk Rules', 'launch-overlay' ) . '</h1>';
			echo '<div class="notice notice-warning"><p>' .
				esc_html__( 'Bulk Rules are a Pro feature. Upgrade to LaunchOverlay Pro to use them.', 'launch-overlay' ) .
			'</p></div></div>';
			return;
		}

		$rules        = self::get_rules();
		$preset_texts = Launch_Overlay_Core::preset_texts();
		$themes       = Launch_Overlay_Core::preset_themes();
		$categories   = get_terms( [ 'taxonomy' => 'product_cat', 'hide_empty' => false ] );
		$tags         = get_terms( [ 'taxonomy' => 'product_tag', 'hide_empty' => false ] );

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'LaunchOverlay – Bulk Rules', 'launch-overlay' ); ?></h1>
			<p><?php esc_html_e( 'Apply overlay settings to all products in a category or tag. Per-product settings always take priority.', 'launch-overlay' ); ?></p>

			<form method="post" action="">
				<?php wp_nonce_field( 'launch_overlay_bulk_rules', 'lo_bulk_nonce' ); ?>

				<table class="wp-list-table widefat fixed striped" id="lo-bulk-rules-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Type', 'launch-overlay' ); ?></th>
							<th><?php esc_html_e( 'Categories / Tags', 'launch-overlay' ); ?></th>
							<th><?php esc_html_e( 'Banner Text', 'launch-overlay' ); ?></th>
							<th><?php esc_html_e( 'Theme', 'launch-overlay' ); ?></th>
							<th><?php esc_html_e( 'Hide Price', 'launch-overlay' ); ?></th>
							<th><?php esc_html_e( 'Disable Cart', 'launch-overlay' ); ?></th>
							<th><?php esc_html_e( 'Priority', 'launch-overlay' ); ?></th>
							<th><?php esc_html_e( 'Remove', 'launch-overlay' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php if ( empty( $rules ) ) : ?>
						<tr id="lo-no-rules"><td colspan="8"><?php esc_html_e( 'No rules yet. Add one below.', 'launch-overlay' ); ?></td></tr>
					<?php endif; ?>
					<?php foreach ( $rules as $i => $rule ) :
						$this->render_rule_row( $i, $rule, $preset_texts, $themes, $categories, $tags );
					endforeach; ?>
					</tbody>
				</table>

				<p>
					<button type="button" class="button" id="lo-add-rule">
						+ <?php esc_html_e( 'Add Rule', 'launch-overlay' ); ?>
					</button>
				</p>

				<?php submit_button( __( 'Save Rules', 'launch-overlay' ) ); ?>
			</form>
		</div>

		<script>
		var loRuleIndex = <?php echo esc_js( absint( count( $rules ) ) ); ?>;
		var loCategories = <?php echo wp_json_encode( array_map( fn($t) => ['id' => $t->term_id, 'name' => $t->name], is_array($categories) ? $categories : [] ) ); ?>;
		var loTags = <?php echo wp_json_encode( array_map( fn($t) => ['id' => $t->term_id, 'name' => $t->name], is_array($tags) ? $tags : [] ) ); ?>;
		var loPresets = <?php echo wp_json_encode( array_map( fn($k, $v) => ['key' => $k, 'label' => $v], array_keys(Launch_Overlay_Core::preset_texts()), Launch_Overlay_Core::preset_texts() ) ); ?>;
		var loThemes = <?php echo wp_json_encode( array_map( fn($k, $v) => ['key' => $k, 'label' => $v['label']], array_keys(Launch_Overlay_Core::preset_themes()), Launch_Overlay_Core::preset_themes() ) ); ?>;

		document.getElementById('lo-add-rule').addEventListener('click', function() {
			var noRules = document.getElementById('lo-no-rules');
			if (noRules) noRules.remove();

			var tbody = document.querySelector('#lo-bulk-rules-table tbody');
			var row = document.createElement('tr');
			var i = loRuleIndex++;

			var catOptions = loCategories.map(c => '<option value="'+c.id+'">'+c.name+'</option>').join('');
			var tagOptions = loTags.map(t => '<option value="'+t.id+'">'+t.name+'</option>').join('');
			var presetOptions = loPresets.map(p => '<option value="'+p.key+'">'+p.label+'</option>').join('');
			var themeOptions = loThemes.map(t => '<option value="'+t.key+'">'+t.label+'</option>').join('');

			row.innerHTML =
				'<td><select name="lo_rules['+i+'][type]" onchange="loToggleTerms(this,'+i+')">' +
				'<option value="category">Category</option><option value="tag">Tag</option></select></td>' +
				'<td><select name="lo_rules['+i+'][term_ids][]" multiple class="lo-term-select" id="lo-terms-'+i+'" style="width:200px">'+catOptions+'</select></td>' +
				'<td><select name="lo_rules['+i+'][preset_text]">'+presetOptions+'</select></td>' +
				'<td><select name="lo_rules['+i+'][preset_theme]">'+themeOptions+'</select></td>' +
				'<td><input type="checkbox" name="lo_rules['+i+'][disable_price]" value="yes"></td>' +
				'<td><input type="checkbox" name="lo_rules['+i+'][disable_add_to_cart]" value="yes" checked></td>' +
				'<td><input type="number" name="lo_rules['+i+'][priority]" value="10" style="width:60px"></td>' +
				'<td><button type="button" class="button-link-delete" onclick="this.closest(\'tr\').remove()">&#x2715;</button></td>';
			tbody.appendChild(row);
		});

		function loToggleTerms(sel, i) {
			var termSel = document.getElementById('lo-terms-'+i);
			var options = sel.value === 'category' ?
				loCategories.map(c => '<option value="'+c.id+'">'+c.name+'</option>').join('') :
				loTags.map(t => '<option value="'+t.id+'">'+t.name+'</option>').join('');
			termSel.innerHTML = options;
		}
		</script>
		<?php
	}

	private function render_rule_row( int $i, array $rule, array $preset_texts, array $themes, $categories, $tags ): void {
		$type      = $rule['type'] ?? 'category';
		$term_ids  = (array) ( $rule['term_ids'] ?? [] );
		$terms     = $type === 'category' ? $categories : $tags;
		?>
		<tr>
			<td>
				<select name="lo_rules[<?php echo esc_attr( absint( $i ) ); ?>][type]" onchange="loToggleTerms(this,<?php echo esc_attr( absint( $i ) ); ?>)">
					<option value="category" <?php selected( $type, 'category' ); ?>><?php esc_html_e( 'Category', 'launch-overlay' ); ?></option>
					<option value="tag"      <?php selected( $type, 'tag' ); ?>><?php esc_html_e( 'Tag', 'launch-overlay' ); ?></option>
				</select>
			</td>
			<td>
				<select name="lo_rules[<?php echo esc_attr( absint( $i ) ); ?>][term_ids][]" multiple id="lo-terms-<?php echo esc_attr( absint( $i ) ); ?>" style="width:200px">
					<?php if ( is_array( $terms ) ) : foreach ( $terms as $term ) : ?>
						<option value="<?php echo esc_attr( $term->term_id ); ?>" <?php echo selected( in_array( (int) $term->term_id, array_map( 'absint', $term_ids ), true ), true, false ); ?>>
							<?php echo esc_html( $term->name ); ?>
						</option>
					<?php endforeach; endif; ?>
				</select>
			</td>
			<td>
				<select name="lo_rules[<?php echo esc_attr( absint( $i ) ); ?>][preset_text]">
					<?php foreach ( $preset_texts as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $rule['preset_text'] ?? '', $key ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</td>
			<td>
				<select name="lo_rules[<?php echo esc_attr( absint( $i ) ); ?>][preset_theme]">
					<?php foreach ( $themes as $key => $theme ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $rule['preset_theme'] ?? '', $key ); ?>><?php echo esc_html( $theme['label'] ); ?></option>
					<?php endforeach; ?>
				</select>
			</td>
			<td><input type="checkbox" name="lo_rules[<?php echo esc_attr( absint( $i ) ); ?>][disable_price]" value="yes" <?php checked( $rule['disable_price'] ?? '', 'yes' ); ?>></td>
			<td><input type="checkbox" name="lo_rules[<?php echo esc_attr( absint( $i ) ); ?>][disable_add_to_cart]" value="yes" <?php checked( $rule['disable_add_to_cart'] ?? 'yes', 'yes' ); ?>></td>
			<td><input type="number" name="lo_rules[<?php echo esc_attr( absint( $i ) ); ?>][priority]" value="<?php echo esc_attr( $rule['priority'] ?? 10 ); ?>" style="width:60px"></td>
			<td><button type="button" class="button-link-delete" onclick="this.closest('tr').remove()">&#x2715;</button></td>
		</tr>
		<?php
	}

	// ── Save ──────────────────────────────────────────────────────────────────

	public function handle_save(): void {
		if ( ! isset( $_POST['lo_bulk_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['lo_bulk_nonce'] ) ), 'launch_overlay_bulk_rules' ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$raw_rules = isset( $_POST['lo_rules'] ) ? wp_unslash( $_POST['lo_rules'] ) : [];
		$clean     = [];

		$valid_texts  = array_keys( Launch_Overlay_Core::preset_texts() );
		$valid_themes = array_keys( Launch_Overlay_Core::preset_themes() );

		foreach ( $raw_rules as $raw ) {
			$type = in_array( $raw['type'] ?? '', [ 'category', 'tag' ], true ) ? $raw['type'] : 'category';

			$term_ids = array_filter( array_map( 'absint', (array) ( $raw['term_ids'] ?? [] ) ) );
			if ( empty( $term_ids ) ) {
				continue;
			}

			$preset_text  = in_array( $raw['preset_text'] ?? '', $valid_texts, true )  ? $raw['preset_text']  : 'coming_soon';
			$preset_theme = in_array( $raw['preset_theme'] ?? '', $valid_themes, true ) ? $raw['preset_theme'] : 'dark';

			$clean[] = [
				'id'                  => 'rule_' . wp_generate_password( 8, false ),
				'type'                => $type,
				'term_ids'            => array_values( $term_ids ),
				'preset_text'         => $preset_text,
				'preset_theme'        => $preset_theme,
				'disable_price'       => isset( $raw['disable_price'] ) ? 'yes' : 'no',
				'disable_add_to_cart' => isset( $raw['disable_add_to_cart'] ) ? 'yes' : 'no',
				'replace_button_text' => sanitize_text_field( $raw['replace_button_text'] ?? '' ),
				'priority'            => (int) ( $raw['priority'] ?? 10 ),
			];
		}

		// Sort by priority ascending.
		usort( $clean, fn( $a, $b ) => $a['priority'] <=> $b['priority'] );

		update_option( self::OPTION_KEY, $clean );
		add_action( 'admin_notices', fn() => print( '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Bulk rules saved.', 'launch-overlay' ) . '</p></div>' ) );
	}

	// ── Static helpers ────────────────────────────────────────────────────────

	public static function get_rules(): array {
		return (array) get_option( self::OPTION_KEY, [] );
	}

	/**
	 * Find the first matching bulk rule for a product.
	 * Returns null if no rule matches.
	 *
	 * @param int $product_id
	 * @return array|null
	 */
	public static function get_matching_rule( int $product_id ): ?array {
		if ( ! Launch_Overlay_Core::is_pro() ) {
			return null;
		}

		$rules = self::get_rules();
		if ( empty( $rules ) ) {
			return null;
		}

		$product_cats = wp_get_post_terms( $product_id, 'product_cat', [ 'fields' => 'ids' ] );
		$product_tags = wp_get_post_terms( $product_id, 'product_tag', [ 'fields' => 'ids' ] );

		foreach ( $rules as $rule ) {
			$term_ids = (array) ( $rule['term_ids'] ?? [] );

			if ( $rule['type'] === 'category' ) {
				$match = array_intersect( $term_ids, is_array( $product_cats ) ? $product_cats : [] );
			} else {
				$match = array_intersect( $term_ids, is_array( $product_tags ) ? $product_tags : [] );
			}

			if ( ! empty( $match ) ) {
				return $rule;
			}
		}

		return null;
	}
}
