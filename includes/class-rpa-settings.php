<?php
defined( 'ABSPATH' ) || exit;

/**
 * Settings page: WooCommerce → Rug Pad Addon.
 */
class RPA_Settings {

	const OPTION = 'rpa_settings';
	const SCREEN = 'woocommerce_page_rpa-settings';

	public static function defaults() {
		return array(
			'product_ids'      => array(),
			'price_per_sqft'   => 2,
			'max_dimension'    => 50,
			'prompt_text'      => __( 'Need a rugpad?', 'rug-pad-addon' ),
			'link_text'        => __( 'Click Here', 'rug-pad-addon' ),
			'heading'          => __( 'Do you need a product addon?', 'rug-pad-addon' ),
			'button_label'     => __( 'Add rug pad to order', 'rug-pad-addon' ),
			'show_on_cart'     => 1,
			'show_on_checkout' => 1,
			'show_when_empty'  => 1,
		);
	}

	public static function all() {
		return wp_parse_args( (array) get_option( self::OPTION, array() ), self::defaults() );
	}

	public static function get( $key ) {
		$all = self::all();
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	/**
	 * IDs of the products the store owner set as rug pads (existing products only).
	 *
	 * @return int[]
	 */
	public static function product_ids() {
		$ids = array_map( 'absint', (array) self::get( 'product_ids' ) );
		$ids = array_filter(
			$ids,
			function ( $id ) {
				return $id && 'product' === get_post_type( $id ) && 'trash' !== get_post_status( $id );
			}
		);
		return array_values( array_unique( $ids ) );
	}

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_assets' ), 20 );
		add_filter( 'plugin_action_links_' . plugin_basename( RPA_FILE ), array( __CLASS__, 'action_links' ) );
	}

	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=rpa-settings' ) ) . '">' . esc_html__( 'Settings', 'rug-pad-addon' ) . '</a>' );
		return $links;
	}

	public static function admin_assets( $hook ) {
		if ( self::SCREEN !== $hook ) {
			return;
		}
		// Product search (selectWoo) used by WooCommerce itself.
		wp_enqueue_style( 'woocommerce_admin_styles' );
		wp_enqueue_script( 'wc-enhanced-select' );
	}

	public static function menu() {
		add_submenu_page(
			'woocommerce',
			__( 'Rug Pad Addon', 'rug-pad-addon' ),
			__( 'Rug Pad Addon', 'rug-pad-addon' ),
			'manage_woocommerce',
			'rpa-settings',
			array( __CLASS__, 'page' )
		);
	}

	public static function register() {
		register_setting( 'rpa_group', self::OPTION, array( 'sanitize_callback' => array( __CLASS__, 'sanitize' ) ) );
	}

	public static function sanitize( $input ) {
		$d = self::defaults();
		$o = array();

		// Rug pad products: simple products only.
		$ids = array();
		foreach ( isset( $input['product_ids'] ) ? (array) $input['product_ids'] : array() as $raw ) {
			$product = wc_get_product( absint( $raw ) );
			if ( ! $product ) {
				continue;
			}
			if ( ! $product->is_type( 'simple' ) ) {
				/* translators: %s: product name */
				add_settings_error( self::OPTION, 'rpa_type', sprintf( __( '“%s” was not added: only simple products can be used as rug pads.', 'rug-pad-addon' ), $product->get_name() ), 'warning' );
				continue;
			}
			$ids[] = $product->get_id();
		}
		$o['product_ids'] = array_values( array_unique( $ids ) );

		$o['price_per_sqft']   = isset( $input['price_per_sqft'] ) ? max( 0, (float) wc_format_decimal( $input['price_per_sqft'] ) ) : $d['price_per_sqft'];
		$o['max_dimension']    = isset( $input['max_dimension'] ) ? max( 1, min( 200, (int) $input['max_dimension'] ) ) : $d['max_dimension'];
		$o['prompt_text']      = isset( $input['prompt_text'] ) ? sanitize_text_field( $input['prompt_text'] ) : $d['prompt_text'];
		$o['link_text']        = isset( $input['link_text'] ) && '' !== trim( $input['link_text'] ) ? sanitize_text_field( $input['link_text'] ) : $d['link_text'];
		$o['heading']          = isset( $input['heading'] ) ? sanitize_text_field( $input['heading'] ) : $d['heading'];
		$o['button_label']     = isset( $input['button_label'] ) ? sanitize_text_field( $input['button_label'] ) : $d['button_label'];
		$o['show_on_cart']     = empty( $input['show_on_cart'] ) ? 0 : 1;
		$o['show_on_checkout'] = empty( $input['show_on_checkout'] ) ? 0 : 1;
		$o['show_when_empty']  = empty( $input['show_when_empty'] ) ? 0 : 1;

		return $o;
	}

	public static function page() {
		$s   = self::all();
		$n   = self::OPTION;
		$ids = self::product_ids();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Rug Pad Addon', 'rug-pad-addon' ); ?></h1>
			<?php settings_errors( self::OPTION ); ?>

			<?php if ( empty( $ids ) ) : ?>
				<div class="notice notice-warning inline"><p><?php esc_html_e( 'No rug pad product selected yet. The “Need a rugpad?” link will not appear on the store until you choose at least one product below.', 'rug-pad-addon' ); ?></p></div>
			<?php endif; ?>

			<?php
			foreach ( $ids as $pid ) {
				$p = wc_get_product( $pid );
				if ( $p && ( $p->is_virtual() || $p->is_downloadable() ) ) {
					echo '<div class="notice notice-warning inline"><p>' . sprintf(
						/* translators: %s: product name */
						esc_html__( '“%s” is marked Virtual/Downloadable, so no shipping will be charged for it. Untick those options in the product if the pad is a physical item.', 'rug-pad-addon' ),
						esc_html( $p->get_name() )
					) . '</p></div>';
				}
			}
			?>

			<form method="post" action="options.php">
				<?php settings_fields( 'rpa_group' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="rpa_products"><?php esc_html_e( 'Rug pad products', 'rug-pad-addon' ); ?></label></th>
						<td>
							<select id="rpa_products" class="wc-product-search" multiple="multiple" style="width: 50%;" name="<?php echo esc_attr( $n ); ?>[product_ids][]" data-placeholder="<?php esc_attr_e( 'Search for a product…', 'rug-pad-addon' ); ?>" data-action="woocommerce_json_search_products" data-exclude_type="variable">
								<?php
								foreach ( $ids as $pid ) {
									$p = wc_get_product( $pid );
									if ( $p ) {
										echo '<option value="' . esc_attr( $pid ) . '" selected="selected">' . esc_html( wp_strip_all_tags( $p->get_formatted_name() ) ) . '</option>';
									}
								}
								?>
							</select>
							<p class="description"><?php esc_html_e( 'Choose the existing simple product(s) that are rug pads. Shipping, weight, tax class and stock follow each product’s own settings; the price is calculated from the size entered. If you select more than one, customers pick which pad they want.', 'rug-pad-addon' ); ?></p>
							<p class="description"><?php esc_html_e( 'Tip: set the product’s catalog visibility to “Hidden” so it is only sold through the size calculator. Adding it to the cart any other way is blocked.', 'rug-pad-addon' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="rpa_price"><?php esc_html_e( 'Price per square foot', 'rug-pad-addon' ); ?></label></th>
						<td><input id="rpa_price" type="number" step="0.01" min="0" name="<?php echo esc_attr( $n ); ?>[price_per_sqft]" value="<?php echo esc_attr( $s['price_per_sqft'] ); ?>" class="small-text"> <?php echo esc_html( get_woocommerce_currency_symbol() ); ?></td>
					</tr>
					<tr>
						<th scope="row"><label for="rpa_max"><?php esc_html_e( 'Maximum feet per side', 'rug-pad-addon' ); ?></label></th>
						<td><input id="rpa_max" type="number" min="1" max="200" name="<?php echo esc_attr( $n ); ?>[max_dimension]" value="<?php echo esc_attr( $s['max_dimension'] ); ?>" class="small-text"></td>
					</tr>
					<tr>
						<th scope="row"><label for="rpa_prompt"><?php esc_html_e( 'Link text', 'rug-pad-addon' ); ?></label></th>
						<td>
							<input id="rpa_prompt" type="text" name="<?php echo esc_attr( $n ); ?>[prompt_text]" value="<?php echo esc_attr( $s['prompt_text'] ); ?>" class="regular-text">
							<input type="text" name="<?php echo esc_attr( $n ); ?>[link_text]" value="<?php echo esc_attr( $s['link_text'] ); ?>" class="regular-text" aria-label="<?php esc_attr_e( 'Clickable link text', 'rug-pad-addon' ); ?>">
							<p class="description"><?php esc_html_e( 'Plain text, then the clickable link. Default: “Need a rugpad?” + “Click Here”.', 'rug-pad-addon' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="rpa_heading"><?php esc_html_e( 'Form heading', 'rug-pad-addon' ); ?></label></th>
						<td><input id="rpa_heading" type="text" name="<?php echo esc_attr( $n ); ?>[heading]" value="<?php echo esc_attr( $s['heading'] ); ?>" class="regular-text"></td>
					</tr>
					<tr>
						<th scope="row"><label for="rpa_btn"><?php esc_html_e( 'Button label', 'rug-pad-addon' ); ?></label></th>
						<td><input id="rpa_btn" type="text" name="<?php echo esc_attr( $n ); ?>[button_label]" value="<?php echo esc_attr( $s['button_label'] ); ?>" class="regular-text"></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Show link on', 'rug-pad-addon' ); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[show_on_cart]" value="1" <?php checked( $s['show_on_cart'] ); ?>> <?php esc_html_e( 'Cart page', 'rug-pad-addon' ); ?></label><br>
							<label><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[show_on_checkout]" value="1" <?php checked( $s['show_on_checkout'] ); ?>> <?php esc_html_e( 'Checkout page', 'rug-pad-addon' ); ?></label><br>
							<label><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[show_when_empty]" value="1" <?php checked( $s['show_when_empty'] ); ?>> <?php esc_html_e( 'Empty cart (lets customers buy a rug pad on its own)', 'rug-pad-addon' ); ?></label>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
			<p><?php esc_html_e( 'Using a custom cart/checkout layout? Place the link manually with the shortcode', 'rug-pad-addon' ); ?> <code>[rug_pad_addon]</code>.</p>
		</div>
		<?php
	}
}
