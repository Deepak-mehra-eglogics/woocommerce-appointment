<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce Deposits - Partial Payments plugin integration class.
 *
 * Last compatibility check: 3.0.0
 */
class WC_Appointments_Integration_Webtomizer_Deposits {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_custom_post_status' ) );
		add_action( 'woocommerce_order_status_on-hold_to_partially-paid', array( $this, 'handle_on_hold_to_partially_paid' ), 20, 2 );
		add_filter( 'woocommerce_payment_complete_order_status', array( $this, 'handle_partially_paid' ), 20, 2 );
		add_filter( 'woocommerce_payment_complete_order_status', array( $this, 'handle_completed_payment' ), 40, 2 );
		add_filter( 'woocommerce_appointments_get_wc_appointment_statuses', array( $this, 'add_custom_status' ) );
		add_filter( 'woocommerce_appointments_get_status_label', array( $this, 'add_custom_status' ) );
		add_filter( 'woocommerce_appointments_gcal_sync_statuses', array( $this, 'add_custom_paid_status' ) );
		add_action( 'woocommerce_payment_complete', array( $this, 'save_order_status' ) );

        // When an order is processed or completed, we can mark publish the pending appointments.
        add_action( 'woocommerce_order_status_processing', array( $this, 'publish_appointments' ), 10, 1 );
        add_action( 'woocommerce_order_status_completed', array( $this, 'publish_appointments' ), 10, 1 );

    }

    /**
     * Called when an order is paid
     * @param  int $order_id
     */
    public function publish_appointments( $order_id ) {
        $order             = wc_get_order( $order_id );
        $payment_method    = $order ? $order->get_payment_method() : null;
        $order_id          = apply_filters( 'woocommerce_appointments_publish_appointments_order_id', $order_id );
        $order_has_deposit = $order->get_meta( '_wc_deposits_order_has_deposit', true ) === 'yes';

        if ( 'wcdp_payment' === $order->get_type() || ! $order_has_deposit ) return;

        $appointments = WC_Appointment_Data_Store::get_appointment_ids_from_order_id( $order_id );

        // Don't publish appointments for COD orders, but still schedule their events
        $no_publish = $order->has_status( 'processing' ) && 'cod' === $payment_method;

        foreach ( $appointments as $appointment_id ) {
            $appointment = get_wc_appointment( $appointment_id );

            if ( $no_publish ) {
                $appointment->maybe_schedule_event( 'reminder' );
                $appointment->maybe_schedule_event( 'complete' );
                // Send email notification to admin and staff.
                if ( ! as_next_scheduled_action( 'woocommerce_admin_new_appointment_notification', [ $appointment_id ] ) ) {
                    as_schedule_single_action( time(), 'woocommerce_admin_new_appointment_notification', [ $appointment_id ], 'wca' );
                }
            } else {
                $appointment->set_status( 'paid' );
                $appointment->save();
            }
        }
    }



    /**
	 * Process partial payments for on hold status.
	 *
	 * @since 4.9.0
	 *
	 * @param integer $order_id to state which order we're working with.
	 * @param object  $order we are working with.
	 */
	public function handle_on_hold_to_partially_paid( $order_id, $order ) {
		$this->handle_partially_paid( $order->get_status(), $order_id );
	}

	/**
	 * Process partial payments
	 *
	 * @since 3.5.6
	 *
	 * @param string  $order_status to be changed for filter.
	 * @param integer $order_id to state which order we're working with.
	 */
	public function handle_partially_paid( $order_status, $order_id ) {
		// Deposits order status support.
		if ( 'partially-paid' === $order_status ) {
			$this->set_status_for_appointments_in_order( $order_id, 'wc-partially-paid' );
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
			$appointment = get_wc_appointment( $appointment_id );
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
		if ( 'processing' !== $order_status
			|| ! $order->has_status( 'pending' ) ) {
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
	 * Register the Deposits integration post status.
	 *
	 * @since 3.5.6
	 */
	public function register_custom_post_status() {
		if ( is_admin() && isset( $_GET['post_type'] ) && 'wc_appointment' === $_GET['post_type'] ) {
			register_post_status(
				'wc-partially-paid',
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
		$statuses['wc-partially-paid'] = __( 'Partially Paid', 'woocommerce-appointments' );
		return $statuses;
	}

	/**
	 * Make martial payment count as paid so items are added to Google calendar.
	 *
	 * @param array $statuses Current paid statuses.
	 *
	 * @return array
	 */
	public function add_custom_paid_status( $statuses ) {
		$statuses[] = 'wc-partially-paid';
		return $statuses;
	}

	/**
	 * Saves the order status from pending to wc-partially-paid
	 * so that the reminder cron job can pick it up.
	 *
	 * @since 4.9.2
	 * @return void
	 */
	public function save_order_status( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( 'partially-paid' === $order->get_status() ) {
			$this->set_status_for_appointments_in_order( $order_id, 'wc-partially-paid' );
		}
	}
}

new WC_Appointments_Integration_Webtomizer_Deposits();
