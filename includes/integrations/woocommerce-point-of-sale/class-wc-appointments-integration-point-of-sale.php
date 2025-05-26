<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Files.FileName

/**
 * WooCommerce Point of Sale integration class.
 * https://codecanyon.net/item/woocommerce-point-of-sale-pos/7869665
 *
 * Last compatibility check: WooCommerce Point of Sale 4.4.33
 */
class WC_Appointments_Integration_POS {

    /**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'woocommerce_order_status_completed', array( $this, 'publish_appointments' ), 10, 1 );

	}

	/**
	 * Called when an order is paid
	 * @param  int $order_id
	 */
	public function publish_appointments( $order_id ) {
		$order          = wc_get_order( $order_id );
		$payment_method = $order ? $order->get_payment_method() : null;

		if ( class_exists( 'WC_Deposits' ) ) {
			// Is this a final payment?
			$parent_id = wp_get_post_parent_id( $order_id );
			if ( ! empty( $parent_id ) ) {
				$order_id = $parent_id;
			}
		}

		$appointments = WC_Appointment_Data_Store::get_appointment_ids_from_order_id( $order_id );

		// Don't publish appointments for COD orders, but still schedule their events
		$amount_change = get_post_meta( $order_id, 'wc_pos_order_type', true );
		$is_pos        = $order->has_status( 'processing' ) && 'cod' === $payment_method && 'POS' === $amount_change;

		foreach ( $appointments as $appointment_id ) {
			$appointment = get_wc_appointment( $appointment_id );

			if ( $is_pos ) {
				$appointment->paid();
			}
		}
	}

}

$GLOBALS['wc_appointments_integration_pos'] = new WC_Appointments_Integration_POS();
