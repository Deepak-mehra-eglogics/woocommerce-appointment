<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Kadence WooCommerce Email Designer integration class.
 * https://wordpress.org/plugins/kadence-woocommerce-email-designer/
 *
 * Last compatibility check: 1.3.3
 */
class WC_Appointments_Integration_Kadence_Woomail {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_filter( 'kadence_woomail_preview_email_object', array( $this, 'appointments_preview_email_object' ) );
		#add_filter( 'kadence_woomail_customized_email_types', array( $this, 'appointments_customized_email_types' ) );
		add_filter( 'kadence_woomail_email_types', array( $this, 'appointments_email_types' ) );
		add_filter( 'kadence_woomail_email_type_class_name_array', array( $this, 'appointments_email_type_class_name_array' ) );
		add_filter( 'kadence_woomail_email_type_order_status_array', array( $this, 'appointments_email_type_order_status_array' ) );
		add_filter( 'kadence_woomail_email_settings_default_values', array( $this, 'appointments_email_settings_default_values' ) );
	}

	/**
	 * Add appointment object for preview.
	 *
 	 * @since 4.6.0
 	 * @return array
 	 */
	public function appointments_preview_email_object( $email ) {
		#error_log( var_export( Kadence_Woomail_Customizer::opt( 'preview_order_id' ), true ) );
		$order_id           = Kadence_Woomail_Customizer::opt( 'preview_order_id' );
		$appointment_emails = array(
			'admin_appointment_cancelled',
			'admin_appointment_rescheduled',
			'admin_new_appointment',
			'appointment_cancelled',
			'appointment_confirmed',
			'appointment_reminder',
			'appointment_follow_up',
		);

		if ( in_array( $email->id, $appointment_emails ) ) {
			if ( $order_id && 'mockup' !== $order_id ) {
				$email->object = wc_appointments_maybe_appointment_object( $order_id );
				if ( $email->object ) {
					$user_id           = $email->object->get_order()->get_customer_id();
					$customer_username = '';
					if ( $user_id && is_callable( array( 'Kadence_Woomail_Designer', 'get_username_from_id' ) ) ) {
						$customer_username = Kadence_Woomail_Designer::get_username_from_id( $user_id );
					}
					$email->find['appointment-number']     = '{appointment_number}';
					$email->find['appointment-start']      = '{appointment_start}';
					$email->find['appointment-end']        = '{appointment_end}';
					$email->find['appointment-title']      = '{appointment_title}';
					$email->find['order-date']             = '{order_date}';
					$email->find['order-number']           = '{order_number}';
					$email->find['customer-first-name']    = '{customer_first_name}';
					$email->find['customer-last-name']     = '{customer_last_name}';
					$email->find['customer-full-name']     = '{customer_full_name}';
					$email->find['customer-username']      = '{customer_username}';
					$email->find['customer-email']         = '{customer_email}';
					$email->replace['appointment-number']  = $email->object->get_id();
					$email->replace['appointment-start']   = $email->object->get_start_date();
					$email->replace['appointment-end']     = $email->object->get_end_date();
					$email->replace['appointment-title']   = $email->object->get_product_name();
					$email->replace['order-date']          = wc_format_datetime( $email->object->get_order()->get_date_created() );
					$email->replace['order-number']        = $email->object->get_order()->get_order_number();
					$email->replace['customer-first-name'] = $email->object->get_order()->get_billing_first_name();
					$email->replace['customer-last-name']  = $email->object->get_order()->get_billing_last_name();
					$email->replace['customer-full-name']  = $email->object->get_order()->get_formatted_billing_full_name();
					$email->replace['customer-username']   = $customer_username;
					$email->replace['customer-email']      = $email->object->get_order()->get_billing_email();
				}
			} else {
				$email->object = get_wc_appointment( 0 );
			}
		}

		#error_log( var_export( $email, true ) );

		return $email;
	}

	/**
	 * Add customized email types.
	 *
 	 * @since 4.8.0
 	 * @return array
 	 */
	public function appointments_customized_email_types( $types ) {
		// Appointment emails.
		$appointment_emails = array(
			'admin_appointment_cancelled'   => __( 'Admin Appointment Cancelled', 'woocommerce-appointments' ),
			'admin_appointment_rescheduled' => __( 'Admin Appointment Rescheduled', 'woocommerce-appointments' ),
			'admin_new_appointment'         => __( 'Admin New Appointment', 'woocommerce-appointments' ),
			'appointment_cancelled'         => __( 'Appointment Cancelled', 'woocommerce-appointments' ),
			'appointment_confirmed'         => __( 'Appointment Confirmed', 'woocommerce-appointments' ),
			'appointment_reminder'          => __( 'Appointment Reminder', 'woocommerce-appointments' ),
			'appointment_follow_up'         => __( 'Appointment Follow-up', 'woocommerce-appointments' ),
		);

		// Merge the types.
		$types = array_merge( $types, $appointment_emails );

		return $types;
	}

	/**
	 * Add email types for editing.
	 *
 	 * @since 4.6.0
 	 * @return array
 	 */
	public function appointments_email_types( $types ) {
		// Appointment emails.
		$appointment_emails = array(
			'admin_appointment_cancelled'   => __( 'Admin Appointment Cancelled', 'woocommerce-appointments' ),
			'admin_appointment_rescheduled' => __( 'Admin Appointment Rescheduled', 'woocommerce-appointments' ),
			'admin_new_appointment'         => __( 'Admin New Appointment', 'woocommerce-appointments' ),
			'appointment_cancelled'         => __( 'Appointment Cancelled', 'woocommerce-appointments' ),
			'appointment_confirmed'         => __( 'Appointment Confirmed', 'woocommerce-appointments' ),
			'appointment_reminder'          => __( 'Appointment Reminder', 'woocommerce-appointments' ),
			'appointment_follow_up'         => __( 'Appointment Follow-up', 'woocommerce-appointments' ),
		);

		// Merge the types.
		$types = array_merge( $types, $appointment_emails );

		#error_log( var_export( $types, true ) );

		return $types;
	}

	/**
	 * Match email types with their class.
	 *
 	 * @since 4.6.0
 	 * @return array
 	 */
	public function appointments_email_type_class_name_array( $types ) {
		// Appointment emails.
		$appointment_emails = array(
			'admin_appointment_cancelled'   => 'WC_Email_Admin_Appointment_Cancelled',
			'admin_appointment_rescheduled' => 'WC_Email_Admin_Appointment_Rescheduled',
			'admin_new_appointment'         => 'WC_Email_Admin_New_Appointment',
			'appointment_cancelled'         => 'WC_Email_Appointment_Cancelled',
			'appointment_confirmed'         => 'WC_Email_Appointment_Confirmed',
			'appointment_reminder'          => 'WC_Email_Appointment_Reminder',
			'appointment_follow_up'         => 'WC_Email_Appointment_Follow_Up',
		);

		// Merge the types.
		$types = array_merge( $types, $appointment_emails );

		return $types;
	}

	/**
	 * Match email types with their order status.
	 *
 	 * @since 4.6.0
 	 * @return array
 	 */
	public function appointments_email_type_order_status_array( $types ) {
		// Appointment emails.
		$appointment_emails = array(
			'admin_appointment_cancelled'   => null,
			'admin_appointment_rescheduled' => null,
			'admin_new_appointment'         => null,
			'appointment_cancelled'         => null,
			'appointment_confirmed'         => null,
			'appointment_reminder'          => null,
			'appointment_follow_up'         => null,
		);

		// Merge the types.
		$types = array_merge( $types, $appointment_emails );

		return $types;
	}

	/**
	 * Default values for email types.
	 *
 	 * @since 4.6.0
 	 * @return array
 	 */
	public function appointments_email_settings_default_values( $default_values ) {
		// Appointment emails.
		$appointment_default_values = array(
			'admin_appointment_cancelled_heading'      => __( 'Appointment Cancelled: #{appointment_number}', 'woocommerce-appointments' ),
			'admin_appointment_cancelled_subject'      => __( '[{site_title}]: Appointment for {product_title} has been cancelled', 'woocommerce-appointments' ),
			'admin_appointment_cancelled_body'         => false,
			'admin_new_appointment_heading'            => __( 'New appointment: #{appointment_number}', 'woocommerce-appointments' ),
			'admin_new_appointment_subject'            => __( '[{site_title}]: New appointment for {product_title}', 'woocommerce-appointments' ),
			'admin_new_appointment_body'               => false,
			'appointment_cancelled_heading'            => __( 'Appointment Cancelled: #{appointment_number}', 'woocommerce-appointments' ),
			'appointment_cancelled_subject'            => __( '[{site_title}]: Appointment for {product_title} has been cancelled', 'woocommerce-appointments' ),
			'appointment_cancelled_additional_content' => __( 'Please contact us if you have any questions or concerns.', 'woocommerce-appointments' ),
			'appointment_cancelled_body'               => false,
			'appointment_confirmed_heading'            => __( 'Appointment Confirmed: #{appointment_number}', 'woocommerce-appointments' ),
			'appointment_confirmed_subject'            => __( '[{site_title}]: Appointment for {product_title} has been confirmed', 'woocommerce-appointments' ),
			'appointment_confirmed_additional_content' => __( 'Thanks for scheduling with us.', 'woocommerce-appointments' ),
			'appointment_confirmed_body'               => false,
			'appointment_reminder_heading'             => __( 'Appointment Reminder: #{appointment_number}', 'woocommerce-appointments' ),
			'appointment_reminder_subject'             => __( '[{site_title}]: Reminder of your appointment for {product_title}', 'woocommerce-appointments' ),
			'appointment_reminder_additional_content'  => __( 'Thanks for scheduling with us.', 'woocommerce-appointments' ),
			'appointment_reminder_body'                => false,
			'appointment_follow_up_heading'            => __( 'Thanks for your appointment!', 'woocommerce-appointments' ),
			'appointment_follow_up_subject'            => __( '[{site_title}]: Appointment follow-up for {product_title}', 'woocommerce-appointments' ),
			'appointment_follow_up_additional_content' => __( 'Thanks for scheduling with us.', 'woocommerce-appointments' ),
			'appointment_follow_up_body'               => false,
		);

		// Merge the types.
		$default_values = array_merge( $default_values, $appointment_default_values );

		return $default_values;
	}

}

new WC_Appointments_Integration_Kadence_Woomail();
