<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce Print Invoices/Packing Lists integration class.
 */
class WC_Appointments_Integration_Invoices {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'wc_pip_order_item_meta_end', array( $this, 'appointment_invoice_display' ), 10, 3 ); #WooCommerce Print Invoices/Packing Lists.
	}

	/**
	 * Show appointment data for various Invoice plugin integrations.
	 */
	public function appointment_invoice_display( $item_id, $item, $order ) {
		$appointment_ids = WC_Appointment_Data_Store::get_appointment_ids_from_order_item_id( $item_id );

		if ( $appointment_ids ) {
			foreach ( $appointment_ids as $appointment_id ) {
				$appointment = get_wc_appointment( $appointment_id );
				wc_appointments_get_summary_list( $appointment );
			}
		}
	}

}

$GLOBALS['wc_appointments_integration_invoices'] = new WC_Appointments_Integration_Invoices();
