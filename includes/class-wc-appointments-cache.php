<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * WC_Appointments_Cache class.
 *
 * @package WooCommerce-Appointments/Classes
 */

/**
 * Helper cache class.
 *
 * @since 4.7.0
 */
class WC_Appointments_Cache {
	/**
	 * Constructor.
	 *
	 * @since 4.7.0
	 */
	public function __construct() {
		add_action( 'woocommerce_appointment_cancelled', array( __CLASS__, 'clear_cache' ) );
		add_action( 'woocommerce_appointment_cancelled', array( __CLASS__, 'clear_cron_hooks' ) );
		add_action( 'before_delete_post', array( __CLASS__, 'clear_cache' ) );
		add_action( 'wp_trash_post', array( __CLASS__, 'clear_cache' ) );
		add_action( 'untrash_post', array( __CLASS__, 'clear_cache' ) );
		add_action( 'save_post', array( __CLASS__, 'clear_cache_on_save_post' ) );
		add_action( 'woocommerce_order_status_changed', array( __CLASS__, 'clear_cache' ) );
		add_action( 'woocommerce_pre_payment_complete', array( __CLASS__, 'clear_cache' ) );

		// Scheduled events.
		add_action( 'delete_appointment_transients', array( __CLASS__, 'clear_cache' ) );
		add_action( 'delete_appointment_ts_transients', array( __CLASS__, 'clear_cache' ) );
		add_action( 'delete_appointment_dr_transients', array( __CLASS__, 'clear_cache' ) );
		add_action( 'delete_appointment_staff_transients', array( __CLASS__, 'clear_cache' ) );
	}

	/**
	 * Determines if debug mode is enabled. Used to
	 * get around stale cache when testing.
	 *
	 * @since 4.7.0
	 * @return bool
	 */
	public static function is_debug_mode() {
		return true === WC_APPOINTMENTS_DEBUG;
	}

	/**
	 * Invalidate cache group.
	 *
	 * @param string $group Group of cache to clear.
	 * @since 4.8.10
	 */
 	public static function invalidate_cache_group( $group ) {
 		wp_cache_set( 'wc_' . $group . '_cache_prefix', microtime(), $group );
 	}

	/**
	 * Gets the cache transient from db.
	 *
	 * @since 4.7.0
	 * @param string $name Name of the cache.
	 * @return mixed $data
	 */
	public static function get( $name = '' ) {
		if ( empty( $name ) || self::is_debug_mode() ) {
			return false;
		}

		return get_transient( $name );
	}

	/**
	 * Sets the cache transient to db.
	 *
	 * @since 4.7.0
	 * @param string $name Name of the cache.
	 * @param mixed  $data The data to be cached.
	 * @param int $expiration When to expire the cache.
	 * @return void
	 */
	public static function set( $name = '', $data = null, $expiration = YEAR_IN_SECONDS ) {
		set_transient( $name, $data, $expiration );
	}

	/**
	 * Deletes the cache transient from db.
	 *
	 * @since 4.7.0
	 * @param string $name Name of the cache.
	 * @return void
	 */
	public static function delete( $name = '' ) {
		delete_transient( $name );
	}

	public static function clear_cache() {
		WC_Cache_Helper::get_transient_version( 'appointments', true );

		// It only makes sense to delete transients from the DB if we're not using an external cache.
		if ( ! wp_using_ext_object_cache() ) {
			self::delete_appointment_transients();
			self::delete_appointment_ts_transients();
			self::delete_appointment_dr_transients();
			self::delete_appointment_staff_transients();
		} else {
			// Flush Memcache or Memcached.
			wp_cache_flush();
		}
	}

	/**
	 * Clear cron hooks for appointment
	 *
	 * @param mixed $post_id
	 */
	public static function clear_cron_hooks( $post_id = 0 ) {
		as_unschedule_action( 'wc-appointment-reminder', array( $post_id ), 'wca' );
		as_unschedule_action( 'wc-appointment-complete', array( $post_id ), 'wca' );
		as_unschedule_action( 'wc-appointment-remove-inactive-cart', array( $post_id ), 'wca' );
		as_unschedule_action( 'wc-appointment-follow-up', array( $post_id ), 'wca' );
	}

	/**
	 * Clears the transients when appointment is edited.
	 *
	 * @param int $post_id
	 * @return int|void $post_id
	 */
	public static function clear_cache_on_save_post( $post_id ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return $post_id;
		}

		if ( 'wc_appointment' !== get_post_type( $post_id ) && 'product' !== get_post_type( $post_id ) ) {
			return $post_id;
		}

