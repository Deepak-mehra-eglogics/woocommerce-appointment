<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Handle frontend forms
 */
class WC_Appointment_Form_Handler {

	/**
	 * Hook in methods
	 */
	public static function init() {
		add_action( 'init', [ __CLASS__, 'cancel_appointment' ], 20 ); #init ok
		add_action( 'init', [ __CLASS__, 'reschedule_appointment' ], 20 ); #init ok
	}

	/**
	 * Cancel an appointment.
	 */
	public static function cancel_appointment() {
		if ( isset( $_GET['cancel_appointment'] ) && isset( $_GET['appointment_id'] ) ) {

			$appointment_id         = absint( $_GET['appointment_id'] );
			$appointment            = get_wc_appointment( $appointment_id );
			$appointment_can_cancel = $appointment->has_status( get_wc_appointment_statuses( 'cancel' ) );
			$is_wc_appointment      = is_a( $appointment, 'WC_Appointment' ) ? true : false;

			// Filter the redirect.
			$redirect = apply_filters(
				'woocommerce_appointments_cancel_appointment_redirect',
				esc_url_raw( wp_unslash( $_GET['redirect'] ) ),
				$appointment
			); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated

			if ( $appointment->has_status( 'cancelled' ) ) {
				// Message: Already cancelled - take no action.
				wc_add_notice( __( 'Your appointment has already been cancelled.', 'woocommerce-appointments' ), 'notice' );

			} elseif ( $is_wc_appointment && $appointment_can_cancel && $appointment->get_id() == $appointment_id && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'woocommerce-appointments-cancel_appointment' ) ) {
				// Cancel the appointment
				$appointment->update_status( 'cancelled' );
				WC_Cache_Helper::get_transient_version( 'appointments', true );

				// Message.
				wc_add_notice( apply_filters( 'woocommerce_appointment_cancelled_notice', __( 'Your appointment has been cancelled.', 'woocommerce-appointments' ) ), apply_filters( 'woocommerce_appointment_cancelled_notice_type', 'notice' ) );

				do_action( 'woocommerce_appointments_cancelled_appointment', $appointment->get_id() );
			} elseif ( ! $appointment_can_cancel ) {
				wc_add_notice( __( 'Your appointment can no longer be cancelled. Please contact us if you need assistance.', 'woocommerce-appointments' ), 'error' );
			} else {
				wc_add_notice( __( 'Invalid appointment.', 'woocommerce-appointments' ), 'error' );
			}

			if ( $redirect ) {
				wp_safe_redirect( $redirect );
				exit;
			}
		}
	}

	/**
	 * Reschedule an appointment.
	 *
	 * @since 4.9.8
	 */
	public static function reschedule_appointment() {
		#error_log( var_export( $_POST, true ) );
		if ( isset( $_POST['reschedule-appointment'] ) && isset( $_POST['appointment-id'] ) ) {

			$appointment_id    = intval( $_POST['appointment-id'] );
			$appointment       = get_wc_appointment( $appointment_id );
			$is_wc_appointment = is_a( $appointment, 'WC_Appointment' ) ? true : false;

			// Filter the redirect.
			$redirect = apply_filters(
				'woocommerce_appointments_reschedule_appointment_redirect',
				esc_url( wc_get_endpoint_url( 'appointments', '', wc_get_page_permalink( 'myaccount' ) ) ),
				$appointment
			);

			if ( $is_wc_appointment && $appointment->has_status( 'cancelled' ) ) {
				// Message: Already cancelled - take no action.
				wc_add_notice( __( 'Your appointment has already been cancelled.', 'woocommerce-appointments' ), 'notice' );

			} elseif ( $is_wc_appointment && $appointment->get_id() == $appointment_id && isset( $_POST['_wpnonce'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'woocommerce-appointments-reschedule_appointment' ) ) {
				$order           = $appointment->get_order();
				$prev_start_date = $appointment->get_start_date();
				$prev_end_date   = $appointment->get_end_date();

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

				#error_log( var_export( $appointment_duration, true ) );

				// Make sure only appointment staff is set to product.
				if ( $appointment->get_staff_ids() ) {
					#$product->set_staff_assignment( 'automatic' );
					$product->set_staff_ids( $appointment->get_staff_ids() );
					$product->set_staff_nopref( false );
				}

				#print '<pre>'; print_r( $product->get_duration() ); print '</pre>';

				// Appointant data.
				$appointment_data = wc_appointments_get_posted_data( $_POST, $product );
				#$cost             = WC_Appointments_Cost_Calculation::calculate_appointment_cost( $_POST, $product );
				#$appointment_cost = $cost && ! is_wp_error( $cost ) ? number_format( $cost, 2, '.', '' ) : 0;

				#error_log( var_export( $appointment_data, true ) );

				do_action( 'woocommerce_appointments_before_rescheduled_appointment', $appointment, $appointment_data );

				// Set start and end date.
				$appointment->set_start( $appointment_data['_start_date'] );
				$appointment->set_end( $appointment_data['_end_date'] );
				$appointment->save();

				WC_Cache_Helper::get_transient_version( 'appointments', true );

				$new_start_date = $appointment->get_start_date();
				$notice         = apply_filters(
					'woocommerce_appointment_rescheduled_notice',
					sprintf(
						/* translators: %1$d: appointment id, %2$s: old appointment time, %3$s: new appointment time */
						__( 'Appointment #%1$d has been rescheduled from %2$s to %3$s.', 'woocommerce-appointments' ),
						$appointment->get_id(),
						$prev_start_date,
						$new_start_date
					)
				);

				// Add the note
				if ( $order ) {
					$order->add_order_note( $notice, true, true );
				}

				// Message.
				wc_add_notice(
					$notice,
					apply_filters(
						'woocommerce_appointment_rescheduled_notice_type',
						'notice'
					)
				);

				do_action( 'woocommerce_appointments_rescheduled_appointment', $appointment->get_id(), $prev_start_date, $prev_end_date );
			} else {
				wc_add_notice( __( 'Invalid appointment.', 'woocommerce-appointments' ), 'error' );
			}

			if ( $redirect ) {
				wp_safe_redirect( $redirect );
				exit;
			}
		}
	}
}

WC_Appointment_Form_Handler::init();
