<?php
if ( ! class_exists( 'WC_Abstract_Privacy' ) ) {
	return;
}

class WC_Appointment_Privacy extends WC_Abstract_Privacy {
	/**
	 * Constructor
	 *
	 */
	public function __construct() {
		parent::__construct( __( 'Appointments', 'woocommerce-appointments' ) );

		$this->add_exporter( 'woocommerce-appointments-data', __( 'WooCommerce Appointments Data', 'woocommerce-appointments' ), array( $this, 'appointments_data_exporter' ) );
		$this->add_eraser( 'woocommerce-appointments-data', __( 'WooCommerce Appointments Data', 'woocommerce-appointments' ), array( $this, 'appointments_data_eraser' ) );
	}

	/**
	 * Returns a list of Appointments for the user.
	 *
	 * @param string  $email_address
	 * @param int     $page
	 *
	 * @return array WC_Appointment
	 */
	protected function get_appointments( $email_address, $page ) {
		$user = get_user_by( 'email', $email_address ); // Check if user has an ID in the DB to load stored personal data.

		if ( ! $user instanceof WP_User ) {
			return [];
		}

		return WC_Appointment_Data_Store::get_appointments_for_user( $user->ID );
	}

	/**
	 * Gets the message of the privacy to display.
	 *
	 */
	public function get_privacy_message() {
		return wpautop( __( 'By using this extension, you may be storing personal data or sharing data with an external service.', 'woocommerce-appointments' ) );
	}

	/**
	 * Handle exporting data for Appointments.
	 *
	 * @param string $email_address E-mail address to export.
	 * @param int    $page          Pagination of data.
	 *
	 * @return array
	 */
	public function appointments_data_exporter( $email_address, $page = 1 ) {
		$done           = false;
		$data_to_export = [];

		$appointments = $this->get_appointments( $email_address, (int) $page );

		$done = true;

		if ( 0 < count( $appointments ) ) {
			foreach ( $appointments as $appointment ) {
				$data_to_export[] = array(
					'group_id'    => 'woocommerce_appointments',
					'group_label' => __( 'Appointments', 'woocommerce-appointments' ),
					'item_id'     => 'appointment-' . $appointment->get_id(),
					'data'        => array(
						array(
							'name'  => __( 'Appointment Number', 'woocommerce-appointments' ),
							'value' => $appointment->get_id(),
						),
						array(
							'name'  => __( 'Appointment start', 'woocommerce-appointments' ),
							'value' => date_i18n( 'Y-m-d H:i:s', $appointment->get_start() ),
						),
						array(
							'name'  => __( 'Appointment end', 'woocommerce-appointments' ),
							'value' => date_i18n( 'Y-m-d H:i:s', $appointment->get_end() ),
						),
						array(
							'name'  => __( 'Appointable product', 'woocommerce-appointments' ),
							'value' => $appointment->get_product() ? $appointment->get_product()->get_name() : '',
						),
						array(
							'name'  => __( 'Scheduled order ID', 'woocommerce-appointments' ),
							'value' => $appointment->get_order_id(),
						),
					),
				);
			}

			$done = 10 > count( $appointments );
		}

		return array(
			'data' => $data_to_export,
			'done' => $done,
		);
	}

	/**
	 * Finds and erases Appointments data by email address.
	 *
	 * @since 3.4.0
	 * @param string $email_address The user email address.
	 * @param int    $page  Page.
	 * @return array An array of personal data in name value pairs
	 */
	public function appointments_data_eraser( $email_address, $page ) {
		$appointments = $this->get_appointments( $email_address, 1 );

		$items_removed  = false;
		$items_retained = false;
		$messages       = [];

		foreach ( (array) $appointments as $appointment ) {
			list( $removed, $retained, $msgs ) = $this->maybe_handle_appointment( $appointment );
			$items_removed                    |= $removed;
			$items_retained                   |= $retained;
			$messages                          = array_merge( $messages, $msgs );
		}

		// Tell core if we have more Appointments to work on still
		$done = count( $appointments ) < 10;

		return array(
			'items_removed'  => $items_removed,
			'items_retained' => $items_retained,
			'messages'       => $messages,
			'done'           => $done,
		);
	}

	/**
	 * Handle eraser of data tied to Appointments
	 *
	 * @param WC_Appointment $appointment
	 * @return array
	 */
	protected function maybe_handle_appointment( $appointment ) {
		$appointment->get_data_store()->delete( $appointment, array( 'force_delete' => true ) );
		return array( true, false, array( __( 'WooCommerce Appointments Data Erased.', 'woocommerce-appointments' ) ) );
	}
}

new WC_Appointment_Privacy();
