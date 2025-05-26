<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Appointment ajax callbacks.
 */
class WC_Appointments_Admin_Ajax {

	/**
	 * Constructor
	 */
	public function __construct() {
		// TODO: Switch from `wp_ajax` to `wc_ajax`
		add_action( 'wp_ajax_woocommerce_add_appointable_staff', [ $this, 'add_appointable_staff' ] );
		add_action( 'wp_ajax_woocommerce_remove_appointable_staff', [ $this, 'remove_appointable_staff' ] );
		add_action( 'wp_ajax_woocommerce_add_staff_product', [ $this, 'add_staff_product' ] );
		add_action( 'wp_ajax_woocommerce_manual_sync', [ $this, 'manual_sync' ] );
		add_action( 'wp_ajax_woocommerce_oauth_redirect', [ $this, 'oauth_redirect' ] );
		add_action( 'wp_ajax_woocommerce_json_search_appointable_products', [ $this, 'json_search_appointable_products' ] );
		add_action( 'wp_ajax_wc-appointment-confirm', [ $this, 'mark_appointment_confirmed' ] );
		add_action( 'wp_ajax_wc_appointments_calculate_costs', [ $this, 'calculate_costs' ] );
		add_action( 'wp_ajax_nopriv_wc_appointments_calculate_costs', [ $this, 'calculate_costs' ] );
		add_action( 'wp_ajax_wc_appointments_get_slots', [ $this, 'get_time_slots_for_date' ] );
		add_action( 'wp_ajax_nopriv_wc_appointments_get_slots', [ $this, 'get_time_slots_for_date' ] );
		add_action( 'wp_ajax_wc_appointments_json_search_order', [ $this, 'json_search_order' ] );
	}

	/**
	 * Add staff
	 */
	public function add_appointable_staff() {
		check_ajax_referer( 'add-appointable-staff', 'security' );

		$post_id      = intval( $_POST['post_id'] );
		$loop         = intval( $_POST['loop'] );
		$add_staff_id = intval( $_POST['add_staff_id'] );

		if ( ! $add_staff_id ) {
			$staff = new WC_Product_Appointment_Staff( 0 );
		} else {
			$staff = new WC_Product_Appointment_Staff( $add_staff_id );
		}

		// Return html
		if ( $add_staff_id ) {
			$appointable_product = get_wc_product_appointment( $post_id );
			$staff_ids           = $appointable_product->get_staff_ids();

			if ( in_array( $add_staff_id, $staff_ids ) ) {
				wp_send_json(
					[
						'error' => __( 'The staff has already been assigned to this product', 'woocommerce-appointments' ),
					]
				);
			}

			$staff_ids[] = $add_staff_id;
			$appointable_product->set_staff_ids( $staff_ids );
			$appointable_product->save();

			// get the post object due to it is used in the included template
			$post = get_post( $post_id );

			ob_start();
			include 'views/html-appointment-staff-member.php';
			wp_send_json(
				[
					'html' => ob_get_clean(),
				]
			);
		}

		wp_send_json(
			[
				'error' => __( 'Unable to add staff', 'woocommerce-appointments' ),
			]
		);
	}

	/**
	 * Remove staff
	 * TO DO: you should revert post meta logic that is set in class-wc-appointments-admin.php on line 559-593 ????
	 */
	public function remove_appointable_staff() {
		check_ajax_referer( 'delete-appointable-staff', 'security' );

		$post_id   = absint( $_POST['post_id'] );
		$staff_id  = absint( $_POST['staff_id'] );
		$product   = get_wc_product_appointment( $post_id );
		$staff_ids = $product->get_staff_ids();
		$staff_ids = array_diff( $staff_ids, [ $staff_id ] );
		$product->set_staff_ids( $staff_ids );
		$product->save();
		die();
	}

