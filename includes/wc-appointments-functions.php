<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Get an appointment object
 * @param  int $id
 * @return WC_Appointment|false
 */
function get_wc_appointment( $id = '' ) {
	try {
		return new WC_Appointment( $id );
	} catch ( Exception $e ) {
		wc_get_logger()->error( $e->getMessage() );
		return;
	}
}

/**
 * Get a appointable product object
 * @param  int $id
 * @return WC_Product_Appointment|false
 */
function get_wc_product_appointment( $id = 0 ) {
	try {
		return new WC_Product_Appointment( (int) $id );
	} catch ( Exception $e ) {
		wc_get_logger()->error( $e->getMessage() );
		return;
	}
}

/**
 * Get a availability rules object
 * @param  int $id
 * @return WC_Appointments_Availability|false
 */
function get_wc_appointments_availability( $id = '' ) {
	try {
		return new WC_Appointments_Availability( $id );
	} catch ( Exception $e ) {
		return false;
	}
}

/**
 * Santiize and format a string into a valid 24 hour time
 * @return string
 */
function wc_appointment_sanitize_time( $raw_time ) {
	$time = wc_clean( $raw_time );
	$time = date( 'H:i', strtotime( $time ) );

	return $time;
}

/**
 * Returns true if the product is an appointment product, false if not
 * @return bool
 */
function is_wc_appointment_product( $product ) {
	return isset( $product ) && is_object( $product ) && is_a( $product, 'WC_Product_Appointment' );
}

/**
 * Convert key to a nice readable label
 * @param  string $key
 * @return string
 */
function get_wc_appointment_data_label( $key, $product ) {
	$labels = apply_filters(
		'woocommerce_appointments_data_labels',
		[
			'staff'    => ( $product->get_staff_label() ? $product->get_staff_label() : __( 'Providers', 'woocommerce-appointments' ) ),
			'date'     => __( 'Date', 'woocommerce-appointments' ),
			'time'     => __( 'Time', 'woocommerce-appointments' ),
			'duration' => __( 'Duration', 'woocommerce-appointments' ),
		]
	);

	if ( ! array_key_exists( $key, $labels ) ) {
		return $key;
	}

	return $labels[ $key ];
}

/**
 * Convert status to human readable label.
 *
 * @since  3.0.0
 * @param  string $status
 * @return string
 */
function wc_appointments_get_status_label( $status ) {
	$statuses = [
		'unpaid'               => __( 'Unpaid', 'woocommerce-appointments' ),
		'pending-confirmation' => __( 'Pending Confirmation', 'woocommerce-appointments' ),
		'confirmed'            => __( 'Confirmed', 'woocommerce-appointments' ),
		'paid'                 => __( 'Paid', 'woocommerce-appointments' ),
		'cancelled'            => __( 'Cancelled', 'woocommerce-appointments' ),
		'complete'             => __( 'Complete', 'woocommerce-appointments' ),
		'in-cart'              => __( 'In Cart', 'woocommerce-appointments' ),
	];

	/**
	 * Filter the return value of wc_appointments_get_status_label.
	 *
	 * @since 3.5.6
	 */
	$statuses = apply_filters( 'woocommerce_appointments_get_status_label', $statuses );

	return array_key_exists( $status, $statuses ) ? $statuses[ $status ] : $status;
}

/**
 * Returns a list of appointment statuses.
 *
 * @since 2.3.0 Add new parameter that allows globalised status strings as part of the array.
 * @param  string $context An optional context (filters) for user or cancel statuses
 * @param boolean $include_translation_strings. Defaults to false. This introduces status translations text string. In future (2.0) should default to true.
 * @return array $statuses
 */
function get_wc_appointment_statuses( $context = 'fully_scheduled', $include_translation_strings = false ) {
	if ( 'user' === $context ) {
		$statuses = apply_filters(
			'woocommerce_appointment_statuses_for_user',
			[
				'unpaid'               => __( 'Unpaid', 'woocommerce-appointments' ),
				'pending-confirmation' => __( 'Pending Confirmation', 'woocommerce-appointments' ),
				'confirmed'            => __( 'Confirmed', 'woocommerce-appointments' ),
				'paid'                 => __( 'Paid', 'woocommerce-appointments' ),
				'cancelled'            => __( 'Cancelled', 'woocommerce-appointments' ),
				'complete'             => __( 'Complete', 'woocommerce-appointments' ),
			]
		);
	} elseif ( 'validate' === $context ) {
		$statuses = apply_filters(
			'woocommerce_appointment_statuses_for_validation',
			[
				'unpaid'               => __( 'Unpaid', 'woocommerce-appointments' ),
				'pending-confirmation' => __( 'Pending Confirmation', 'woocommerce-appointments' ),
				'confirmed'            => __( 'Confirmed', 'woocommerce-appointments' ),
				'paid'                 => __( 'Paid', 'woocommerce-appointments' ),
			]
		);
	} elseif ( 'customer' === $context ) {
		$statuses = apply_filters(
			'woocommerce_appointment_statuses_for_customer',
			[
				'expected' => __( 'Expected', 'woocommerce-appointments' ),
				'arrived'  => __( 'Arrived', 'woocommerce-appointments' ),
				'no-show'  => __( 'No-show', 'woocommerce-appointments' ),
			]
		);
	} elseif ( 'cancel' === $context ) {
		$statuses = apply_filters(
			'woocommerce_appointment_statuses_for_cancel',
			[
				'unpaid'               => __( 'Unpaid', 'woocommerce-appointments' ),
				'pending-confirmation' => __( 'Pending Confirmation', 'woocommerce-appointments' ),
				'confirmed'            => __( 'Confirmed', 'woocommerce-appointments' ),
				'paid'                 => __( 'Paid', 'woocommerce-appointments' ),
			]
		);
	} elseif ( 'scheduled' === $context ) {
		$statuses = apply_filters(
			'woocommerce_appointment_statuses_for_scheduled',
			[
				'confirmed' => __( 'Confirmed', 'woocommerce-appointments' ),
				'paid'      => __( 'Paid', 'woocommerce-appointments' ),
			]
		);
	} elseif ( 'all' === $context ) {
		$statuses = apply_filters(
			'woocommerce_appointment_statuses_for_all',
			[
				'unpaid'               => __( 'Unpaid', 'woocommerce-appointments' ),
				'paid'                 => __( 'Paid', 'woocommerce-appointments' ),
				'pending-confirmation' => __( 'Pending Confirmation', 'woocommerce-appointments' ),
				'confirmed'            => __( 'Confirmed', 'woocommerce-appointments' ),
				'cancelled'            => __( 'Cancelled', 'woocommerce-appointments' ),
				'complete'             => __( 'Complete', 'woocommerce-appointments' ),
				'was-in-cart'          => __( 'Was In Cart', 'woocommerce-appointments' ),
				'in-cart'              => __( 'In Cart', 'woocommerce-appointments' ),
			]
		);
	} else {
		$statuses = apply_filters(
			'woocommerce_appointment_statuses_for_fully_scheduled',
			[
				'unpaid'               => __( 'Unpaid', 'woocommerce-appointments' ),
				'paid'                 => __( 'Paid', 'woocommerce-appointments' ),
				'pending-confirmation' => __( 'Pending Confirmation', 'woocommerce-appointments' ),
				'confirmed'            => __( 'Confirmed', 'woocommerce-appointments' ),
				'complete'             => __( 'Complete', 'woocommerce-appointments' ),
				'in-cart'              => __( 'In Cart', 'woocommerce-appointments' ),
			]
		);
	}

	/**
 	 * Filter the return value of get_wc_appointment_statuses.
 	 *
 	 * @since 3.5.6
 	 */
	$statuses = apply_filters( 'woocommerce_appointments_get_wc_appointment_statuses', $statuses );

	// backwards compatibility
	return $include_translation_strings ? $statuses : array_keys( $statuses );
}

