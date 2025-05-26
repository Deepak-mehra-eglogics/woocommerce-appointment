<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Class that handles all cost calculations.
 *
 * @since 4.7.0
 */
class WC_Appointments_Cost_Calculation {
	public static $applied_cost_rules;
	public static $appointment_cost = 0;

	/**
	 * Calculate costs from posted values
	 *
	 * @param  array  $data
	 * @param  object $product
	 *
	 * @return string|WP_Error cost
	 */
	public static function calculate_appointment_cost( $posted, $product ) {
		if ( ! empty( self::$appointment_cost ) ) {
			return self::$appointment_cost;
		}

		// Get pricing rules.
		$costs = $product->get_costs();

		// Get posted data.
		$data     = wc_appointments_get_posted_data( $posted, $product );
		$validate = $product->is_appointable( $data );

		if ( is_wp_error( $validate ) ) {
			return $validate;
		}

		// Base price.
		$product_price   = apply_filters( 'appointments_calculated_product_price', $product->get_price(), $product, $posted );
		$base_cost       = max( 0, $product_price );
		$base_slot_cost  = 0;
		$total_slot_cost = 0;

		// See if we have an $product->assigned_staff_id.
		if ( isset( $product->assigned_staff_id ) && $product->assigned_staff_id ) {
			$data['_staff_id'] = $product->assigned_staff_id;
		}

		// Get staff cost.
		if ( isset( $data['_staff_ids'] ) && is_array( $data['_staff_ids'] ) ) { #multiple staff
			foreach ( $data['_staff_ids'] as $data_staff_id ) {
				$staff      = $product->get_staff_member( absint( $data_staff_id ) );
				$base_cost += $staff ? $staff->get_base_cost() : 0;
			}
		} elseif ( isset( $data['_staff_id'] ) ) { #single staff
			$staff      = $product->get_staff_member( absint( $data['_staff_id'] ) );
			$base_cost += $staff ? $staff->get_base_cost() : 0;
		}

		// Slot data.
		self::$applied_cost_rules = [];
		$slot_duration            = $product->get_duration();
		$slot_unit                = $product->get_duration_unit();
		// As we have converted the hourly duration earlier to minutes, convert back.
		if ( isset( $data['_duration'] ) ) {
			$slots_scheduled = 'hour' === $slot_unit ? ceil( absint( $data['_duration'] ) / 60 ) : absint( $data['_duration'] );
		} else {
			$slots_scheduled = $slot_duration;
		}
		$slots_scheduled = ceil( $slots_scheduled / $slot_duration );
		$slot_timestamp  = $data['_start_date'];

		// Check pricing rules for start date only;
		if ( apply_filters( 'appointment_form_pricing_rules_for_start_date', false ) ) {
			$slot_duration = 1;
		}

		// Padding duration.
		$padding_duration = $product->get_padding_duration();
		if ( ! empty( $padding_duration ) ) {
			// handle day paddings
			if ( ! in_array( $slot_unit, [ 'minute', 'hour' ] ) ) {
				$padding_days          = WC_Appointments_Controller::find_padding_day_slots( $product );
				$contains_padding_days = false;
				// Evaluate costs for each scheduled slot
				for ( $slot = 0; $slot < $slots_scheduled; $slot ++ ) {
					$slot_start_time_offset = $slot * $slot_duration;
					$slot_end_time_offset   = ( ( $slot + 1 ) * $slot_duration ) - 1;
					$slot_start_time        = date( 'Y-n-j', strtotime( "+{$slot_start_time_offset} {$slot_unit}", $slot_timestamp ) );
					$slot_end_time          = date( 'Y-n-j', strtotime( "+{$slot_end_time_offset} {$slot_unit}", $slot_timestamp ) );

					if ( in_array( $slot_end_time, $padding_days ) ) {
						$contains_padding_days = true;
					}

					if ( in_array( $slot_start_time, $padding_days ) ) {
						$contains_padding_days = true;
					}
				}

				if ( $contains_padding_days ) {
					return new WP_Error( 'Error', __( 'Sorry, the selected day is not available.', 'woocommerce-appointments' ) );
				}
			}
		}

		// Evaluate pricing rules for each scheduled slot.
		for ( $slot = 0; $slot < $slots_scheduled; $slot ++ ) {
			$slot_cost              = $base_slot_cost;
			$slot_start_time_offset = $slot * $slot_duration;
			$slot_end_time_offset   = ( $slot + 1 ) * $slot_duration;
			$slot_start_time        = wc_appointments_get_formatted_times( strtotime( "+{$slot_start_time_offset} {$slot_unit}", $slot_timestamp ) );
			$slot_end_time          = wc_appointments_get_formatted_times( strtotime( "+{$slot_end_time_offset} {$slot_unit}", $slot_timestamp ) );

			if ( in_array( $slot_unit, array( 'night' ) ) ) {
				$slot_start_time = wc_appointments_get_formatted_times( strtotime( "+{$slot_start_time_offset} day", $slot_timestamp ) );
				$slot_end_time   = wc_appointments_get_formatted_times( strtotime( "+{$slot_end_time_offset} day", $slot_timestamp ) );
			}

			foreach ( $costs as $rule_key => $rule ) {
				$type         = $rule[0];
				$rules        = $rule[1];
				$rule_applied = false;

				if ( strrpos( $type, 'time' ) === 0 ) {
					if ( ! in_array( $slot_unit, [ 'minute', 'hour' ] ) ) {
						continue;
					}

					if ( 'time:range' === $type ) {
						$year  = date( 'Y', $slot_start_time['timestamp'] );
						$month = date( 'n', $slot_start_time['timestamp'] );
						$day   = date( 'j', $slot_start_time['timestamp'] );

						if ( ! isset( $rules[ $year ][ $month ][ $day ] ) ) {
							continue;
						}

						$rule_val = $rules[ $year ][ $month ][ $day ]['rule'];
						$from     = $rules[ $year ][ $month ][ $day ]['from'];
						$to       = $rules[ $year ][ $month ][ $day ]['to'];
					} else {
						if ( ! empty( $rules['day'] ) ) {
							if ( $rules['day'] != $slot_start_time['day_of_week'] ) {
								continue;
							}
						}

						$rule_val = $rules['rule'];
						$from     = $rules['from'];
						$to       = $rules['to'];
					}

					$rule_start_time_hi = date( 'YmdHi', strtotime( str_replace( ':', '', $from ), $slot_start_time['timestamp'] ) );
					$rule_end_time_hi   = date( 'YmdHi', strtotime( str_replace( ':', '', $to ), $slot_start_time['timestamp'] ) );
					$matched            = false;

					// Reverse time rule - The end time is tomorrow e.g. 16:00 today - 12:00 tomorrow
					if ( $rule_end_time_hi <= $rule_start_time_hi ) {
						if ( $slot_end_time['time'] > $rule_start_time_hi ) {
							$matched = true;
						}
						if ( $slot_start_time['time'] >= $rule_start_time_hi && $slot_end_time['time'] >= $rule_end_time_hi ) {
							$matched = true;
						}
						if ( $slot_start_time['time'] <= $rule_start_time_hi && $slot_end_time['time'] <= $rule_end_time_hi ) {
							$matched = true;
						}
					// Normal rule
					} else {
						if ( $slot_start_time['time'] >= $rule_start_time_hi && $slot_end_time['time'] <= $rule_end_time_hi ) {
							$matched = true;
						}
					}

					if ( $matched ) {
						$slot_cost    = self::apply_cost( $slot_cost, $rule_val['slot'][0], $rule_val['slot'][1] );
						$base_cost    = self::apply_base_cost( $base_cost, $rule_val['base'][0], $rule_val['base'][1], $rule_key );
						$rule_applied = true;
					}
				} else {
					switch ( $type ) {
						case 'months':
						case 'weeks':
						case 'days':
							$check_date = $slot_start_time['timestamp'];

							while ( $check_date < $slot_end_time['timestamp'] ) {
								$checking_date = wc_appointments_get_formatted_times( $check_date );
								$date_key      = 'days' == $type ? 'day_of_week' : substr( $type, 0, -1 );

								// Cater to months beyond this year.
								if ( 'month' === $date_key && intval( $checking_date['year'] ) > intval( date( 'Y' ) ) ) {
									$month_beyond_this_year = intval( $checking_date['month'] ) + 12;
									$checking_date['month'] = (string) ( $month_beyond_this_year % 12 );
									if ( '0' === $checking_date['month'] ) {
										$checking_date['month'] = '12';
									}
								}

								if ( isset( $rules[ $checking_date[ $date_key ] ] ) ) {
									$rule         = $rules[ $checking_date[ $date_key ] ];
									$slot_cost    = self::apply_cost( $slot_cost, $rule['slot'][0], $rule['slot'][1] );
									$base_cost    = self::apply_base_cost( $base_cost, $rule['base'][0], $rule['base'][1], $rule_key );
									$rule_applied = true;
								}
								$check_date = strtotime( "+1 {$type}", $check_date );
							}
							break;
						case 'custom':
							$check_date = $slot_start_time['timestamp'];

							while ( $check_date < $slot_end_time['timestamp'] ) {
								$checking_date = wc_appointments_get_formatted_times( $check_date );
								if ( isset( $rules[ $checking_date['year'] ][ $checking_date['month'] ][ $checking_date['day'] ] ) ) {
									$rule         = $rules[ $checking_date['year'] ][ $checking_date['month'] ][ $checking_date['day'] ];
									$slot_cost    = self::apply_cost( $slot_cost, $rule['slot'][0], $rule['slot'][1] );
									$base_cost    = self::apply_base_cost( $base_cost, $rule['base'][0], $rule['base'][1], $rule_key );
									$rule_applied = true;

									/*
									 * Why do we break?
									 * See: Applying a cost rule to an appointment slot
									 * from the DEVELOPER.md
									 */
									break;
								}
								$check_date = strtotime( '+1 day', $check_date );
							}
							break;
						case 'slots':
							if ( ! empty( $data['_duration'] ) ) {
								if ( intval( $rules['from'] ) <= $data['_duration'] && intval( $rules['to'] ) >= $data['_duration'] ) {
									$slot_cost    = self::apply_cost( $slot_cost, $rules['rule']['slot'][0], $rules['rule']['slot'][1] );
									$base_cost    = self::apply_base_cost( $base_cost, $rules['rule']['base'][0], $rules['rule']['base'][1], $rule_key );
									$rule_applied = true;
								}
							}
							break;
						case 'quant':
							if ( ! empty( $data['_qty'] ) ) {
								if ( $rules['from'] <= $data['_qty'] && $rules['to'] >= $data['_qty'] ) {
									$slot_cost    = self::apply_cost( $slot_cost, $rules['rule']['slot'][0], $rules['rule']['slot'][1] );
									$base_cost    = self::apply_base_cost( $base_cost, $rules['rule']['base'][0], $rules['rule']['base'][1], $rule_key );
									$rule_applied = true;
								}
							}
							break;
					}
				}
				/**
				 * Filter to modify rule cost logic. By default, all relevant cost rules will be
				 * applied to a slot. Hooks returning false can modify this so only the first
				 * applicable rule will modify the slot cost.
				 *
				 * @since 4.8.14
				 * @param bool
				 * @param WC_Product_Appointment Current appointable product.
				 */
				if ( $rule_applied && ( ! apply_filters( 'woocommerce_appointments_apply_multiple_rules_per_slot', true, $product ) ) ) {
					break;
				}
			}
			$total_slot_cost += $slot_cost;
		}

		// Calculate costs.
		self::$appointment_cost = max( 0, $total_slot_cost + $base_cost );

		// Multiply costs, when multiple qty scheduled.
		if ( $data['_qty'] > 1 ) {
			self::$appointment_cost = self::$appointment_cost * absint( $data['_qty'] );
		}

		return apply_filters( 'appointment_form_calculated_appointment_cost', self::$appointment_cost, $product, $posted );
	}

