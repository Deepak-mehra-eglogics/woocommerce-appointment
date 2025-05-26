<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * WC Appointment Data Store: Stored in CPT.
 *
 * @todo When 2.6 support is dropped, implement WC_Object_Data_Store_Interface
 */
class WC_Appointment_Data_Store extends WC_Data_Store_WP {

	/**
	 * Meta keys and how they transfer to CRUD props.
	 *
	 * @var array
	 */
	private $appointment_meta_key_to_props = [
		'_appointment_all_day'                  => 'all_day',
		'_appointment_cost'                     => 'cost',
		'_appointment_customer_id'              => 'customer_id',
		'_appointment_order_item_id'            => 'order_item_id',
		'_appointment_parent_id'                => 'parent_id',
		'_appointment_product_id'               => 'product_id',
		'_appointment_staff_id'                 => 'staff_ids',
		'_appointment_start'                    => 'start',
		'_appointment_end'                      => 'end',
		'_wc_appointments_gcal_event_id'        => 'google_calendar_event_id',
		'_wc_appointments_gcal_staff_event_ids' => 'google_calendar_staff_event_ids',
		'_appointment_customer_status'          => 'customer_status',
		'_appointment_qty'                      => 'qty',
		'_appointment_timezone'                 => 'timezone',
		'_local_timezone'                       => 'local_timezone',
	];

	/*
	|--------------------------------------------------------------------------
	| CRUD Methods
	|--------------------------------------------------------------------------
	*/

	/**
	 * Method to create a new appointment in the database.
	 *
	 * @param WC_Appointment $appointment
	 */
	public function create( &$appointment ) {
		if ( ! $appointment->get_date_created( 'edit' ) ) {
			$appointment->set_date_created( current_time( 'timestamp' ) );
		}

		// @codingStandardsIgnoreStart
		$id = wp_insert_post(
			apply_filters(
				'woocommerce_new_appointment_data',
				[
					'post_date'     => date(
						'Y-m-d H:i:s',
						$appointment->get_date_created( 'edit' )
					),
					'post_date_gmt' => get_gmt_from_date(
						date(
							'Y-m-d H:i:s',
							$appointment->get_date_created( 'edit' )
						)
					),
					'post_type'     => 'wc_appointment',
					'post_status'   => $appointment->get_status( 'edit' ),
					'post_author'   => $appointment->get_customer_id( 'edit' ),
					'post_title'    => sprintf(
						/* translators: %s: Appointment date */
						__( 'Appointment &ndash; %s', 'woocommerce-appointments' ),
						date(
							/* translators: Appointment date format parsed by date() function */
							_x( 'M d, Y @ H:i a', 'Appointment date parsed by date()', 'woocommerce-appointments' ),
							$appointment->get_date_created( 'edit' )
						)
					),
					'post_parent'   => $appointment->get_order_id( 'edit' ),
					'ping_status'   => 'closed',
				]
			),
			true
		);
		// @codingStandardsIgnoreEnd

		if ( $id && ! is_wp_error( $id ) ) {
			$appointment->set_id( $id );
			$this->update_post_meta( $appointment );
			$appointment->save_meta_data();
			$appointment->apply_changes();
			WC_Cache_Helper::get_transient_version( 'appointments', true );

			do_action( 'woocommerce_new_appointment', $appointment->get_id() );
		}

		// Stop deleting the transient if the product is added to the cart.
		// Action Scheduler will be used to update new availability.
		// Add some meta to track that this product requires updating.
		$product_id = filter_input( INPUT_POST, 'add-to-cart', FILTER_SANITIZE_NUMBER_INT );
		$min_date   = isset( $_POST['min_date'] ) ? strtotime( wc_clean( wp_unslash( $_POST['min_date'] ) ) ) : '';
		$max_date   = isset( $_POST['max_date'] ) ? strtotime( wc_clean( wp_unslash( $_POST['max_date'] ) ) ) : '';

		// If $min_date or $max_date are somehow was not updated by the JS, stop and clear transient.
		if ( $product_id && $min_date && $max_date ) {
			$product_or_staff_id = $appointment->has_staff() ? $appointment->get_staff_ids() : $product_id;
			$timezone_offset     = isset( $_GET['timezone_offset'] ) ? wc_clean( wp_unslash( $_GET['timezone_offset'] ) ) : 0;

			/**
			 * Filter the maximum number of appointments before scheduling the transient delete.
			 *
			 * @since 4.18.1
			 *
			 * @param int Number of maximum booklings.
			 * @param int $product_or_staff_id Product or the Staff ID.
			 * @param int $min_date Start date timestamp.
			 * @param int $max_date End date timestamp.
			 */
			$max_appointment_count = apply_filters( 'woocommerce_appointments_max_appointments_to_delete_transient', 1000, (int) $product_or_staff_id, $min_date, $max_date );

			// If existing appointments' count is less than 1000, delete the transient now,
			// otherwise schedule it via an action scheduler.
			$existing_appointments = self::get_appointments_in_date_range( $min_date, $max_date, $product_or_staff_id, true );
			if ( count( $existing_appointments ) < $max_appointment_count ) {
				WC_Appointments_Cache::delete_appointment_slots_transient();
			} else {
				// Call the function with an extra argument to prepare data and schedule an event later.
				WC_Appointments_Controller::find_scheduled_day_slots( (int) $_POST['add-to-cart'], $min_date, $max_date, 'Y-n-j', $timezone_offset, array(), 'action-scheduler-helper' );
			}
		} else {
			WC_Appointments_Cache::delete_appointment_slots_transient();
		}
	}

