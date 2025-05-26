<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Handles order status transitions and keeps appointments in sync
 */
class WC_Appointment_Order_Manager {

	/**
	 * ID being synced.
	 *
	 * @var array
	 */
	private static $syncing_ids = [];

	/**
	 * Constructor sets up actions
	 */
	public function __construct() {
		// Add a "My Appointments" area to the My Account page.
		add_action( 'init', array( $this, 'add_endpoints' ) );

		add_action( 'woocommerce_order_item_meta_start', array( $this, 'appointment_display' ), 10, 3 );

		// Order again button.
		remove_action( 'woocommerce_order_details_after_order_table', 'woocommerce_order_again_button' );
		add_action( 'woocommerce_order_details_after_order_table', array( $this, 'order_again_button' ) );

		#add_filter( 'query_vars', array( $this, 'add_query_vars' ), 0 );
		add_filter( 'woocommerce_get_query_vars', array( $this, 'add_query_vars' ), 0 );
		add_filter( 'the_title', array( $this, 'endpoint_title' ) );
		add_filter( 'woocommerce_account_menu_items', array( $this, 'my_account_menu_item' ) );
		add_filter( 'woocommerce_account_menu_item_classes', array( $this, 'my_account_menu_item_classes' ), 10, 2 );
		add_action( 'woocommerce_account_' . $this->get_appointments_endpoint() . '_endpoint', array( $this, 'appointments_endpoint_content' ) );
		add_action( 'woocommerce_account_' . $this->get_reschedule_endpoint() . '_endpoint', array( $this, 'reschedule_endpoint_content' ) );

		// Complete appointment orders if virtual.
		add_filter( 'woocommerce_payment_complete_order_status', array( $this, 'complete_order' ), 20, 2 );

		// When an order is processed or completed, we can mark publish the pending appointments.
		add_action( 'woocommerce_order_status_processing', array( $this, 'publish_appointments' ), 20, 1 );
		add_action( 'woocommerce_order_status_completed', array( $this, 'publish_appointments' ), 20, 1 );

		// When an order goes from "Completed" or "Processing" to "Pending Payment", mark appointments as "Unpaid".
		add_action( 'woocommerce_order_status_processing_to_pending', array( $this, 'mark_as_unpaid_appointments' ), 10, 1 );
		add_action( 'woocommerce_order_status_completed_to_pending', array( $this, 'mark_as_unpaid_appointments' ), 10, 1 );

		// When an order is cancelled/fully refunded, cancel the appointments.
		add_action( 'woocommerce_order_status_cancelled', array( $this, 'cancel_appointments' ), 10, 1 );
		add_action( 'woocommerce_order_status_refunded', array( $this, 'cancel_appointments' ), 10, 1 );

		// When an order is partially refunded, maybe cancel the appointments.
		add_action( 'woocommerce_order_partially_refunded', array( $this, 'appointments_for_partial_refunds' ), 10, 1 );

		// When an order is cancelled/fully refunded, cancel the appointments.
		add_action( 'woocommerce_order_status_completed_to_pending', array( $this, 'unpaid_appointments' ), 10, 1 );
		add_action( 'woocommerce_order_status_completed_to_on-hold', array( $this, 'unpaid_appointments' ), 10, 1 );
		add_action( 'woocommerce_order_status_completed_to_failed', array( $this, 'unpaid_appointments' ), 10, 1 );

		// Status transitions
		add_action( 'before_delete_post', array( $this, 'delete_post' ) ); #NOT HPOS
		add_action( 'wp_trash_post', array( $this, 'trash_post' ) ); #NOT HPOS
		add_action( 'untrash_post', array( $this, 'untrash_post' ) ); #NOT HPOS
		add_action( 'wp_untrash_post_status', array( $this, 'untrash_post_status' ), 10, 3 ); #NOT HPOS
		add_action( 'woocommerce_before_delete_order', array( $this, 'delete_post' ) ); #HPOS
		add_action( 'woocommerce_before_trash_order', array( $this, 'trash_post' ) ); #HPOS
		add_action( 'woocommerce_untrash_order', array( $this, 'untrash_post' ) ); #HPOS
		add_action( 'woocommerce_appointment_cancelled', array( $this, 'maybe_cancel_order' ) );
		add_action( 'woocommerce_appointment_paid', array( $this, 'maybe_process_order' ) );
		add_action( 'woocommerce_appointment_unpaid', array( $this, 'maybe_pending_order' ) );

		// Cancelled status totals recalculation.
		add_action( 'woocommerce_appointment_cancelled', array( $this, 'maybe_remove_totals_order' ), 10, 2 );
		add_action( 'woocommerce_appointment_cancelled_to_pending-confirmation', array( $this, 'maybe_add_totals_order' ), 10, 2 );
		add_action( 'woocommerce_appointment_cancelled_to_confirmed', array( $this, 'maybe_add_totals_order' ), 10, 2 );
		add_action( 'woocommerce_appointment_cancelled_to_complete', array( $this, 'maybe_add_totals_order' ), 10, 2 );
		add_action( 'woocommerce_appointment_cancelled_to_unpaid', array( $this, 'maybe_add_totals_order' ), 10, 2 );
		add_action( 'woocommerce_appointment_cancelled_to_paid', array( $this, 'maybe_add_totals_order' ), 10, 2 );

		// Prevent pending being cancelled.
		add_filter( 'woocommerce_cancel_unpaid_order', array( $this, 'prevent_cancel' ), 10, 2 );

		// Control the my orders actions.
		add_filter( 'woocommerce_my_account_my_orders_actions', array( $this, 'my_orders_actions' ), 10, 2 );

		// Sync order user with appointment user
		add_action( 'woocommerce_appointment_in-cart_to_unpaid', array( $this, 'attach_new_user' ), 10, 2 );
		add_action( 'woocommerce_appointment_in-cart_to_pending-confirmation', array( $this, 'attach_new_user' ), 10, 2 );

		// Sync customer ID between order and the appointment ID.
		add_action( 'woocommerce_order_object_updated_props', array( $this, 'sync_appointment_customer_id' ), 10, 2 );

		// Failed Order Management.
		# Added back with 4.20.0.
		add_action( 'woocommerce_order_status_failed', array( $this, 'schedule_failed_order_event' ) );
		add_action( 'woocommerce_appointments_failed_order_expired', array( $this, 'handle_failed_order_scheduled_event' ), 20, 2 );
	}

