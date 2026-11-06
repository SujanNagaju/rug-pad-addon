<?php
/**
 * Plugin Name:       Rug Pad Addon for WooCommerce
 * Description:       Offers a custom-size rug pad on the Cart and Checkout pages. Customers enter length and width, the price is calculated per square foot (dimensions round up to the next whole foot), and the pad is added to the cart and saved on the order with full details.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Your Name
 * License:           GPL-2.0-or-later
 * Text Domain:       rug-pad-addon
 * WC requires at least: 7.0
 */

defined( 'ABSPATH' ) || exit;

define( 'RPA_VERSION', '1.0.0' );
define( 'RPA_FILE', __FILE__ );
define( 'RPA_DIR', plugin_dir_path( __FILE__ ) );
define( 'RPA_URL', plugin_dir_url( __FILE__ ) );

require_once RPA_DIR . 'includes/class-rpa-settings.php';

final class RPA_Plugin {

	const PRODUCT_OPTION = 'rpa_product_id';

	/** @var RPA_Plugin */
	private static $instance;

	/** True only while our own AJAX handler adds the pad to the cart. */
	private $adding = false;

	/** Prevents the section from rendering twice on a page. */
	private $rendered = false;

	public static function instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		register_activation_hook( RPA_FILE, array( __CLASS__, 'activate' ) );

