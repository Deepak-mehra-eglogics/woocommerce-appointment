<?php
/**
 * The template for displaying an appointment summary to customers.
 * It will display in three places:
 * - After checkout,
 * - In the order confirmation email, and
 * - When customer reviews order in My Account > Orders.
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/order/appointment-display.php.
 *
 * HOWEVER, on occasion we will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see     https://docs.woocommerce.com/document/template-structure/
 * @version 4.17.4
 * @since   3.4.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

if ( $appointment_ids ) {
	foreach ( $appointment_ids as $appointment_id ) {
		$appointment = get_wc_appointment( $appointment_id );
		// Don't proceed if ID is not of a valid appointment.
		if ( ! is_a( $appointment, 'WC_Appointment' ) ) {
			continue;
		}
		?>
		<div class="wc-appointment-summary" style="margin-top: 10px; margin-bottom: 10px;">
			<div class="wc-appointment-summary-name">
				<?php
				printf(
					'<strong%1$s>%2$s</strong>',
					esc_html( isset( $is_rtl ) && 'right' === $is_rtl ? ' dir="rtl"' : '' ),
					sprintf(
						/* translators: %1$s: optional RTL dir attr, %2$d: appointment ID */
						esc_html__( 'Appointment #%d', 'woocommerce-appointments' ),
						esc_attr( $appointment->get_id() )
					)
				);
				// Display only on view order page.
				if ( is_wc_endpoint_url( 'view-order' ) ) {
					printf(
						' &mdash; <small class="status-%s">%s</small>',
						esc_attr( $appointment->get_status() ),
						esc_attr( wc_appointments_get_status_label( $appointment->get_status() ) )
					);
				}
				?>
			</div>
			<?php wc_appointments_get_summary_list( $appointment ); ?>
		</div>
		<?php
	}
}