	/**
	 * Show appointment data if a line item is linked to an appointment ID.
	 */
	public function appointment_display( $item_id, $item, $order ) {
		$product = $item->get_product();
		if ( ! is_wc_appointment_product( $product ) ) {
			return;
		}

		$appointment_ids = WC_Appointment_Data_Store::get_appointment_ids_from_order_and_item_id( $order->get_id(), $item_id );

		wc_get_template(
			'order/appointment-display.php',
			array(
				'appointment_ids' => $appointment_ids,
				'endpoint'        => $this->get_appointments_endpoint(),
				'is_rtl'          => is_rtl() ? 'right' : 'left',
			),
			'',
			WC_APPOINTMENTS_TEMPLATE_PATH
		);
	}

	/**
	 * Remove order again button for appointment orders.
	 */
	public function order_again_button( $order ) {
		if ( ! $order || ! $order->has_status( apply_filters( 'woocommerce_valid_order_statuses_for_order_again', array( 'completed' ) ) ) || ! is_user_logged_in() ) {
			return;
		}

		$appointment_order = 0;

		if ( count( $order->get_items() ) > 0 ) {
			foreach ( $order->get_items() as $item ) {
				if ( $item->is_type( 'line_item' ) ) {
					$product           = $item->get_product();
					$appointment_order = is_wc_appointment_product( $product );
				}
			}
		} else {
			return;
		}

		if ( $appointment_order ) {
			return;
		}

		wc_get_template(
			'order/order-again.php',
			array(
				'order'           => $order,
				'order_again_url' => wp_nonce_url( add_query_arg( 'order_again', $order->get_id(), wc_get_cart_url() ), 'woocommerce-order_again' ),
			)
		);
	}

	/**
	 * Is ID being synced?
	 */
	private static function is_syncing( $id ) {
		return in_array( $id, self::$syncing_ids );
	}

	/**
	 * Store ID on sync.
	 */
	private static function syncing_start( $id ) {
		self::$syncing_ids[] = $id;
	}

	/**
	 * Remove ID on sync completion.
	 */
	private static function syncing_stop( $id ) {
		self::$syncing_ids = array_diff( self::$syncing_ids,  [ $id ] );
	}

	/**
	 * Register new endpoint to use inside My Account page.
	 *
	 * @since 2.1.4
	 * @see https://developer.wordpress.org/reference/functions/add_rewrite_endpoint/
	 */
	public function add_endpoints() {
		add_rewrite_endpoint( $this->get_appointments_endpoint(), EP_PAGES );
		add_rewrite_endpoint( $this->get_reschedule_endpoint(), EP_PAGES );
	}

	/**
	 * Return the my-account/appointments page endpoint.
	 *
	 * @since 2.1.4
	 * @return string
	 */
	public function get_appointments_endpoint() {
		return apply_filters( 'woocommerce_appointments_account_endpoint', 'appointments' );
	}

	/**
	 * Return the my-account/appointments/reschedule page endpoint.
	 *
	 * @since 4.9.8
	 * @return string
	 */
	public function get_reschedule_endpoint() {
		return apply_filters( 'woocommerce_appointments_account_reschedule_endpoint', 'reschedule' );
	}

	/**
	 * Add new query var.
	 *
	 * @since 2.1.4
	 * @param array $vars
	 * @return array
	 */
	public function add_query_vars( $vars ) {
		$vars[] = $this->get_appointments_endpoint();
		$vars[] = $this->get_reschedule_endpoint();
		return $vars;
	}

	/**
	 * Change endpoint title.
	 *
	 * @since 2.1.4
	 * @param string $title
	 * @return string
	 */
	public function endpoint_title( $title ) {
		global $wp_query;
		$is_appointments_endpoint = isset( $wp_query->query_vars[ $this->get_appointments_endpoint() ] );
		$is_reschedule_endpoint   = isset( $wp_query->query_vars[ $this->get_reschedule_endpoint() ] );

		#print '<pre>'; print_r( $wp_query->query_vars ); print '</pre>';

		if ( ! is_admin() && is_main_query() && in_the_loop() && is_account_page() ) {
			if ( $is_appointments_endpoint ) {
				$page_number = intval( $wp_query->query_vars[ $this->get_appointments_endpoint() ] );
				if ( 1 < $page_number ) {
					$title = sprintf(
						/* translators: %d: page number */
						esc_html__( 'Appointments (page %d)', 'woocommerce-appointments' ),
						esc_attr( $page_number )
					);
				} else {
					$title = __( 'Appointments', 'woocommerce-appointments' );
				}
				remove_filter( 'the_title', array( $this, 'endpoint_title' ) );
			} elseif ( $is_reschedule_endpoint ) {
				$appointment_id = intval( $wp_query->query_vars[ $this->get_reschedule_endpoint() ] );
				$title          = sprintf(
					/* translators: %s: Appointment ID */
					esc_html__( 'Reschedule #%d', 'woocommerce-appointments' ),
					esc_attr( $appointment_id )
				);
				remove_filter( 'the_title', array( $this, 'endpoint_title' ) );
			}
		}

		return $title;
	}

