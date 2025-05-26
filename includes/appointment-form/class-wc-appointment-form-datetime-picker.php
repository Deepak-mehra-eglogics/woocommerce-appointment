<?php
/**
 * Class dependencies
 */
if ( ! class_exists( 'WC_Appointment_Form_Date_Picker' ) ) {
	include_once 'class-wc-appointment-form-date-picker.php';
}

/**
 * Date and time Picker class
 */
class WC_Appointment_Form_Datetime_Picker extends WC_Appointment_Form_Date_Picker {

	private $field_type = 'datetime-picker';
	private $field_name = 'start_date';

	/**
	 * Constructor
	 * @param object $appointment_form The appointment form which called this picker
	 */
	public function __construct( $appointment_form ) {
		$this->appointment_form             = $appointment_form;
		$this->args                         = [];
		$this->args['class']                = [];
		$this->args['type']                 = $this->field_type;
		$this->args['name']                 = $this->field_name;
		$this->args['min_date']             = $this->appointment_form->product->get_min_date();
		$this->args['max_date']             = $this->appointment_form->product->get_max_date();
		$this->args['default_availability'] = $this->appointment_form->product->get_default_availability();
		$this->args['availability_rules']   = $this->appointment_form->product->get_availability_rules();
		$this->args['availability_span']    = $this->appointment_form->product->get_availability_span();
		$this->args['label']                = $this->get_field_label( __( 'Date', 'woocommerce-appointments' ) );
		$this->args['min_date_js']          = $this->get_min_date();
		$this->args['max_date_js']          = $this->get_max_date();
		$this->args['interval']             = $this->appointment_form->product->get_duration();
		$this->args['duration_unit']        = $this->appointment_form->product->get_duration_unit();
		$this->args['product_id']           = $this->appointment_form->product->get_id();
		$this->args['is_autoselect']        = $this->appointment_form->product->get_availability_autoselect();
		$this->args['timezone_conversion']  = $this->appointment_form->product->has_timezones();

		if ( in_array( $this->appointment_form->product->get_duration_unit(), [ 'minute', 'hour' ] ) ) {
			$this->args['appointment_duration'] = 1;
		} else {
			$this->args['appointment_duration'] = $this->appointment_form->product->get_duration();
		}

		if ( 'hour' === $this->appointment_form->product->get_duration_unit() ) {
			$this->args['interval'] = $this->args['interval'] * 60;
		} elseif ( 'day' === $this->appointment_form->product->get_duration_unit() ) {
			$this->args['interval'] = $this->args['interval'] * 60 * 24;
		}

		$this->args['default_date'] = date( 'Y-m-d', $this->get_default_date() );
	}

	public static function set_duration_to_day() {
		return 'day';
	}
}
