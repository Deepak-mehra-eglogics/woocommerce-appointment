<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Main WC_Appointments_Admin class.
 */
class WC_Appointments_Admin {

	private static $_this;

	/**
	 * Constructor
	 */
	public function __construct() {
		self::$_this = $this;

		// Add to <head> in admin.
		add_action( 'admin_head', [ $this, 'appointments_admin_head' ], 11 );

		// Load correct list table classes for current screen.
		add_action( 'current_screen', [ $this, 'setup_screen' ] );
		add_action( 'check_ajax_referer', [ $this, 'setup_screen' ] );

		add_action( 'woocommerce_product_duplicate', [ $this, 'woocommerce_duplicate_product' ], 10, 2 );
		add_filter( 'post_updated_messages', [ $this, 'post_updated_messages' ] );
		add_action( 'admin_init', [ $this, 'init_tabs' ] );
		add_action( 'admin_init', [ $this, 'include_meta_box_handlers' ] );
		add_action( 'admin_init', [ $this, 'redirect_new_add_appointment_url' ] );
		add_action( 'woocommerce_product_options_inventory_product_data', [ $this, 'appointment_inventory' ] );
		add_filter( 'product_type_options', [ $this, 'product_type_options' ] );
		add_filter( 'product_type_selector', [ $this, 'product_type_selector' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'styles_and_scripts' ] );
		add_action( 'woocommerce_product_options_general_product_data', [ $this, 'appointment_general' ] );
		add_action( 'load-options-general.php', [ $this, 'reset_ics_exporter_timezone_cache' ] );
		add_action( 'woocommerce_before_order_itemmeta', [ $this, 'appointment_display' ], 10, 3 );
		add_filter( 'woocommerce_get_settings_pages', [ $this, 'add_settings_page' ], 10, 1 );
		add_filter( 'woocommerce_template_overrides_scan_paths', [ $this, 'template_scan_path' ] );
		add_filter( 'woocommerce_product_type_query', [ $this, 'maybe_override_product_type' ], 10, 2 );

		// Saving data.
		add_action( 'woocommerce_process_product_meta', [ $this, 'save_product_data' ], 20 );
		add_action( 'woocommerce_admin_process_product_object', [ $this, 'set_props' ], 20 );
		add_action( 'before_delete_post', [ $this, 'before_delete_product' ], 11 );

		include 'class-wc-appointments-admin-menus.php';
		include 'class-wc-appointments-admin-staff-profile.php';
		include 'class-wc-appointments-admin-exporters.php';
	}

	/**
	 * Add meta tags and scripts to <head> in admin.
	 *
	 * @since 4.10.7
	 */
	public function appointments_admin_head() {
		$client_id = isset( $_POST['wc_appointments_gcal_client_id'] ) ? sanitize_text_field( $_POST['wc_appointments_gcal_client_id'] ) : get_option( 'wc_appointments_gcal_client_id' );

		if ( $client_id ) {
			echo '<meta name="google-signin-client_id" content="' . $client_id . '">';
		}
	}

	/**
	 * Looks at the current screen and loads the correct list table handler.
	 *
	 * @since 3.3.0
	 */
	public function setup_screen() {
		global $wc_list_table;

		$screen_id = false;

		if ( function_exists( 'get_current_screen' ) ) {
			$screen    = get_current_screen();
			$screen_id = isset( $screen, $screen->id ) ? $screen->id : '';
		}

		if ( ! empty( $_REQUEST['screen'] ) ) { // WPCS: input var ok.
			$screen_id = wc_clean( wp_unslash( $_REQUEST['screen'] ) ); // WPCS: input var ok, sanitization ok.
		}

		switch ( $screen_id ) {
			case 'edit-wc_appointment':
				include_once 'class-wc-appointments-admin-list-table.php';
				$wc_list_table = new WC_Appointments_Admin_List_Table();
				break;
		}

		// Ensure the table handler is only loaded once. Prevents multiple loads if a plugin calls check_ajax_referer many times.
		remove_action( 'current_screen', [ $this, 'setup_screen' ] );
		remove_action( 'check_ajax_referer', [ $this, 'setup_screen' ] );
	}

	/**
	 * Save Appointment data for the product in 2.6.x.
	 *
	 * @param int $post_id
	 */
	public function save_product_data( $post_id ) {
		if ( version_compare( WC_VERSION, '3.0', '>=' ) || 'appointment' !== sanitize_title( stripslashes( $_POST['product-type'] ) ) ) {
			return;
		}
		$product = get_wc_product_appointment( $post_id );
		$this->set_props( $product );
		$product->save();
	}

