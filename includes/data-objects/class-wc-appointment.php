<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Main model class for all appointments.
 */
class WC_Appointment extends WC_Data {

	/**
	 * Data array, with defaults.
	 *
	 * @var array
	 */
	protected $data = [
		'all_day'                         => false,
		'cost'                            => 0,
		'customer_id'                     => 0,
		'date_created'                    => '',
		'date_modified'                   => '',
		'start'                           => '',
		'end'                             => '',
		'google_calendar_event_id'        => 0,
		'google_calendar_staff_event_ids' => '',
		'order_id'                        => 0,
		'order_item_id'                   => 0,
		'parent_id'                       => 0,
		'product_id'                      => 0,
		'staff_id'                        => 0,
		'staff_ids'                       => '',
		'status'                          => 'unpaid',
		'customer_status'                 => 'expected',
		'qty'                             => 1,
		'timezone'                        => '',
		'local_timezone'                  => '',
	];

	/**
	 * Stores meta in cache for future reads.
	 *
	 * A group must be set to to enable caching.
	 * @var string
	 */
	protected $cache_group = 'appointment';

	/**
	 * Which data store to load.
	 *
	 * @var string
	 */
	protected $data_store_name = 'appointment';

	/**
	 * This is the name of this object type.
	 *
	 * @var string
	 */
	protected $object_type = 'appointment';

	/**
	 * Stores data about status changes so relevant hooks can be fired.
	 *
	 * @since 3.0.0
	 * @version 3.0.0
	 *
	 * @var bool|array False if it's not transitioned. Otherwise an array containing
	 *                 transitioned status 'from' and 'to'.
	 */
	protected $status_transitioned = false;

	/**
	 * Stores data about staff changes so relevant hooks can be fired.
	 *
	 * @since 4.22.0
	 * @version 4.22.0
	 *
	 * @var bool|array False if it's not transitioned. Otherwise an array containing
	 *                 transitioned status 'from' and 'to'.
	 */
	protected $staff_transitioned = false;

	/**
	 * Cached start time.
	 *
	 * @var int
	 */
	protected $start_cached = null;

	/**
	 * Cached end time.
	 *
	 * @var int
	 */
	protected $end_cached = null;

	/**
	 * Constructor, possibly sets up with post or id belonging to existing appointment
	 * or supplied with an array to construct a new appointment.
	 *
	 * @version  3.3.0
	 * @param    int|array|obj $appointment
	 */
	public function __construct( $appointment = 0 ) {
		parent::__construct( $appointment );

		if ( is_array( $appointment ) ) {
			if ( isset( $appointment['user_id'] ) ) {
				$appointment['customer_id'] = $appointment['user_id'];
			}

			if ( isset( $appointment['start_date'] ) ) {
				$appointment['start'] = $appointment['start_date'];
			}

			if ( isset( $appointment['end_date'] ) ) {
				$appointment['end'] = $appointment['end_date'];
			}

			// Inherit data from parent.
			if ( ! empty( $appointment['parent_id'] ) ) {
				$parent_appointment = get_wc_appointment( $appointment['parent_id'] );

				if ( empty( $appointment['order_item_id'] ) ) {
					$appointment['order_item_id'] = $parent_appointment->data_store->get_appointment_order_item_id( $parent_appointment->get_id() );
				}
				if ( empty( $appointment['customer_id'] ) ) {
					$appointment['customer_id'] = $parent_appointment->data_store->get_appointment_customer_id( $parent_appointment->get_id() );
				}
			}

			// Get order ID from order item
			if ( ! empty( $appointment['order_item_id'] ) ) {
				if ( function_exists( 'wc_get_order_id_by_order_item_id' ) ) {
					$appointment['order_id'] = wc_get_order_id_by_order_item_id( $appointment['order_item_id'] );
				} else {
					global $wpdb;
					$appointment['order_id'] = (int) $wpdb->get_var(
						$wpdb->prepare(
							"SELECT order_id FROM {$wpdb->prefix}woocommerce_order_items WHERE order_item_id = %d",
							$appointment['order_item_id']
						)
					);
				}
			}

			// Get user ID.
			if ( empty( $appointment['customer_id'] ) && is_user_logged_in() && ! is_admin() ) {
				$appointment['customer_id'] = get_current_user_id();
			}

			// Setup the required data for the current user
			if ( empty( $appointment['user_id'] ) ) {
				if ( is_user_logged_in() && ! is_admin() ) {
					$appointment['user_id'] = get_current_user_id();
				}
			}

			#error_log( var_export( $appointment, true ) );

			$this->set_props( $appointment );
			$this->set_object_read( true );
		} elseif ( is_numeric( $appointment ) && $appointment > 0 ) {
			$this->set_id( $appointment );
		} elseif ( $appointment instanceof self ) {
			$this->set_id( $appointment->get_id() );
		} elseif ( ! empty( $appointment->ID ) ) {
			$this->set_id( $appointment->ID );
		} else {
			$this->set_object_read( true );
		}

		$this->data_store = WC_Data_Store::load( $this->data_store_name );

		if ( $this->get_id() > 0 ) {
			$this->data_store->read( $this );
			// For existing appointment: avoid doing the transition (default unpaid to the actual state).
			$this->status_transitioned = false;
			// For existing appointment: avoid doing the transition.
			$this->staff_transitioned = false;
		}
	}

	/**
	 * Save data to the database.
	 *
	 * @param bool $status_transition
	 * @param bool $staff_transition
	 *
	 * @return int appointment ID
	 */
	public function save( $status_transition = true, $staff_transition = true ) {
		// Capture object before saving.
		$get_changes = $this->get_changes();

		if ( $this->data_store ) {
			// Trigger action before saving to the DB. Allows you to adjust object props before save.
			do_action( 'woocommerce_before_' . $this->object_type . '_object_save', $this, $this->data_store );

			if ( $this->get_id() ) {
				$this->data_store->update( $this );
			} else {
				$this->data_store->create( $this );
			}
		}

		WC_Cache_Helper::get_transient_version( 'appointments', true );

		if ( $status_transition ) {
			$this->status_transition();
		}

		if ( $staff_transition ) {
			$this->staff_transition();
		}

		if ( $get_changes ) {
			$this->appointment_changes( $get_changes );
		}

		// Mark the confirmed appointments order as complete if the total is zero.
		$this->mark_confirmed_order_complete_when_total_zero();

		$this->schedule_events( $get_changes );

		return $this->get_id();
	}

	/**
	 * Handle the status transition.
	 */
	protected function status_transition() {
		if ( $this->status_transitioned ) {
			$allowed_statuses = [
				'was-in-cart' => __( 'Was In Cart', 'woocommerce-appointments' ),
			];

			$allowed_statuses = array_unique(
				array_merge(
					$allowed_statuses,
					get_wc_appointment_statuses( null, true ),
					get_wc_appointment_statuses( 'user', true ),
					get_wc_appointment_statuses( 'cancel', true )
				)
			);

			$from = ! empty( $allowed_statuses[ $this->status_transitioned['from'] ] )
				? $allowed_statuses[ $this->status_transitioned['from'] ]
				: false;

			$to = ! empty( $allowed_statuses[ $this->status_transitioned['to'] ] )
				? $allowed_statuses[ $this->status_transitioned['to'] ]
				: false;

			if ( $from && $to ) {
				$this->status_transitioned_handler( $from, $to );
			}

			// This has ran, so reset status transition variable.
			$this->status_transitioned = false;
		}
	}

