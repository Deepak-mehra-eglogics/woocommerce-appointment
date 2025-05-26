<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Polylang for WooCommerce integration class.
 *
 * Last compatibility check: 2.1.4
 */
class WC_Appointments_Integration_Polylang {
	/**
	 * Stores if the locale has been switched.
	 *
	 * @var bool
	 */
	private $switched_locale;

	/**
	 * Constructor.
	 * Setups actions and filters.
	 *
	 * @since 0.6
	 */
	public function __construct() {
		// Post types.
		add_filter( 'pll_get_post_types', [ $this, 'translate_types' ], 10, 2 );

		// Don't go further if we stopped loading the plugin early ( for example when deactivate-polylang=1 ).
		if ( ! function_exists( 'PLL' ) ) {
			return;
		}

		if ( PLL() instanceof PLL_Admin ) {
			add_action( 'wp_loaded', [ $this, 'custom_columns' ], 20 );
			add_action( 'add_meta_boxes', [ $this, 'add_meta_boxes' ], 20 );
		}

		// Statuses.
		$statuses = [
			'unpaid',
			'paid',
			'pending-confirmation',
			'confirmed',
			'cancelled',
			'complete',
			'was-in-cart',
			'in-cart',
		];
		foreach ( $statuses as $status ) {
			add_action( 'woocommerce_appointment_' . $status, [ $this, 'before_appointment_metabox_save' ] );
		}

		add_action( 'woocommerce_appointment_process_meta', [ $this, 'after_appointment_metabox_save' ] );

		// Create appointment.
		add_action( 'woocommerce_new_appointment', [ $this, 'new_appointment' ], 1 );

		// Hooked just before WC_Appointment_Cart_Manager::order_item_meta.
		add_action( 'woocommerce_new_order_item', array( $this, 'set_appointment_language_at_checkout' ), 49, 2 );

		// Products.
		add_action( 'pllwc_copy_product', [ $this, 'copy_providers' ], 10, 3 );
		add_action( 'pllwc_copy_product', [ $this, 'copy_availabilities' ], 10, 3 );
		add_action( 'wp_ajax_woocommerce_remove_appointable_staff', [ $this, 'remove_appointable_staff' ], 5 ); // Before WooCommerce Appointments.

		add_action( 'pll_save_post', [ $this, 'save_post' ], 10, 3 );
		add_filter( 'update_post_metadata', [ $this, 'update_post_metadata' ], 99, 4 ); // After Yoast SEO which returns null at priority 10. See https://github.com/Yoast/wordpress-seo/pull/6902.
		add_filter( 'get_post_metadata', [ $this, 'get_post_metadata' ], 10, 4 );
		add_filter( 'pll_copy_post_metas', [ $this, 'copy_post_metas' ] );
		add_filter( 'pll_post_metas_to_export', array( $this, 'get_metas_to_translate' ), 10 );

		// Cart.
		add_filter( 'pllwc_translate_cart_item', [ $this, 'translate_cart_item' ], 10, 2 );
		add_filter( 'pllwc_add_cart_item_data', [ $this, 'add_cart_item_data' ], 10, 2 );

		// Add e-mails for translation.
		add_filter( 'pllwc_order_email_actions', [ $this, 'filter_order_email_actions' ] );

		add_action( 'parse_query', [ $this, 'filter_appointments_notifications' ] );

		// Endpoints in emails.
		if ( isset( PLL()->translate_slugs ) ) {
			add_action( 'pllwc_email_language', [ PLL()->translate_slugs->slugs_model, 'init_translated_slugs' ] );
		}

		// Appointments endpoint.
		add_filter( 'pll_translation_url', [ $this, 'pll_translation_url' ], 10, 2 );
		add_filter( 'woocommerce_get_query_vars', array( $this, 'get_endpoints_query_vars' ) );

		if ( PLL() instanceof PLL_Frontend ) {
			add_action( 'parse_query', [ $this, 'parse_query' ], 3 ); // Before Polylang (for orders).
		}
	}

