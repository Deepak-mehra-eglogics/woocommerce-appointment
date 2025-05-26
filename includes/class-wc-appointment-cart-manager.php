<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * WC_Appointment_Cart_Manager class.
 */
class WC_Appointment_Cart_Manager {

	/**
	 * The class id used for identification in logging.
	 *
	 * @var $id
	 */
	public $id = 'wc_appointment_cart_manager';

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'woocommerce_before_add_to_cart_button', array( $this, 'add_product_object' ), 0 );
		add_action( 'woocommerce_before_appointment_form_output', array( $this, 'add_product_object' ), 0 );
		add_action( 'woocommerce_after_appointment_form_output', array( $this, 'add_product_object' ), 0 );
		add_action( 'woocommerce_appointment_add_to_cart', array( $this, 'add_to_cart' ), 30 );
		add_filter( 'woocommerce_cart_item_quantity', array( $this, 'cart_item_quantity' ), 15, 3 );
		add_filter( 'woocommerce_add_cart_item', array( $this, 'add_cart_item' ), 9, 1 ); #9 to allow others to hook after.
		add_filter( 'woocommerce_get_cart_item_from_session', array( $this, 'get_cart_item_from_session' ), 9, 3 ); #9 to allow others to hook after.
		add_action( 'woocommerce_cart_loaded_from_session', array( $this, 'cart_loaded_from_session' ), 20 );
		add_filter( 'woocommerce_get_item_data', array( $this, 'get_item_data' ), 10, 2 );
		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'add_cart_item_data' ), 10, 2 );
		add_action( 'woocommerce_new_order_item', array( $this, 'order_item_meta' ), 50, 2 );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'review_items_on_block_checkout' ), 10, 1 );
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'review_items_on_shortcode_checkout' ), 10, 1 );
		add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'validate_appointment_posted_data' ), 10, 3 );
		add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'validate_appointment_requires_confirmation' ), 20, 2 );
		add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'validate_appointment_sold_individually' ), 20, 3 );
		add_filter( 'woocommerce_store_api_product_quantity_editable', array( $this, 'disable_cart_block_qty_field' ), 10, 3 );
		#add_action( 'woocommerce_after_checkout_validation', array( $this, 'validate_appointment_order_legacy_checkout' ), 999, 2 );
		#add_action( 'woocommerce_store_api_cart_errors', array( $this, 'validate_appointment_order_checkout_block_support' ), 999, 2 );
		add_action( 'woocommerce_cart_item_removed', array( $this, 'cart_item_removed' ), 20 );
		add_action( 'woocommerce_cart_item_restored', array( $this, 'cart_item_restored' ), 20 );

		if ( 'yes' === get_option( 'woocommerce_cart_redirect_after_add' ) ) {
			add_filter( 'woocommerce_add_to_cart_redirect', array( $this, 'add_to_cart_redirect' ) );
		}

		add_filter( 'woocommerce_product_add_to_cart_url', array( $this, 'woocommerce_product_link_querystring' ), 10, 2 );
		add_filter( 'woocommerce_loop_product_link', array( $this, 'woocommerce_product_link_querystring' ), 10, 2 );
	}

	/**
	 * Add product object when global variable is missing.
	 */
	public function add_product_object() {
		global $product;

		if ( ! $product && isset( $GLOBALS['product'] ) ) {
			$product = $GLOBALS['product'];
		}
	}


	/**
	 * Add to cart for appointments
	 */
	public function add_to_cart() {
		global $product;

		// Prepare form
		$appointment_form = new WC_Appointment_Form( $product );

		// Get template
		wc_get_template(
			'single-product/add-to-cart/appointment.php',
			array(
				'appointment_form' => $appointment_form,
			),
			'',
			WC_APPOINTMENTS_TEMPLATE_PATH
		);
	}

	/**
	 * Make appointment quantity in cart readonly
	 *
	 * @param mixed $product_quantity
	 * @param mixed $cart_item_key
	 * @param mixed $cart_item
	 * @return string
	 */
	public function cart_item_quantity( $product_quantity, $cart_item_key ) {
		$cart_item = WC()->cart->get_cart_item( $cart_item_key );
		if ( ! empty( $cart_item['appointment'] ) && ! empty( $cart_item['appointment']['_qty'] ) ) {
			$product_quantity = sprintf( '%1$s <input type="hidden" name="cart[%2$s][qty]" value="%1$s" />', $cart_item['quantity'], $cart_item_key );
		}

		return $product_quantity;
	}

	/**
	 * Adjust the price of the appointment product based on appointment properties
	 *
	 * @param mixed $cart_item
	 *
	 * @return array cart item
	 */
	public function add_cart_item( $cart_item ) {
		if ( ! empty( $cart_item['appointment'] ) && isset( $cart_item['appointment']['_cost'] ) && '' !== $cart_item['appointment']['_cost'] ) {
			$quantity = isset( $cart_item['appointment']['_qty'] ) && 0 !== $cart_item['appointment']['_qty'] ? $cart_item['appointment']['_qty'] : 1;
			$cart_item['data']->set_price( $cart_item['appointment']['_cost'] / $quantity );
		}

		return $cart_item;
	}

	/**
	 * Get data from the session and add to the cart item's meta
	 *
	 * @param mixed $cart_item
	 * @param mixed $values
	 * @return array cart item
	 */
	public function get_cart_item_from_session( $cart_item, $values, $cart_item_key ) {
		if ( ! empty( $values['appointment'] ) ) {
			$cart_item['appointment'] = $values['appointment'];
			$cart_item                = $this->add_cart_item( $cart_item );
		}

		return $cart_item;
	}

	/**
	 * Before delete
	 *
	 * @param string $cart_item_key identifying which item in cart.
	 */
	public function cart_item_removed( $cart_item_key ) {
		$cart_item = WC()->cart->removed_cart_contents[ $cart_item_key ];

		if ( isset( $cart_item['appointment'] ) ) {
			$appointment_id = $cart_item['appointment']['_appointment_id'];
			$appointment    = get_wc_appointment( $appointment_id );
			if ( $appointment && $appointment->has_status( 'in-cart' ) ) {
				$appointment->update_status( 'was-in-cart' );
				WC_Cache_Helper::get_transient_version( 'appointments', true );

				$message = sprintf( 'Appointment ID: %s removed from cart', $appointment->get_id() );
				wc_add_appointment_log( $this->id, $message );
			}
		}
	}

	/**
	 * Restore item.
	 *
	 * @param string $cart_item_key identifying which item in cart.
	 */
	public function cart_item_restored( $cart_item_key ) {
		$cart      = WC()->cart->get_cart();
		$cart_item = $cart[ $cart_item_key ];

		if ( isset( $cart_item['appointment'] ) ) {
			$appointment_id = $cart_item['appointment']['_appointment_id'];
			$appointment    = get_wc_appointment( $appointment_id );
			if ( $appointment && $appointment->has_status( 'was-in-cart' ) ) {
				$appointment->update_status( 'in-cart' );
				WC_Cache_Helper::get_transient_version( 'appointments', true );
				$this->schedule_cart_removal( $appointment_id );

				$message = sprintf( 'Appointment ID: %s was restored to cart', $appointment->get_id() );
				wc_add_appointment_log( $this->id, $message );
			}
		}
	}

	/**
	 * Schedule appointment to be deleted if inactive.
	 */
	public function schedule_cart_removal( $appointment_id ) {
		$hold_stock_minutes = (int) get_option( 'woocommerce_hold_stock_minutes', 60 );
		$minutes            = apply_filters( 'woocommerce_appointments_remove_inactive_cart_time', $hold_stock_minutes );

		/**
		 * If this has been emptied, or set to 0, it will just exit. This means that in-cart appointments will need to be manually removed.
		 * Also take note that if the $minutes var is set to 5 or less, this means that it is possible for the in-cart appointment to be
		 * removed before the customer is able to check out.
		 */
		if ( empty( $minutes ) ) {
			return;
		}

		$timestamp = time() + MINUTE_IN_SECONDS * (int) $minutes;

		as_schedule_single_action( $timestamp, 'wc-appointment-remove-inactive-cart', [ $appointment_id ], 'wca' );
	}

	/**
	 * Check for invalid appointments
	 */
	public function cart_loaded_from_session() {
		$titles       = [];
		$count_titles = 0;

		foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
			if ( isset( $cart_item['appointment'] ) ) {
				// If the appointment is gone, remove from cart!
				$appointment_id = $cart_item['appointment']['_appointment_id'];
				$appointment    = get_wc_appointment( $appointment_id );

				if ( ! $appointment || ! $appointment->has_status( array( 'was-in-cart', 'in-cart', 'unpaid', 'paid', 'pending-confirmation' ) ) ) {
					unset( WC()->cart->cart_contents[ $cart_item_key ] );

					WC()->cart->calculate_totals();

					if ( $cart_item['product_id'] ) {
						$title = '<a href="' . get_permalink( $cart_item['product_id'] ) . '">' . get_the_title( $cart_item['product_id'] ) . '</a>';
						$count_titles++;
						if ( ! in_array( $title, $titles, true ) ) {
							$titles[] = $title;
						}
					}
				}
			}
		}

		if ( $count_titles < 1 ) {
			return;
		}
		$formatted_titles = wc_format_list_of_items( $titles );
		/* translators: Admin notice with title and link to bookable product removed from cart. */
		$notice = sprintf( __( 'An appointment for %s has been removed from your cart due to inactivity.', 'woocommerce-appointments' ), $formatted_titles );

		if ( $count_titles > 1 ) {
			/* translators: Admin notice with list of titles and links to bookable products removed from cart. */
			$notice = sprintf( __( 'Appointments for %s have been removed from your cart due to inactivity.', 'woocommerce-appointments' ), $formatted_titles );
		}

		wc_add_notice( $notice, 'notice' );
	}

	/**
	 * Add posted data to the cart item
	 *
	 * @param mixed $cart_item_meta
	 * @param mixed $product_id
	 * @return array $cart_item_meta
	 */
	public function add_cart_item_data( $cart_item_meta, $product_id ) {
		$product = wc_get_product( $product_id );

		if ( ! is_wc_appointment_product( $product ) ) {
			return $cart_item_meta;
		}

		if ( ! key_exists( 'appointment', $cart_item_meta ) ) {
			$cart_item_meta['appointment'] = wc_appointments_get_posted_data( $_POST, $product );
		}
		$cart_item_meta['appointment']['_cost'] = WC_Appointments_Cost_Calculation::calculate_appointment_cost( $_POST, $product );

		if ( $cart_item_meta['appointment']['_cost'] instanceof WP_Error ) {
			throw new Exception( esc_html( $cart_item_meta['appointment']['_cost']->get_error_message() ) );
		}

		// Create the new appointment
		$new_appointment = $this->add_appointment_from_cart_data( $cart_item_meta, $product_id );

		// Store in cart
		$cart_item_meta['appointment']['_appointment_id'] = $new_appointment->get_id();

		// Schedule this item to be removed from the cart if the user is inactive
		$this->schedule_cart_removal( $new_appointment->get_id() );

		return $cart_item_meta;
	}

	/**
	 * Create appointment from cart data
	 *
	 * @param        $cart_item_meta
	 * @param        $product_id
	 * @param string $status
	 *
	 * @return object
	 */
	private function add_appointment_from_cart_data( $cart_item_meta, $product_id, $status = 'in-cart' ) {
		// Create the new appointment
		$new_appointment_data = array(
			'product_id'     => $product_id, // Appointment ID
			'cost'           => $cart_item_meta['appointment']['_cost'], // Cost of this appointment
			'start_date'     => $cart_item_meta['appointment']['_start_date'],
			'end_date'       => $cart_item_meta['appointment']['_end_date'],
			'all_day'        => $cart_item_meta['appointment']['_all_day'],
			'qty'            => $cart_item_meta['appointment']['_qty'],
			'timezone'       => $cart_item_meta['appointment']['_timezone'],
			'local_timezone' => $cart_item_meta['appointment']['_local_timezone'],
		);

		// Check if the appointment has staff
		if ( isset( $cart_item_meta['appointment']['_staff_id'] ) ) {
			$new_appointment_data['staff_id'] = $cart_item_meta['appointment']['_staff_id']; // ID of the staff
		}

		// Pass all staff selected
		if ( isset( $cart_item_meta['appointment']['_staff_ids'] ) ) {
			$new_appointment_data['staff_ids'] = $cart_item_meta['appointment']['_staff_ids']; // IDs of the staff
		}

		$new_appointment = get_wc_appointment( $new_appointment_data );
		$new_appointment->create( $status );

		return $new_appointment;
	}

	/**
	 * Put meta data into format which can be displayed
	 *
	 * @param mixed $other_data
	 * @param mixed $cart_item
	 * @return array meta
	 */
	public function get_item_data( $other_data, $cart_item ) {
		if ( empty( $cart_item['appointment'] ) ) {
			return $other_data;
		}

		if ( ! empty( $cart_item['appointment']['_appointment_id'] ) ) {
			$appointment = get_wc_appointment( $cart_item['appointment']['_appointment_id'] );
		}

		if ( ! empty( $cart_item['appointment'] ) ) {
			foreach ( $cart_item['appointment'] as $key => $value ) {
				if ( substr( $key, 0, 1 ) !== '_' ) {
					$other_data[] = array(
						'name'    => get_wc_appointment_data_label( $key, $cart_item['data'] ),
						'value'   => $value,
						'display' => '',
					);
				}
			}
		}

		return $other_data;
	}

	/**
	 * order_item_meta function.
	 *
	 * @param mixed $item_id
	 * @param mixed $values
	 */
	public function order_item_meta( $item_id, $values ) {
		$appointment_cost = 0;

		if ( ! empty( $values['appointment'] ) ) {
			$product          = $values['data'];
			$appointment_id   = $values['appointment']['_appointment_id'];
			$appointment_cost = (float) $values['appointment']['_cost'];
		}

		if ( ! isset( $appointment_id ) && property_exists( $values, 'legacy_values' ) && ! empty( $values->legacy_values ) && is_array( $values->legacy_values ) && ! empty( $values->legacy_values['appointment'] ) ) {
			$product          = $values->legacy_values['data'];
			$appointment_id   = $values->legacy_values['appointment']['_appointment_id'];
			$appointment_cost = (float) $values->legacy_values['appointment']['_cost'];
		}

		if ( isset( $appointment_id ) ) {
			$appointment = get_wc_appointment( $appointment_id );

			if ( function_exists( 'wc_get_order_id_by_order_item_id' ) ) {
				$order_id = wc_get_order_id_by_order_item_id( $item_id );
			} else {
				global $wpdb;
				$order_id = (int) $wpdb->get_var(
					$wpdb->prepare(
						"SELECT order_id FROM {$wpdb->prefix}woocommerce_order_items WHERE order_item_id = %d",
						$item_id
					)
				);
			}

			$order        = wc_get_order( $order_id );
			$order_status = $order->get_status();

			// Testing.
			#error_log( var_export( 'Appointment ID', true ) );
			#error_log( var_export( $appointment_id, true ) );
			#error_log( var_export( 'Appointment Cost', true ) );
			#error_log( var_export( $appointment_cost, true ) );
			#error_log( var_export( 'Appointment Status', true ) );
			#error_log( var_export( $appointment->get_status(), true ) );
			#error_log( var_export( 'Order Status', true ) );
			#error_log( var_export( $order_status, true ) );

			$appointment->set_order_id( $order_id );
			$appointment->set_order_item_id( $item_id );

			/**
			 * In this particular case, the status will be 'in-cart' as we don't want to change it
			 * before the actual order is done if we're dealing with the checkout blocks.
			 * The checkout block creates a draft order before it is then changes to another more final status.
			 * Later the woocommerce_blocks_checkout_order_processed hook is called and
			 * review_items_on_checkout runs to change the status of the appointment to their correct value.
			 */
			if ( 'checkout-draft' === $order_status ) {
				$appointment->set_status( 'in-cart' );
			}

			// Save everything.
			$appointment->save();
		}
	}

	/**
	 * Redirects directly to the cart the products they need confirmation.
	 *
	 * @since 1.0.0
	 * @version 3.4.0
	 *
	 * @param string $url URL.
	 */
	public function add_to_cart_redirect( $url ) {
		if ( isset( $_REQUEST['add-to-cart'] ) && is_numeric( $_REQUEST['add-to-cart'] ) && wc_appointment_requires_confirmation( intval( $_REQUEST['add-to-cart'] ) ) ) {
			// Remove add to cart messages only in case there's no error.
			$notices = wc_get_notices();
			if ( empty( $notices['error'] ) ) {
				wc_clear_notices();

				// Go to checkout.
				return wc_get_cart_url();
			}
		}

		return $url;
	}

	/**
	 * Add querystring to product link.
	 *
	 * @since 3.4.0
	 * @version 3.4.0
	 *
	 * @param string $url URL.
	 * @param object $product.
	 */
	public function woocommerce_product_link_querystring( $permalink, $product ) {
		if ( ! is_wc_appointment_product( $product ) ) {
			return $permalink;
		}

		// Querystrings exist?
		$date  = isset( $_GET['min_date'] ) ? wc_clean( wp_unslash( $_GET['min_date'] ) ) : ''; // WPCS: input var ok, CSRF ok.
		$time  = isset( $_GET['time'] ) ? wc_clean( wp_unslash( $_GET['time'] ) ) : ''; // WPCS: input var ok, CSRF ok.
		$staff = isset( $_GET['staff'] ) ? wc_clean( wp_unslash( $_GET['staff'] ) ) : ''; // WPCS: input var ok, CSRF ok.

		if ( $date ) {
			$permalink = add_query_arg( 'date', $date, $permalink );
		}
		if ( $time ) {
			$permalink = add_query_arg( 'time', $time, $permalink );
		}
		if ( $staff ) {
			$permalink = add_query_arg( 'staff', $staff, $permalink );
		}

		return apply_filters( 'woocommerce_appointment_get_permalink', $permalink, $this );
	}

	/**
	 * Remove all appointments that require confirmation.
	 *
	 * @return void
	 */
	protected function remove_appointment_that_requires_confirmation() {
		foreach ( WC()->cart->cart_contents as $item_key => $item ) {
			if ( wc_appointment_requires_confirmation( $item['product_id'] ) ) {
				WC()->cart->set_quantity( $item_key, 0 );
			}
		}
	}

	/**
	 * Goes through all the appointments after the order is submitted via a checkout block to update their statuses.
	 *
	 * @param WC_Order $order The order represented.
	 */
	public function review_items_on_block_checkout( $order ) {
		$order_id = $order->get_id();

		if ( empty( $order_id ) ) {
			return;
		}

		$order        = wc_get_order( $order_id );
		$order_status = $order->get_status();

		$appointments = WC_Appointment_Data_Store::get_appointment_ids_from_order_id( $order_id );

		foreach ( $appointments as $appointment_id ) {
			$appointment = get_wc_appointment( $appointment_id );
			$product_id  = $appointment->get_product_id();
			if ( empty( $product_id ) ) {
				continue;
			}

			/**
			 * We just want to deal with the appointments that we left forcibly on the 'in-cart' state
			 * and provide them the same state they would be if not using blocks.
			 */
			if ( ! wc_appointment_requires_confirmation( $product_id ) && ! in_array( $order_status, array( 'processing', 'completed' ), true ) ) {
				/**
				 * We need to bring the appointment status from the new in-cart status to unpaid if it doesn't require confirmation
				 */
				$appointment->set_status( 'unpaid' );
				$appointment->save();
			} elseif ( 'in-cart' === $appointment->get_status() && wc_appointment_requires_confirmation( $product_id ) ) {
				/**
				 * If the order is in cart and requires confirmation, we need to change this.
				 */
				$appointment->set_status( 'pending-confirmation' );
				$appointment->save();
			}
		}
	}

	/**
	 * Makes sure we change the appointment statuses to account for the new order statuses created by WooCommerce Blocks
	 * and also account for products that might have the new in-cart status.
	 *
	 * @param mixed $order_id The order represented.
	 */
	public function review_items_on_shortcode_checkout( $order_id ) {
		if ( empty( $order_id ) ) {
			return;
		}

		/**
		 * We need to make sure we don't do anything to the appointment just yet because of the new checkout-draft status
		 * assigned by the checkout block when entering the checkout page.
		 */
		$order        = wc_get_order( $order_id );
		$order_status = $order->get_status();

		if ( 'checkout-draft' === $order_status ) {
			return;
		}

		$appointments = WC_Appointment_Data_Store::get_appointment_ids_from_order_id( $order_id );

		foreach ( $appointments as $appointment_id ) {
			$appointment = get_wc_appointment( $appointment_id );
			$product_id  = $appointment->get_product_id();

			if ( empty( $product_id ) ) {
				continue;
			}
			if ( ! wc_appointment_requires_confirmation( $product_id ) && ! in_array( $order_status, array( 'processing', 'completed' ), true ) ) {
				/**
				 * We need to bring the appointment status from the new in-cart status to unpaid if it doesn't require confirmation
				 */
				$appointment->set_status( 'unpaid' );
				$appointment->save();
			} elseif ( 'in-cart' === $appointment->get_status() && wc_appointment_requires_confirmation( $product_id ) ) {
				/**
				 * If the order is in cart and requires confirmation, we need to change this.
				 */
				$appointment->set_status( 'pending-confirmation' );
				$appointment->save();
			}
		}
	}

	/**
	 * When an appointment is added to the cart, validate it.
	 *
	 * @param mixed $passed
	 * @param int   $product_id
	 * @param mixed $qty
	 *
	 * @return bool
	 */
	public function validate_appointment_posted_data( $passed, $product_id, $qty ) {
		$product = wc_get_product( $product_id );

		if ( ! is_wc_appointment_product( $product ) ) {
			return $passed;
		}

		$data     = wc_appointments_get_posted_data( $_POST, $product );
		$validate = $product->is_appointable( $data );

		if ( is_wp_error( $validate ) ) {
			wc_add_notice( $validate->get_error_message(), 'error' );
			return false;
		}

		return $passed;
	}

	/**
	 * Removes all products when cart have an appointment which requires confirmation
	 *
	 * @param  bool $passed
	 * @param  int  $product_id
	 *
	 * @return bool
	 */
	public function validate_appointment_requires_confirmation( $passed, $product_id ) {
		if ( wc_appointment_requires_confirmation( $product_id ) ) {
			$items = WC()->cart->get_cart();

			foreach ( $items as $item_key => $item ) {
				if ( ! isset( $item['appointment'] ) || ! wc_appointment_requires_confirmation( $item['product_id'] ) ) {
					WC()->cart->remove_cart_item( $item_key );
					// Item qty.
					$item_qty = $item['quantity'] > 1 ? absint( $item['quantity'] ) . ' &times; ' : '';
					// Item name.
					$item_name = apply_filters(
						'woocommerce_add_to_cart_item_name_in_quotes',
						sprintf(
							'&ldquo;%s&rdquo;',
							wp_strip_all_tags( get_the_title( $item['product_id'] ) )
						),
						$item['product_id']
					);
					// Add notice.
					wc_add_notice(
						sprintf(
							/* translators: %s: product name */
							__( '%s has been removed from your cart. It is not possible to complete the purchase along with an appointment that requires confirmation.', 'woocommerce-appointments' ),
							$item_qty . $item_name
						),
						'notice'
					);
				}
			}
		} elseif ( wc_appointment_cart_requires_confirmation() ) {
			// Remove appointment that requires confirmation.
			$this->remove_appointment_that_requires_confirmation();
			// Add notice.
			wc_add_notice( __( 'An appointment that requires confirmation has been removed from your cart. It is not possible to complete the purchase along with an appointment that doesn\'t require confirmation.', 'woocommerce-appointments' ), 'notice' );
		}

		return $passed;
	}

	/**
	 * Disable quantity field editing for appointments in cart block
	 *
	 * // ... 'woocommerce_store_api_product_quantity_.' $this->filter_value() = 'editable'
	 *
	 * @param  bool  $value
	 * @param  mixed $product
	 * @param  mixed $cart_item
	 *
	 * @return bool
	 */
	public function disable_cart_block_qty_field( $value, $product, $cart_item ) {
		#error_log( var_export( $value, true ) );
		#error_log( var_export( $product, true ) );
		#error_log( var_export( $cart_item, true ) );

		// Disable quantity field editing for appointments.
		if ( is_wc_appointment_product( $product ) ) {
			return false;
		}

		return $value;
	}

	/**
	 * Removes all products when cart have an appointment which requires confirmation
	 *
	 * @param  bool $passed
	 * @param  int  $product_id
	 *
	 * @return bool
	 */
	public function validate_appointment_sold_individually( $passed, $product_id, $qty ) {
		if ( wc_appointment_sold_individually( $product_id ) ) {
			$product = wc_get_product( $product_id );
			$items   = WC()->cart->get_cart();

			foreach ( $items as $item_key => $item ) {
				// Ptroduct already in cart. Stop here.
				if ( $item['product_id'] === $product_id ) {
					// Product name.
					$product_name = apply_filters(
						'woocommerce_add_to_cart_item_name_in_quotes',
						sprintf(
							'&ldquo;%s&rdquo;',
							wp_strip_all_tags( get_the_title( $item['product_id'] ) )
						),
						$product->get_name()
					);
					// Add notice.
					$message = sprintf(
						/* translators: %s: product name */
						__( 'You cannot add another %s to your cart.', 'woocommerce-appointments' ),
						$product_name
					);
					$wp_button_class = wc_wp_theme_get_element_class_name( 'button' ) ? ' ' . wc_wp_theme_get_element_class_name( 'button' ) : '';
					wc_add_notice(
						sprintf(
							'<a href="%s" class="button wc-forward%s">%s</a> %s',
							wc_get_cart_url(),
							esc_attr( $wp_button_class ),
							__( 'View cart', 'woocommerce-appointments' ),
							$message
						),
						'error'
					);

					$passed = false;
				}
			}
		}

		return $passed;
	}

	/**
	 * Should validate appointment product order for appointment for checkout block.
	 *
	 * @since 1.15.76
	 *
	 * @param WP_Error $errors
	 * @param WC_Cart  $cart
	 *
	 * @return void
	 */
	public function validate_appointment_order_checkout_block_support( \WP_Error $errors, \WC_Cart $cart ) {
		// Existing checkout validation if rest api request generates from gutenberg editor.
		if ( is_admin() ) {
			return;
		}

		$this->validate_appointment_order( $errors, $cart );
	}

	/**
	 * Should validate appointment product order for appointment for legacy checkout.
	 *
	 * @since 1.15.76
	 *
	 * @param WP_Error $errors
	 * @param WC_Cart  $cart
	 *
	 * @return void
	 */
	public function validate_appointment_order_legacy_checkout( array $data, \WP_Error $errors ) {
		$this->validate_appointment_order( $errors, WC()->cart );
	}

	/**
	 * Should validate appointment order items.
	 *
	 * Booking availability validates without in-cart appointments.
	 *
	 * @since 1.15.76
	 *
	 * @return void
	 */
	public function validate_appointment_order( \WP_Error $errors, \WC_Cart $cart ) {
		// Do not need to validate if cart is empty.
		if ( $cart->is_empty() ) {
			return;
		}

		$cart_items                             = $cart->get_cart();
		$temporary_confirmed_order_appointments = [];

		$appointment_errors = [];

		foreach ( $cart_items as $product_data ) {
			/* @var WC_Product_Booking $product */
			$product = $product_data['data'];

			if ( ! is_wc_appointment_product( $product ) ) {
				continue;
			}

			$appointment = new WC_Appointment( $product_data['appointment']['_appointment_id'] );

			// Unique key to store temporary confirmed appointments in array.
			// Each appointment has following unique key: appointment_id + staff_id + start_date + end_date.
			$temporary_confirmed_checkout_appointments_array_key = "{$appointment->get_product_id()}_{$appointment->get_staff_ids()}_{$appointment->get_start()}_{$appointment->get_end()}";

			if ( array_key_exists( $temporary_confirmed_checkout_appointments_array_key, $temporary_confirmed_order_appointments ) ) {
				$product->confirmed_order_appointments[] = $temporary_confirmed_order_appointments[ $temporary_confirmed_checkout_appointments_array_key ];
			}

			$product->check_in_cart = false;
			$validate               = $product->is_appointable( $product_data['appointment'] );

			if ( is_wp_error( $validate ) ) {
				/* translators: 1: Booking product name */
				$appointment_errors[ "appointment-order-item-error-{$appointment->get_product_id()}" ] = sprintf(
					esc_html__(
						'Sorry, the selected block is no longer available for %1$s. Please choose another block.',
						'woocommerce-appointments'
					),
					$product->get_name()
				);
			}

			// Flag appointment as temporary confirmed for availability check.
			$temporary_confirmed_order_appointments[ $temporary_confirmed_checkout_appointments_array_key ] = $appointment;
		}

		// Add appointment checkout errors.
		if ( ! empty( $appointment_errors ) ) {
			foreach ( $appointment_errors as $error_code => $error_message ) {
				$errors->add( $error_code, $error_message );
			}
		}
	}
}

$GLOBALS['wc_appointment_cart_manager'] = new WC_Appointment_Cart_Manager();