	/**
	 * Get posted pricing fields and format.
	 *
	 * @return array
	 */
	private function get_posted_pricing() {
		$pricing  = [];
		$row_size = isset( $_POST['wc_appointment_pricing_type'] ) ? sizeof( $_POST['wc_appointment_pricing_type'] ) : 0;
		for ( $i = 0; $i < $row_size; $i ++ ) {
			$pricing[ $i ]['type']          = wc_clean( $_POST['wc_appointment_pricing_type'][ $i ] );
			$pricing[ $i ]['cost']          = wc_clean( $_POST['wc_appointment_pricing_cost'][ $i ] );
			$pricing[ $i ]['modifier']      = wc_clean( $_POST['wc_appointment_pricing_cost_modifier'][ $i ] );
			$pricing[ $i ]['base_cost']     = wc_clean( $_POST['wc_appointment_pricing_base_cost'][ $i ] );
			$pricing[ $i ]['base_modifier'] = wc_clean( $_POST['wc_appointment_pricing_base_cost_modifier'][ $i ] );

			switch ( $pricing[ $i ]['type'] ) {
				case 'custom':
					$pricing[ $i ]['from'] = wc_clean( $_POST['wc_appointment_pricing_from_date'][ $i ] );
					$pricing[ $i ]['to']   = wc_clean( $_POST['wc_appointment_pricing_to_date'][ $i ] );
					break;
				case 'months':
					$pricing[ $i ]['from'] = wc_clean( $_POST['wc_appointment_pricing_from_month'][ $i ] );
					$pricing[ $i ]['to']   = wc_clean( $_POST['wc_appointment_pricing_to_month'][ $i ] );
					break;
				case 'weeks':
					$pricing[ $i ]['from'] = wc_clean( $_POST['wc_appointment_pricing_from_week'][ $i ] );
					$pricing[ $i ]['to']   = wc_clean( $_POST['wc_appointment_pricing_to_week'][ $i ] );
					break;
				case 'days':
					$pricing[ $i ]['from'] = wc_clean( $_POST['wc_appointment_pricing_from_day_of_week'][ $i ] );
					$pricing[ $i ]['to']   = wc_clean( $_POST['wc_appointment_pricing_to_day_of_week'][ $i ] );
					break;
				case 'time':
				case 'time:1':
				case 'time:2':
				case 'time:3':
				case 'time:4':
				case 'time:5':
				case 'time:6':
				case 'time:7':
					$pricing[ $i ]['from'] = wc_appointment_sanitize_time( $_POST['wc_appointment_pricing_from_time'][ $i ] );
					$pricing[ $i ]['to']   = wc_appointment_sanitize_time( $_POST['wc_appointment_pricing_to_time'][ $i ] );
					break;
				case 'time:range':
					$pricing[ $i ]['from'] = wc_appointment_sanitize_time( $_POST['wc_appointment_pricing_from_time'][ $i ] );
					$pricing[ $i ]['to']   = wc_appointment_sanitize_time( $_POST['wc_appointment_pricing_to_time'][ $i ] );

					$pricing[ $i ]['from_date'] = wc_clean( $_POST['wc_appointment_pricing_from_date'][ $i ] );
					$pricing[ $i ]['to_date']   = wc_clean( $_POST['wc_appointment_pricing_to_date'][ $i ] );
					break;
				default:
					$pricing[ $i ]['from'] = wc_clean( $_POST['wc_appointment_pricing_from'][ $i ] );
					$pricing[ $i ]['to']   = wc_clean( $_POST['wc_appointment_pricing_to'][ $i ] );
					break;
			}
		}
		return $pricing;
	}

	/**
	 * Get posted staff. Staffs are global, but appointment products store information about the relationship.
	 *
	 * @return array
	 */
	private function get_posted_staff( $product ) {
		$staff = [];

		if ( isset( $_POST['staff_id'] ) ) {
			$staff_ids        = $_POST['staff_id'];
			$staff_menu_order = $_POST['staff_menu_order'];
			$staff_base_cost  = $_POST['staff_cost'];
			$staff_qty        = $_POST['staff_qty'];
			$max_loop         = max( array_keys( $_POST['staff_id'] ) );
			$staff_base_costs = [];
			$staff_qtys       = [];

			foreach ( $staff_menu_order as $key => $value ) {
				$staff[ absint( $staff_ids[ $key ] ) ] = [
					'base_cost' => wc_clean( $staff_base_cost[ $key ] ),
					'qty'       => wc_clean( $staff_qty[ $key ] ),
				];
			}
		}

		return $staff;
	}

