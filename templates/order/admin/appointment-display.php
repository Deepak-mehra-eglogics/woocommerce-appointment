<?php
/**
 * The template for displaying an appointment summary in the admin.
 * It will display in one place:
 * - When reviewing a customer order.
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/order/admin/appointment-display.php.
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
			<div class="wc-appointment-summary-name" style="white-space: nowrap;">
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
				printf(
					' &mdash; <small class="status-%s">%s</small>',
					esc_attr( $appointment->get_status() ),
					esc_attr( wc_appointments_get_status_label( $appointment->get_status() ) )
				);
				?>
			</div>
			<?php wc_appointments_get_summary_list( $appointment ); ?>
			<div class="wc-appointment-summary-actions">
				<?php
				// Confirmation link.
				if ( $appointment->has_status( array( 'pending-confirmation' ) ) ) {
					printf(
						'<a href="%1$s">%2$s</a>',
						esc_url(
							wp_nonce_url(
								admin_url( 'admin-ajax.php?action=wc-appointment-confirm&appointment_id=' . $appointment_id ),
								'wc-appointment-confirm'
							)
						),
						esc_attr( 'Confirm appointment', 'woocommerce-appointments' )
					);
				}
				// Edit link.
				if ( $appointment_id ) {
					printf(
						'<a href="%1$s">%2$s</a>',
						esc_url(
							admin_url( 'post.php?post=' . absint( $appointment_id ) . '&action=edit' )
						),
						esc_attr( 'Edit Appointment', 'woocommerce-appointments' )
					);
				}
				?>
			</div>
		</div>
		<?php
	}
}