/**
 * Validate and create a new appointment manually.
 *
 * @version  1.10.7
 * @see      WC_Appointment::new_appointment() for available $new_appointment_data args
 * @param    int    $product_id of the appointment
 * @param    array  $new_appointment_data
 * @param    string $status
 * @param    bool   $exact If false, the function will look for the next available slot after your start date if the date is unavailable.
 * @return   mixed  WC_Appointment object on success or false on fail
 */
function create_wc_appointment( $product_id, $new_appointment_data = [], $status = 'confirmed', $exact = false ) {
	// Merge appointment data
	$defaults = [
		'product_id' => $product_id, // Product ID
		'start_date' => '',
		'end_date'   => '',
		'staff_id'   => '',
		'staff_ids'  => '',
		'timezone'   => '',
	];

	$new_appointment_data = wp_parse_args( $new_appointment_data, $defaults );
	$product              = wc_get_product( $product_id );
	$start_date           = $new_appointment_data['start_date'];
	$end_date             = $new_appointment_data['end_date'];
	$staff_id             = $new_appointment_data['staff_id'] ?? $new_appointment_data['staff_ids'];
	$max_date             = $product->get_max_date_a();
	$all_day              = isset( $new_appointment_data['all_day'] ) && $new_appointment_data['all_day'] ? true : false;
	$qty                  = 1;

	// If not set, use next available
	if ( ! $start_date ) {
		$min_date   = $product->get_min_date_a();
		$start_date = strtotime( "+{$min_date['value']} {$min_date['unit']}", current_time( 'timestamp' ) );
	}

	// If not set, use next available + slot duration
	if ( ! $end_date ) {
		$end_date = strtotime( '+' . $product->get_duration() . ' ' . $product->get_duration_unit(), $start_date );
	}

	$searching = true;
	$date_diff = $all_day ? DAY_IN_SECONDS : $end_date - $start_date;

	while ( $searching ) {

		$available_appointments = wc_appointments_get_total_available_appointments_for_range(
			$product,
			$start_date, #start_date
			$end_date, #end_date
			$staff_id,
			$qty
		);

		if ( $available_appointments && ! is_wp_error( $available_appointments ) ) {

			if ( ! $staff_id && is_array( $available_appointments ) ) {
				$new_appointment_data['staff_ids'] = current( array_keys( $available_appointments ) );
			}

			$searching = false;

		} else {
			if ( $exact ) {
				return false;
			}

			$start_date += $date_diff;
			$end_date   += $date_diff;

			if ( $end_date > strtotime( "+{$max_date['value']} {$max_date['unit']}" ) ) {
				return false;
			}
		}
	}

	// Set dates
	$new_appointment_data['start_date'] = $start_date;
	$new_appointment_data['end_date']   = $end_date;

	// Create it
	$new_appointment = get_wc_appointment( $new_appointment_data );
	$new_appointment->create( $status );

	return $new_appointment;
}

/**
 * Check if product/appointment requires confirmation.
 *
 * @param  int $id Product ID.
 *
 * @return bool
 */
function wc_appointment_requires_confirmation( $id ) {
	$product = wc_get_product( $id );

	if ( is_wc_appointment_product( $product ) && $product->requires_confirmation() ) {
		return true;
	}

	return false;
}

/**
 * Check if product/appointment is sold individually.
 *
 * @param  int $id Product ID.
 *
 * @return bool
 */
function wc_appointment_sold_individually( $id ) {
	$product = wc_get_product( $id );

	if ( is_wc_appointment_product( $product ) && $product->is_sold_individually() ) {
		return true;
	}

	return false;
}

/**
 * Check if the cart has appointment that requires confirmation.
 *
 * @return bool
 */
function wc_appointment_cart_requires_confirmation() {
	$has_it = false;

	if ( ! empty( WC()->cart->cart_contents ) ) {
		foreach ( WC()->cart->cart_contents as $item ) {
			if ( wc_appointment_requires_confirmation( $item['product_id'] ) ) {
				$has_it = true;
				break;
			}
		}
	}

	return $has_it;
}

/**
 * Check if the order has appointment that requires confirmation.
 *
 * @param  WC_Order $order
 *
 * @return bool
 */
function wc_appointment_order_requires_confirmation( $order ) {
	$requires = false;

	if ( $order && is_a( $order, 'WC_Order' ) ) {
		foreach ( $order->get_items() as $item ) {
			if ( wc_appointment_requires_confirmation( $item['product_id'] ) ) {
				$requires = true;
				break;
			}
		}
	}

	return $requires;
}

/**
 * Get timezone string.
 *
 * inspired by https://wordpress.org/plugins/event-organiser/
 *
 * @return string
 */
function wc_appointment_get_timezone_string() {
	wc_deprecated_function( __FUNCTION__, '4.22.5', 'wc_timezone_string' );
	return wc_timezone_string();
}

/**
 * Get duration in minutes from timestampts.
 *
 * @param  $start         int
 * @param  $end           int
 * @param  $duration_unit string
 * @param  $pretty        bool
 *
 * @return string|integer
 */
function wc_appointment_duration_in_minutes( $start, $end, $duration_unit = 'minute', $pretty = true ) {
	if ( $start && $end ) {
		$start_time = round( $start / 60 ) * 60; #round to nearest minute
		$end_time   = round( $end / 60 ) * 60; #round to nearest minute
		$timeDiff   = abs( $end_time - $start_time ); #calculate difference
		$time       = intval( $timeDiff / 60 ); #force integer value

		// Return minutes.
		if ( $pretty ) {
			return wc_appointment_pretty_timestamp( $time, $duration_unit );
		}

		return $time;
	}

	return 0;
}