	/**
	 * Add Appointment e-mails in the translation mechanism.
	 *
	 * @since 4.15.0
	 *
	 * @param string[] $actions Array of actions used to send emails.
	 * @return string[]
	 */
	public function filter_order_email_actions( $actions ) {
		return array_merge(
			$actions,
			[
				// Cancelled appointment.
				'woocommerce_appointment_pending-confirmation_to_cancelled_notification',
				'woocommerce_appointment_confirmed_to_cancelled_notification',
				'woocommerce_appointment_paid_to_cancelled_notification',
				// Appointment confirmed.
				'wc-appointment-confirmed',
				// Reminder.
				'wc-appointment-reminder',
				// Follow-up.
				'wc-appointment-follow-up',
				// New appointment.
				'woocommerce_admin_new_appointment_notification',
			]
		);
	}

	/**
	 * Language and translation management for custom post types.
	 * Hooked to the filter 'pll_get_post_types'.
	 *
	 * @since 0.6
	 *
	 * @param array $types List of post type names for which Polylang manages language and translations.
	 * @param bool  $hide  True when displaying the list in Polylang settings.
	 *
	 * @return array List of post type names for which Polylang manages language and translations.
	 */
	public function translate_types( $types, $hide ) {
		$wc_appointments_types = [
			'wc_appointment',
		];

		return $hide ? array_diff( $types, $wc_appointments_types ) : array_merge( $types, $wc_appointments_types );
	}

	/**
	 * Removes the standard languages columns for appointments
	 * and replaces them with one unique column as for orders.
	 * Hooked to the action 'wp_loaded'.
	 *
	 * @since 0.6
	 *
	 * @return void
	 */
	public function custom_columns() {
		remove_filter( 'manage_edit-wc_appointment_columns', [ PLL()->filters_columns, 'add_post_column' ], 100 );
		remove_action( 'manage_wc_appointment_posts_custom_column', [ PLL()->filters_columns, 'post_column' ], 10, 2 );

		add_filter( 'manage_edit-wc_appointment_columns', [ PLLWC()->admin_orders, 'add_order_column' ], 100 );
		add_action( 'manage_wc_appointment_posts_custom_column', [ PLLWC()->admin_orders, 'order_column' ], 10, 2 );

		// @FIXME Add a filter in PLLWC for the position of the column?
	}

	/**
	 * Removes the language metabox for appointments
	 * Hooked to the action 'add_meta_boxes'.
	 *
	 * @since 0.6
	 *
	 * @param string $post_type Post type.
	 * @return void
	 */
	public function add_meta_boxes( $post_type ) {
		if ( 'wc_appointment' === $post_type ) {
			remove_meta_box( 'ml_box', $post_type, 'side' ); // Remove Polylang metabox.
		}
	}

	/**
	 * Reloads the WooCommerce Appointments and WP text domains to work around localized appointments meta.
	 * Hooked to the actions 'woocommerce_appointment_{$status}'.
	 *
	 * @since 0.6
	 *
	 * @param int $post_id Appointment ID.
	 * @return void
	 */
	public function before_appointment_metabox_save( $post_id ) {
		if ( isset( $_POST['post_type'], $_POST['wc_appointments_details_meta_box_nonce'] ) && 'wc_appointment' === $_POST['post_type'] ) {  // phpcs:ignore WordPress.Security.NonceVerification
			$appointment_locale    = pll_get_post_language( $post_id, 'locale' );
			$this->switched_locale = switch_to_locale( $appointment_locale );
		}
	}

	/**
	 * Reloads the WooCommerce Appointments and WP text domains to work around localized appointments meta.
	 * Part of the workaround for localized appointments meta keys.
	 * Hooked to the action 'woocommerce_appointment_process_meta'.
	 *
	 * @since 0.6
	 *
	 * @return void
	 */
	public function after_appointment_metabox_save() {
		if ( $this->switched_locale ) {
			unset( $this->switched_locale );
			restore_previous_locale();
		}
	}

