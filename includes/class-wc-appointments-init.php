<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Initializes appointments.
 *
 * @since 4.3.4
 */
class WC_Appointments_Init {
	/**
	 * Constructor.
	 *
	 * @since 4.3.4
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'init_post_types' ), 9 );

		// Load payment gateway name.
		add_filter( 'woocommerce_payment_gateways', array( $this, 'include_gateway' ) );

		add_action( 'admin_init', array( $this, 'init_wc_admin_bar' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'appointment_form_styles' ), 99 ); #front
		add_action( 'wp_enqueue_scripts', array( $this, 'appointment_form_scripts' ), 99 ); #front
		add_action( 'admin_enqueue_scripts', array( $this, 'appointment_form_scripts' ), 99 ); #admin
		add_action( 'admin_enqueue_scripts', array( $this, 'appointment_admin_analytics' ), 99 ); #admin
		add_filter( 'woocommerce_data_stores', array( $this, 'register_data_stores' ) );
		add_filter( 'woocommerce_product_class', array( $this, 'woocommerce_product_class' ), 10, 2 );
		add_filter( 'woocommerce_locate_template', array( $this, 'woocommerce_locate_template' ), 10, 3 ); #For backward compatibility only.

		// Default date and time formats must not be empty.
		add_filter( 'woocommerce_date_format', array( $this, 'default_date_format' ) );
		add_filter( 'woocommerce_time_format', array( $this, 'default_time_format' ) );

		// Adjust appointment time to staff/customer timezone.
		#add_filter( 'woocommerce_appointments_get_start_date_with_time', array( $this, 'adjust_appointment_to_timezone' ), 15, 3 ); #lower priority
		#add_filter( 'woocommerce_appointments_get_end_date_with_time',  array( $this, 'adjust_appointment_to_timezone' ), 15, 3 ); #lower priority

		// Disable WooCommerce Bloat.
		add_filter( 'woocommerce_allow_marketplace_suggestions', '__return_false' );
		add_filter( 'woocommerce_admin_onboarding_product_types', array( $this, 'onboarding_product_types' ) );
	}

	/**
	 * Init post types
	 */
	public function init_post_types() {
		register_post_type(
			'wc_appointment',
			apply_filters(
				'woocommerce_register_post_type_wc_appointment',
				array(
					'label'               => __( 'Appointment', 'woocommerce-appointments' ),
					'labels'              => array(
						'name'               => __( 'Appointments', 'woocommerce-appointments' ),
						'singular_name'      => __( 'Appointment', 'woocommerce-appointments' ),
						'add_new'            => __( 'Add New', 'woocommerce-appointments' ),
						'add_new_item'       => __( 'Add New Appointment', 'woocommerce-appointments' ),
						'edit'               => __( 'Edit', 'woocommerce-appointments' ),
						'edit_item'          => __( 'Edit Appointment', 'woocommerce-appointments' ),
						'new_item'           => __( 'New Appointment', 'woocommerce-appointments' ),
						'view'               => __( 'View Appointment', 'woocommerce-appointments' ),
						'view_item'          => __( 'View Appointment', 'woocommerce-appointments' ),
						'search_items'       => __( 'Search Appointments', 'woocommerce-appointments' ),
						'not_found'          => __( 'No Appointments found', 'woocommerce-appointments' ),
						'not_found_in_trash' => __( 'No Appointments found in trash', 'woocommerce-appointments' ),
						'parent'             => __( 'Parent Appointments', 'woocommerce-appointments' ),
						'menu_name'          => _x( 'Appointments', 'Admin menu name', 'woocommerce-appointments' ),
						'all_items'          => __( 'All Appointments', 'woocommerce-appointments' ),
					),
					'description'         => __( 'This is where appointments are stored.', 'woocommerce-appointments' ),
					'public'              => false,
					'show_ui'             => true,
					'capability_type'     => 'appointment',
					#'menu_icon'           => 'dashicons-backup',
					'menu_icon'           => 'dashicons-clock',
					'map_meta_cap'        => true,
					'publicly_queryable'  => false,
					'exclude_from_search' => true,
					'show_in_menu'        => true,
					'hierarchical'        => false,
					'show_in_nav_menus'   => false,
					'rewrite'             => false,
					'query_var'           => false,
					'supports'            => array( '' ),
					'has_archive'         => false,
				)
			)
		);

		/**
		 * Post status
		 */
		register_post_status(
			'complete',
			array(
				'label'                     => '<span class="status-complete tips" data-tip="' . wc_sanitize_tooltip( _x( 'Complete', 'woocommerce-appointments', 'woocommerce-appointments' ) ) . '">' . _x( 'Complete', 'woocommerce-appointments', 'woocommerce-appointments' ) . '</span>',
				'public'                    => true,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				/* translators: %s: count */
				'label_count'               => _n_noop( 'Complete <span class="count">(%s)</span>', 'Complete <span class="count">(%s)</span>', 'woocommerce-appointments' ),
			)
		);
		register_post_status(
			'paid',
			array(
				'label'                     => '<span class="status-paid tips" data-tip="' . wc_sanitize_tooltip( _x( 'Paid &amp; Confirmed', 'woocommerce-appointments', 'woocommerce-appointments' ) ) . '">' . _x( 'Paid &amp; Confirmed', 'woocommerce-appointments', 'woocommerce-appointments' ) . '</span>',
				'public'                    => true,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				/* translators: %s: count */
				'label_count'               => _n_noop( 'Paid &amp; Confirmed <span class="count">(%s)</span>', 'Paid &amp; Confirmed <span class="count">(%s)</span>', 'woocommerce-appointments' ),
			)
		);
		register_post_status(
			'confirmed',
			array(
				'label'                     => '<span class="status-confirmed tips" data-tip="' . wc_sanitize_tooltip( _x( 'Confirmed', 'woocommerce-appointments', 'woocommerce-appointments' ) ) . '">' . _x( 'Confirmed', 'woocommerce-appointments', 'woocommerce-appointments' ) . '</span>',
				'public'                    => true,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				/* translators: %s: count */
				'label_count'               => _n_noop( 'Confirmed <span class="count">(%s)</span>', 'Confirmed <span class="count">(%s)</span>', 'woocommerce-appointments' ),
			)
		);
		register_post_status(
			'unpaid',
			array(
				'label'                     => '<span class="status-unpaid tips" data-tip="' . wc_sanitize_tooltip( _x( 'Un-paid', 'woocommerce-appointments', 'woocommerce-appointments' ) ) . '">' . _x( 'Un-paid', 'woocommerce-appointments', 'woocommerce-appointments' ) . '</span>',
				'public'                    => true,
				'exclude_from_search'       => true,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				/* translators: %s: count */
				'label_count'               => _n_noop( 'Un-paid <span class="count">(%s)</span>', 'Un-paid <span class="count">(%s)</span>', 'woocommerce-appointments' ),
			)
		);
		register_post_status(
			'pending-confirmation',
			array(
				'label'                     => '<span class="status-pending tips" data-tip="' . wc_sanitize_tooltip( _x( 'Pending Confirmation', 'woocommerce-appointments', 'woocommerce-appointments' ) ) . '">' . _x( 'Pending Confirmation', 'woocommerce-appointments', 'woocommerce-appointments' ) . '</span>',
				'public'                    => true,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				/* translators: %s: count */
				'label_count'               => _n_noop( 'Pending Confirmation <span class="count">(%s)</span>', 'Pending Confirmation <span class="count">(%s)</span>', 'woocommerce-appointments' ),
			)
		);
		register_post_status(
			'cancelled',
			array(
				'label'                     => '<span class="status-cancelled tips" data-tip="' . wc_sanitize_tooltip( _x( 'Cancelled', 'woocommerce-appointments', 'woocommerce-appointments' ) ) . '">' . _x( 'Cancelled', 'woocommerce-appointments', 'woocommerce-appointments' ) . '</span>',
				'public'                    => true,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				/* translators: %s: count */
				'label_count'               => _n_noop( 'Cancelled <span class="count">(%s)</span>', 'Cancelled <span class="count">(%s)</span>', 'woocommerce-appointments' ),
			)
		);
		register_post_status(
			'in-cart',
			array(
				'label'                     => '<span class="status-incart tips" data-tip="' . wc_sanitize_tooltip( _x( 'In Cart', 'woocommerce-appointments', 'woocommerce-appointments' ) ) . '">' . _x( 'In Cart', 'woocommerce-appointments', 'woocommerce-appointments' ) . '</span>',
				'public'                    => false,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				/* translators: %s: count */
				'label_count'               => _n_noop( 'In Cart <span class="count">(%s)</span>', 'In Cart <span class="count">(%s)</span>', 'woocommerce-appointments' ),
			)
		);
		register_post_status(
			'was-in-cart',
			array(
				'label'                     => false,
				'public'                    => false,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => false,
				'label_count'               => false,
			)
		);

	}