/**
 * Convert timestamp to formated time.
 *
 * @param  $timestamp  int
 * @param  $is_all_day bool
 *
 * @return string
 */
function wc_appointment_format_timestamp( $timestamp, $is_all_day = false ) {
	if ( $timestamp ) {
		$date_format = wc_appointments_date_format();
		$time_format = ', ' . wc_appointments_time_format();
		if ( $is_all_day ) {
			return date_i18n( $date_format, $timestamp );
		} else {
			return date_i18n( $date_format . $time_format, $timestamp );
		}
	}
	return false;
}

/**
 * Convert time in minutes to hours and minutes
 *
 * @param  $time int
 *
 * @return string|void
 */
function wc_appointment_pretty_addon_duration( $time ) {
	global $product;

	if ( '' === $time || '0' == $time ) {
		return;
	}

	$neg    = false;
	$prefix = '<span class="amount-symbol">+</span>';

	if ( $time < 0 ) {
		$neg    = true;
		$prefix = '<span class="amount-symbol">-</span>';
	}

	// Make sure it is always positive.
	$time = absint( $time );

	// Get product object from cart item.
	if ( ( is_cart() || is_checkout() ) && isset( $cart_item ) && null !== $cart_item ) {
		$product = wc_get_product( $cart_item->get_id() );
	}

	// Check product object.
	if ( is_wc_appointment_product( $product ) ) {
		$duration_unit = $product->get_duration_unit() ? $product->get_duration_unit() : 'minute';
	} else {
		$duration_unit = 'minute';
	}

	if ( 'month' === $duration_unit ) {
		/* translators: %s: months */
		$return = sprintf( _n( '%s month', '%s months', $time, 'woocommerce-appointments' ), $time );
	} elseif ( 'day' === $duration_unit ) {
		/* translators: %s: days */
		$return = sprintf( _n( '%s day', '%s days', $time, 'woocommerce-appointments' ), $time );
	// Hourly or minutes product duration sets add-on duration in minutes.
	} else {
		$return = wc_appointment_pretty_timestamp( $time, 'minute' );
	}

	$html = '<span class="addon-duration">' . $prefix . $return . '</span>';

	return apply_filters( 'wc_appointment_pretty_addon_duration', $html, $time, $product );
}

/**
 * Convert duration in minutes to pretty time display
 *
 * @param  $time int in minutes
 *
 * @return string
 */
function wc_appointment_pretty_timestamp( $time, $duration_unit = 'minute' ) {
	$minsPerMonth = apply_filters( 'woocommerce_appointments_month_duration_break', 60 * 24 * 30 ); #1 month
	$minsPerDay   = apply_filters( 'woocommerce_appointments_day_duration_break', 60 * 24 ); #1 day
	$minsPerHour  = apply_filters( 'woocommerce_appointments_hour_duration_break', 60 * 2 ); #2 hours

	// Months.
	if ( $time >= $minsPerMonth && 'month' === $duration_unit ) {
		$months           = floor( $time / 43200 );
		$months_in_minuts = $months * 43200;
		/* translators: %s: months */
		$return = sprintf( _n( '%s month', '%s months', $months, 'woocommerce-appointments' ), $months );
		$days   = floor( ( $time - $months_in_minuts ) / 7200 );
		if ( $days > 0 ) {
			$return .= '&nbsp;'; #empty space
			/* translators: %s: days */
			$return .= sprintf( _n( '%s day', '%s days', $days, 'woocommerce-appointments' ), $days );
		}
	// Days by calculation.
	} elseif ( $time >= $minsPerDay || 'day' === $duration_unit ) {
		$days           = floor( $time / 1440 );
		$days_in_minuts = $days * 1440;
		/* translators: %s: days */
		$return = sprintf( _n( '%s day', '%s days', $days, 'woocommerce-appointments' ), $days );
		$hours  = floor( ( $time - $days_in_minuts ) / 60 );
		if ( $hours > 0 ) {
			$return .= '&nbsp;'; #empty space
			/* translators: %s: hours */
			$return .= sprintf( _n( '%s hour', '%s hours', $hours, 'woocommerce-appointments' ), $hours );
		}
		$minutes = ( $time % 60 );
		if ( $minutes > 0 ) {
			$return .= '&nbsp;'; #empty space
			/* translators: %s: minutes */
			$return .= sprintf( _n( '%s minute', '%s minutes', $minutes, 'woocommerce-appointments' ), $minutes );
		}
	// Hours by calculation.
	} elseif ( $time >= $minsPerHour ) {
		$hours = floor( $time / 60 );
		/* translators: %s: hours */
		$return  = sprintf( _n( '%s hour', '%s hours', $hours, 'woocommerce-appointments' ), $hours );
		$minutes = ( $time % 60 );
		if ( $minutes > 0 ) {
			$return .= '&nbsp;'; #empty space
			/* translators: %s: minutes */
			$return .= sprintf( _n( '%s minute', '%s minutes', $minutes, 'woocommerce-appointments' ), $minutes );
		}
	// Minutes by calculation.
	} else {
		/* translators: %s: minutes */
		$return = sprintf( _n( '%s minute', '%s minutes', $time, 'woocommerce-appointments' ), $time );
	}

	return apply_filters( 'wc_appointment_pretty_timestamp', $return, $time );
}

/**
 * Convert duration in minutes to array of duration parameters.
 *
 * @param  $time int in minutes
 *
 * @since  4.9.8
 *
 * @return array [ 'duration', 'duration_unit' ]
 */
function wc_appointment_duration_parameters( $time ) {
	$minsPerMonth = apply_filters( 'woocommerce_appointments_month_duration_break', 60 * 24 * 30 ); #1 month
	$minsPerDay   = apply_filters( 'woocommerce_appointments_day_duration_break', 60 * 24 ); #1 day
	$minsPerHour  = apply_filters( 'woocommerce_appointments_hour_duration_break', 60 * 2 ); #2 hours

	$return = [
		'duration'      => 1,
		'duration_unit' => 'minute',
	];

	// Months.
	if ( $time >= $minsPerMonth ) {
		$months = floor( $time / 43200 );

		$return['duration']      = intval( $months );
		$return['duration_unit'] = 'month';

		$months_in_minuts = $months * 43200;
		$days             = floor( ( $time - $months_in_minuts ) / 7200 );
		if ( $days > 0 ) {
			$days = floor( $time / 1440 );

			$return['duration']      = intval( $days );
			$return['duration_unit'] = 'day';
		}
	// Days.
	} elseif ( $time >= $minsPerDay ) {
		$days = floor( $time / 1440 );

		$return['duration']      = intval( $days );
		$return['duration_unit'] = 'day';

		$days_in_minuts = $days * 1440;
		$hours          = floor( ( $time - $days_in_minuts ) / 60 );
		if ( $hours > 0 ) {
			$hours = floor( $time / 60 );

			$return['duration']      = intval( $hours );
			$return['duration_unit'] = 'hour';
		}

		$minutes = ( $time % 60 );
		if ( $minutes > 0 ) {
			$return['duration']      = intval( $time );
			$return['duration_unit'] = 'minute';
		}
	// Hours.
	} elseif ( $time >= $minsPerHour ) {
		$hours = floor( $time / 60 );

		$return['duration']      = intval( $hours );
		$return['duration_unit'] = 'hour';

		$minutes = ( $time % 60 );
		if ( $minutes > 0 ) {
			$return['duration']      = intval( $time );
			$return['duration_unit'] = 'minute';
		}
	// Minutes.
	} else {
		$return['duration']      = intval( $time );
		$return['duration_unit'] = 'minute';
	}

	return apply_filters( 'wc_appointment_duration_parameters', $return, $time );
}

