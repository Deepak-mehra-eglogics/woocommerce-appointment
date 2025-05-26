<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce Add-ons integration class.
 */
class WC_Appointments_Integration_Addons {

	/**
	 * Stores addon_class if available.
	 *
	 * @var array
	 */
	public $addon_class = false;

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'includes' ), 5 );
		add_action( 'admin_init', array( $this, 'disable_addons' ));
		add_action( 'init', array( $this, 'reorder_addon_fields' ), 20 );
		add_filter( 'woocommerce_product_addons_show_grand_total', array( $this, 'addons_show_grand_total' ), 20, 2 ); #hide addons grand total
		add_action( 'woocommerce_product_addons_panel_before_options', array( $this, 'addon_options' ), 20, 3 );
		add_action( 'woocommerce_product_addons_panel_option_heading', array( $this, 'addon_option_head' ), 10, 3 );
		add_action( 'woocommerce_product_addons_panel_option_row', array( $this, 'addon_option_body' ), 10, 4 );
		add_filter( 'woocommerce_product_addons_save_data', array( $this, 'save_addon_options' ), 20, 2 );
		add_action( 'woocommerce_appointments_add_appointment_page_add_order_item', array( $this, 'save_addon_options_in_admin' ), 10, 3 );
		#add_filter( 'woocommerce_appointments_calculated_appointment_cost_success_output', array( $this, 'filter_output_cost' ), 10, 3 );
		add_filter( 'woocommerce_product_addons_adjust_price', array( $this, 'adjust_price' ), 20, 2 ); #addons cost is added here, so don't add it through addons cart
		add_filter( 'appointments_calculated_product_price', array( $this, 'adjust_appointment_cost' ), 10, 3 ); #at the beginning
		#add_filter( 'appointment_form_calculated_appointment_cost', array( $this, 'adjust_appointment_cost' ), 10, 3 ); #at the end
		add_filter( 'appointment_form_posted_total_duration', array( $this, 'adjust_appointment_duration' ), 10, 3 );
		add_filter( 'woocommerce_product_addons_duration', array( $this, 'hide_product_addons_option_duration' ), 10, 5 );
		add_filter( 'woocommerce_product_addons_option_duration', array( $this, 'hide_product_addons_option_duration' ), 10, 5 );
		add_filter( 'woocommerce_product_addons_price', array( $this, 'hide_product_addons_option_price' ), 10, 5 );
		add_filter( 'woocommerce_product_addons_option_price', array( $this, 'hide_product_addons_option_price' ), 10, 5 );
		add_filter( 'add_price_to_value', array( $this, 'maybe_hide_addon_price_label' ), 20, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'addons_script_styles' ), 100 );
	}

	/**
	 * Fire up Product Add-ons plugin.
	 * Forked plugin with mods to suit Appointments.
	 *
	 * @return bool
	 */
	public function includes() {
		/**
		 * Detect plugin. For use on Front End and Back End.
		 */
		if (
			! in_array(
				'woocommerce-product-addons/woocommerce-product-addons.php',
				apply_filters(
					'active_plugins',
					get_option( 'active_plugins' )
				)
			)
		) {
			include_once __DIR__ . '/woocommerce-product-addons.php';
		}
	}

	/**
	 * Disable Product Add-ons plugin.
	 *
	 * @return bool
	 */
	public function disable_addons() {
		/**
		 * Detect plugin. For use on Front End and Back End.
		 */
		if (
			in_array(
				'woocommerce-product-addons/woocommerce-product-addons.php',
				apply_filters(
					'active_plugins',
					get_option( 'active_plugins' )
				)
			)
		) {
			deactivate_plugins( 'woocommerce-product-addons/woocommerce-product-addons.php' );
			WC_Admin_Notices::add_custom_notice(
				'woocommerce_appointments_addons_deactivation',
				sprintf(
					/* translators: %1$s: WC Product Add-ons, %2$s: WC Appointments */
					__( '%1$s has been deactivated as it is already included with the %2$s.', 'woocommerce-appointments' ),
					'WooCommerce Product Add-Ons',
					'WooCommerce Appointments'
				)
			);
		}

		/*
		} else {
			WC_Admin_Notices::remove_notice( 'woocommerce_appointments_addons_deactivation' );
		}
		*/
	}

	/**
	 * Add addons to top of form as well.
	 *
	 * @return bool
	 */
	public function reorder_addon_fields() {
		// Display addons before and after appointment form.
		add_action( 'woocommerce_before_appointment_form_output', array( $this, 'appointment_display' ), 10, 2 );
		add_action( 'woocommerce_after_appointment_form_output', array( $this, 'appointment_display' ), 10, 2 );
	}

	/**
	 * Display add-ons.
	 *
	 * @param int|bool $position  Add-on position on form.
	 */
	public function appointment_display( $position = 'after', $product = 0 ) {
		// Get the addon display class.
		$addon_class = $GLOBALS['Product_Addon_Display'];

		// Check if product ID is provided.
		if ( is_int( $product ) ) {
			$product = wc_get_product( $product );
		}

		// Check if this is appointable product.
		if ( ! is_wc_appointment_product( $product ) ) {
			return;
		}

		$product_id = $product->get_id();

		// Get product addons from global variable.
		if ( ! isset( $GLOBALS['get_product_addons'][ $product_id ] ) ) {
			$GLOBALS['get_product_addons'][ $product_id ] = WC_Product_Addons_Helper::get_product_addons( $product_id, false );
		}
		$product_addons = $GLOBALS['get_product_addons'][ $product_id ] ? $GLOBALS['get_product_addons'][ $product_id ] : false;

		if ( is_array( $product_addons ) && count( $product_addons ) > 0 ) {
			// Run only once.
			if ( 'before' === $position && $addon_class ) {
				// Remove default addons display.
				remove_action( 'woocommerce_before_add_to_cart_button', array( $addon_class, 'display' ) ); #remove hook.

				// Load scripts for addons.
				$addon_class->addon_scripts();
			}

			// Run only once.
			if ( 'before' === $position && $addon_class ) {
				do_action( 'woocommerce_product_addons_start', $product_id );
			}

			foreach ( $product_addons as $addon ) {
				if ( ! isset( $addon['field_name'] ) ) {
					continue;
				}

				// Position the addon correctly.
				$on_top = $addon['wc_appointment_show_on_top'] ?? false;
				if ( $on_top && 'after' === $position || ! $on_top && 'before' === $position ) {
					continue;
				}

				wc_get_template(
					'addons/addon-start.php',
					array(
						'addons'              => $product_addons,
						'addon'               => $addon,
						'required'            => WC_Product_Addons_Helper::is_addon_required( $addon ),
						'name'                => $addon['name'],
						'description'         => $addon['description'],
						'display_description' => WC_Product_Addons_Helper::should_display_description( $addon ),
						'type'                => $addon['type'],
						'product'             => $product,
					),
					'woocommerce-product-addons',
					WC_PRODUCT_ADDONS_PLUGIN_PATH . '/templates/'
				);

				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This is only using the value for display purposes.
				$value = wc_clean( wp_unslash( $_REQUEST[ 'addon-' . sanitize_title( $addon['field_name'] ) ] ?? null ) );

				// This is sanitised in the template files and earlier functions.
				echo WC_Product_Addons_Html_Generator::get_addon_html( $addon, $value, $product_addons ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

				wc_get_template(
					'addons/addon-end.php',
					array(
						'addon' => $addon,
					),
					'woocommerce-product-addons',
					WC_PRODUCT_ADDONS_PLUGIN_PATH . '/templates/'
				);
			}

			// Run only once.
			if ( 'before' !== $position && $addon_class ) {
				do_action( 'woocommerce_product_addons_end', $product_id );
			}
		}
	}

	/**
	 * Show grand total or not?
	 * @param  bool $show_grand_total
	 * @param  object $product
	 * @return bool
	 */
	public function addons_show_grand_total( $show_grand_total, $product ) {
		if ( is_wc_appointment_product( $product ) ) {
			$show_grand_total = false;
		}
		return $show_grand_total;
	}

	/**
	 * Show options
	 */
	public function addon_options( $post, $addon, $loop ) {
		$addon_type                          = ! empty( $addon['type'] ) ? $addon['type'] : 'multiple_choice';
		$hide_duration_enable                = ! empty( $addon['wc_appointment_hide_duration_label'] ) ? $addon['wc_appointment_hide_duration_label'] : '';
		$hide_price_enable                   = ! empty( $addon['wc_appointment_hide_price_label'] ) ? $addon['wc_appointment_hide_price_label'] : '';
		$show_on_top_enable                  = ! empty( $addon['wc_appointment_show_on_top'] ) ? $addon['wc_appointment_show_on_top'] : '';
		$adjust_duration                     = ! empty( $addon['adjust_duration'] ) ? $addon['adjust_duration'] : '';
		$duration_type                       = ! empty( $addon['duration_type'] ) ? $addon['duration_type'] : '';
		$_duration                           = ! empty( $addon['duration'] ) ? $addon['duration'] : '';
		$display_hide_duration_setting_class = 'show';
		$display_hide_price_setting_class    = 'show';
		$display_show_on_top_setting_class   = 'show';
		$display_adjust_duration             = 'show';

		if ( in_array( $addon_type, array( 'heading', 'multiple_choice', 'checkbox', 'custom_price' ) ) ) {
			$display_adjust_duration = 'hide';
		}
		#error_log( var_export( $addon, true ) );
		?>
		<div class="wc-pao-addons-secondary-settings show_if_appointment hide_if_simple hide_if_grouped hide_if_external hide_if_variable">
			<div class="wc-pao-row wc-pao-addon-hide_duration-setting <?php esc_attr_e( $display_hide_duration_setting_class ); ?>">
				<label for="wc-pao-addon-hide_duration-<?php esc_attr_e( $loop ); ?>">
					<input type="checkbox" id="wc-pao-addon-hide_duration-<?php esc_attr_e( $loop ); ?>" name="addon_wc_appointment_hide_duration_label[<?php esc_attr_e( $loop ); ?>]" <?php checked( $hide_duration_enable, 1 ); ?> />
					<?php esc_html_e( 'Hide duration label', 'woocommerce-appointments' ); ?>
				</label>
			</div>
			<div class="wc-pao-row wc-pao-addon-hide_price-setting <?php esc_attr_e( $display_hide_price_setting_class ); ?>">
				<label for="wc-pao-addon-hide_price-<?php esc_attr_e( $loop ); ?>">
					<input type="checkbox" id="wc-pao-addon-hide_price-<?php esc_attr_e( $loop ); ?>" name="addon_wc_appointment_hide_price_label[<?php esc_attr_e( $loop ); ?>]" <?php checked( $hide_price_enable, 1 ); ?> />
					<?php esc_html_e( 'Hide price label', 'woocommerce-appointments' ); ?>
				</label>
			</div>
			<div class="wc-pao-row wc-pao-addon-show_on_top-setting <?php esc_attr_e( $display_show_on_top_setting_class ); ?>">
				<label for="wc-pao-addon-show_on_top-<?php esc_attr_e( $loop ); ?>">
					<input type="checkbox" id="wc-pao-addon-show_on_top-<?php esc_attr_e( $loop ); ?>" name="addon_wc_appointment_show_on_top[<?php esc_attr_e( $loop ); ?>]" <?php checked( $show_on_top_enable, 1 ); ?> />
					<?php esc_html_e( 'Show before appointment form', 'woocommerce-appointments' ); ?>
				</label>
			</div>
			<?php
			$display_adjust_duration_settings = ! empty( $adjust_duration ) ? 'show' : 'hide';
			?>
		</div>
		<div class="wc-pao-addon-content-non-option-rows show_if_appointment hide_if_simple hide_if_grouped hide_if_external hide_if_variable">
			<div class="wc-pao-row wc-pao-addon-adjust-duration-container <?php esc_attr_e( $display_adjust_duration ); ?>">
				<label for="wc-pao-addon-adjust-duration-<?php esc_attr_e( $loop ); ?>">
					<input type="checkbox" id="wc-pao-addon-adjust-duration-<?php esc_attr_e( $loop ); ?>" class="wc-pao-addon-adjust-duration" name="product_addon_adjust_duration[<?php esc_attr_e( $loop ); ?>]" <?php checked( $adjust_duration, 1 ); ?> />
					<?php
					esc_html_e( 'Adjust duration', 'woocommerce-appointments' );
					echo wc_help_tip( esc_html__( 'Choose how to calculate duration: apply a flat time regardless of quantity or charge per quantity ordered', 'woocommerce-appointments' ) );
					?>
				</label>
				<div class="wc-pao-addon-adjust-duration-settings <?php esc_attr_e( $display_adjust_duration_settings ); ?>">
					<select id="wc-pao-addon-adjust-duration-select-<?php esc_attr_e( $loop ); ?>" name="product_addon_duration_type[<?php esc_attr_e( $loop ); ?>]" class="wc-pao-addon-adjust-duration-select">
						<option <?php selected( 'flat_time', $duration_type ); ?> value="flat_time"><?php esc_html_e( 'Flat Time', 'woocommerce-appointments' ); ?></option>
						<option <?php selected( 'quantity_based', $duration_type ); ?> value="quantity_based"><?php esc_html_e( 'Quantity Based', 'woocommerce-appointments' ); ?></option>
					</select>

					<input type="number" name="product_addon_duration[<?php esc_attr_e( $loop ); ?>]" value="<?php esc_attr_e( $_duration ); ?>" placeholder="N/A" step="1" class="wc-pao-addon-adjust-duration-value wc_input_duration" />
					<?php do_action( 'woocommerce_product_addons_after_adjust_duration', $addon, $loop ); ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Show option head
	 */
	public function addon_option_head( $post, $addon, $loop ) {
		?>
		<div class="wc-pao-addon-content-duration-header show_if_appointment hide_if_simple hide_if_grouped hide_if_external hide_if_variable">
			<?php esc_html_e( 'Duration', 'woocommerce-appointments' ); ?>
			<?php echo wc_help_tip( esc_html__( 'Choose how to calculate the duration: Apply a flat time regardless of quantity or charge per quantity ordered', 'woocommerce-appointments' ) ); ?>
		</div>
		<?php
	}

	/**
	 * Show option body
	 */
	public function addon_option_body( $post, $addon, $loop, $option = [] ) {
		$opt_duration_type = isset( $option['duration_type'] ) ? esc_attr( $option['duration_type'] ) : '';
		$opt_duration      = isset( $option['duration'] ) ? esc_attr( $option['duration'] ) : '';
		?>
		<div class="wc-pao-addon-content-duration-type show_if_appointment hide_if_simple hide_if_grouped hide_if_external hide_if_variable">
			<select name="product_addon_option_duration_type[<?php esc_attr_e( $loop ); ?>][]" class="wc-pao-addon-option-duration-type">
				<option <?php selected( 'flat_time', $opt_duration_type ); ?> value="flat_time"><?php esc_html_e( 'Flat Time', 'woocommerce-appointments' ); ?></option>
				<option <?php selected( 'quantity_based', $opt_duration_type ); ?> value="quantity_based"><?php esc_html_e( 'Quantity Based', 'woocommerce-appointments' ); ?></option>
			</select>
		</div>
		<div class="wc-pao-addon-content-duration show_if_appointment hide_if_simple hide_if_grouped hide_if_external hide_if_variable">
			<input type="number" name="product_addon_option_duration[<?php esc_attr_e( $loop ); ?>][]" value="<?php echo $opt_duration; ?>" placeholder="N/A" step="1" />
		</div>
		<?php
	}

	/**
	 * Save options
	 */
	public function save_addon_options( $data, $i ) {
		$addon_option_duration_type = $_POST['product_addon_option_duration_type'][ $i ];
		$addon_option_duration      = $_POST['product_addon_option_duration'][ $i ];
		$addon_option_label         = $_POST['product_addon_option_label'][ $i ];
		$addon_option_size          = is_array( $addon_option_label ) ? count( $addon_option_label ) : 0;

		for ( $ii = 0; $ii < $addon_option_size; $ii++ ) {
			$duration_type                           = sanitize_text_field( stripslashes( $addon_option_duration_type[ $ii ] ) );
			$duration                                = sanitize_text_field( stripslashes( $addon_option_duration[ $ii ] ) );
			$data['options'][ $ii ]['duration_type'] = $duration_type;
			$data['options'][ $ii ]['duration']      = $duration;
		}

		$data['wc_appointment_hide_duration_label'] = isset( $_POST['addon_wc_appointment_hide_duration_label'][ $i ] ) ? 1 : 0;
		$data['wc_appointment_hide_price_label']    = isset( $_POST['addon_wc_appointment_hide_price_label'][ $i ] ) ? 1 : 0;
		$data['wc_appointment_show_on_top']         = isset( $_POST['addon_wc_appointment_show_on_top'][ $i ] ) ? 1 : 0;
		$data['adjust_duration']                    = isset( $_POST['product_addon_adjust_duration'][ $i ] ) ? 1 : 0;
		$data['duration_type']                      = sanitize_text_field( stripslashes( $_POST['product_addon_duration_type'][ $i ] ) );
		$data['duration']                           = sanitize_text_field( stripslashes( $_POST['product_addon_duration'][ $i ] ) );

		return $data;
	}

	/**
	 * Save options in admin
	 */
	public function save_addon_options_in_admin( $order_id, $item_id, $product ) {
		if ( ! $item_id ) {
			throw new Exception( __( 'Error: Could not create item', 'woocommerce-appointments' ) );
		}

		// Support new WooCommerce 3.0 WC_Product->get_id().
		if ( is_callable( array( $product, 'get_id' ) ) ) {
			$product_id = $product->get_id();
		} else {
			$product_id = $product->id;
		}

		// $cart_item_data, $product_id, $variation_id, $quantity, $post_data = [];
		$addons = $GLOBALS['Product_Addon_Cart']->add_cart_item_data( [], $product_id, $_POST );

		if ( ! empty( $addons['addons'] ) ) {
			foreach ( $addons['addons'] as $addon ) {

				$name = $addon['name'];

				if ( $addon['price'] > 0 && apply_filters( 'woocommerce_addons_add_price_to_name', true, $addon ) ) {
					$name .= ' (' . strip_tags( wc_price( WC_Product_Addons_Helper::get_product_addon_price_for_display( $addon['price'] ) ) ) . ')';
				}

				wc_add_order_item_meta( $item_id, $name, $addon['value'] );
			}
		}
	}

	/**
	 * Filter the cost display of bookings after booking selection.
	 * This only filters on success.
	 *
	 * @since 4.2.0
	 * @param html $output
	 * @param string $display_price
	 * @param object $product
	 * @return JSON Filtered results
	 */
	public function filter_output_cost( $output, $display_price, $product ) {
		parse_str( $_POST['form'], $posted );
		$cost = WC_Appointments_Cost_Calculation::calculate_appointment_cost( $posted, $product );

		wp_send_json(
			array(
				'result'    => 'SUCCESS',
				'html'      => $output,
				'raw_price' => (float) wc_get_price_to_display( $product, array( 'price' => $cost ) ),
			)
		);
	}

	/**
	 * Don't adjust price for appointments since the appointment form class adds the costs itself
	 * @return bool
	 */
	public function adjust_price( $bool, $cart_item ) {
		if ( $cart_item['data']->is_type( 'appointment' ) ) {
			return false;
		}

		return $bool;
	}

	/**
	 * Adjust the final appointment cost
	 */
	public function adjust_appointment_cost( $appointment_cost, $product, $posted ) {
		// Get addon cost.
		$addon_cost = $posted['wc_appointments_field_addons_cost'] ?? 0;

		#print '<pre>'; print_r( $posted ); print '</pre>';

		// Adjust.
		if ( 0 !== $addon_cost ) {
			$adjusted_cost = floatval( $appointment_cost ) + floatval( $addon_cost );
			$adjusted_cost = $adjusted_cost > 0 ? $adjusted_cost : 0; #turn negative cost to zero.
		// Do nothing.
		} else {
			$adjusted_cost = $appointment_cost;
		}

		#print '<pre>'; print_r( $adjusted_cost ); print '</pre>';

		return apply_filters( 'wc_appointments_adjust_addon_cost', $adjusted_cost, $appointment_cost, $product, $posted );
	}

	/**
	 * Adjust the final appointment duration
	 */
	public function adjust_appointment_duration( $appointment_duration, $product, $posted ) {
		// Get addon duration.
		$addon_duration = $posted['wc_appointments_field_addons_duration'] ?? 0;

		#print '<pre>'; print_r( $appointment_duration ); print '</pre>';
		#print '<pre>'; print_r( $addon_duration ); print '</pre>';

		// Adjust.
		if ( 0 !== $addon_duration ) {
			$adjusted_duration = floatval( $appointment_duration ) + floatval( $addon_duration );
			$adjusted_duration = $adjusted_duration > 0 ? $adjusted_duration : 0; #turn negative duration to zero.
		// Do nothing.
		} else {
			$adjusted_duration = $appointment_duration;
		}

		// Make sure duration is not zero or negative.
		if ( $adjusted_duration <= 0 ) {
			$adjusted_duration = $appointment_duration;
		}

		#print '<pre>'; print_r( $adjusted_duration ); print '</pre>';

		return apply_filters( 'wc_appointments_adjust_addon_duration', $adjusted_duration, $appointment_duration, $product, $posted );
	}

	/**
	 * Optionally hide duration label for customers.
	 *
	 */
	public function hide_product_addons_option_duration( $posted, $option, $key, $addon, $type = '' ) {
		$hide_label = $addon['wc_appointment_hide_duration_label'] ?? false;
		if ( $hide_label ) {
			return;
		}

		return $posted;
	}

	/**
	 * Optionally hide price label for customers.
	 *
	 */
	public function hide_product_addons_option_price( $posted, $option, $key, $addon, $type = '' ) {
		#error_log( var_export( $addon, true ) );
		$hide_label = isset( $addon['wc_appointment_hide_price_label'] ) ? $addon['wc_appointment_hide_price_label'] : false;
		if ( $hide_label ) {
			return;
		}

		return $posted;
	}

	/**
	 * Optionally hide price label for customers.
	 *
	 */
	public function maybe_hide_addon_price_label( $return, $addon ) {
		#error_log( var_export( $addon, true ) );
		$hide_label = isset( $addon['hide_price'] ) ? $addon['hide_price'] : false;
		if ( $hide_label ) {
			return;
		}

		return $return;
	}

	/**
	 * Enqueue scripts and styles.
	 *
	 * @since 4.2.0
	 */
	public function addons_script_styles() {
		// Get current screen.
		$screen    = function_exists( 'get_current_screen' ) ? get_current_screen() : '';
		$screen_id = $screen ? $screen->id : '';

		// Allowed screen IDs.
		$allowed_screens = array(
			'product_page_addons',
			'shop_order',
			'shop_subscription',
		);

		if ( in_array( $screen_id, $allowed_screens ) ) {
			wp_enqueue_script( 'wc_appointments_writepanel_js' );
		}
	}

}

$GLOBALS['Appointments_Integration_Addons'] = new WC_Appointments_Integration_Addons();