	/**
	 * Get posted availability fields and format.
	 *
	 * @return void
	 */
	private function save_product_availability( $product ) {
		// Delete.
		if ( ! empty( $_POST['wc_appointment_availability_deleted'] ) ) {
			$deleted_ids = array_filter( explode( ',', wc_clean( wp_unslash( $_POST['wc_appointment_availability_deleted'] ) ) ) );

			foreach ( $deleted_ids as $delete_id ) {
				$availability_object = get_wc_appointments_availability( $delete_id );
				if ( $availability_object ) {
					$availability_object->delete();
				}
			}
		}

		// Save.
		$types    = isset( $_POST['wc_appointment_availability_type'] ) ? wc_clean( wp_unslash( $_POST['wc_appointment_availability_type'] ) ) : [];
		$row_size = count( $types );

		for ( $i = 0; $i < $row_size; $i ++ ) {
			if ( isset( $_POST['wc_appointment_availability_id'][ $i ] ) ) {
				$current_id = intval( $_POST['wc_appointment_availability_id'][ $i ] );
			} else {
				$current_id = 0;
			}

			$availability = get_wc_appointments_availability( $current_id );
			$availability->set_ordering( $i );
			$availability->set_range_type( $types[ $i ] );
			$availability->set_kind( 'availability#product' );
			$availability->set_kind_id( $product->get_id() );

			if ( isset( $_POST['wc_appointment_availability_appointable'][ $i ] ) ) {
				$availability->set_appointable( wc_clean( wp_unslash( $_POST['wc_appointment_availability_appointable'][ $i ] ) ) );
			}

			if ( isset( $_POST['wc_appointment_availability_title'][ $i ] ) ) {
				$availability->set_title( sanitize_text_field( wp_unslash( $_POST['wc_appointment_availability_title'][ $i ] ) ) );
			}

			if ( isset( $_POST['wc_appointment_availability_qty'][ $i ] ) ) {
				$availability->set_qty( intval( $_POST['wc_appointment_availability_qty'][ $i ] ) );
			}

			if ( isset( $_POST['wc_appointment_availability_priority'][ $i ] ) ) {
				$availability->set_priority( intval( $_POST['wc_appointment_availability_priority'][ $i ] ) );
			}

			switch ( $availability->get_range_type() ) {
				case 'custom':
					if ( isset( $_POST['wc_appointment_availability_from_date'][ $i ] ) && isset( $_POST['wc_appointment_availability_to_date'][ $i ] ) ) {
						$availability->set_from_range( wc_clean( wp_unslash( $_POST['wc_appointment_availability_from_date'][ $i ] ) ) );
						$availability->set_to_range( wc_clean( wp_unslash( $_POST['wc_appointment_availability_to_date'][ $i ] ) ) );
					}
					break;
				case 'months':
					if ( isset( $_POST['wc_appointment_availability_from_month'][ $i ] ) && isset( $_POST['wc_appointment_availability_to_month'][ $i ] ) ) {
						$availability->set_from_range( wc_clean( wp_unslash( $_POST['wc_appointment_availability_from_month'][ $i ] ) ) );
						$availability->set_to_range( wc_clean( wp_unslash( $_POST['wc_appointment_availability_to_month'][ $i ] ) ) );
					}
					break;
				case 'weeks':
					if ( isset( $_POST['wc_appointment_availability_from_week'][ $i ] ) && isset( $_POST['wc_appointment_availability_to_week'][ $i ] ) ) {
						$availability->set_from_range( wc_clean( wp_unslash( $_POST['wc_appointment_availability_from_week'][ $i ] ) ) );
						$availability->set_to_range( wc_clean( wp_unslash( $_POST['wc_appointment_availability_to_week'][ $i ] ) ) );
					}
					break;
				case 'days':
					if ( isset( $_POST['wc_appointment_availability_from_day_of_week'][ $i ] ) && isset( $_POST['wc_appointment_availability_to_day_of_week'][ $i ] ) ) {
						$availability->set_from_range( wc_clean( wp_unslash( $_POST['wc_appointment_availability_from_day_of_week'][ $i ] ) ) );
						$availability->set_to_range( wc_clean( wp_unslash( $_POST['wc_appointment_availability_to_day_of_week'][ $i ] ) ) );
					}
					break;
				case 'rrule':
					// Do nothing rrules are read only for now.
					break;
				case 'time':
				case 'time:1':
				case 'time:2':
				case 'time:3':
				case 'time:4':
				case 'time:5':
				case 'time:6':
				case 'time:7':
					if ( isset( $_POST['wc_appointment_availability_from_time'][ $i ] ) && isset( $_POST['wc_appointment_availability_to_time'][ $i ] ) ) {
						$availability->set_from_range( wc_appointment_sanitize_time( wp_unslash( $_POST['wc_appointment_availability_from_time'][ $i ] ) ) );
						$availability->set_to_range( wc_appointment_sanitize_time( wp_unslash( $_POST['wc_appointment_availability_to_time'][ $i ] ) ) );
					}
					break;
				case 'time:range':
				case 'custom:daterange':
					if ( isset( $_POST['wc_appointment_availability_from_time'][ $i ] ) && isset( $_POST['wc_appointment_availability_to_time'][ $i ] ) ) {
						$availability->set_from_range( wc_appointment_sanitize_time( wp_unslash( $_POST['wc_appointment_availability_from_time'][ $i ] ) ) );
						$availability->set_to_range( wc_appointment_sanitize_time( wp_unslash( $_POST['wc_appointment_availability_to_time'][ $i ] ) ) );
					}
					if ( isset( $_POST['wc_appointment_availability_from_date'][ $i ] ) && isset( $_POST['wc_appointment_availability_to_date'][ $i ] ) ) {
						$availability->set_from_date( wc_clean( wp_unslash( $_POST['wc_appointment_availability_from_date'][ $i ] ) ) );
						$availability->set_to_date( wc_clean( wp_unslash( $_POST['wc_appointment_availability_to_date'][ $i ] ) ) );
					}
					break;
			}

			$availability->save();
		}
	}