/**
 * Get timezone offset in seconds.
 *
 * @since  3.1.8
 * @return float
 */
function wc_appointment_timezone_offset() {
	$timezone = wc_timezone_string();

	if ( $timezone ) {
		$timezone_object = new DateTimeZone( $timezone );
		return $timezone_object->getOffset( new DateTime( 'now' ) );
	} else {
		return floatval( get_option( 'gmt_offset', 0 ) ) * HOUR_IN_SECONDS;
	}
}

/**
 * Get the offset in seconds between a timezone and UTC
 *
 * @param string $timezone
 *
 * @return int
 */
function wc_appointment_get_timezone_offset( $timezone ) {
	if ( ! $timezone ) {
		return false;
	}

	// Map UTC+- timezones to gmt_offsets and set timezone_string to empty.
	if ( ! empty( $timezone ) && preg_match( '/^UTC[+-]/', $timezone ) ) {
		$gmt_offset = $timezone;
		$gmt_offset = preg_replace( '/UTC\+?/', '', $gmt_offset );
		$gmt_offset = $gmt_offset * HOUR_IN_SECONDS;
		$timezone   = '';
	}

	if ( $timezone ) {
		$utc_tz   = new DateTimeZone( $timezone );
		$utc_date = new DateTime( 'now', $utc_tz );

		$offset = $utc_tz->getOffset( $utc_date );

		return (int) $offset;

	} else {
		$offset = $gmt_offset;

		return (int) $offset;
	}
}

/**
 * Get timezone name.
 *
 * @since  4.0.0
 * @return float
 */
function wc_appointment_get_timezone_name( $timezone = '' ) {
	static $mo_loaded = false, $locale_loaded = null;

	// Map UTC+- timezones to gmt_offsets and set timezone_string to empty.
	if ( ! empty( $timezone ) && preg_match( '/^UTC[+-]/', $timezone ) ) {
		return $timezone;
	}

	$locale = get_user_locale();

	// Load translations for continents and cities.
	$continents = [
		'Africa',
		'America',
		'Antarctica',
		'Arctic',
		'Asia',
		'Atlantic',
		'Australia',
		'Europe',
		'Indian',
		'Pacific',
	];

	// Load translations for continents and cities.
	if ( ! $mo_loaded || $locale !== $locale_loaded ) {
		$locale_loaded = $locale ? $locale : get_locale();
		$mofile        = WP_LANG_DIR . '/continents-cities-' . $locale_loaded . '.mo';
		unload_textdomain( 'continents-cities' );
		load_textdomain( 'continents-cities', $mofile );
		$mo_loaded = true;
	}

	$zone_name = '';
	foreach ( timezone_identifiers_list() as $zone ) {
		$zone_full = $zone;
		$zone      = explode( '/', $zone );
		if ( ! in_array( $zone[0], $continents ) ) {
			continue;
		}

		// This determines what gets set and translated - we don't translate Etc/* strings here, they are done later
		$exists    = [
			0 => ( isset( $zone[0] ) && $zone[0] ),
			1 => ( isset( $zone[1] ) && $zone[1] ),
			2 => ( isset( $zone[2] ) && $zone[2] ),
		];
		$exists[3] = ( $exists[0] && 'Etc' !== $zone[0] );
		$exists[4] = ( $exists[1] && $exists[3] );
		$exists[5] = ( $exists[2] && $exists[3] );

		$zonen = [
			'continent'   => ( $exists[0] ? $zone[0] : '' ),
			'city'        => ( $exists[1] ? $zone[1] : '' ),
			'subcity'     => ( $exists[2] ? $zone[2] : '' ),
			't_continent' => ( $exists[3] ? translate( str_replace( '_', ' ', $zone[0] ), 'continents-cities' ) : '' ),
			't_city'      => ( $exists[4] ? translate( str_replace( '_', ' ', $zone[1] ), 'continents-cities' ) : '' ),
			't_subcity'   => ( $exists[5] ? translate( str_replace( '_', ' ', $zone[2] ), 'continents-cities' ) : '' ),
		];

		if ( $timezone && $timezone === $zone_full ) {
			$zone_name = $zonen['t_city'];
		}
	}

	$timezone_name = $zone_name;

	if ( $timezone && $timezone_name ) {
		return $timezone_name;
	} else {
		return '';
	}
}

/**
 * Convert Unix timestamps to/from various locales
 *
 * @param string $from
 * @param string $to
 * @param int    $time
 * @param string $format (optional)
 *
 * @return string|void
 */
function wc_appointment_timezone_locale( $from = '', $to = '', $time = '', $format = 'U', $user_timezone = '', $reverse = false ) {
	// Validate Unix timestamp
	if ( ! is_numeric( $time ) || $time > PHP_INT_MAX || $time < ~PHP_INT_MAX ) {
		return;
	}

	// Calc "from" offset
	$from = ( 'site' === $from ) ? wc_timezone_string() : ( ( 'user' === $from ) ? $user_timezone : 'GMT' );
	$from = wc_appointment_get_timezone_offset( $from );

	// Calc "to" offset
	$to = ( 'site' === $to ) ? wc_timezone_string() : ( ( 'user' === $to ) ? $user_timezone : 'GMT' );
	$to = wc_appointment_get_timezone_offset( $to );

	// Calc GMT time using "from" offset
	$gmt = $time - $from;

	// Calc final date string using "to" offset
	$date = date( $format, $gmt + $to );

	// Reverse to original timezone.
	if ( $reverse ) {
		$gmt  = $time + $from;
		$date = date( $format, $gmt - $to );
	}

	return (string) $date;
}

/**
 * @since 3.0.0
 * @param $minute
 * @param $check_date
 *
 * @return int
 */
