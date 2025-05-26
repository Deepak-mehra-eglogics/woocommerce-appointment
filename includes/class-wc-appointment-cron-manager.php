<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Cron job handler.
 */
class WC_Appointment_Cron_Manager {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'woocommerce_admin_new_appointment_notification', [ $this, 'send_appointment_notification_to_admin' ] );
		add_action( 'wc-appointment-confirmed', [ $this, 'send_appointment_confirmation' ] );
		add_action( 'wc-appointment-reminder', [ $this, 'send_appointment_reminder' ] );
		add_action( 'wc-appointment-complete', [ $this, 'maybe_mark_appointment_complete' ] );
		add_action( 'wc-appointment-follow-up', [ $this, 'send_appointment_follow_up' ] );
		add_action( 'wc-appointment-remove-inactive-cart', [ $this, 'remove_inactive_appointment_from_cart' ] );
		add_action( 'woocommerce_cleanup_sessions', [ $this, 'cleanup_cart_data' ] );
	}

	/**
	 * Send appointment to admin and staff
	 */
	public function send_appointment_notification_to_admin( $appointment_id ) {
		$appointment = get_wc_appointment( $appointment_id );

		// Don't procede if id is not of a valid appointment.
		if ( ! is_a( $appointment, 'WC_Appointment' ) ) {
			return;
		}

		$mailer       = WC()->mailer();
		$notification = $mailer->emails['WC_Email_Admin_New_Appointment'];
		$notification->trigger( $appointment_id );
	}

	/**
	 * Send appointment to admin and staff
	 */
	public function send_appointment_confirmation( $appointment_id ) {
		$appointment = get_wc_appointment( $appointment_id );

		// Don't procede if id is not of a valid appointment.
		if ( ! is_a( $appointment, 'WC_Appointment' ) ) {
			return;
		}

		$mailer       = WC()->mailer();
		$notification = $mailer->emails['WC_Email_Appointment_Confirmed'];
		$notification->trigger( $appointment_id );
	}

	/**
	 * Send appointment reminder email
	 */
	public function send_appointment_reminder( $appointment_id ) {
		$appointment = get_wc_appointment( $appointment_id );

		// Don't procede if id is not of a valid appointment.
		if ( ! is_a( $appointment, 'WC_Appointment' ) || ! $appointment->is_active() ) {
			return;
		}

		$mailer       = WC()->mailer();
		$notification = $mailer->emails['WC_Email_Appointment_Reminder'];
		$notification->trigger( $appointment_id );
	}

	/**
	 * Change the appointment status if it wasn't previously cancelled
	 */
	public function maybe_mark_appointment_complete( $appointment_id ) {
		$appointment = get_wc_appointment( $appointment_id );

		// Don't procede if id is not of a valid appointment.
		if ( ! is_a( $appointment, 'WC_Appointment' ) ) {
			return;
		}

		if ( 'cancelled' === get_post_status( $appointment_id ) ) {
			$appointment->schedule_events();
		} else {
			$this->mark_appointment_complete( $appointment );
		}
	}

	/**
	 * Send appointment follow-up email
	 */
	public function send_appointment_follow_up( $appointment_id ) {
		$appointment = get_wc_appointment( $appointment_id );

		// Don't procede if id is not of a valid appointment.
		if ( ! is_a( $appointment, 'WC_Appointment' ) || ! $appointment->is_active() ) {
			return;
		}

		$mailer       = WC()->mailer();
		$notification = $mailer->emails['WC_Email_Appointment_Follow_Up'];
		$notification->trigger( $appointment_id );
	}

	/**
	 * Change the appointment status to complete
	 */
	public function mark_appointment_complete( $appointment ) {
		$appointment->update_status( 'complete' );
		$appointment->update_customer_status( 'arrived' );
	}

	/**
	 * Remove inactive appointment
	 */
	public function remove_inactive_appointment_from_cart( $appointment_id ) {
		$appointment = $appointment_id ? get_wc_appointment( $appointment_id ) : false;
		if ( $appointment_id && $appointment && $appointment->has_status( [ 'in-cart', 'was-in-cart' ] ) ) {
			wp_delete_post( $appointment_id );
			// Scheduled hook deletes itself on execution, but do it anyways if fired manually.
			as_unschedule_action( 'wc-appointment-remove-inactive-cart', [ $appointment_id ], 'wca' );
			// Delete transient of this appointable product to free up the slots.
			if ( $appointment ) {
				WC_Appointments_Cache::delete_appointment_slots_transient( $appointment->get_product_id() );
			}
		}
	}

	/**
	 * Cleans up old in-cart data
	 *
	 * Cron callback twice per day.
	 *
	 * @since 3.7.0
	 */
	public function cleanup_cart_data() {
		// Make sure active in-cart appointment are not removed.
		$hold_stock_minutes = (int) get_option( 'woocommerce_hold_stock_minutes', 60 );
		$minutes            = apply_filters( 'woocommerce_appointments_remove_inactive_cart_time', $hold_stock_minutes );
		$timestamp          = current_time( 'timestamp' ) - MINUTE_IN_SECONDS * (int) $minutes;

		$appointment_ids = WC_Appointment_Data_Store::get_appointment_ids_by(
			[
				'status'           => [ 'in-cart', 'was-in-cart' ],
				'post_date_before' => $timestamp,
				'limit'            => 100,
			]
		);

		if ( $appointment_ids ) {
			foreach ( $appointment_ids as $appointment_id ) {
				wp_trash_post( $appointment_id );
			}
		}
	}
}

$GLOBALS['wc_appointment_cron_manager'] = new WC_Appointment_Cron_Manager();