	/**
	 * Assigns the appointment and order languages when creating a new appointment from the backend.
	 * Hooked to the action 'woocommerce_new_appointment'.
	 *
	 * @since 0.6
	 *
	 * @param int $appointment_id Appointment ID.
	 * @return void
	 */
	public function new_appointment( $appointment_id ) {
		$data_store = PLLWC_Data_Store::load( 'product_language' );

		$appointment = get_wc_appointment( $appointment_id );
		$lang        = $data_store->get_language( $appointment->product_id );
		pll_set_post_language( $appointment->id, $lang );

		if ( ! empty( $appointment->order_id ) ) {
			$data_store = PLLWC_Data_Store::load( 'order_language' );
			$data_store->set_language( $appointment->order_id, $lang );
		}
	}

	/**
	 * Assigns the right appointment language.
	 * In case a visitor adds the product to cart in a language
	 * and then switches the language before he completes the checkout.
	 * Hooked to the action 'woocommerce_new_order_item'.
	 *
	 * @since 4.20.1
	 *
	 * @param int                 $item_id     An order item ID.
	 * @param WC_Order_Item|false $order_item  Order item object.
	 * @return int
	 */
	public function set_appointment_language_at_checkout( $item_id, $order_item ) {
		if ( empty( $order_item->legacy_values ) || ! is_array( $order_item->legacy_values ) || empty( $order_item->legacy_values['appointment'] ) ) {
			return $item_id;
		}
		$appointment_id = $order_item->legacy_values['appointment']['_appointment_id'];

		$lang = pll_current_language();
		if ( ! empty( $lang ) && pll_get_post_language( $appointment_id ) !== $lang ) {
			pll_set_post_language( $appointment_id, $lang );
		}

		return $item_id;
	}

	/**
	 * Copies or synchronizes appointable posts (resource, person).
	 *
	 * @since 0.6
	 *
	 * @param array  $post Appointable post to copy (person or resource).
	 * @param int    $to   ID of the product to which we paste information.
	 * @param string $lang Language slug.
	 * @return int Translated appointable post.
	 */
	protected function copy_appointable_post( $post, $to, $lang ) {
		$id    = $post['ID'];
		$tr_id = pll_get_post( $id, $lang );

		if ( $tr_id ) {
			// If the translation already exists, make sure it has the right post_parent.
			$post = get_post( $tr_id );
			if ( $post->post_parent !== $to ) {
				wp_update_post(
					[
						'ID'          => $tr_id,
						'post_parent' => $to,
					]
				);
			}
		}

		// Synchronize metas.
		PLL()->sync->post_metas->copy( $id, $tr_id, $lang );

		return $tr_id;
	}