	/**
	 * Apply a cost.
	 *
	 * @since 1.15.0
	 * @param  float $base
	 * @param  string $multiplier
	 * @param  float $cost
	 * @return float
	 */
	public static function apply_cost( $base, $multiplier, $cost ) {
		$base = floatval( $base );
		$cost = floatval( $cost );

		if ( ! $cost ) {
			return $base;
		}

		switch ( $multiplier ) {
			case 'times':
				$new_cost = $base * $cost;
				break;
			case 'divide':
				$new_cost = $base / $cost;
				break;
			case 'minus':
				$new_cost = $base - $cost;
				break;
			case 'equals':
				$new_cost = $cost;
				break;
			default:
				$new_cost = $base + $cost;
				break;
		}

		return $new_cost;
	}

	/**
	 * Apply base cost.
	 *
	 * @since 4.7.0
	 *
	 * @param  float $base
	 * @param  string $multiplier
	 * @param  float $cost
	 * @param  string $rule_key Cost to apply the rule to - used for * and /
	 *
	 * @return float
	 */
	private static function apply_base_cost( $base, $multiplier, $cost, $rule_key = '' ) {
		if ( ! $cost || in_array( $rule_key, self::$applied_cost_rules ) ) {
			return $base;
		}

		self::$applied_cost_rules[] = $rule_key;

		return self::apply_cost( $base, $multiplier, $cost );
	}

}