	/**
	 * Register WooCommerce admin bar for appointments.
	 *
	 * @since 4.9.0
	 */
	public function init_wc_admin_bar() {
		if ( ! current_user_can( 'manage_others_appointments' ) ) {
			return;
		}

		if ( function_exists( 'wc_admin_connect_page' ) ) {
			$posttype_list_base = 'edit.php';

			// WooCommerce > Appointments.
		    wc_admin_connect_page(
		        array(
				    'id'        => 'woocommerce-appointments',
				    'screen_id' => 'edit-wc_appointment',
				    'title'     => __( 'Appointments', 'woocommerce-appointments' ),
					'path'      => add_query_arg( 'post_type', 'wc_appointment', $posttype_list_base ),
				)
		    );

			// WooCommerce > Appointments > Export Appointments.
		    wc_admin_connect_page(
				array(
					'id'        => 'woocommerce-export-appointments',
					'parent'    => 'woocommerce-appointments',
					'screen_id' => 'wc_appointment_page_appointment_exporter',
					'title'     => __( 'Export Appointments', 'woocommerce-appointments' ),
				)
		    );

			// WooCommerce > Appointments > Add New.
		    wc_admin_connect_page(
		        array(
				    'id'        => 'woocommerce-add-appointment',
					'parent'    => 'woocommerce-appointments',
				    'screen_id' => 'wc_appointment_page_add_appointment',
				    'title'     => __( 'Add New', 'woocommerce-appointments' ),
				)
		    );

			// WooCommerce > Appointments > Edit Appointment.
		    wc_admin_connect_page(
		        array(
				    'id'        => 'woocommerce-edit-appointment',
					'parent'    => 'woocommerce-appointments',
				    'screen_id' => 'wc_appointment',
				    'title'     => __( 'Edit Appointment', 'woocommerce-appointments' ),
				)
		    );

			// WooCommerce > Appointments > Calendar.
		    wc_admin_connect_page(
		        array(
				    'id'        => 'woocommerce-appointments-calendar',
					'parent'    => 'woocommerce-appointments',
				    'screen_id' => 'wc_appointment_page_appointment_calendar',
				    'title'     => __( 'Calendar', 'woocommerce-appointments' ),
				)
		    );
		}
	}