	/**
	 * Copy or synchronize providers
	 * Hooked to the action 'pllwc_copy_product'.
	 *
	 * @since 0.6
	 *
	 * @param int    $from ID of the product from which we copy information.
	 * @param int    $to   ID of the product to which we paste information.
	 * @param string $lang language slug.
	 * @return void
	 */
	public function copy_providers( $from, $to, $lang ) {
		global $wpdb;

		$relationships = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT *
					FROM {$wpdb->prefix}wc_appointment_relationships
					WHERE product_id = %d",
				$from
			),
			ARRAY_A
		);

		foreach ( $relationships as $relationship ) {
			$tr_staff_id   = $relationship['staff_id'];
			$tr_sort_order = $relationship['sort_order'];
			if ( ! $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}wc_appointment_relationships WHERE product_id = %d AND staff_id = %d", $to, $tr_staff_id ) ) ) {
				unset( $relationship['ID'] );
				$relationship['product_id'] = $to;
				$wpdb->insert(
					"{$wpdb->prefix}wc_appointment_relationships",
					$relationship
				);
			} else {
				$wpdb->update(
					"{$wpdb->prefix}wc_appointment_relationships",
					[
						'sort_order' => $tr_sort_order,
					],
					[
						'product_id' => $to,
						'staff_id'   => $tr_staff_id,
					]
				);
			}
		}
	}

	/**
	 * Copy or synchronize avaialbility rules
	 * Hooked to the action 'pllwc_copy_product'.
	 *
	 * @since 0.6
	 *
	 * @param int    $from id of the product from which we copy informations.
	 * @param int    $to   id of the product to which we paste informations.
	 * @param string $lang language slug.
	 * @return void
	 */
	public function copy_availabilities( $from, $to, $lang ) {
		global $wpdb;

		$availabilities = $wpdb->get_results(
			$wpdb->prepare(
            	"SELECT *
					FROM {$wpdb->prefix}wc_appointments_availability
					WHERE kind_id = %d",
				$from
			),
			ARRAY_A
		);

		foreach ( $availabilities as $availability ) {
			$tr_sort_order = $availability['ordering'];
			if ( ! $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}wc_appointments_availability WHERE kind_id = %d", $to ) ) ) {
				unset( $availability['ID'] );
				$availability['kind_id'] = $to;
				$wpdb->insert(
					"{$wpdb->prefix}wc_appointments_availability",
					$availability
				);
			} else {
				$wpdb->update(
					"{$wpdb->prefix}wc_appointments_availability",
					[
						'ordering' => $tr_sort_order,
					],
					[
						'kind_id' => $to,
					]
				);
			}
		}
	}

	/**
	 * Removes providers in translated products when a staff is removed in Ajax.
	 * Hooked to the action 'wp_ajax_woocommerce_remove_appointable_staff'.
	 *
	 * Remove providers in translated products when a staff is removed in Ajax.
	 *
	 * @since 0.6
	 *
	 * @return void
	 */
	public function remove_appointable_staff() {
		global $wpdb;

		check_ajax_referer( 'delete-appointable-staff', 'security' );

		if ( isset( $_POST['post_id'], $_POST['staff_id'] ) ) {
			$product_id = absint( $_POST['post_id'] );
			$staff_id   = absint( $_POST['staff_id'] );

			$data_store = PLLWC_Data_Store::load( 'product_language' );

			foreach ( $data_store->get_translations( $product_id ) as $lang => $tr_id ) {
				if ( $tr_id !== $product_id ) { // Let WooCommerce delete the current relationship
					$tr_staff_id = pll_get_post( $staff_id, $lang );

					$wpdb->delete(
						"{$wpdb->prefix}wc_appointment_relationships",
						[
							'product_id' => $tr_id,
							'staff_id'   => $tr_staff_id,
						]
					);
				}
			}
		}
	}

	/**
	 * Add appointments metas when creating a new product or staff.
	 *
	 * @since 0.9.3
	 *
	 * @param int    $post_id      New product or staff.
	 * @param array  $translations Existing product or staff translations.
	 * @param string $meta_key     Meta to add to the appointment.
	 * @return void
	 */
	protected function add_metas_to_appointment( $post_id, $translations, $meta_key ) {
		global $wpdb;

		if ( ! empty( $translations ) ) { // If there is no translation, the query returns all appointments!
			$query_translations = new WP_Query(
				[
					'fields'      => 'ids',
					'post_type'   => 'wc_appointment',
					'numberposts' => -1,
					'nopaging'    => true, // phpcs:ignore WordPressVIPMinimum.Performance.NoPaging.nopaging_nopaging
					'lang'        => '',
					'meta_query'  => [
						[
							'key'     => $meta_key,
							'value'   => $translations,
							'compare' => 'IN',
						],
					],
				]
			);

			$query_current = new WP_Query(
				[
					'fields'      => 'ids',
					'post_type'   => 'wc_appointment',
					'numberposts' => -1,
					'nopaging'    => true, // phpcs:ignore WordPressVIPMinimum.Performance.NoPaging.nopaging_nopaging
					'lang'        => '',
					'meta_query'  => [
						[
							'key'     => $meta_key,
							'value'   => [ $post_id ],
							'compare' => 'IN',
						],
					],
				]
			);

			$appointment_ids = array_diff( $query_translations->posts, $query_current->posts );

			if ( ! empty( $appointment_ids ) ) {
				$values = [];

				foreach ( $appointment_ids as $appointment ) {
					$values[] = $wpdb->prepare( '( %d, %s, %d )', $appointment, $meta_key, $post_id );
				}

				$wpdb->query( "INSERT INTO {$wpdb->postmeta} ( post_id, meta_key, meta_value ) VALUES " . implode( ',', $values ) ); // // PHPCS:ignore WordPress.DB.PreparedSQL.NotPrepared
			}
		}
	}

	/**
	 * Updates the appointments associated to the translated products
	 * when creating a new product translation.
	 * Hooked to the action 'pll_save_post'.
	 *
	 * @since 0.9.3
	 *
	 * @param int     $post_id      Post id.
	 * @param WP_Post $post         Post object.
	 * @param array   $translations Post translations.
	 * @return void
	 */
	public function save_post( $post_id, $post, $translations ) {
		$translations = array_diff( $translations, [ $post_id ] );

		if ( 'product' === $post->post_type ) {
			$this->add_metas_to_appointment( $post_id, $translations, '_appointment_product_id' );
		}
	}
	/**
	 * Allows to associate several products or staff to an appointment.
	 * Hooked to the filter 'update_post_metadata'.
	 *
	 * @since 0.6
	 *
	 * @param null|bool  $r          Returned value (null by default).
	 * @param int        $post_id    Appointment ID.
	 * @param string     $meta_key   Meta key.
	 * @param int|string $meta_value Meta value.
	 *
	 * @return null|bool
	 */
	public function update_post_metadata( $r, $post_id, $meta_key, $meta_value ) {
		static $once = false;

		if ( in_array( $meta_key, [ '_appointment_product_id', '_appointment_staff_id' ] ) && ! empty( $meta_value ) && ! $once ) {
			$once = true;
			$r    = $this->update_post_meta( $post_id, $meta_key, $meta_value );
		}
		$once = false;

		return $r;
	}

	/**
	 * Associates all products in a translation group to an appointment.
	 *
	 * @since 0.6
	 *
	 * @param int    $post_id    Appointment ID.
	 * @param string $meta_key   Meta key.
	 * @param int    $meta_value Product ID.
	 *
	 * @return bool
	 */
	protected function update_post_meta( $post_id, $meta_key, $meta_value ) {
		$values = get_post_meta( $post_id, $meta_key );

		if ( empty( $values ) ) {
			foreach ( pll_get_post_translations( $meta_value ) as $id ) {
				add_post_meta( $post_id, $meta_key, $id );
			}
		} else {
			$to_keep = array_intersect( $values, pll_get_post_translations( $meta_value ) );
			$olds    = array_values( array_diff( $values, $to_keep ) );
			$news    = array_values( array_diff( pll_get_post_translations( $meta_value ), $to_keep ) );
			foreach ( $olds as $k => $old ) {
				update_post_meta( $post_id, $meta_key, $news[ $k ], $old );
			}
		}

		return true;
	}

	/**
	 * Allows to get the appointment's associated product and staff in the current language.
	 * Hooked to the filter 'get_post_metadata'.
	 *
	 *
	 * @since 0.6
	 *
	 * @param null|bool $r         Returned value (null by default).
	 * @param int       $post_id   Appointment ID.
	 * @param string    $meta_key  Meta key.
	 * @param bool      $single    Whether a single meta value has been requested.
	 *
	 * @return mixed
	 */
	public function get_post_metadata( $r, $post_id, $meta_key, $single ) {
		static $once = false;

		if ( ! $once && $single ) {
			switch ( $meta_key ) {
				case '_appointment_product_id':
				case '_appointment_staff_id':
					$once     = true;
					$value    = get_post_meta( $post_id, $meta_key, true );
					$language = PLL() instanceof PLL_Frontend ? pll_current_language() : pll_get_post_language( $post_id );
					$once     = false;
					return pll_get_post( $value, $language );
			}
		}

		if ( ! $once && empty( $meta_key ) && 'wc_appointment' === get_post_type( $post_id ) ) {
			$once     = true;
			$value    = get_post_meta( $post_id );
			$language = PLL() instanceof PLL_Frontend ? pll_current_language() : pll_get_post_language( $post_id );
			$keys     = [
				'_appointment_product_id',
				'_appointment_staff_id',
			];

			foreach ( $keys as $key ) {
				if ( ! empty( $value[ $key ] ) ) {
					$value[ $key ] = array( pll_get_post( reset( $value[ $key ] ), $language ) );
				}
			}

			$once = false;
			return $value;
		}

		return $r;
	}

	/**
	 * Adds metas to synchronize when saving a product or staff.
	 * Hooked to the filter 'pll_copy_post_metas'.
	 *
	 * @since 0.6
	 *
	 * @param string[] $metas List of custom fields names.
	 * @return string[]
	 */
	public function copy_post_metas( $metas ) {
		$to_sync = [
			/*'_wc_appointment_has_price_label',*/
			/*'_wc_appointment_price_label',*/
			'_wc_appointment_has_pricing',
			'_wc_appointment_pricing',
			'_wc_appointment_qty',
			'_wc_appointment_qty_min',
			'_wc_appointment_qty_max',
			'_wc_appointment_staff_assignment',
			'_wc_appointment_duration',
			'_wc_appointment_duration_unit',
			'_wc_appointment_interval',
			'_wc_appointment_interval_unit',
			'_wc_appointment_min_date',
			'_wc_appointment_min_date_unit',
			'_wc_appointment_max_date',
			'_wc_appointment_max_date_unit',
			'_wc_appointment_padding_duration',
			'_wc_appointment_padding_duration_unit',
			'_wc_appointment_user_can_cancel',
			'_wc_appointment_cancel_limit',
			'_wc_appointment_cancel_limit_unit',
			'_wc_appointment_user_can_reschedule',
			'_wc_appointment_reschedule_limit_unit',
			'_wc_appointment_reschedule_limit',
			'_wc_appointment_customer_timezones',
			'_wc_appointment_cal_color',
			'_wc_appointment_requires_confirmation',
			'_wc_appointment_availability_span',
			'_wc_appointment_availability_autoselect',
			'_wc_appointment_has_restricted_days',
			'_wc_appointment_restricted_days',
			/*'_wc_appointment_staff_label',*/
			'_wc_appointment_staff_assignment',
			'_wc_appointment_staff_nopref',
			'_staff_base_costs', // To translate.
			'_staff_qtys', // To translate.
			'_product_addons', // Add-ons.
			'_product_addons_exclude_global', // Add-ons.
		];

		return array_merge( $metas, $to_sync );
	}

	/**
	 * Adds the appointments metas to export.
	 *
	 * @since 1.8
	 *
	 * @param array $metas An array of post metas to export.
	 * @return array
	 */
	public function get_metas_to_translate( $metas ) {
		return array_merge( $metas, array( '_wc_appointment_staff_label' => 1 ) );
	}

	/**
	 * Translates appointments items in cart.
	 * See WC_Appointment_Form::get_posted_data().
	 * Hooked to the filter 'pllwc_translate_cart_item'.
	 *
	 * @since 0.6
	 *
	 * @param array  $item Cart item.
	 * @param string $lang Language code.
	 *
	 * @return array
	 */
	public function translate_cart_item( $item, $lang ) {
		if ( ! empty( $item['appointment'] ) ) {
			$appointment = &$item['appointment'];

			// Translate date.
			if ( ! empty( $appointment['date'] ) && ! empty( $appointment['_date'] ) ) {
				$appointment['date'] = date_i18n( wc_appointments_date_format(), strtotime( $appointment['_date'] ) );
			}

			// Translate time.
			if ( ! empty( $appointment['time'] ) && ! empty( $appointment['_time'] ) ) {
				$appointment['time'] = date_i18n( wc_appointments_time_format(), strtotime( "{$appointment['_year']}-{$appointment['_month']}-{$appointment['_day']} {$appointment['_time']}" ) );
			}

			// We need to set the price.
			if ( ! empty( $item['data'] ) && ! empty( $appointment['_cost'] ) ) {
				$item['data']->set_price( $appointment['_cost'] );
			}
		}

		return $item;
	}

	/**
	 * Adds the appointment to the cart item data when translating the cart.
	 * Hooked to the filter 'pllwc_add_cart_item_data'.
	 *
	 * @since 0.7.4
	 *
	 * @param array $cart_item_data Cart item data.
	 * @param array $item           Cart item.
	 *
	 * @return array
	 */
	public function add_cart_item_data( $cart_item_data, $item ) {
		if ( isset( $item['appointment'] ) ) {
			$cart_item_data['appointment'] = $item['appointment'];
		}
		return $cart_item_data;
	}

	/**
	 * Filters appointments when sending notifications to get only appointments in the same language as the chosen product.
	 * Hooked to the action 'parse_query'.
	 *
	 * @since 0.6
	 *
	 * @param WP_Query $query WP_Query object.
	 * @return void
	 */
	public function filter_appointments_notifications( $query ) {
		$qvars  = &$query->query_vars;
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : new stdClass();

		if ( isset( $screen->id ) && 'wc_appointment_page_appointment_notification' === $screen->id && 'wc_appointment' === $qvars['post_type'] ) {
			$meta_query = reset( $qvars['meta_query'] );
			$query->set( 'lang', pll_get_post_language( $meta_query['value'] ) );
		}
	}

	/**
	 * Returns the translation of the appointments endpoint url.
	 * Hooked to the filter 'pll_translation_url'.
	 *
	 * @since 0.6
	 *
	 * @param string $url  URL of the translation, to modify.
	 * @param string $lang Language slug.
	 * @return string
	 */
	public function pll_translation_url( $url, $lang ) {
		global $wp;

		$endpoint = apply_filters( 'woocommerce_appointments_account_endpoint', 'appointments' );

		if ( isset( PLL()->translate_slugs->slugs_model, $wp->query_vars[ $endpoint ] ) ) {
			$language = PLL()->model->get_language( $lang );
			$url      = wc_get_endpoint_url( $endpoint, '', $url );
			$url      = PLL()->translate_slugs->slugs_model->switch_translated_slug( $url, $language, 'wc_appointments' );
		}

		return $url;
	}

	/**
	 * Adds the appointments endpoint to the list of endpoints to translate.
	 * Hooked to the filter 'woocommerce_get_query_vars'.
	 * Copies `WC_Appointment_Order_Manager::get_endpoint()` as the class is instantiated anonymously.
	 *
	 * @since 0.6
	 *
	 * @param array $slugs Endpoints slugs.
	 * @return array
	 */
	public function get_endpoints_query_vars( $slugs ) {
		$slugs[] = apply_filters( 'woocommerce_appointments_account_endpoint', 'appointments' );
		return $slugs;
	}

	/**
	 * Disables the languages filter for a customer to see all appointments whatever the languages.
	 * Hooked to the action 'parse_query'.
	 *
	 * @since 0.6
	 *
	 * @param WP_Query $query WP_Query object.
	 * @return void
	 */
	public function parse_query( $query ) {
		$qvars = $query->query_vars;

		// Customers should see all their orders whatever the language.
		if ( isset( $qvars['post_type'] ) && ( 'wc_appointment' === $qvars['post_type'] || ( is_array( $qvars['post_type'] ) && in_array( 'wc_appointment', $qvars['post_type'] ) ) ) ) {
			$query->set( 'lang', 0 );
		}
	}
}

new WC_Appointments_Integration_Polylang();
