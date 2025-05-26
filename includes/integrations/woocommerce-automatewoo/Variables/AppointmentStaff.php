<?php

namespace AutomateWoo\Variables;

use AutomateWoo\Variable;
use WC_Appointment;

defined( 'ABSPATH' ) || exit;

/**
 * Class AppointmentResource
 *
 * @since 4.18.1
 */
class AppointmentStaff extends Variable {

	/**
	 * Load admin details.
	 */
	public function load_admin_details() {
		$this->description = __( 'Displays any staff included in the appointment.', 'woocommerce-appointments' );
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
		$staff = $appointment->get_staff_members( true );
		if ( ! $staff ) {
			return '';
		}

		return $staff;
	}
}
