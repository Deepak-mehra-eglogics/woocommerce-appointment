<?php

namespace AutomateWoo\Variables;

use AutomateWoo\Variable;
use AutomateWoo\Variable_Abstract_Price;
use WC_Appointment;

defined( 'ABSPATH' ) || exit;

/**
 * Class AppointmentCost
 *
 * @since 4.18.1
 */
class AppointmentCost extends Variable_Abstract_Price {

	/**
	 * Load admin details.
	 */
	public function load_admin_details() {
		parent::load_admin_details();
		$this->description = __( 'Displays the cost of the appointment.', 'woocommerce-appointments' );
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
		return parent::format_amount( $appointment->get_cost(), $parameters );
	}
}
