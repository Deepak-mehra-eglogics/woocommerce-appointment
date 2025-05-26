<?php
/**
 * Picker class
 */
abstract class WC_Appointment_Form_Picker {

	protected $appointment_form;
	protected $args = [];

	/**
	 * Get the label for the field based on appointment durations and type
	 * @param  string $text text to insert into label string
	 * @return string
	 */
	protected function get_field_label( $text ) {
		/* translators: %s: Text to insert into label string */
		return sprintf( '%s', $text );
	}

	/**
	 * Get the min date in date picker format
	 * @return string
	 */
	protected function get_min_date() {
		$js_string = '';
		$min_date  = $this->appointment_form->product->get_min_date_a();
		if ( $min_date['value'] ) {
			$unit = strtolower( substr( $min_date['unit'], 0, 1 ) );

			if ( in_array( $unit, [ 'd', 'y', 'm' ] ) ) {
				$js_string = "+{$min_date['value']}{$unit}";
			} elseif ( 'w' === $unit ) {
				// Change weeks to days.
				$min_d     = (int) ( $min_date['value'] * 7 );
				$js_string = '+' . $min_d . 'd';
			} elseif ( 'h' === $unit ) {
				// if less than 24 hours are entered, we determine if the time falls in today or tomorrow.
				// if more than 24 hours are entered, we determine how many days should be marked off
				if ( 24 > $min_date['value'] ) {
					$current_d = date( 'd', current_time( 'timestamp' ) );
					$min_d     = date( 'd', strtotime( "+{$min_date['value']} hour", current_time( 'timestamp' ) ) );
					$js_string = '+' . ( $current_d == $min_d ? 0 : 1 ) . 'd';
				} else {
					$min_d     = (int) ( $min_date['value'] / 24 );
					$js_string = '+' . $min_d . 'd';
				}
			}
		}

		#print '<pre>'; print_r( $js_string ); print '</pre>';
		return $js_string;
	}

	/**
	 * Get the max date in date picker format
	 * @return string
	 */
	protected function get_max_date() {
		$js_string = '';
		$max_date  = $this->appointment_form->product->get_max_date_a();
		$unit      = strtolower( substr( $max_date['unit'], 0, 1 ) );

		if ( in_array( $unit, [ 'd', 'w', 'y', 'm' ] ) ) {
			$js_string = "+{$max_date['value']}{$unit}";
		} elseif ( 'h' === $unit ) {
			$current_d = date( 'd', current_time( 'timestamp' ) );
			$max_d     = date( 'd', strtotime( "+{$max_date['value']}{$max_date['unit']}", current_time( 'timestamp' ) ) );
			$js_string = '+' . ( $current_d == $max_d ? 0 : absint( $max_d - $current_d ) ) . "d";
		}

		return $js_string;
	}

	/**
	 * Return args for the field
	 * @return array
	 */
	public function get_args() {
		return $this->args;
	}

}
