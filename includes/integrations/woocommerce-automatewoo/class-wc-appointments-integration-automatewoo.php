<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce WC_Appointments_Integration_AutomateWoo integration class.
 *
 * Last compatibility check: AutomateWoo 6.1.5
 */
class WC_Appointments_Integration_AutomateWoo {

	public function __construct() {
		add_filter( 'automatewoo/triggers', array( $this, 'add_triggers' ) );
		add_filter( 'automatewoo/variables', array( $this, 'add_variables' ) );
		add_filter( 'automatewoo/data_types/includes', array( $this, 'data_types' ) );
		add_filter( 'automatewoo/async_events/includes', array( $this, 'async_events' ) );
		add_filter( 'automatewoo_validate_data_item', array( $this, 'validate_custom_variables' ), 10, 3 );

		// Includes.
		require_once __DIR__ . '/Fields/AppointmentStatus.php';

		require_once __DIR__ . '/Async_Events/AppointmentCreated.php';
		require_once __DIR__ . '/Async_Events/AppointmentStatusChanged.php';

		require_once __DIR__ . '/DataTypes/Appointment.php';

		require_once __DIR__ . '/Proxies/AppointmentsInterface.php';
		require_once __DIR__ . '/Proxies/Appointments.php';

		require_once __DIR__ . '/Triggers/Utilities/AppointmentDataLayer.php';
		require_once __DIR__ . '/Triggers/Utilities/AppointmentsGroup.php';

		require_once __DIR__ . '/Triggers/AppointmentCreated.php';
		require_once __DIR__ . '/Triggers/AppointmentStatusChanged.php';

		require_once __DIR__ . '/Variables/AbstractTime.php';
		require_once __DIR__ . '/Variables/AbstractAppointmentTime.php';
		require_once __DIR__ . '/Variables/AppointmentCost.php';
		require_once __DIR__ . '/Variables/AppointmentEndDate.php';
		require_once __DIR__ . '/Variables/AppointmentEndTime.php';
		require_once __DIR__ . '/Variables/AppointmentId.php';
		require_once __DIR__ . '/Variables/AppointmentStaff.php';
		require_once __DIR__ . '/Variables/AppointmentStartDate.php';
		require_once __DIR__ . '/Variables/AppointmentStartTime.php';
		require_once __DIR__ . '/Variables/AppointmentStatus.php';
    }

    /**
     * Create supported AutomateWoo triggers.
	 *
	 * @param array $triggers All the triggers.
     */
    public function add_triggers( $triggers ) {
		$triggers['appointment_created']        = AutomateWoo\Triggers\AppointmentCreated::class;
		$triggers['appointment_status_changed'] = AutomateWoo\Triggers\AppointmentStatusChanged::class;

		#error_log( var_export( $triggers, true ) );

		return $triggers;
    }

	/**
	 * Add variables to AutomateWoo workflows.
	 *
	 * @param array $variables available AutomateWoo variables.
	 */
    public function add_variables( $variables ) {
		$variables['appointment']['id']         = AutomateWoo\Variables\AppointmentId::class;
		$variables['appointment']['cost']       = AutomateWoo\Variables\AppointmentCost::class;
		$variables['appointment']['staff']      = AutomateWoo\Variables\AppointmentStaff::class;
		$variables['appointment']['status']     = AutomateWoo\Variables\AppointmentStatus::class;
		$variables['appointment']['start_date'] = AutomateWoo\Variables\AppointmentStartDate::class;
		$variables['appointment']['start_time'] = AutomateWoo\Variables\AppointmentStartTime::class;
		$variables['appointment']['end_date']   = AutomateWoo\Variables\AppointmentEndDate::class;
		$variables['appointment']['end_time']   = AutomateWoo\Variables\AppointmentEndTime::class;

		return $variables;
    }

	/**
	 * Add data types to AutomateWoo workflows.
	 *
	 * @param array $datatypes available AutomateWoo data tyoes.
	 */
    public function data_types( $datatypes ) {
		$datatypes['appointment'] = AutomateWoo\DataTypes\Appointment::class;

		return $datatypes;
    }

	/**
	 * Load async event includes.
	 *
	 * @param array $includes available AutomateWoo async events.
	 */
    public function async_events( $includes ) {
		$includes['appointment_created']        = AutomateWoo\Async_Events\AppointmentCreated::class;
		$includes['appointment_status_changed'] = AutomateWoo\Async_Events\AppointmentStatusChanged::class;

		return $includes;
    }

	/**
	 * Allow for variables.
	 *
	 * @param bool   $valid valid.
	 * @param string $type type.
	 * @param string $item item.
	 */
	public function validate_custom_variables( $valid, $type, $item ) {
		if ( 'appointment' === $type ) {
			return true;
		}

		return $valid;
	}

}

$GLOBALS['wc_appointments_integration_automatewoo'] = new WC_Appointments_Integration_AutomateWoo();
