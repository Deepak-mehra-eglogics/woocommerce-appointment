<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce Price Based on Country integration class.
 * https://wordpress.org/plugins/woocommerce-product-price-based-on-countries/
 *
 * Last compatibility check: 3.4.5
 */
class WC_Appointments_Integration_WCPBC {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_filter( 'wc_price_based_country_third_party_product_types', array( $this, 'third_party_product_types' ) );
		add_filter( 'wc_price_based_country_product_types_overriden', array( $this, 'product_types_overriden' ) );
		add_action( 'woocommerce_process_product_meta_appointment', array( $this, 'process_product_meta' ) );
 	}

 	/**
 	 * OPC Single-product appointments add-to-cart template
 	 *
 	 * @param  array  $types
 	 * @return void
 	 */
	public function third_party_product_types( $types ) {
	    $types['appointment'] = __( 'Appointable product', 'woocommerce-appointments' );

	    return $types;
	}

	/**
 	 * OPC Single-product appointments add-to-cart template
 	 *
 	 * @param  array  $types
 	 * @return void
 	 */
	public function product_types_overriden( $types ) {
	    $types[] = 'appointment';

	    return $types;
	}

	/**
	 * Save product metadata
	 *
	 * @param int $post_id Post ID.
	 * @param int $index Index of variations to save.
	 */
	public static function process_product_meta( $post_id, $index = false ) {
		$fields = array( '_price_method', '_regular_price', '_sale_price', '_sale_price_dates', '_sale_price_dates_from', '_sale_price_dates_to' );
		foreach ( WCPBC_Pricing_Zones::get_zones() as $zone ) {
			$data = [];
			foreach ( $fields as $field ) {
				$var_name       = false !== $index ? '_variable' . $field : $field;
				$data[ $field ] = $zone->get_input_var( $var_name, $index );
			}

			// Save metadata.
			wcpbc_update_product_pricing( $post_id, $zone, $data );
		}
	}
}

$GLOBALS['wc_appointments_integration_wcpbc'] = new WC_Appointments_Integration_WCPBC();