function wc_appointment_minute_to_time_stamp( $minute, $check_date ) {
	return strtotime( "+ $minute minutes", $check_date );
}

/**
 * Convert a timestamp into the minutes after 0:00
 *
 * @since 3.0.0
 * @param integer $timestamp
 * @return integer $minutes_after_midnight
 */
function wc_appointment_time_stamp_to_minutes_after_midnight( $timestamp ) {
	$hour = absint( date( 'H', $timestamp ) );
	$min  = absint( date( 'i', $timestamp ) );

	return $min + ( $hour * 60 );
}

/**
 * Convert a timestamp into iso8601 format.
 *
 * @since 4.2.5
 * @param integer $timestamp
 * @return string iso8601 time format
 */
function get_wc_appointment_time_as_iso8601( $timestamp ) {
	$timezone    = wc_timezone_string();
	$server_time = new DateTime( date( 'Y-m-d\TH:i:s', $timestamp ), new DateTimeZone( $timezone ) );

	return $server_time->format( 'Y-m-d\TH:i:s' );
}

/**
 * @sine 1.9.13
 * @return string
 */
function get_wc_appointment_rules_explanation() {
	return __( 'Rules further down the table will override those at the top.', 'woocommerce-appointments' );
}

function get_wc_appointment_priority_explanation() {
	return __( 'Rules with lower priority numbers will override rules with a higher priority (e.g. 9 overrides 10 ). Global rules take priority over product rules which take priority over staff rules. By using priority numbers you can execute rules in different orders.', 'woocommerce-appointments' );
}

/**
 * Write to woocommerce log files
 * @return void
 */
function wc_add_appointment_log( $log_id, $message ) {
	$log = wc_get_logger();
	$log->add( $log_id, $message );
}

/**
 * Get staff from provided IDs.
 *
 * @param int $staff_ids
 * @param string $post_status (optional)
 *
 * @return int
 */
function wc_appointments_get_staff_from_ids( $ids = [], $names = false, $with_link = false ) {
	if ( ! is_array( $ids ) ) {
		$ids = [ $ids ];
	}

	$staff_members = [];

	if ( ! empty( $ids ) ) {
		foreach ( $ids as $id ) {
			$staff_member = new WC_Product_Appointment_Staff( $id );

			if ( $with_link ) {
				$staff_members[] = '<a href="' . get_edit_user_link( $staff_member->get_id() ) . '">' . $staff_member->get_display_name() . '</a>';
			} elseif ( $names ) {
				$staff_members[] = $staff_member->get_display_name();
			} else {
				$staff_members[] = $staff_member;
			}
		}
	}

	if ( $names && ! empty( $staff_members ) ) {
		$staff_members = implode( ', ', $staff_members );
	}

	return $staff_members;
}

/**
 * Get the min timestamp that is appointable based on settings.
 *
 * If $today is the current day, offset starts from NOW rather than midnight.
 *
 * @param int $today Current timestamp, defaults to now.
 * @param int $offset
 * @param string $unit
 * @return int
 */
function wc_appointments_get_min_timestamp_for_day( $date, $offset, $unit ) {
	$timestamp = $date;

	$now      = current_time( 'timestamp' );
	$is_today = date( 'y-m-d', $date ) === date( 'y-m-d', $now );

	if ( $is_today || empty( $date ) ) {
		$timestamp = strtotime( "midnight +{$offset} {$unit}", $now );
	}

	return $timestamp;
}

/**
 * Give this function an appointment or staff ID, and a range of dates and get back
 * how many places are available for the requested quantity of appointments for all slots within those dates.
 *
 * Replaces the WC_Product_Appointment::get_available_appointments method.
 *
 * @param  WC_Product_Appointment | integer $appointable_product Can be a product object or an appointment prouct ID.
 * @param  integer $start_date
 * @param  integer $end_date
 * @param  integer|null optional $staff_id
 * @param  integer $qty
 * @return array|int|boolean|void|WP_Error False if no places/slots are available or the dates are invalid.
 */
function wc_appointments_get_total_available_appointments_for_range( $appointable_product, $start_date, $end_date, $staff_id = null, $qty = 1 ) {
	// Alter the end date to limit it to go up to one slot if the setting is enabled
	if ( $appointable_product->get_availability_span() ) {
		$end_date = strtotime( '+ ' . $appointable_product->get_duration() . ' ' . $appointable_product->get_duration_unit(), $start_date );
	}

	// Check the date is not in the past
	if ( date( 'Ymd', $start_date ) < date( 'Ymd', current_time( 'timestamp' ) ) ) {
		return false;
	}

	// Check we have a staff if needed
	if ( $appointable_product->has_staff() && ! is_numeric( $staff_id ) && ! $appointable_product->is_staff_assignment_type( 'all' ) ) {
		if ( is_array( $staff_id ) && 1 < count( $staff_id ) ) {
			return false;
		}
	}

	$min_date   = $appointable_product->get_min_date_a();
	$max_date   = $appointable_product->get_max_date_a();
	$check_from = strtotime( "midnight +{$min_date['value']} {$min_date['unit']}", current_time( 'timestamp' ) );
	$check_to   = strtotime( "+{$max_date['value']} {$max_date['unit']}", current_time( 'timestamp' ) );

	// Min max checks
	if ( 'month' === $appointable_product->get_duration_unit() ) {
		$check_to = strtotime( 'midnight', strtotime( date( 'Y-m-t', $check_to ) ) );
	}
	if ( $end_date < $check_from || $start_date > $check_to ) {
		return false;
	}

	// Appointments query start / end dates.
	$appointments_start_date = $start_date;
	$appointments_end_date   = $end_date;

	// Get appointments.
	$appointments          = [];
	$existing_appointments = WC_Appointment_Data_Store::get_all_existing_appointments( $appointable_product, $start_date, $end_date, $staff_id );
	if ( ! empty( $existing_appointments ) ) {
		foreach ( $existing_appointments as $existing_appointment ) {
			#print '<pre>'; print_r( $existing_appointment->get_id() ); print '</pre>';
			$appointments[] = [
				'get_staff_ids'  => $existing_appointment->get_staff_ids(),
				'get_start'      => $existing_appointment->get_start(),
				'get_end'        => $existing_appointment->get_end(),
				'get_qty'        => $existing_appointment->get_qty(),
				'get_id'         => $existing_appointment->get_id(),
				'get_product_id' => $existing_appointment->get_product_id(),
				'is_all_day'     => $existing_appointment->is_all_day(),
			];
		}
	}

	// Get availability of each staff - no staff has been chosen yet.
	if ( $appointable_product->has_staff() && ! $staff_id ) {
		return $appointable_product->get_all_staff_availability( $start_date, $end_date, $qty );
	} else {
		// If we are checking for appointments for a specific staff, or have none.
		$check_date = $start_date;

		if ( in_array( $appointable_product->get_duration_unit(), [ 'minute', 'hour' ] ) ) {
			if ( ! WC_Product_Appointment_Rule_Manager::check_availability_rules_against_time( $appointable_product, $start_date, $end_date, $staff_id ) ) {
				return;
			}
		} else {
			while ( $check_date < $end_date ) {
				if ( ! WC_Product_Appointment_Rule_Manager::check_availability_rules_against_date( $appointable_product, $check_date, $staff_id ) ) {
					return;
				}
				if ( $appointable_product->get_availability_span() ) {
					break; // Only need to check first day
				}
				$check_date = strtotime( '+1 day', $check_date );
			}
		}

		// Get slots availability
		return $appointable_product->get_slots_availability( $start_date, $end_date, $qty, $staff_id, [], $appointments );
	}
}

