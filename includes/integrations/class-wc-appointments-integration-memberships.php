<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce Memberships integration class.
 *
 * Last compatibility check: 1.15.2
 *
 * @since 3.2.0
 */
class WC_Appointments_Integration_Memberships {


	/**
	 * Constructor
	 *
	 * @since 3.2.0
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
		$product_cost     = (float) $product->get_price();
		$user_id          = isset( $posted['customer_id'] ) ? absint( $posted['customer_id'] ) : get_current_user_id();
		$member_discounts = wc_memberships()->get_member_discounts_instance();

        // Product price with no discounts.
        $original_product_cost = 0 >= $product_cost ? 0 : $member_discounts->get_original_price( $product_cost, $product );
		// Extras price with no discounts.
		$original_extra_cost = $appointment_cost - $product_cost;
		// Make sure extras are present.
		if ( ! $original_extra_cost ) {
            $original_extra_cost = 0;
        }
		// Appointment cost with no discounts.
		$original_appointment_cost = $original_product_cost + $original_extra_cost;

		// Appointment cost with discounts applied.
		$member_appointment_cost = $this->get_member_price( $original_appointment_cost, $product, $member_discounts, $user_id );
		// Make sure appointment cost cannot be negative with applied discounts.
		if ( 0 > $member_appointment_cost ) {
            $member_appointment_cost = 0;
        }

		#print '<pre>'; print_r( 0 >= $product_cost ); print '</pre>';
		#print '<pre>'; print_r( (float) $original_product_cost ); print '</pre>';
		#print '<pre>'; print_r( (float) $original_extra_cost ); print '</pre>';
		#print '<pre>'; print_r( (float) $original_appointment_cost ); print '</pre>';
		#print '<pre>'; print_r( (float) $member_appointment_cost ); print '</pre>';

		// Don't discount the price when adding an appointment to the cart.
		if ( doing_action( 'woocommerce_add_cart_item_data' ) ) {
            $member_appointment_cost = $member_discounts->get_original_price( $member_appointment_cost, $product );
        } elseif ( is_admin() && isset( $posted['add_appointment_2'] ) ) {
			$member_appointment_cost = $this->get_member_price( $appointment_cost, $product, $member_discounts, $user_id );
		}

		// Make sure price is numeric.
		$member_appointment_cost = is_numeric( $member_appointment_cost ) ? $member_appointment_cost : 0;

		#error_log( var_export( $member_appointment_cost, true ) );

		return (float) $member_appointment_cost;
	}

	/**
	 * Applies purchasing discounts to a product price.
	 *
	 * @since 3.7.0
	 *
	 * @param string|int|float $price price to discount (normally a float, maybe a string number)
	 * @param \WC_Product $product the product object
	 * @param \WC_Memberships_Member_Discounts $instance
	 * @param int $user this is user ID
	 * @return float price
	 */
	public function get_member_price( $price, $product, $instance, $user ) {
		// Bail out if any of the following is true:
		// - product is excluded from member discounts
		// - user has no member discount over the product
		if ( ! $instance->is_product_excluded_from_member_discounts( $product )
		    && $instance->user_has_member_discount( $product, $user ) ) {

			$member_price = $instance->get_discounted_price( $price, $product, $user );

			$price = is_numeric( $member_price ) ? $member_price : $price;
		}

		return $price;
	}

}

$GLOBALS['wc_appointments_integration_memberships'] = new WC_Appointments_Integration_Memberships();
