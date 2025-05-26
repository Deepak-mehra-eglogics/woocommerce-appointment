<?php

namespace AutomateWoo\Variables;

use AutomateWoo\Variable_Abstract_Datetime;
use WC_Appointment;

defined( 'ABSPATH' ) || exit;

/**
 * Class AppointmentEndDate
 *
 * @since 4.18.1
 */
class AppointmentEndDate extends Variable_Abstract_Datetime {

	/**
	 * Load variable admin details.
	 */
	public function load_admin_details() {
		$this->description = __( "Displays the appointment end date in your website's timezone.", 'woocommerce-appointments' );
		parent::load_admin_details();
	}

	/**
	 * Get the variable value.
	 *
	 * @param WC_Appointment $appointment
	 * @param array      $parameters
	 *
	 * @return string
	 */
	public function get_value( $appointment, $parameters ) {
		return $this->format_datetime( $appointment->get_end( 'view', true ), $parameters );
	}
}
