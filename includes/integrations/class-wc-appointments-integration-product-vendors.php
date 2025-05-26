<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

class WC_Appointments_Integration_Product_Vendors {
	/**
	 * Constructor
	 *
	 * @access public
	 * @since 3.1.0
	 * @version 3.1.0
	 * @return bool
	 */
	public function __construct() {
		// clear appointments query (cache)
		add_action( 'parse_query', [ $this, 'clear_appointments_cache' ] );

		add_action( 'admin_init', [ $this, 'add_default_roles' ] );

		// filter products for specific vendor
		add_filter( 'get_appointment_products_args', [ $this, 'filter_products' ] );

		// filter staff for specific vendor
		add_filter( 'get_appointment_staff_args', [ $this, 'filter_products' ] );

		// filter products from appointment list
		add_filter( 'pre_get_posts', [ $this, 'filter_products_appointment_list' ] );

		// filter products from appointment calendar
		add_filter( 'woocommerce_appointments_in_date_range_query', [ $this, 'filter_appointments_calendar' ] );

		// add vendor email for confirm appointment email
		add_filter( 'woocommerce_email_recipient_new_appointment', [ $this, 'filter_appointment_emails' ], 10, 2 );

		// add vendor email for cancelled appointment email
		add_filter( 'woocommerce_email_recipient_appointment_cancelled', [ $this, 'filter_appointment_emails' ], 10, 2 );

		// modify the appointment status views
		add_filter( 'views_edit-wc_appointment', [ $this, 'appointment_status_views' ] );

		// setup dashboard widget
		add_action( 'wp_dashboard_setup', [ $this, 'add_vendor_dashboard_widget' ], 99999 );

		// redirect the page after creating appointments
		add_filter( 'wp_redirect', [ $this, 'add_appointment_redirect' ] );
		add_filter( 'wp_safe_redirect', [ $this, 'add_appointment_redirect' ] );

		// clears any cache for the recent appointments on dashboard
		add_action( 'save_post', [ $this, 'clear_recent_appointments_cache_on_save_post' ], 10, 2 );

		// clears any cache for the recent appointments on dashboard
		add_action( 'woocommerce_new_appointment_order', [ $this, 'clear_recent_appointments_cache_on_create' ] );

		return true;
	}

	/**
	 * Clears the appointments query cache on page load
	 *
	 * @access public
	 * @since 3.1.0
	 * @version 3.1.0
	 * @return bool
	 */
	public function clear_appointments_cache() {
		global $wpdb, $typenow, $current_screen;

		if ( 'wc_appointment' === $typenow && is_admin() && ( 'edit-wc_appointment' === $current_screen->id || 'wc_appointment_page_appointment_calendar' === $current_screen->id ) ) {
			$wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE '%schedule_dr%'" );
		}

		return true;
	}

	/**
	 * Adds the default roles
	 *
	 * @access public
	 * @since 3.1.0
	 * @version 3.1.0
	 * @return bool
	 */
	public function add_default_roles() {
		if ( class_exists( 'WP_Roles' ) && ! isset( $wp_roles ) ) {
			$wp_roles = new WP_Roles();
		}

		// Add manage appointments cap to vendor admins and managers
		if ( is_object( $wp_roles ) ) {
			// Ability to manage appointments.
			$wp_roles->add_cap( 'wc_product_vendors_admin_vendor', 'manage_appointments' );
			$wp_roles->add_cap( 'wc_product_vendors_manager_vendor', 'manage_appointments' );

			// Ability to view others appointments.
			$wp_roles->add_cap( 'wc_product_vendors_admin_vendor', 'manage_others_appointments' );
			$wp_roles->add_cap( 'wc_product_vendors_manager_vendor', 'manage_others_appointments' );
		}

		return true;
	}

	/**
	 * Filter products for specific vendor
	 *
	 * @access public
	 * @since 3.1.0
	 * @version 3.1.0
	 * @param array $query_args
	 * @return array $products
	 */
	public function filter_products( $query_args ) {
		if ( WC_Product_Vendors_Utils::is_vendor() ) {
			$product_ids = WC_Product_Vendors_Utils::get_vendor_product_ids();

			$product_ids = ! empty( $product_ids ) ? $product_ids : [ '0' ];

			$query_args['post__in'] = $product_ids;
		}

		return $query_args;
	}

	/**
	 * Filter products appointment list to specific vendor
	 *
	 * @access public
	 * @since 3.1.0
	 * @version 3.1.0
	 * @param array $query
	 * @return bool
	 */
	public function filter_products_appointment_list( $query ) {
		global $typenow, $current_screen;

		if ( ! $query->is_main_query() ) {
			return;
		}

		remove_filter( 'pre_get_posts', [ $this, 'filter_products_appointment_list' ] );

		if ( 'wc_appointment' === $typenow && WC_Product_Vendors_Utils::auth_vendor_user() && is_admin() && 'edit-wc_appointment' === $current_screen->id ) {
			$product_ids = WC_Product_Vendors_Utils::get_vendor_product_ids();

			$product_ids = ! empty( $product_ids ) ? $product_ids : [ '0' ];
			$query->set( 'meta_key', '_appointment_product_id' );
			$query->set( 'meta_compare', 'IN' );
			$query->set( 'meta_value', $product_ids );
		}

		return true;
	}