/**
 * Summary of appointment data for admin and checkout.
 *
 * @version 3.3.0
 *
 * @param  WC_Appointment $appointment
 * @param  bool       $is_admin To determine if this is being called in admin or not.
 */
function wc_appointments_get_summary_list( $appointment, $is_admin = false ) {
	$product   = $appointment->get_product();
	$providers = $appointment->get_staff_members( true );
	$label     = $product && is_callable( [ $product, 'get_staff_label' ] ) && $product->get_staff_label() ? $product->get_staff_label() : __( 'Providers', 'woocommerce-appointments' );
	$date      = sprintf( '%1$s', $appointment->get_start_date() );
	$duration  = sprintf( '%1$s', $appointment->get_duration() );

	$template_args = apply_filters(
		'wc_appointments_get_summary_list',
		[
			'appointment' => $appointment,
			'product'     => $product,
			'providers'   => $providers,
			'label'       => $label,
			'date'        => $date,
			'duration'    => $duration,
			'is_admin'    => $is_admin,
			'is_rtl'      => is_rtl() ? 'right' : 'left',
		]
	);

	wc_get_template( 'order/appointment-summary-list.php', $template_args, '', WC_APPOINTMENTS_TEMPLATE_PATH );
}

/**
 * Converts a string (e.g. yes or no) to a bool.
 *
 * @param  string $string
 * @return boolean
 */
function wc_appointments_string_to_bool( $string ) {
	if ( function_exists( 'wc_string_to_bool' ) ) {
		return wc_string_to_bool( $string );
	}

	return is_bool( $string ) ? $string : ( 'yes' === $string || 1 === $string || 'true' === $string || '1' === $string );
}

/**
 * Escape RRULE string
 *
 * @since 4.5.14
 *
 * @return string
 */
function wc_appointments_esc_rrule( $rrule, $is_all_day = false ) {
	// UNTIL is missing time.
	$until_time_missing = false;
	foreach ( explode( ';', $rrule ) as $pair ) {
		$pair = explode( '=', $pair );
		if ( ! isset( $pair[1] ) || isset( $pair[2] ) ) {
			continue;
		}
		list( $key, $value ) = $pair;
		if ( 'UNTIL' === $key ) {
			if ( false === strpos( $value, 'T' ) ) {
				$until_time_missing = true;
				break;
			}
		}
	}

	// Remove time from UNTIL.
	if ( $is_all_day ) {
		$rrule = preg_replace_callback(
			'/UNTIL=([\dTZ]+)(?=;?)/',
			function( $matches ) {
				#error_log( var_export( $matches, true ) );
			    $dtUntil = new WC_DateTime( substr( $matches[1], 0, 8 ) );

				return 'UNTIL=' . $dtUntil->format( 'Ymd' );
			},
			$rrule
		);
		#error_log( var_export( $rrule, true ) );

	// Append time to UNTIL.
	} elseif ( $until_time_missing ) {
		$rrule = preg_replace( '/UNTIL=[^;]*/', '\0T000000Z', $rrule );
		#error_log( var_export( $rrule, true ) );
	}

	return $rrule;
}

/**
 * Return appointment object.
 *
 * @since 4.6.0
 *
 * @return object|void
 */
function wc_appointments_maybe_appointment_object( $appointment ) {
	// Check if provided $appointment_id is indeed an $appointment.
	if ( is_a( $appointment, 'WC_Appointment' ) ) {
		return $appointment;
	} else {
		// Check if provided $appointment_id is an $order.
		// Some extensions use only orders as email triggers
		// so make sure they are also included.
		$order = wc_get_order( $appointment );
		if ( is_a( $order, 'WC_Order' ) ) {
			// Get $appointment_ids from an $order.
			$appointment_ids = WC_Appointment_Data_Store::get_appointment_ids_from_order_id( $order->get_id() );
			if ( ! empty( $appointment_ids ) && isset( $appointment_ids[0] ) ) {
				// Set $appointment_id from an $order.
				$appointment_id = absint( $appointment_ids[0] );
				// Set $this->object from $appointment_id.
				return get_wc_appointment( $appointment_id );
			} else {
				return;
			}
		} else {
			return;
		}
	}
}

/**
 * Get an array of formatted time values
 * @param  string $timestamp
 * @return array
 */
function wc_appointments_get_formatted_times( $timestamp ) {
	return [
		'timestamp'   => $timestamp,
		'year'        => intval( date( 'Y', $timestamp ) ),
		'month'       => intval( date( 'n', $timestamp ) ),
		'day'         => intval( date( 'j', $timestamp ) ),
		'week'        => intval( date( 'W', $timestamp ) ),
		'day_of_week' => intval( date( 'N', $timestamp ) ),
		'time'        => date( 'YmdHi', $timestamp ),
	];
}

/**
 * Get posted form data into a neat array.
 *
 * @since 4.7.0
 *
 * @param  array       $posted
 * @param  object      $product
 * @param  string|bool $get_data
 *
 * @return array|bool
 */
