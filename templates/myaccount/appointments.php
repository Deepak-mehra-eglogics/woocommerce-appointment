<?php
/**
 * My Appointments
 *
 * Shows customer appointments on the My Account > Appointments page
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/myaccount/appointments.php.
 *
 * HOWEVER, on occasion we will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see         https://docs.woocommerce.com/document/template-structure/
 * @version     4.15.1
 * @since       3.4.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;
?>
<noscript>
	<?php
	esc_html_e(
		'Your browser must support JavaScript in order to schedule an appointment.',
		'woocommerce-appointments'
	);
	?>
</noscript>
<?php
wp_enqueue_script( 'wc-appointments-my-account' );

if ( ! empty( $tables ) ) :
?>
	<?php
	foreach ( $tables as $table_id => $table ) :
		$i = 0;
		?>
		<h3><?php esc_html_e( $table['header'] ); ?></h3>
		<table class="shop_table shop_table_responsive my_account_orders my_account_appointments <?php esc_html_e( $table_id ) . '_appointments'; ?>">
			<thead>
				<tr>
					<th scope="col" class="appointment-id">
						<span class="nobr"><?php esc_html_e( 'Appointment', 'woocommerce-appointments' ); ?></span>
					</th>
					<th scope="col" class="appointment-when">
						<span class="nobr"><?php esc_html_e( 'When', 'woocommerce-appointments' ); ?></span>
					</th>
					<th scope="col" class="scheduled-product">
						<span class="nobr"><?php esc_html_e( 'Scheduled', 'woocommerce-appointments' ); ?></span>
					</th>
					<th scope="col" class="appointment-status">
						<span class="nobr"><?php esc_html_e( 'Status', 'woocommerce-appointments' ); ?></span>
					</th>
					<th scope="col" class="appointment-actions"></th>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach ( $table['appointments'] as $appointment ) :
					$i++;

					// Skip bookings which were added to detect pagination.
					if ( $i > $appointments_per_page ) {
						break;
					}

					// Can appointment be cancelled?
					$cancellable =
					    'cancelled' !== $appointment->get_status() &&
						'completed' !== $appointment->get_status() &&
					    ! $appointment->passed_cancel_day();

					// Can appointment be rescheduled?
					$reschedulable =
					    'cancelled' !== $appointment->get_status() &&
						'completed' !== $appointment->get_status() &&
						! $appointment->passed_reschedule_day();
					?>
					<tr>
						<td class="appointment-id anowrap" data-title="<?php esc_html_e( 'Appointment', 'woocommerce-appointments' ); ?>">
							<?php
							printf(
								'#%d',
								esc_attr( $appointment->get_id() )
							);
							if ( $appointment->get_order() ) :
								if ( 'pending-confirmation' !== $appointment->get_status() ) :
									printf(
										'<a href="%1$s" class="adesc">%2$s</a>',
										esc_url( $appointment->get_order()->get_view_order_url() ),
										esc_html__( 'Order', 'woocommerce-appointments' )
									);
								endif;
							endif;
							?>
						</td>
						<td class="appointment-when anowrap" data-title="<?php esc_html_e( 'When', 'woocommerce-appointments' ); ?>">
							<?php esc_attr_e( $appointment->get_start_date() ); ?>
							<span class="adesc"><?php esc_attr_e( $appointment->get_duration() ); ?></span>
						</td>
						<td class="scheduled-product" data-title="<?php esc_html_e( 'Scheduled', 'woocommerce-appointments' ); ?>">
							<?php if ( $appointment->get_product() && $appointment->get_product()->is_type( 'appointment' ) ) : ?>
								<a href="<?php echo esc_url( get_permalink( $appointment->get_product_id() ) ); ?>">
									<?php esc_html_e( $appointment->get_product_name() ); ?>
								</a>
							<?php endif; ?>
						</td>
						<td class="appointment-status" data-title="<?php esc_html_e( 'Status', 'woocommerce-appointments' ); ?>">
							<?php esc_html_e( wc_appointments_get_status_label( $appointment->get_status() ) ); ?>
						</td>
						<td class="appointment-actions" data-title="<?php esc_html_e( 'Actions', 'woocommerce-appointments' ); ?>">
							<?php if ( $cancellable ) : ?>
								<a href="<?php echo esc_url( $appointment->get_cancel_url() ); ?>" class="woocommerce-button button anowrap cancel">
									<?php esc_html_e( 'Cancel', 'woocommerce-appointments' ); ?>
								</a>
							<?php endif ?>
							<?php if ( $reschedulable ) : ?>
								<a href="<?php echo esc_url( $appointment->get_reschedule_url() ); ?>" class="woocommerce-button button anowrap reschedule">
									<?php esc_html_e( 'Reschedule', 'woocommerce-appointments' ); ?>
								</a>
							<?php endif ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php do_action( 'woocommerce_before_account_appointments_pagination' ); ?>
		<div class="woocommerce-pagination woocommerce-pagination--without-numbers woocommerce-Pagination">
			<?php if ( 1 !== $page ) : ?>
				<a href="<?php echo esc_url( wc_get_endpoint_url( $endpoint, $page - 1 ) ); ?>" class="woocommerce-button woocommerce-button--previous woocommerce-Button woocommerce-Button--previous button"><?php esc_html_e( 'Previous', 'woocommerce-appointments' ); ?></a>
			<?php endif; ?>

			<?php if ( count( $table['appointments'] ) > $appointments_per_page ) : ?>
				<a href="<?php echo esc_url( wc_get_endpoint_url( $endpoint, $page + 1 ) ); ?>" class="woocommerce-button woocommerce-button--next woocommerce-Button woocommerce-Button--next button"><?php esc_html_e( 'Next', 'woocommerce-appointments' ); ?></a>
			<?php endif; ?>
		</div>
		<?php do_action( 'woocommerce_after_account_appointments_pagination' ); ?>
	<?php endforeach; ?>
<?php else : ?>
	<div class="woocommerce-Message woocommerce-Message--info woocommerce-info">
		<a class="woocommerce-Button button" href="<?php echo esc_url( apply_filters( 'woocommerce_return_to_shop_redirect', wc_get_page_permalink( 'shop' ) ) ); ?>">
			<?php esc_html_e( 'Book', 'woocommerce-appointments' ); ?>
		</a>
		<?php esc_html_e( 'No appointments scheduled yet.', 'woocommerce-appointments' ); ?>
	</div>
<?php endif; ?>