	/**
	 * Filter products appointment calendar to specific vendor
	 *
	 * @access public
	 * @since 3.1.0
	 * @version 3.1.0
	 * @param array $appointment_ids appointment ids
	 * @return array
	 */
	public function filter_appointments_calendar( $appointment_ids ) {
		$filtered_ids = [];

		if ( WC_Product_Vendors_Utils::is_vendor() ) {
			$product_ids = WC_Product_Vendors_Utils::get_vendor_product_ids();

			if ( ! empty( $product_ids ) ) {
				foreach ( $appointment_ids as $id ) {
					$appointment = get_wc_appointment( $id );

					if ( in_array( $appointment->product_id, $product_ids ) ) {
						$filtered_ids[] = $id;
					}
				}

				$filtered_ids = array_unique( $filtered_ids );

				return $filtered_ids;
			} else {
				return [];
			}
		}

		return $appointment_ids;
	}

	/**
	 * Add vendor email to appointments admin emails
	 *
	 * @access public
	 * @since 3.1.0
	 * @version 3.1.0
	 * @param array $recipients
	 * @param object $this_email
	 * @return array $recipients
	 */
	public function filter_appointment_emails( $recipients, $this_email ) {
		if ( ! empty( $this_email ) ) {
			$vendor_id   = WC_Product_Vendors_Utils::get_vendor_id_from_product( $this_email->product_id );
			$vendor_data = WC_Product_Vendors_Utils::get_vendor_data_by_id( $vendor_id );

			if ( ! empty( $vendor_id ) && ! empty( $vendor_data ) ) {
				if ( isset( $recipients ) ) {
					$recipients .= ',' . $vendor_data['email'];
				} else {
					$recipients = $vendor_data['email'];
				}
			}
		}

		return $recipients;
	}

	/**
	 * Modifies the appointment status views
	 *
	 * @access public
	 * @since 2.0.9
	 * @version 2.0.9
	 * @param array $views
	 * @return array $post_type_args
	 */
	public function appointment_status_views( $views ) {
		global $typenow;

		if ( WC_Product_Vendors_Utils::auth_vendor_user() && 'wc_appointment' === $typenow ) {
			$new_views = [];

			// remove the count from each status
			foreach ( $views as $k => $v ) {
				$new_views[ $k ] = preg_replace( '/\(\d+\)/', '', $v );
			}

			$views = $new_views;
		}

		return $views;
	}

	/**
	 * Add dashboard widgets for vendors
	 *
	 * @access public
	 * @since 2.1.0
	 * @version 2.1.0
	 * @return bool
	 */
	public function add_vendor_dashboard_widget() {
		wp_add_dashboard_widget(
			'wcpv_vendor_appointments_dashboard_widget',
			__( 'Recent Appointments', 'woocommerce-appointments' ),
			[ $this, 'render_appointments_dashboard_widget' ]
		);

		return true;
	}