	/**
	 * Handle the staff transition.
	 */
	protected function staff_transition() {
		if ( $this->staff_transitioned ) {
			$from = $this->staff_transitioned['from'];
			$to = $this->staff_transitioned['to'];

			if ( $from || $to ) {
				$this->staff_transitioned_handler( $from, $to );
			}

			// This has ran, so reset status transition variable.
			$this->staff_transitioned = false;
		}
	}

	/**
	 * Mark the confirmed appointments order as complete if the total is zero.
	 *
	 * @since x.x.x
	 */
	public function mark_confirmed_order_complete_when_total_zero() {
		$order = $this->get_order();

		// Return if order not found.
		if ( ! $order ) {
			return;
		}

		$order_total = (float) $order->get_total();

		// Handle orders that have a zero total, like free orders.
		if ( 0.0 !== $order_total ) {
			return;
		}

		$appointment_ids = WC_Appointment_Data_Store::get_appointment_ids_from_order_id( $order->get_id() );

		// Don't proceed and mark order as complete if any of the linked appointment are not confirmed yet.
		foreach ( $appointment_ids as $appointmet_id ) {
			$appointmet = get_wc_appointment( $appointmet_id );
			if ( 'confirmed' !== $appointmet->get_status() ) {
				return;
			}
		}

		/**
		 * Filter to update the order status when all appointments are confirmed when total is zero.
		 *
		 * @param string 'completed' The order status.
		 * @param WC_Order $order
		 *
		 * @since x.x.x
		 */
		$status = apply_filters( 'woocommerce_appointments_zero_order_status', 'completed', $order );

		$order->update_status( $status );
	}

	/**
	 * Skip status transition events.
	 *
	 * Allows self::status_transition to be bypassed before calling self::save().
	 *
	 * @since 3.0.0
	 * @version 3.0.0
	 */
	public function skip_status_transition_events() {
		$this->status_transitioned = false;
	}

	/**
	 * Handler when appointment status is transitioned.
	 *
	 * @since 3.0.0
	 *
	 * @param string $from Status from.
	 * @param string $to   Status to.
	 */
	protected function status_transitioned_handler( $from, $to ) {
		// Add note to related order.
		$order = $this->get_order();

		if ( $order ) {
			/* translators: %1$d: appointment id %2$s: old status %3$s: new status */
			$order->add_order_note( sprintf( __( 'Appointment #%1$d status changed from "%2$s" to "%3$s"', 'woocommerce-appointments' ), $this->get_id(), $from, $to ), false, true );
		}

		// Fire the events of valid status has been transitioned.
		/**
		 * Hook: woocommerce_appointment_{new_status}
		 *
		 * @since 3.0.0
		 *
		 * @param int            $appointment_id Appointment id.
		 * @param WC_Appointment $appointment    Appointment object.
		 */
		do_action( 'woocommerce_appointment_' . $this->status_transitioned['to'], $this->get_id(), $this );
		/**
		 * Hook: woocommerce_appointment_{old_status}_to_{new_status}
		 *
		 * @since 3.0.0
		 *
		 * @param int            $appointment_id Appointment id.
		 * @param WC_Appointment $appointment    Appointment object.
		 */
		do_action( 'woocommerce_appointment_' . $this->status_transitioned['from'] . '_to_' . $this->status_transitioned['to'], $this->get_id(), $this );

		/**
		 * Hook: woocommerce_appointment_status_changed
		 *
		 * @since 4.11.3
		 *
		 * @param string         $from           Previous status.
		 * @param string         $to             New (current) status.
		 * @param int            $appointment_id Appointment id.
		 * @param WC_Appointment $appointment    Appointment object.
		 */
		do_action( 'woocommerce_appointment_status_changed', $this->status_transitioned['from'], $this->status_transitioned['to'], $this->get_id(), $this );
	}

	/**
	 * Handler when appointment staff is changed.
	 *
	 * @since 4.22.0
	 *
	 * @param string $from Status from.
	 * @param string $to   Status to.
	 */
	protected function staff_transitioned_handler( $from, $to ) {
		// Add note to related order.
		$order = $this->get_order();

		if ( $order ) {
			$order->add_order_note(
				/* translators: %1$d: appointment id %2$s: old staff %3$s: new staff */
				sprintf(
					__( 'Appointment #%1$d staff changed from "%2$s" to "%3$s"', 'woocommerce-appointments' ),
					$this->get_id(),
					wc_appointments_get_staff_from_ids( $from, true ),
					wc_appointments_get_staff_from_ids( $to, true )
				),
				false,
				true
			);
		}

		/**
		 * Hook: woocommerce_appointment_status_changed
		 *
		 * @since 4.22.0
		 *
		 * @param string         $from           Previous status.
		 * @param string         $to             New (current) status.
		 * @param int            $appointment_id Appointment id.
		 * @param WC_Appointment $appointment    Appointment object.
		 */
		do_action( 'woocommerce_appointment_staff_changed', $from, $to, $this->get_id(), $this );
	}

	/**
	 * Handle the appointment changes.
	 */
	protected function appointment_changes( $get_changes = [] ) {
		// Get order object.
		$order = $this->get_order();

		// Loop through changes.
		foreach ( $get_changes as $change_key => $change_value ) {

			// Product ID has changed.
			if ( $order && 'product_id' === $change_key ) {
				// New product object.
				$product = wc_get_product( $change_value );

				// Go through all order items.
				if ( count( $order->get_items() ) > 0 ) {

					// Calculate totals again.
					foreach ( $order->get_items() as $order_item_id => $item ) {

						// Get appointment IDs from order item.
						$appointment_ids = WC_Appointment_Data_Store::get_appointment_ids_from_order_item_id( $order_item_id );

						if ( $appointment_ids && in_array( $this->get_id(), $appointment_ids ) ) {
							$line_item = new WC_Order_Item_Product( $order_item_id );

							// Update line item.
							$line_item->set_product( $product );
							$line_item->save();
						}
					}
				}
			}
		}
	}

	/*
	|--------------------------------------------------------------------------
	| CRUD Getters and setters.
	|--------------------------------------------------------------------------
	*/

	/**
	 * Get all_day.
	 *
	 * @param  string $context
	 * @return boolean
	 */
	public function get_all_day( $context = 'view' ) {
		return $this->get_prop( 'all_day', $context );
	}

	/**
	 * Set all_day.
	 *
	 * @param boolean $value
	 */
	public function set_all_day( $value ) {
		$this->set_prop( 'all_day', wc_appointments_string_to_bool( $value ) );
	}

	/**
	 * Get cost.
	 *
	 * @param  string $context
	 * @return float
	 */
	public function get_cost( $context = 'view' ) {
		return $this->get_prop( 'cost', $context );
	}

	/**
	 * Set cost.
	 *
	 * @param float $value
	 */
	public function set_cost( $value ) {
		$this->set_prop( 'cost', wc_format_decimal( $value ) );
	}

