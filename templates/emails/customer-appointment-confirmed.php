<?php
/**
 * Customer appointment confirmed email
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/customer-appointment-confirmed.php.
 *
 * HOWEVER, on occasion we will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see         https://docs.woocommerce.com/document/template-structure/
 * @version     4.14.2
 * @since       3.4.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

$text_align  = is_rtl() ? 'right' : 'left';
$appointment = wc_appointments_maybe_appointment_object( $appointment );
$appointment = $appointment ? $appointment : get_wc_appointment( 0 );
?>

<?php do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

<p><?php esc_html_e( 'Your appointment has been confirmed. The details of your appointment are shown below.', 'woocommerce-appointments' ); ?></p>

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

<?php $wc_order = $appointment->get_order(); ?>
<?php if ( $wc_order ) : ?>

	<?php if ( 'pending' === $wc_order->get_status() && 0 < $wc_order->get_total() ) : ?>
		<p>
		<?php
		printf(
			/* translators: %s: checkout payment url */
			esc_html__( 'To pay for this appointment please use the following link: %s', 'woocommerce-appointments' ),
			'<a href="' . esc_url( $wc_order->get_checkout_payment_url() ) . '">' . esc_attr__( 'Pay for appointment', 'woocommerce-appointments' ) . '</a>'
		);
		?>
		</p>
	<?php endif; ?>

	<?php do_action( 'woocommerce_email_before_order_table', $wc_order, $sent_to_admin, $plain_text, $email ); ?>

	<br />
	<h2>
	<?php esc_html_e( 'Order', 'woocommerce-appointments' ) . ': #' . esc_html( $wc_order->get_order_number() ); ?>
	(
	<?php
	$order_date = $wc_order->get_date_created() ? $wc_order->get_date_created()->date( 'Y-m-d H:i:s' ) : '';
	printf(
		'<time datetime="%s">%s</time>',
		esc_attr( date_i18n( 'c', strtotime( $order_date ) ) ),
		esc_attr( date_i18n( wc_appointments_date_format(), strtotime( $order_date ) ) )
	);
	?>
	)</h2>

	<table class="td" cellspacing="0" cellpadding="6" style="width: 100%; margin:0 0 16px;" border="1">
		<thead>
			<tr>
				<th class="td" scope="col" style="text-align:<?php esc_attr_e( $text_align ); ?>;">
					<?php esc_html_e( 'Product', 'woocommerce-appointments' ); ?>
				</th>
				<th class="td" scope="col" style="text-align:<?php esc_attr_e( $text_align ); ?>;">
					<?php esc_html_e( 'Quantity', 'woocommerce-appointments' ); ?>
				</th>
				<th class="td" scope="col" style="text-align:<?php esc_attr_e( $text_align ); ?>;">
					<?php esc_html_e( 'Price', 'woocommerce-appointments' ); ?>
				</th>
			</tr>
		</thead>
		<tbody>
			<?php
			switch ( $wc_order->get_status() ) {
				case 'completed':
					echo wc_get_email_order_items(
						$wc_order,
						[ 'show_sku' => false ]
					); // WPCS: XSS ok.
					break;
				case 'processing':
				default:
					echo wc_get_email_order_items(
						$wc_order,
						[ 'show_sku' => true ]
					); // WPCS: XSS ok.
					break;
			}
			?>
		</tbody>
		<tfoot>
			<?php
			$order_totals = $wc_order->get_order_item_totals();
			if ( $order_totals ) {
				$i = 0;
				foreach ( $order_totals as $order_total ) {
					$i++;
					?>
					<tr>
						<th class="td" scope="row" colspan="2" style="text-align:<?php esc_attr_e( $text_align ); ?>; <?php echo ( 1 === $i ) ? 'border-top-width: 4px;' : ''; ?>">
							<?php echo wp_kses_post( $order_total['label'] ); ?>
						</th>
						<td class="td" style="text-align:<?php esc_attr_e( $text_align ); ?>; <?php echo ( 1 === $i ) ? 'border-top-width: 4px;' : ''; ?>">
							<?php echo wp_kses_post( $order_total['value'] ); ?>
						</td>
					</tr>
					<?php
				}
			}
			?>
		</tfoot>
	</table>

	<?php do_action( 'woocommerce_email_after_order_table', $wc_order, $sent_to_admin, $plain_text, $email ); ?>

	<?php do_action( 'woocommerce_email_order_meta', $wc_order, $sent_to_admin, $plain_text, $email ); ?>

<?php endif; ?>

<?php
/**
 * Show user-defined additonal content - this is set in each email's settings.
 */
if ( $additional_content ) {
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}
?>

<?php do_action( 'woocommerce_email_footer', $email ); ?>
