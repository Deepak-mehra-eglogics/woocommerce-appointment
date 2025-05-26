<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * WC_Appointment_Checkout_Manager class.
 */
class WC_Appointment_Checkout_Manager {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_filter( 'woocommerce_available_payment_gateways', [ $this, 'remove_payment_methods' ] );
		add_filter( 'woocommerce_cart_needs_payment', [ $this, 'appointment_requires_confirmation' ], 10, 2 );
		add_filter( 'woocommerce_order_needs_payment', [ $this, 'appointment_order_requires_confirmation' ], 10, 3 );
	}

	/**
	 * Removes all payment methods when cart has an appointment that requires confirmation.
	 *
	 * @param  array $available_gateways
	 * @return array
	 */
	public function remove_payment_methods( $available_gateways ) {
		if ( wc_appointment_cart_requires_confirmation() ) {
			unset( $available_gateways );

			$available_gateways                          = [];
			$available_gateways['wcappointmentsgateway'] = new WC_Appointments_Gateway();
		}

		return $available_gateways;
	}

	/**
	 * Always require payment if the order have an appointment that requires confirmation.
	 *
	 * @param  bool $needs_payment
	 * @param  WC_Cart $cart
	 *
	 * @return bool
	 */
	public function appointment_requires_confirmation( $needs_payment, $cart ) {
		if ( ! $needs_payment ) {
			foreach ( $cart->cart_contents as $cart_item ) {
				if ( wc_appointment_requires_confirmation( $cart_item['product_id'] ) ) {
					$needs_payment = true;
					break;
				}
			}
		}

		return $needs_payment;
	}

	/**
	 * If a appointable product is in the order and that appointable product requires confirmation, the order
	 * "requires payment" regardless of the order total.
	 * @param  bool $needs_payment the WooCommerce value passed to the 'woocommerce_order_needs_payment' filter
	 * @param  object $order the WooCommerce order
	 * @return bool  the filtered value of $needs_payment
	 */
	public function appointment_order_requires_confirmation( $needs_payment, $order, $valid_order_statuses ) {
		if ( ! $needs_payment && $order->has_status( $valid_order_statuses ) ) {
			$order_items = $order->get_items();
			foreach ( $order_items as $order_item ) {
				if ( wc_appointment_requires_confirmation( $order_item['product_id'] ) ) {
					$needs_payment = true;
					break;
				}
			}
		}

		return $needs_payment;
	}
}

$GLOBALS['wc_appointment_checkout_manager'] = new WC_Appointment_Checkout_Manager();
