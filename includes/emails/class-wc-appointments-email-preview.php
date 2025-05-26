<?php
/**
 * Appointments Email Preview Class
 */
class WC_Appointments_Email_Preview {

	/**
	 * The email type being previewed
	 *
	 * @var string
	 */
	private $email_type;

	/**
	 * Previous start date.
	 *
	 * @var string
	 */
	public $prev_start_date;

	/**
	 * Previous end date.
	 *
	 * @var string
	 */
	public $prev_end_date;

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'woocommerce_prepare_email_for_preview', [ $this, 'prepare_email_for_preview' ] );
		add_filter( 'woocommerce_email_preview_placeholders', [ $this, 'email_preview_placeholders' ], 10, 2 );
	}

	/**
	 * Prepare appointment email dummy data for preview.
	 *
	 * @param WC_Email $email The email object.
	 *
	 * @return WC_Email
	 */
	public function prepare_email_for_preview( $email ) {
		$this->email_type = get_class( $email );

		#error_log( var_export( $email, true ) );

		if ( ! $this->is_appointment_email() ) {
			return $email;
		}

		switch ( $this->email_type ) {
			case 'WC_Email_Admin_Appointment_Rescheduled':
				// Mock up rescheduled old appointment times.
				$email->prev_start_date = gmdate( 'F j, Y g:i a', strtotime( '-3 day' ) );
				$email->prev_end_date = gmdate( 'F j, Y g:i a', strtotime( '-1 day' ) );
				$email->set_object( $this->get_dummy_appointment() );
				break;
			case 'WC_Email_Admin_Appointment_Cancelled':
			case 'WC_Email_Admin_New_Appointment':
			case 'WC_Email_Appointment_Cancelled':
			case 'WC_Email_Appointment_Confirmed':
			case 'WC_Email_Appointment_Reminder':
			case 'WC_Email_Appointment_Follow_Up':
				$email->set_object( $this->get_dummy_appointment() );
				break;
		}

		return $email;
	}

	/**
	 * Placeholders for email preview.
	 *
	 * @param WC_Order $placeholders Placeholders for email subject.
	 * @param string   $email_type The email type to preview.
	 *
	 * @return array
	 */
	public function email_preview_placeholders( $placeholders, $email_type ) {
		$this->email_type = $email_type;

		if ( ! $this->is_appointment_email() ) {
			return $placeholders;
		}

		$appointment = $this->get_dummy_appointment();
		$product     = $this->get_dummy_product();
		$order       = $this->get_dummy_order();

		#error_log( var_export( $placeholders, true ) );

		$placeholders['{product_title}']          = $product->get_name();
		$placeholders['{appointment_number}']     = $appointment->get_id();
		$placeholders['{appointment_start}']      = $appointment->get_start_date();
		$placeholders['{appointment_end}']        = $appointment->get_end_date();
		$placeholders['{prev_appointment_start}'] = gmdate( 'F j, Y g:i a', strtotime( '-3 day' ) );
		$placeholders['{prev_appointment_end}']   = gmdate( 'F j, Y g:i a', strtotime( '-1 day' ) );
		$placeholders['{order_date}']             = $order->get_date_created();
		$placeholders['{order_number}']           = $order->get_id();
		$placeholders['{customer_first_name}']    = $order->get_billing_first_name();
		$placeholders['{customer_last_name}']     = $order->get_billing_last_name();
		$placeholders['{customer_full_name}']     = $order->get_formatted_billing_full_name();
		$placeholders['{customer_email}']         = $order->get_billing_email();

		return $placeholders;
	}

	/**
	 * Get a dummy appointment for use in preview emails.
	 *
	 * @return WC_Appointment
	 */
	private function get_dummy_appointment() {
		$appointment = new WC_Appointment();
		$product     = $this->get_dummy_product();
		$order       = $this->get_dummy_order();

		$appointment->set_id( 12346 );
		$appointment->set_product( $product );
		$appointment->set_order( $order );
		$appointment->set_start( gmdate( 'F j, Y g:i a', strtotime( '-1 day' ) ) );
		$appointment->set_end( gmdate( 'F j, Y g:i a', strtotime( '+1 day' ) ) );

		/**
		 * Filter the dummy appointment object used in email previews.
		 *
		 * @param WC_Appointment $appointment The dummy appointment object.
		 * @param string          $email_type   The email type being previewed.
		 */
		return apply_filters( 'woocommerce_appointments_email_preview_dummy_appointment', $appointment, $this->email_type );
	}

	/**
	 * Get a dummy product for use when previewing appointment emails.
	 *
	 * @return WC_Product
	 */
	private function get_dummy_product() {
		$product = new WC_Product_Appointment();

		$product->set_id( 12344 );
		$product->set_name( 'Dummy Service' );
		$product->set_price( 25 );
		$product->set_user_can_reschedule( true );

		/**
		 * Filter the dummy appointment product object used in email previews.
		 *
		 * @param WC_Product $product The dummy product object.
		 * @param string     $email_type The email type being previewed.
		 */
		return apply_filters( 'woocommerce_appointments_email_preview_dummy_product', $product, $this->email_type );
	}

	/**
	 * Get a dummy order object without the need to create in the database.
	 *
	 * @return WC_Order
	 */
	private function get_dummy_order() {
		$product   = $this->get_dummy_product();

		$order = new WC_Order();
		if ( $product ) {
			$order->add_product( $product, 2 );
		}
		$order->set_id( 12345 );
		$order->set_date_created( time() );
		$order->set_currency( 'USD' );
		$order->set_discount_total( 10 );
		$order->set_shipping_total( 5 );
		$order->set_total( 45 );
		$order->set_payment_method_title( __( 'Direct bank transfer', 'woocommerce-appointments' ) );
		$order->set_customer_note( __( "This is a customer note. Customers can add a note to their order on checkout.\n\nIt can be multiple lines. If there’s no note, this section is hidden.", 'woocommerce-appointments' ) );

		$address = $this->get_dummy_address();
		$order->set_billing_address( $address );
		$order->set_shipping_address( $address );

		/**
		 * A dummy WC_Order used in email preview.
		 *
		 * @param WC_Order $order The dummy order object.
		 * @param string   $email_type The email type to preview.
		 *
		 * @since 9.6.0
		 */
		return apply_filters( 'woocommerce_email_preview_dummy_order', $order, $this->email_type );
	}

	/**
	 * Get a dummy address used when previewing appointment emails.
	 *
	 * @return array
	 */
	private function get_dummy_address() {
		$address = [
			'first_name' => 'John',
			'last_name'  => 'Doe',
			'company'    => 'Company',
			'email'      => 'john@company.com',
			'phone'      => '555-555-5555',
			'address_1'  => '123 Fake Street',
			'city'       => 'Faketown',
			'postcode'   => '12345',
			'country'    => 'US',
			'state'      => 'CA',
		];

		/**
		 * Filter the dummy address used in email previews.
		 *
		 * @param array  $address    The dummy address.
		 * @param string $email_type The email type being previewed.
		 */
		return apply_filters( 'woocommerce_appointments_email_preview_dummy_address', $address, $this->email_type );
	}

	/**
	 * Check if the email being previewed is a appointment email.
	 *
	 * @return bool
	 */
	private function is_appointment_email() {
		// Appointment emails.
		$appointment_emails = [
			'admin_appointment_cancelled'   => 'WC_Email_Admin_Appointment_Cancelled',
			'admin_appointment_rescheduled' => 'WC_Email_Admin_Appointment_Rescheduled',
			'admin_new_appointment'         => 'WC_Email_Admin_New_Appointment',
			'appointment_cancelled'         => 'WC_Email_Appointment_Cancelled',
			'appointment_confirmed'         => 'WC_Email_Appointment_Confirmed',
			'appointment_reminder'          => 'WC_Email_Appointment_Reminder',
			'appointment_follow_up'         => 'WC_Email_Appointment_Follow_Up',
		];

		return in_array( $this->email_type, $appointment_emails );
	}
}

new WC_Appointments_Email_Preview();
