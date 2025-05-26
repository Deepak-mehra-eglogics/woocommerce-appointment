<?php
/**
 * REST API Product slots objects.
 *
 * Handles requests to the /slots endpoint.
 *
 * @package WooCommerce\Appointments\Rest\Controller
 */

/**
 * REST API Products controller class.
 */
class WC_Appointments_REST_Slots_Controller extends WC_REST_Controller {

	use WC_Appointments_Rest_Permission_Check;

	/**
	 * Endpoint namespace.
	 *
	 * @var string
	 */
	protected $namespace = WC_Appointments_REST_API::V1_NAMESPACE;

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'slots';

	/**
	 * Register the route for appointments slots.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_items' ],
					'permission_callback' => '__return_true',
				],
			]
		);
	}

	/**
	 * Abbreviations constants.
	 */
	const AVAILABLE     = 'a';
	const SCHEDULED     = 'b';
	const DATE          = 'd';
	const DURATION      = 'du';
	const DURATION_UNIT = 'duu';
	const ID            = 'i';
	const NAME          = 'n';
	const STAFF         = 's';

	/**
	 * Mapping of abbrieviations to expanded versions of lables.
	 * Used to minimize storred transient size.
	 */
	protected $transient_keys_mapping = [
		self::AVAILABLE     => 'available',
		self::SCHEDULED     => 'scheduled',
		self::DATE          => 'date',
		self::DURATION      => 'duration',
		self::DURATION_UNIT => 'duration_unit',
		self::STAFF         => 'staff_id',
		self::ID            => 'product_id',
		self::NAME          => 'product_name',
	];

	/**
	 * @param $availablity with abbreviated lables.
	 *
	 * @return object with lables expanded to their full version.
	 */
	public function transient_expand( $availability ) {
		$expanded_availability = [];
		foreach ( $availability['records'] as $key => $slot ) {
			$expanded_slot = [];
			foreach ( $slot as $abbrieviation  => $value ) {
				$expanded_slot[ $this->transient_keys_mapping[ $abbrieviation ] ] = $value;
			}
			$expanded_availability[] = $expanded_slot;
		}

		return [
			'records' => $expanded_availability,
			'count'   => $availability['count'],
		];
	}

	/**
	 * Format timestamp to the shortest reasonable format usable in API.
	 *
	 * @param $timestamp
	 * @param $timezone DateTimeZone
	 *
	 * @return string
	 */
	public function get_time( $timestamp, $timezone ) {
		$server_time = new DateTime( date( 'Y-m-d\TH:i:s', $timestamp ), $timezone );

		return $server_time->format( "Y-m-d\TH:i" );
	}

