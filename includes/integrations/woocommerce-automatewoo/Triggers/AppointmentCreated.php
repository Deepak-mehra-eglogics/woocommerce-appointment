<?php

namespace AutomateWoo\Triggers;

use AutomateWoo\Trigger;
use AutomateWoo\Async_Events;
use AutomateWoo\Logger;
use AutomateWoo\Proxies\Appointments;
use AutomateWoo\Proxies\AppointmentsInterface;
use AutomateWoo\Triggers\Utilities\AppointmentsGroup;
use AutomateWoo\Async_Events\AppointmentCreated as AppointmentCreatedEvent;
use AutomateWoo\Triggers\Utilities\AppointmentDataLayer;

/**
 * @class AppointmentCreated
 *
 * @since 4.18.1
 */
class AppointmentCreated extends Trigger {

	use AppointmentsGroup;
	use AppointmentDataLayer;

	/**
	 * @var AppointmentsInterface Proxy for functionality from WooCommerce Appointments extension.
	 */
	protected $appointments_proxy;

	/**
	 * Async events required by the trigger.
	 *
	 * @var array|string
	 */
	protected $required_async_events = AppointmentCreatedEvent::NAME;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->supplied_data_items = $this->get_supplied_data_items_for_appointment();

		parent::__construct();

		$this->appointments_proxy = new Appointments();
	}

	/**
	 * Declare our UI metadata.
	 */
	public function load_admin_details() {
		$this->title       = __( 'Appointment Created', 'woocommerce-appointments' );
		$this->description = __(
			'This trigger fires when a new appointment is created. This includes appointments initiated by shoppers on store front end and manually created by admin users. This trigger doesn\'t fire for "in-cart" appointments and a valid customer is needed to trigger this job.',
			'woocommerce-appointments'
		);
	}

	/**
	 * Register handlers to drive triggers from internal AW async event hook.
	 */
	public function register_hooks() {
		$async_event = Async_Events::get( AppointmentCreatedEvent::NAME );
		if ( $async_event ) {
			add_action( $async_event->get_hook_name(), [ $this, 'handle_appointment_created' ], 10, 1 );
		}
	}

	/**
	 * Handle the appointment created event.
	 *
	 * @param int $appointment_id
	 */
	public function handle_appointment_created( int $appointment_id ) {
		try {
			$appointment = $this->appointments_proxy->get_appointment( $appointment_id );
			$data_layer  = $this->generate_appointment_data_layer( $appointment );
		} catch ( \Exception $e ) {
			Logger::notice( 'appointments', $e->getMessage() );
			return;
		}

		$this->maybe_run( $data_layer );
	}
}
