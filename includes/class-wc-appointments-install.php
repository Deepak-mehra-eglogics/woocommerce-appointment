<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

// Define database version.
define( 'WC_APPOINTMENTS_DB_VERSION', '4.5.0' );

/**
 * Installation/Migration Class.
 *
 * Handles the activation/installation of the plugin.
 *
 * @version  4.3.4
 */
class WC_Appointments_Install {
	/**
	 * Initialize hooks.
	 *
	 * @since 4.3.4
	 */
	public static function init() {
		self::run();
	}

	/**
	 * Run the installation.
	 *
	 * @since 4.3.4
	 */
	private static function run() {
		$saved_version     = get_option( 'wc_appointments_version' );
		$installed_version = ! $saved_version ? WC_APPOINTMENTS_VERSION : $saved_version;

		// Check the version before running.
		if ( ! defined( 'IFRAME_REQUEST' ) && WC_APPOINTMENTS_VERSION !== $saved_version ) {
			if ( ! defined( 'WC_APPOINTMENTS_INSTALLING' ) ) {
				define( 'WC_APPOINTMENTS_INSTALLING', true );
			}

			self::update_plugin_version();
			self::update_db_version();

			global $wpdb, $wp_roles;

			$wpdb->hide_errors();

			$collate = '';

			if ( $wpdb->has_cap( 'collation' ) ) {
				if ( ! empty( $wpdb->charset ) ) {
					$collate .= "DEFAULT CHARACTER SET $wpdb->charset";
				}
				if ( ! empty( $wpdb->collate ) ) {
					$collate .= " COLLATE $wpdb->collate";
				}
			}

			require_once ABSPATH . 'wp-admin/includes/upgrade.php';

			dbDelta(
				"CREATE TABLE {$wpdb->prefix}wc_appointment_relationships (
				ID bigint(20) unsigned NOT NULL auto_increment,
				product_id bigint(20) unsigned NOT NULL,
				staff_id bigint(20) unsigned NOT NULL,
				sort_order bigint(20) unsigned NOT NULL default 0,
				PRIMARY KEY  (ID),
				KEY product_id (product_id),
				KEY staff_id (staff_id)
				) $collate;
				CREATE TABLE {$wpdb->prefix}wc_appointments_availability (
				ID bigint(20) unsigned NOT NULL auto_increment,
				kind varchar(100) NOT NULL,
				kind_id varchar(100) NOT NULL,
				event_id varchar(255) NOT NULL,
				title varchar(255) NULL,
				range_type varchar(60) NOT NULL,
				from_date varchar(60) NOT NULL,
				to_date varchar(60) NOT NULL,
				from_range varchar(60) NULL,
				to_range varchar(60) NULL,
				appointable varchar(5) NOT NULL default 'yes',
				priority int(2) NOT NULL default 10,
				qty bigint(20) NOT NULL,
				ordering int(2) NOT NULL default 0,
				date_created datetime NULL default NULL,
				date_modified datetime NULL default NULL,
			    rrule text NULL default NULL,
				PRIMARY KEY  (ID),
				KEY kind_id (kind_id)
				) $collate;"
			);

			// Product type.
			if ( ! get_term_by( 'slug', sanitize_title( 'appointment' ), 'product_type' ) ) {
				wp_insert_term( 'appointment', 'product_type' );
			}

			// Capabilities.
			if ( class_exists( 'WP_Roles' ) ) {
				if ( ! isset( $wp_roles ) ) {
					$wp_roles = new WP_Roles();
				}
			}

			// Shop staff role.
			add_role(
				'shop_staff',
				__( 'Shop Staff', 'woocommerce-appointments' ),
				array(
					'level_8'                   => true,
					'level_7'                   => true,
					'level_6'                   => true,
					'level_5'                   => true,
					'level_4'                   => true,
					'level_3'                   => true,
					'level_2'                   => true,
					'level_1'                   => true,
					'level_0'                   => true,

					'read'                      => true,

					'read_private_posts'        => true,
					'edit_posts'                => true,
					'edit_published_posts'      => true,
					'edit_private_posts'        => true,
					'edit_others_posts'         => false,
					'publish_posts'             => true,
					'delete_private_posts'      => true,
					'delete_posts'              => true,
					'delete_published_posts'    => true,
					'delete_others_posts'       => false,

					'read_private_pages'        => true,
					'edit_pages'                => true,
					'edit_published_pages'      => true,
					'edit_private_pages'        => true,
					'edit_others_pages'         => false,
					'publish_pages'             => true,
					'delete_pages'              => true,
					'delete_private_pages'      => true,
					'delete_published_pages'    => true,
					'delete_others_pages'       => false,

					'read_private_products'     => true,
					'edit_products'             => true,
					'edit_published_products'   => true,
					'edit_private_products'     => true,
					'edit_others_products'      => false,
					'publish_products'          => true,
					'delete_products'           => true,
					'delete_private_products'   => true,
					'delete_published_products' => true,
					'delete_others_products'    => false,
					'edit_shop_orders'          => true,
					'edit_others_shop_orders'   => true,

					'manage_categories'         => false,
					'manage_links'              => false,
					'moderate_comments'         => true,
					'unfiltered_html'           => true,
					'upload_files'              => true,
					'export'                    => false,
					'import'                    => false,

					'edit_users'                => true,
					'list_users'                => true,
				)
			);

			if ( is_object( $wp_roles ) ) {
				// Ability to manage appointments.
				$wp_roles->add_cap( 'shop_manager', 'manage_appointments' );
				$wp_roles->add_cap( 'administrator', 'manage_appointments' );
				$wp_roles->add_cap( 'shop_staff', 'manage_appointments' );

				// Ability to edit their shop orders.
				$wp_roles->add_cap( 'shop_staff', 'edit_shop_orders' );
				$wp_roles->add_cap( 'shop_manager', 'edit_shop_orders' );

				// Ability to edit others shop orders.
				$wp_roles->add_cap( 'shop_staff', 'edit_others_shop_orders' );
				$wp_roles->add_cap( 'shop_manager', 'edit_others_shop_orders' );

				// Ability to view others appointments.
				$wp_roles->add_cap( 'shop_manager', 'manage_others_appointments' );
				$wp_roles->add_cap( 'administrator', 'manage_others_appointments' );
				$wp_roles->remove_cap( 'shop_staff', 'manage_others_appointments' );
			}

			// Shop staff expand capabilities.
			$capabilities         = [];
			$capabilities['core'] = array(
				'view_woocommerce_reports',
			);

			$capability_types = array(
				'appointment',
			);
			foreach ( $capability_types as $capability_type ) {
				$capabilities[ $capability_type ] = array(
					// Post type
					"edit_{$capability_type}",
					"read_{$capability_type}",
					"delete_{$capability_type}",
					"edit_{$capability_type}s",
					"edit_others_{$capability_type}s",
					"publish_{$capability_type}s",
					"read_private_{$capability_type}s",
					"delete_{$capability_type}s",
					"delete_private_{$capability_type}s",
					"delete_published_{$capability_type}s",
					"delete_others_{$capability_type}s",
					"edit_private_{$capability_type}s",
					"edit_published_{$capability_type}s",

					// Terms
					"manage_{$capability_type}_terms",
					"edit_{$capability_type}_terms",
					"delete_{$capability_type}_terms",
					"assign_{$capability_type}_terms",
				);
			}

			foreach ( $capabilities as $cap_group ) {
				foreach ( $cap_group as $cap ) {
					$wp_roles->add_cap( 'shop_staff', $cap );
					$wp_roles->add_cap( 'shop_manager', $cap );
					$wp_roles->add_cap( 'administrator', $cap );
				}
			}

			// Update 4.21.3
			if ( version_compare( $installed_version, '4.22.0', '<' ) ) {
				// Flush Permalinks.
				flush_rewrite_rules();
			}

			// Update 4.21.3
			if ( version_compare( $installed_version, '4.21.3', '<' ) ) {
				// Flush Permalinks.
				flush_rewrite_rules();
			}

			// Update 4.5.1
			if ( version_compare( $installed_version, '4.5.1', '<' ) ) {
				self::migration_4_5_1();
			}

			// Update 4.4.0
			if ( version_compare( $installed_version, '4.4.0', '<' ) ) {
				self::migration_4_4_0();
			}

			// Update 4.1.5
			if ( version_compare( get_option( 'wc_appointments_version', WC_APPOINTMENTS_VERSION ), '4.1.5', '<' ) ) {
				self::migration_4_1_5();
			}

			// Update 3.4.0
			if ( version_compare( get_option( 'wc_appointments_version', WC_APPOINTMENTS_VERSION ), '3.4', '<' ) ) {
				self::migration_3_4_0();
			}

			// Update 3.7.0
			if ( version_compare( get_option( 'wc_appointments_version', WC_APPOINTMENTS_VERSION ), '3.7', '<' ) ) {
				self::migration_3_7_0();
			}

			// Check template versions.
			if ( class_exists( 'WC_Appointments_Admin' ) ) {
				WC_Appointments_Admin::template_file_check_notice();
			}

			do_action( 'wc_appointments_updated' );
		}
	}

	/**
	 * Updates the plugin version in db.
	 *
	 * @since 4.3.4
	 */
	private static function update_plugin_version() {
		delete_option( 'wc_appointments_version' );
		add_option( 'wc_appointments_version', WC_APPOINTMENTS_VERSION );
	}

	/**
	 * Updates the plugin db version in db.
	 *
	 * @since 4.3.4
	 */
	private static function update_db_version() {
		delete_option( 'wc_appointments_db_version' );
		add_option( 'wc_appointments_db_version', WC_APPOINTMENTS_DB_VERSION );
	}

	/**
	 * Trash all gcal "fake" appointments and move gcal settings.
	 *
	 * @since 3.7.0
	 */
	private static function migration_3_7_0() {
		global $wpdb;

		// Trash all GCal appointments.
		$gcal_id = apply_filters( 'woocommerce_appointments_gcal_synced_product_id', 2147483647 );

		$wpdb->query(
			"UPDATE {$wpdb->posts} as posts
			LEFT JOIN {$wpdb->postmeta} AS meta ON posts.ID = meta.post_id
			SET posts.post_status = 'trash'
			WHERE posts.post_type = 'wc_appointment'
				AND meta.meta_key = '_appointment_product_id'
				AND meta.meta_value = {$gcal_id}"
		);

		// Delete full sync checking.
		wp_clear_scheduled_hook( 'wc-appointment-sync-full-from-gcal' );

		// Move Google Calendar Integration settings.
		$old_gcal_settings = get_option( 'wc_appointments_gcal_settings' );
		if ( $old_gcal_settings ) {
			foreach ( $old_gcal_settings as $old_gcal_setting_key => $old_gcal_setting_value ) {
				switch ( $old_gcal_setting_key ) {
					case 'client_id':
						add_option( 'wc_appointments_gcal_client_id', $old_gcal_setting_value );
						break;
					case 'client_secret':
						add_option( 'wc_appointments_gcal_client_secret', $old_gcal_setting_value );
						break;
					case 'calendar_id':
						add_option( 'wc_appointments_gcal_calendar_id', $old_gcal_setting_value );
						break;
					case 'authorization':
						add_option( 'wc_appointments_gcal_authorization', $old_gcal_setting_value );
						break;
					case 'twoway':
						add_option( 'wc_appointments_gcal_twoway', $old_gcal_setting_value );
						break;
					case 'debug':
						add_option( 'wc_appointments_gcal_debug', $old_gcal_setting_value );
						break;
				}
			}
			#delete_option( 'wc_appointments_gcal_settings' );
		}
	}

	/**
	 * Change appointment status from "pending" to "pending-confirmation".
	 *
	 * @since 3.4.0
	 */
	private static function migration_3_4_0() {
		global $wpdb;

		$wpdb->query(
			"UPDATE {$wpdb->posts} as posts
			SET posts.post_status = 'pending-confirmation'
			WHERE posts.post_type = 'wc_appointment'
			AND posts.post_status = 'pending';"
		);
	}

	/**
	 * Trash duplicated appointments for WPML.
	 *
	 * @since 4.1.5
	 */
	private static function migration_4_1_5() {
		global $wpdb;

		if ( class_exists( 'SitePress' ) && class_exists( 'woocommerce_wpml' ) && class_exists( 'WPML_Element_Translation_Package' ) ) {
			$wpdb->query(
				"UPDATE {$wpdb->posts} as posts
				LEFT JOIN {$wpdb->prefix}icl_translations AS translations ON translations.element_id = posts.id
				SET posts.post_status = 'trash'
				WHERE posts.post_type = 'wc_appointment'
					AND translations.element_type = 'post_wc_appointment'
					AND translations.source_language_code != ''
				"
			);
		}
	}

	/**
	 * Migrate global availabiltity from options table
	 * to custom availability table.
	 *
	 * @since 4.3.4
	 */
	private static function migration_4_4_0() {
		global $wpdb;

		// Get 'wc_appointments_gcal_twoway' option.
		$two_way_option = get_option( 'wc_appointments_gcal_twoway' );

		// Migrate global availabilities.
		$global_availability = get_option( 'wc_global_appointment_availability', [] );

		if ( ! empty( $global_availability ) ) {
			$index = 0;

			foreach ( $global_availability as $rule ) {
				$type        = ! empty( $rule['type'] ) ? $rule['type'] : '';
				$from_range  = ! empty( $rule['from'] ) ? $rule['from'] : '';
				$to_range    = ! empty( $rule['to'] ) ? $rule['to'] : '';
				$from_date   = ! empty( $rule['from_date'] ) ? $rule['from_date'] : '';
				$to_date     = ! empty( $rule['to_date'] ) ? $rule['to_date'] : '';
				$appointable = ! empty( $rule['appointable'] ) ? $rule['appointable'] : '';
				$priority    = ! empty( $rule['priority'] ) ? $rule['priority'] : 10;

				$wpdb->insert(
					$wpdb->prefix . 'wc_appointments_availability',
					array(
						'kind'          => 'availability#global',
						'kind_id'       => '',
						'title'         => '',
						'range_type'    => $type,
						'from_range'    => $from_range,
						'to_range'      => $to_range,
						'from_date'     => $from_date,
						'to_date'       => $to_date,
						'appointable'   => $appointable,
						'priority'      => $priority,
						'ordering'      => $index,
						'rrule'         => '',
						'date_created'  => current_time( 'mysql' ),
						'date_modified' => current_time( 'mysql' ),
					)
				);

				$index++;
			}

			// When migrated, delete old availability rules.
			delete_option( 'wc_global_appointment_availability' );
		}

		// Migrate product availabilities.
		$all_product_args = array(
			'post_status'      => 'publish',
			'post_type'        => 'product',
			'posts_per_page'   => -1,
			'suppress_filters' => true,
			'fields'           => 'ids',
			'orderby'          => 'title',
			'order'            => 'ASC',
		);
		$posts_query      = new WP_Query();
	    $all_product_ids  = $posts_query->query( $all_product_args );

		if ( $all_product_ids ) {
			foreach ( $all_product_ids as $all_product_id ) {
				$product_availabilities = get_post_meta( $all_product_id, '_wc_appointment_availability', true );

				if ( $product_availabilities && is_array( $product_availabilities ) && ! empty( $product_availabilities ) ) {
					$index_p = 0;

					foreach ( $product_availabilities as $rule ) {
						$type        = ! empty( $rule['type'] ) ? $rule['type'] : '';
						$from_range  = ! empty( $rule['from'] ) ? $rule['from'] : '';
						$to_range    = ! empty( $rule['to'] ) ? $rule['to'] : '';
						$from_date   = ! empty( $rule['from_date'] ) ? $rule['from_date'] : '';
						$to_date     = ! empty( $rule['to_date'] ) ? $rule['to_date'] : '';
						$appointable = ! empty( $rule['appointable'] ) ? $rule['appointable'] : '';
						$priority    = ! empty( $rule['priority'] ) ? $rule['priority'] : 10;

						$wpdb->insert(
							$wpdb->prefix . 'wc_appointments_availability',
							array(
								'kind'          => 'availability#product',
								'kind_id'       => $all_product_id,
								'title'         => '',
								'range_type'    => $type,
								'from_range'    => $from_range,
								'to_range'      => $to_range,
								'from_date'     => $from_date,
								'to_date'       => $to_date,
								'appointable'   => $appointable,
								'priority'      => $priority,
								'ordering'      => $index_p,
								'rrule'         => '',
								'date_created'  => current_time( 'mysql' ),
								'date_modified' => current_time( 'mysql' ),
							)
						);

						$index_p++;
					}

					// When migrated, delete old availability rules.
					delete_post_meta( $all_product_id, '_wc_appointment_availability', $rule );
				} else {
					continue;
				}
			}
		}

		// Migrate staff availabilities and settings.
		$all_staff = get_users(
			array(
				'role'    => 'shop_staff',
				'orderby' => 'nicename',
				'order'   => 'asc',
			)
		);

		if ( $all_staff ) {
			foreach ( $all_staff as $single_staff ) {
				// Get single staff availability rules.
				$single_staff_availabilities = get_user_meta( $single_staff->ID, '_wc_appointment_availability', true );

				// Get single staff availability rules.
				$single_staff_gcal_calendar_id = get_user_meta( $single_staff->ID, 'wc_appointments_gcal_calendar_id', true );

				// Update single staff two_way option.
				if ( 'yes' === $two_way_option ) {
					update_user_meta( $single_staff->ID, 'wc_appointments_gcal_twoway', 'two_way' );
				} elseif ( 'no' === $two_way_option ) {
					update_user_meta( $single_staff->ID, 'wc_appointments_gcal_twoway', 'one_way' );
				}

				if ( $single_staff_availabilities && is_array( $single_staff_availabilities ) && ! empty( $single_staff_availabilities ) ) {
					$index_s = 0;

					foreach ( $single_staff_availabilities as $rule ) {
						$type        = ! empty( $rule['type'] ) ? $rule['type'] : '';
						$from_range  = ! empty( $rule['from'] ) ? $rule['from'] : '';
						$to_range    = ! empty( $rule['to'] ) ? $rule['to'] : '';
						$from_date   = ! empty( $rule['from_date'] ) ? $rule['from_date'] : '';
						$to_date     = ! empty( $rule['to_date'] ) ? $rule['to_date'] : '';
						$appointable = ! empty( $rule['appointable'] ) ? $rule['appointable'] : '';
						$priority    = ! empty( $rule['priority'] ) ? $rule['priority'] : 10;

						$wpdb->insert(
							$wpdb->prefix . 'wc_appointments_availability',
							array(
								'kind'          => 'availability#staff',
								'kind_id'       => $single_staff->ID,
								'title'         => '',
								'range_type'    => $type,
								'from_range'    => $from_range,
								'to_range'      => $to_range,
								'from_date'     => $from_date,
								'to_date'       => $to_date,
								'appointable'   => $appointable,
								'priority'      => $priority,
								'ordering'      => $index_s,
								'rrule'         => '',
								'date_created'  => current_time( 'mysql' ),
								'date_modified' => current_time( 'mysql' ),
							)
						);

						$index_s++;
					}

					// When migrated, delete old availability rules.
					delete_user_meta( $single_staff->ID, '_wc_appointment_availability' );
				} else {
					continue;
				}
			}
		}

		// Update 'wc_appointments_gcal_twoway' option.
		if ( 'yes' === $two_way_option ) {
			update_option( 'wc_appointments_gcal_twoway', 'two_way' );
		} elseif ( 'no' === $two_way_option ) {
			update_option( 'wc_appointments_gcal_twoway', 'one_way' );
		}

		// Stop syncing via WP Cron.
		wp_clear_scheduled_hook( 'wc-appointment-sync-from-gcal' );
		wp_clear_scheduled_hook( 'wc-appointment-complete' );
		wp_clear_scheduled_hook( 'wc-appointment-reminder' );
		wp_clear_scheduled_hook( 'wc-appointment-remove-inactive-cart' );
	}

	/**
	 * Remove as scheduled action.
	 *
	 * @since 4.5.1
	 */
	private static function migration_4_5_1() {
		if ( ! defined( 'EMPTY_TRASH_DAYS' ) ) {
			define( 'EMPTY_TRASH_DAYS', 30 );
		}

		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( 'wc-appointment-sync-from-gcal' );
		}
	}
}