function wc_appointments_get_posted_data( $posted = [], $product = false, $get_data = false ) {
	if ( empty( $posted ) ) {
		$posted = $_POST;
	}

	$data = [
		'_year'           => '',
		'_month'          => '',
		'_day'            => '',
		'_timezone'       => '',
		'_local_timezone' => '',
	];

	// Get year month field.
	if ( ! empty( $posted['wc_appointments_field_start_date_yearmonth'] ) ) {
		$yearmonth      = strtotime( $posted['wc_appointments_field_start_date_yearmonth'] . '-01' );
		$data['_year']  = absint( date( 'Y', $yearmonth ) );
		$data['_month'] = absint( date( 'm', $yearmonth ) );
		$data['_day']   = 1;
		$data['_date']  = $data['_year'] . '-' . $data['_month'] . '-' . $data['_day'];
		$data['date']   = date_i18n( 'F Y', $yearmonth );
	// Get date fields (y, m, d).
	} elseif (
		! empty( $posted['wc_appointments_field_start_date_year'] ) &&
		! empty( $posted['wc_appointments_field_start_date_month'] ) &&
		! empty( $posted['wc_appointments_field_start_date_day'] )
	) {
		$data['_year']  = absint( $posted['wc_appointments_field_start_date_year'] );
		$data['_year']  = $data['_year'] ? $data['_year'] : date( 'Y' );
		$data['_month'] = absint( $posted['wc_appointments_field_start_date_month'] );
		$data['_day']   = absint( $posted['wc_appointments_field_start_date_day'] );
		$data['_date']  = $data['_year'] . '-' . $data['_month'] . '-' . $data['_day'];
		$data['date']   = date_i18n( wc_appointments_date_format(), strtotime( $data['_date'] ) );
	}

	// Get time field.
	if ( ! empty( $posted['wc_appointments_field_start_date_time'] ) ) {
		$data['_time'] = wc_clean( $posted['wc_appointments_field_start_date_time'] );
		$data['time']  = date_i18n( wc_appointments_time_format(), strtotime( "{$data['_year']}-{$data['_month']}-{$data['_day']} {$data['_time']}" ) );
	} else {
		$data['_time'] = '';
	}

	// Quantity being scheduled.
	$data['_qty'] = isset( $posted['quantity'] ) ? absint( $posted['quantity'] ) : 1;

	// Timezones.
	// Re-calculate and display customer's timezone and save in site's timezone.
	if ( $product->has_timezones() && isset( $posted['wc_appointments_field_timezone'] ) && $posted['wc_appointments_field_timezone'] && ! empty( $posted['wc_appointments_field_start_date_time'] ) ) {
		$site_tzstring = wc_timezone_string();
		$tzstring      = isset( $_COOKIE['appointments_time_zone'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['appointments_time_zone'] ) ) : 'UTC';
		$tzstring      = $posted['wc_appointments_field_timezone'] ?? $tzstring;

		// Set timezone for saving.
		$data['_local_timezone'] = $tzstring;

		if ( $tzstring !== $site_tzstring ) {
			$timestamp       = strtotime( "{$data['_year']}-{$data['_month']}-{$data['_day']} {$data['_time']}" );
			$local_timestamp = wc_appointment_timezone_locale( 'site', 'user', $timestamp, 'U', $tzstring, true );

			$data['_day']  = date( 'd', $local_timestamp );
			$data['_date'] = date( 'Y-m-d', $local_timestamp );
			$data['_time'] = date( 'H:i', $local_timestamp );

			#print '<pre>'; print_r( date( 'Y-m-d H:i', $timestamp ) ); print '</pre>';
			#print '<pre>'; print_r( date( 'Y-m-d H:i', $local_timestamp ) ); print '</pre>';
		}
	}

	// Set appointment timezone for saving.
	$data['_timezone'] = wc_timezone_string();

	// Fixed duration.
	$duration_unit     = in_array( $product->get_duration_unit(), [ 'minute', 'hour' ] ) ? 'minute' : $product->get_duration_unit();
	$duration_in_mins  = 'hour' === $product->get_duration_unit() ? $product->get_duration() * 60 : $product->get_duration();
	$duration_in_total = 'day' === $product->get_duration_unit() ? $product->get_duration() : $duration_in_mins;
	$duration_in_total = 'month' === $product->get_duration_unit() ? $product->get_duration() : $duration_in_mins;
	$duration_total    = apply_filters( 'appointment_form_posted_total_duration', $duration_in_total, $product, $posted );

	// Display hours and minutes in a readable form.
	if ( 'month' === $product->get_duration_unit() ) {
		/* translators: %s: months */
		$total_duration_n = sprintf( _n( '%s month', '%s months', $duration_total, 'woocommerce-appointments' ), $duration_total );
	} elseif ( 'day' === $product->get_duration_unit() ) {
		/* translators: %s: days */
		$total_duration_n = sprintf( _n( '%s day', '%s days', $duration_total, 'woocommerce-appointments' ), $duration_total );
	} else {
		/* translators: %s: minutes */
		$total_duration_n = wc_appointment_pretty_timestamp( $duration_total, 'minute' );
	}

	#error_log( var_export( 'total_duration_n: ' . $total_duration_n, true ) );

	// Work out start and end dates/times.
	if ( ! empty( $data['_time'] ) ) {
		$data['_start_date'] = strtotime( "{$data['_year']}-{$data['_month']}-{$data['_day']} {$data['_time']}" );
		$data['_end_date']   = strtotime( "+{$duration_total} {$duration_unit}", $data['_start_date'] );
		$data['_all_day']    = 0;
		$data['_duration']   = $duration_total;
		$data['duration']    = $total_duration_n;
	} elseif ( 'night' === $product->get_duration_unit() ) {
		$data['_start_date'] = strtotime( "{$data['_year']}-{$data['_month']}-{$data['_day']}" );
		$data['_end_date']   = strtotime( "+{$duration_total} day", $data['_start_date'] );
		$data['_all_day']    = 0;
	} else {
		$data['_start_date'] = strtotime( "{$data['_year']}-{$data['_month']}-{$data['_day']}" );
		$data['_end_date']   = strtotime( "+{$duration_total} {$duration_unit} - 1 second", $data['_start_date'] );
		$data['_all_day']    = 1;
		$data['_duration']   = $duration_total;
		$data['duration']    = $total_duration_n;
	}


	#error_log( var_export( $data, true ) );

	#$data['duration'] = wc_appointment_pretty_timestamp( $product->get_duration_in_minutes(), $duration_unit );

	#error_log( var_export( $data, true ) );

	// If requested, return data directly
	// before going through expensive resources.
	if ( $get_data ) {
		return $data[ $get_data ] ?? false;
	}

	// Get posted staff or assign one for the date range.
	if ( $product->has_staff() ) {
		if ( $product->is_staff_assignment_type( 'customer' ) && ! empty( $posted['wc_appointments_field_staff'] ) ) {
			$staff = $product->get_staff_member( absint( $posted['wc_appointments_field_staff'] ) );
			if ( $staff ) {
				$data['_staff_id'] = $staff->get_id();
				$data['staff']     = $staff->get_display_name();
			} else {
				$data['_staff_id'] = 0;
			}
		} elseif ( $product->is_staff_assignment_type( 'all' ) ) {
			$staff = $product->get_staff();
			if ( $staff ) {
				$data['_staff_id'] = '';
				$data['staff']     = '';
				foreach ( $staff as $staff_member ) {
					$data['_staff_ids'][]   = $staff_member->get_id();
					$data['_staff_names'][] = $staff_member->get_display_name();
				}
				$data['_staff_id'] = $data['_staff_ids'][0];
				$staff_names       = is_array( $data['_staff_names'] ) ? $data['_staff_names'] : (array) $data['_staff_names'];
				$data['staff']     = implode( ', ', $staff_names );
			} else {
				$data['_staff_id'] = 0;
			}
		} else {
			// Assign an available staff automatically
			$available_appointments = wc_appointments_get_total_available_appointments_for_range(
				$product,
				$data['_start_date'],
				$data['_end_date'],
				0,
				$data['_qty']
			);

			if ( is_array( $available_appointments ) ) {
				$shuffleKeys = array_keys( $available_appointments );
				shuffle( $shuffleKeys ); // randomize
				$staff             = get_user_by( 'id', current( $shuffleKeys ) );
				$data['_staff_id'] = current( $shuffleKeys );
				$data['staff']     = $staff->display_name;
			}
		}
	}

	#error_log( var_export( $data, true ) );

	return apply_filters( 'woocommerce_appointments_get_posted_data', $data, $product, $posted );
}

