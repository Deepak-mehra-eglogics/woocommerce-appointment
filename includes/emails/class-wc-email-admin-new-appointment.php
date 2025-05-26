<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * New Appointment Email
 *
 * An email sent to the admin when a new appointment is created.
 *
 * @class       WC_Email_Admin_New_Appointment
 * @extends     WC_Email
 */
class WC_Email_Admin_New_Appointment extends WC_Email {
	/**
	 * Subject for pending confirmation emails.
	 *
	 * @var string
	 */
	public $subject_confirmation = '';

	/**
	 * Heading for pending confirmation emails.
	 *
	 * @var string
	 */
	public $heading_confirmation = '';

	/**
	 * Constructor
	 */
	public function __construct() {

		$this->id             = 'admin_new_appointment';
		$this->title          = __( 'Admin New Appointment', 'woocommerce-appointments' );
		$this->description    = __( 'New appointment emails are sent to the admin when a new appointment is created and paid. This email is also received when a Pending confirmation appointment is created.', 'woocommerce-appointments' );
		$this->template_html  = 'emails/admin-new-appointment.php';
		$this->template_plain = 'emails/plain/admin-new-appointment.php';
		$this->placeholders   = [
			'{site_title}'         => $this->get_blogname(),
			'{product_title}'      => '',
			'{appointment_number}' => '',
			'{appointment_start}'  => '',
			'{appointment_end}'    => '',
			'{order_date}'         => '',
			'{order_number}'       => '',
		];

		// Triggers for this email
		add_filter( 'woocommerce_email_recipient_admin_new_appointment', [ $this, 'email_recipients' ], 10, 2 );
		add_action( 'woocommerce_appointment_in-cart_to_paid_notification', [ $this, 'queue_notification' ] );
		add_action( 'woocommerce_appointment_in-cart_to_pending-confirmation_notification', [ $this, 'queue_notification' ] );
		add_action( 'woocommerce_appointment_unpaid_to_paid_notification', [ $this, 'queue_notification' ] );
		add_action( 'woocommerce_appointment_unpaid_to_pending-confirmation_notification', [ $this, 'queue_notification' ] );
		add_action( 'woocommerce_appointment_confirmed_to_paid_notification', [ $this, 'queue_notification' ] );

		// Call parent constructor
		parent::__construct();

		// Other settings
		$this->template_base = WC_APPOINTMENTS_TEMPLATE_PATH;
		$this->recipient     = $this->get_option( 'recipient', get_option( 'admin_email' ) );
	}

	/**
	 * When appointments are created, orders and other parts may not exist yet. e.g. during order creation on checkout.
	 *
	 * This ensures emails are sent last, once all other logic is complete.
	 */
	public function email_recipients( $recipient, $appointment ) {
		if ( ! is_a( $appointment, 'WC_Appointment' ) ) {
			return $recipient;
		}

		$staff = $appointment->get_staff_members();

		if ( $appointment->has_staff() && ( $staff ) ) {
			$staff_emails = [];
			foreach ( (array) $staff as $staff_member ) {
				$staff_emails[] = $staff_member->user_email;
			}
			$admin_emails = explode( ', ', $recipient ); #add admin emails from options
			$all_emails   = array_unique(
				array_merge(
					$admin_emails,
					$staff_emails
				)
			);
			$recipient    = implode( ', ', $all_emails );
		}

		#error_log( var_export( $recipient, true ) );

		return $recipient;
	}

	/**
	 * When appointments are created, orders and other parts may not exist yet. e.g. during order creation on checkout.
	 *
	 * This ensures emails are sent last, once all other logic is complete.
	 */
	public function queue_notification( $appointment_id ) {
		if ( ! as_next_scheduled_action( 'woocommerce_admin_new_appointment_notification', [ $appointment_id ] ) ) {
			as_schedule_single_action( time(), 'woocommerce_admin_new_appointment_notification', [ $appointment_id ], 'wca' );
		}
	}

	/**
	 * Get email subject.
	 *
	 * @since  4.2.9
	 * @return string
	 */
	public function get_default_subject( $confirmation = false ) {
		if ( $confirmation ) {
			return __( '[{site_title}]: Confirm appointment for {product_title}', 'woocommerce-appointments' );
		} else {
			return __( '[{site_title}]: New appointment for {product_title}', 'woocommerce-appointments' );
		}
	}