	/**
	 * Register data stores for appointments.
	 *
	 * @param  array  $data_stores
	 * @return array
	 */
	public function register_data_stores( $data_stores = [] ) {
		$data_stores['appointment']               = 'WC_Appointment_Data_Store';
		$data_stores['appointments-availability'] = 'WC_Appointments_Availability_Data_Store';
		$data_stores['product-appointment']       = 'WC_Product_Appointment_Data_Store_CPT';

		return $data_stores;
	}

	/**
	 * Checks the classname being used for a appointable product type to see if it should be an appointable product
	 * and if so, returns this as the class which should be instantiated (instead of the default
	 * WC_Product_Simple class).
	 *
	 * @return string $classname The name of the WC_Product_* class which should be instantiated to create an instance of this product.
	 * @since 3.9.4
	 */
	public function woocommerce_product_class( $classname, $product_type ) {
	    if ( 'appointment' === $product_type ) {
	        $classname = 'WC_Product_Appointment';
	    }

	    return $classname;
	}

	/**
	 * Backdrop to deprecated templates inside 'woocommerce-appointments' theme folder
	 *
	 * Will be removed in later versions
	 *
	 * @deprecated
	 */
	public function woocommerce_locate_template( $template, $template_name, $template_path ) {
		$deprecated_template_path = 'woocommerce-appointments';

		$deprecated_template = locate_template(
			array(
				trailingslashit( $deprecated_template_path ) . $template_name,
				$template_name,
			)
		);

		if ( $deprecated_template ) {
			return $deprecated_template;
		}

		return $template;
	}

