<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Display availability fields.
 *
 * @package Woocommerce/Appointments
 * @var $availability WC_Appointments_Availability
 */

$intervals = [];

$intervals['months'] = [
	'1'  => __( 'January', 'woocommerce-appointments' ),
	'2'  => __( 'February', 'woocommerce-appointments' ),
	'3'  => __( 'March', 'woocommerce-appointments' ),
	'4'  => __( 'April', 'woocommerce-appointments' ),
	'5'  => __( 'May', 'woocommerce-appointments' ),
	'6'  => __( 'June', 'woocommerce-appointments' ),
	'7'  => __( 'July', 'woocommerce-appointments' ),
	'8'  => __( 'August', 'woocommerce-appointments' ),
	'9'  => __( 'September', 'woocommerce-appointments' ),
	'10' => __( 'October', 'woocommerce-appointments' ),
	'11' => __( 'November', 'woocommerce-appointments' ),
	'12' => __( 'December', 'woocommerce-appointments' ),
];

$intervals['days'] = [
	'1' => __( 'Monday', 'woocommerce-appointments' ),
	'2' => __( 'Tuesday', 'woocommerce-appointments' ),
	'3' => __( 'Wednesday', 'woocommerce-appointments' ),
	'4' => __( 'Thursday', 'woocommerce-appointments' ),
	'5' => __( 'Friday', 'woocommerce-appointments' ),
	'6' => __( 'Saturday', 'woocommerce-appointments' ),
	'7' => __( 'Sunday', 'woocommerce-appointments' ),
];

/* translators: %s: week number */
$week_string = __( 'Week %s', 'woocommerce-appointments' );
for ( $i = 1; $i <= 53; $i ++ ) {
	$intervals['weeks'][ $i ] = sprintf( $week_string, $i );
}

if ( ! isset( $availability['type'] ) ) {
	$availability['type'] = 'custom';
}

if ( ! isset( $availability['priority'] ) ) {
	$availability['priority'] = 10;
}

$availability_title = ! empty( $availability['title'] ) ? $availability['title'] : '';
$kind               = ! empty( $availability['kind'] ) ? $availability['kind'] : '';
$kind_id            = ! empty( $availability['kind_id'] ) ? $availability['kind_id'] : '';
$event_id           = ! empty( $availability['event_id'] ) ? $availability['event_id'] : '';
$availability_id    = ! empty( $availability['ID'] ) ? $availability['ID'] : '';
$is_google          = ! empty( $availability['event_id'] );
$is_rrule           = 'rrule' === $availability['type'];

// Get current screen.
$get_current_screen    = function_exists( 'get_current_screen' ) ? get_current_screen() : '';
$get_current_screen_id = $get_current_screen ? $get_current_screen->id : '';