		add_action( 'plugins_loaded', array( $this, 'init' ) );
		add_action( 'before_woocommerce_init', array( $this, 'declare_compat' ) );
	}

	public function declare_compat() {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', RPA_FILE, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', RPA_FILE, true );
		}
	}

	public static function activate() {
		if ( class_exists( 'WooCommerce' ) ) {
			self::instance()->get_product_id();
		}
	}

	public function init() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', function () {
				echo '<div class="notice notice-error"><p>' . esc_html__( 'Rug Pad Addon requires WooCommerce to be installed and active.', 'rug-pad-addon' ) . '</p></div>';
			} );
			return;
		}

		RPA_Settings::init();

		// Section output: classic cart / checkout.
		add_action( 'woocommerce_before_cart_collaterals', array( $this, 'render_cart_section' ) );
		add_action( 'woocommerce_before_checkout_form', array( $this, 'render_checkout_section' ), 20 );
		// Section output: Cart / Checkout blocks.
		add_filter( 'render_block', array( $this, 'render_block_section' ), 10, 2 );
		// Manual placement.
		add_shortcode( 'rug_pad_addon', array( $this, 'shortcode' ) );

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		// AJAX (uses ?wc-ajax= so the cart/session is fully loaded).
		add_action( 'wc_ajax_rpa_add_pad', array( $this, 'ajax_add_pad' ) );

		// Cart behaviour.
		add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'validate_add_to_cart' ), 10, 2 );
		add_filter( 'woocommerce_get_item_data', array( $this, 'cart_item_data' ), 10, 2 );
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'set_cart_prices' ), 20 );
		add_filter( 'woocommerce_cart_item_quantity', array( $this, 'lock_quantity' ), 10, 3 );

		// Order.
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'save_order_item_meta' ), 10, 4 );
		add_filter( 'woocommerce_order_item_display_meta_key', array( $this, 'pretty_meta_key' ), 10, 2 );

		// Keep the helper product out of the storefront.
		add_action( 'template_redirect', array( $this, 'block_product_page' ) );
		add_filter( 'woocommerce_product_is_visible', array( $this, 'hide_from_catalog' ), 10, 2 );
	}

	/* ------------------------------------------------------------------
	 * Helper product
	 * ---------------------------------------------------------------- */

	public function get_product_id() {
		$id = (int) get_option( self::PRODUCT_OPTION );
		if ( $id && 'product' === get_post_type( $id ) && 'trash' !== get_post_status( $id ) ) {
			return $id;
		}

		$product = new WC_Product_Simple();
		$product->set_name( __( 'Rug Pad', 'rug-pad-addon' ) );
		$product->set_status( 'publish' );
		$product->set_catalog_visibility( 'hidden' );
		$product->set_virtual( true );
		$product->set_sold_individually( true );
		$product->set_regular_price( '0' );
		$product->set_tax_status( 'taxable' );
		$product->set_short_description( __( 'Premium non-slip rug pad, cut to the exact size you need. Keeps your rug in place and protects your floors.', 'rug-pad-addon' ) );
		$id = $product->save();

		update_option( self::PRODUCT_OPTION, $id );
		return $id;
	}

	private function is_pad_product( $product_id ) {
		return (int) $product_id === (int) get_option( self::PRODUCT_OPTION );
	}

	public function hide_from_catalog( $visible, $product_id ) {
		return $this->is_pad_product( $product_id ) ? false : $visible;
	}

	public function block_product_page() {
		if ( is_singular( 'product' ) && $this->is_pad_product( get_queried_object_id() ) && ! current_user_can( 'manage_woocommerce' ) ) {
			wp_safe_redirect( wc_get_cart_url() );
			exit;
		}
	}

	/* ------------------------------------------------------------------
	 * Pricing
	 * ---------------------------------------------------------------- */

	/**
	 * Convert feet + inches to billable whole feet. Any extra inch rounds up
	 * (11 ft 1 in => 12 ft).
	 */
	public static function billable_feet( $feet, $inches ) {
		$feet   = max( 0, (int) $feet );
		$inches = max( 0, (int) $inches );
		return $feet + ( $inches > 0 ? 1 : 0 );
	}

	/**
	 * Validate input and calculate area and price.
	 *
	 * @return array|WP_Error
	 */
	public static function calculate( $length_ft, $length_in, $width_ft, $width_in ) {
		$max = RPA_Settings::get( 'max_dimension' );

		foreach ( array( $length_ft, $width_ft ) as $v ) {
			if ( ! is_numeric( $v ) || (int) $v < 0 ) {
				return new WP_Error( 'rpa_invalid', __( 'Please enter valid dimensions.', 'rug-pad-addon' ) );
			}
		}
		foreach ( array( $length_in, $width_in ) as $v ) {
			if ( ! is_numeric( $v ) || (int) $v < 0 || (int) $v > 11 ) {
				return new WP_Error( 'rpa_invalid', __( 'Inches must be between 0 and 11.', 'rug-pad-addon' ) );
			}
		}

		$length = self::billable_feet( $length_ft, $length_in );
		$width  = self::billable_feet( $width_ft, $width_in );

		if ( $length < 1 || $width < 1 ) {
			return new WP_Error( 'rpa_invalid', __( 'Length and width must be at least 1 foot.', 'rug-pad-addon' ) );
		}
		if ( $length > $max || $width > $max ) {
			/* translators: %d: maximum feet */
			return new WP_Error( 'rpa_too_big', sprintf( __( 'Maximum size is %d feet per side.', 'rug-pad-addon' ), $max ) );
		}

		$rate  = (float) RPA_Settings::get( 'price_per_sqft' );
		$area  = $length * $width;
		$price = round( $area * $rate, wc_get_price_decimals() );

		return array(
			'length_ft'   => (int) $length_ft,
			'length_in'   => (int) $length_in,
			'width_ft'    => (int) $width_ft,
			'width_in'    => (int) $width_in,
			'length'      => $length,
			'width'       => $width,
			'area'        => $area,
			'rate'        => $rate,
			'price'       => $price,
		);
	}

	private static function format_size( $feet, $inches ) {
		return $inches > 0
			/* translators: 1: feet, 2: inches */
			? sprintf( __( '%1$d ft %2$d in', 'rug-pad-addon' ), $feet, $inches )
			/* translators: %d: feet */
			: sprintf( __( '%d ft', 'rug-pad-addon' ), $feet );
	}

	/* ------------------------------------------------------------------
	 * AJAX: add pad to cart
	 * ---------------------------------------------------------------- */

	public function ajax_add_pad() {
		check_ajax_referer( 'rpa_add_pad', 'nonce' );

		$data = self::calculate(
			isset( $_POST['length_ft'] ) ? wp_unslash( $_POST['length_ft'] ) : '',
			isset( $_POST['length_in'] ) ? wp_unslash( $_POST['length_in'] ) : 0,
			isset( $_POST['width_ft'] ) ? wp_unslash( $_POST['width_ft'] ) : '',
			isset( $_POST['width_in'] ) ? wp_unslash( $_POST['width_in'] ) : 0
		);

		if ( is_wp_error( $data ) ) {
			wp_send_json_error( array( 'message' => $data->get_error_message() ), 400 );
		}

		$this->adding = true;
		$key          = WC()->cart->add_to_cart(
			$this->get_product_id(),
			1,
			0,
			array(),
			array(
				'rpa_data' => $data,
				'rpa_uid'  => wp_generate_uuid4(), // Unique line per pad, even with identical sizes.
			)
		);
		$this->adding = false;

		if ( ! $key ) {
			wp_send_json_error( array( 'message' => __( 'Could not add the rug pad to your cart. Please try again.', 'rug-pad-addon' ) ), 500 );
		}

		WC()->cart->calculate_totals();

		wp_send_json_success( array( 'message' => __( 'Rug pad added to your order.', 'rug-pad-addon' ) ) );
	}

	/** Only allow the pad to enter the cart through our calculator. */
	public function validate_add_to_cart( $passed, $product_id ) {
		if ( $this->is_pad_product( $product_id ) && ! $this->adding ) {
			wc_add_notice( __( 'Rug pads must be added with the size calculator in your cart.', 'rug-pad-addon' ), 'error' );
			return false;
		}
		return $passed;
	}

	public function set_cart_prices( $cart ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}
		foreach ( $cart->get_cart() as $item ) {
			if ( isset( $item['rpa_data']['price'] ) ) {
				$item['data']->set_price( (float) $item['rpa_data']['price'] );
			}
		}
	}

	public function lock_quantity( $quantity_html, $cart_item_key, $cart_item ) {
		if ( isset( $cart_item['rpa_data'] ) ) {
			return '1';
		}
		return $quantity_html;
	}

	public function cart_item_data( $item_data, $cart_item ) {
		if ( empty( $cart_item['rpa_data'] ) ) {
			return $item_data;
		}
		$d = $cart_item['rpa_data'];

		$item_data[] = array( 'key' => __( 'Length', 'rug-pad-addon' ), 'value' => self::format_size( $d['length_ft'], $d['length_in'] ) );
		$item_data[] = array( 'key' => __( 'Width', 'rug-pad-addon' ), 'value' => self::format_size( $d['width_ft'], $d['width_in'] ) );
		$item_data[] = array(
			'key'   => __( 'Billed size', 'rug-pad-addon' ),
			/* translators: 1: length, 2: width */
			'value' => sprintf( __( '%1$d ft × %2$d ft', 'rug-pad-addon' ), $d['length'], $d['width'] ),
		);
		$item_data[] = array(
			'key'   => __( 'Total area', 'rug-pad-addon' ),
			/* translators: %d: square feet */
			'value' => sprintf( __( '%d sq ft', 'rug-pad-addon' ), $d['area'] ),
		);
		$item_data[] = array( 'key' => __( 'Price per sq ft', 'rug-pad-addon' ), 'value' => wp_strip_all_tags( wc_price( $d['rate'] ) ) );

		return $item_data;
	}

	/* ------------------------------------------------------------------
	 * Order
	 * ---------------------------------------------------------------- */

	public function save_order_item_meta( $item, $cart_item_key, $values, $order ) {
		if ( empty( $values['rpa_data'] ) ) {
			return;
		}
		$d = $values['rpa_data'];

		// Visible to customer and admin (emails, order screen, invoices).
		$item->add_meta_data( __( 'Length', 'rug-pad-addon' ), self::format_size( $d['length_ft'], $d['length_in'] ), true );
		$item->add_meta_data( __( 'Width', 'rug-pad-addon' ), self::format_size( $d['width_ft'], $d['width_in'] ), true );
		/* translators: 1: length, 2: width */
		$item->add_meta_data( __( 'Billed size', 'rug-pad-addon' ), sprintf( __( '%1$d ft × %2$d ft', 'rug-pad-addon' ), $d['length'], $d['width'] ), true );
		/* translators: %d: square feet */
		$item->add_meta_data( __( 'Total area', 'rug-pad-addon' ), sprintf( __( '%d sq ft', 'rug-pad-addon' ), $d['area'] ), true );
		$item->add_meta_data( __( 'Price per sq ft', 'rug-pad-addon' ), wp_strip_all_tags( wc_price( $d['rate'] ) ), true );
		$item->add_meta_data( __( 'Pad total', 'rug-pad-addon' ), wp_strip_all_tags( wc_price( $d['price'] ) ), true );

		// Hidden raw values for reporting / exports.
		$item->add_meta_data( '_rpa_length_ft', $d['length_ft'], true );
		$item->add_meta_data( '_rpa_length_in', $d['length_in'], true );
		$item->add_meta_data( '_rpa_width_ft', $d['width_ft'], true );
		$item->add_meta_data( '_rpa_width_in', $d['width_in'], true );
		$item->add_meta_data( '_rpa_billed_length', $d['length'], true );
		$item->add_meta_data( '_rpa_billed_width', $d['width'], true );
		$item->add_meta_data( '_rpa_area_sqft', $d['area'], true );
		$item->add_meta_data( '_rpa_rate', $d['rate'], true );
		$item->add_meta_data( '_rpa_total', $d['price'], true );
	}

	public function pretty_meta_key( $display_key, $meta ) {
		return $display_key;
	}

	/* ------------------------------------------------------------------
	 * Front-end output
	 * ---------------------------------------------------------------- */

	private function should_show( $context ) {
		if ( ! RPA_Settings::get( 'show_on_' . $context ) ) {
			return false;
		}
		if ( ! WC()->cart || WC()->cart->is_empty() ) {
			return false;
		}
		if ( RPA_Settings::get( 'require_other_items' ) ) {
			foreach ( WC()->cart->get_cart() as $item ) {
				if ( ! isset( $item['rpa_data'] ) ) {
					return true;
				}
			}
			return false;
		}
		return true;
	}

	public function render_cart_section() {
		if ( $this->should_show( 'cart' ) ) {
			echo $this->get_section_html(); // phpcs:ignore WordPress.Security.EscapeOutput
		}
	}

	public function render_checkout_section() {
		if ( $this->should_show( 'checkout' ) ) {
			echo $this->get_section_html(); // phpcs:ignore WordPress.Security.EscapeOutput
		}
	}

	public function render_block_section( $content, $block ) {
		if ( empty( $block['blockName'] ) ) {
			return $content;
		}
		if ( 'woocommerce/cart' === $block['blockName'] && $this->should_show( 'cart' ) ) {
			return $this->get_section_html() . $content;
		}
		if ( 'woocommerce/checkout' === $block['blockName'] && $this->should_show( 'checkout' ) ) {
			return $this->get_section_html() . $content;
		}
		return $content;
	}

	public function shortcode() {
		return $this->get_section_html();
	}

	private function get_section_html() {
		if ( $this->rendered ) {
			return '';
		}
		$this->rendered = true;

		$product  = wc_get_product( $this->get_product_id() );
		if ( ! $product ) {
			return '';
		}
		$settings = RPA_Settings::all();

		wp_enqueue_style( 'rpa-style' );
		wp_enqueue_script( 'rpa-script' );

		ob_start();
		include RPA_DIR . 'templates/addon-section.php';
		return ob_get_clean();
	}

	public function enqueue_assets() {
		wp_register_style( 'rpa-style', RPA_URL . 'assets/css/rug-pad.css', array(), RPA_VERSION );
		wp_register_script( 'rpa-script', RPA_URL . 'assets/js/rug-pad.js', array(), RPA_VERSION, true );

		wp_localize_script(
			'rpa-script',
			'rpaData',
			array(
				'ajaxUrl'   => WC_AJAX::get_endpoint( 'rpa_add_pad' ),
				'nonce'     => wp_create_nonce( 'rpa_add_pad' ),
				'rate'      => (float) RPA_Settings::get( 'price_per_sqft' ),
				'maxDim'    => (int) RPA_Settings::get( 'max_dimension' ),
				'currency'  => array(
					'symbol'   => get_woocommerce_currency_symbol(),
					'format'   => get_woocommerce_price_format(),
					'decimals' => wc_get_price_decimals(),
					'decSep'   => wc_get_price_decimal_separator(),
					'thouSep'  => wc_get_price_thousand_separator(),
				),
				'i18n'      => array(
					'enter'    => __( 'Enter the length and width to see the price.', 'rug-pad-addon' ),
					'tooBig'   => __( 'Maximum size is %d feet per side.', 'rug-pad-addon' ),
					'adding'   => __( 'Adding…', 'rug-pad-addon' ),
					'error'    => __( 'Something went wrong. Please try again.', 'rug-pad-addon' ),
					'summary'  => __( 'Billed size: %1$s ft × %2$s ft = %3$s sq ft', 'rug-pad-addon' ),
				),
			)
		);
	}
}

RPA_Plugin::instance();
