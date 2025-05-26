<?php

namespace AutomateWoo\Triggers;

use AutomateWoo\Trigger;
use AutomateWoo\Async_Events;
use AutomateWoo\Logger;
use AutomateWoo\Temporary_Data;
use AutomateWoo\Fields\AppointmentStatus;
use AutomateWoo\Proxies\Appointments;
use AutomateWoo\Proxies\AppointmentsInterface;
use AutomateWoo\Triggers\Utilities\AppointmentsGroup;
use AutomateWoo\Async_Events\AppointmentStatusChanged as AppointmentStatusChangedEvent;
use AutomateWoo\Triggers\Utilities\AppointmentDataLayer;

/**
 * @class AppointmentStatusChanged
 *
 * @since 4.18.1
 */
class AppointmentStatusChanged extends Trigger {

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
	protected $required_async_events = AppointmentStatusChangedEvent::NAME;

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
		$this->title       = __( 'Appointment Status Changed', 'woocommerce-appointments' );
		$this->description = __(
			'This trigger fires when a appointment status changes. Notice a valid customer is needed to trigger this job.',
			'woocommerce-appointments'
		);
	}

	/**
	 * Declare our trigger options.
	 */
	public function load_fields() {
		$from = ( new AppointmentStatus() )
			->set_title( __( 'Status changes from', 'woocommerce-appointments' ) )
			->set_name( 'appointment_status_from' )
			->set_description( __( 'Select valid previous appointment status values to trigger this workflow. Leave blank to allow any previous status. ', 'woocommerce-appointments' ) )
			->set_multiple();
		$this->add_field( $from );

		$to = ( new AppointmentStatus() )
			->set_title( __( 'Status changes to', 'woocommerce-appointments' ) )
			->set_name( 'appointment_status_to' )
			->set_description( __( 'Select which appointment status values will trigger this workflow. Leave blank to allow all.', 'woocommerce-appointments' ) )
			->set_multiple();
		$this->add_field( $to );

		$this->add_field_validate_queued_order_status();
	}

	/**
	 * Register handlers to drive triggers from internal AW async event hook.
	 */
	public function register_hooks() {
		$async_event = Async_Events::get( AppointmentStatusChangedEvent::NAME );
		if ( $async_event ) {
			add_action( $async_event->get_hook_name(), [ $this, 'handle_status_changed' ], 10, 3 );
		}
	}

	/**
	 * @param int    $appointment_id
	 * @param string $old_status
	 * @param string $new_status
	 */
	public function handle_status_changed( int $appointment_id, string $old_status, string $new_status ) {
		try {
			$appointment = $this->appointments_proxy->get_appointment( $appointment_id );
			$data_layer  = $this->generate_appointment_data_layer( $appointment );
		} catch ( \Exception $e ) {
			Logger::notice( 'appointments', $e->getMessage() );
			return;
		}

		// Freeze appointment status values so we have the trigger-time value when running async.
		Temporary_Data::set( 'appointment_trigger_from_status', $appointment_id, $old_status );
		Temporary_Data::set( 'appointment_trigger_to_status', $appointment_id, $new_status );

		$this->maybe_run( $data_layer );
	}

	/**
	 * @param \AutomateWoo\Workflow $workflow
	 *
	 * @return bool
	 */
	public function validate_workflow( $workflow ) {
		$appointment         = $workflow->data_layer()->get_item( 'appointment' );
		$allowed_from_statii = $workflow->get_trigger_option( 'appointment_status_from' );
		$allowed_to_statii   = $workflow->get_trigger_option( 'appointment_status_to' );

		if ( ! $appointment ) {
			return false;
		}

		// Defrost saved status data from when trigger fired.
		$from_status = Temporary_Data::get( 'appointment_trigger_from_status', $appointment->get_id() );
		$to_status   = Temporary_Data::get( 'appointment_trigger_to_status', $appointment->get_id() );

		if ( ! $this->validate_status_field( $allowed_from_statii, $from_status ) ) {
			return false;
		}

		if ( ! $this->validate_status_field( $allowed_to_statii, $to_status ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Ensures 'to' status has not changed while sitting in queue in case
	 * `validate_order_status_before_queued_run` is checked in Trigger the UI.
	 *
	 * @param \AutomateWoo\Workflow $workflow The workflow to validate
	 * @return bool True if it's valid
	 */
	public function validate_before_queued_event( $workflow ) {

		if ( ! $workflow ) {
			return false;
		}

		if ( $workflow->get_trigger_option( 'validate_order_status_before_queued_run' ) ) {
			$status_to   = $workflow->get_trigger_option( 'appointment_status_to' );
			$appointment = $workflow->data_layer()->get_item( 'appointment' );

			if ( ! $this->validate_status_field( $status_to, $appointment->get_status() ) ) {
				return false;
			}
		}

		return true;
	}
}
