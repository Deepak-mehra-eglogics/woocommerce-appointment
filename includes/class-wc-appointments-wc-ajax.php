<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Appointments WC ajax callbacks.
 */
class WC_Appointments_WC_Ajax {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wc_ajax_wc_appointments_find_scheduled_day_slots', array( $this, 'find_scheduled_day_slots' ) );
		add_action( 'wc_ajax_wc_appointments_set_timezone_cookie', array( $this, 'set_timezone_cookie' ) );
		add_action( 'wc_ajax_wc_appointments_add_appointment_to_cart', array( $this, 'add_appointment_to_cart' ) );
	}

	/**
	 * This endpoint is supposed to replace the back-end logic in appointment-form.
	 */
	public function find_scheduled_day_slots() {
		// Enable, when you figure out why it results in 403 error sometimes.
		#check_ajax_referer( 'find-scheduled-day-slots', 'security' );

		// Filter posted data.
		$posted = apply_filters( 'wc_appointments_before_find_scheduled_day_slots', $_POST );

		#error_log( var_export( $posted, true ) );

		// Get product ID-
		$product_id = absint( $posted['product_id'] );

		if ( empty( $product_id ) || '' === $product_id ) {
			wp_send_json_error( 'Missing product ID' );
			exit;
		}

		try {

			$args                         = [];
			$product                      = get_wc_product_appointment( $product_id );
			$args['availability_rules']   = $product->get_availability_rules();
			$args['default_availability'] = $product->get_default_availability();
			$args['has_staff']            = $product->has_staff();
			$args['has_staff_ids']        = $product->get_staff_ids();
			$args['set_staff_id']         = isset( $posted['set_staff_id'] ) && $posted['set_staff_id'] ? absint( $posted['set_staff_id'] ) : 0;
			$args['appointment_duration'] = in_array( $product->get_duration_unit(), [ 'minute', 'hour' ] ) ? 1 : $product->get_duration();
			$args['staff_assignment']     = ! $product->has_staff() ? 'customer' : $product->get_staff_assignment();
			$args['min_date']             = isset( $posted['min_date'] ) ? strtotime( $posted['min_date'] ) : $product->get_min_date_a();
			$args['max_date']             = isset( $posted['max_date'] ) ? strtotime( $posted['max_date'] ) : $product->get_max_date_a();

			$min_date  = ! isset( $posted['min_date'] ) ? strtotime( "+{$args['min_date']['value']} {$args['min_date']['unit']}", current_time( 'timestamp' ) ) : $args['min_date'];
			$max_date  = ! isset( $posted['max_date'] ) ? strtotime( "+{$args['max_date']['value']} {$args['max_date']['unit']}", current_time( 'timestamp' ) ) : $args['max_date'];
			$staff_ids = $args['set_staff_id'] && ! is_array( $args['set_staff_id'] ) ? array( $args['set_staff_id'] ) : [];

			$scheduled = WC_Appointments_Controller::find_scheduled_day_slots( $product, $min_date, $max_date, 'Y-n-j', 0, $staff_ids );

			#error_log( var_export( current_time( 'Y-m-d H:i:s.u' ), true ) );

			$args['partially_scheduled_days'] = $scheduled['partially_scheduled_days'];
			$args['remaining_scheduled_days'] = $scheduled['remaining_scheduled_days'];
			$args['fully_scheduled_days']     = $scheduled['fully_scheduled_days'];
			$args['unavailable_days']         = $scheduled['unavailable_days'];
			$args['restricted_days']          = $product->has_restricted_days() ? $product->get_restricted_days() : false;

			$padding_days = [];
			if ( ! in_array( $product->get_duration_unit(), [ 'minute', 'hour' ] ) ) {
				$padding_days = WC_Appointments_Controller::get_padding_day_slots_for_scheduled_days( $product, $args['fully_scheduled_days'] );
			}

			$args['padding_days'] = $padding_days;

			// Filter all arguments.
			$args = apply_filters( 'wc_appointments_find_scheduled_day_slots', $args, $product );

			#print '<pre>'; print_r( $args ); print '</pre>';
			#error_log( var_export( $args, true ) );
			#global $wpdb;
			#error_log( var_export( $wpdb->queries, true ) );
			#$logger = wc_get_logger();
			#$logger->alert( wc_print_r( $args, true ) );

			wp_send_json( $args );

		} catch ( Exception $e ) {

			wp_die();

		}
	}

	/**
	 * This endpoint saves timezone in a cookie.
	 */
	public function set_timezone_cookie() {
		$timezone = $_POST['timezone'];

		if ( empty( $timezone ) ) {
			wp_send_json_error( __( 'Missing timezone', 'woocommerce-appointments' ) );
			exit;
		}

		try {

			setcookie( 'appointments_time_zone', $timezone, time() + ( DAY_IN_SECONDS * 30 ), "/" );

			#print '<pre>' . var_export( $timezone, true ) . '</pre>';

			wp_send_json( $timezone );

		} catch ( Exception $e ) {

			wp_die();

		}
	}

	/**
	 * Adds the appointment to the cart using WC().
	 *
	 * @since 4.5.0
	 */
	public function add_appointment_to_cart() {
		check_ajax_referer( 'add-appointment-to-cart', 'security' );

		$date = isset( $_GET['date'] ) ? $_GET['date'] : '';

		if ( empty( $_GET['product_id'] ) || empty( $date ) ) {
			wp_die();
		}

		$product = wc_get_product( absint( $_GET['product_id'] ) );

		if ( ! is_wc_appointment_product( $product ) ) {
			wp_die();
		}

		$link = apply_filters( 'woocommerce_loop_product_link', $product->get_permalink(), $product );

		try {
			/*
			 * At this point we need to check if appointment can be
			 * made without any further user selection such as
			 * staff or product add-ons...etc. If so we cannot
			 * add appointment to cart via AJAX. Redirect them.
			 */
			if ( $product->has_staff() && $product->is_staff_assignment_type( 'customer' ) ) {
				wp_send_json(
					array(
						'scheduled' => false,
						'link'      => esc_url( $link ),
					)
				);
			}

			if ( 'hour' === $product->get_duration_unit() || 'minute' === $product->get_duration_unit() ) {
				$_POST['wc_appointments_field_start_date_time'] = $date;
			} else {
				$date_time                                       = new DateTime( $date );
				$_POST['wc_appointments_field_start_date_month'] = $date_time->format( 'm' );
				$_POST['wc_appointments_field_start_date_day']   = $date_time->format( 'd' );
				$_POST['wc_appointments_field_start_date_year']  = $date_time->format( 'Y' );
			}

			$added = WC()->cart->add_to_cart(
				$product->get_id()
			);

			wp_send_json(
				array(
					'scheduled' => false !== $added,
					'link'      => esc_url( $link ),
				)
			);
		} catch ( Exception $e ) {
			wp_send_json(
				array(
					'scheduled' => false,
					'link'      => esc_url( $link ),
				)
			);
		}
	}
}

new WC_Appointments_WC_Ajax();
