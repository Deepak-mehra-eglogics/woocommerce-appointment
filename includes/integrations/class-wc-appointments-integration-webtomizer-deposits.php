<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce Deposits - Partial Payments by Webtomizer plugin integration class.
 *
 * Last compatibility check: 4.6.9
 */
class WC_Appointments_Integration_Webtomizer_Deposits {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_filter( 'woocommerce_payment_complete_order_status', array( $this, 'handle_partial_payment' ), 20, 2 );
		add_filter( 'woocommerce_payment_complete_order_status', array( $this, 'handle_completed_payment' ), 40, 2 );
		add_action( 'woocommerce_order_status_changed', array( $this, 'handle_order_status_changed' ), 20, 3 );
		add_action( 'init', array( $this, 'register_custom_post_status' ) );
		add_filter( 'woocommerce_appointments_get_wc_appointment_statuses', array( $this, 'add_custom_status' ) );
		add_filter( 'woocommerce_appointments_get_status_label', array( $this, 'add_custom_status' ) );
		add_filter( 'woocommerce_appointments_gcal_sync_statuses', array( $this, 'add_custom_paid_status' ) );
		add_action( 'woocommerce_payment_complete', array( $this, 'save_order_status' ) );
    }

	/**
	 * Process partial payments
	 *
	 * @since 3.5.6
	 *
	 * @param string  $order_status to be changed for filter.
	 * @param integer $order_id to state which order we're working with.
	 */
	public function handle_partial_payment( $order_status, $order_id ) {
		// Deposits order status support.
		if ( 'partial-payment' === $order_status ) {
			$this->set_status_for_appointments_in_order( $order_id, 'wc-partial-payment' );
		}

		return $order_status;
	}

	/**
	 * Go through all appointment for an order and update the status for each.
	 *
	 * @since 3.5.6
	 *
	 * @param integer $order_id To find appointments.
	 * @param string  $new_status To set to appointments of order.
	 */
	public function set_status_for_appointments_in_order( $order_id, $new_status ) {
		$appointment_ids = WC_Appointment_Data_Store::get_appointment_ids_from_order_id( $order_id );

		foreach ( $appointment_ids as $appointment_id ) {
			try {
				$appointment = new WC_Appointment( $appointment_id );
			} catch ( Exception $e ) {
				wc_get_logger()->error( $e->getMessage() );
				continue;
			}

			#error_log( var_export( $new_status, true ) );

			$appointment->set_status( $new_status );
			$appointment->save();
		}
	}

	/**
	 * Process partial payments.
	 *
	 * @since 3.5.6
	 *
	 * @param string  $order_status To filter/change.
	 * @param integer $order_id To which this applies.
	 */
	public function handle_completed_payment( $order_status, $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! is_a( $order, 'WC_Order' ) ) {
			return $order_status;
		}

		if ( count( $order->get_items() ) < 1 ) {
			return $order_status;
		}
		$virtual_appointment_order = false;

		foreach ( $order->get_items() as $item ) {
			if ( $item->is_type( 'line_item' ) ) {
				$product                   = $item->get_product();
				$virtual_appointment_order = $product && $product->is_virtual() && $product->is_type( 'appointment' );
			}
			if ( ! $virtual_appointment_order ) {
				break;
			}
		}

		// Virtual order, mark as completed.
		if ( $virtual_appointment_order ) {
			return 'completed';
		}

		return $order_status;
	}

	/**
	 * Handle changes in the order status.
	 *
	 * @since 4.22.8
	 *
	 * @param integer $order_id Id of de order whose status is changing.
	 * @param string $from Previous order status.
	 * @param string $to New order status.
	 */
	public function handle_order_status_changed( $order_id, $from, $to ) {
		if ( 'partial-payment' === $to ) {
			$this->set_status_for_appointments_in_order( $order_id, 'wc-' . $to );
		} elseif ( 'failed' === $to ) {
			$this->set_status_for_appointments_in_order( $order_id, 'unpaid' );
		}
	}

	/**
	 * Register the Deposits integration post status.
	 *
	 * @since 3.5.6
	 */
	public function register_custom_post_status() {
		if ( is_admin() && isset( $_GET['post_type'] ) && 'wc_appointment' === $_GET['post_type'] ) {
			register_post_status(
				'wc-partial-payment',
				array(
					'label'                     => '<span class="status-partial-payment tips" data-tip="' . wc_sanitize_tooltip( _x( 'Partially Paid', 'woocommerce-appointments', 'woocommerce-appointments' ) ) . '">' . _x( 'Partially Paid', 'woocommerce-appointments', 'woocommerce-appointments' ) . '</span>',
					'public'                    => true,
					'exclude_from_search'       => false,
					'show_in_admin_all_list'    => true,
					'show_in_admin_status_list' => true,
					/* translators: %s */
					'label_count'               => _n_noop( 'Partially Paid <span class="count">(%s)</span>', 'Partially Paid <span class="count">(%s)</span>', 'woocommerce-appointments' ),
				)
			);
		}
	}

	/**
	 * Add custom status to the list of standard appointments status.
	 *
	 * @since 3.5.6
	 *
	 * @param array $statuses to be changed in this function.
	 */
	public function add_custom_status( $statuses ) {
		$statuses['wc-partial-payment'] = __( 'Partially Paid', 'woocommerce-appointments' );
		return $statuses;
	}

	/**
	 * Set partially paid status for appointments.
	 *
	 * @since 4.23.0
	 *
	 * @param array $order_id Id of de order whose status is changing.
	 */
	public function adjust_appointment_status( $order_id ){
        $appointment_ids = \WC_Appointment_Data_Store::get_appointment_ids_from_order_id( $order_id );

		if ( is_array( $appointment_id ) && !empty( $appointment_id ) ) {
			foreach ( $appointment_ids as $appointment_id ) {
				$appointment = new \WC_Appointment( $appointment_id );
	            $appointment->set_status( 'wc-partial-payment' );
	            $appointment->save();
			}
		}
    }

	/**
	 * Make martial payment count as paid so items are added to Google calendar.
	 *
	 * @param array $statuses Current paid statuses.
	 *
	 * @return array
	 */
	public function add_custom_paid_status( $statuses ) {
		$statuses[] = 'wc-partial-payment';
		return $statuses;
	}

	/**
	 * Saves the order status from pending to wc-partial-payment
	 * so that the reminder cron job can pick it up.
	 *
	 * @since 4.9.2
	 * @return void
	 */
	public function save_order_status( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( 'partially-paid' === $order->get_status() ) {
			$this->set_status_for_appointments_in_order( $order_id, 'wc-partial-payment' );
		}
	}
}

new WC_Appointments_Integration_Webtomizer_Deposits();
