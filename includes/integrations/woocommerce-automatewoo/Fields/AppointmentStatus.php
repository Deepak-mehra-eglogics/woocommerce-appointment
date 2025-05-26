<?php

namespace AutomateWoo\Fields;

use AutomateWoo\Proxies\Appointments;

/**
 * @class AppointmentStatus
 */
class AppointmentStatus extends Select {

	/**
	 * @var Appointments Proxy for functionality from WooCommerce Appointments extension.
	 */
	protected $appointments_proxy;

	/**
	 * @var $name Field type name.
	 */
	protected $name = 'appointment_status';

	/**
	 * @param bool $allow_all
	 */
	public function __construct( $allow_all = true ) {
		parent::__construct( true );

		$this->set_title( __( 'Appointment status', 'woocommerce-appointments' ) );

		if ( $allow_all ) {
			$this->set_placeholder( __( '[Any]', 'woocommerce-appointments' ) );
		}

		$this->appointments_proxy = new Appointments();

		$this->set_options( $this->appointments_proxy->get_appointment_statuses() );
	}
}