	/**
	 * Method to read an order from the database.
	 *
	 * @param WC_Appointment
	 */
	public function read( &$appointment ) {
		$appointment->set_defaults();
		$appointment_id = $appointment->get_id();
		$post_object    = $appointment_id ? get_post( $appointment_id ) : false;

		if ( ! $appointment_id || ! $post_object || 'wc_appointment' !== $post_object->post_type ) {
			throw new Exception(
				__( 'Invalid appointment.', 'woocommerce-appointments' )
			);
		}

		$set_props = [];

		// Read post data.
		$set_props['date_created']  = $post_object->post_date;
		$set_props['date_modified'] = $post_object->post_modified;
		$set_props['status']        = $post_object->post_status;
		$set_props['order_id']      = $post_object->post_parent;

		// Read meta data.
		foreach ( $this->appointment_meta_key_to_props as $key => $prop ) {
			$value = get_post_meta( $appointment->get_id(), $key, true );

			switch ( $prop ) {
				case 'end':
				case 'start':
					#error_log( var_export( $value ? ( (bool) strtotime( $value ) ? strtotime( $value ) : $value ) : '', true ) );
					$set_props[ $prop ] = $value ? strtotime( $value ) : 0;
					break;
				case 'all_day':
					$set_props[ $prop ] = wc_appointments_string_to_bool( $value );
					break;
				case 'staff_ids':
					// Staff can be saved multiple times to same meta key.
					$value              = get_post_meta( $appointment->get_id(), $key, false );
					$set_props[ $prop ] = $value;
					break;
				default:
					$set_props[ $prop ] = $value;
					break;
			}
		}

		#error_log( var_export( $set_props, true ) );

		$appointment->set_props( $set_props );
		$appointment->set_object_read( true );
	}

	/**
	 * Method to update an order in the database.
	 *
	 * @param WC_Appointment $appointment
	 */
	public function update( &$appointment ) {
		wp_update_post(
			[
				'ID'            => $appointment->get_id(),
				'post_date'     => date(
					'Y-m-d H:i:s',
					$appointment->get_date_created( 'edit' )
				),
				'post_date_gmt' => get_gmt_from_date(
					date(
						'Y-m-d H:i:s',
						$appointment->get_date_created( 'edit' )
					)
				),
				'post_status'   => $appointment->get_status( 'edit' ),
				'post_author'   => $appointment->get_customer_id( 'edit' ),
				'post_parent'   => $appointment->get_order_id( 'edit' ),
			]
		);
		$this->update_post_meta( $appointment );
		$appointment->save_meta_data();
		$appointment->apply_changes();
		WC_Cache_Helper::get_transient_version( 'appointments', true );
		WC_Appointments_Cache::flush_all_appointment_connected_transients( $appointment );
	}

	/**
	 * Method to delete an appointment from the database.
	 * @param WC_Appointment
	 * @param array $args Array of args to pass to the delete method.
	 */
	public function delete( &$appointment, $args = [] ) {
		$id   = $appointment->get_id();
		$args = wp_parse_args(
			$args,
			[
				'force_delete' => false,
			]
		);

		WC_Appointments_Cache::flush_all_appointment_connected_transients( $appointment );

		if ( $args['force_delete'] ) {
			wp_delete_post( $id );
			$appointment->set_id( 0 );
			do_action( 'woocommerce_delete_appointment', $id );
		} else {
			wp_trash_post( $id );
			$appointment->set_status( 'trash' );
			do_action( 'woocommerce_trash_appointment', $id );
		}
	}