	/**
	 * Insert the new endpoint into the My Account menu.
	 *
	 * @since 2.1.4
	 * @param array $items
	 * @return array
	 */
	public function my_account_menu_item( $items ) {
		// Remove logout menu item.
		if ( array_key_exists( 'customer-logout', $items ) ) {
			$logout = $items['customer-logout'];
			unset( $items['customer-logout'] );
		}

		// Add appointments menu item.
		$items[ $this->get_appointments_endpoint() ] = __( 'Appointments', 'woocommerce-appointments' );

		// Add back the logout item.
		if ( isset( $logout ) ) {
			$items['customer-logout'] = $logout;
		}

		return $items;
	}

	/**
	 * Define CSS classes for each menu item.
	 *
	 * @since 4.9.8
	 *
	 * @param array  $classes
	 * @param string $endpoint
	 *
	 * @return array
	 */
	public function my_account_menu_item_classes( $classes, $endpoint ) {
		global $wp;

		if ( 'appointments' === $endpoint && isset( $wp->query_vars['reschedule'] ) ) {
			$classes[] = 'is-active';
		}

		return $classes;
	}

	/**
	 * Appointments Endpoint HTML content.
	 *
	 * @param int $current_page
	 *
	 * @since    1.9.11
	 * @version  4.9.8
	 */
	public function appointments_endpoint_content( $current_page ) {
		$current_page = empty( $current_page ) ? 1 : absint( $current_page );
		$this->my_appointments( $current_page );
	}

	/**
	 * Reschedule Endpoint HTML content.
	 *
	 * @param int $current_page
	 *
	 * @since    4.9.8
	 * @version  4.9.8
	 */
	public function reschedule_endpoint_content( $appointment_id ) {
		$appointment       = get_wc_appointment( absint( $appointment_id ) );
		$is_wc_appointment = is_a( $appointment, 'WC_Appointment' ) ? true : false;

		#error_log( var_export( $appointment, true ) );

		// Stop if not appointment object.
		if ( ! $is_wc_appointment ) {
			wc_add_notice( __( 'Invalid appointment.', 'woocommerce-appointments' ), 'error' );
			exit;
		}

		// Get product object.
		$product = $appointment->get_product();

		// Stop if not appointable product.
		if ( ! is_wc_appointment_product( $product ) ) {
			wc_add_notice( __( 'Invalid appointment.', 'woocommerce-appointments' ), 'error' );
			exit;
		}

		// Make sure only appointment duration is set to product.
		$appointment_duration = $appointment->get_duration_parameters();
		$product->set_duration( $appointment_duration['duration'] );
		$product->set_duration_unit( $appointment_duration['duration_unit'] );

		// Make sure only appointment staff is set to product.
		if ( $appointment->get_staff_ids() ) {
			#$product->set_staff_assignment( 'automatic' );
			$product->set_staff_ids( $appointment->get_staff_ids() );
			$product->set_staff_nopref( false );
		}

		// Get appointment form.
		$appointment_form = new WC_Appointment_Form( $product );

		// Show the reschedule template to customer.
		wc_get_template(
			'myaccount/reschedule.php',
			apply_filters(
				'woocommerce_appointments_reschedule_template_args',
				array(
					'endpoint'         => $this->get_reschedule_endpoint(),
					'product'          => $product,
					'appointment_form' => $appointment_form,
					'appointment'      => $appointment,
					'appointment_id'   => $appointment_id,
				)
			),
			'',
			WC_APPOINTMENTS_TEMPLATE_PATH
		);
	}