	/**
	 * Set data in 3.0.x
	 *
	 * @version  3.3.0
	 * @param    WC_Product $product
	 */
	public function set_props( $product ) {
		// Only set props if the product is a appointable product.
		if ( ! is_wc_appointment_product( $product ) ) {
			return;
		}

		$staff        = $this->get_posted_staff( $product );
		$availability = $this->save_product_availability( $product );
		$product->set_props(
			[
				'has_price_label'         => isset( $_POST['_wc_appointment_has_price_label'] ),
				'price_label'             => wc_clean( $_POST['_wc_appointment_price_label'] ),
				'has_pricing'             => isset( $_POST['_wc_appointment_has_pricing'] ),
				'pricing'                 => $this->get_posted_pricing(),
				'qty'                     => wc_clean( $_POST['_wc_appointment_qty'] ),
				'qty_min'                 => wc_clean( $_POST['_wc_appointment_qty_min'] ),
				'qty_max'                 => wc_clean( $_POST['_wc_appointment_qty_max'] ),
				'duration_unit'           => wc_clean( $_POST['_wc_appointment_duration_unit'] ),
				'duration'                => wc_clean( $_POST['_wc_appointment_duration'] ),
				'interval_unit'           => wc_clean( $_POST['_wc_appointment_interval_unit'] ),
				'interval'                => wc_clean( $_POST['_wc_appointment_interval'] ),
				'padding_duration_unit'   => wc_clean( $_POST['_wc_appointment_padding_duration_unit'] ),
				'padding_duration'        => wc_clean( $_POST['_wc_appointment_padding_duration'] ),
				'min_date_unit'           => wc_clean( $_POST['_wc_appointment_min_date_unit'] ),
				'min_date'                => wc_clean( $_POST['_wc_appointment_min_date'] ),
				'max_date_unit'           => wc_clean( $_POST['_wc_appointment_max_date_unit'] ),
				'max_date'                => wc_clean( $_POST['_wc_appointment_max_date'] ),
				'user_can_cancel'         => isset( $_POST['_wc_appointment_user_can_cancel'] ),
				'cancel_limit_unit'       => wc_clean( $_POST['_wc_appointment_cancel_limit_unit'] ),
				'cancel_limit'            => wc_clean( $_POST['_wc_appointment_cancel_limit'] ),
				'user_can_reschedule'     => isset( $_POST['_wc_appointment_user_can_reschedule'] ),
				'reschedule_limit_unit'   => wc_clean( $_POST['_wc_appointment_reschedule_limit_unit'] ),
				'reschedule_limit'        => wc_clean( $_POST['_wc_appointment_reschedule_limit'] ),
				'requires_confirmation'   => isset( $_POST['_wc_appointment_requires_confirmation'] ),
				'customer_timezones'      => isset( $_POST['_wc_appointment_customer_timezones'] ),
				'cal_color'               => wc_clean( $_POST['_wc_appointment_cal_color'] ),
				'availability_span'       => wc_clean( $_POST['_wc_appointment_availability_span'] ),
				'availability_autoselect' => isset( $_POST['_wc_appointment_availability_autoselect'] ),
				'has_restricted_days'     => isset( $_POST['_wc_appointment_has_restricted_days'] ),
				'restricted_days'         => isset( $_POST['_wc_appointment_restricted_days'] ) ? wc_clean( $_POST['_wc_appointment_restricted_days'] ) : '',
				'staff_label'             => wc_clean( $_POST['_wc_appointment_staff_label'] ),
				'staff_ids'               => array_keys( $staff ),
				'staff_base_costs'        => wp_list_pluck( $staff, 'base_cost' ),
				'staff_qtys'              => wp_list_pluck( $staff, 'qty' ),
				'staff_assignment'        => wc_clean( $_POST['_wc_appointment_staff_assignment'] ),
				'staff_nopref'            => isset( $_POST['_wc_appointment_staff_nopref'] ),
				'appointments_version'    => WC_APPOINTMENTS_VERSION,
				'appointments_db_version' => WC_APPOINTMENTS_DB_VERSION,
			]
		);
	}

