<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Appointment is confirmed
 *
 * An email sent to the user when an appointment is confirmed.
 *
 * @class   WC_Email_Appointment_Confirmed
 * @extends WC_Email
 */
class WC_Email_Appointment_Confirmed extends WC_Email {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->id    = 'appointment_confirmed';
		$this->title = __( 'Appointment Confirmed', 'woocommerce-appointments' );
		/* translators: %1$s: <mark>, %2$s: </mark> */
		$this->description    = sprintf( __( 'Appointment confirmation emails are sent when the status of an appointment goes to confirmed. %1$sNew appointment notification is part of New order email%2$s.', 'woocommerce-appointments' ), '<mark>', '</mark>' );
		$this->customer_email = true;
		$this->template_html  = 'emails/customer-appointment-confirmed.php';
		$this->template_plain = 'emails/plain/customer-appointment-confirmed.php';
		$this->placeholders   = [
			'{site_title}'         => $this->get_blogname(),
			'{product_title}'      => '',
			'{appointment_number}' => '',
			'{appointment_start}'  => '',
			'{appointment_end}'    => '',
			'{order_date}'         => '',
			'{order_number}'       => '',
		];

		/*
		 * The following action is initiated via WC core.
		 * It is added to WC core's list in WC_Booking_Email_Manager::appointments_email_actions.
		 */
		add_action( 'woocommerce_admin_confirmed_notification', [ $this, 'queue_notification' ] );

		// Triggers for this email
		add_action( 'woocommerce_appointment_confirmed_notification', [ $this, 'queue_notification' ] );

		// Call parent constructor
		parent::__construct();

		// Other settings
		$this->template_base = WC_APPOINTMENTS_TEMPLATE_PATH;
	}

	/**
	 * This is to allow for cases where the dates are changed and emails are sent before the new data is saved.
	 *
	 * @param    $appointment_id
 	 * @since    4.0.1 introduced
 	 * @version  4.0.1
	 */
	public function queue_notification( $appointment_id ) {
		as_schedule_single_action( time(), 'wc-appointment-confirmed', [ $appointment_id ], 'wca' );
	}

	/**
	 * Get email subject.
	 *
	 * @since  4.2.9
	 * @return string
	 */
	public function get_default_subject() {
		return __( '[{site_title}]: Appointment for {product_title} has been confirmed', 'woocommerce-appointments' );
	}

	/**
	 * Get email heading.
	 *
	 * @since  4.2.9
	 * @return string
	 */
	public function get_default_heading() {
		return __( 'Appointment #{appointment_number} Confirmed', 'woocommerce-appointments' );
	}

	/**
	 * Default content to show below main email content.
	 *
	 * @since 4.8.0
	 * @return string
	 */
	public function get_default_additional_content() {
		return __( 'Thanks for scheduling with us.', 'woocommerce-appointments' );
	}

	/**
	 * trigger function.
	 *
	 * @access public
	 * @return void
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

			$this->placeholders['{appointment_number}'] = $appointment_id;
			$this->placeholders['{appointment_start}']  = $this->object->get_start_date();
			$this->placeholders['{appointment_end}']    = $this->object->get_end_date();

			if ( $this->object->get_product() ) {
				$this->placeholders['{product_title}'] = $this->object->get_product_name();
			}

			if ( $this->object->get_order() ) {
				$billing_email = $this->object->get_order()->get_billing_email();
				$order_date    = $this->object->get_order()->get_date_created() ? $this->object->get_order()->get_date_created() : '';

				$this->placeholders['{order_date}']          = wc_format_datetime( $order_date );
				$this->placeholders['{order_number}']        = $this->object->get_order()->get_order_number();
				$this->placeholders['{customer_first_name}'] = $this->object->get_order()->get_billing_first_name();
				$this->placeholders['{customer_last_name}']  = $this->object->get_order()->get_billing_last_name();
				$this->placeholders['{customer_full_name}']  = $this->object->get_order()->get_formatted_billing_full_name();
				$this->placeholders['{customer_email}']      = $this->object->get_order()->get_billing_email();

				$this->recipient = apply_filters( 'woocommerce_email_confirmed_recipients', $billing_email, $this->object );
			} else {
				$this->placeholders['{order_date}']          = date_i18n( wc_appointments_date_format(), strtotime( $this->object->appointment_date ) );
				$this->placeholders['{order_number}']        = __( 'N/A', 'woocommerce-appointments' );
				$this->placeholders['{customer_first_name}'] = $this->object->get_customer()->full_name;
				$this->placeholders['{customer_last_name}']  = $this->object->get_customer()->full_name;
				$this->placeholders['{customer_full_name}']  = $this->object->get_customer()->full_name;
				$this->placeholders['{customer_email}']      = $this->object->get_customer()->email;

				$customer_id = $this->object->customer_id;
				$customer    = $customer_id ? get_user_by( 'id', $customer_id ) : false;

				if ( $customer_id && $customer ) {
					$this->recipient = apply_filters( 'woocommerce_email_confirmed_recipients', $customer->user_email, $this->object );
				}
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
				'appointment'        => $this->object,
				'email_heading'      => $this->get_heading(),
				'additional_content' => $this->get_additional_content(),
				'sent_to_admin'      => false,
				'plain_text'         => false,
				'email'              => $this,
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
			$this->template_plain,
			[
				'appointment'        => $this->object,
				'email_heading'      => $this->get_heading(),
				'additional_content' => $this->get_additional_content(),
				'sent_to_admin'      => false,
				'plain_text'         => true,
				'email'              => $this,
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
			'enabled'            => [
				'title'   => __( 'Enable/Disable', 'woocommerce-appointments' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable this email notification', 'woocommerce-appointments' ),
				'default' => 'yes',
			],
			'subject'            => [
				'title'       => __( 'Subject', 'woocommerce-appointments' ),
				'type'        => 'text',
				/* translators: %s: list of placeholders */
				'description' => sprintf( __( 'Available placeholders: %s', 'woocommerce-appointments' ), '<code>{site_title}, {product_title}, {appointment_number}, {appointment_start}, {appointment_end}, {order_date}, {order_number}</code>' ),
				'placeholder' => $this->get_default_subject(),
				'default'     => '',
				'desc_tip'    => true,
			],
			'heading'            => [
				'title'       => __( 'Email Heading', 'woocommerce-appointments' ),
				'type'        => 'text',
				/* translators: %s: list of placeholders */
				'description' => sprintf( __( 'Available placeholders: %s', 'woocommerce-appointments' ), '<code>{site_title}, {product_title}, {appointment_number}, {appointment_start}, {appointment_end}, {order_date}, {order_number}</code>' ),
				'placeholder' => $this->get_default_heading(),
				'default'     => '',
				'desc_tip'    => true,
			],
			'additional_content' => [
				'title'       => __( 'Additional Content', 'woocommerce-appointments' ),
				'type'        => 'textarea',
				'css'         => 'width:400px; height: 75px;',
				/* translators: %s: list of placeholders */
				'description' => sprintf( __( 'Available placeholders: %s', 'woocommerce-appointments' ), '<code>{site_title}, {product_title}, {appointment_number}, {appointment_start}, {appointment_end}, {order_date}, {order_number}</code>' ),
				'placeholder' => $this->get_default_additional_content(),
				'default'     => $this->get_default_additional_content(),
				'desc_tip'    => true,
			],
			'email_type'         => [
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

return new WC_Email_Appointment_Confirmed();
