<?php

namespace AutomateWoo\Proxies;

use AutomateWoo\Exceptions\InvalidIntegration;
use AutomateWoo\Exceptions\InvalidValue;
use WC_Appointment;

/**
 * Proxy for the WooCommerce appointments extension.
 *
 * @since 4.18.1
 */
interface AppointmentsInterface {

	/**
	 * Get a appointment by ID.
	 *
	 * @param int $id
	 *
	 * @return WC_Appointment
	 *
	 * @throws InvalidValue If appointment not found.
	 * @throws InvalidIntegration If appointments plugin not active.
	 */
	public function get_appointment( int $id ): WC_Appointment;

	/**
	 * Return a list of supported appointment status values & labels.
	 *
	 * @return Array Array of valid status values, in slug => label form.
	 */
	public function get_appointment_statuses(): array;
}
