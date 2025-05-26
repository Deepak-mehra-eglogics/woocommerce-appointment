<?php
/**
 * Class dependencies
 */
if ( ! class_exists( 'WC_Appointment_Form_Picker' ) ) {
	include_once 'class-wc-appointment-form-picker.php';
}

/**
 * Date Picker class
 */
class WC_Appointment_Form_Date_Picker extends WC_Appointment_Form_Picker {

	private $field_type = 'date-picker';
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
		$this->args['availability_span']    = $this->appointment_form->product->get_availability_span();
		$this->args['min_date_js']          = $this->get_min_date();
		$this->args['max_date_js']          = $this->get_max_date();
		$this->args['duration_unit']        = $this->appointment_form->product->get_duration_unit();
		$this->args['appointment_duration'] = $this->appointment_form->product->get_duration();
		$this->args['product_id']           = $this->appointment_form->product->get_id();
		$this->args['is_autoselect']        = $this->appointment_form->product->get_availability_autoselect();
		$this->args['label']                = $this->get_field_label( __( 'Date', 'woocommerce-appointments' ) );
		$this->args['product_type']         = $this->appointment_form->product->get_type();
		$this->args['default_date']         = date( 'Y-m-d', $this->get_default_date() );
	}

	/**
	 * Attempts to find what date to default to in the date picker
	 * by looking at the fist available slot. Otherwise, the current date is used.
	 *
	 * @return int Timestamp
	 */
	public function get_default_date() {
		/**
		 * Filter woocommerce_appointments_override_form_default_date
		 *
		 * @since 1.9.6
		 * @param int $default_date unix time stamp.
		 * @param WC_Appointment_Form_Picker $form_instance
		 */
		$default_date = apply_filters( 'woocommerce_appointments_override_form_default_date', null, $this );

		if ( $default_date ) {
			return $default_date;
		}

		$default_date = current_time( 'timestamp' );

		/**
		 * Filter wc_appointments_calendar_default_to_current_date. By default the calendar
		 * will show the current date first. If you would like it to display the first available date
		 * you can return false to this filter and then we'll search for the first available date,
		 * depending on the scheduled days calculation.
		 *
		 * @since 3.5.0
		 * @param bool
		 */
		if ( apply_filters( 'wc_appointments_calendar_default_to_current_date', true ) && $this->appointment_form->product->get_availability_autoselect() ) {
			/*
			 * Handles the case where a user can set all dates to be not-available by default.
			 * Also they add an availability rule where they are appointable at a future date in time.
			 */

			$now      = strtotime( 'midnight', current_time( 'timestamp' ) );
			$min      = $this->appointment_form->product->get_min_date_a();
			$max      = $this->appointment_form->product->get_max_date_a();
			$min_date = strtotime( 'midnight' );

			if ( ! empty( $min ) ) {
				$min_date = strtotime( "+{$min['value']} {$min['unit']}", $now );
			}

			/*
			 * Handling months differently due to performance impact it has. Get it in three
			 * months batches to ensure we can exit when we find the first one without going
			 * through all 12 months.
			 */
			for ( $i = 1; $i <= $max['value']; $i += 3 ) {
				/*
				 * $min_date calculated above first.
				 * Only add months up to the max value.
				 */
				$range_end_increment = ( $i + 3 ) > $max['value'] ? $max['value'] : ( $i + 3 );
				$max_date            = strtotime( "+ $range_end_increment month", $now );
				$slots_in_range      = $this->appointment_form->product->get_slots_in_range( $min_date, $max_date );
				$last_element        = end( $slots_in_range );

				reset( $slots_in_range ); // restore the internal pointer.

				if ( ! empty( $slots_in_range ) && isset( $slots_in_range[0] ) && $slots_in_range[0] > $last_element ) {
					/*
					 * In certain cases the starting date is at the end
					 * `product->get_available_slots` expects it to be at the beginning.
					 */
					$slots_in_range = array_reverse( $slots_in_range );
				}

				$available_slots = $this->appointment_form->product->get_available_slots(
					[
						'slots' => $slots_in_range,
					]
				);

				if ( ! empty( $available_slots[0] ) ) {
					$default_date = $available_slots[0];
					break;
				} // else continue with loop until we get a default date where the calendar can start at.

				$min_date = strtotime( '+' . $i . ' month', $now );
			}
		}

		return $default_date;
	}
}
