<?php
/**
 * Admin appointment cancelled email
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/admin-appointment-cancelled.php.
 *
 * HOWEVER, on occasion we will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see         https://docs.woocommerce.com/document/template-structure/
 * @version     4.14.0
 * @since       3.4.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

$text_align  = is_rtl() ? 'right' : 'left';
$appointment = wc_appointments_maybe_appointment_object( $appointment );
$appointment = $appointment ? $appointment : get_wc_appointment( 0 );
$wc_order    = $appointment->get_order();
?>

<?php do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

<p><?php esc_html_e( 'The following appointment has been cancelled by the customer. The details of the cancelled appointment can be found below.', 'woocommerce-appointments' ); ?></p>

<table class="td" cellspacing="0" cellpadding="6" style="width: 100%; margin:0 0 16px;" border="1">
	<tbody>
		<?php if ( $appointment->get_product() ) : ?>
			<tr>
				<th class="td" scope="row" style="text-align:<?php esc_attr_e( $text_align ); ?>;">
					<?php esc_html_e( 'Scheduled Product', 'woocommerce-appointments' ); ?>
				</th>
				<td class="td" style="text-align:<?php esc_attr_e( $text_align ); ?>;">
					<?php echo wp_kses_post( $appointment->get_product_name() ); ?>
				</td>
			</tr>
		<?php endif; ?>
		<tr>
			<th class="td" scope="row" style="text-align:<?php esc_attr_e( $text_align ); ?>;">
				<?php esc_html_e( 'Appointment ID', 'woocommerce-appointments' ); ?>
			</th>
			<td class="td" style="text-align:<?php esc_attr_e( $text_align ); ?>;">
				<?php esc_attr_e( $appointment->get_id() ); ?>
			</td>
		</tr>
		<tr>
			<th class="td" scope="row" style="text-align:<?php esc_attr_e( $text_align ); ?>;">
				<?php esc_html_e( 'Appointment Date', 'woocommerce-appointments' ); ?>
			</th>
			<td class="td" style="text-align:<?php esc_attr_e( $text_align ); ?>;">
				<?php esc_attr_e( $appointment->get_start_date() ); ?>
			</td>
		</tr>
		<tr>
			<th class="td" scope="row" style="text-align:<?php esc_attr_e( $text_align ); ?>;">
				<?php esc_html_e( 'Appointment Duration', 'woocommerce-appointments' ); ?>
			</th>
			<td class="td" style="text-align:<?php esc_attr_e( $text_align ); ?>;">
				<?php esc_attr_e( $appointment->get_duration() ); ?>
			</td>
		</tr>
		<?php $staff = $appointment->get_staff_members( true ); ?>
		<?php if ( $appointment->has_staff() && $staff ) : ?>
			<?php $staff_label = $appointment->get_product()->get_staff_label() ? $appointment->get_product()->get_staff_label() : esc_html__( 'Appointment Providers', 'woocommerce-appointments' ); ?>
			<tr>
				<th class="td" scope="row" style="text-align:<?php esc_attr_e( $text_align ); ?>;">
					<?php echo wp_kses_post( $staff_label ); ?>
				</th>
				<td class="td" style="text-align:<?php esc_attr_e( $text_align ); ?>;">
					<?php esc_attr_e( $staff ); ?>
				</td>
			</tr>
		<?php endif; ?>
	</tbody>
</table>

<p>
<?php
echo make_clickable(
	sprintf(
		/* translators: %s: a link to an appointment */
		esc_html__( 'You can view and edit this appointment in the dashboard here: %s', 'woocommerce-appointments' ),
		admin_url( 'post.php?post=' . $appointment->get_id() . '&action=edit' )
	)
); // WPCS: XSS ok.
?>
</p>

<?php if ( $wc_order ) : ?>

	<?php do_action( 'woocommerce_email_order_details', $wc_order, $sent_to_admin, $plain_text, $email ); ?>

	<?php do_action( 'woocommerce_email_order_meta', $wc_order, $sent_to_admin, $plain_text, $email ); ?>

	<?php do_action( 'woocommerce_email_customer_details', $wc_order, $sent_to_admin, $plain_text, $email ); ?>

<?php endif; ?>

<?php do_action( 'woocommerce_email_footer', $email ); ?>