	/**
	 * Add a custom payment gateway
	 * This gateway works with appointments that require confirmation.
	 * It's only needed on the front-end so we make sure we hide
	 * that gateway in the admin, as it has no options to configure.
	 */
	public function include_gateway( $gateways ) {
		$page = isset( $_GET['page'] ) ? wc_clean( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$tab  = isset( $_GET['tab'] ) ? wc_clean( wp_unslash( $_GET['tab'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		if ( is_admin() && 'wc-settings' === $page && 'checkout' === $tab ) {
			return $gateways;
		}

		// Hide the gateway on the payment providers API request (New react-based payments settings page), as it has no options to configure.
		if ( $this->is_payment_providers_api_request() ) {
			return $gateways;
		}

		$gateways[] = 'WC_Appointments_Gateway';

		return $gateways;
	}

	/**
	 * Date format should not be zero.
	 *
	 * @since 4.2.7
	 */
	public function default_date_format( $format ) {
		if ( '' === $format ) {
			return 'F j, Y';
		}

		return $format;
	}

	/**
	 * Time format should not be zero.
	 *
	 * @since 4.2.7
	 */
	public function default_time_format( $format ) {
		if ( '' === $format ) {
			return 'g:i a';
		}

		return $format;
	}

	/**
	 * Adjust appointment time to staff/customer timezone.
	 *
	 * @since 4.9.3
	 *
	 * @param  string $timestring  Start or End time string.
	 * @param  object $appointment WC_Appointment object.
	 * @param  int    $timestamp   Start or End time stamp.
	 *
	 * @return string Date/time string.
	 */
	public function adjust_appointment_to_timezone( $timestring, $appointment, $timestamp ) {
		if ( is_user_logged_in() ) {
			$user           = wp_get_current_user();
			$current_offset = get_option( 'gmt_offset' );
			$tzstring_site  = get_option( 'timezone_string' );
			$tzstring       = get_user_meta( $user->ID, 'timezone_string', true );
			$tzstring       = $tzstring ? $tzstring : $tzstring_site;
			$date_format    = wc_appointments_date_format();
			$time_format    = ', ' . wc_appointments_time_format();
			$roles          = (array) $user->roles;

			#print '<pre>'; print_r( $roles ); print '</pre>';

			if ( in_array( 'shop_staff', $roles ) ) {
				$start_date = wc_appointment_timezone_locale( 'site', 'user', $timestamp, 'U', $tzstring );
				$timestring = date_i18n( $date_format . $time_format, $start_date ) . ' (' . wc_appointment_get_timezone_name( $tzstring ) . ')';
			} else {
				return $timestring;
			}
		}

		return $timestring;
	}

	/**
	 * Adjust products in the onbarding screen.
	 *
	 * @since 4.10.1
	 *
	 * @param  array $product_types  Product type in the onbarding screen.
	 *
	 * @return array $product_types.
	 */
	public function onboarding_product_types( $product_types ) {
		// Remove Bookings from the onboarding.
		if ( isset( $product_types['bookings'] ) ) {
			unset( $product_types['bookings'] );
		}

		// Remove Product Add-ons from the onboarding.
		if ( isset( $product_types['product-add-ons'] ) ) {
			unset( $product_types['product-add-ons'] );
		}

		return $product_types;
	}

	/**
	 * Appointment form styles
	 */
	public static function appointment_form_styles() {
		if ( ! wp_style_is( 'woocommerce-addons-css', 'registered' ) ) {
			wp_register_style(
				'woocommerce-addons-css',
				WC_APPOINTMENTS_PLUGIN_URL . '/includes/integrations/woocommerce-product-addons/assets/css/frontend/frontend.css',
				null,
				WC_APPOINTMENTS_VERSION
			);
		}
		wp_register_style(
			'wc-appointments-styles',
			WC_APPOINTMENTS_PLUGIN_URL . '/assets/css/frontend.css',
			array(
				'woocommerce-addons-css',
			),
			WC_APPOINTMENTS_VERSION
		);

		// Enqueue styles.
		if ( ! wp_style_is( 'select2', 'enqueued' ) ) {
			wp_enqueue_style( 'select2' );
		}
		wp_enqueue_style( 'wc-appointments-styles' );
	}

	/**
	 * Appointment form scripts
	 */
	public static function appointment_form_scripts() {
		// JS suffix.
		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		wp_register_script(
			'wc-appointments-appointment-form',
			WC_APPOINTMENTS_PLUGIN_URL . '/assets/js/appointment-form' . $suffix . '.js',
			[
				'jquery',
				'jquery-blockui',
			],
			WC_APPOINTMENTS_VERSION,
			true
		);
		wp_register_script(
			'wc-appointments-month-picker',
			WC_APPOINTMENTS_PLUGIN_URL . '/assets/js/month-picker' . $suffix . '.js',
			[
				'wc-appointments-appointment-form',
			],
			WC_APPOINTMENTS_VERSION,
			true
		);
		wp_register_script(
			'wc-appointments-date-picker',
			WC_APPOINTMENTS_PLUGIN_URL . '/assets/js/date-picker' . $suffix . '.js',
			[
				'wc-appointments-appointment-form',
				'wc-appointments-rrule',
				'wc-appointments-moment',
				'jquery-ui-datepicker',
				'underscore',
			],
			WC_APPOINTMENTS_VERSION,
			true
		);
		wp_register_script(
			'wc-appointments-time-picker',
			WC_APPOINTMENTS_PLUGIN_URL . '/assets/js/time-picker' . $suffix . '.js',
			[
				'wc-appointments-appointment-form',
			],
			WC_APPOINTMENTS_VERSION,
			true
		);
		wp_register_script(
			'wc-appointments-timezone-picker',
			WC_APPOINTMENTS_PLUGIN_URL . '/assets/js/timezone-picker' . $suffix . '.js',
			[
				'wc-appointments-appointment-form',
			],
			WC_APPOINTMENTS_VERSION,
			true
		);
		wp_register_script(
			'wc-appointments-staff-picker',
			WC_APPOINTMENTS_PLUGIN_URL . '/assets/js/staff-picker' . $suffix . '.js',
			[
				'wc-appointments-appointment-form',
			],
			WC_APPOINTMENTS_VERSION,
			true
		);
		wp_register_script(
			'wc-appointments-moment',
			WC_APPOINTMENTS_PLUGIN_URL . '/assets/js/moment/moment-with-locales' . $suffix . '.js',
			[],
			WC_APPOINTMENTS_VERSION,
			true
		);
		wp_register_script(
			'wc-appointments-moment-timezone',
			WC_APPOINTMENTS_PLUGIN_URL . '/assets/js/moment/moment-timezone-with-data' . $suffix . '.js',
			[],
			WC_APPOINTMENTS_VERSION,
			true
		);
		wp_register_script(
			'wc-appointments-rrule',
			WC_APPOINTMENTS_PLUGIN_URL . '/assets/js/rrule/rrule' . $suffix . '.js',
			[],
			WC_APPOINTMENTS_VERSION,
			true
		);
		wp_register_script(
			'wc-appointments-rrule-tz',
			WC_APPOINTMENTS_PLUGIN_URL . '/assets/js/rrule/rrule-tz' . $suffix . '.js',
			[],
			WC_APPOINTMENTS_VERSION,
			true
		);
		wp_register_script(
			'wc-appointments-my-account',
			WC_APPOINTMENTS_PLUGIN_URL . '/assets/js/my-account' . $suffix . '.js',
			[],
			WC_APPOINTMENTS_VERSION,
			true
		);
		wp_register_script(
			'wc_appointments_analytics_script',
			WC_APPOINTMENTS_PLUGIN_URL . '/assets/js/appointment-analytics.js',
			[
				'wp-i18n',
			],
			WC_APPOINTMENTS_VERSION,
			true
		);

		// Move "Select2" above "selectWoo".
		// Remove once WooCommerce fixes the html rendering issues.
		if ( function_exists( 'get_current_screen' ) ) {
			// Get current screen.
			$screen    = get_current_screen();
			$screen_id = $screen ? $screen->id : '';

			if ( wp_script_is( 'selectWoo', 'enqueued' ) && 'wc_appointment_page_add_appointment' === $screen_id ) {
				wp_deregister_script( 'selectWoo' );
				wp_register_script(
					'selectWoo',
					WC()->plugin_url() . '/assets/js/selectWoo/selectWoo.full' . $suffix . '.js',
					[
						'select2',
					],
					WC_APPOINTMENTS_VERSION,
					true
				);
	        }
		}

		// Get $wp_locale.
		$wp_locale = new WP_Locale();

		// Variables for JS scripts
		$appointment_form_params = array(
			'nonce_find_day_slots'          => wp_create_nonce( 'find-scheduled-day-slots' ),
			'nonce_set_timezone_cookie'     => wp_create_nonce( 'set-timezone-cookie' ),
			'nonce_staff_html'              => wp_create_nonce( 'appointable-staff-html' ),
			'closeText'                     => esc_js( __( 'Close', 'woocommerce-appointments' ) ),
			'currentText'                   => esc_js( __( 'Today', 'woocommerce-appointments' ) ),
			'prevText'                      => esc_js( __( 'Previous', 'woocommerce-appointments' ) ),
			'nextText'                      => esc_js( __( 'Next', 'woocommerce-appointments' ) ),
			'monthNames'                    => array_values( $wp_locale->month ),
			'monthNamesShort'               => array_values( $wp_locale->month_abbrev ),
			'dayNames'                      => array_values( $wp_locale->weekday ),
			'dayNamesShort'                 => array_values( $wp_locale->weekday_abbrev ),
			'dayNamesMin'                   => array_values( $wp_locale->weekday_initial ),
			'firstDay'                      => get_option( 'start_of_week' ),
			'current_time'                  => date( 'Ymd', current_time( 'timestamp' ) ),
			'showWeek'                      => false,
			'showOn'                        => false,
			'numberOfMonths'                => 1,
			'showButtonPanel'               => false,
			'showOtherMonths'               => true,
			'selectOtherMonths'             => true,
			'gotoCurrent'                   => true,
			'changeMonth'                   => false,
			'changeYear'                    => false,
			'duration_changed'              => false,
			'default_availability'          => false,
			'costs_changed'                 => false,
			'cache_ajax_requests'           => 'false',
			'ajax_url'                      => admin_url( 'admin-ajax.php' ),
			'i18n_date_unavailable'         => esc_js( __( 'Selected date is unavailable', 'woocommerce-appointments' ) ),
			'i18n_date_invalid'             => esc_js( __( 'Selected date is not valid', 'woocommerce-appointments' ) ),
			'i18n_time_unavailable'         => esc_js( __( 'Selected time is unavailable', 'woocommerce-appointments' ) ),
			'i18n_date_fully_scheduled'     => esc_js( __( 'Selected date is fully scheduled and unavailable', 'woocommerce-appointments' ) ),
			'i18n_date_partially_scheduled' => esc_js( __( 'Selected date is partially scheduled - but appointments still remain', 'woocommerce-appointments' ) ),
			'i18n_date_available'           => esc_js( __( 'Selected date is available', 'woocommerce-appointments' ) ),
			'i18n_start_date'               => esc_js( __( 'Choose a Start Date', 'woocommerce-appointments' ) ),
			'i18n_end_date'                 => esc_js( __( 'Choose an End Date', 'woocommerce-appointments' ) ),
			'i18n_dates'                    => esc_js( __( 'Dates', 'woocommerce-appointments' ) ),
			'i18n_clear_date_selection'     => esc_js( __( 'To clear selection, pick a new start date', 'woocommerce-appointments' ) ),
			'i18n_confirmation'             => esc_js( __( 'Are you sure?', 'woocommerce-appointments' ) ),
			'is_admin'                      => is_admin(),
			'isRTL'                         => is_rtl(),
			'server_timezone'               => wc_timezone_string(),
			// 'server_time_format'            => wc_appointments_convert_to_moment_format( wc_appointments_time_format() ),
			// 'product_id'                    => $this->product->get_id(),
		);

		// Variables for JS scripts
		$appointment_analytics_params = array(
			'i18n_appointable_products' => esc_js( __( 'Appointable products', 'woocommerce-appointments' ) ),
			'i18n_staff'                => esc_js( __( 'Staff', 'woocommerce-appointments' ) ),
		);

		$wc_appointments_date_picker_args = array(
			'ajax_url' => WC_Ajax_Compat::get_endpoint( 'wc_appointments_find_scheduled_day_slots' ),
		);

		$wc_appointments_timezone_picker_args = array(
			'ajax_url' => WC_Ajax_Compat::get_endpoint( 'wc_appointments_set_timezone_cookie' ),
		);

		wp_localize_script(
			'wc-appointments-appointment-form',
			'wc_appointment_form_params',
			apply_filters(
				'wc_appointment_form_params',
				$appointment_form_params
			)
		);
		wp_localize_script(
			'wc-appointments-date-picker',
			'wc_appointments_date_picker_args',
			$wc_appointments_date_picker_args
		);
		wp_localize_script(
			'wc-appointments-timezone-picker',
			'wc_appointments_timezone_picker_args',
			$wc_appointments_timezone_picker_args
		);
		wp_localize_script(
			'wc-appointments-my-account',
			'wc_appointments_my_account_params',
			apply_filters(
				'wc_appointment_form_params',
				$appointment_form_params
			)
		);
		wp_localize_script(
			'wc_appointments_analytics_script',
			'wc_appointments_analytics_params',
			apply_filters(
				'wc_appointment_form_params',
				$appointment_analytics_params
			)
		);
	}

	/**
	 * Register/enqueue admin dependencies.
	 *
	 * @since 4.20.0
	 */
	public function appointment_admin_analytics() {
		// Enqueue Analytics JS.
		wp_enqueue_script( 'wc_appointments_analytics_script' );
	}

	/**
	 * Attempt to convert a date formatting string from PHP to Moment
	 *
	 * @param string $format
	 * @return string
	 */
	protected function convert_to_moment_format( $format ) {
		wc_deprecated_function( __METHOD__, '4.7.0', 'wc_appointments_convert_to_moment_format' );
		return wc_appointments_convert_to_moment_format( $format );
	}

	/**
	 * Returns true if the request is a payment settings providers REST API request.
	 *
	 * @return bool
	 */
	public function is_payment_providers_api_request() {
		if ( empty( $_SERVER['REQUEST_URI'] ) ) {
			return false;
		}
		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		return false !== strpos( $_SERVER['REQUEST_URI'], trailingslashit( rest_get_url_prefix() ) . 'wc-admin/settings/payments/providers' );
	}
}

new WC_Appointments_Init();
