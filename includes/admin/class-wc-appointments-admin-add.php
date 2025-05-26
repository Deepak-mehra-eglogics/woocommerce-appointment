<?php
/**
 * Create new appointments page
 */
class WC_Appointments_Admin_Add {

	/**
	 * Stores errors.
	 *
	 * @var array
	 */
	private $errors = [];

	/**
	 * Get billing fields for the meta box.
	 *
	 * @param \WC_Order $order Order object.
	 * @param string    $context Context of fields (view or edit).
	 * @return array
	 */
	public static function get_billing_fields( $order = false, $context = 'view' ) {

		/**
		 * Provides an opportunity to modify the list of order billing fields displayed on the admin.
		 *
		 * @since WC 1.4.0
		 *
		 * @param array Billing fields.
		 * @param WC_Order|false $order Order object.
		 * @param string $context Context of fields (view or edit).
		 */
		return apply_filters(
			'woocommerce_admin_billing_fields',
			[
				'first_name' => [
					'label' => __( 'First name', 'woocommerce-appointments' ),
					'show'  => false,
				],
				'last_name'  => [
					'label' => __( 'Last name', 'woocommerce-appointments' ),
					'show'  => false,
				],
				'company'    => [
					'label' => __( 'Company', 'woocommerce-appointments' ),
					'show'  => false,
				],
				'address_1'  => [
					'label' => __( 'Address line 1', 'woocommerce-appointments' ),
					'show'  => false,
				],
				'address_2'  => [
					'label' => __( 'Address line 2', 'woocommerce-appointments' ),
					'show'  => false,
				],
				'city'       => [
					'label' => __( 'City', 'woocommerce-appointments' ),
					'show'  => false,
				],
				'postcode'   => [
					'label' => __( 'Postcode / ZIP', 'woocommerce-appointments' ),
					'show'  => false,
				],
				'country'    => [
					'label'   => __( 'Country / Region', 'woocommerce-appointments' ),
					'show'    => false,
					'class'   => 'js_field-country select short',
					'type'    => 'select',
					'options' => [
						'' => __( 'Select a country / region&hellip;', 'woocommerce-appointments' ),
					] + WC()->countries->get_allowed_countries(),
				],
				'state'      => [
					'label' => __( 'State / County', 'woocommerce-appointments' ),
					'class' => 'js_field-state select short',
					'show'  => false,
				],
				'email'      => [
					'label' => __( 'Email address', 'woocommerce-appointments' ),
				],
				'phone'      => [
					'label' => __( 'Phone', 'woocommerce-appointments' ),
				],
			],
			$order,
			$context
		);

	}

