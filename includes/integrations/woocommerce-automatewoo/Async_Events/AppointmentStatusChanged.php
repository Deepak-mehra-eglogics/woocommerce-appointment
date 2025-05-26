<?php

namespace AutomateWoo\Async_Events;

use AutomateWoo\ActionScheduler\ActionSchedulerInterface;
use AutomateWoo\Proxies\Appointments;

defined( 'ABSPATH' ) || exit;

/**
 * @class AppointmentStatusChanged
 *
 * @since 4.18.1
 */
class AppointmentStatusChanged extends Abstract_Async_Event {

	const NAME = 'appointment_status_changed';

	/**
	 * @var Appointments
	 */
	protected $appointments_proxy;

	/**
	 * AppointmentStatusChanged constructor.
	 *
	 * @since 6.0.18
	 *
	 * @param ActionSchedulerInterface $action_scheduler
	 */
	public function __construct( ActionSchedulerInterface $action_scheduler ) {
		$this->appointments_proxy = new Appointments();
		parent::__construct( $action_scheduler );
	}

	/**
	 * Init the event.
	 */
	public function init() {
		add_action( 'woocommerce_appointment_status_changed', [ $this, 'schedule_event' ], 30, 3 );
	}

	/**
	 * Schedule appointments status change event for consumption by triggers.
	 *
	 * Doesn't dispatch for 'was-in-cart' status changes because this status isn't a real appointment status and essentially
	 * functions as a 'trash' status. The was in cart is used when a appointment cart item is removed from the cart.
	 *
	 * @param string $from       Previous status.
	 * @param string $to         New (current) status.
	 * @param int    $appointment_id Appointment id.
	 */
	public function schedule_event( string $from, string $to, int $appointment_id ) {
		$was_in_cart = 'was-in-cart';
		if ( $to === $was_in_cart || $from === $was_in_cart ) {
			// Don't dispatch an event for 'was-in-cart' status changes
			return;
		}

		$appointment = $this->appointments_proxy->get_appointment( $appointment_id );

		// When the the user is a guest and adds the appointment to the cart, the appointment is not associated with an order yet neither a customer.
		// So runnning this workflow for this appointment will only log errors as the data layer needs a customer to run the workflow.
		// See AppointmentDataLayer::generate_appointment_data_layer
		if ( ( ! $appointment->get_order() || ! is_a( $appointment->get_order(), 'WC_Order' ) ) && ! $appointment->get_customer_id() && $to === 'in-cart' ) {
			return;
		}

		$this->create_async_event(
			[
				$appointment_id,
				$from,
				$to,
			]
		);
	}
}