	/**
	 * Get customer_id.
	 *
	 * @param  string $context
	 * @return integer
	 */
	public function get_customer_id( $context = 'view' ) {
		return $this->get_prop( 'customer_id', $context );
	}

	/**
	 * Set customer_id.
	 *
	 * @param integer $value
	 */
	public function set_customer_id( $value ) {
		$new_customer_id = absint( $value );

		// Add customer ID, when creating new account.
		if ( 0 === $new_customer_id ) {
			$customer        = $this->get_customer();
			$new_customer_id = isset( $customer->user_id ) ? $customer->user_id : $new_customer_id;
		}

		$this->set_prop( 'customer_id', $new_customer_id );
	}

	/**
	 * Get date_created.
	 *
	 * @param  string $context
	 * @return int
	 */
	public function get_date_created( $context = 'view' ) {
		return $this->get_prop( 'date_created', $context );
	}

	/**
	 * Set date_created.
	 *
	 * @param string $timestamp Timestamp
	 * @throws WC_Data_Exception
	 */
	public function set_date_created( $timestamp ) {
		$this->set_prop( 'date_created', is_numeric( $timestamp ) ? $timestamp : strtotime( $timestamp ) );
	}

	/**
	 * Get date_modified.
	 *
	 * @param  string $context
	 * @return int
	 */
	public function get_date_modified( $context = 'view' ) {
		return $this->get_prop( 'date_modified', $context );
	}

	/**
	 * Set date_modified.
	 *
	 * @param string $timestamp
	 * @throws WC_Data_Exception
	 */
	public function set_date_modified( $timestamp ) {
		$this->set_prop( 'date_modified', is_numeric( $timestamp ) ? $timestamp : strtotime( $timestamp ) );
	}

	/**
	 * Get end_time.
	 *
	 * @param  string $context
	 * @return int
	 */
	public function get_end( $context = 'view', $deprecated = '' ) {
		$end = (int) $this->get_prop( 'end', $context );

		return $this->is_all_day() ? strtotime( 'midnight +1 day -1 second', $end ) : $end;
	}

	/**
	 * Set end_time.
	 *
	 * @param string $timestamp
	 * @throws WC_Data_Exception
	 */
	public function set_end( $timestamp ) {
		$this->end_cached = null;
		$this->set_prop( 'end', is_numeric( $timestamp ) ? $timestamp : strtotime( $timestamp ) );
	}

	/**
	 * Get google_calendar_event_id.
	 *
	 * @param  string $context
	 * @return string
	 */
	public function get_google_calendar_event_id( $context = 'view' ) {
		return $this->get_prop( 'google_calendar_event_id', $context );
	}

	/**
	 * Set google_calendar_event_id
	 *
	 * @param string $value
	 */
	public function set_google_calendar_event_id( $value ) {
		$this->set_prop( 'google_calendar_event_id', $value );
	}

	/**
	 * Get google_calendar_staff_event_ids.
	 *
	 * @param  string $context
	 * @return string
	 */
	public function get_google_calendar_staff_event_ids( $context = 'view' ) {
		return $this->get_prop( 'google_calendar_staff_event_ids', $context );
	}

	/**
	 * Set google_calendar_staff_event_ids
	 *
	 * @param string $value
	 */
	public function set_google_calendar_staff_event_ids( $value ) {
		$this->set_prop( 'google_calendar_staff_event_ids', $value );
	}

	/**
	 * Get order ID.
	 *
	 * @param  string $context
	 * @return int
	 */
	public function get_order_id( $context = 'view' ) {
		return $this->get_prop( 'order_id', $context );
	}

	/**
	 * Set order_id
	 *
	 * @param  int $value
	 */
	public function set_order_id( $value ) {
		$this->set_prop( 'order_id', absint( $value ) );
	}

	/**
	 * Get order_item_id.
	 *
	 * @param  string $context
	 * @return integer
	 */
	public function get_order_item_id( $context = 'view' ) {
		return $this->get_prop( 'order_item_id', $context );
	}

	/**
	 * Set order_item_id.
	 *
	 * @param integer $value
	 */
	public function set_order_item_id( $value ) {
		$this->set_prop( 'order_item_id', absint( $value ) );
	}

	/**
	 * Get parent ID.
	 *
	 * @param  string $context
	 * @return int
	 */
	public function get_parent_id( $context = 'view' ) {
		return $this->get_prop( 'parent_id', $context );
	}

	/**
	 * Set parent ID.
	 *
	 * @param  int $value
	 */
	public function set_parent_id( $value ) {
		$this->set_prop( 'parent_id', absint( $value ) );
	}

	/**
	 * Get product_id.
	 *
	 * @param  string $context
	 * @return integer
	 */
	public function get_product_id( $context = 'view' ) {
		return $this->get_prop( 'product_id', $context );
	}

	/**
	 * Set product_id.
	 *
	 * @param integer $value
	 */
	public function set_product_id( $value ) {
		$this->set_prop( 'product_id', absint( $value ) );
	}

	/**
	 * Return the staff_ids without wc- internal prefix.
	 *
	 * @param  string $context
	 * @return array
	 */
	public function get_staff_ids( $context = 'view' ) {
		return $this->get_prop( 'staff_ids', $context );
	}

	/**
	 * Set staff_ids (from admin page).
	 *
	 * @param string $new_status Status to change the order to. No internal wc- prefix is required.
	 * @return array details of change
	 */
	public function set_staff_ids( $new_staff_ids ) {
		$old_staff_ids = $this->get_staff_ids();
		// Each 'staff_id' is saved in appointment data store
		$this->set_prop( 'staff_ids', $new_staff_ids );

		// Fire up only on staff change and existing appointment.
		if ( $new_staff_ids !== $old_staff_ids ) {
			$this->staff_transitioned = [
				'from' => $old_staff_ids,
				'to'   => $new_staff_ids,
			];
		}

		return [
			'from' => $old_staff_ids,
			'to'   => $new_staff_ids,
		];
	}

	/**
	 * Set staff_ids (from front page).
	 *
	 * @param string $new_status Status to change the order to. No internal wc- prefix is required.
	 * @return array details of change
	 */
	public function set_staff_id( $new_staff_id ) {
		// Each 'staff_id' is saved in appointment data store
		$this->set_prop( 'staff_ids', $new_staff_id );
	}

	/**
	 * Get start_time.
	 *
	 * @param  string $context
	 * @return int
	 */
	public function get_start( $context = 'view', $deprecated = '' ) {
		$start = (int) $this->get_prop( 'start', $context );

		return $this->is_all_day() ? strtotime( 'midnight', $start ) : $start;
	}

	/**
	 * Set start_time.
	 *
	 * @param string $timestamp
	 * @throws WC_Data_Exception
	 */
	public function set_start( $timestamp ) {
		$new_start = is_numeric( $timestamp ) ? $timestamp : strtotime( $timestamp );

		$this->start_cached = null;

		$this->set_prop( 'start', $new_start );
	}

	/**
	 * Return the status without wc- internal prefix.
	 *
	 * @param  string $context
	 * @return string
	 */
	public function get_status( $context = 'view' ) {
		return $this->get_prop( 'status', $context );
	}