	/**
	 * Show a users appointments in My Account > Appointments.
 	 *
	 * @param int   $current_page
	 * @since       2.0.0
	 * @version     3.4.0
	 */
	public function my_appointments( $current_page = 0 ) {
		$user_id = get_current_user_id();

		$appointments_per_page = apply_filters( 'woocommerce_appointments_my_appointments_per_page', 10 );

		$current_appointments = WC_Appointment_Data_Store::get_appointments_for_user(
			$user_id,
			apply_filters(
				'woocommerce_appointments_my_appointments_today_query_args',
				array(
					'order_by'     => apply_filters( 'woocommerce_appointments_my_appointments_today_order_by', 'start_date' ),
					'order'        => 'ASC',
					'date_between' => array(
						'start' => current_time( 'timestamp' ),
						'end'   => current_time( 'timestamp' ),
					),
					'offset'       => ( $current_page - 1 ) * $appointments_per_page,
					'limit'        => $appointments_per_page + 1, // Increment to detect pagination.
				)
			)
		);

		#print '<pre>'; print_r( $current_appointments ); print '</pre>';

		$upcoming_appointments = WC_Appointment_Data_Store::get_appointments_for_user(
			$user_id,
			apply_filters(
				'woocommerce_appointments_my_appointments_upcoming_query_args',
				array(
					'order_by'   => apply_filters( 'woocommerce_appointments_my_appointments_upcoming_order_by', 'start_date' ),
					'order'      => 'ASC',
					'date_after' => current_time( 'timestamp' ),
					'offset'     => ( $current_page - 1 ) * $appointments_per_page,
					'limit'      => $appointments_per_page + 1, // Increment to detect pagination.
				)
			)
		);

		#print '<pre>'; print_r( $upcoming_appointments ); print '</pre>';

		$past_appointments = WC_Appointment_Data_Store::get_appointments_for_user(
			$user_id,
			apply_filters(
				'woocommerce_appointments_my_appointments_past_query_args',
				array(
					'order_by'    => apply_filters( 'woocommerce_appointments_my_appointments_past_order_by', 'start_date' ),
					'order'       => 'DESC',
					'date_before' => current_time( 'timestamp' ),
					'offset'      => ( $current_page - 1 ) * $appointments_per_page,
					'limit'       => $appointments_per_page + 1, // Increment to detect pagination.
				)
			)
		);

		$tables = [];
		if ( ! empty( $current_appointments ) ) {
			$tables['current'] = array(
				'header'       => __( 'Current', 'woocommerce-appointments' ),
				'appointments' => $current_appointments,
			);
		}
		if ( ! empty( $upcoming_appointments ) ) {
			$tables['upcoming'] = array(
				'header'       => __( 'Upcoming', 'woocommerce-appointments' ),
				'appointments' => $upcoming_appointments,
			);
		}
		if ( ! empty( $past_appointments ) ) {
			$tables['past'] = array(
				'header'       => __( 'Past', 'woocommerce-appointments' ),
				'appointments' => $past_appointments,
			);
		}

		wc_get_template(
			'myaccount/appointments.php',
			apply_filters(
				'woocommerce_appointments_my_appointments_template_args',
				array(
					'tables'                => apply_filters( 'woocommerce_appointments_account_tables', $tables ),
					'page'                  => $current_page,
					'endpoint'              => $this->get_appointments_endpoint(),
					'appointments_per_page' => $appointments_per_page,
				)
			),
			'',
			WC_APPOINTMENTS_TEMPLATE_PATH
		);
	}

	/**
	 * Called when an order is paid
	 * @param  int $order_id
	 */
	public function publish_appointments( $order_id ) {
		$order          = wc_get_order( $order_id );
		$payment_method = $order ? $order->get_payment_method() : null;
		$order_id       = apply_filters( 'woocommerce_appointments_publish_appointments_order_id', $order_id );

		if ( class_exists( 'WC_Deposits' ) || class_exists( 'Webtomizer\WCDP\WC_Deposits' ) ) {
			// Is this a final payment?
			$parent_id = wp_get_post_parent_id( $order_id );
			if ( ! empty( $parent_id ) ) {
				$order_id = $parent_id;
			}
		}

		$appointments = WC_Appointment_Data_Store::get_appointment_ids_from_order_id( $order_id );

		// Don't publish appointments for COD orders, but still schedule their events
		$no_publish = $order->has_status( 'processing' ) && 'cod' === $payment_method;

		foreach ( $appointments as $appointment_id ) {
			$appointment = get_wc_appointment( $appointment_id );

			if ( ! $appointment || $appointment->has_status( 'was-in-cart' ) ) {
				continue;
			}

			if ( $no_publish ) {
				$appointment->maybe_schedule_event( 'reminder' );
				$appointment->maybe_schedule_event( 'complete' );
				// Send email notification to admin and staff.
				if ( ! as_next_scheduled_action( 'woocommerce_admin_new_appointment_notification', [ $appointment_id ] ) ) {
					as_schedule_single_action( time(), 'woocommerce_admin_new_appointment_notification', [ $appointment_id ], 'wca' );
				}
			} else {
				$appointment->paid();
			}
		}
	}

	/**
	 * Complete virtual appointment orders.
	 *
	 * $order_id = apply_filters( 'woocommerce_appointments_publish_appointments_order_id', $order_id );
	 *
	 * @param $order_status
	 * @param $order_id
	 * @return string
	 */
	public function complete_order( $order_status, $order_id ) {
 		$order = wc_get_order( $order_id );
 		if ( 'processing' === $order_status
 			&& $order->has_status( array( 'on-hold', 'pending', 'failed' ) ) ) {
 			$virtual_appointment_order = false;

 			if ( count( $order->get_items() ) < 1 ) {
 				return $order_status;
 			}

 			foreach ( $order->get_items() as $item ) {
				if ( $item->is_type( 'line_item' ) ) {
					$product                   = $item->get_product();
					$virtual_appointment_order = $product && $product->is_virtual() && $product->is_type( 'appointment' );
				}
 				if ( ! $virtual_appointment_order ) {
 					break;
 				}
 			}
 			// virtual order, mark as completed
 			if ( $virtual_appointment_order ) {
 				return 'completed';
 			}
 		}

 		// non-virtual order, return original status
 		return $order_status;
 	}

	/**
	 * Called when an order goes from Processing or Completed to Pending.
	 * Sets Appointments to status Unpaid.
	 *
	 * @since 4.9.4
	 * @param  int $order_id Id of current order.
	 * @return void
	 */
	public function mark_as_unpaid_appointments( $order_id ) {
		global $wpdb;
		$order        = wc_get_order( $order_id );
		$appointments = WC_Appointment_Data_Store::get_appointment_ids_from_order_id( $order_id );

		foreach ( $appointments as $appointment_id ) {
			$appointment = get_wc_appointment( $appointment_id );
			$appointment->set_status( 'unpaid' );
			$appointment->save();
		}
	}

