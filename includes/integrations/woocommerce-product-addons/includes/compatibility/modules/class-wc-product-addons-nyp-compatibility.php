<?php
/**
 * WC_Product_Addons_NYP_Compatibility class
 *
 * @package  WooCommerce Product Add-Ons
 * @since    7.0.3
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Name Your Price Compatibility.
 *
 * @version 7.0.3
 */
class WC_Product_Addons_NYP_Compatibility {

	/**
	 * Init.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'woocommerce_product_addons_get_order_item_price', array( __CLASS__, 'get_line_item_price' ), 10, 2 );
	}

	/**
	 * Get line item price.
	 *
	 * @param string $price Default line item price.
	 * @param array  $values Order item values.
	 * @return string
	 */
	public static function get_line_item_price( $price, $values ) {
		if ( ! empty( $values['nyp'] ) ) {
			return strval( $values['nyp'] );
		}
		return $price;
	}
}

WC_Product_Addons_NYP_Compatibility::init();