	/**
	 * Add staff
	 */
	public function add_staff_product() {
		check_ajax_referer( 'add-staff-product', 'security' );

		$add_product_id    = intval( $_POST['add_product_id'] );
		$staff_id          = intval( $_POST['staff_id'] );
		$assigned_products = explode( ',', $_POST['assigned_products'] );

		if ( ! $add_product_id ) {
			wp_send_json(
				[
					'error' => __( 'Unable to add product', 'woocommerce-appointments' ),
				]
			);
		}

		// Return html
		if ( $add_product_id ) {
			if ( ! empty( $assigned_products ) && is_array( $assigned_products ) && in_array( $add_product_id, $assigned_products ) ) {
				wp_send_json(
					[
						'error' => __( 'The product has already been assigned to this staff', 'woocommerce-appointments' ),
					]
				);
			}

			// Variables needed for 'views/html-appointment-staff-fields.php'
			$user_id         = $staff_id;
			$user_product_id = $add_product_id;

			ob_start();
			include 'views/html-appointment-staff-fields.php';
			wp_send_json(
				[
					'html' => ob_get_clean(),
				]
			);
		}

		wp_send_json(
			[
				'error' => __( 'Unable to add product', 'woocommerce-appointments' ),
			]
		);
	}

	/**
	 * Manually perform calendar sync.
	 */
	public function manual_sync() {
		$nonce = $_POST['security'];

		if ( empty( $_POST ) || ! wp_verify_nonce( $nonce, 'add-manual-sync' ) ) {
		    wp_send_json(
				[
					'error' => __( 'Reload the page and try again.', 'woocommerce-appointments' ),
				]
			);
		}

		check_ajax_referer( 'add-manual-sync', 'security' );

		$user_id = isset( $_POST['staff_id'] ) ? absint( $_POST['staff_id'] ) : '';
		$counter = false;

		if ( $user_id ) {

			// Run GCal sync manually.
			$gcal_integration_class = wc_appointments_gcal();
			$gcal_integration_class->set_user_id( $user_id );
			$gcal_integration_class->sync_from_gcal();
			$last_synced_saved     = get_user_meta( $user_id, 'wc_appointments_gcal_availability_last_synced', true );
			$last_synced_timestamp = isset( $last_synced_saved[0] ) && $last_synced_saved[0] ? absint( $last_synced_saved[0] ) : absint( current_time( 'timestamp' ) );
			$last_synced_counter   = isset( $last_synced_saved[1] ) && $last_synced_saved[1] ? absint( $last_synced_saved[1] ) : 0;

			// Update last synced timestamp.
			$last_synced[] = absint( current_time( 'timestamp' ) );
			$last_synced[] = $last_synced_counter;
			update_user_meta( $user_id, 'wc_appointments_gcal_availability_last_synced', $last_synced );

		} else {

			// Run GCal sync manually.
			$gcal_integration_class = wc_appointments_gcal();
			$gcal_integration_class->sync_from_gcal();
			$last_synced_saved     = get_option( 'wc_appointments_gcal_availability_last_synced' );
			$last_synced_timestamp = isset( $last_synced_saved[0] ) && $last_synced_saved[0] ? absint( $last_synced_saved[0] ) : absint( current_time( 'timestamp' ) );
			$last_synced_counter   = isset( $last_synced_saved[1] ) && $last_synced_saved[1] ? absint( $last_synced_saved[1] ) : 0;

			// Update last synced timestamp.
			$last_synced[] = absint( current_time( 'timestamp' ) );
			$last_synced[] = $last_synced_counter;
			update_option( 'wc_appointments_gcal_availability_last_synced', $last_synced );

		}

		// Last sycned event count, date, time.
		/* translators: %1$s: date format, %2$s: time format */
		$ls_message  = sprintf( __( '%1$s, %2$s', 'woocommerce-appointments' ), date_i18n( wc_appointments_date_format(), $last_synced_timestamp ), date_i18n( wc_appointments_time_format(), $last_synced_timestamp ) );
		$ls_message .= ' - ';
		if ( $user_id ) {
			/* translators: %1$s: link to staff rules, %2$s: sync text */
			$ls_message .= sprintf( '<a href="%1$s" onclick="location.reload()">%2$s</a>', esc_url( admin_url( "user-edit.php?user_id=$user_id#staff-details" ) ), __( 'check synced events', 'woocommerce-appointments' ) );
		} else {
			/* translators: %1$s: link to global rules, %2$s: sync text */
			$ls_message .= sprintf( '<a href="%1$s">%2$s</a>', esc_url( admin_url( 'admin.php?page=wc-settings&tab=appointments&view=synced' ) ), __( 'check synced events', 'woocommerce-appointments' ) );
		}

		wp_send_json(
			[
				'result' => 'SUCCESS',
				'html'   => $ls_message,
			]
		);

		die();
	}