	/**
	 * Set status.
	 *
	 * @param string $new_status Status to change the order to. No internal wc- prefix is required.
	 * @return array details of change
	 */
	public function set_status( $new_status ) {
		$old_status = $this->get_status();

		$this->set_prop( 'status', $new_status );

		if ( $new_status !== $old_status ) {
			$this->status_transitioned = [
				'from' => $old_status,
				'to'   => $new_status,
			];
		}

		return [
			'from' => $old_status,
			'to'   => $new_status,
		];
	}

	/**
	 * Return the customer_status without wc- internal prefix.
	 *
	 * @param  string $context
	 * @return string
	 */
	public function get_customer_status( $context = 'view' ) {
		return $this->get_prop( 'customer_status', $context );
	}

	/**
	 * Set customer_status.
	 *
	 * @param string $new_status Status to change the order to. No internal wc- prefix is required.
	 * @return array details of change
	 */
	public function set_customer_status( $new_customer_status ) {
		// Set status to "no-show", when appointment is past current time, is not paid and customer status is set to "expected".
		if ( 'expected' === $this->get_customer_status()
		    && ! $this->has_status( [ 'paid', 'complete' ] )
		    && $this->get_start() < current_time( 'timestamp' )
		    && $this->get_end() < current_time( 'timestamp' )
		) {
			$new_customer_status = 'no-show';
		}

		$this->set_prop( 'customer_status', $new_customer_status );
	}

	/**
	 * Return the qty without wc- internal prefix.
	 *
	 * @param  string $context
	 * @return string
	 */
	public function get_qty( $context = 'view' ) {
		return $this->get_prop( 'qty', $context );
	}

	/**
	 * Set qty.
	 *
	 * @param string $new_status Status to change the order to. No internal wc- prefix is required.
	 * @return array details of change
	 */
	public function set_qty( $new_qty ) {
		$this->set_prop( 'qty', absint( $new_qty ) );
	}

	/**
	 * Return the timezone without wc- internal prefix.
	 *
	 * @param  string $context
	 * @return string
	 */
	public function get_timezone( $context = 'view' ) {
		return $this->get_prop( 'timezone', $context );
	}

	/**
	 * Set timezone.
	 *
	 * @param string $new_status Status to change the order to. No internal wc- prefix is required.
	 * @return array details of change
	 */
	public function set_timezone( $new_timezone ) {
		$this->set_prop( 'timezone', $new_timezone );
	}

	/**
	 * Get local_timezone.
	 *
	 * @param  string $context
	 * @return string
	 */
	public function get_local_timezone( $context = 'view' ) {
		return $this->get_prop( 'local_timezone', $context );
	}

	/**
	 * Set local_timezone.
	 *
	 * @param string $timestamp
	 * @throws WC_Data_Exception
	 */
	public function set_local_timezone( $timezone ) {
		$this->set_prop( 'local_timezone', $timezone );
	}

	/*
	|--------------------------------------------------------------------------
	| Conditonals
	|--------------------------------------------------------------------------
	*/

	/**
	 * Checks the appointment status against a passed in status.
	 *
	 * @return bool
	 */
	public function has_status( $status ) {
		return apply_filters( 'woocommerce_appointment_has_status', ( is_array( $status ) && in_array( $this->get_status(), $status ) ) || $this->get_status() === $status ? true : false, $this, $status );
	}

	/**
	 * Return if all day event.
	 *
	 * @return boolean
	 */
	public function is_all_day() {
		return $this->get_all_day();
	}

	/**
	 * See if this appointment is within a slot.
	 *
	 * @return boolean
	 */
	public function is_within_slot( $slot_start, $slot_end ) {
		// Cache start/end to speed up repeated calls.
		if ( null === $this->start_cached ) {
			$this->start_cached = $this->get_start();
		}
		if ( null === $this->end_cached ) {
			$this->end_cached = $this->get_end();
		}
		$start = $this->start_cached;
		$end   = $this->end_cached;

		#print '<pre>'; print_r( $end . ' - ' . $this->get_end() ); print '</pre>';

		if ( ! $start || ! $end || $start >= $slot_end || $end <= $slot_start ) {
			return false;
		}

		return true;

		/*
		// Condition: Already Booked range must be inside (or equals to) the slot range.
		if ( ! $start || ! $end || ! ( $slot_start <= $start && $end <= $slot_end ) ) {
			return false;
		}

		return true;
		*/
	}

	/**
	 * See if the appointment intersects a slot.
	 *
	 * @param int $slot_start Slot start timestamp.
	 * @param int $slot_end Slot end timestamp.
	 *
	 * @return bool
	 */
	public function is_intersecting_slot( $slot_start, $slot_end ) {
		// Cache start/end to speed up repeated calls.
		if ( null === $this->start_cached ) {
			$this->start_cached = $this->get_start();
		}
		if ( null === $this->end_cached ) {
			$this->end_cached = $this->get_end();
		}
		$start = $this->start_cached;
		$end   = $this->end_cached;

		// Condition: Already Booked range must intersect the slot range.
		if ( ! $start || ! $end || $end <= $slot_start || $slot_end <= $start ) {
			return false;
		}

		return true;
	}

