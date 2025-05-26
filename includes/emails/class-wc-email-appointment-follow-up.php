<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WC_Email_Appointment_Follow_Up' ) ) :

	/**
	 * Appointment follow-up
	 *
	 * An email sent to the user after appointment is completed.
	 *
	 * @class   WC_Email_Appointment_Follow_Up
	 * @extends WC_Email
	 */
	class WC_Email_Appointment_Follow_Up extends WC_Email {

		/**
		 * Constructor
		 */
		public function __construct() {
			$this->id             = 'appointment_follow_up';
			$this->title          = __( 'Appointment Follow-up', 'woocommerce-appointments' );
			$this->description    = __( 'Appointment follow-ups are sent to the customer after appointment is completed.', 'woocommerce-appointments' );
			$this->customer_email = true;
			$this->template_html  = 'emails/customer-appointment-follow-up.php';
			$this->template_plain = 'emails/plain/customer-appointment-follow-up.php';
			$this->placeholders   = [
				'{site_title}'         => $this->get_blogname(),
				'{product_title}'      => '',
				'{appointment_number}' => '',
				'{appointment_start}'  => '',
				'{appointment_end}'    => '',
				'{order_date}'         => '',
				'{order_number}'       => '',
			];

			// Call parent constructor
			parent::__construct();

			// Other settings
			$this->template_base = WC_APPOINTMENTS_TEMPLATE_PATH;
		}

		/**
		 * Email follow-up time options.
		 *
		 * @since  4.8.0
		 * @return array
		 */
		public function get_follow_up_time_options() {
			// Hours.
			for ( $i = 1; $i <= 23; $i ++ ) {
				/* translators: %s: hours in singular or plural */
				$key = sprintf( _nx( '%s hour', '%s hour', $i, 'nx time' ), $i ); #prevent translation.
				/* translators: %s: hours in singular or plural */
				$value  = sprintf( _n( '%s hour', '%s hours', $i, 'woocommerce-appointments' ), $i );
				$value .= '&nbsp;' . __( 'after the completion.', 'woocommerce-appointments' );

				$times[ $key ] = $value;
			}
			// Days.
			for ( $i = 1; $i <= 30; $i ++ ) {
				/* translators: %s: days in singular or plural */
				$key = sprintf( _nx( '%s day', '%s days', $i, 'nx time' ), $i ); #prevent translation.
				/* translators: %s: days in singular or plural */
				$value  = sprintf( _n( '%s day', '%s days', $i, 'woocommerce-appointments' ), $i );
				$value .= '&nbsp;' . __( 'after the completion.', 'woocommerce-appointments' );

				$times[ $key ] = $value;
			}

			return $times;
		}

		/**
		 * Get email subject.
		 *
		 * @since  4.8.0
		 * @return string
		 */
		public function get_default_subject() {
			return __( '[{site_title}]: Appointment follow-up for {product_title}', 'woocommerce-appointments' );
		}

		/**
		 * Get email heading.
		 *
		 * @since  4.8.0
		 * @return string
		 */
		public function get_default_heading() {
			return __( 'Thanks for your appointment!', 'woocommerce-appointments' );
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

					$this->recipient = apply_filters( 'woocommerce_email_follow_up_recipients', $billing_email, $this->object );
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
						$this->recipient = apply_filters( 'woocommerce_email_follow_up_recipients', $customer->user_email, $this->object );
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
					'default' => 'no',
				],
				'follow_up_time'     => [
					'title'       => __( 'Sending time', 'woocommerce-appointments' ),
					'type'        => 'select',
					'description' => __( 'Sending time after the appointment is completed.', 'woocommerce-appointments' ),
					'default'     => '1 day',
					'class'       => 'email_time wc-enhanced-select',
					'options'     => $this->get_follow_up_time_options(),
					'desc_tip'    => true,
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

endif;

return new WC_Email_Appointment_Follow_Up();