	/**
	 * Init product edit tabs.
	 */
	public function init_tabs() {
		add_filter( 'woocommerce_product_data_tabs', [ $this, 'register_tab' ], 5 );
		add_action( 'woocommerce_product_data_panels', [ $this, 'appointment_panels' ] );
	}

	/**
	 * Add tabs to WC 2.6+
	 *
	 * @param  array $tabs
	 * @return array
	 */
	public function register_tab( $tabs ) {
		$tabs['appointments_staff']        = [
			'label'    => __( 'Staff', 'woocommerce-appointments' ),
			'target'   => 'appointments_staff',
			'class'    => [
				'show_if_appointment',
			],
			'priority' => 25,
		];
		$tabs['appointments_availability'] = [
			'label'    => __( 'Availability', 'woocommerce-appointments' ),
			'target'   => 'appointments_availability',
			'class'    => [
				'show_if_appointment',
			],
			'priority' => 25,
		];

		// Inventory
		$tabs['inventory']['class'][] = 'show_if_appointment';

		return $tabs;
	}

	/**
	 * Public access to instance object
	 *
	 * @return object
	 */
	public static function get_instance() {
		return self::$_this;
	}

	/**
	 * Duplicate a post.
	 *
	 * @param  WC_Product $new_product Duplicated product.
	 * @param  WC_Product $product     Original product.
	 */
	public function woocommerce_duplicate_product( $new_product, $product ) {
		if ( is_wc_appointment_product( $product ) ) {
			// Get all product availability rules.
			$product_rules = WC_Data_Store::load( 'appointments-availability' )->get_all(
				[
					[
						'key'     => 'kind',
						'compare' => '=',
						'value'   => 'availability#product',
					],
					[
						'key'     => 'kind_id',
						'compare' => '=',
						'value'   => $product->get_id(),
					],
				]
			);

			// Stop here when no rules.
			if ( ! $product_rules ) {
				return;
			}

			// Clone and re-save availabilities.
			foreach ( $product_rules as $availability ) {
				$dupe_availability = clone $availability;
				$dupe_availability->set_id( 0 );
				$dupe_availability->set_kind_id( $new_product->get_id() );
				$dupe_availability->save();
			}
		}
	}

