<?php

namespace AutomateWoo\Variables;

use AutomateWoo\DateTime;
use WC_Appointment;

defined( 'ABSPATH' ) || exit;

/**
 * Class AppointmentStartTime
 *
 * @since 4.18.1
 */
class AppointmentStartTime extends AbstractAppointmentTime {

	/**
	 * Load variable admin details.
	 */
	public function load_admin_details() {
		$this->description = __( "Displays the appointment start time in your website's timezone. Nothing will be displayed for all-day appointments.", 'woocommerce-appointments' );
	}

	/**
	 * Get the target appointment datetime value for the variable.
	 *
	 * @param WC_Appointment $appointment
	 *
	 * @return DateTime|null The variable's target datetime value in the site's local timezone.
	 */
	protected function get_target_datetime_value( WC_Appointment $appointment ) {
		$datetime = aw_normalize_date( $appointment->get_start( 'view', true ) );
		return $datetime ? $datetime : null;
	}
}
