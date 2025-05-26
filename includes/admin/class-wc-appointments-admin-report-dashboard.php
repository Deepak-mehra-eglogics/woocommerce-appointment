<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Logic for WooCommerce dashboard display.
 */
class WC_Appointments_Admin_Report_Dashboard {

	/**
	 * Hook in additional reporting to WooCommerce dashboard widget
	 */
	public function __construct() {
		// Add the dashboard widget text
		add_action( 'woocommerce_after_dashboard_status_widget', __CLASS__ . '::add_stats_to_dashboard' );
	}

	/**
	 * Add the Appointments specific details to the bottom of the dashboard widget
	 */
	public static function add_stats_to_dashboard() {
		global $wpdb;

		$new_appointments = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT wcappointments.ID) AS count
					FROM {$wpdb->posts} AS wcappointments
					INNER JOIN {$wpdb->posts} AS wcorder
						ON wcappointments.post_parent = wcorder.ID
					WHERE wcorder.post_type IN ( 'shop_order' )
						AND wcappointments.post_type IN ( 'wc_appointment' )
						AND wcorder.post_status IN ( 'wc-completed', 'wc-processing', 'wc-on-hold', 'wc-refunded' )
						AND wcorder.post_date >= '%s'
						AND wcorder.post_date < '%s'",
				date( 'Y-m-01', current_time( 'timestamp' ) ),
				date( 'Y-m-d H:i:s', current_time( 'timestamp' ) )
			)
		);

		$require_confirmation = (int) $wpdb->get_var(
			"SELECT COUNT(DISTINCT wcappointments.ID) AS count
				FROM {$wpdb->posts} AS wcappointments
					WHERE wcappointments.post_type IN ( 'wc_appointment' )
					AND wcappointments.post_status = 'pending-confirmation'"
		);

		?>
		<li class="processing-orders">
			<a href="<?php esc_html_e( admin_url( 'edit.php?post_type=wc_appointment&post_status=paid' ) ); ?>">
				<?php
				/* translators: %1$s: number of appointments, %2$s: number of appointments */
				printf( wp_kses_post( _n( '<strong>%s appointment</strong> new this month', '<strong>%s appointments</strong> new this month', esc_html( $new_appointments ), 'woocommerce-appointments' ) ), esc_html( $new_appointments ) );
				?>
			</a>
		</li>
		<li class="low-in-stock">
			<a href="<?php esc_html_e( admin_url( 'edit.php?post_type=wc_appointment&post_status=pending-confirmation' ) ); ?> ">
				<?php
				/* translators: %1$s: number of appointments that require confirmation, %2$s: number of appointments that require confirmation */
				printf( wp_kses_post( _n( '<strong>%s appointment</strong> requires confirmation', '<strong>%s appointments</strong> require confirmation', esc_html( $new_appointments ), 'woocommerce-appointments' ) ), esc_html( $require_confirmation ) );
				?>
			</a>
		</li>
		<?php
	}
}

new WC_Appointments_Admin_Report_Dashboard();