	/**
	 * Get email heading.
	 *
	 * @since  4.2.9
	 * @return string
	 */
	public function get_default_heading( $confirmation = false ) {
		if ( $confirmation ) {
			return __( 'Confirm appointment: #{appointment_number}', 'woocommerce-appointments' );
		} else {
			return __( 'New appointment: #{appointment_number}', 'woocommerce-appointments' );
		}
	}

	/**
	 * Return the function for this email type.
	 *
	 * @version 3.5.6 Set the subject and then return it.
	 *
	 * @return string
	 */
	public function get_subject() {
		// Check if provided $appointment_id is indeed an $appointment.
		$this->object = wc_appointments_maybe_appointment_object( $this->object );

		if ( $this->object && wc_appointment_order_requires_confirmation( $this->object->get_order() ) && $this->object->has_status( [ 'pending-confirmation' ] ) ) {
			$subject = $this->get_option( 'subject_confirmation', $this->get_default_subject( true ) );
			return apply_filters( 'woocommerce_email_subject_admin_new_appointment_confirmation', $this->format_string( $subject ), $this->object );
		}

		$subject = $this->get_option( 'subject', $this->get_default_subject() );
		return apply_filters( 'woocommerce_email_subject_admin_new_appointment', $this->format_string( $subject ), $this->object );
	}

	/**
	 * get_heading function.
	 *
	 * @return string
	 */
	public function get_heading() {
		// Check if provided $appointment_id is indeed an $appointment.
		$this->object = wc_appointments_maybe_appointment_object( $this->object );

		if ( $this->object && wc_appointment_order_requires_confirmation( $this->object->get_order() ) && $this->object->has_status( [ 'pending-confirmation' ] ) ) {
			$heading = $this->get_option( 'heading_confirmation', $this->get_default_heading( true ) );
			return apply_filters( 'woocommerce_email_heading_admin_new_appointment_donfirmation', $this->format_string( $heading ), $this->object );
		}

		$heading = $this->get_option( 'heading', $this->get_default_heading() );
		return apply_filters( 'woocommerce_email_heading_admin_new_appointment', $this->format_string( $heading ), $this->object );
	}

	/**
	 * trigger function.
	 */
	public function trigger( $appointment_id ) {
		$this->setup_locale();

		if ( $appointment_id ) {
			$appointment = get_wc_appointment( $appointment_id );

			// Check if provided $appointment_id is indeed an $appointment.
			$this->object = wc_appointments_maybe_appointment_object( $appointment );

			if ( ! $this->object ) {
				return;
			}

			if ( $this->object->has_status( 'in-cart' ) ) {
				return;
			}

			$this->placeholders['{appointment_number}'] = $appointment_id;
			$this->placeholders['{appointment_start}']  = $this->object->get_start_date();
			$this->placeholders['{appointment_end}']    = $this->object->get_end_date();

			if ( $this->object->get_product() ) {
				$this->placeholders['{product_title}'] = $this->object->get_product_name();
			}

			if ( $this->object->get_order() ) {
				$order_date = $this->object->get_order()->get_date_created() ? $this->object->get_order()->get_date_created() : '';

				$this->placeholders['{order_date}']          = wc_format_datetime( $order_date );
				$this->placeholders['{order_number}']        = $this->object->get_order()->get_order_number();
				$this->placeholders['{customer_first_name}'] = $this->object->get_order()->get_billing_first_name();
				$this->placeholders['{customer_last_name}']  = $this->object->get_order()->get_billing_last_name();
				$this->placeholders['{customer_full_name}']  = $this->object->get_order()->get_formatted_billing_full_name();
				$this->placeholders['{customer_email}']      = $this->object->get_order()->get_billing_email();
			} else {
				$this->placeholders['{order_date}']          = date_i18n( wc_appointments_date_format(), strtotime( $this->object->appointment_date ) );
				$this->placeholders['{order_number}']        = __( 'N/A', 'woocommerce-appointments' );
				$this->placeholders['{customer_first_name}'] = $this->object->get_customer()->full_name;
				$this->placeholders['{customer_last_name}']  = $this->object->get_customer()->full_name;
				$this->placeholders['{customer_full_name}']  = $this->object->get_customer()->full_name;
				$this->placeholders['{customer_email}']      = $this->object->get_customer()->email;
			}

			if ( ! $this->is_enabled() || ! $this->get_recipient() ) {
				return;
			}

			$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
		}
	}

