<?php
/**
 * Class dependencies
 */
if ( ! class_exists( 'WC_Appointment_Form_Picker' ) ) {
	include_once 'class-wc-appointment-form-picker.php';
}

/**
 * Month Picker class
 */
class WC_Appointment_Form_Month_Picker extends WC_Appointment_Form_Picker {

	private $field_type = 'month-picker';
	private $field_name = 'start_date';

	/**
	 * Constructor
	 * @param object $appointment_form The appointment form which called this picker
	 */
	public function __construct( $appointment_form ) {
		$this->appointment_form              = $appointment_form;
		$this->args                          = [];
		$this->args['product']               = $this->appointment_form->product;
		$this->args['class']                 = [ 'form-field-month' ];
		$this->args['type']                  = $this->field_type;
		$this->args['name']                  = $this->field_name;
		$this->args['min_date_a']            = $this->appointment_form->product->get_min_date_a();
		$this->args['max_date_a']            = $this->appointment_form->product->get_max_date_a();
		$this->args['duration_unit']         = $this->appointment_form->product->get_duration_unit();
		$this->args['appointment_duration']  = $this->appointment_form->product->get_duration();
		$this->args['product_id']            = $this->appointment_form->product->get_id();
		$this->args['is_autoselect']         = $this->appointment_form->product->get_availability_autoselect();
		$this->args['default_availability']  = $this->appointment_form->product->get_default_availability();
		$this->args['availability_span']     = $this->appointment_form->product->get_availability_span();
		$this->args['label']                 = $this->get_field_label( __( 'Month', 'woocommerce-appointments' ) );
		$this->args['slots']                 = $this->get_appointment_slots();
		$this->args['availability_rules']    = [];
		$this->args['availability_rules'][0] = $this->appointment_form->product->get_availability_rules();

		if ( $this->appointment_form->product->has_staff() ) {
			foreach ( $this->appointment_form->product->get_staff_ids() as $staff_member_id ) {
				$this->args['availability_rules'][ $staff_member_id ] = $this->appointment_form->product->get_availability_rules( $staff_member_id );
			}
		}

		$fully_scheduled_slots = $this->find_fully_scheduled_slots();

		$this->args = array_merge( $this->args, $fully_scheduled_slots );
	}

	/**
	 * Return the available slots for this appointment in array format
	 *
	 * @return array Array of slots
	 */
	public function get_appointment_slots() {
		$min_date_a = $this->args['min_date_a'];
		$max_date_a = $this->args['max_date_a'];

		// Generate a range of slots for months
		if ( $min_date_a ) {
			if ( 0 === $min_date_a['value'] ) {
				$min_date_a['value'] = 1;
			}
			$from = strtotime( date( 'Y-m-01', strtotime( "+{$min_date_a['value']} {$min_date_a['unit']}" ) ) );
		} else {
			$from = strtotime( date( 'Y-m-01', strtotime( '+28 days' ) ) );
		}
		$to = strtotime( date( 'Y-m-t', strtotime( "+{$max_date_a['value']} {$max_date_a['unit']}" ) ) );

		return $this->appointment_form->product->get_slots_in_range( $from, $to );
	}

	/**
	 * Finds months which are fully scheduled already so they can be blocked on the date picker
	 */
	protected function find_fully_scheduled_slots() {
		$scheduled = WC_Appointments_Controller::find_scheduled_month_slots( $this->appointment_form->product->get_id() );

		return [
			'fully_scheduled_months' => $scheduled['fully_scheduled_months'],
		];
	}
}
