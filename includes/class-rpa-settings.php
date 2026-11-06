<?php
defined( 'ABSPATH' ) || exit;

/**
 * Settings page: WooCommerce → Rug Pad Addon.
 */
class RPA_Settings {

	const OPTION = 'rpa_settings';

	public static function defaults() {
		return array(
			'price_per_sqft'      => 2,
			'max_dimension'       => 50,
			'heading'             => __( 'Do you need a product addon?', 'rug-pad-addon' ),
			'button_label'        => __( 'Add rug pad to order', 'rug-pad-addon' ),
			'show_on_cart'        => 1,
			'show_on_checkout'    => 1,
			'require_other_items' => 1,
		);
	}

	public static function all() {
		return wp_parse_args( (array) get_option( self::OPTION, array() ), self::defaults() );
	}

	public static function get( $key ) {
		$all = self::all();
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( RPA_FILE ), array( __CLASS__, 'action_links' ) );
	}

	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=rpa-settings' ) ) . '">' . esc_html__( 'Settings', 'rug-pad-addon' ) . '</a>' );
		return $links;
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

		$o['price_per_sqft']      = isset( $input['price_per_sqft'] ) ? max( 0, (float) wc_format_decimal( $input['price_per_sqft'] ) ) : $d['price_per_sqft'];
		$o['max_dimension']       = isset( $input['max_dimension'] ) ? max( 1, min( 200, (int) $input['max_dimension'] ) ) : $d['max_dimension'];
		$o['heading']             = isset( $input['heading'] ) ? sanitize_text_field( $input['heading'] ) : $d['heading'];
		$o['button_label']        = isset( $input['button_label'] ) ? sanitize_text_field( $input['button_label'] ) : $d['button_label'];
		$o['show_on_cart']        = empty( $input['show_on_cart'] ) ? 0 : 1;
		$o['show_on_checkout']    = empty( $input['show_on_checkout'] ) ? 0 : 1;
		$o['require_other_items'] = empty( $input['require_other_items'] ) ? 0 : 1;

		return $o;
	}

	public static function page() {
		$s   = self::all();
		$pid = (int) get_option( RPA_Plugin::PRODUCT_OPTION );
		$n   = self::OPTION;
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Rug Pad Addon', 'rug-pad-addon' ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'rpa_group' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="rpa_price"><?php esc_html_e( 'Price per square foot', 'rug-pad-addon' ); ?></label></th>
						<td><input id="rpa_price" type="number" step="0.01" min="0" name="<?php echo esc_attr( $n ); ?>[price_per_sqft]" value="<?php echo esc_attr( $s['price_per_sqft'] ); ?>" class="small-text"> <?php echo esc_html( get_woocommerce_currency_symbol() ); ?></td>
					</tr>
					<tr>
						<th scope="row"><label for="rpa_max"><?php esc_html_e( 'Maximum feet per side', 'rug-pad-addon' ); ?></label></th>
						<td><input id="rpa_max" type="number" min="1" max="200" name="<?php echo esc_attr( $n ); ?>[max_dimension]" value="<?php echo esc_attr( $s['max_dimension'] ); ?>" class="small-text"></td>
					</tr>
					<tr>
						<th scope="row"><label for="rpa_heading"><?php esc_html_e( 'Section heading', 'rug-pad-addon' ); ?></label></th>
						<td><input id="rpa_heading" type="text" name="<?php echo esc_attr( $n ); ?>[heading]" value="<?php echo esc_attr( $s['heading'] ); ?>" class="regular-text"></td>
					</tr>
					<tr>
						<th scope="row"><label for="rpa_btn"><?php esc_html_e( 'Button label', 'rug-pad-addon' ); ?></label></th>
						<td><input id="rpa_btn" type="text" name="<?php echo esc_attr( $n ); ?>[button_label]" value="<?php echo esc_attr( $s['button_label'] ); ?>" class="regular-text"></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Show on', 'rug-pad-addon' ); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[show_on_cart]" value="1" <?php checked( $s['show_on_cart'] ); ?>> <?php esc_html_e( 'Cart page', 'rug-pad-addon' ); ?></label><br>
							<label><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[show_on_checkout]" value="1" <?php checked( $s['show_on_checkout'] ); ?>> <?php esc_html_e( 'Checkout page', 'rug-pad-addon' ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Visibility', 'rug-pad-addon' ); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[require_other_items]" value="1" <?php checked( $s['require_other_items'] ); ?>> <?php esc_html_e( 'Hide the section when the cart only contains rug pads', 'rug-pad-addon' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Product image & description', 'rug-pad-addon' ); ?></th>
						<td>
							<?php if ( $pid ) : ?>
								<a href="<?php echo esc_url( get_edit_post_link( $pid ) ); ?>"><?php esc_html_e( 'Edit the “Rug Pad” product', 'rug-pad-addon' ); ?></a>
								<p class="description"><?php esc_html_e( 'The featured image and short description of that product are shown in the section. Do not change its price – it is calculated automatically.', 'rug-pad-addon' ); ?></p>
							<?php else : ?>
								<p class="description"><?php esc_html_e( 'The product is created the first time the section is shown.', 'rug-pad-addon' ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
			<p><?php esc_html_e( 'Using a custom cart/checkout layout? Place the section manually with the shortcode', 'rug-pad-addon' ); ?> <code>[rug_pad_addon]</code>.</p>
		</div>
		<?php
	}
}