	/**
	 * Cancel appointments with order.
	 * @param  int $order_id
	 */
	public function cancel_appointments( $order_id ) {
		$appointments = WC_Appointment_Data_Store::get_appointment_ids_from_order_id( $order_id );

		foreach ( $appointments as $appointment_id ) {
			if ( self::is_syncing( $appointment_id ) ) {
				continue;
			}
			$appointment = get_wc_appointment( $appointment_id );

			// Don't proceed if ID is not of a valid appointment.
			if ( ! is_a( $appointment, 'WC_Appointment' ) ) {
				continue;
			}

			$appointment->update_status( 'cancelled' );
		}

		self::syncing_stop( $order_id );
	}

	/**
	 * Maybe Cancel appointments when an order refunded partially.
	 *
	 * @since 2.7.0
	 * @version 2.7.0
	 * @param int $order_id Order ID.
	 */
	public function appointments_for_partial_refunds( $order_id ) {
		$order                  = wc_get_order( $order_id );
		$cancelled_appointments = [];
		$qty_appointments       = [];

		// Prevents infinite loop during sync.
		$order->update_meta_data( '_appointment_status_sync', true );
		$order->save_meta_data();

		// Collect appointment IDs where refunded qty matches with its order item being refunded.
		foreach ( $order->get_items() as $order_item_id => $item ) {
			$refunded_qty   = $order->get_qty_refunded_for_item( $order_item_id );
			$remaining_qty  = abs( $item->get_quantity() ) - abs( $refunded_qty );
			$refunded_total = $order->get_total_refunded_for_item( $order_item_id );

			#error_log( var_export( $item->get_quantity(), true ) );
			#error_log( var_export( $refunded_qty, true ) );
			#error_log( var_export( $remaining_qty, true ) );
			#error_log( var_export( $refunded_total, true ) );
			#error_log( var_export( $item->get_total(), true ) );

			// Build array of cancelled appointment IDs.
			if ( 'line_item' === $item['type'] && 0 !== $refunded_qty && $item->get_total() <= $refunded_total ) {
				$cancelled_ids = WC_Appointment_Data_Store::get_appointment_ids_from_order_and_item_id( $order_id, $order_item_id );

				if ( $cancelled_ids ) {
					$cancelled_appointments = array_merge(
						$cancelled_appointments,
						$cancelled_ids
					);
				}
			}

			// Build array of reduced qty appointment IDs.
			if ( 'line_item' === $item['type'] && 0 !== $refunded_qty && $item->get_quantity() > abs( $refunded_qty ) ) {
				$qty_ids = WC_Appointment_Data_Store::get_appointment_ids_from_order_and_item_id( $order_id, $order_item_id );

				if ( $qty_ids ) {
					$qty_appointments = array_merge(
						$qty_appointments,
						$qty_ids
					);
				}
			}
		}

		// Update all cancelled appointments.
		foreach ( $cancelled_appointments as $appointment_id ) {
			// Skip appointment that still in synch state.
			if ( get_post_meta( $appointment_id, '_appointment_status_sync', true ) ) {
				continue;
			}

			$appointment = get_wc_appointment( $appointment_id );

			// Don't proceed if ID is not of a valid appointment.
			if ( ! is_a( $appointment, 'WC_Appointment' ) ) {
				continue;
			}

			$appointment->update_status( 'cancelled' );
		}

		// Update all appointments with reduced qty.
		foreach ( $qty_appointments as $appointment_id ) {
			// Skip appointment that still in synch state.
			if ( get_post_meta( $appointment_id, '_appointment_status_sync', true ) ) {
				continue;
			}

			$appointment = get_wc_appointment( $appointment_id );

			// Don't proceed if ID is not of a valid appointment.
			if ( ! is_a( $appointment, 'WC_Appointment' ) ) {
				continue;
			}

			$appointment->set_qty( $remaining_qty );
			$appointment->save();
		}

		WC_Cache_Helper::get_transient_version( 'appointments', true );
		$order->delete_meta_data( '_appointment_status_sync' );
		$order->save_meta_data();
	}

	/**
	 * Unpaid appointments with order.
	 * @param  int $order_id
	 */
	public function unpaid_appointments( $order_id ) {
		$appointments = WC_Appointment_Data_Store::get_appointment_ids_from_order_id( $order_id );

		foreach ( $appointments as $appointment_id ) {
			if ( self::is_syncing( $appointment_id ) ) {
				continue;
			}
			$appointment = get_wc_appointment( $appointment_id );
			$appointment->update_status( 'unpaid' );
		}

		self::syncing_stop( $order_id );
	}

	/**
	 * Removes appointments related to the order being deleted.
	 *
	 * @param mixed $post_id ID of post being deleted
	 */
	public function delete_post( $post_id ) {
		if ( ! current_user_can( 'delete_posts' ) || ! $post_id ) {
			return;
		}

		if ( 'wc_appointment' === get_post_type( $post_id ) ) {
			self::syncing_start( $post_id );

			$order_id   = WC_Appointment_Data_Store::get_appointment_order_id( $post_id );
			$order      = wc_get_order( $order_id );
			$item_count = is_a( $order, 'WC_Order' ) ? count( $order->get_items() ) : 0;

			if ( 1 === $item_count && $order_id && ! self::is_syncing( $order_id ) ) {
				$order->delete( true );
			}

			$this->clear_cron_hooks( (int) $post_id );

			self::syncing_stop( $post_id );
		}

		if ( WC_Appointment_Order_Compat::is_shop_order( $post_id ) ) {
			self::syncing_start( $post_id );

			$appointments = WC_Appointment_Data_Store::get_appointment_ids_from_order_id( $post_id );

			foreach ( $appointments as $appointment_id ) {
				if ( self::is_syncing( $appointment_id ) ) {
					continue;
				}
				wp_delete_post( $appointment_id, true );
			}

			self::syncing_stop( $post_id );
		}
	}

