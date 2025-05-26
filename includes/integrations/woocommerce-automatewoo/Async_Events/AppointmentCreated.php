<?php

namespace AutomateWoo\Async_Events;

use AutomateWoo\ActionScheduler\ActionSchedulerInterface;
use AutomateWoo\Exceptions\Exception as ExceptionInterface;
use AutomateWoo\Logger;
use AutomateWoo\Proxies\Appointments;
use WC_Appointment;

defined( 'ABSPATH' ) || exit;

/**
 * @class AppointmentCreated
 *
 * @since   4.18.1
 */
class AppointmentCreated extends Abstract_Async_Event {

	const NAME = 'appointment_created';

	/**
	 * @var Appointments
	 */
	protected $appointments_proxy;

	/**
	 * AppointmentCreated constructor.
	 *
	 * @since 5.4.0
	 *
	 * @param ActionSchedulerInterface $action_scheduler
	 * @param Appointments             $appointments_proxy
	 */
	public function __construct( ActionSchedulerInterface $action_scheduler ) {
		$this->appointments_proxy = new Appointments();

		parent::__construct( $action_scheduler );
	}

	/**
	 * Init the event.
	 */
	public function init() {
		add_action( 'woocommerce_new_appointment', [ $this, 'handle_new_appointment' ], 30, 1 );
		add_action( 'woocommerce_appointment_status_changed', [ $this, 'handle_appointment_status_changed' ], 30, 3 );
		add_action( $this->get_interim_hook_name(), [ $this, 'handle_interim_appointment_created_event' ] );
	}

	/**
	 * Dispatch async event for consumption by triggers.
	 *
	 * @since 5.4.0
	 *
	 * @param int $appointment_id Appointment id.
	 */
	public function handle_new_appointment( int $appointment_id ) {
		try {
			$appointment = $this->appointments_proxy->get_appointment( $appointment_id );

			if ( in_array( $appointment->get_status(), $this->appointments_proxy->get_draft_appointment_statuses(), true ) ) {
				// Appointment is not considered created yet
				return;
			}

			// When a appointment is created without an order and customer, it's not possible to run the workflow,
			// it  will only log errors as the data layer needs a customer to run the workflow.
			// See AppointmentDataLayer::generate_appointment_data_layer
			if ( ( ! $appointment->get_order() || ! is_a( $appointment->get_order(), 'WC_Order' ) ) && ! $appointment->get_customer_id() && $appointment->get_status() === 'confirmed' ) {
				return;
			}

			$this->dispatch_interim_appointment_created_event( $appointment_id );
		} catch ( ExceptionInterface $e ) {
			Logger::notice( 'appointments', $e->getMessage() );
		}
	}

	/**
	 * Listens for when a appointment status transitions from a "draft" type to a "non-draft" type.
	 *
	 * @since 5.4.0
	 *
	 * @param string $old_status
	 * @param string $new_status
	 * @param int    $appointment_id Appointment id.
	 */
	public function handle_appointment_status_changed( string $old_status, string $new_status, int $appointment_id ) {
		if (
			in_array( $old_status, $this->appointments_proxy->get_draft_appointment_statuses(), true ) &&
			! in_array( $new_status, $this->appointments_proxy->get_draft_appointment_statuses(), true )
		) {
			// Only consider the appointment to be "created" if it transitions from "draft" to a "non-draft" status.
			$this->dispatch_interim_appointment_created_event( $appointment_id );
		}
	}

	/**
	 * Dispatch an interim scheduled action to ensure we don't interfere with the initial appointment status change and
	 * creation hooks.
	 *
	 * Calling ::save() on a appointment object during a complex appointment life-cycle event could cause unintended side-effects.
	 *
	 * @since 5.4.0
	 *
	 * @param int $appointment_id
	 */
	protected function dispatch_interim_appointment_created_event( int $appointment_id ) {
		$this->action_scheduler->enqueue_async_action( $this->get_interim_hook_name(), [ $appointment_id ] );
	}

	/**
	 * Get the interim async event hook name.
	 *
	 * @see AppointmentCreated::dispatch_interim_appointment_created_event()
	 *
	 * @since 5.4.0
	 *
	 * @return string
	 */
	protected function get_interim_hook_name(): string {
		return "{$this->get_hook_name()}/interim";
	}

	/**
	 * Handle the interim appointment created hook.
	 *
	 * @since 5.4.0
	 *
	 * @param int $appointment_id
	 */
	public function handle_interim_appointment_created_event( int $appointment_id ) {
		try {
			$appointment = $this->appointments_proxy->get_appointment( $appointment_id );
			$this->dispatch_final_appointment_created_event( $appointment );
		} catch ( ExceptionInterface $e ) {
			Logger::notice( 'appointments', $e->getMessage() );
		}
	}

	/**
	 * Dispatch the final appointment created event but only allow one to fire per appointment.
	 *
	 * @param WC_Appointment $appointment
	 */
	protected function dispatch_final_appointment_created_event( WC_Appointment $appointment ) {
		// Use a meta check to prevent duplicates
		$meta_key = '_automatewoo_is_created';
		if ( $appointment->get_meta( $meta_key ) ) {
			return;
		}

		$appointment->update_meta_data( $meta_key, true );
		$appointment->save();

		// Dispatch actual async hook
		do_action( $this->get_hook_name(), $appointment->get_id() );
	}
}
