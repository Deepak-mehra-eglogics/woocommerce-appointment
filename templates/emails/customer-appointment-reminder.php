<?php
/**
 * Customer appointment reminder email
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/customer-appointment-reminder.php.
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
?>

<?php do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

<?php if ( $appointment->get_start_date( wc_appointments_date_format(), '' ) === date( wc_appointments_date_format() ) ) : ?>
	<p><?php esc_html_e( 'Your appointment will take place today.', 'woocommerce-appointments' ); ?></p>
<?php else : ?>
	<p>
	<?php
	printf(
		/* translators: %1$s: appointment start date */
		esc_html__( 'Your appointment will take place on %1$s.', 'woocommerce-appointments' ),
		esc_html( $appointment->get_start_date() )
	);
	?>
	</p>
<?php endif; ?>

<table class="td" cellspacing="0" cellpadding="6" style="width: 100%; margin:0 0 16px;" border="1">
	<tbody>
		<tr>
			<th class="td" scope="row" style="text-align:<?php esc_attr_e( $text_align ); ?>;">
				<?php esc_html_e( 'Scheduled Product', 'woocommerce-appointments' ); ?>
			</th>
			<td class="td" style="text-align:<?php esc_attr_e( $text_align ); ?>;">
				<?php echo wp_kses_post( $appointment->get_product_name() ); ?>
			</td>
		</tr>
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

<?php
/**
 * Show user-defined additonal content - this is set in each email's settings.
 */
if ( $additional_content ) {
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}
?>

<?php do_action( 'woocommerce_email_footer', $email ); ?>