	/**
	 * Trash appointments with orders
	 *
	 * @param mixed $post_id
	 */
	public function trash_post( $post_id ) {
		if ( ! $post_id ) {
			return;
		}

		if ( 'wc_appointment' === get_post_type( $post_id ) ) {
			self::syncing_start( $post_id );

			$order_id   = WC_Appointment_Data_Store::get_appointment_order_id( $post_id );
			$order      = wc_get_order( $order_id );
			$item_count = is_a( $order, 'WC_Order' ) ? count( $order->get_items() ) : 0;

			// only delete this order if this appointment is the only item in it
			if ( 1 === $item_count && $order_id && ! self::is_syncing( $order_id ) ) {
				$order->delete();
			}

			$this->clear_cron_hooks( (int) $post_id );

			self::syncing_stop( $post_id );

			// Clear appointment slots transient.
			WC_Appointments_Cache::flush_all_appointment_connected_transients( get_wc_appointment( $post_id ) );
		}

		if ( WC_Appointment_Order_Compat::is_shop_order( $post_id ) ) {
			self::syncing_start( $post_id );

			$appointments = WC_Appointment_Data_Store::get_appointment_ids_from_order_id( $post_id );

			foreach ( $appointments as $appointment_id ) {
				if ( self::is_syncing( $appointment_id ) ) {
					continue;
				}
				wp_trash_post( $appointment_id );

				// Clear appointment slots transient.
				WC_Appointments_Cache::flush_all_appointment_connected_transients( get_wc_appointment( $appointment_id ) );
			}

			self::syncing_stop( $post_id );
		}
	}

	/**
	 * Untrash appointments with orders
	 *
	 * @param mixed $post_id
	 */
	public function untrash_post( $post_id ) {
		if ( ! $post_id ) {
			return;
		}

		if ( 'wc_appointment' === get_post_type( $post_id ) ) {
			self::syncing_start( $post_id );

			$order_id = WC_Appointment_Data_Store::get_appointment_order_id( $post_id );

			if ( $order_id && ! self::is_syncing( $order_id ) ) {
				WC_Appointment_Order_Compat::untrash_post( $order_id );
			}

			// Schedule again.
			$appointment = get_wc_appointment( $post_id );
			$appointment->schedule_events();

			self::syncing_stop( $post_id );

			// Clear appointment slots transient.
			WC_Appointments_Cache::flush_all_appointment_connected_transients( get_wc_appointment( $post_id ) );
		}

		if ( WC_Appointment_Order_Compat::is_shop_order( $post_id ) ) {
			self::syncing_start( $post_id );

			$appointments = WC_Appointment_Data_Store::get_appointment_ids_from_order_id( $post_id );

			foreach ( $appointments as $appointment_id ) {
				if ( self::is_syncing( $appointment_id ) ) {
					continue;
				}
				wp_untrash_post( $appointment_id );

				// Clear appointment slots transient.
				WC_Appointments_Cache::flush_all_appointment_connected_transients( get_wc_appointment( $appointment_id ) );
			}

			self::syncing_stop( $post_id );
		}
	}

	/**
	 * Keep the old status of appointments when untrashing a appointment/order.
	 *
	 * @param string $new_status New status.
	 * @param int    $post_id Post ID.
	 * @param string $previous_status Previous status.
	 *
	 * @return string Updated status.
	 */
	public function untrash_post_status( $new_status, $post_id, $previous_status ) {
		if ( 'wc_appointment' === get_post_type( $post_id ) ) {
			return $previous_status;
		}

		if ( WC_Appointment_Order_Compat::is_shop_order( $post_id ) ) {
			$appointments = WC_Appointment_Data_Store::get_appointment_ids_from_order_id( $post_id );

			foreach ( $appointments as $appointment_id ) {
				$appointment = get_wc_appointment( $appointment_id );
				$appointment->update_status( $previous_status );
			}
		}

		return $new_status;
	}

	/**
	 * Clear cron hooks for appointment
	 *
	 * @param mixed $post_id
	 */
	public function clear_cron_hooks( $post_id ) {
		as_unschedule_action( 'wc-appointment-reminder', array( $post_id ), 'wca' );
		as_unschedule_action( 'wc-appointment-complete', array( $post_id ), 'wca' );
		as_unschedule_action( 'wc-appointment-remove-inactive-cart', array( $post_id ), 'wca' );
		as_unschedule_action( 'wc-appointment-follow-up', array( $post_id ), 'wca' );
	}

	/**
	 * Stops WC cancelling unpaid appointments orders.
	 *
	 * @param  bool $return
	 * @param  object $order
	 * @return bool
	 */
	public function prevent_cancel( $return, $order ) {
		// Don't cancel unpaid appointments created through admin.
		$created_via = $order ? $order->get_created_via() : null;
		// Don't cancel unpaid orders that require appointment confirmation.
		$payment_method = $order ? $order->get_payment_method() : null;
		// Appointments gatway ID (include previous).
		$appointments_gateway_ids = array(
			'wcappointmentsgateway',
			'wc-appointment-gateway',
			'wc-appointments-gateway',
		);

		if ( 'appointments' === $created_via || in_array( $payment_method, $appointments_gateway_ids ) ) {
			return false;
		}

		return $return;
	}