	/**
	 * Change messages when a post type is updated.
	 *
	 * @param  array $messages
	 * @return array
	 */
	public function post_updated_messages( $messages ) {
		$messages['wc_appointment'] = [
			0  => '', // Unused. Messages start at index 1.
			1  => __( 'Appointment updated.', 'woocommerce-appointments' ),
			2  => __( 'Custom field updated.', 'woocommerce-appointments' ),
			3  => __( 'Custom field deleted.', 'woocommerce-appointments' ),
			4  => __( 'Appointment updated.', 'woocommerce-appointments' ),
			5  => '',
			6  => __( 'Appointment updated.', 'woocommerce-appointments' ),
			7  => __( 'Appointment saved.', 'woocommerce-appointments' ),
			8  => __( 'Appointment submitted.', 'woocommerce-appointments' ),
			9  => '',
			10 => '',
		];

		return $messages;
	}

	/**
	 * Show appointment data if a line item is linked to an appointment ID.
	 */
	public function appointment_display( $item_id, $item, $product ) {
		if ( ! is_wc_appointment_product( $product ) ) {
			return;
		}

		$appointment_ids = WC_Appointment_Data_Store::get_appointment_ids_from_order_and_item_id( $item->get_order_id(), $item_id );

		wc_get_template(
			'order/admin/appointment-display.php',
			[
				'appointment_ids' => $appointment_ids,
				'is_rtl'          => is_rtl() ? 'right' : 'left',
			],
			'',
			WC_APPOINTMENTS_TEMPLATE_PATH
		);
	}

	/**
	 * Include meta box handlers
	 */
	public function include_meta_box_handlers() {
		include 'class-wc-appointments-admin-meta-boxes.php';
	}

	/**
	 * Redirect the default add appointment url to the custom one
	 */
	public function redirect_new_add_appointment_url() {
		global $pagenow;

		if ( 'post-new.php' == $pagenow && isset( $_GET['post_type'] ) && 'wc_appointment' == $_GET['post_type'] ) {
			wp_safe_redirect( admin_url( 'edit.php?post_type=wc_appointment&page=add_appointment' ), '301' );
		}
	}

	/**
	 * Get appointment products.
	 *
	 * @return array
	 */
	public static function get_appointment_products( $show_all = false ) {
		$ids                  = WC_Data_Store::load( 'product-appointment' )->get_appointable_product_ids( $show_all );
		$appointable_products = [];

		if ( ! $ids ) {
			return $appointable_products;
		}

		foreach ( $ids as $id ) {
			$appointable_products[] = get_wc_product_appointment( $id );
		}

		return $appointable_products;
	}

	/**
	 * Get appointment products
	 * @return array
	 */
	public static function get_appointment_staff() {
		// Cache query.
		$cache_group = 'wc-appointments-admin-staff';
		$cache_key   = WC_Cache_Helper::get_cache_prefix( $cache_group ) . 'get_appointment_staff';

		$data = wp_cache_get( $cache_key, $cache_group );

		if ( false === $data ) {
			$data = get_users(
				apply_filters(
					'get_appointment_staff_args',
					[
						'role'    => 'shop_staff',
						'orderby' => 'nicename',
						'order'   => 'asc',
					]
				)
			);

			wp_cache_set( $cache_key, $data, $cache_group );
		}

		return $data;
	}

	/**
	 * On before delete product hook remove the product from all appointments
	 *
	 * @param int $product_id The id of the product that we are deleting
	 */
	public function before_delete_product( $product_id ) {
		if ( ! current_user_can( 'delete_posts' ) ) {
			return;
		}

		if ( $product_id > 0 && 'product' === get_post_type( $product_id ) ) {
			$product_appointments = WC_Appointment_Data_Store::get_appointments_for_product( $product_id, get_wc_appointment_statuses( 'validate' ) );
			// Loop appointable products is added to remove the product from it
			foreach ( $product_appointments as $product_appointment ) {
				delete_post_meta( $product_appointment->get_id(), '_appointment_product_id' );
			}
		}
	}

	/**
	 * Show the appointment inventory view
	 */
	public function appointment_inventory() {
		global $appointable_product;

		if ( empty( $appointable_product ) || $appointable_product->get_id() !== get_the_ID() ) {
			$appointable_product = get_wc_product_appointment( get_the_ID() );
		}

		include 'views/html-appointment-inventory.php';
	}

	/**
	 * Tweak product type options
	 * @param  array $options
	 * @return array
	 */
	public function product_type_options( $options ) {
		$options['virtual']['wrapper_class']      .= ' show_if_appointment';
		$options['downloadable']['wrapper_class'] .= ' show_if_appointment';

		return $options;
	}

