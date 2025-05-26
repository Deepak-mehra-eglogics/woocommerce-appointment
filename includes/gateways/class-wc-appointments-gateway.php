<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * WC_Appointments_Gateway class.
 */
class WC_Appointments_Gateway extends WC_Payment_Gateway {

	/**
	 * Constructor for the gateway.
	 */
	public function __construct() {
		$this->id                = 'wcappointmentsgateway';
		$this->icon              = '';
		$this->has_fields        = false;
		$this->method_title      = __( 'Check appointment availability', 'woocommerce-appointments' );
		$this->title             = $this->method_title;
		$this->description       = '';
		$this->order_button_text = __( 'Request Confirmation', 'woocommerce-appointments' );
		$this->supports          = [
			'products',
			'subscriptions',
			'subscription_cancellation',
			'subscription_suspension',
			'subscription_reactivation',
			'subscription_amount_changes',
			'subscription_date_changes',
			'subscription_payment_method_change',
		];

		add_action( 'woocommerce_thankyou_' . $this->id, [ $this, 'thankyou_page' ] );
	}

	/**
	 * Admin page.
	 */
	public function admin_options() {
		$title = ( ! empty( $this->method_title ) ) ? $this->method_title : __( 'Settings', 'woocommerce-appointments' );

		echo '<h3>' . esc_html( $title ) . '</h3>';

		echo '<p>' . esc_html__( 'This is fictitious payment method used for appointments that requires confirmation.', 'woocommerce-appointments' ) . '</p>';
		echo '<p>' . esc_html__( 'This gateway requires no configuration.', 'woocommerce-appointments' ) . '</p>';

		// Hides the save button
		echo '<style>p.submit input[type="submit"] { display: none }</style>';
	}

	/**
	 * Check if this gateway is enabled.
	 *
	 * @return bool
	 */
	public function is_available() {
		if ( 'yes' !== $this->enabled ) {
			return false;
		}

		if ( wc_appointment_cart_requires_confirmation() ) {
			return true;
		}

		return false;
	}

	/**
	 * Process the payment and return the result
	 *
	 * @param  int $order_id
	 *
	 * @return array
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		// Add custom order note.
		$order->add_order_note( __( 'This order is awaiting confirmation from the shop manager', 'woocommerce-appointments' ) );

		// Remove cart
		WC()->cart->empty_cart();

		// Return thankyou redirect
		return [
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		];
	}

	/**
	 * Output for the order received page.
	 */
	public function thankyou_page( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( 'completed' == $order->get_status() ) {
			echo '<p>' . esc_html__( 'Your appointment has been confirmed. Thank you.', 'woocommerce-appointments' ) . '</p>';
		} else {
			echo '<p>' . esc_html__( 'Your appointment is awaiting confirmation. You will be notified by email as soon as we\'ve confirmed availability.', 'woocommerce-appointments' ) . '</p>';
		}
	}
}