	/**
	 * Helper method that updates all the post meta for an appointment based on it's settings in the WC_Appointment class.
	 *
	 * @param WC_Appointment
	 */
	protected function update_post_meta( &$appointment ) {
		foreach ( $this->appointment_meta_key_to_props as $key => $prop ) {
			if ( is_callable( [ $appointment, "get_$prop" ] ) ) {
				$value = $appointment->{ "get_$prop" }( 'edit' );

				switch ( $prop ) {
					case 'all_day':
						update_post_meta( $appointment->get_id(), $key, $value ? 1 : 0 );
						break;
					case 'end':
					case 'start':
						update_post_meta( $appointment->get_id(), $key, $value ? date( 'YmdHis', $value ) : '' );
						break;
					case 'staff_ids':
						delete_post_meta( $appointment->get_id(), $key );
						if ( is_array( $value ) ) {
							foreach ( $value as $staff_id ) {
								add_post_meta( $appointment->get_id(), '_appointment_staff_id', $staff_id );
							}
						} elseif ( is_numeric( $value ) ) {
							add_post_meta( $appointment->get_id(), '_appointment_staff_id', $value );
						}
						break;
					case 'google_calendar_staff_event_ids':
						if ( $value && is_array( $value ) ) {
							$new_value = [];
							foreach ( $value as $staff_id => $event_id ) {
								$new_value[ $staff_id ] = $event_id;
							}
							$current_value = get_post_meta( $appointment->get_id(), $key, true );
							if ( $current_value && is_array( $current_value ) ) {
								foreach ( $current_value as $c_staff_id => $c_event_id ) {
									$new_value[ $c_staff_id ] = $c_event_id;
								}
							}
							update_post_meta( $appointment->get_id(), $key, $new_value );
						}
						// Delete.
						if ( ! $value ) {
							delete_post_meta( $appointment->get_id(), $key );
						}
						break;
					default:
						update_post_meta( $appointment->get_id(), $key, $value );
						break;
				}
			}
		}
	}

	/**
	 * For a given order ID, get all appointments that belong to it.
	 * DO NOT CACHE
	 *
	 * @param  int|array $order_id
	 *
	 * @return int[] Array of appointment IDs.
	 */
	public static function get_appointment_ids_from_order_id( $order_id ) {
		global $wpdb;

		// Search multiple in multiple order IDs.
		if ( is_array( $order_id ) ) {
			global $wpdb;

			$order_ids = wp_parse_id_list( $order_id );

			$data = wp_parse_id_list(
				$wpdb->get_col(
					"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'wc_appointment' AND post_parent IN (" . implode( ',', array_map( 'esc_sql', $order_ids ) ) . ");"
				)
			);

			return $data;

		// Search in single order ID.
		} else {
			global $wpdb;

			$order_id = absint( $order_id );

			$data = wp_parse_id_list(
				$wpdb->get_col(
					$wpdb->prepare(
						"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'wc_appointment' AND post_parent = %d;",
						$order_id
					)
				)
			);

			return $data;
		}
	}

	/**
	 * For a given order item ID, get all appointments that belong to it.
	 * DO NOT CACHE
	 *
	 * @param  int $order_item_id
	 * @return array
	 */
	public static function get_appointment_ids_from_order_item_id( $order_item_id, $no_cache = false ) {
		$order_item_id = absint( $order_item_id );

		// Try getting the appointment IDs from order item meta.
		$appointment_ids = wc_get_order_item_meta( $order_item_id, '_appointment_id', true );

		// If IDs are not cached, make the database query.
		if ( empty( $appointment_ids ) ) {
			global $wpdb;

			$appointment_ids = wp_parse_id_list(
				$wpdb->get_col(
					$wpdb->prepare(
						"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_appointment_order_item_id' AND meta_value = %d;",
						$order_item_id
					)
				)
			);

			// Save meta for future use.
			wc_update_order_item_meta( $order_item_id, '_appointment_id', $appointment_ids );
		}

		// Make sure it is always an array.
		if ( $appointment_ids && ! is_array( $appointment_ids ) ) {
			$appointment_ids = array( $appointment_ids );
		}

		return $appointment_ids;
	}

	/**
	 * For a given order item ID and order ID, get all appointments that belong to both.
	 *
	 * @param  int $order_item_id
	 * @return array
	 */
	public static function get_appointment_ids_from_order_and_item_id( $order_id = 0, $order_item_id = 0 ) {
		$appointment_ids_i = self::get_appointment_ids_from_order_item_id( $order_item_id );
		$appointment_ids_o = self::get_appointment_ids_from_order_id( $order_id );

		#echo '<pre>' . var_export( $appointment_ids_i, true ) . '</pre>';

		// Remove appointments that are from different orders.
		if ( $appointment_ids_i && $appointment_ids_o ) {
			return array_intersect( $appointment_ids_i, $appointment_ids_o );
		} elseif ( $appointment_ids_i ) {
			return (array) $appointment_ids_i;
		} else {
			return [];
		}
	}