	/**
	 * My Orders custom actions.
	 *
	 * Remove the pay button when the appointment requires confirmation.
	 * @hooked woocommerce_my_account_my_orders_actions
	 * @param  array $actions
	 * @param  WC_Order $order
	 * @return array
	 */
	public function my_orders_actions( $actions, $order ) {
		if ( ! is_a( $order, 'WC_Order' ) ) {
			return $actions;
		}
		$payment_method = $order ? $order->get_payment_method() : null;

		// Appointments gatway ID (include previous).
		$appointments_gateway_ids = array(
			'wcappointmentsgateway',
			'wc-appointment-gateway',
			'wc-appointments-gateway',
		);

		if ( $order->has_status( 'pending' ) && in_array( $payment_method, $appointments_gateway_ids ) ) {
			$status = [];

			foreach ( $order->get_items() as $order_item_id => $item ) {
				$appointment_ids = WC_Appointment_Data_Store::get_appointment_ids_from_order_item_id( $order_item_id );
				if ( $appointment_ids ) {
					foreach ( $appointment_ids as $appointment_id ) {
						$appointment = get_wc_appointment( $appointment_id );
						$status[]    = $appointment->get_status();
					}
				}
			}

			if ( in_array( 'pending-confirmation', $status ) && isset( $actions['pay'] ) ) {
				unset( $actions['pay'] );
			}
		}

		return $actions;
	}

	/**
	 * Triggered after an order is updated.
	 *
	 * @param  WC_Order $order
	 * @param  array    $props
	 */
	public function sync_appointment_customer_id( $order, $props ) {
		if ( in_array( 'customer_id', $props ) ) {
			$appointment_ids = WC_Appointment_Data_Store::get_appointment_ids_from_order_id( $order->get_id() );

			foreach ( $appointment_ids as $appointment_id ) {
				$appointment = get_wc_appointment( $appointment_id );

				if ( $appointment->get_customer_id() !== $order->get_customer_id() ) {
					$appointment->set_customer_id( $order->get_customer_id() );
					$appointment->save();
				}
			}
		}
	}

	/**
	 * Attaches a newly created user (during checkout) to an appointment.
	 */
	public function attach_new_user( $appointment_id, $appointment ) {
		if ( 0 === $appointment->get_customer_id() && get_current_user_id() > 0 ) {
			$appointment->set_customer_id( get_current_user_id() );
			$appointment->save( false );
		}
	}

	/**
	 * Cancel order with appointments.
	 *
	 * @param  int $appointment_id
	 */
	public function maybe_cancel_order( $appointment_id ) {
		$this->maybe_update_order( $appointment_id, 'cancelled' );
	}

	/**
	 * Sync order with appointments (paid) only in case where the appointment is the only item in the order.
	 *
	 * @param  int $appointment_id
	 */
	public function maybe_process_order( $appointment_id ) {
		$this->maybe_update_order( $appointment_id, 'processing' );
	}

	/**
	 * Sync order with appointments (unpaid) only in case where the appointment is the only item in the order.
	 *
	 * @param  int $appointment_id
	 */
	public function maybe_pending_order( $appointment_id ) {
		$this->maybe_update_order( $appointment_id, 'pending' );
	}

	/**
	 * Update appointment's related order
	 * This only applies if the order has only the appointment as an item.
	 *
	 *
	 * @param  int $appointment_id
	 * @param  string $status
	 */
	private function maybe_update_order( $appointment_id, $status ) {
 		// Prevents infinite loop during synchronization
 		self::syncing_start( $appointment_id );

 		$order_id = WC_Appointment_Data_Store::get_appointment_order_id( $appointment_id );
 		$order    = wc_get_order( $order_id );

 		if ( ! is_a( $order, 'WC_Order' ) ) {
 			self::syncing_stop( $appointment_id );
 			return;
 		}

 		$completed = 'processing' === $status && 'completed' === $order->get_status();
 		$refunded  = 'cancelled' === $status && 'refunded' === $order->get_status();

 		// Do not update status of completed or refunded orders.
 		if ( self::is_syncing( $order_id ) || $completed || $refunded ) {
 			self::syncing_stop( $appointment_id );
 			return;
 		}

		// Do not change a failed order to "pending" when the appointment goes to "unpaid".
		if (
			'pending' === $status &&
		    'failed' === $order->get_status() &&
			'unpaid' === get_wc_appointment( $appointment_id )->get_status()
		) {
			self::syncing_stop( $appointment_id );
			return;
		}

 		// Only update status if the order has 1 appointment
 		if ( 1 === count( $order->get_items() ) ) {
 			$order->update_status( $status );
 		} elseif ( 'cancelled' === $status ) {
 			$appointment_ids = WC_Appointment_Data_Store::get_order_contains_only_appointments( $order );

 			if ( empty( $appointment_ids ) ) {
 				self::syncing_stop( $appointment_id );
 				return;
 			}

 			$appointment_statuses = array_map(
				function( $appointment_id ) {
	 				return get_wc_appointment( $appointment_id )->get_status();
	 			},
				$appointment_ids
			);

 			// Cancel the order only if all Appointments are cancelled
 			if ( array( $status ) === array_unique( $appointment_statuses ) ) {
 				$order->update_status( $status );
 			}
 		}

 		self::syncing_stop( $appointment_id );
 	}