	/**
	 * Get available appointments slots.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_items( $request ) {
		$product_ids    = ! empty( $request['product_ids'] ) ? array_map( 'absint', explode( ',', $request['product_ids'] ) ) : [];
		$category_ids   = ! empty( $request['category_ids'] ) ? array_map( 'absint', explode( ',', $request['category_ids'] ) ) : [];
		$staff_ids      = ! empty( $request['staff_ids'] ) ? array_map( 'absint', explode( ',', $request['staff_ids'] ) ) : [];
		$get_past_times = isset( $request['get_past_times'] ) && 'true' === $request['get_past_times'] ? true : false;

		$min_date = isset( $request['min_date'] ) ? strtotime( urldecode( $request['min_date'] ) ) : 0;
		$max_date = isset( $request['max_date'] ) ? strtotime( urldecode( $request['max_date'] ) ) : 0;
		$timezone = new DateTimeZone( wc_timezone_string() );

		$page             = isset( $request['page'] ) ? absint( $request['page'] ) : false;
		$records_per_page = isset( $request['limit'] ) ? absint( $request['limit'] ) : 10;
		$hide_unavailable = isset( $request['hide_unavailable'] ) && 'true' === $request['hide_unavailable'] ? true : false;

		// If no product ids are specified, just use all products.
		if ( empty( $product_ids ) ) {
			$product_ids = WC_Data_Store::load( 'product-appointment' )->get_appointable_product_ids();
		}

		$products = array_filter(
			array_map(
				function( $product_id ) {
					return wc_get_product( $product_id );
				},
				$product_ids
			)
		);

		foreach ( $products as $product ) {
			$is_vaild_rest_type = 'appointment' === $product->get_type();
			$is_vaild_rest_type = apply_filters( "woocommerce_appointments_product_type_rest_check", $is_vaild_rest_type, $product );
			if ( ! $is_vaild_rest_type ) {
				wp_send_json( __( 'Not an appointable product', 'woocommerce-appointments' ), 400 );
			}
		}

		// If category ids are specified filter the product ids.
		if ( ! empty( $category_ids ) ) {
			$products = array_filter(
				$products,
				function( $product ) use ( $category_ids ) {
					$product_id = $product->get_id();

					return array_reduce(
						$category_ids,
						function( $is_in_category, $category_id ) use ( $product_id ) {
							$term = get_term_by( 'id', $category_id, 'product_cat' );

							if ( ! $term ) {
								return $is_in_category;
							}

							return $is_in_category || has_term( $term, 'product_cat', $product_id );
						},
						false
					);
				}
			);
		}

		// Get product ids from products after they filtered by categories.
		$product_ids = array_filter(
			array_map(
				function( $product ) {
					return $product->get_id();
				},
				$products
			)
		);

		$transient_name                   = 'appointment_slots_' . md5( http_build_query( [ $product_ids, $category_ids, $staff_ids, $min_date, $max_date, $page, $records_per_page ] ) );
		$appointment_slots_transient_keys = array_filter( (array) WC_Appointments_Cache::get( 'appointment_slots_transient_keys' ) );
		$cached_availabilities            = WC_Appointments_Cache::get( $transient_name );

		if ( $cached_availabilities ) {
			$availability = wc_appointments_paginated_availability( $cached_availabilities, $page, $records_per_page );
			return $this->transient_expand( $availability );
		}

		$needs_cache_set = false;
		foreach ( $product_ids as $product_id ) {
			if ( ! isset( $appointment_slots_transient_keys[ $product_id ] ) ) {
				$appointment_slots_transient_keys[ $product_id ] = [];
			}

			// Don't store in cache if it already exists there.
			if ( ! in_array( $transient_name, $appointment_slots_transient_keys[ $product_id ] ) ) {
				$appointment_slots_transient_keys[ $product_id ][] = $transient_name;
				$needs_cache_set                                   = true;
			}
		}

		// Only set cache if existing cached data has changed.
		if ( $needs_cache_set ) {
			// Give array of keys a long ttl because if it expires we won't be able to flush the keys when needed.
			// We can't use 0 to never expire because then WordPress will autoload the option on every page.
			WC_Appointments_Cache::set( 'appointment_slots_transient_keys', $appointment_slots_transient_keys, YEAR_IN_SECONDS );
		}

		// Calculate partially scheduled/fully scheduled/unavailable days for each product.
		$scheduled_data = array_values(
			array_map(
				function( $appointable_product ) use ( $min_date, $max_date, $staff_ids, $get_past_times, $timezone, $hide_unavailable ) {
					// Determine a min date.
					if ( empty( $min_date ) ) {
						$min_date = strtotime( 'today' );
					}

					// Get Minimum appointable time in the future.
					$product_min_date     = $appointable_product->get_min_date_a();
					$min_appointable_date = strtotime( "+{$product_min_date['value']} {$product_min_date['unit']}", current_time( 'timestamp' ) );

					// Reset $min_date if it's before the minimum appointable date/time in the future.
					if ( $min_date < $min_appointable_date ) {
						$min_date = $min_appointable_date;
					}

					// Determine a max date.
					if ( empty( $max_date ) ) {
						$max_date = strtotime( 'tomorrow' );
					}

					// Get Maximum appointable time in the future.
					$product_max_date     = $appointable_product->get_max_date_a();
					$max_appointable_date = strtotime( "+{$product_max_date['value']} {$product_max_date['unit']}", current_time( 'timestamp' ) );

					// Reset $max_date to the next dat of the $min_date if it is smaller.
					if ( $max_date < $min_date ) {
						$max_date = strtotime( '+1 day', $min_date );
					}

					// Reset $max_date if it's after the maximum appointable date/time in the future.
					if ( $max_date > $max_appointable_date ) {
						$max_date = $max_appointable_date;
					}

					$product_staff      = $appointable_product->get_staff_ids() ?: [];
					$catalog_visibility = $appointable_product->get_catalog_visibility();
					$duration           = $appointable_product->get_duration();
					$duration_unit      = $appointable_product->get_duration_unit();
					$availability       = [];

					$staff = empty( $product_staff ) ? [ 0 ] : $product_staff;
					if ( ! empty( $staff_ids ) ) {
						$staff = array_intersect( $staff, $staff_ids );
					}

					// Get slots for days before and after, which accounts for timezone differences.
					$start_date = strtotime( '-1 day', $min_date );
					$end_date   = strtotime( '+1 day', $max_date );

					foreach ( $staff as $staff_id ) {
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
						$slots           = $appointable_product->get_slots_in_range( $start_date, $end_date, [], $staff_id, [], $get_past_times );
						$available_slots = $appointable_product->get_time_slots(
							[
								'slots'            => $slots,
								'staff_id'         => $staff_id,
								'from'             => $start_date,
								'to'               => $end_date,
								'appointments'     => $appointments,
								'include_sold_out' => true,
							]
						);

						foreach ( $available_slots as $timestamp => $data ) {
							// Filter slots outside of timerange.
							if ( $timestamp < $min_date || $timestamp >= $max_date ) {
								continue;
							}

							unset( $data['staff'] );

							if ( ( ! $hide_unavailable || 1 <= $data['available'] ) && 'hidden' !== $catalog_visibility ) {
								/**
								 * For the day duration, check if the required duration is available in the following days or not
								 * For example, for 3 days duration, check if the starting day has the following 3 days available or not.
								 * If it's available, the starting day should be considered available, otherwise not.
								 */
								if ( 'day' === $duration_unit ) {
									$day_timestamp = $timestamp;
									for ( $i = 1; $i < $duration; $i ++ ) {
										$day_timestamp = strtotime( '+1 day', $day_timestamp );
										if ( ! isset( $available_slots[ $day_timestamp ] ) ) {
											continue 2;
										}
									}
								}

