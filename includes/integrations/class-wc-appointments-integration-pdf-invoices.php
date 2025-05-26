<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce PDF Invoices & Packing Slips integration class.
 * Last compatibility check: v2.8.2
 *
 * @since 4.11.4
 */
class WC_Appointments_Integration_PDF_Invoices {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'wpo_wcpdf_before_item_meta', array( $this, 'appointment_invoice_display' ), 10, 3 );
	}

	/**
	 * Show appointment data for various Invoice plugin integrations.
	 */
	public function appointment_invoice_display( $type, $item, $order ) {
		$appointment_ids = WC_Appointment_Data_Store::get_appointment_ids_from_order_item_id( $item['item_id'] );

		if ( $appointment_ids ) {
			foreach ( $appointment_ids as $appointment_id ) {
				$appointment = get_wc_appointment( $appointment_id );
				wc_appointments_get_summary_list( $appointment );
			}
		}
	}

}

$GLOBALS['wc_appointments_integration_pdf_invoices'] = new WC_Appointments_Integration_PDF_Invoices();