	/**
	 * Add the appointment product type
	 */
	public function product_type_selector( $types ) {
		$types['appointment'] = __( 'Appointable product', 'woocommerce-appointments' );

		return $types;
	}

	/**
	 * Show the appointment general view
	 */
	public function appointment_general() {
		global $appointable_product;

		if ( empty( $appointable_product ) || $appointable_product->get_id() !== get_the_ID() ) {
			$appointable_product = get_wc_product_appointment( get_the_ID() );
		}

		include 'views/html-appointment-general.php';
	}

	/**
	 * Show the appointment panels views
	 */
	public function appointment_panels() {
		global $appointable_product;

		if ( empty( $appointable_product ) || $appointable_product->get_id() !== get_the_ID() ) {
			$appointable_product = get_wc_product_appointment( get_the_ID() );
		}

		$restricted_meta = $appointable_product->get_restricted_days();

		for ( $i = 0; $i < 7; $i++ ) {

			if ( $restricted_meta && in_array( $i, $restricted_meta ) ) {
				$restricted_days[ $i ] = $i;
			} else {
				$restricted_days[ $i ] = false;
			}
		}

		wp_enqueue_script( 'wc_appointments_writepanel_js' );

		include 'views/html-appointment-staff.php';
		include 'views/html-appointment-availability.php';
	}

	/**
	 * Add admin styles
	 */
	public function styles_and_scripts() {
		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		wp_enqueue_style( 'wc_appointments_admin_styles', WC_APPOINTMENTS_PLUGIN_URL . '/assets/css/admin.css', true, WC_APPOINTMENTS_VERSION );
		wp_enqueue_script( 'wp-color-picker' );
		wp_register_script( 'wc_appointments_writepanel_js', WC_APPOINTMENTS_PLUGIN_URL . '/assets/js/writepanel' . $suffix . '.js', [ 'jquery', 'jquery-ui-datepicker' ], WC_APPOINTMENTS_VERSION, true );
		wp_register_script( 'wc_appointments_exporter_js', WC_APPOINTMENTS_PLUGIN_URL . '/assets/js/appointment-export' . $suffix . '.js', [ 'jquery', 'jquery-ui-datepicker', 'wc-enhanced-select' ], WC_APPOINTMENTS_VERSION, true );

		// Remove ACF plugin's timepicker scripts.
		if ( 'product' === get_post_type() ) {
			wp_deregister_script( 'acf-timepicker' );
			wp_dequeue_style( 'acf-timepicker' );
    	}

		// Get default location.
		$default_location = wc_get_customer_default_location();

		$params = [
			'nonce_delete_staff'        => wp_create_nonce( 'delete-appointable-staff' ),
			'nonce_add_staff'           => wp_create_nonce( 'add-appointable-staff' ),
			'nonce_add_product'         => wp_create_nonce( 'add-staff-product' ),
			'nonce_staff_html'          => wp_create_nonce( 'appointable-staff-html' ),
			'nonce_manual_sync'         => wp_create_nonce( 'add-manual-sync' ),
			'nonce_oauth_redirect'      => wp_create_nonce( 'add-oauth-redirect' ),
			'nonce_export_appointemnts' => wp_create_nonce( 'wc-appointment-export' ),
			'nonce_appointment_order'   => wp_create_nonce( 'search-appointment-order' ),

			'i18n_confirmation'         => esc_js( __( 'Are you sure?', 'woocommerce-appointments' ) ),
			'i18n_minutes'              => esc_js( __( 'minutes', 'woocommerce-appointments' ) ),
			'i18n_hours'                => esc_js( __( 'hours', 'woocommerce-appointments' ) ),
			'i18n_days'                 => esc_js( __( 'days', 'woocommerce-appointments' ) ),

			'post'                      => get_the_ID(),
			'plugin_url'                => WC()->plugin_url(),
			'ajax_url'                  => admin_url( 'admin-ajax.php' ),
			'firstday'                  => absint( get_option( 'start_of_week', 1 ) ),
			'calendar_image'            => WC()->plugin_url() . '/assets/images/calendar.png',

	        'exporter'                  => [
				'string'     => esc_js( __( 'Export', 'woocommerce-appointments' ) ),
				'url'        => esc_url_raw( admin_url( 'edit.php?post_type=wc_appointment&page=appointment_exporter' ) ),
				'permission' => current_user_can( 'export' ) ? true : false,
			],

			'billing'                   => [
				'countries'       => wp_json_encode( array_merge( WC()->countries->get_allowed_country_states(), WC()->countries->get_shipping_country_states() ) ),
				'default_country' => isset( $default_location['country'] ) ? $default_location['country'] : '',
				'default_state'   => isset( $default_location['state'] ) ? $default_location['state'] : '',
			],
		];

		wp_localize_script( 'wc_appointments_writepanel_js', 'wc_appointments_writepanel_js_params', $params );
		wp_localize_script( 'wc_appointments_exporter_js', 'wc_appointments_exporter_js_params', $params );
	}