	/**
	 * Output the form
	 *
	 * @version  3.3.0
	 */
	public function output() {
		global $thepostid;

		$this->errors = [];
		$step         = 1;
		$thepostid    = -99; #negative ID, to avoid clash with a valid post.

		try {
			if ( ! empty( $_POST ) && ! check_admin_referer( 'add_appointment_notification' ) ) {
				throw new Exception( __( 'Error - please try again', 'woocommerce-appointments' ) );
			}

			if ( ! empty( $_POST['add_appointment'] ) ) {
				$customer_id            = ! empty( $_POST['customer_id'] ) ? absint( $_POST['customer_id'] ) : '';
				$appointable_product_id = absint( $_POST['appointable_product_id'] );
				$appointment_order      = wc_clean( $_POST['appointment_order'] );

				// Billing fields.
				$_billing_first_name = wc_clean( $_POST['_billing_first_name'] );
				$_billing_last_name  = wc_clean( $_POST['_billing_last_name'] );
				$_billing_company    = wc_clean( $_POST['_billing_company'] );
				$_billing_address_1  = wc_clean( $_POST['_billing_address_1'] );
				$_billing_address_2  = wc_clean( $_POST['_billing_address_2'] );
				$_billing_city       = wc_clean( $_POST['_billing_city'] );
				$_billing_postcode   = wc_clean( $_POST['_billing_postcode'] );
				$_billing_country    = wc_clean( $_POST['_billing_country'] );
				$_billing_state      = wc_clean( $_POST['_billing_state'] );
				$_billing_email      = wc_clean( $_POST['_billing_email'] );
				$_billing_phone      = wc_clean( $_POST['_billing_phone'] );
				$_payment_method     = wc_clean( $_POST['_payment_method'] );
				$_transaction_id     = wc_clean( $_POST['_transaction_id'] );

				if ( ! $appointable_product_id ) {
					throw new Exception( __( 'Please choose an appointable product', 'woocommerce-appointments' ) );
				}

				if ( 'existing' === $appointment_order ) {
					if ( class_exists( 'WC_Seq_Order_Number_Pro' ) ) {
						$order_id = WC_Seq_Order_Number_Pro::find_order_by_order_number( wc_clean( $_POST['appointment_order_id'] ) );
					} else {
						$order_id = absint( $_POST['appointment_order_id'] );
					}

					$appointment_order = $order_id;

					if ( ! $appointment_order || ! WC_Appointment_Order_Compat::is_shop_order( $appointment_order ) ) {
						throw new Exception( __( 'Invalid order ID provided', 'woocommerce-appointments' ) );
					}
				}

				$product          = get_wc_product_appointment( $appointable_product_id );
				$appointment_form = new WC_Appointment_Form( $product );
				$step++;

			} elseif ( ! empty( $_POST['add_appointment_2'] ) ) {
				$customer_id            = ! empty( $_POST['customer_id'] ) ? absint( $_POST['customer_id'] ) : '';
				$appointable_product_id = absint( $_POST['appointable_product_id'] );
				$appointment_order      = wc_clean( $_POST['appointment_order'] );
				$product                = get_wc_product_appointment( $appointable_product_id );
				$appointment_form       = new WC_Appointment_Form( $product );
				$appointment_data       = wc_appointments_get_posted_data( $_POST, $product );
				$cost                   = WC_Appointments_Cost_Calculation::calculate_appointment_cost( $_POST, $product );
				$appointment_cost       = $cost && ! is_wp_error( $cost ) ? number_format( $cost, 2, '.', '' ) : 0;
				$order_total            = $appointment_cost;
				$create_order           = false;
				$order_id               = 0;
				$item_id                = 0;
				$validate               = $product->is_appointable( $appointment_data );

				if ( is_wp_error( $validate ) ) {
					throw new Exception( __( $validate->get_error_message(), 'woocommerce-appointments' ) );
				}

				// Billing fields.
				$_billing_first_name = wc_clean( $_POST['_billing_first_name'] );
				$_billing_last_name  = wc_clean( $_POST['_billing_last_name'] );
				$_billing_company    = wc_clean( $_POST['_billing_company'] );
				$_billing_address_1  = wc_clean( $_POST['_billing_address_1'] );
				$_billing_address_2  = wc_clean( $_POST['_billing_address_2'] );
				$_billing_city       = wc_clean( $_POST['_billing_city'] );
				$_billing_postcode   = wc_clean( $_POST['_billing_postcode'] );
				$_billing_country    = wc_clean( $_POST['_billing_country'] );
				$_billing_state      = wc_clean( $_POST['_billing_state'] );
				$_billing_email      = wc_clean( $_POST['_billing_email'] );
				$_billing_phone      = wc_clean( $_POST['_billing_phone'] );
				$_payment_method     = wc_clean( $_POST['_payment_method'] );
				$_transaction_id     = wc_clean( $_POST['_transaction_id'] );

				// Adjust the order total, so it is calculated correctly.
				// Leave appointment cost the same.
				if ( wc_prices_include_tax() ) {
					$base_tax_rates = WC_Tax::get_base_tax_rates( $product->get_tax_class() );
					$base_taxes     = WC_Tax::calc_tax( $appointment_cost, $base_tax_rates, true );
					$order_total    = $appointment_cost - array_sum( $base_taxes );
				}

				// Data to go into the appointment
				$staff_id             = $appointment_data['_staff_id'] ?? '';
				$new_appointment_data = [
					'customer_id' => $customer_id,
					'product_id'  => $product->get_id(),
					'staff_ids'   => $appointment_data['_staff_ids'] ?? $staff_id,
					'cost'        => $appointment_cost,
					'start'       => $appointment_data['_start_date'],
					'end'         => $appointment_data['_end_date'],
					'all_day'     => $appointment_data['_all_day'] ? 1 : 0,
					'qty'         => $appointment_data['_qty'] ? $appointment_data['_qty'] : 1,
					'timezone'    => $appointment_data['_timezone'] ? $appointment_data['_timezone'] : '',
				];

				// Create order
				if ( 'new' === $appointment_order ) {
					$create_order = true;

					#error_log( var_export( $_POST, true ) );

					// Set props.
					$props = [];

					// Billing fields.
					$billing_fields = self::get_billing_fields();

					if ( ! empty( $billing_fields ) ) {
						foreach ( $billing_fields as $key => $field ) {
							if ( ! isset( $field['id'] ) ) {
								$field['id'] = '_billing_' . $key;
							}

							if ( ! isset( $_POST[ $field['id'] ] ) ) {
								continue;
							}

							$props[ 'billing_' . $key ] = wc_clean( wp_unslash( $_POST[ $field['id'] ] ) );
						}
					}

					// Transation ID.
					if ( isset( $_POST['_transaction_id'] ) ) {
						$props['transaction_id'] = wc_clean( wp_unslash( $_POST['_transaction_id'] ) );
					}

					// Payment method handling.
					if ( isset( $_POST['_payment_method'] ) ) {
						$methods              = WC()->payment_gateways->payment_gateways();
						$payment_method       = wc_clean( wp_unslash( $_POST['_payment_method'] ) );
						$payment_method_title = $payment_method;

						if ( isset( $methods ) && isset( $methods[ $payment_method ] ) ) {
							$payment_method_title = $methods[ $payment_method ]->get_title();
						}

						$props['payment_method']       = $payment_method;
						$props['payment_method_title'] = $payment_method_title;
					}

					// Create order.
					$order_id = $this->create_order( $order_total, $customer_id, $props );

					if ( ! $order_id ) {
						throw new Exception( __( 'Error: Could not create order', 'woocommerce-appointments' ) );
					}
				} elseif ( $appointment_order > 0 ) {
					if ( class_exists( 'WC_Seq_Order_Number_Pro' ) ) {
						$order_id = WC_Seq_Order_Number_Pro::find_order_by_order_number( $appointment_order );
					} else {
						$order_id = absint( $appointment_order );
					}

					if ( ! $order_id || ! WC_Appointment_Order_Compat::is_shop_order( $order_id ) ) {
						throw new Exception( __( 'Invalid order ID provided', 'woocommerce-appointments' ) );
					}

					$order = wc_get_order( $order_id );
					$order->set_total( $order->get_total( 'edit' ) + $order_total );
					$order->save();
				}

				if ( $order_id ) {
		           	if ( ! empty( $customer_id ) ) {
						// set order address.
						$order = wc_get_order( $order_id );
						$keys  = [
							'first_name',
							'last_name',
							'company',
							'address_1',
							'address_2',
							'phone',
							'city',
							'state',
							'postcode',
							'country',
						];

						// Set address.
						$types = [ 'shipping', 'billing' ];
						foreach ( $types as $type ) {
							foreach ( $keys as $key ) {
								// Skip is set inside billing.
								$field_id = '_billing_' . $key;
								if ( isset( $_POST[ $field_id ] ) && ! empty( $_POST[ $field_id ] ) ) {
									continue;
								}

								$value = (string) get_user_meta( $customer_id, $type . '_' . $key, true );
								$order->update_meta_data( '_' . $type . '_' . $key, $value );
							}
						}
						$order->save();
					}

					$item_id = wc_add_order_item(
						$order_id,
						[
					 		'order_item_name' => $product->get_title(),
					 		'order_item_type' => 'line_item',
					 	]
					);

				 	if ( ! $item_id ) {
						throw new Exception( __( 'Error: Could not create item', 'woocommerce-appointments' ) );
				 	}

				 	// Add line item meta.
				 	wc_update_order_item_meta( $item_id, '_qty', $appointment_data['_qty'] ? $appointment_data['_qty'] : 1 );
				 	wc_update_order_item_meta( $item_id, '_tax_class', $product->get_tax_class() );
				 	wc_update_order_item_meta( $item_id, '_product_id', $product->get_id() );
				 	wc_update_order_item_meta( $item_id, '_variation_id', '' );
				 	wc_update_order_item_meta( $item_id, '_line_subtotal', $order_total );
				 	wc_update_order_item_meta( $item_id, '_line_total', $order_total );
				 	wc_update_order_item_meta( $item_id, '_line_tax', 0 );
				 	wc_update_order_item_meta( $item_id, '_line_subtotal_tax', 0 );

					do_action( 'woocommerce_appointments_add_appointment_page_add_order_item', $order_id, $item_id, $product );
				}

				// Calculate the order taxes.
				$order = wc_get_order( $order_id );
				if ( is_a( $order, 'WC_Order' ) ) {
					$order->calculate_totals( wc_tax_enabled() );
				}

				// New Appointment arguments.
				$new_appointment_args = apply_filters(
					'woocommerce_appointments_create_appointment_args',
					[
						'order_id'     => $order_id,
						'create_order' => $create_order,
						'item_id'      => $item_id,
						'status'       => $create_order && $appointment_cost ? 'unpaid' : 'confirmed',
					],
					$new_appointment_data
				);

				// Create the appointment itself.
				$new_appointment = get_wc_appointment( $new_appointment_data );
				$new_appointment->set_order_id( $new_appointment_args['order_id'] );
				$new_appointment->set_order_item_id( $new_appointment_args['item_id'] );
				$new_appointment->set_status( $new_appointment_args['status'] );
				$new_appointment->save();

				// Schedule notifications.
				$new_appointment->maybe_schedule_event( 'reminder' );
				$new_appointment->maybe_schedule_event( 'complete' );

				do_action( 'woocommerce_appointment_added_manual_appointment', $new_appointment );

				// Make sure unpaid status is hooked the same way
				// as for the front-end.
				if ( 'unpaid' === $new_appointment_args['status'] ) {
					do_action( 'woocommerce_appointment_' . $new_appointment_args['status'], $new_appointment->get_id(), $new_appointment );
				}

				$redirect_url = $create_order
					? $order->get_edit_order_url()
					: admin_url( 'post.php?post=' . $new_appointment->get_id() . '&action=edit' );

				wp_safe_redirect( $redirect_url );
				exit;

			}
		} catch ( Exception $e ) {
			$this->errors[] = $e->getMessage();
		}

		switch ( $step ) {
			case 1:
				include 'views/html-add-appointment-page.php';
				break;
			case 2:
				add_filter( 'wc_get_template', [ $this, 'use_default_form_template' ], 10, 5 );
				include 'views/html-add-appointment-page-2.php';
				remove_filter( 'wc_get_template', [ $this, 'use_default_form_template' ], 10 );
				break;
		}
	}

	/**
	 * Create order
	 *
	 * @param  float $total
	 * @param  int $customer_id
	 * @return int
	 */
	public function create_order( $total, $customer_id, $props ) {
		$order = new WC_Order();

		// Props.
		if ( $props ) {
			$order->set_props( $props );
		}

		// Customer ID.
		$order->set_customer_id( $customer_id );

		// Total.
		$order->set_total( $total );

		// Created via.
		$order->set_created_via( 'appointments' );

		// Save.
		$order_id = $order->save();

		do_action( 'woocommerce_new_appointment_order', $order_id );

		return $order_id;
	}

	/**
	 * Output any errors
	 */
	public function show_errors() {
		foreach ( $this->errors as $error ) {
			echo '<div class="error"><p>' . esc_html( $error ) . '</p></div>';
		}
	}

	/**
	 * Use default template form from the extension.
	 *
	 * This prevents any overridden template via theme being used in
	 * add appointment screen.
	 *
	 * @since 2.1.4
	 */
	public function use_default_form_template( $located, $template_name, $args, $template_path, $default_path ) {
		if ( 'woocommerce-appointments' === $template_path ) {
			$located = $default_path . $template_name;
		}

		return $located;
	}
}
