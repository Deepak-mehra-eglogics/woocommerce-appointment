<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce Twilio SMS Notification integration class.
 */
class WC_Appointments_Integration_WCTSN {

    /**
	 * Constructor
	 */
	public function __construct() {
		add_filter( 'wc_order_statuses', array( $this, 'add_twilio_sms_order_statuses' ) );
		add_action( 'wc-appointment-reminder', array( $this, 'send_twilio_sms_reminder' ) );
		add_action( 'woocommerce_settings_start', array( $this, 'add_twilio_sms_reminder_default' ) );
	}

	/**
	 * Add order statusus to WooCommmerce
	 * Plugin does not have any filters, so this is the only way to add the option
	 * @param array $order_statuses
	 * @return array
	 */
	public function add_twilio_sms_order_statuses( $order_statuses ) {
		if ( ! is_admin() ) {
			return $order_statuses;
		}

		$current_tab = empty( $_GET['tab'] ) ? 'general' : sanitize_title( $_GET['tab'] );

		if ( 'twilio_sms' === $current_tab ) {
			$order_statuses['wc-reminder'] = _x( 'Appointment Reminder', 'woocommerce-appointments', 'woocommerce-appointments' );
		}

		return $order_statuses;
	}

	/**
	 * Send appointment reminder email
	 */
	public function send_twilio_sms_reminder( $appointment_id ) {
		global $wpdb;

		// Get order ID.
		$order_id = $wpdb->get_var( $wpdb->prepare( "SELECT post_parent FROM {$wpdb->posts} WHERE ID = %d", $appointment_id ) );

		// Stop here if order not attached to appointment as we can't get customer phone
		if ( ! $order_id ) {
			return;
		}

		// Get order object.
		$order = wc_get_order( $order_id );

		// get checkbox opt-in label
		$optin = get_option( 'wc_twilio_sms_checkout_optin_checkbox_label', '' );

		// check if opt-in checkbox is enabled
		if ( ! empty( $optin ) ) {

			// get opt-in meta for order
			$optin = get_post_meta( $order_id, '_wc_twilio_sms_optin', true );

			// check if customer has opted-in
			if ( empty( $optin ) ) {
				// no meta set, so customer has not opted in
				return;
			}
		}

		// Manually set SMS status to 'reminder'.
		$sms_status = 'reminder';

		// Check if sending SMS updates for this order's status.
		if ( in_array( 'wc-' . $sms_status, get_option( 'wc_twilio_sms_send_sms_order_statuses' ) ) ) {

			// Get message template.
			$message = get_option( 'wc_twilio_sms_' . $sms_status . '_sms_template', '' );

			// Use the default template if status-specific one is blank.
			if ( empty( $message ) ) {
				$message = __( 'Reminder: Appointment (%appointment_name%) will take place soon (%appointment_time%).', 'woocommerce-appointments' );
			}

			// Allow modification of message before variable replace (add additional variables, etc).
			$message = apply_filters( 'wc_twilio_sms_customer_sms_before_variable_replace', $message, $order );

			// Replace template variables.
			$message = $this->sms_replace_message_variables( $message, $order, $appointment_id );

			// Allow modification of message after variable replace.
			$message = apply_filters( 'wc_twilio_sms_customer_sms_after_variable_replace', $message, $order );

			// Fire up Twilio.
			$notification = new WC_Twilio_SMS_Notification( $order_id );

			// Send the SMS.
			$notification->send_manual_customer_notification( $message );
		}

	}

	/**
	 * Replaces template variables in SMS message
	 *
	 * @param string $message raw SMS message to replace with variable info
	 * @return string message with variables replaced with indicated values
	 */
	public function sms_replace_message_variables( $message, $order, $appointment_id ) {
		$appointment = get_wc_appointment( $appointment_id );
		$appointment_time = $appointment->is_all_day() ? $appointment->get_start_date( wc_appointments_date_format(), '' ) : $appointment->get_start_date( wc_appointments_date_format(), '' ) . ', ' . $appointment->get_start_date( '', wc_appointments_time_format() );

		$replacements = array(
			'%shop_name%'        => get_bloginfo( 'name' ),
			'%order_id%'         => $order->get_order_number(),
			'%order_count%'      => $order->get_item_count(),
			'%order_amount%'     => $order->get_total(),
			'%order_status%'     => ucfirst( $order->get_status() ),
			'%billing_name%'     => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
			'%shipping_name%'    => $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name(),
			'%shipping_method%'  => $order->get_shipping_method(),
			'%appointment_name%' => $appointment->get_product_name(),
			'%appointment_time%' => $appointment_time,
		);

		return str_replace( array_keys( $replacements ), $replacements, $message );
	}

	/**
	 * Register default reminder sms message if empty
	 *
	 * @return string message
	 */
	public function add_twilio_sms_reminder_default() {
		$sms_status = 'reminder';

		// Get message template.
		$message = get_option( 'wc_twilio_sms_' . $sms_status . '_sms_template', '' );

		// Use the default template if status-specific one is blank.
		if ( empty( $message ) ) {
			$message = __( 'Reminder: Appointment (%appointment_name%) will take place soon (%appointment_time%).', 'woocommerce-appointments' );
			update_option( 'wc_twilio_sms_' . $sms_status . '_sms_template', $message );
		}
	}

}

$GLOBALS['wc_appointments_integration_wctsn'] = new WC_Appointments_Integration_WCTSN();