	/**
	 * Manually perform calendar sync.
	 */
	public function oauth_redirect() {
		$nonce = $_POST['security'];

		if ( empty( $_POST ) || ! wp_verify_nonce( $nonce, 'add-oauth-redirect' ) ) {
		    wp_send_json(
				[
					'error' => __( 'Reload the page and try again.', 'woocommerce-appointments' ),
				]
			);
		}

		check_ajax_referer( 'add-oauth-redirect', 'security' );

		$user_id = isset( $_POST['staff_id'] ) ? absint( $_POST['staff_id'] ) : '';
		$logout  = isset( $_POST['logout'] ) && $_POST['logout'] ? true : false;

		if ( $user_id ) {
			// Run Gcal oauth redirect.
			$gcal_integration_class = wc_appointments_gcal();
			$gcal_integration_class->set_user_id( $user_id );

			$oauth_url = add_query_arg(
				[
					'scope'           => $gcal_integration_class->api_scope,
					'redirect_uri'    => $gcal_integration_class->get_redirect_uri(),
					'response_type'   => 'code',
					'client_id'       => $gcal_integration_class->get_client_id(),
					'approval_prompt' => 'force',
					'access_type'     => 'offline',
					'state'           => $user_id,
				],
				$gcal_integration_class->oauth_uri . 'auth'
			);

			// Logout.
			if ( $logout ) {
				$oauth_url = add_query_arg(
					[
						'logout' => 'true',
						'state'  => $user_id,
					],
					$gcal_integration_class->get_redirect_uri()
				);
			// Run GCal sync for the first time.
			} else {
				$gcal_integration_class->sync_from_gcal();
			}
		} else {
			// Run Gcal oauth redirect.
			$gcal_integration_class = wc_appointments_gcal();

			$oauth_url = add_query_arg(
				[
					'scope'           => $gcal_integration_class->api_scope,
					'redirect_uri'    => $gcal_integration_class->get_redirect_uri(),
					'response_type'   => 'code',
					'client_id'       => $gcal_integration_class->get_client_id(),
					'approval_prompt' => 'force',
					'access_type'     => 'offline',
				],
				$gcal_integration_class->oauth_uri . 'auth'
			);

			// Logout.
			if ( $logout ) {
				$oauth_url = add_query_arg(
					[
						'logout' => 'true',
					],
					$gcal_integration_class->get_redirect_uri()
				);
			// Run GCal sync for the first time.
			} else {
				$gcal_integration_class->sync_from_gcal();
			}
		}

		wp_send_json(
			[
				'result' => 'SUCCESS',
				'uri'    => $oauth_url,
			]
		);

		die();
	}

	/**
	 * Search for appointable products and return json.
	 *
	 * @see WC_AJAX::json_search_appointable_products()
	 */
	public static function json_search_appointable_products() {
		check_ajax_referer( 'search-products', 'security' );

		if ( ! empty( $_GET['limit'] ) ) {
			$limit = absint( $_GET['limit'] );
		} else {
			$limit = absint( apply_filters( 'woocommerce_json_search_limit', 30 ) );
		}

		$include_ids = ! empty( $_GET['include'] ) ? array_map( 'absint', (array) wp_unslash( $_GET['include'] ) ) : [];
		$exclude_ids = ! empty( $_GET['exclude'] ) ? array_map( 'absint', (array) wp_unslash( $_GET['exclude'] ) ) : [];

		$term       = isset( $_GET['term'] ) ? (string) wc_clean( wp_unslash( $_GET['term'] ) ) : '';
		$data_store = WC_Data_Store::load( 'product' );
		$ids        = $data_store->search_products( $term, '', true, false, $limit );

		$product_objects = array_filter( array_map( 'wc_get_product', $ids ), 'wc_products_array_filter_readable' );
		$products        = [];
		$current_user_id = get_current_user_id();

		foreach ( $product_objects as $product_object ) {
			if ( ! $product_object->is_type( 'appointment' ) ) {
				continue;
			}
			$staff_ids        = $product_object->get_staff_ids();
			$personal_product = '';
			if ( $product_object->has_staff() && in_array( $current_user_id, (array) $staff_ids ) ) {
				$personal_product = ' - <strong>' . esc_html( 'assigned to you', 'woocommerce-appointments' ) . '</strong>';
			}
			$products[ $product_object->get_id() ] = rawurldecode( $product_object->get_formatted_name() ) . $personal_product;
		}

		wp_send_json( $products );
	}

