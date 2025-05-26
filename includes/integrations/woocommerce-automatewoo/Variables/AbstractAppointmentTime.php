<?php

namespace AutomateWoo\Variables;

use AutomateWoo\DateTime;
use WC_Appointment;

defined( 'ABSPATH' ) || exit;

/**
 * Class AbstractAppointmentTime
 *
 * @since 4.19.0
 */
abstract class AbstractAppointmentTime extends AbstractTime {

	/**
	 * Get the target appointment datetime value for the variable.
	 *
	 * @param WC_Appointment $appointment
	 *
	 * @return DateTime|null The variable's target datetime value in the site's local timezone.
	 */
	abstract protected function get_target_datetime_value( WC_Appointment $appointment );

	/**
	 * Get the variable value.
	 *
	 * If appointment is "all-day" no time will be returned.
	 *
	 * @param WC_Appointment $appointment
	 * @param array      $parameters
	 *
	 * @return string
	 */
	public function get_value( $appointment, $parameters ) {
		if ( $appointment->is_all_day() ) {
			// All-day appointments have no time.
			// Returning '' here lets users use the 'fallback' parameter for all-day appointments.
			return '';
		}

		$datetime = $this->get_target_datetime_value( $appointment );
		if ( ! $datetime ) {
			return '';
		}

		return $this->format_value_from_local_tz( $datetime );
	}
}