?>
<tr data-id="<?php esc_attr_e( $availability_id ); ?>">
	<input type="hidden" name="wc_appointment_availability_id[]" value="<?php esc_attr_e( $availability_id ); ?>"/>
	<input type="hidden" name="wc_appointment_availability_kind_id[]" value="<?php esc_attr_e( $kind_id ); ?>" />
	<input type="hidden" name="wc_appointment_availability_event_id[]" value="<?php esc_attr_e( $event_id ); ?>" />
	<td class="sort">&nbsp;</td>
	<td class="range_type">
		<div class="select wc_appointment_availability_type">
			<?php if ( $is_google ) { ?>
				<p>
					<strong>
						<?php
						if ( $is_rrule ) {
							esc_html_e( 'Google Recurring Event', 'woocommerce-appointments' );
							?>
							<input type="hidden" name="wc_appointment_availability_type[]" value="rrule"/>
							<?php
						} else {
							esc_html_e( 'Google Event', 'woocommerce-appointments' );
							?>
							<input type="hidden" name="wc_appointment_availability_type[]" value="<?php esc_attr_e( $availability['type'] ); ?>"/>
							<?php
						}
						?>
					</strong>
				</p>
			<?php } else { ?>
				<select name="wc_appointment_availability_type[]">
					<option value="custom" <?php selected( $availability['type'], 'custom' ); ?>><?php esc_html_e( 'Date range', 'woocommerce-appointments' ); ?></option>
					<option value="custom:daterange" <?php selected( $availability['type'], 'custom:daterange' ); ?>><?php esc_html_e( 'Date range with time', 'woocommerce-appointments' ); ?></option>
					<option value="months" <?php selected( $availability['type'], 'months' ); ?>><?php esc_html_e( 'Range of months', 'woocommerce-appointments' ); ?></option>
					<option value="weeks" <?php selected( $availability['type'], 'weeks' ); ?>><?php esc_html_e( 'Range of weeks', 'woocommerce-appointments' ); ?></option>
					<option value="days" <?php selected( $availability['type'], 'days' ); ?>><?php esc_html_e( 'Range of days', 'woocommerce-appointments' ); ?></option>
					<optgroup label="<?php esc_html_e( 'Time Ranges', 'woocommerce-appointments' ); ?>">
						<option value="time" <?php selected( $availability['type'], 'time' ); ?>><?php esc_html_e( 'Recurring Time (all week)', 'woocommerce-appointments' ); ?></option>
						<option value="time:range" <?php selected( $availability['type'], 'time:range' ); ?>><?php esc_html_e( 'Recurring Time (date range)', 'woocommerce-appointments' ); ?></option>
						<?php foreach ( $intervals['days'] as $key => $label ) : ?>
							<option value="time:<?php esc_html_e( $key ); ?>" <?php selected( $availability['type'], 'time:' . $key ); ?>><?php esc_html_e( $label ); ?></option>
						<?php endforeach; ?>
					</optgroup>
				</select>
			<?php } ?>
		</div>
	</td>
	<td class="range_from" <?php echo $is_rrule ? 'colspan="2"' : ''; ?>>
		<div class="appointments-datetime-select-from">
			<?php
			if ( $is_rrule ) {
				$is_all_day = false === strpos( $availability['from'], ':' );
				$rrule_str  = wc_appointments_esc_rrule( $availability['rrule'], $is_all_day );
				#$rrule_str   = $availability['rrule'];
				#print '<pre>'; print_r( $availability['rrule'] ); print '</pre>';
				#print '<pre>'; print_r( $rrule_str ); print '</pre>';
				$date_format = $is_all_day ? 'Y-m-d' : 'Y-m-d g:i A';
				$from_date   = new WC_DateTime( $availability['from'] );
				$to_date     = new WC_DateTime( $availability['to'] );
				$timezone    = new DateTimeZone( wc_timezone_string() );
				$from_date->setTimezone( $timezone );
				$to_date->setTimezone( $timezone );
				$human_readable_options = [
					'date_formatter' => function( $date ) use ( $date_format ) {
						return $date->format( $date_format );
					},
					'locale'         => 'en',
				];

				try {
					$rset = new \RRule\RSet( $rrule_str, $is_all_day ? $from_date->format( $date_format ) : $from_date );
					?>
					<div class="rrule">
					<strong>
						<?php esc_html_e( $from_date->format( $date_format ) ); ?>
						<?php esc_html_e( 'to', 'woocommerce-appointments' ); ?>
						<?php esc_html_e( $to_date->format( $date_format ) ); ?>
					</strong>
					<br />
					<?php
					esc_html_e( 'Repeating ', 'woocommerce-appointments' );
					foreach ( $rset->getRRules() as $rrule ) {
						esc_html_e( $rrule->humanReadable( $human_readable_options ) );
					}
					if ( $rset->getExDates() ) {
						esc_html_e( ', except ', 'woocommerce-appointments' );
						esc_html_e(
							join(
								' and ',
								array_map(
									function ( $date ) use ( $date_format ) {
										return $date->format( $date_format );
									},
									$rset->getExDates()
								)
							)
						);
					}
				} catch ( Exception $e ) {
					?>
					<strong><?php esc_html_e( 'Invalid recurring rule', 'woocommerce-appointments' ); ?></strong><br />
					<?php
					esc_html_e( $e->getMessage() );
				}
				?>
				</div>
				<input type="hidden" name="wc_appointment_availability_from_day_of_week[]" value=""/>
				<input type="hidden" name="wc_appointment_availability_from_month[]" value=""/>
				<input type="hidden" name="wc_appointment_availability_from_week[]" value=""/>
				<input type="hidden" name="wc_appointment_availability_from_date[]" value=""/>
				<input type="hidden" name="wc_appointment_availability_from_time[]" value=""/>
			<?php } else { ?>
				<?php if ( $is_google ) { ?>
					<input type="hidden" name="wc_appointment_availability_from_day_of_week[]" value=""/>
					<input type="hidden" name="wc_appointment_availability_from_month[]" value=""/>
					<input type="hidden" name="wc_appointment_availability_from_week[]" value=""/>
				<?php } ?>
				<?php if ( ! $is_google ) { ?>
					<div class="select from_day_of_week">
						<select name="wc_appointment_availability_from_day_of_week[]" class="day-of-week-picker">
							<?php foreach ( $intervals['days'] as $key => $label ) : ?>
								<option value="<?php esc_html_e( $key ); ?>" <?php selected( isset( $availability['from'] ) && $availability['from'] == $key, true ); ?>><?php esc_html_e( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="select from_month">
						<select name="wc_appointment_availability_from_month[]" class="month-picker">
							<?php foreach ( $intervals['months'] as $key => $label ) : ?>
								<option value="<?php esc_html_e( $key ); ?>" <?php selected( isset( $availability['from'] ) && $availability['from'] == $key, true ); ?>><?php esc_html_e( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="select from_week">
						<select name="wc_appointment_availability_from_week[]" class="week-picker">
							<?php foreach ( $intervals['weeks'] as $key => $label ) : ?>
								<option value="<?php esc_html_e( $key ); ?>" <?php selected( isset( $availability['from'] ) && $availability['from'] == $key, true ); ?>><?php esc_html_e( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
				<?php } ?>
				<div class="from_date">
					<?php
					$from_date = '';
					if ( 'custom' === $availability['type'] && ! empty( $availability['from'] ) ) {
						$from_date = $availability['from'];
					} elseif ( in_array( $availability['type'], [ 'time:range', 'custom:daterange' ], true ) && ! empty( $availability['from_date'] ) ) {
						$from_date = $availability['from_date'];
					}
					?>
					<input type="text" class="date-picker" name="wc_appointment_availability_from_date[]" value="<?php esc_attr_e( $from_date ); ?>" placeholder="yyyy-mm-dd" autocomplete="off" />
				</div>
				<div class="from_time">
					<?php
					$from_time = '';
					if ( strrpos( $availability['type'], 'time' ) === 0 || 'custom:daterange' === $availability['type'] ) {
						$from_time = $availability['from'];
					}
					?>
					<input
						type="time"
						class="time-picker"
						name="wc_appointment_availability_from_time[]"
						value="<?php esc_attr_e( $from_time ); ?>"
						placeholder="HH:MM"
					/>
				</div>
			<?php } ?>
		</div>
	</td>
	<td class="range_to" style="<?php echo $is_rrule ? 'display:none;' : ''; ?>">
		<div class='appointments-datetime-select-to'>
			<?php if ( $is_google ) { ?>
				<input type="hidden" name="wc_appointment_availability_to_day_of_week[]" value=""/>
				<input type="hidden" name="wc_appointment_availability_to_month[]" value=""/>
				<input type="hidden" name="wc_appointment_availability_to_week[]" value=""/>
			<?php } ?>
			<?php if ( ! $is_google ) { ?>
				<div class="select to_day_of_week">
					<select name="wc_appointment_availability_to_day_of_week[]" class="day-of-week-picker">
						<?php foreach ( $intervals['days'] as $key => $label ) : ?>
							<option value="<?php esc_html_e( $key ); ?>" <?php selected( isset( $availability['to'] ) && $availability['to'] == $key, true ); ?>><?php esc_html_e( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="select to_month">
					<select name="wc_appointment_availability_to_month[]" class="month-picker">
						<?php foreach ( $intervals['months'] as $key => $label ) : ?>
							<option value="<?php esc_html_e( $key ); ?>" <?php selected( isset( $availability['to'] ) && $availability['to'] == $key, true ); ?>><?php esc_html_e( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="select to_week">
					<select name="wc_appointment_availability_to_week[]" class="week-picker">
						<?php foreach ( $intervals['weeks'] as $key => $label ) : ?>
							<option value="<?php esc_html_e( $key ); ?>" <?php selected( isset( $availability['to'] ) && $availability['to'] == $key, true ); ?>><?php esc_html_e( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			<?php } ?>
			<div class="to_date">
				<?php
				$to_date = '';
				if ( 'custom' === $availability['type'] && ! empty( $availability['to'] ) ) {
					$to_date = $availability['to'];
				} elseif ( in_array( $availability['type'], [ 'time:range', 'custom:daterange' ], true ) && ! empty( $availability['to_date'] ) ) {
					$to_date = $availability['to_date'];
				}
				?>
				<input type="text" class="date-picker" name="wc_appointment_availability_to_date[]" value="<?php esc_attr_e( $to_date ); ?>" placeholder="yyyy-mm-dd" autocomplete="off" />
			</div>

			<div class="to_time">
				<?php
				$to_time = '';
				if ( strrpos( $availability['type'], 'time' ) === 0 || 'custom:daterange' === $availability['type'] ) {
					$to_time = $availability['to'];
				}
				?>
				<input
					type="time"
					class="time-picker"
					name="wc_appointment_availability_to_time[]"
					value="<?php esc_attr_e( $to_time ); ?>"
					placeholder="HH:MM"
				/>
			</div>
		</div>
	</td>
	<?php if ( 'product' === $get_current_screen_id ) { ?>
		<td class="range_capacity">
			<input
				type="number"
				name="wc_appointment_availability_qty[]"
				id="wc_appointment_availability_qty"
				<?php
				if ( isset( $availability['qty'] ) && ! empty( $availability['qty'] ) ) {
					echo 'value="' . esc_html( $availability['qty'] ) . '"';
				}
				?>
				step="1"
				min="1"
				placeholder="<?php esc_html_e( 'N/A', 'woocommerce-appointments' ); ?>"
			/>
		</td>
	<?php } ?>
	<?php if ( ! empty( $show_title ) ) : ?>
		<td>
			<div class="title">
				<input type="text" name="wc_appointment_availability_title[]" value="<?php esc_html_e( $availability_title ); ?>" />
			</div>
		</td>
	<?php endif; ?>
	<td class="range_priority">
		<div class="priority">
			<input type="number" name="wc_appointment_availability_priority[]" value="<?php esc_attr_e( $availability['priority'] ); ?>" placeholder="10" />

		</div>
	</td>
	<td class="range_appointable">
		<div class="select">
		<?php if ( $is_google ) : ?>
			<p>
				<?php esc_html_e( 'No', 'woocommerce-appointments' ); ?>
			</p>
			<input type="hidden" name="wc_appointment_availability_appointable[]" value="no"/>
		<?php else : ?>
			<select name="wc_appointment_availability_appointable[]">
				<option value="no" <?php selected( isset( $availability['appointable'] ) && 'no' === $availability['appointable'], true ); ?>><?php esc_html_e( 'No', 'woocommerce-appointments' ); ?></option>
				<option value="yes" <?php selected( isset( $availability['appointable'] ) && 'yes' === $availability['appointable'], true ); ?>><?php esc_html_e( 'Yes', 'woocommerce-appointments' ); ?></option>
			</select>
		<?php endif; ?>
		</div>
	</td>
	<?php if ( ! empty( $show_title ) ) : ?>
		<?php do_action( 'woocommerce_appointments_extra_availability_fields', $availability ); ?>
	<?php endif; ?>
	<td class="remove remove_grid_row remove_rule">&nbsp;</td>
</tr>