	/**
	 * Sync order with appointments (unpaid) only in case where the appointment is the only item in the order.
	 *
	 * @param  int $appointment_id
	 */
	public function maybe_remove_totals_order( $appointment_id ) {
		$this->maybe_recalculate_order( $appointment_id, 'cancelled' );
	}

	/**
	 * Sync order with appointments (unpaid) only in case where the appointment is the only item in the order.
	 *
	 * @param  int $appointment_id
	 */
	public function maybe_add_totals_order( $appointment_id ) {
		$this->maybe_recalculate_order( $appointment_id, 'not-cancelled' );
	}

	/**
	 * Update appointment's related order totals
	 * This only applies if the order has only the appointment as an item.
	 *
	 *
	 * @param  int $appointment_id
	 * @param  string $status
	 */
	private function maybe_recalculate_order( $appointment_id, $status ) {
 		// Prevents infinite loop during synchronization
 		self::syncing_start( $appointment_id );

 		$order_id = WC_Appointment_Data_Store::get_appointment_order_id( $appointment_id );
 		$order    = wc_get_order( $order_id );

 		if ( ! is_a( $order, 'WC_Order' ) ) {
 			self::syncing_stop( $appointment_id );
 			return;
 		}

		if ( 1 >= count( $order->get_items() ) ) {
			self::syncing_stop( $appointment_id );
			return;
		}

		// Customers can turn off recaulculation.
		if ( apply_filters( 'woocommerce_appointments_stop_recalculate_order', false, $appointment_id ) ) {
			return;
		}

 		// Only update status if the order has 1 appointment
		$appointment_ids = WC_Appointment_Data_Store::get_order_contains_only_appointments( $order );

		if ( empty( $appointment_ids ) ) {
			self::syncing_stop( $appointment_id );
			return;
		}

		// Calculate totals again.
		foreach ( $order->get_items() as $item_id => $item ) {
			$appointment_ids = WC_Appointment_Data_Store::get_appointment_ids_from_order_item_id( $item_id );
			if ( $appointment_ids && in_array( $appointment_id, $appointment_ids ) ) {
				if ( ! $item->is_type( 'line_item' ) ) {
					continue;
				}

				// Appointment status goes to cancelled.
				if ( 'cancelled' === $status ) {
					// Update line item totals.
					$item->set_total( 0 ); #only update total (DISCOUNT), leave subtotal.
					$item->set_total_tax( 0 ); #only update total (DISCOUNT), leave subtotal.
					$item->save(); #save

				// Appointment status goes from cancelled.
				} else {
					// Update line item totals.
					$item->set_total( $item->get_subtotal() ); #update total from subtotal.
					$item->set_total_tax( $item->get_subtotal_tax() ); #update total from subtotal.
					$item->save(); #save
				}
			}
		}

		// Recalculate totals and taxes.
		$order->calculate_totals( true );

 		self::syncing_stop( $appointment_id );
 	}

	/**
	 * Scheduling an event for a failed order if it has appointments.
	 *
	 * @since 3.5.6
	 *
	 * @param integer $order_id The order that failed.
	 */
	public function schedule_failed_order_event( $order_id ) {
		$appointment_ids = WC_Appointment_Data_Store::get_appointment_ids_from_order_id( $order_id );
		if ( empty( $appointment_ids ) ) {
			return;
		}

		// Revert the appointment status to in-cart to enable the re-payment with the same order and appointment.
		foreach ( $appointment_ids as $appointment_id ) {
			// Only change status for appointments not already cancelled, paid or completed.
			if ( $appointment && ! $appointment->has_status( [ 'paid', 'complete', 'cancelled' ] ) ) {
				$appointment->set_status( 'in-cart' );
				$appointment->save();
			}
		}

		$args = [
			'order_id'        => $order_id,
			'appointment_ids' => $appointment_ids,
		];

		$timestamp = apply_filters(
			'woocommerce_appointments_failed_order_expire_scheduled_time_stamp',
			time() + ( HOUR_IN_SECONDS )
		);

		// Schedule faild order action.
		as_schedule_single_action(
			$timestamp,
			'woocommerce_appointments_failed_order_expired',
			$args,
			'wca'
		);
	}

	/**
	 * Responding to scheduled event for a failed order.
	 *
	 * @since 3.5.6
	 *
	 * @param integer $order_id The order that failed.
	 * @param array   $appointment_ids Attached to failed order.
	 */
	public function handle_failed_order_scheduled_event( $order_id = 0, $appointment_ids = [] ) {
		$order = wc_get_order( $order_id );
		if ( ! is_a( $order, 'WC_Order' )
			|| 'failed' !== $order->get_status()
			|| empty( $appointment_ids ) ) {
			return;
		}

		foreach ( $appointment_ids as $appointment_id ) {
			$appointment = get_wc_appointment( $appointment_id );

			// Don't proceed if ID is not of a valid appointment.
			if ( ! is_a( $appointment, 'WC_Appointment' ) && $appointment->has_status( [ 'paid', 'complete', 'cancelled' ] ) ) {
				continue;
			}

			/* translators: %1$d: appointment ID */
			$order->add_order_note( sprintf( __( 'Appointment #%1$d cancelled due to failed order.', 'woocommerce-appointments' ), $appointment->get_id() ) );

			// Only cancel appointments not already cancelled, paid or completed.
			if ( $appointment && $appointment->get_order_id() === $order_id ) {
				$appointment->set_status( 'cancelled' );
				$appointment->save();
			}
		}
	}
}

$GLOBALS['wc_appointment_order_manager'] = new WC_Appointment_Order_Manager();