/**
 * Attempt to convert a date formatting string from PHP to Moment
 *
 * @param string $format
 * @return string
 */
function wc_appointments_convert_to_moment_format( $format ) {
	$replacements = [
		'd' => 'DD',
		'D' => 'ddd',
		'j' => 'D',
		'l' => 'dddd',
		'N' => 'E',
		'S' => 'o',
		'w' => 'e',
		'z' => 'DDD',
		'W' => 'W',
		'F' => 'MMMM',
		'm' => 'MM',
		'M' => 'MMM',
		'n' => 'M',
		't' => '', // no equivalent
		'L' => '', // no equivalent
		'o' => 'YYYY',
		'Y' => 'YYYY',
		'y' => 'YY',
		'a' => 'a',
		'A' => 'A',
		'B' => '', // no equivalent
		'g' => 'h',
		'G' => 'H',
		'h' => 'hh',
		'H' => 'HH',
		'i' => 'mm',
		's' => 'ss',
		'u' => 'SSS',
		'e' => 'zz', // deprecated since version 1.6.0 of moment.js
		'I' => '', // no equivalent
		'O' => '', // no equivalent
		'P' => '', // no equivalent
		'T' => '', // no equivalent
		'Z' => '', // no equivalent
		'c' => '', // no equivalent
		'r' => '', // no equivalent
		'U' => 'X',
	];

	return strtr( $format, $replacements );
}

/**
 * Renders a json object with a paginated availability set.
 *
 * @since 4.5.0
 */
function wc_appointments_paginated_availability( $availability, $page, $records_per_page ) {
	if ( false === $page ) {
		$records = $availability;
	} else {
		$records = array_slice( $availability, ( $page - 1 ) * $records_per_page, $records_per_page );
	}
	$paginated_appointment_slots = [
		'records' => $records,
		'count'   => count( $availability ),
	];

	return $paginated_appointment_slots;
}

/**
 * Return WP's date format, defaulting to a non-empty one if it is unset.
 *
 * @return string
 */
function wc_appointments_date_format() {
	return apply_filters( 'woocommerce_appointments_date_format', wc_date_format() ?: 'F j, Y' );
}

/**
 * Return WP's time format, defaulting to a non-empty one if it is unset.
 *
 * @return string
 */
function wc_appointments_time_format() {
	return apply_filters( 'woocommerce_appointments_time_format', wc_time_format() ?: 'g:i a' );
}

/**
 * Search appointments.
 *
 * @param  string $term Term to search.
 * @return array List of appointments ID.
 */
function wc_appointment_search( $term ) {
	$data_store = WC_Data_Store::load( 'appointment' );
	return $data_store->search_appointments( str_replace( 'Appointment #', '', wc_clean( $term ) ) );
}

/**
 * (via Action Scheduler) Find available and scheduled slots for specific resources (if any) and return them as array.
 *
 * @param  int $product_id
 * @param  integer $min_date The starting date for the set of slots
 * @param  integer $max_date The ending date for the set of slots
 * @param  integer $timezone_offset Timezone offest.
 * @return void
 *
 * @version  4.18.1
 */
add_action( 'wc-appointment-scheduled-update-availability', 'wc_appointments_get_time_slots_scheduled', 10, 8 );
function wc_appointments_get_time_slots_scheduled( $product_id, $min_date, $max_date, $timezone_offset ) {

	// Add meta to mark the event is started.
	$transient_name = 'schedule_ts_' . md5( http_build_query( array( $product_id, 0, $min_date, $max_date, false ) ) );
	update_post_meta( $product_id, 'cache_update_started-' . $transient_name, true );

	// Delete transient, we can't delete it for a specific product,
	// because the same resource may be in use by other products,
	// so we also need to delete their transients, perhaps a work for the future.
	// right now deleting all transients.
	WC_Appointments_Cache::delete_appointment_slots_transient();

	// Re-call the function to generate the latest time slots.
	WC_Appointments_Controller::find_scheduled_day_slots( $product_id, $min_date, $max_date, 'Y-n-j', $timezone_offset, array(), 'action-scheduler' );
}

/**
 * Get global availability rules.
 *
 * @param  bool $with_gcal
 * @return array
 */
function wc_appointments_get_global_availability( $with_gcal = true ) {
	wc_deprecated_function( __METHOD__, '4.7.0', 'WC_Appointments_Availability_Data_Store::get_global_availability()' );
	return WC_Appointments_Availability_Data_Store::get_global_availability( $with_gcal );
}

/**
 * Get staff availability rules.
 *
 * @param  array $staff_ids
 * @return array
 */
function wc_appointments_get_staff_availability( $staff_ids = [] ) {
	wc_deprecated_function( __METHOD__, '4.7.0', 'WC_Appointments_Availability_Data_Store::get_staff_availability()' );
	return WC_Appointments_Availability_Data_Store::get_staff_availability( $staff_ids );
}

/**
 * Find available and scheduled slots for specific staff (if any) and return them as array.
 */
function wc_appointments_get_time_slots( $args ) {
	wc_deprecated_function( __FUNCTION__, '1.15.0', 'WC_Product_Appointment::get_time_slots()' );
	$appointable_product = $args['product'];
	return $appointable_product->get_time_slots( $args );
}

/**
 * Find available slots and return HTML for the user to choose a slot. Used in class-wc-appointments-ajax.php.
 */
function wc_appointments_get_time_slots_html( $args ) {
	wc_deprecated_function( __FUNCTION__, '1.15.0', 'WC_Product_Appointment::get_time_slots_html()' );
	$appointable_product = $args['product'];
	return $appointable_product->get_time_slots_html( $args );
}
