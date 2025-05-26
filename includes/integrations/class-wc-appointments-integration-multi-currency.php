<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce Multi Currency integration class.
 * https://codecanyon.net/item/woocommerce-multi-currency/20948446
 *
 * Last compatibility check: 2.1.10.2
 *
 * @since 4.10.9
 */
class WC_Appointments_Integration_Multi_Currency {


	/**
	 * Constructor
	 *
	 * @since 4.10.9
	 */
	public function __construct() {
		add_filter( 'appointment_form_calculated_appointment_cost', array( $this, 'adjust_appointment_cost' ), 10, 3 );
	}

	/**
	 * Adjust appointment cost
	 *
	 * @since 3.2.0
	 * @param float $cost
	 * @param \WC_Appointment_Form $form
	 * @param array $posted
	 * @return float
	 */
	public function adjust_appointment_cost( $appointment_cost, $product, $posted ) {
		$product_cost          = (float) $product->get_price();
	    $original_product_cost = 0 >= $product_cost ? 0 : $product_cost;
	    $setting               = WOOMULTI_CURRENCY_Data::get_ins();
	    $selected_currencies   = $setting->get_list_currencies();
	    $current_currency      = $setting->get_current_currency();

	    // Return appointment cost, when no currency manipulation.
	    if ( ! $current_currency ) {
	        return $appointment_cost;
	    }

	    // Product price with no currency changes.
	    if ( $product_cost ) {
	        $original_product_cost = (float) ( $product_cost / $selected_currencies[ $current_currency ]['rate'] );
	    }

	    // Extras price with no currency.
	    $original_extra_cost = $appointment_cost - $product_cost;

	    // Make sure extras are present.
	    if ( ! $original_extra_cost ) {
	        $original_extra_cost = 0;
	    }
	    // Appointment cost with no currency.
	    $original_appointment_cost = $original_product_cost + $original_extra_cost;

	    // Appointment cost with currency applied.
	    $currency_appointment_cost = wmc_get_price( $original_appointment_cost );

	    // Make sure appointment cost cannot be negative with applied currency.
	    if ( 0 > $currency_appointment_cost ) {
	        $currency_appointment_cost = 0;
	    }

	    // Don't discount the price when adding an appointment to the cart.
	    if ( doing_action( 'woocommerce_add_cart_item_data' ) ) {
	        $currency_appointment_cost = $original_appointment_cost;
	    }

	    // Make sure price is numeric.
	    $currency_appointment_cost = is_numeric( $currency_appointment_cost ) ? $currency_appointment_cost : 0;

	    return (float) $currency_appointment_cost;
	}

}

$GLOBALS['wc_appointments_integration_multi_currency'] = new WC_Appointments_Integration_Multi_Currency();
