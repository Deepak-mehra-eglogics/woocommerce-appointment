<?php

namespace AutomateWoo\Variables;

use AutomateWoo\Variable;
use WC_Appointment;

defined( 'ABSPATH' ) || exit;

/**
 * Class AppointmentId
 *
 * @since 4.18.1
 */
class AppointmentId extends Variable {

	/**
	 * Load admin details.
	 */
	public function load_admin_details() {
		$this->description = __( "Displays the appointment's unique ID.", 'woocommerce-appointments' );
	}

	/**
	 * Get variable value.
	 *
	 * @param WC_Appointment $appointment
	 * @param array      $parameters
	 *
	 * @return string
	 */
	public function get_value( $appointment, $parameters ) {
		return (string) $appointment->get_id();
	}
}