	/**
	 * Mark an appointment confirmed
	 */
	public function mark_appointment_confirmed() {
		if ( ! current_user_can( 'manage_appointments' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'woocommerce-appointments' ) );
		}
		if ( ! check_admin_referer( 'wc-appointment-confirm' ) ) {
			wp_die( esc_html__( 'You have taken too long. Please go back and retry.', 'woocommerce-appointments' ) );
		}
		$appointment_id = isset( $_GET['appointment_id'] ) && (int) $_GET['appointment_id'] ? (int) $_GET['appointment_id'] : '';
		if ( ! $appointment_id ) {
			die;
		}

		$appointment = get_wc_appointment( $appointment_id );
		if ( 'confirmed' !== $appointment->get_status() ) {
			$appointment->update_status( 'confirmed' );
		}

		wp_safe_redirect( wp_get_referer() );
		die();
	}

	/**
	 * Calculate costs
	 *
	 * Take posted appointment form values and then use these to quote a price for what has been chosen.
	 * Returns a string which is appended to the appointment form.
	 */
	public function calculate_costs() {
		$posted = [];

		parse_str( $_POST['form'], $posted );

		$product_id = $posted['add-to-cart'] ?? 0;
		if ( ! $product_id && isset( $posted['appointable-product-id'] ) ) {
			$product_id = $posted['appointable-product-id'];
		}
		$product = get_wc_product_appointment( $product_id );

		if ( ! $product ) {
			wp_send_json(
				[
					'result' => 'ERROR',
					'html'   => apply_filters(
						'woocommerce_appointments_appointment_cost_html',
						'<span class="appointment-error">' . __( 'This appointment is unavailable.', 'woocommerce-appointments' ) . '</span>',
						null,
						$posted
					),
				]
			);
		}

		$appointment_form = new WC_Appointment_Form( $product );
		$cost             = WC_Appointments_Cost_Calculation::calculate_appointment_cost( $posted, $product );

		if ( is_wp_error( $cost ) ) {
			wp_send_json(
				[
					'result' => 'ERROR',
					'html'   => apply_filters(
						'woocommerce_appointments_appointment_cost_html',
						'<span class="appointment-error">' . $cost->get_error_message() . '</span>',
						null,
						$posted
					),
				]
			);
		}

		if ( 'incl' === get_option( 'woocommerce_tax_display_shop' ) ) {
			$display_price = wc_get_price_including_tax(
				$product,
				[
					'price' => $cost,
				]
			);
		} else {
			$display_price = wc_get_price_excluding_tax(
				$product,
				[
					'price' => $cost,
				]
			);
		}

		$appointment_cost_html = '
			<dl>
				<dt>' . _x( 'Cost', 'appointment cost string', 'woocommerce-appointments' ) . ':</dt>
				<dd><strong>' . $product->get_cost_html( $cost ) . '</strong></dd>
			</dl>
		';
		$appointment_cost_html = apply_filters(
			'woocommerce_appointments_appointment_cost_html',
			$appointment_cost_html,
			$product,
			$posted
		);

		wp_send_json(
			[
				'result' => 'SUCCESS',
				'html'   => apply_filters(
					'woocommerce_appointments_calculated_appointment_cost_success_output',
					$appointment_cost_html,
					$display_price,
					$product
				),
			]
		);
	}

	/**
	 * Get a list of time slots available on a date
	 */
	public function get_time_slots_for_date() {
		// Clean posted data.
		$posted = [];
		parse_str( $_POST['form'], $posted );

		$product_id = $posted['add-to-cart'] ?? 0;
		if ( ! $product_id && isset( $posted['appointable-product-id'] ) ) {
			$product_id = $posted['appointable-product-id'];
		}

		// Product Checking.
		$product = get_wc_product_appointment( $product_id );
		if ( ! $product ) {
			return false;
		}

		#print '<pre>'; print_r( $posted ); print '</pre>';

		// Rescheduling appointment?
		if ( isset( $posted['reschedule-appointment'] ) && $posted['reschedule-appointment'] && isset( $posted['appointment-id'] ) ) {
			$appointment       = get_wc_appointment( absint( $posted['appointment-id'] ) );
 			$is_wc_appointment = is_a( $appointment, 'WC_Appointment' ) ? true : false;

			// Is appointment object?
			if ( $is_wc_appointment ) {
				// Make sure only appointment duration is set to product.
				$appointment_duration = $appointment->get_duration_parameters();
				$product->set_duration( $appointment_duration['duration'] );
				$product->set_duration_unit( $appointment_duration['duration_unit'] );

				// Make sure qty is taken into account.
				if ( 1 < $product->get_qty() ) {
					$remaining_qty = abs( $product->get_qty() ) - abs( $appointment->get_qty() );
					$product->set_qty( max( $remaining_qty, 0 ) ); #negative number is set to 0.
				}

				// Make sure only appointment staff is set to product.
				if ( $appointment->get_staff_ids() ) {
					#$product->set_staff_assignment( 'automatic' );
					$product->set_staff_ids( $appointment->get_staff_ids() );
					$product->set_staff_nopref( false );
				}

				#print '<pre>'; print_r( $product->get_duration() ); print '</pre>';
			}
		}

		// Addons duration.
		$addons_duration = $_POST['duration'] ?? 0;

		#print '<pre>'; print_r( $addons_duration ); print '</pre>';

		// Check selected date.
		if ( ! empty( $posted['wc_appointments_field_start_date_year'] ) && ! empty( $posted['wc_appointments_field_start_date_month'] ) && ! empty( $posted['wc_appointments_field_start_date_day'] ) ) {
			$year      = max( date( 'Y' ), absint( $posted['wc_appointments_field_start_date_year'] ) );
			$month     = absint( $posted['wc_appointments_field_start_date_month'] );
			$day       = absint( $posted['wc_appointments_field_start_date_day'] );
			$timestamp = strtotime( "{$year}-{$month}-{$day}" );
		}
		if ( empty( $timestamp ) ) {
			die( esc_html__( 'Please enter a valid date.', 'woocommerce-appointments' ) );
		}

		// Intervals.
		list( $interval, $base_interval ) = $product->get_intervals();

		// Adjust duration if extended with addons.
		if ( 0 !== $addons_duration ) {
			$interval += absint( $addons_duration );
			$interval  = $interval > 0 ? $interval : 0; #turn negative duration to zero.
		}

		$from              = strtotime( 'midnight', $timestamp );
		$to                = strtotime( '+1 day', $from ) + $interval;
		$time_to_check     = ! empty( $posted['wc_appointments_field_start_date_time'] ) ? strtotime( $posted['wc_appointments_field_start_date_time'] ) : 0;
		$staff_id_to_check = ! empty( $posted['wc_appointments_field_staff'] ) ? $posted['wc_appointments_field_staff'] : 0;
		$staff_member      = $product->get_staff_member( absint( $staff_id_to_check ) );
		$staff             = $product->get_staff();
		if ( $staff_id_to_check && $staff_member ) {
			$staff_id_to_check = $staff_member->get_id();
		} elseif ( ( $staff ) && count( $staff ) === 1 ) {
			$staff_id_to_check = current( $staff )->ID;
		} elseif ( $product->is_staff_assignment_type( 'all' ) ) {
			$staff_id_to_check = $product->get_staff_ids();
		} else {
			$staff_id_to_check = 0;
		}

		// Timezones.
		$tzstring = 'UTC';
		if ( $product->has_timezones() ) {
			$site_tzstring = wc_timezone_string();
			$tzstring      = $_COOKIE['appointments_time_zone'] ?? '';
			$tzstring      = $posted['wc_appointments_field_timezone'] ?? $tzstring;

			// Span 1 day before and after to account for all timezones.
			/*
			$local_from = strtotime( '-1 day', $from );
			$local_to   = strtotime( '+1 day', $to );
			$from       = $tzstring && $tzstring !== $site_tzstring ? $local_from : $from;
			$to         = $tzstring && $tzstring !== $site_tzstring ? $local_to : $to;
			*/

			#print '<pre>'; print_r( $tzstring ); print '</pre>';
			#print '<pre>'; print_r( date( 'Y-m-d H:i', $from ) ); print '</pre>';
		}

		#$logger = wc_get_logger();
		#$logger->alert( wc_print_r( date( 'Y-m-d H:i:s', microtime( true ) ), true ) );

		// Get appointments.
		$appointments          = [];
		$existing_appointments = WC_Appointment_Data_Store::get_all_existing_appointments( $product, $from, $to, $staff_id_to_check );
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

		// Slots HTML.
		$slots     = $product->get_slots_in_range( $from, $to, [ $interval, $base_interval ], $staff_id_to_check );
		$slot_html = $product->get_time_slots_html(
			[
				'slots'         => $slots,
				'intervals'     => [ $interval, $base_interval ],
				'time_to_check' => $time_to_check,
				'staff_id'      => $staff_id_to_check,
				'from'          => $from,
				'to'            => $to,
				'timestamp'     => $timestamp,
				'timezone'      => $tzstring,
				'appointments'  => $appointments,
			]
		);

		if ( empty( $slot_html ) ) {
			$slot_html .= __( 'No slots available.', 'woocommerce-appointments' );
		}

		die( $slot_html );
	}

	/**
	 * Search for orders and return json.
	 */
	public function json_search_order() {
		global $wpdb;

		check_ajax_referer( 'search-appointment-order', 'security' );

		$term = wc_clean( stripslashes( $_GET['term'] ) );

		if ( empty( $term ) ) {
			die();
		}

		$found_orders = [];

		$term = apply_filters( 'woocommerce_appointment_json_search_order_number', $term );

		$query_orders = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_title FROM {$wpdb->posts} AS posts
				WHERE posts.post_type = 'shop_order'
				AND posts.ID LIKE %s
				LIMIT 10",
				$term . '%'
			)
		);

		if ( WC_Appointment_Order_Compat::is_cot_enabled() ) {
			$query_orders = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id FROM {$wpdb->prefix}wc_orders AS wc_orders
					WHERE wc_orders.id LIKE %s
					LIMIT 10",
					$term . '%'
				)
			);
			if ( $query_orders ) {
				foreach ( $query_orders as $item ) {
					$order = wc_get_order( $item->id );
					if ( is_a( $order, 'WC_Order' ) ) {
						$found_orders[ $order->get_id() ] = $order->get_order_number() . ' &ndash; ' . date_i18n( wc_appointments_date_format(), strtotime( $order->get_date_created() ) );
					}
				}
			}
		} else {
			$query_orders = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT ID, post_title FROM {$wpdb->posts} AS posts
					WHERE posts.post_type = 'shop_order'
					AND posts.ID LIKE %s
					LIMIT 10",
					$term . '%'
				)
			);
			if ( $query_orders ) {
				foreach ( $query_orders as $item ) {
					$order = wc_get_order( $item->ID );
					if ( is_a( $order, 'WC_Order' ) ) {
						$found_orders[ $order->get_id() ] = $order->get_order_number() . ' &ndash; ' . date_i18n( wc_appointments_date_format(), strtotime( $order->get_date_created() ) );
					}
				}
			}
		}

		wp_send_json( $found_orders );
	}

}

$GLOBALS['wc_appointments_admin_ajax'] = new WC_Appointments_Admin_Ajax();
