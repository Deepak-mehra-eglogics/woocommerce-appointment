<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce Box Office integration class.
 *
 * Last compatibility check: 1.1.29
 */
class WC_Appointments_Integration_WC_Box_Office {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_filter( 'product_type_options', array( $this, 'appointments_product_type_options' ) );
	}

	/**
	 * Show ticket fields for appointable product.
	 *
	 * @param array $options Classes for the product type.
	 *
	 * @return array Classes for the product type.
	 */
	public function appointments_product_type_options( $options ) {
		if ( isset( $options['ticket']['wrapper_class'] ) ) {
			$options['ticket']['wrapper_class'] = $options['ticket']['wrapper_class'] . ' show_if_appointment';
		}

		return $options;
	}

}

$GLOBALS['wc_appointments_integration_wc_box_office'] = new WC_Appointments_Integration_WC_Box_Office();