		self::clear_cache();
	}

	/**
	 * Delete Appointment Related Transients
	 */
	public static function delete_appointment_transients() {
		global $wpdb;
		$limit = 500;

		$affected_timeouts   = $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s LIMIT %d;", '_transient_timeout_schedule_fo_%', $limit ) );
		$affected_transients = $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s LIMIT %d;", '_transient_schedule_fo_%', $limit ) );

		// If affected rows is equal to limit, there are more rows to delete. Delete in 10 secs.
		if ( $affected_transients === $limit ) {
			as_schedule_single_action( time() + 10, 'delete_appointment_transients', array( time() ), 'wca' );
		}
	}

	/**
	 * Delete Appointment Time Slots Related Transients
	 */
	public static function delete_appointment_ts_transients() {
		global $wpdb;
		$limit = 500;

		$affected_timeouts   = $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s LIMIT %d;", '_transient_timeout_schedule_ts_%', $limit ) );
		$affected_transients = $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s LIMIT %d;", '_transient_schedule_ts_%', $limit ) );

		// If affected rows is equal to limit, there are more rows to delete. Delete in 10 secs.
		if ( $affected_transients === $limit ) {
			as_schedule_single_action( time() + 10, 'delete_appointment_ts_transients', array( time() ), 'wca' );
		}
	}

	/**
	 * Delete Appointment Date Range Related Transients
	 */
	public static function delete_appointment_dr_transients() {
		global $wpdb;
		$limit = 500;

		$affected_timeouts   = $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s LIMIT %d;", '_transient_timeout_schedule_dr_%', $limit ) );
		$affected_transients = $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s LIMIT %d;", '_transient_schedule_dr_%', $limit ) );

		// If affected rows is equal to limit, there are more rows to delete. Delete in 10 secs.
		if ( $affected_transients === $limit ) {
			as_schedule_single_action( time() + 10, 'delete_appointment_dr_transients', array( time() ), 'wca' );
		}
	}

	/**
	 * Flush transients for all products related to a specific staff.
	 *
	 * @param  WP_User ID $staff_id
	 * @since  4.10.7
	 */
	public static function flush_staff_products_transients( $user_id ) {
		$staff = new WC_Product_Appointment_Staff( $user_id );

		if ( ! $staff ) {
			return;
		}

		// Get product IDs for staff.
		$product_ids = $staff->get_product_ids();

		if ( ! $product_ids ) {
			return;
		}

		foreach ( $product_ids as $product_id ) {
			self::delete_appointment_slots_transient( $product_id );
		}
	}

	/**
	 * Clear appointment slots transient.
	 * If there are staff find connected products and clear their transients.
	 *
	 * @param  WC_Appointment $appointment
	 * @since  4.10.7
	 */
	public static function flush_all_appointment_connected_transients( $appointment ) {
		if ( ! $appointment ) {
			return;
		}

		$staff_ids = $appointment->get_staff_ids();
		if ( $staff_ids ) {
			// Array.
			if ( is_array( $staff_ids ) ) {
				foreach ( $staff_ids as $staff_id ) {
					// We have staff. Other products may be affected.
					self::flush_staff_products_transients( $staff_id );
				}
			// Int.
			} else {
				// We have staff. Other products may be affected.
				self::flush_staff_products_transients( (int) $staff_ids );
			}
			return; #stop here since products are already flushed.
		}

		// No resource. Just flush for this appointment product.
		$product_id = $appointment->get_product_id();
		self::delete_appointment_slots_transient( $product_id );
	}

	/**
	 * Delete Staff Related Transients
	 */
	public static function delete_appointment_staff_transients() {
		global $wpdb;
		$limit = 500;

		$affected_timeouts   = $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s LIMIT %d;", '_transient_timeout_staff_ps_%', $limit ) );
		$affected_transients = $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s LIMIT %d;", '_transient_staff_ps_%', $limit ) );

		// If affected rows is equal to limit, there are more rows to delete. Delete in 10 secs.
		if ( $affected_transients === $limit ) {
			as_schedule_single_action( time() + 10, 'delete_appointment_staff_transients', array( time() ), 'wca' );
		}
	}

	/**
	 * Delete appointment slots transient.
	 *
	 * In contexts where we have a product id, it will only delete the specific ones.
	 * However, not all contexts will have a product id, e.g. Global Availability.
	 *
	 * @param  int|null $appointable_product_id
	 * @since  4.5.0
	 */
	public static function delete_appointment_slots_transient( $appointable_product_id = null ) {
		$appointment_slots_transient_keys = array_filter( (array) self::get( 'appointment_slots_transient_keys' ) );

		if ( is_int( $appointable_product_id ) ) {
			if ( ! isset( $appointment_slots_transient_keys[ $appointable_product_id ] ) ) {
				return;
			}

			// Get a list of flushed transients
			$flushed_transients = array_map(
				function( $transient_name ) {
					self::delete( $transient_name );
					return $transient_name;
				},
				$appointment_slots_transient_keys[ $appointable_product_id ]
			);

			// Remove the flushed transients referenced from other product ids (if there's such a cross-reference)
			array_walk(
				$appointment_slots_transient_keys,
				function( &$transients, $appointable_product_id ) use ( $flushed_transients ) {
					$transients = array_values( array_diff( $transients, $flushed_transients ) );
				}
			);

			$appointment_slots_transient_keys = array_filter( $appointment_slots_transient_keys );

			unset( $appointment_slots_transient_keys[ $appointable_product_id ] );
			self::set( 'appointment_slots_transient_keys', $appointment_slots_transient_keys, YEAR_IN_SECONDS );
		} else {
			$transients = array_unique(
				array_reduce(
					$appointment_slots_transient_keys,
					function( $result, $item ) {
						return array_merge( $result, $item );
					},
					[]
				)
			);

			foreach ( $transients as $transient_key ) {
				self::delete( $transient_key );
			}

			self::delete( 'appointment_slots_transient_keys' );
		}
	}
}

new WC_Appointments_Cache();
