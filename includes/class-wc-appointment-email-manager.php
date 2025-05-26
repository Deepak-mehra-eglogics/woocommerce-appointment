<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Handles email sending
 */
class WC_Appointment_Email_Manager {

	/**
	 * Constructor sets up actions
	 */
	public function __construct() {
		$this->appointments_email_preview();

		add_filter( 'woocommerce_email_classes', [ $this, 'init_emails' ] );
		add_filter( 'woocommerce_email_attachments', [ $this, 'attach_ics_file' ], 10, 3 );
		add_filter( 'woocommerce_template_directory', [ $this, 'template_directory' ], 10, 2 );

		$this->appointments_email_actions();
	}

	/**
	 * Appointments email preview.
	 *
	 * @since   4.23.1
	 * @version 4.23.1
	 */
	public function appointments_email_preview() {
		include 'emails/class-wc-appointments-email-preview.php';
	}

	/**
	 * Include our mail templates
	 *
	 * @param  array $emails
	 * @return array
	 */
	public function init_emails( $emails ) {
		if ( ! isset( $emails['WC_Email_Admin_Appointment_Cancelled'] ) ) {
			$emails['WC_Email_Admin_Appointment_Cancelled'] = include 'emails/class-wc-email-admin-appointment-cancelled.php';
		}

		if ( ! isset( $emails['WC_Email_Admin_Appointment_Rescheduled'] ) ) {
			$emails['WC_Email_Admin_Appointment_Rescheduled'] = include 'emails/class-wc-email-admin-appointment-rescheduled.php';
		}

		if ( ! isset( $emails['WC_Email_Admin_New_Appointment'] ) ) {
			$emails['WC_Email_Admin_New_Appointment'] = include 'emails/class-wc-email-admin-new-appointment.php';
		}

		if ( ! isset( $emails['WC_Email_Appointment_Cancelled'] ) ) {
			$emails['WC_Email_Appointment_Cancelled'] = include 'emails/class-wc-email-appointment-cancelled.php';
		}

		if ( ! isset( $emails['WC_Email_Appointment_Confirmed'] ) ) {
			$emails['WC_Email_Appointment_Confirmed'] = include 'emails/class-wc-email-appointment-confirmed.php';
		}

		if ( ! isset( $emails['WC_Email_Appointment_Reminder'] ) ) {
			$emails['WC_Email_Appointment_Reminder'] = include 'emails/class-wc-email-appointment-reminder.php';
		}

		if ( ! isset( $emails['WC_Email_Appointment_Follow_Up'] ) ) {
			$emails['WC_Email_Appointment_Follow_Up'] = include 'emails/class-wc-email-appointment-follow-up.php';
		}

		return $emails;
	}

	/**
	 * Attach the .ics files in the emails.
	 *
	 * @param  array  $attachments
	 * @param  string $email_id
	 * @param  mixed  $object
	 *
	 * @return array
	 */
	public function attach_ics_file( $attachments, $email_id, $object ) {
		$available = apply_filters(
			'woocommerce_appointments_emails_ics',
			[
				'admin_new_appointment',
				'admin_appointment_rescheduled',
				'appointment_confirmed',
				'appointment_reminder',
				'customer_processing_order',
				'customer_completed_order',
			]
		);

		#error_log( var_export( $email_id, true ) );
		#error_log( var_export( $object, true ) );

		if ( in_array( $email_id, $available ) ) {
			$generate = new WC_Appointments_ICS_Exporter();

			// Email object is for WC_Order.
			if ( is_a( $object, 'WC_Order' ) ) {
				$appointment_ids = WC_Appointment_Data_Store::get_appointment_ids_from_order_id( $object->get_id() );

				// Order contains appointments.
				if ( $appointment_ids ) {
					$appointment_objects = array_map( 'get_wc_appointment', $appointment_ids );
					$attachments[]       = $generate->get_ics( $appointment_objects );
				}
			// Email object is for single WC_Appointment.
			} elseif ( is_a( $object, 'WC_Appointment' ) ) {
				$attachments[] = $generate->get_appointment_ics( $object );
			}
		}

		return $attachments;
	}

	/**
	 * Custom template directory.
	 *
	 * @param  string $directory
	 * @param  string $template
	 *
	 * @return string
	 */
	public function template_directory( $directory, $template ) {
		if ( false !== strpos( $template, '-appointment' ) ) {
			return 'woocommerce-appointments';
		}

		return $directory;
	}

	/**
	 * Appointments email actions for transactional emails.
	 *
	 * @since   3.2.4
	 * @version 3.2.4
	 */
	public function appointments_email_actions() {
		// Email Actions
		$email_actions = apply_filters(
			'woocommerce_appointments_email_actions',
			[
				// New & Pending Confirmation
				'woocommerce_appointment_in-cart_to_paid',
				'woocommerce_appointment_in-cart_to_pending-confirmation',
				'woocommerce_appointment_unpaid_to_paid',
				'woocommerce_appointment_unpaid_to_pending-confirmation',
				'woocommerce_appointment_confirmed_to_paid',

				// Confirmed
				'woocommerce_appointment_confirmed',
				'woocommerce_admin_confirmed',

				// Cancelled
				'woocommerce_appointment_pending-confirmation_to_cancelled',
				'woocommerce_appointment_confirmed_to_cancelled',
				'woocommerce_appointment_paid_to_cancelled',
				'woocommerce_appointment_unpaid_to_cancelled',
			]
		);

		foreach ( $email_actions as $action ) {
			add_action( $action, [ 'WC_Emails', 'send_transactional_email' ], 10, 10 );
		}
	}
}

return new WC_Appointment_Email_Manager();