	/**
	 * See if this appointment can still be cancelled by the user or not.
	 *
	 * @return boolean
	 */
	public function passed_cancel_day() {
		$product = $this->get_product();

		if ( ! $product || ! $product->can_be_cancelled() ) {
			return true;
		}

		if ( false !== $product ) {
			$cancel_limit      = $product->get_cancel_limit();
			$cancel_limit_unit = $cancel_limit > 1 ? $product->get_cancel_limit_unit() . 's' : $product->get_cancel_limit_unit();
			$cancel_string     = sprintf( '%s +%d %s', current_time( 'd F Y H:i:s' ), $cancel_limit, $cancel_limit_unit );

			if ( strtotime( $cancel_string ) >= $this->get_start() ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * See if this appointment can still be rescheduled by the user or not.
	 *
	 * @return boolean
	 */
	public function passed_reschedule_day() {
		$product = $this->get_product();

		if ( ! $product || ! $product->can_be_rescheduled() ) {
			return true;
		}

		if ( false !== $product ) {
			$reschedule_limit      = $product->get_reschedule_limit();
			$reschedule_limit_unit = $reschedule_limit > 1 ? $product->get_reschedule_limit_unit() . 's' : $product->get_reschedule_limit_unit();
			$reschedule_string     = sprintf( '%s +%d %s', current_time( 'd F Y H:i:s' ), $reschedule_limit, $reschedule_limit_unit );

			if ( strtotime( $reschedule_string ) >= $this->get_start() ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Returns if staff are enabled/needed for the appointment product
	 *
	 * @return boolean
	 */
	public function has_staff() {
		return $this->get_product() ? $this->get_product()->has_staff() : false;
	}

	/**
	 * Get the staff/type for this appointment if applicable.
	 *
	 * @param  bool $names
	 * @param  bool $with_link
	 *
	 * @return bool|object WP_Post
	 */
	public function get_staff_members( $names = false, $with_link = false ) {
		$ids = $this->get_staff_ids();

		if ( ! $ids ) {
			return false;
		}

		return wc_appointments_get_staff_from_ids( $ids, $names, $with_link );
	}

	/*
	|--------------------------------------------------------------------------
	| Non-CRUD getters/helpers.
	|--------------------------------------------------------------------------
	*/

	/**
	 * Returns appointment start date.
	 *
	 * @param string $date_format
	 * @param string $time_format
	 *
	 * @return string Date formatted via date_i18n
	 */
	public function get_start_date( $date_format = null, $time_format = null ) {
		if ( $this->get_start() ) {
			if ( is_null( $date_format ) ) {
				$date_format = wc_appointments_date_format();
			}
			if ( is_null( $time_format ) ) {
				$time_format = ', ' . wc_appointments_time_format();
			}
			if ( $this->is_all_day() ) {
				return date_i18n( $date_format, $this->get_start() );
			} else {

				#echo current_filter();

				// Customer's timezone viewpoints.
				$customers_viewpoints = [
					'woocommerce_order_item_meta_start',
					'woocommerce_order_item_meta_end',
					'woocommerce_account_appointments_endpoint',
					'woocommerce_appointment_pending-confirmation_to_cancelled_notification',
					'woocommerce_appointment_confirmed_to_cancelled_notification',
					'woocommerce_appointment_paid_to_cancelled_notification',
					'wc-appointment-confirmed',
					'wc-appointment-reminder',
					'wc-appointment-follow-up',
					'fue_before_variable_replacements', #follow ups
				];

				// Timezone caluclation.
				if ( $this->get_local_timezone() && $this->get_product_has_timezones() && in_array( current_filter(), $customers_viewpoints ) ) {
					$start_date     = wc_appointment_timezone_locale( 'site', 'user', $this->get_start(), 'U', $this->get_local_timezone() );
					$get_start_date = date_i18n( $date_format . $time_format, $start_date ) . ' (' . wc_appointment_get_timezone_name( $this->get_local_timezone() ) . ')';
				} else {
					$get_start_date = date_i18n( $date_format . $time_format, $this->get_start() );
				}

				return apply_filters( 'woocommerce_appointments_get_start_date_with_time', $get_start_date, $this, $this->get_start() );
			}
		}
		return false;
	}

	/**
	 * Returns appointment end date.
	 *
	 * @param string $date_format
	 * @param string $time_format
	 *
	 * @return string Date formatted via date_i18n
	 */
	public function get_end_date( $date_format = null, $time_format = null ) {
		if ( $this->get_end() ) {
			if ( is_null( $date_format ) ) {
				$date_format = wc_appointments_date_format();
			}
			if ( is_null( $time_format ) ) {
				$time_format = ', ' . wc_appointments_time_format();
			}
			if ( $this->is_all_day() ) {
				return date_i18n( $date_format, $this->get_end() );
			} else {

				#echo current_filter();

				// Customer's timezone viewpoints.
				$customers_viewpoints = [
					'woocommerce_order_item_meta_start',
					'woocommerce_order_item_meta_end',
					'woocommerce_account_appointments_endpoint',
					'woocommerce_appointment_pending-confirmation_to_cancelled_notification',
					'woocommerce_appointment_confirmed_to_cancelled_notification',
					'woocommerce_appointment_paid_to_cancelled_notification',
					'wc-appointment-confirmed',
					'wc-appointment-reminder',
					'wc-appointment-follow-up',
					'fue_before_variable_replacements', #follow ups
				];

				// Timezone caluclation.
				if ( $this->get_local_timezone() && $this->get_product_has_timezones() && in_array( current_filter(), $customers_viewpoints ) ) {
					$end_date     = wc_appointment_timezone_locale( 'site', 'user', $this->get_end(), 'U', $this->get_local_timezone() );
					$get_end_date = date_i18n( $date_format . $time_format, $end_date ) . ' (' . wc_appointment_get_timezone_name( $this->get_local_timezone() ) . ')';
				} else {
					$get_end_date = date_i18n( $date_format . $time_format, $this->get_end() );
				}

				return apply_filters( 'woocommerce_appointments_get_end_date_with_time', $get_end_date, $this, $this->get_end() );
			}
		}
		return false;
	}

	/**
	 * Returns appointment duration.
	 *
	 * @param array $pretty
	 *
	 * @return string duration formatted as pretty timestamp
	 */
	public function get_duration( $pretty = true ) {
		return wc_appointment_duration_in_minutes( $this->get_start(), $this->get_end(), $this->get_duration_unit(), $pretty );
	}

	/**
	 * Returns appointment duration unit.
	 *
	 * @param array $pretty
	 *
	 * @return string duration formatted as pretty timestamp
	 */
	public function get_duration_unit( $pretty = true ) {
		try {
			if ( $this->get_product() ) {
				return $this->get_product()->get_duration_unit();
			}
		} catch ( Exception $e ) {
			return 'minute';
		}
	}

	/**
	 * Returns appointment duration parameters.
	 *
	 * @param array $pretty
	 *
	 * @return string duration formatted as pretty timestamp
	 */
	public function get_duration_parameters() {
		$duration_in_minutes = wc_appointment_duration_in_minutes( $this->get_start(), $this->get_end(), $this->get_duration_unit(), false );
		return wc_appointment_duration_parameters( $duration_in_minutes );
	}

	/**
	 * Returns appointment addons.
	 *
	 * @param array $args
	 *
	 * @return string html formatted addon fields
	 */
	public function get_addons( $args = [] ) {
		$args = wp_parse_args(
			$args,
			[
				'before'    => '<ul class="wc-item-meta"><li>',
				'after'     => '</li></ul>',
				'separator' => '</li><li>',
				'label'     => true,
				'echo'      => false,
				'autop'     => true,
			]
		);

		if ( $this->has_status( [ 'was-in-cart', 'in-cart' ] ) ) {
			$addons_from_cart = $this->get_addons_from_cart( $args );

			if ( $addons_from_cart ) {
				return apply_filters( 'woocommerce_appointments_get_addons', $addons_from_cart, $this, $this->get_product() );
			}
		}

		$addons_from_order = $this->get_addons_from_order( $args );

		if ( $addons_from_order ) {
			return apply_filters( 'woocommerce_appointments_get_addons', $addons_from_order, $this, $this->get_product() );
		}

		return apply_filters( 'woocommerce_appointments_get_addons', false, $this, $this->get_product() );
	}

	/**
	 * Returns appointment addons.
	 *
	 * @param array $args
	 *
	 * @return string html formatted addon fields
	 */
	public function get_addons_from_order( $args = [] ) {
		$order = $this->get_order();
		if ( $order ) {
			$item_meta = '';

			if ( count( $order->get_items() ) > 0 ) {
				foreach ( $order->get_items() as $item ) {
					$product = false;
					$item_id = 0;

					if ( $item->is_type( 'line_item' ) ) {
						$product = $item->get_product();
						$item_id = $item->get_id();
					}

					$appointment_ids = WC_Appointment_Data_Store::get_appointment_ids_from_order_item_id( $item_id );

					if ( $appointment_ids
					    && $product
					    && is_wc_appointment_product( $product )
					    && $this->get_product_id() === $product->get_id()
					    && in_array( $this->get_id(), $appointment_ids )
				    ) {
						$item_meta = wc_display_item_meta( $item, $args );
					}
				}
			}

			return $item_meta;
		}

		return false;
	}

	/**
	 * Returns appointment addons.
	 *
	 * @param array $args
	 *
	 * @return string html formatted addon fields
	 */
	public function get_addons_from_cart( $args = [] ) {
		if ( null === WC()->cart ) {
			WC()->frontend_includes();
			WC()->session = new WC_Session_Handler();
			WC()->session->init();
			WC()->customer = new WC_Customer( get_current_user_id(), true );
			WC()->cart     = new WC_Cart();
		}

		$strings = [];
		foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
		    if ( isset( $cart_item['appointment'] ) ) {
		        $appointment_id = $cart_item['appointment']['_appointment_id'];
		        $appointment    = get_wc_appointment( $appointment_id );
		        $product        = $cart_item['data'];

		        if ( $product
		            && is_wc_appointment_product( $product )
		            && $this->get_product_id() === $product->get_id()
		            && $this->get_id() === $appointment_id
		            && isset( $cart_item['addons'] )
		        ) {
		            foreach ( $cart_item['addons'] as $addon ) {
		                $name = $addon['name'];

		                if ( $addon['price'] > 0 && apply_filters( 'woocommerce_addons_add_price_to_name', true, $addon ) ) {
		                    $name .= ' (' . wp_strip_all_tags( wc_price( WC_Product_Addons_Helper::get_product_addon_price_for_display( $addon['price'] ) ) ) . ')';
		                }

		                $value = $args['autop'] ? wp_kses_post( $addon['value'] ) : wp_kses_post( make_clickable( trim( wp_strip_all_tags( $addon['value'] ) ) ) );

		                if ( $args['label'] ) {
		                    $strings[] = '<strong class="wc-item-meta-label">' . wp_kses_post( $name ) . ':</strong> ' . $value;
		                } else {
		                    $strings[] = wp_kses_post( $name ) . ': ' . $value;
		                }
		            }
		        }
		    }
		}

		if ( $strings ) {
			return $args['before'] . implode( $args['separator'], $strings ) . $args['after'];
		}

		return false;
	}

	/**
	 * Returns the object of the order corresponding to this appointment.
	 *
	 * @return WC_Product|Boolean
	 */
	public function get_product() {
		try {
			if ( $this->get_product_id() ) {
				return get_wc_product_appointment( $this->get_product_id() );
			}
		} catch ( Exception $e ) {
			return false;
		}
	}

	/**
	 * Returns the object of the order corresponding to this appointment.
	 *
	 * @return WC_Product|Boolean
	 */
	public function get_product_name() {
		try {
			if ( $this->get_product() ) {
				return $this->get_product()->get_title();
			}
		} catch ( Exception $e ) {
			return false;
		}
	}

	/**
	 * Returns the object of the order corresponding to this appointment.
	 *
	 * @return WC_Order|Boolean
	 */
	public function get_order() {
		if ( $this->get_order_id() ) {
			// Cache query.
			$cache_group = 'wc-appointment-order';
			$cache_key   = WC_Cache_Helper::get_cache_prefix( $cache_group ) . 'get_order' . $this->get_order_id();

			$data = wp_cache_get( $cache_key, $cache_group );

			if ( false === $data ) {
				$data = wc_get_order( $this->get_order_id() );

				wp_cache_set( $cache_key, $data, $cache_group );
			}

			return $data;
		}

		return false;
	}

	/**
	 * Returns information about the customer of this order.
	 *
	 * @param object $order
	 *
	 * @return object containing customer information
	 */
	public function get_customer( $order = false ) {
		// Defaults.
		$return = [
			'name'             => __( 'Guest', 'woocommerce-appointments' ),
			'full_name'        => __( 'Guest', 'woocommerce-appointments' ),
			'first_name'       => '',
			'last_name'        => '',
			'phone'            => '',
			'email'            => '',
			'billing_address'  => '',
			'shipping_address' => '',
			'user_id'          => 0,
		];

		// Appointment has customer ID.
		$user = $this->get_customer_id() ? get_user_by( 'id', $this->get_customer_id() ) : 0;
		if ( $user ) {
			$return['name']       = $user->display_name;
			$return['first_name'] = $user->user_firstname;
			$return['last_name']  = $user->user_lastname;
			$return['full_name']  = trim( $user->user_firstname . ' ' . $user->user_lastname );
			$return['phone']      = $user->user_phone;
			$return['email']      = $user->user_email;
			$return['user_id']    = $this->get_customer_id();
		}

		// Appointment has order ID.
		if ( $this->get_order_id() ) {

			$order = $order ? $order : $this->get_order();

			if ( $order ) {
				// First name.
				if ( $order->get_billing_first_name() && ! $return['first_name'] ) {
					$return['first_name'] = $order->get_billing_first_name();
				} elseif ( $order->get_shipping_first_name() && ! $return['first_name'] ) {
					$return['first_name'] = $order->get_shipping_first_name();
				}
				// Last name.
				if ( $order->get_billing_last_name() && ! $return['last_name'] ) {
					$return['last_name'] = $order->get_billing_last_name();
				} elseif ( $order->get_shipping_last_name() && ! $return['last_name'] ) {
					$return['last_name'] = $order->get_shipping_last_name();
				}
				// Full name.
				if ( $return['first_name'] || $return['last_name'] ) {
					$return['full_name'] = trim( $return['first_name'] . ' ' . $return['last_name'] );
					$return['name']      = trim( $return['first_name'] . ' ' . $return['last_name'] );
				}
				$customer_id = 0 !== absint( $order->get_customer_id() );
				if ( ! $customer_id && ( $return['first_name'] || $return['last_name'] ) ) {
					/* translators: %s: Guest name */
					$return['full_name'] = sprintf( _x( '%s (Guest)', 'Guest string with name from appointment order in brackets', 'woocommerce-appointments' ), $return['full_name'] );
					/* translators: %s: Guest name */
					$return['name'] = sprintf( _x( '%s (Guest)', 'Guest string with name from appointment order in brackets', 'woocommerce-appointments' ), $return['full_name'] );
				}
				// Address.
				if ( $order->get_formatted_billing_address() ) {
					$return['billing_address'] = $order->get_formatted_billing_address();
				} elseif ( $order->get_formatted_shipping_address() ) {
					$return['shipping_address'] = $order->get_formatted_shipping_address();
				}

				// Phone.
				if ( $order->get_billing_phone() && ! $return['phone'] ) {
					$return['phone'] = $order->get_billing_phone();
				} elseif ( $order->get_shipping_phone() && ! $return['phone'] ) {
					$return['phone'] = $order->get_shipping_phone();
				}

				// Email.
				if ( ! $return['email'] ) {
					$return['email'] = $order->get_billing_email();
				}

				// Display name.
				$return['name'] = $return['full_name'];

				// User ID.
				if ( ! $return['user_id'] ) {
					$return['user_id'] = $order->get_customer_id();
				}

				#var_dump($return);

				return (object) $return;
			}
		}

		// Guest and no order.
		return (object) $return;
	}

	/**
	 * Returns true if product has timezones, otherwise false.
	 *
	 * @return Boolean
	 */
	public function get_product_has_timezones() {
		try {
			if ( $this->get_product() ) {
				return $this->get_product()->has_timezones();
			}
		} catch ( Exception $e ) {
			return false;
		}
	}

	/**
	 * Is edited from post.php's meta box.
	 *
	 * @return bool
	 */
	public function is_edited_from_meta_box() {
		return (
			! empty( $_POST['wc_appointments_details_meta_box_nonce'] )
			&&
			wp_verify_nonce( $_POST['wc_appointments_details_meta_box_nonce'], 'wc_appointments_details_meta_box' )
		);
	}

	/**
	 * Schedule events for this appointment.
	 *
	 * @param array $get_changes
	 */
	public function schedule_events( $get_changes = '' ) {
		$order          = $this->get_order();
		$order_status   = $order ? $order->get_status() : null;
		$payment_method = $order ? $order->get_payment_method() : null;
		$created_via    = $order ? $order->get_created_via() : null;

		// Check if emails are set.
		$is_reminder_set = as_next_scheduled_action( 'wc-appointment-reminder', [ $this->get_id() ], 'wca' );
		$is_complete_set = as_next_scheduled_action( 'wc-appointment-complete', [ $this->get_id() ], 'wca' );

		#error_log( var_export( $is_reminder_set, true ) );
		#error_log( var_export( $is_complete_set, true ) );

		// Appointment is edited in admin.
		if ( $this->is_edited_from_meta_box() ) {
			if ( isset( $get_changes['status'] ) ) {
				// Status is OK.
				if ( $this->has_status( get_wc_appointment_statuses( 'scheduled' ) ) ) {
					$this->maybe_schedule_event( 'reminder' );
					$this->maybe_schedule_event( 'complete' );
				} else {
					as_unschedule_action( 'wc-appointment-reminder', [ $this->get_id() ], 'wca' );
					as_unschedule_action( 'wc-appointment-complete', [ $this->get_id() ], 'wca' );
					if ( ! $this->has_status( 'complete' ) ) {
						as_unschedule_action( 'wc-appointment-follow-up', [ $this->get_id() ], 'wca' );
					}
				}
			} elseif ( isset( $get_changes['start'] ) ) {
				// Status is OK.
				if ( $this->has_status( get_wc_appointment_statuses( 'scheduled' ) ) ) {
					$this->maybe_schedule_event( 'reminder' );
					$this->maybe_schedule_event( 'complete' );
				} else {
					if ( false !== $is_reminder_set ) {
						$this->maybe_schedule_event( 'reminder' );
					}
					if ( false !== $is_complete_set ) {
						$this->maybe_schedule_event( 'complete' );
					}
				}
			}
		// When status is OK, always schedule.
		} elseif ( $this->has_status( get_wc_appointment_statuses( 'scheduled' ) ) ) {
			$this->maybe_schedule_event( 'reminder' );
			$this->maybe_schedule_event( 'complete' );
		// Unschedule emails in all other cases.
		} else {
			as_unschedule_action( 'wc-appointment-reminder', [ $this->get_id() ], 'wca' );
			as_unschedule_action( 'wc-appointment-complete', [ $this->get_id() ], 'wca' );
			if ( ! $this->has_status( 'complete' ) ) {
				as_unschedule_action( 'wc-appointment-follow-up', [ $this->get_id() ], 'wca' );
			}
		}
	}

	/**
	 * Checks if appointment end date has already passed.
	 *
	 * @since 4.9.4
	 * @return bool True if current time is bigger than appointment end date.
	 */
	public function passed_end_date() {
		if ( $this->get_end() && ( $this->get_end() < current_time( 'timestamp' ) ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Schedule event for this appointment.
	 *
	 * @param string $type
	 *
	 * @return bool Whether schedule was done or not.
	 */
	public function maybe_schedule_event( $type ) {
		$reminder_mailer   = WC()->mailer()->emails['WC_Email_Appointment_Reminder'];
		$reminder_time     = $reminder_mailer->get_option( 'reminder_time', '1 day' );
		$follow_up_mailer  = WC()->mailer()->emails['WC_Email_Appointment_Follow_Up'];
		$follow_up_time    = $follow_up_mailer->get_option( 'follow_up_time', '1 day' );
		$timezone_addition = - wc_appointment_timezone_offset();

		// Timestamps.
		$reminder_timestamp  = $timezone_addition + strtotime( '-' . apply_filters( 'woocommerce_appointments_remind_before_time', $reminder_time, $this ), $this->get_start() );
		$complete_timestamp  = $timezone_addition + apply_filters( 'woocommerce_appointments_complete_time', $this->get_end(), $this );
		$follow_up_timestamp = $timezone_addition + strtotime( '+' . apply_filters( 'woocommerce_appointments_follow_up_time', $follow_up_time, $this ), $this->get_end() );

		switch ( $type ) {
			case 'reminder':
				if ( $this->get_start() && ! $this->passed_end_date() ) {
					as_unschedule_action( 'wc-appointment-reminder', [ $this->get_id() ], 'wca' );
					return is_null( as_schedule_single_action( $reminder_timestamp, 'wc-appointment-reminder', [ $this->get_id() ], 'wca' ) );

				}
				break;
			case 'complete':
				if ( $this->get_end() ) {
					as_unschedule_action( 'wc-appointment-complete', [ $this->get_id() ], 'wca' );
					$return_complete = is_null( as_schedule_single_action( $complete_timestamp, 'wc-appointment-complete', [ $this->get_id() ], 'wca' ) );
					as_unschedule_action( 'wc-appointment-follow-up', [ $this->get_id() ], 'wca' );
					$return_follow_up = is_null( as_schedule_single_action( $follow_up_timestamp, 'wc-appointment-follow-up', [ $this->get_id() ], 'wca' ) );
					return $return_complete || $return_follow_up;
				}
				break;
		}

		return false;
	}

	/**
	 * Returns the cancel URL for an appointment
	 *
	 * @param string $redirect
	 *
	 * @return string
	 */
	public function get_cancel_url( $redirect = '' ) {
		return apply_filters(
			'appointments_cancel_appointment_url',
			wp_nonce_url(
				add_query_arg(
					[
						'cancel_appointment' => 'true',
						'appointment_id'     => $this->get_id(),
						'redirect'           => $redirect,
					],
					wc_get_page_permalink( 'myaccount' )
				),
				'woocommerce-appointments-cancel_appointment'
			),
			$this
		);
	}

	/**
	 * Returns the reschedule URL for an appointment
	 *
	 * @param string $redirect
	 *
	 * @return string
	 */
	public function get_reschedule_url( $redirect = '' ) {
		return apply_filters(
			'appointments_reschedule_appointment_url',
			esc_url(
				wc_get_endpoint_url(
					'reschedule',
					$this->get_id(),
					wc_get_page_permalink( 'myaccount' )
				)
			),
			$this
		);
	}

	/*
	|--------------------------------------------------------------------------
	| Legacy.
	|--------------------------------------------------------------------------
	*/

	/**
	 * Actualy create for the new appointment belonging to an order.
	 *
	 * @param string Status for new order
	 */
	public function create( $status = 'unpaid' ) {
		$this->set_status( $status );
		$this->save();
	}

	/**
	 * Will change the appointment status once the order is paid for.
	 *
	 * @return bool
	 */
	public function paid() {
		if ( $this->has_status( [ 'unpaid', 'confirmed', 'wc-partial-payment' ] ) ) {
			$this->set_status( 'paid' );
			$this->save();

			return true;
		}

		return false;
	}

	/**
	 * Populate the data with the id of the appointment provided
	 * Will query for the post belonging to this appointment and store it
	 *
	 * @param int $appointment_id
	 *
	 * @return boolean
	 */
	public function populate_data( $appointment_id ) {
		$this->set_defaults();
		$this->set_id( $appointment_id );
		$this->data_store->read( $this );

		return 0 < $this->get_id();
	}

	/**
	 * Set the new status for this appointment.
	 *
	 * @param string $status
	 *
	 * @return bool
	 */
	public function update_status( $status ) {
		$current_status = $this->get_status( 'edit' );

		$allowed_statuses = [
			'was-in-cart' => __( 'Was In Cart', 'woocommerce-appointments' ),
		];

		$allowed_statuses = array_unique(
			array_merge(
				$allowed_statuses,
				get_wc_appointment_statuses( null, true ),
				get_wc_appointment_statuses( 'user', true ),
				get_wc_appointment_statuses( 'cancel', true )
			)
		);

		$allowed_status_keys = array_keys( $allowed_statuses );

		if ( in_array( $status, $allowed_status_keys ) ) {
			$this->set_status( $status );
			$this->save();

			return true;
		}

		return false;
	}

	/**
	 * Set the new customer status for this appointment
	 *
	 * @param string $status
	 *
	 * @return bool
	 */
	public function update_customer_status( $status ) {
		$current_status   = $this->get_customer_status( 'edit' );
		$allowed_statuses = get_wc_appointment_statuses( 'customer' );
		$allowed_statuses = array_keys( $allowed_statuses );

		if ( in_array( $status, $allowed_statuses ) ) {
			$this->set_customer_status( $status );
			$this->save();

			return true;
		}

		return false;
	}

	/**
	 * Magic __isset method for backwards compatibility.
	 *
	 * @param string $key
	 *
	 * @return bool
	 */
	public function __isset( $key ) {
		$legacy_props = [ 'appointment_date', 'modified_date', 'populated', 'post', 'custom_fields' ];
		return $this->get_id() ? ( in_array( $key, $legacy_props ) || is_callable( [ $this, "get_{$key}" ] ) ) : false;
	}

	/**
	 * Magic __get method for backwards compatibility.
	 *
	 * @param string $key
	 *
	 * @return mixed
	 */
	public function __get( $key ) {
		// wc_doing_it_wrong( $key, 'Appointment properties should not be accessed directly.', '3.0.0' ); @todo deprecated when 2.6.x dropped
		if ( 'appointment_date' === $key ) {
			return $this->get_date_created();
		} elseif ( 'modified_date' === $key ) {
			return $this->get_date_modified();
		} elseif ( 'populated' === $key ) {
			return $this->get_object_read();
		} elseif ( 'post' === $key ) {
			return get_post( $this->get_id() );
		} elseif ( 'custom_fields' === $key ) {
			return get_post_meta( $this->get_id() );
		} elseif ( is_callable( [ $this, "get_{$key}" ] ) ) {
			return $this->{"get_{$key}"}();
		} else {
			return get_post_meta( $this->get_id(), '_' . $key, true );
		}
	}

	/**
	 * Get date localized to client timezone.
	 *
	 * @param  int $date timestamp to convert.
	 * @return string|null datetime string in client's timezone.
	 */
	public function get_localized_date( $date ) {
		$localized_date = null;

		// Timezone may not exist so wrap it in a try/catch block
		try {
			$local_timezone  = new DateTimeZone( $this->get_local_timezone() );
			$server_timezone = wc_timezone_string();

			// Create DateTime in server's timezone (otherwise UTC is assumed).
			$dt = new DateTime( date( 'Y-m-d\TH:i:s', $date ), new DateTimeZone( $server_timezone ) );
			$dt->setTimezone( $local_timezone );

			// Calling simply `getTimestamp` will not calculate the timezone.
			$localized_date = strtotime( $dt->format( 'Y-m-d H:i:s' ) );
		} catch ( Exception $e ) {
			return null;
		}

		return $localized_date;
	}

	/**
	 * If there is no local timezone assume server timezone.
	 *
	 * @since 4.19.0
	 */
	public function get_appointment_timezone() {
		$timezone = $this->get_local_timezone();
		return $timezone ? $timezone : wc_timezone_string();
	}

	/**
	 * Indicate whether the appointment is active, i.e. not cancelled or refunded.
	 *
	 * @since 4.10.8.
	 *
	 * @return bool
	 */
	public function is_active() {
		$appointment_status = $this->get_status();
		$order_id           = WC_Appointment_Data_Store::get_appointment_order_id( $this->get_id() );

		// Check only appointment status if no order associated with appointment (eg: manually created appointment without order).
		if ( 0 === $order_id && 'cancelled' !== $appointment_status ) {
			return true;
		}

		$order = wc_get_order( $order_id );

		// Dangling appointment, probably not a valid one.
		if ( ! is_a( $order, 'WC_Order' ) ) {
			return false;
		}

		$order_status = $order->get_status();

		// Don't consider the appointment active for cancelled appointment, or if the order is cancelled or refunded.
		if ( 'cancelled' === $appointment_status || 'refunded' === $order_status || 'cancelled' === $order_status ) {
			return false;
		}

		return true;
	}

	/**
	 * See if this appointment is scheduled on a date.
	 *
	 * @return boolean
	 */
	public function is_scheduled_on_day( $slot_start, $slot_end ) {
		_deprecated_function( __METHOD__, '4.2.0' );

		$is_scheduled         = false;
		$loop_date            = $this->get_start();
		$multiday_appointment = date( 'Y-m-d', $this->get_start() ) < date( 'Y-m-d', $this->get_end() );

		if ( $multiday_appointment ) {
			if ( date( 'YmdHi', $slot_end ) > date( 'YmdHi', $this->get_start() ) && date( 'YmdHi', $slot_start ) < date( 'YmdHi', $this->get_end() ) ) {
				$is_scheduled = true;
			} else {
				$is_scheduled = false;
			}
		} else {
			while ( $loop_date <= $this->get_end() ) {
				if ( date( 'Y-m-d', $loop_date ) === date( 'Y-m-d', $slot_start ) ) {
					$is_scheduled = true;
				}
				$loop_date = strtotime( '+1 day', $loop_date );
			}
		}

		/**
		 * Filter the appointment objects is_scheduled_on_day method return result.
		 *
		 * @since 1.9.13
		 *
		 * @param bool $is_scheduled
		 * @param WC_Appointment $appointment
		 * @param WC_Appointment $slot_start
		 * @param WC_Appointment $slot_end
		 */
		return apply_filters( 'woocommerce_appointment_is_scheduled_on_day', $is_scheduled, $this, $slot_start, $slot_end );
	}
}
