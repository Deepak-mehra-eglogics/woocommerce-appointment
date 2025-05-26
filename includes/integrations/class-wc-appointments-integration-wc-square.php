<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce Payments integration class.
 *
 * Last compatibility check: 2.7.0
 */
class WC_Appointments_Integration_WC_Square {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_filter( 'wc_square_digital_wallets_supported_product_types', [ $this, 'appointments_add_to_supported_types' ] );
	}

	/**
	 * Add 'appointment' product type to the array.
	 *
	 * @param array $product_types Supported product types.
	 *
	 * @return array Supported product types.
	 */
	public function appointments_add_to_supported_types( $product_types ) {
		$product_types[] = 'appointment';

		return $product_types;
	}

}

new WC_Appointments_Integration_WC_Square();