	/**
	 * Renders the appointments dashboard widgets for vendors
	 *
	 * @access public
	 * @since 3.1.0
	 * @version 3.1.0
	 * @return bool
	 */
	public function render_appointments_dashboard_widget() {
		$appointments = WC_Appointments_Cache::get( 'wcpv_reports_appointments_wg_' . WC_Product_Vendors_Utils::get_logged_in_vendor() );
		if ( false === $appointments ) {
			$args = [
				'post_type'      => 'wc_appointment',
				'posts_per_page' => 20,
				'post_status'    => get_wc_appointment_statuses(),
			];

			$appointments = get_posts( apply_filters( 'wcpv_appointments_list_widget_args', $args ) );

			if ( ! empty( $appointments ) ) {
				// filter out only appointments with products of the vendor
				$appointments = array_filter( $appointments, [ $this, 'filter_appointment_products' ] );
			}

			WC_Appointments_Cache::set( 'wcpv_reports_appointments_wg_' . WC_Product_Vendors_Utils::get_logged_in_vendor(), $appointments, DAY_IN_SECONDS );
		}

		if ( empty( $appointments ) ) {
			echo '<p>' . esc_attr__( 'There are no appointments available.', 'woocommerce-appointments' ) . '</p>';

			return;
		}
		?>

		<table class="wcpv-vendor-appointments-widget wp-list-table widefat fixed striped posts">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Appointment ID', 'woocommerce-appointments' ); ?></th>
					<th><?php esc_html_e( 'Scheduled Product', 'woocommerce-appointments' ); ?></th>
					<th><?php esc_html_e( 'Customer', 'woocommerce-appointments' ); ?></th>
					<th><?php esc_html_e( 'Start Date', 'woocommerce-appointments' ); ?></th>
					<th><?php esc_html_e( 'End Date', 'woocommerce-appointments' ); ?></th>
				</tr>
			</thead>

			<tbody id="the-list">
				<?php
				foreach ( $appointments as $appointment ) {
					$appointment_item = get_wc_appointment( $appointment->ID );
					?>
					<tr>
						<td><a href="<?php echo esc_url( get_edit_post_link( $appointment->ID ) ); ?>" title="<?php esc_attr_e( 'Edit Appointment', 'woocommerce-appointments' ); ?>"><?php printf( esc_attr__( 'Appointment #%d', 'woocommerce-appointments' ), esc_attr( $appointment->ID ) ); ?></a></td>

						<td><a href="<?php echo esc_url( get_edit_post_link( $appointment_item->get_product_id() ) ); ?>" title="<?php esc_attr_e( 'Edit Product', 'woocommerce-appointments' ); ?>"><?php esc_attr_e( $appointment_item->get_product()->post->post_title ); ?></a></td>

						<td>
							<?php
							if ( $appointment_item->get_customer() ) {
							?>
								<a href="mailto:<?php esc_attr_e( $appointment_item->get_customer()->email ); ?>"><?php esc_attr_e( $appointment_item->get_customer()->full_name ); ?></a>
							<?php
							} else {
								esc_html_e( 'N/A', 'woocommerce-appointments' );
							}
							?>
						</td>

						<td>
							<?php
							if ( $appointment_item->get_order() ) {
							?>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=wcpv-vendor-order&id=' . $appointment_item->order_id ) ); ?>" title="<?php esc_attr_e( 'Order Detail', 'woocommerce-appointments' ); ?>"><?php printf( esc_attr__( '#%d', 'woocommerce-appointments' ), esc_attr( $appointment_item->order_id ) ); ?></a> &mdash; <?php esc_attr_e( WC_Product_Vendors_Utils::format_order_status( $appointment_item->get_order()->get_status() ) ); ?>
							<?php
							} else {
								esc_html_e( 'N/A', 'woocommerce-appointments' );
							}
							?>
						</td>

						<td><?php esc_attr_e( $appointment_item->get_start_date() ); ?></td>
						<td><?php esc_attr_e( $appointment_item->get_end_date() ); ?></td>
					</tr>
					<?php
				}
				?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Filters the product ids for logged in vendor
	 *
	 * @access public
	 * @since 3.1.0
	 * @version 3.1.0
	 * @param string $term the slug of the term
	 * @return array $ids product ids
	 */
	public function filter_appointment_products( $item ) {
		$product_ids = WC_Product_Vendors_Utils::get_vendor_product_ids();

		$appointment_item = get_wc_appointment( $item->ID );

		if ( is_object( $appointment_item ) && is_object( $appointment_item->get_product() ) && $appointment_item->get_product_id() && in_array( $appointment_item->get_product_id(), $product_ids ) ) {
			return $item;
		}
	}

	public function add_appointment_redirect( $location ) {
		if ( ! WC_Product_Vendors_Utils::is_vendor() ) {
			return $location;
		}

		if ( ! is_admin() ) {
			return $location;
		}

		// most likely an admin, no need to redirect
		if ( current_user_can( 'manage_options' ) ) {
			return $location;
		}

		if ( preg_match( '/\bpost=(\d+)/', $location, $matches ) ) {
			// check the post type
			$post = get_post( $matches[1] );

			if ( 'shop_order' === $post->post_type ) {
				wp_safe_redirect( admin_url( 'admin.php?page=wcpv-vendor-order&id=' . $post->ID ) );
				exit;
			}
		}

		return $location;
	}

	/**
	 * Clears the recent appointments cache on dashboard
	 *
	 * @access public
	 * @since 2.0.21
	 * @version 2.0.21
	 * @return bool
	 */
	public function clear_recent_appointments_cache_on_create() {
		global $wpdb;

		$wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE '%wcpv_reports_appointments_wg%'" );

		return true;
	}

	/**
	 * Clears the recent appointments cache on dashboard
	 *
	 * @access public
	 * @since 2.0.21
	 * @version 2.0.21
	 * @return bool
	 */
	public function clear_recent_appointments_cache_on_save_post( $post_id, $post ) {
		global $wpdb;

		if ( 'wc_appointment' !== $post->post_type ) {
			return;
		}

		$wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE '%wcpv_reports_appointments_wg%'" );

		return true;
	}
}

$GLOBALS['wc_appointments_integration_product_vendors'] = new WC_Appointments_Integration_Product_Vendors();