	/**
	 * get_content_html function.
	 *
	 * @access public
	 * @return string
	 */
	public function get_content_html() {
		ob_start();
		wc_get_template(
			$this->template_html,
			[
				'appointment'   => $this->object,
				'email_heading' => $this->get_heading(),
				'sent_to_admin' => false,
				'plain_text'    => false,
				'email'         => $this,
			],
			'',
			$this->template_base
		);

		return ob_get_clean();
	}

	/**
	 * get_content_plain function.
	 *
	 * @access public
	 * @return string
	 */
	public function get_content_plain() {
		ob_start();
		wc_get_template(
			$this->template_html,
			[
				'appointment'   => $this->object,
				'email_heading' => $this->get_heading(),
				'sent_to_admin' => false,
				'plain_text'    => false,
				'email'         => $this,
			],
			'',
			$this->template_base
		);

		return ob_get_clean();
	}

	/**
	 * Initialise Settings Form Fields
	 *
	 * @access public
	 * @return void
	 */
	public function init_form_fields() {
		$this->form_fields = [
			'enabled'              => [
				'title'   => __( 'Enable/Disable', 'woocommerce-appointments' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable this email notification', 'woocommerce-appointments' ),
				'default' => 'yes',
			],
			'recipient'            => [
				'title'       => __( 'Recipient(s)', 'woocommerce-appointments' ),
				'type'        => 'text',
				/* translators: %s: WP admin email */
				'description' => sprintf( __( 'Enter recipients (comma separated) for this email. Defaults to assigned staff and <code>%s</code>.', 'woocommerce-appointments' ), esc_attr( get_option( 'admin_email' ) ) ),
				'placeholder' => '',
				'default'     => '',
				'desc_tip'    => true,
			],
			'subject'              => [
				'title'       => __( 'Subject', 'woocommerce-appointments' ),
				'type'        => 'text',
				/* translators: %s: list of placeholders */
				'description' => sprintf( __( 'Available placeholders: %s', 'woocommerce-appointments' ), '<code>{site_title}, {product_title}, {appointment_number}, {appointment_start}, {appointment_end}, {order_date}, {order_number}</code>' ),
				'placeholder' => $this->get_default_subject(),
				'default'     => '',
				'desc_tip'    => true,
			],
			'subject_confirmation' => [
				'title'       => __( 'Subject (Pending confirmation)', 'woocommerce-appointments' ),
				'type'        => 'text',
				/* translators: %s: list of placeholders */
				'description' => sprintf( __( 'Available placeholders: %s', 'woocommerce-appointments' ), '<code>{site_title}, {product_title}, {appointment_number}, {appointment_start}, {appointment_end}, {order_date}, {order_number}</code>' ),
				'placeholder' => $this->get_default_subject( true ),
				'default'     => '',
				'desc_tip'    => true,
			],
			'heading'              => [
				'title'       => __( 'Email Heading', 'woocommerce-appointments' ),
				'type'        => 'text',
				/* translators: %s: list of placeholders */
				'description' => sprintf( __( 'Available placeholders: %s', 'woocommerce-appointments' ), '<code>{site_title}, {product_title}, {appointment_number}, {appointment_start}, {appointment_end}, {order_date}, {order_number}</code>' ),
				'placeholder' => $this->get_default_heading(),
				'default'     => '',
				'desc_tip'    => true,
			],
			'heading_confirmation' => [
				'title'       => __( 'Email Heading (Pending confirmation)', 'woocommerce-appointments' ),
				'type'        => 'text',
				/* translators: %s: list of placeholders */
				'description' => sprintf( __( 'Available placeholders: %s', 'woocommerce-appointments' ), '<code>{site_title}, {product_title}, {appointment_number}, {appointment_start}, {appointment_end}, {order_date}, {order_number}</code>' ),
				'placeholder' => $this->get_default_heading( true ),
				'default'     => '',
				'desc_tip'    => true,
			],
			'email_type'           => [
				'title'       => __( 'Email type', 'woocommerce-appointments' ),
				'type'        => 'select',
				'description' => __( 'Choose which format of email to send.', 'woocommerce-appointments' ),
				'default'     => 'html',
				'class'       => 'email_type wc-enhanced-select',
				'options'     => $this->get_email_type_options(),
				'desc_tip'    => true,
			],
		];
	}
}

return new WC_Email_Admin_New_Appointment();
