<?php

namespace AutomateWoo\Triggers\Utilities;

/**
 * Trait AppointmentsGroup
 *
 * Declare trigger as belonging to Appointments group.
 *
 * @since 4.18.1
 */
trait AppointmentsGroup {

	/**
	 * @return string
	 */
	public function get_group() {
		return __( 'Appointments', 'woocommerce-appointments' );
	}
}