	/**
	 * Reset the ics exporter timezone string cache.
	 *
	 * @return void
	 */
	public function reset_ics_exporter_timezone_cache() {
		if ( isset( $_GET['settings-updated'] ) && 'true' == $_GET['settings-updated'] ) {
			wp_cache_delete( 'wc_appointments_timezone_string' );
		}
	}

	/**
	 * Add memberships settings page
	 *
	 * @since 1.0
	 * @param array $settings
	 * @return array
	 */
	public function add_settings_page( $settings ) {
		$settings[] = include 'class-wc-appointments-admin-settings.php';

		return $settings;
	}

	/**
	 * Support scanning for template overrides in extension.
	 *
	 * @param  array $paths
	 * @return array
	 */
	public function template_scan_path( $paths ) {
		$paths['WooCommerce Appointments'] = WC_APPOINTMENTS_TEMPLATE_PATH;

		return $paths;
	}

	/**
	 * Show a notice highlighting bad template files.
	 *
	 */
	public static function template_file_check_notice() {
		$core_templates = WC_Admin_Status::scan_template_files( WC_APPOINTMENTS_TEMPLATE_PATH );
		$outdated       = false;

		foreach ( $core_templates as $file ) {

			$theme_file = false;
			if ( file_exists( get_stylesheet_directory() . '/' . $file ) ) {
				$theme_file = get_stylesheet_directory() . '/' . $file;
			} elseif ( file_exists( get_stylesheet_directory() . '/woocommerce/' . $file ) ) {
				$theme_file = get_stylesheet_directory() . '/woocommerce/' . $file;
			} elseif ( file_exists( get_template_directory() . '/' . $file ) ) {
				$theme_file = get_template_directory() . '/' . $file;
			} elseif ( file_exists( get_template_directory() . '/woocommerce/' . $file ) ) {
				$theme_file = get_template_directory() . '/woocommerce/' . $file;
			}

			if ( false !== $theme_file ) {
				$core_version  = WC_Admin_Status::get_file_version( WC_APPOINTMENTS_TEMPLATE_PATH . $file );
				$theme_version = WC_Admin_Status::get_file_version( $theme_file );

				if ( $core_version && $theme_version && version_compare( $theme_version, $core_version, '<' ) ) {
					$outdated = true;
					break;
				}
			}
		}

		if ( $outdated ) {
			$theme = wp_get_theme();

			WC_Admin_Notices::add_custom_notice(
				'wc_appointments_template_files',
				sprintf(
					/* translators: %1$s: Theme name, %2$d: Admin URL, %3$s: Link to template strucure docs */
					__( '<p><strong>Your theme (%1$s) contains outdated copies of some WooCommerce Appointments template files.</strong> These files may need updating to ensure they are compatible with the current version of WooCommerce Appointments. You can see which files are affected from the <a href="%2$s">system status page</a>. If in doubt, check with the author of the theme.<p><p class="submit"><a class="button-primary" href="%3$s" target="_blank">Learn More About Templates</a></p>', 'woocommerce-appointments' ),
					esc_html( $theme['Name'] ),
					esc_url( admin_url( 'admin.php?page=wc-status' ) ),
					esc_url( 'https://docs.woocommerce.com/document/template-structure/' )
				)
			);
		} else {
			WC_Admin_Notices::remove_notice( 'wc_appointments_template_files' );
		}
	}

	/**
	 * Override product type for New Product screen, if a request parameter is set.
	 *
	 * @param string $override Product Type
	 * @param int    $product_id
	 *
	 * @return string
	 */
	public function maybe_override_product_type( $override, $product_id ) {
		if ( ! empty( $_REQUEST['appointable_product'] ) ) {
			return 'appointment';
		}

		return $override;
	}
}

$GLOBALS['wc_appointments_admin'] = new WC_Appointments_Admin();
