<div class="wrap woocommerce">
	<h2><?php esc_attr_e( 'Calendar', 'woocommerce-appointments' ); ?> <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=wc_appointment&page=add_appointment' ) ); ?>" class="add-new-h2"><?php esc_attr_e( 'Add New Appointment', 'woocommerce-appointments' ); ?></a></h2>
	<form method="get" id="mainform" enctype="multipart/form-data" class="wc_appointments_calendar_form month_view">
		<input type="hidden" name="post_type" value="wc_appointment" />
		<input type="hidden" name="page" value="appointment_calendar" />
		<input type="hidden" name="calendar_month" value="<?php echo absint( $month ); ?>" />
		<input type="hidden" name="view" value="<?php esc_attr_e( $view ); ?>" />
		<input type="hidden" name="tab" value="calendar" />

		<?php require 'html-calendar-nav.php'; ?>

		<table class="wc_appointments_calendar widefat">
			<thead>
				<tr>
					<?php $start_of_week = absint( get_option( 'start_of_week', 1 ) ); ?>
					<?php for ( $ii = $start_of_week; $ii < $start_of_week + 7; $ii ++ ) : ?>
						<th><?php esc_attr_e( date_i18n( _x( 'l', 'date format', 'woocommerce-appointments' ), strtotime( "next sunday +{$ii} day" ) ) ); ?></th>
					<?php endfor; ?>
				</tr>
			</thead>
			<tbody>
				<tr>
					<?php
					$timestamp    = $start_time;
					$current_date = date( 'Y-m-d', current_time( 'timestamp' ) );
					$index        = 0;
					while ( $timestamp <= $end_time ) :
						$timestamp_date = date( 'Y-m-d', $timestamp );
						$is_today       = $timestamp_date === $current_date;
						?>
						<td width="14.285%" class="<?php
						if ( date( 'n', $timestamp ) != absint( $month ) ) {
							echo 'calendar-diff-month';
						}
						if ( ( $timestamp + DAY_IN_SECONDS ) < current_time( 'timestamp' ) ) {
							echo ' calendar-passed-day';
						}
						if ( $is_today ) {
							echo ' calendar-current-day';
						}
						?>">
							<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=wc_appointment&page=appointment_calendar&view=day&tab=calendar&calendar_day=' . date( 'Y-m-d', $timestamp ) ) ); ?>" class="datenum">
								<?php esc_attr_e( date( 'd', $timestamp ) ); ?>
							</a>
							<div class="events bymonth">
								<?php
								$this->list_events(
									date( 'd', $timestamp ),
									date( 'm', $timestamp ),
									date( 'Y', $timestamp ),
									'by_month'
								);
								?>
							</div>
						</td>
						<?php
						$timestamp = strtotime( '+1 day', $timestamp );
						$index ++;

						if ( 0 === $index % 7 ) {
							echo '</tr><tr>';
						}
					endwhile;
					?>
				</tr>
			</tbody>
		</table>
	</form>
</div>