								$availability[] = [
									self::DATE          => $this->get_time( $timestamp, $timezone ),
									self::DURATION      => $duration,
									self::DURATION_UNIT => $duration_unit,
									self::AVAILABLE     => $data['available'],
									self::SCHEDULED     => $data['scheduled'],
									self::STAFF         => $staff_id,
								];

							}
						}
					}

					$data = [
						'product_id'    => $appointable_product->get_id(),
						'product_title' => $appointable_product->get_title(),
						'availability'  => $availability,
					];

					return $data;
				},
				$products
			)
		);

		$scheduled_data = apply_filters( 'woocommerce_appointments_rest_slots_get_items', $scheduled_data );

		$cached_availabilities = array_merge(
			...array_map(
				function( $value ) {
					return array_map(
						function( $availability ) use ( $value ) {
							$availability[ self::ID ]   = $value['product_id'];
							$availability[ self::NAME ] = $value['product_title'];
							return $availability;
						},
						$value['availability']
					);
				},
				$scheduled_data
			)
		);

		// Sort by date.
		usort(
			$cached_availabilities,
			function( $a, $b ) {
				return $a[ self::DATE ] <=> $b[ self::DATE ];
			}
		);

		// This transient should be cleared when appointment or products are added or updated but keep it short just in case.
		WC_Appointments_Cache::set( $transient_name, $cached_availabilities, HOUR_IN_SECONDS );

		$availability = wc_appointments_paginated_availability( $cached_availabilities, $page, $records_per_page );

		return $this->transient_expand( $availability );
	}
}