	/**
	 * Check if a given order contains only Appointments items.
	 * If the order contains non-appointment items, it will return false.
	 * Otherwise, it will return an array of Appointments.
	 *
	 * @param  WC_Order $order
	 * @return bool|array
	 */
	public static function get_order_contains_only_appointments( $order ) {
		$all_appointment_ids = [];

		foreach ( array_keys( $order->get_items() ) as $order_item_id ) {
			$appointment_ids = self::get_appointment_ids_from_order_item_id( $order_item_id );

			if ( empty( $appointment_ids ) ) {
				return false;
			}

			$all_appointment_ids = array_merge( $all_appointment_ids, $appointment_ids );
		}

		return $all_appointment_ids;
	}

	/**
	 * Get appointment ids for an object  by ID. e.g. product.
	 * DO NOT CACHE
	 *
	 * @param  array
	 * @return array
	 */
	public static function get_appointment_ids_by( $filters = [] ) {
		global $wpdb;

		$filters = wp_parse_args(
			$filters,
			[
				'object_id'     => 0,
				'product_id'    => 0,
				'staff_id'      => 0,
				'object_type'   => 'product',
				'strict'        => false,
				'status'        => false,
				'limit'         => -1,
				'offset'        => 0,
				'order_by'      => 'date_created',
				'order'         => 'DESC',
				'date_before'   => false,
				'date_after'    => false,
				'gcal_event_id' => false,
				'date_between'  => [
					'start' => false,
					'end'   => false,
				],
			]
		);

		// Product and staff.
		$filters['product_id'] = $filters['product_id'] ? $filters['product_id'] : $filters['object_id'];
		$filters['staff_id']   = $filters['staff_id'] ? $filters['staff_id'] : $filters['object_id'];

		$meta_keys             = [];
		$query_where           = [ 'WHERE 1=1', "p.post_type = 'wc_appointment'" ];
		$filters['object_id']  = array_filter( wp_parse_id_list( is_array( $filters['object_id'] ) ? $filters['object_id'] : [ $filters['object_id'] ] ) );
		$filters['product_id'] = array_filter( wp_parse_id_list( is_array( $filters['product_id'] ) ? $filters['product_id'] : [ $filters['product_id'] ] ) );
		$filters['staff_id']   = array_filter( wp_parse_id_list( is_array( $filters['staff_id'] ) ? $filters['staff_id'] : [ $filters['staff_id'] ] ) );

		#echo '<pre>' . var_export( $filters['product_id'], true ) . '</pre>';
		#echo '<pre>' . var_export( $filters['staff_id'], true ) . '</pre>';
		#echo '<pre>' . var_export( $filters, true ) . '</pre>';

		switch ( $filters['object_type'] ) {
			case 'product':
			case 'product_and_staff':
				if ( ! empty( $filters['product_id'] ) && ! empty( $filters['staff_id'] ) ) {
					$meta_keys[] = '_appointment_product_id';
					$meta_keys[] = '_appointment_staff_id';
					// If strict, staff and product must exist.
					if ( ! empty( $filters['strict'] ) && $filters['strict'] ) {
						$query_where[] = "( (
							_appointment_product_id.meta_value IN ('" . implode( "','", array_map( 'esc_sql', $filters['product_id'] ) ) . "')
						) AND (
							_appointment_staff_id.meta_value IN ('" . implode( "','", array_map( 'esc_sql', $filters['staff_id'] ) ) . "')
						) )";
					} else {
						$query_where[] = "( (
							_appointment_product_id.meta_value IN ('" . implode( "','", array_map( 'esc_sql', $filters['product_id'] ) ) . "')
						) OR (
							_appointment_staff_id.meta_value IN ('" . implode( "','", array_map( 'esc_sql', $filters['staff_id'] ) ) . "')
						) )";
					}
				} elseif ( ! empty( $filters['product_id'] ) ) {
					$meta_keys[]   = '_appointment_product_id';
					$query_where[] = "_appointment_product_id.meta_value IN ('" . implode( "','", array_map( 'esc_sql', $filters['product_id'] ) ) . "')";
				} elseif ( ! empty( $filters['staff_id'] ) ) {
					$meta_keys[]   = '_appointment_staff_id';
					$query_where[] = "_appointment_staff_id.meta_value IN ('" . implode( "','", array_map( 'esc_sql', $filters['staff_id'] ) ) . "')";
				}
				break;
			case 'staff':
				if ( ! empty( $filters['staff_id'] ) ) {
					$meta_keys[]   = '_appointment_staff_id';
					$query_where[] = "_appointment_staff_id.meta_value IN ('" . implode( "','", array_map( 'esc_sql', $filters['staff_id'] ) ) . "')";
				}
				break;
			case 'customer':
				if ( ! empty( $filters['object_id'] ) ) {
					$meta_keys[]   = '_appointment_customer_id';
					$query_where[] = "_appointment_customer_id.meta_value IN ('" . implode( "','", array_map( 'esc_sql', $filters['object_id'] ) ) . "')";
				}
				break;
		}

		// Status.
		if ( ! empty( $filters['status'] ) ) {
			$query_where[] = "p.post_status IN ('" . implode( "','", $filters['status'] ) . "')";
		}

		// Google Calendar event ID.
		if ( ! empty( $filters['gcal_event_id'] ) ) {
			$meta_keys[]   = '_wc_appointments_gcal_event_id';
			$query_where[] = "_wc_appointments_gcal_event_id.meta_value IN ('" .
				implode(
					"','",
					array_map(
						'esc_sql',
						(array) $filters['gcal_event_id']
					)
				)
				. "')";
		}

		// Post Date.
		if ( ! empty( $filters['post_date_before'] ) ) {
			$query_where[] = "p.post_date < '" . esc_sql( date( 'YmdHis', $filters['post_date_before'] ) ) . "'";
		}
		if ( ! empty( $filters['post_date_after'] ) ) {
			$query_where[] = "p.post_date > '" . esc_sql( date( 'YmdHis', $filters['post_date_after'] ) ) . "'";
		}

		// Date between.
		if ( ! empty( $filters['date_between']['start'] ) && ! empty( $filters['date_between']['end'] ) ) {
			$meta_keys[]   = '_appointment_start';
			$meta_keys[]   = '_appointment_end';
			$meta_keys[]   = '_appointment_all_day';
			$query_where[] = "( (
				_appointment_start.meta_value < '" . esc_sql( date( 'YmdHis', $filters['date_between']['end'] ) ) . "' AND
				_appointment_end.meta_value > '" . esc_sql( date( 'YmdHis', $filters['date_between']['start'] ) ) . "' AND
				_appointment_all_day.meta_value = '0'
			) OR (
				_appointment_start.meta_value <= '" . esc_sql( date( 'Ymd000000', $filters['date_between']['end'] ) ) . "' AND
				_appointment_end.meta_value >= '" . esc_sql( date( 'Ymd000000', $filters['date_between']['start'] ) ) . "' AND
				_appointment_all_day.meta_value = '1'
			) )";
		}

		if ( ! empty( $filters['date_after'] ) ) {
			$meta_keys[]   = '_appointment_start';
			$query_where[] = "_appointment_start.meta_value > '" . esc_sql( date( 'YmdHis', $filters['date_after'] ) ) . "'";
		}

		if ( ! empty( $filters['date_before'] ) ) {
			$meta_keys[]   = '_appointment_end';
			$query_where[] = "_appointment_end.meta_value < '" . esc_sql( date( 'YmdHis', $filters['date_before'] ) ) . "'";
		}

		if ( ! empty( $filters['order_by'] ) ) {
			switch ( $filters['order_by'] ) {
				case 'date_created':
					$filters['order_by'] = 'p.post_date';
					break;
				case 'start_date':
					$meta_keys[]         = '_appointment_start';
					$filters['order_by'] = '_appointment_start.meta_value';
					break;
			}
			$query_order = ' ORDER BY ' . esc_sql( $filters['order_by'] ) . ' ' . esc_sql( $filters['order'] );
		} else {
			$query_order = '';
		}

		if ( $filters['limit'] > 0 ) {
			$query_limit = ' LIMIT ' . absint( $filters['offset'] ) . ',' . absint( $filters['limit'] );
		} else {
			$query_limit = '';
		}

		$query_select = "SELECT p.ID FROM {$wpdb->posts} p";
		$meta_keys    = array_unique( $meta_keys );
		$query_where  = implode( ' AND ', $query_where );

		foreach ( $meta_keys as $index => $meta_key ) {
			$key           = esc_sql( $meta_key );
			$query_select .= " LEFT JOIN {$wpdb->postmeta} {$key} ON p.ID = {$key}.post_id AND {$key}.meta_key = '{$key}'";
		}

		#echo '<pre>' . var_export( "{$query_select} {$query_where} {$query_order} {$query_limit};", true ) . '</pre>';
		#error_log( var_export( current_time( 'Y-m-d H:i:s.u' ), true ) );

		$data = array_filter(
			wp_parse_id_list(
				$wpdb->get_col(
					"{$query_select} {$query_where} {$query_order} {$query_limit};"
				)
			)
		);

		#error_log( var_export( current_time( 'Y-m-d H:i:s.u' ), true ) );

		return $data;
	}

	/**
	 * For a given appointment ID, get it's linked order ID if set.
	 *
	 * @param  int $appointment_id
	 *
	 * @return int
	 */
	public static function get_appointment_order_id( $appointment_id ) {
		return absint( wp_get_post_parent_id( $appointment_id ) );
	}

	/**
	 * For a given appointment ID, get it's linked order item ID if set.
	 *
	 * @param  int $appointment_id
	 * @return int
	 */
	public static function get_appointment_order_item_id( $appointment_id ) {
		return absint( get_post_meta( $appointment_id, '_appointment_order_item_id', true ) );
	}

	/**
	 * For a given appointment ID, get it's linked order customer ID if set.
	 *
	 * @param  int $appointment_id
	 * @return int
	 */
	public static function get_appointment_customer_id( $appointment_id ) {
		return absint( get_post_meta( $appointment_id, '_appointment_customer_id', true ) );
	}

	/**
	 * Gets appointments for product ids and staff ids
	 * @param  array    $product_ids
	 * @param  array    $staff_ids
	 * @param  array    $status
	 * @param  integer  $date_from
	 * @param  integer  $date_to
	 *
	 * @return array of WC_Appointment objects
	 */
	public static function get_appointments_for_objects_query( $product_ids, $staff_ids, $status, $date_from = 0, $date_to = 0 ) {
		$status    = ! empty( $status ) ? $status : get_wc_appointment_statuses( 'fully_scheduled' );
		$date_from = ! empty( $date_from ) ? $date_from : strtotime( 'midnight', current_time( 'timestamp' ) );
		$date_to   = ! empty( $date_to ) ? $date_to : strtotime( '+12 month', current_time( 'timestamp' ) );

		// Filter the arguments
		$args = apply_filters(
			'woocommerce_appointments_for_objects_query_args',
			[
				'status'       => $status,
				'product_id'   => $product_ids,
				'staff_id'     => $staff_ids,
				'object_type'  => 'product_and_staff',
				'date_between' => [
					'start' => $date_from,
					'end'   => $date_to,
				],
			]
		);

		$appointment_ids = self::get_appointment_ids_by( $args );

		return apply_filters( 'woocommerce_appointments_for_objects_query', $appointment_ids );
	}

	/**
	 * Gets appointments for product ids and staff ids
	 * @param  array    $product_ids
	 * @param  array    $staff_ids
	 * @param  array    $status
	 * @param  integer  $date_from
	 * @param  integer  $date_to
	 *
	 * @return array of WC_Appointment objects
	 */
	public static function get_appointments_for_objects( $product_ids = [], $staff_ids = [], $status = [], $date_from = 0, $date_to = 0 ) {
		// TODO: We need to round date_from/date_to to something specific.
		// Otherwise, one might abuse the DB transient cache by calling various combinations from the front-end with min-date/max-date.
		$transient_name  = 'schedule_fo_' . md5( http_build_query( [ $product_ids, $staff_ids, $date_from, $date_to, WC_Cache_Helper::get_transient_version( 'appointments' ) ] ) );
		$status          = ( ! empty( $status ) ) ? $status : get_wc_appointment_statuses( 'fully_scheduled' );
		$date_from       = ! empty( $date_from ) ? $date_from : strtotime( 'midnight', current_time( 'timestamp' ) );
		$date_to         = ! empty( $date_to ) ? $date_to : strtotime( '+12 month', current_time( 'timestamp' ) );
		$appointment_ids = WC_Appointments_Cache::get( $transient_name );

		if ( false === $appointment_ids ) {
			$appointment_ids = self::get_appointments_for_objects_query( $product_ids, $staff_ids, $status, $date_from, $date_to );
			WC_Appointments_Cache::set( $transient_name, $appointment_ids, DAY_IN_SECONDS * 30 );
		}

		#echo '<pre>' . var_export( $appointment_ids, true ) . '</pre>';

		// Get objects.
		if ( ! empty( $appointment_ids ) ) {
			return array_map( 'get_wc_appointment', wp_parse_id_list( $appointment_ids ) );
		}

		return [];
	}

	/**
	 * Finds existing appointments for a product and its tied staff.
	 *
	 * @param  int|WC_Product_Appointment $appointable_product  Product ID or object
	 * @param  int                        $min_date
	 * @param  int                        $max_date
	 * @param  array                      $staff_ids
	 *
	 * @return array
	 */
	public static function get_all_existing_appointments( $appointable_product, $min_date = 0, $max_date = 0, $staff_ids = [] ) {
		if ( is_int( $appointable_product ) ) {
			$appointable_product = wc_get_product( $appointable_product );
		}
		$find_appointments_for_product = [ $appointable_product->get_id() ];
		$find_appointments_for_staff   = [];

		// Account for staff?
		if ( $appointable_product->has_staff() ) {
			$staff_ids   = 0 === absint( $staff_ids ) ? $appointable_product->get_staff_ids() : $staff_ids; #no preference
			$staff_ids   = ! is_array( $staff_ids ) ? [ $staff_ids ] : $staff_ids; #make sure it is array.
			$staff_array = ! empty( $staff_ids ) ? $staff_ids : $appointable_product->get_staff_ids();
			// Loop through staff.
			foreach ( $staff_array as $staff_member_id ) {
				$find_appointments_for_staff[] = $staff_member_id;
			}
		}

		// Account for padding?
		$padding_duration_in_minutes = $appointable_product->get_padding_duration_in_minutes();
		if ( $padding_duration_in_minutes && in_array( $appointable_product->get_duration_unit(), [ 'hour', 'minute', 'day' ] ) ) {
			if ( ! empty( $min_date ) ) {
				$min_date = strtotime( "-{$padding_duration_in_minutes} minutes", $min_date );
			}
			if ( ! empty( $max_date ) ) {
				$max_date = strtotime( "+{$padding_duration_in_minutes} minutes", $max_date );
			}
		}

		if ( empty( $min_date ) ) {
			// Determine a min and max date
			$min_date = $appointable_product->get_min_date_a();
			$min_date = empty( $min_date ) ? [
				'unit'  => 'minute',
				'value' => 1,
			] : $min_date;
			$min_date = strtotime( "midnight +{$min_date['value']} {$min_date['unit']}", current_time( 'timestamp' ) );
		}

		if ( empty( $max_date ) ) {
			$max_date = $appointable_product->get_max_date_a();
			$max_date = empty( $max_date ) ? [
				'unit'  => 'month',
				'value' => 12,
			] : $max_date;
			$max_date = strtotime( "+{$max_date['value']} {$max_date['unit']}", current_time( 'timestamp' ) );
		}

		return self::get_appointments_for_objects(
			$find_appointments_for_product,
			$find_appointments_for_staff,
			get_wc_appointment_statuses( 'fully_scheduled' ),
			$min_date,
			$max_date
		);
	}

	/**
 	 * Return all appointments for a product and/or staff in a given range  - the query part (no cache)
 	 * @param integer $start_date
 	 * @param integer $end_date
 	 * @param integer $product_id
 	 * @param integer $staff_id
 	 * @param bool    $check_in_cart
 	 * @param array   $filters
 	 * @param bool    $strict
 	 *
 	 * @return array of appointment ids
 	 */
	private static function get_appointments_in_date_range_query( $start_date, $end_date, $product_id, $staff_id, $check_in_cart, $filters, $strict ) {
		$args = wp_parse_args(
			$filters,
			[
				'status'       => get_wc_appointment_statuses(),
				'object_id'    => 0,
				'product_id'   => 0,
				'staff_id'     => 0,
				'object_type'  => 'product',
				'strict'       => $strict,
				'date_between' => [
					'start' => $start_date,
					'end'   => $end_date,
				],
			]
		);

		if ( ! $check_in_cart ) {
			$args['status'] = array_diff( $args['status'], [ 'in-cart' ] );
		}

		if ( $product_id ) {
			$args['product_id'] = $product_id;
		}

		if ( $staff_id ) {
			$args['staff_id'] = $staff_id;
		}

		if ( ! $product_id && $staff_id ) {
			$args['object_type'] = 'staff';
		}

		if ( $product_id && $staff_id ) {
			$args['object_type'] = 'product_and_staff';
		}

		// Filter the arguments
		$args = apply_filters( 'woocommerce_appointments_in_date_range_query_args', $args );

		// Filter the appointment IDs.
		return apply_filters( 'woocommerce_appointments_in_date_range_query', self::get_appointment_ids_by( $args ) );
	}

	/**
	 * Return all appointments for a product and/or staff in a given range
	 * @param integer $start_date
	 * @param integer $end_date
	 * @param integer $product_id
	 * @param integer $staff_id
	 * @param bool    $check_in_cart
	 * @param array   $filters
	 * @param bool    $strict
	 *
	 * @return array of appointment ids
	 */
	public static function get_appointments_in_date_range( $start_date, $end_date, $product_id = 0, $staff_id = 0, $check_in_cart = true, $filters = [], $strict = false ) {
		$transient_name  = 'schedule_dr_' . md5( http_build_query( [ $start_date, $end_date, $product_id, $staff_id, $check_in_cart, WC_Cache_Helper::get_transient_version( 'appointments' ) ] ) );
		$appointment_ids = WC_Appointments_Cache::get( $transient_name );

		if ( false === $appointment_ids ) {
			$appointment_ids = self::get_appointments_in_date_range_query( $start_date, $end_date, $product_id, $staff_id, $check_in_cart, $filters, $strict );
			WC_Appointments_Cache::set( $transient_name, $appointment_ids, DAY_IN_SECONDS * 30 );
		}

		#print '<pre>'; print_r( $appointment_ids ); print '</pre>';
		#error_log( var_export( $start_date, true ) );
		#error_log( var_export( $end_date, true ) );
		#error_log( var_export( $filters, true ) );


		// Get objects
		return array_map( 'get_wc_appointment', wp_parse_id_list( $appointment_ids ) );
	}

	/**
	 * Gets appointments for a user by ID
	 *
	 * @param  int   $user_id    The id of the user that we want appointments for
	 * @param  array $query_args The query arguments used to get appointment IDs
	 *
	 * @return array             Array of WC_Appointment objects
	 */
	public static function get_appointments_for_user( $user_id, $query_args = null ) {
		$appointment_ids = self::get_appointment_ids_by(
			array_merge(
				[
					'status'      => get_wc_appointment_statuses( 'user' ),
					'object_id'   => $user_id,
					'object_type' => 'customer',
				],
				$query_args
			)
		);

		return array_map( 'get_wc_appointment', $appointment_ids );
	}

	/**
	 * Gets appointments for a product by ID
	 *
	 * @param int $product_id The id of the product that we want appointments for
	 * @param array $status
	 *
	 * @return array of WC_Appointment objects
	 */
	public static function get_appointments_for_product( $product_id, $status = [ 'confirmed', 'paid' ] ) {
		$appointment_ids = self::get_appointment_ids_by(
			[
				'object_id'   => $product_id,
				'object_type' => 'product',
				'status'      => $status,
			]
		);

		return array_map( 'get_wc_appointment', $appointment_ids );
	}

	/**
	 * Search appointment data for a term and return ids.
	 *
	 * @since 4.10.2
	 *
	 * @param  string $term Searched term.
	 * @return array of ids
	 */
	public function search_appointments( $term ) {
		global $wpdb;

		$search_fields   = array_map(
			'wc_clean',
			apply_filters( 'woocommerce_appointment_search_fields', [] )
		);
		$appointment_ids = [];

		if ( is_numeric( $term ) ) {
			$appointment_ids[] = absint( $term );
		}

		if ( ! empty( $search_fields ) ) {
			$appointment_ids = array_unique(
				array_merge(
					$appointment_ids,
					$wpdb->get_col(
						$wpdb->prepare(
							"SELECT DISTINCT p1.post_id
							FROM {$wpdb->postmeta} p1
							WHERE p1.meta_value LIKE %s
							AND p1.meta_key IN ('" . implode( "','", array_map( 'esc_sql', $search_fields ) ) . "')", // @codingStandardsIgnoreLine
							'%' . $wpdb->esc_like( wc_clean( $term ) ) . '%'
						)
					)
				)
			);
		}

		$appointment_ids = array_unique(
			array_merge(
				$appointment_ids,
				$wpdb->get_col(
					$wpdb->prepare(
						"SELECT p.id
						FROM {$wpdb->prefix}posts p
						INNER JOIN $wpdb->users u ON p.post_author = u.id
						WHERE display_name LIKE %s OR user_nicename LIKE %s",
						'%' . $wpdb->esc_like( wc_clean( $term ) ) . '%',
						'%' . $wpdb->esc_like( wc_clean( $term ) ) . '%'
					)
				),
				$wpdb->get_col(
					$wpdb->prepare(
						"SELECT pm.post_id
						FROM {$wpdb->prefix}postmeta pm
						INNER JOIN {$wpdb->prefix}posts p ON p.id = pm.meta_value
						WHERE meta_key = '_appointment_product_id' AND p.post_title LIKE %s",
						'%' . $wpdb->esc_like( wc_clean( $term ) ) . '%'
					)
				)
			)
		);

		return apply_filters( 'woocommerce_appointment_search_results', $appointment_ids, $term, $search_fields );
	}
}
