<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;
?>
<div class="options_group show_if_appointment">
	<?php
	woocommerce_wp_checkbox(
		[
			'id'          => '_wc_appointment_has_price_label',
			'label'       => __( 'Label instead of price?', 'woocommerce-appointments' ),
			'value'       => $appointable_product->get_has_price_label( 'edit' ) ? 'yes' : 'no',
			'description' => __( 'Check this box if the appointment should display text label instead of fixed price amount.', 'woocommerce-appointments' ),
		]
	);

	woocommerce_wp_text_input(
		[
			'id'          => '_wc_appointment_price_label',
			'label'       => __( 'Price Label', 'woocommerce-appointments' ),
			'placeholder' => __( 'Price Varies', 'woocommerce-appointments' ),
			'value'       => $appointable_product->get_price_label( 'edit' ),
			'desc_tip'    => true,
			'description' => __( 'Show this label instead of fixed price amount.', 'woocommerce-appointments' ),
		]
	);

	woocommerce_wp_checkbox(
		[
			'id'          => '_wc_appointment_has_pricing',
			'label'       => __( 'Custom pricing rules?', 'woocommerce-appointments' ),
			'value'       => $appointable_product->get_has_pricing( 'edit' ) ? 'yes' : 'no',
			'description' => __( 'Check this box if the appointment has custom pricing rules.', 'woocommerce-appointments' ),
		]
	);
	?>
	<?php do_action( 'woocommerce_appointments_after_display_cost', get_the_ID() ); ?>
	<div id="appointments_pricing">
		<div class="table_grid">
			<table class="widefat">
				<thead>
					<tr>
						<th class="sort">&nbsp;</th>
						<th class="range_type"><?php esc_html_e( 'Type', 'woocommerce-appointments' ); ?></th>
						<th class="range_name"><?php esc_html_e( 'Range', 'woocommerce-appointments' ); ?></th>
						<th class="range_name2"></th>
						<th class="range_cost"><?php esc_html_e( 'Base cost', 'woocommerce-appointments' ); ?><?php echo wc_help_tip( esc_html__( 'Applied to the appointment as a whole. Must be inside range rules to be applied.', 'woocommerce-appointments' ) ); // WPCS: XSS ok. ?></th>
						<th class="range_cost"><?php esc_html_e( 'Slot cost', 'woocommerce-appointments' ); ?><?php echo wc_help_tip( esc_html__( 'Applied to each appointment slot separately. When appointment lasts for 2 days or more, this cost applies to each day in range separately.', 'woocommerce-appointments' ) ); // WPCS: XSS ok. ?></th>
						<th class="remove">&nbsp;</th>
					</tr>
				</thead>
				<tfoot>
					<tr>
						<th colspan="7">
							<a
								href="#"
								class="button add_grid_row"
								<?php
								ob_start();
								require 'html-appointment-pricing-fields.php';
								$html = ob_get_clean();
								echo 'data-row="' . esc_attr( $html ) . '"';
								?>
							>
								<?php esc_html_e( 'Add Rule', 'woocommerce-appointments' ); ?>
							</a>
							<span class="description"><?php esc_html_e( 'All matching rules will be applied to the appointment.', 'woocommerce-appointments' ); ?></span>
						</th>
					</tr>
				</tfoot>
				<tbody id="pricing_rows">
					<?php
					$values = $appointable_product->get_pricing( 'edit' );
					#print '<pre>'; print_r( $values ); print '</pre>';
					if ( ! empty( $values ) && is_array( $values ) ) {
						foreach ( $values as $pricing ) {
							require 'html-appointment-pricing-fields.php';
							do_action( 'woocommerce_appointments_pricing_fields', $pricing );
						}
					}
					?>
				</tbody>
			</table>
		</div>
		<?php do_action( 'woocommerce_appointments_after_appointments_pricing', get_the_ID() ); ?>
	</div>
</div>
<div class="options_group show_if_appointment">
	<?php
	$duration      = 0 === $appointable_product->get_duration( 'edit' ) ? 1 : max( absint( $appointable_product->get_duration( 'edit' ) ), 1 );
	$duration_unit = $appointable_product->get_duration_unit( 'edit' );
	if ( '' == $duration_unit ) {
		$duration_unit = 'hour';
	}
	?>
	<p class="form-field">
		<label for="_wc_appointment_duration"><?php esc_html_e( 'Duration', 'woocommerce-appointments' ); ?></label>
		<input type="number" name="_wc_appointment_duration" id="_wc_appointment_duration" value="<?php esc_html_e( $duration ); ?>" step="1" min="1" style="margin-right: 7px; width: 4em;">
		<select name="_wc_appointment_duration_unit" id="_wc_appointment_duration_unit" class="short" style="width: auto; margin-right: 7px;">
			<option value="minute" <?php selected( $duration_unit, 'minute' ); ?>><?php esc_html_e( 'Minute(s)', 'woocommerce-appointments' ); ?></option>
			<option value="hour" <?php selected( $duration_unit, 'hour' ); ?>><?php esc_html_e( 'Hour(s)', 'woocommerce-appointments' ); ?></option>
			<option value="day" <?php selected( $duration_unit, 'day' ); ?>><?php esc_html_e( 'Day(s)', 'woocommerce-appointments' ); ?></option>
			<option value="month" <?php selected( $duration_unit, 'month' ); ?>><?php esc_html_e( 'Month(s)', 'woocommerce-appointments' ); ?></option>
		</select>
		<?php echo wc_help_tip( esc_html__( 'How long do you plan this appointment to last?', 'woocommerce-appointments' ) ); // WPCS: XSS ok. ?>
	</p>
	<?php
	$interval_s = $appointable_product->get_interval( 'edit' );
	if ( '' == $interval_s ) {
		$interval = $duration;
	} else {
		$interval = max( absint( $interval_s ), 1 );
	}
	$interval_unit = $appointable_product->get_interval_unit( 'edit' );
	if ( '' == $interval_unit ) {
		$interval_unit = $duration_unit;
	} elseif ( 'day' == $interval_unit ) {
		$interval_unit = 'hour';
	}
	?>
	<p class="form-field _wc_appointment_interval_duration_wrap">
		<label for="_wc_appointment_interval"><?php esc_html_e( 'Interval', 'woocommerce-appointments' ); ?></label>
		<input type="number" name="_wc_appointment_interval" id="_wc_appointment_interval" value="<?php esc_html_e( $interval ); ?>" step="1" min="1" style="margin-right: 7px; width: 4em;">
		<select name="_wc_appointment_interval_unit" id="_wc_appointment_interval_unit" class="short" style="width: auto; margin-right: 7px;">
			<option value="minute" <?php selected( $interval_unit, 'minute' ); ?>><?php esc_html_e( 'Minute(s)', 'woocommerce-appointments' ); ?></option>
			<option value="hour" <?php selected( $interval_unit, 'hour' ); ?>><?php esc_html_e( 'Hour(s)', 'woocommerce-appointments' ); ?></option>
		</select>
		<?php echo wc_help_tip( esc_html__( 'Select intervals when each appointment slot is available for scheduling.', 'woocommerce-appointments' ) ); // WPCS: XSS ok. ?>
	</p>
	<?php
	$padding_duration      = absint( $appointable_product->get_padding_duration( 'edit' ) );
	$padding_duration_unit = $appointable_product->get_padding_duration_unit( 'edit' );
	if ( '' == $padding_duration_unit ) {
		$padding_duration_unit = 'minute';
	}
	?>
	<p class="form-field _wc_appointment_padding_duration_wrap">
		<label for="_wc_appointment_padding_duration"><?php esc_html_e( 'Padding Time', 'woocommerce-appointments' ); ?></label>
		<input type="number" name="_wc_appointment_padding_duration" id="_wc_appointment_padding_duration" value="<?php esc_html_e( $padding_duration ); ?>" step="1" min="0" style="margin-right: 7px; width: 4em;">
		<select name="_wc_appointment_padding_duration_unit" id="_wc_appointment_padding_duration_unit" class="short" style="width: auto; margin-right: 7px;">
			<option value="minute" <?php selected( $padding_duration_unit, 'minute' ); ?>><?php esc_html_e( 'Minute(s)', 'woocommerce-appointments' ); ?></option>
			<option value="hour" <?php selected( $padding_duration_unit, 'hour' ); ?>><?php esc_html_e( 'Hour(s)', 'woocommerce-appointments' ); ?></option>
			<option value="day" <?php selected( $padding_duration_unit, 'day' ); ?>><?php esc_html_e( 'Day(s)', 'woocommerce-appointments' ); ?></option>
			<option value="month" <?php selected( $padding_duration_unit, 'month' ); ?>><?php esc_html_e( 'Month(s)', 'woocommerce-appointments' ); ?></option>
		</select>
		<?php echo wc_help_tip( esc_html__( 'Specify the padding time you need between appointments.', 'woocommerce-appointments' ) ); // WPCS: XSS ok. ?>
	</p>
	<?php
	$min_date      = absint( $appointable_product->get_min_date( 'edit' ) );
	$min_date_unit = $appointable_product->get_min_date_unit( 'edit' );
	if ( '' == $min_date_unit ) {
		$min_date_unit = 'month';
	}
	?>
	<p class="form-field">
		<label for="_wc_appointment_min_date"><?php esc_html_e( 'Lead Time', 'woocommerce-appointments' ); ?></label>
		<input type="number" name="_wc_appointment_min_date" id="_wc_appointment_min_date" value="<?php esc_html_e( $min_date ); ?>" step="1" min="0" style="margin-right: 7px; width: 4em;">
		<select name="_wc_appointment_min_date_unit" id="_wc_appointment_min_date_unit" class="short" style="margin-right: 7px; width: auto;">
			<option value="hour" <?php selected( $min_date_unit, 'hour' ); ?>><?php esc_html_e( 'Hour(s)', 'woocommerce-appointments' ); ?></option>
			<option value="day" <?php selected( $min_date_unit, 'day' ); ?>><?php esc_html_e( 'Day(s)', 'woocommerce-appointments' ); ?></option>
			<option value="week" <?php selected( $min_date_unit, 'week' ); ?>><?php esc_html_e( 'Week(s)', 'woocommerce-appointments' ); ?></option>
			<option value="month" <?php selected( $min_date_unit, 'month' ); ?>><?php esc_html_e( 'Month(s)', 'woocommerce-appointments' ); ?></option>
		</select> <?php echo wc_help_tip( esc_html__( 'How much in advance do you need before a client schedules an appointment?', 'woocommerce-appointments' ) ); // WPCS: XSS ok. ?>
	</p>
	<?php
	$max_date = $appointable_product->get_max_date( 'edit' );
	if ( '' == $max_date ) {
		$max_date = 12;
	}
	$max_date      = max( absint( $max_date ), 1 );
	$max_date_unit = $appointable_product->get_max_date_unit( 'edit' );
	if ( '' == $max_date_unit ) {
		$max_date_unit = 'month';
	}
	?>
	<p class="form-field">
		<label for="_wc_appointment_max_date"><?php esc_html_e( 'Scheduling Window', 'woocommerce-appointments' ); ?></label>
		<input type="number" name="_wc_appointment_max_date" id="_wc_appointment_max_date" value="<?php esc_html_e( $max_date ); ?>" step="1" min="1" style="margin-right: 7px; width: 4em;">
		<select name="_wc_appointment_max_date_unit" id="_wc_appointment_max_date_unit" class="short" style="margin-right: 7px; width: auto;">
			<option value="hour" <?php selected( $max_date_unit, 'hour' ); ?>><?php esc_html_e( 'Hour(s)', 'woocommerce-appointments' ); ?></option>
			<option value="day" <?php selected( $max_date_unit, 'day' ); ?>><?php esc_html_e( 'Day(s)', 'woocommerce-appointments' ); ?></option>
			<option value="week" <?php selected( $max_date_unit, 'week' ); ?>><?php esc_html_e( 'Week(s)', 'woocommerce-appointments' ); ?></option>
			<option value="month" <?php selected( $max_date_unit, 'month' ); ?>><?php esc_html_e( 'Month(s)', 'woocommerce-appointments' ); ?></option>
		</select>
		<?php echo wc_help_tip( esc_html__( 'How far in advance are customers allowed to schedule an appointment?', 'woocommerce-appointments' ) ); // WPCS: XSS ok. ?>
	</p>
</div>
<div class="options_group show_if_appointment">
	<?php
	woocommerce_wp_checkbox(
		[
			'id'          => '_wc_appointment_requires_confirmation',
			'label'       => __( 'Requires confirmation?', 'woocommerce-appointments' ),
			'value'       => $appointable_product->get_requires_confirmation( 'edit' ) ? 'yes' : 'no',
			'description' => __( 'Check this box if appointment requires confirmation. Payment will not be taken during checkout.', 'woocommerce-appointments' ),
		]
	);

	woocommerce_wp_checkbox(
		[
			'id'          => '_wc_appointment_user_can_cancel',
			'label'       => __( 'Can be cancelled?', 'woocommerce-appointments' ),
			'value'       => $appointable_product->get_user_can_cancel( 'edit' ) ? 'yes' : 'no',
			'description' => __( 'Check this box if appointment can be cancelled by the customer. A refund will not be sent automatically.', 'woocommerce-appointments' ),
		]
	);

	$cancel_limit      = max( absint( $appointable_product->get_cancel_limit( 'edit' ) ), 1 );
	$cancel_limit_unit = $appointable_product->get_cancel_limit_unit( 'edit' );
	if ( '' == $cancel_limit_unit ) {
		$cancel_limit_unit = 'day';
	}
	?>
	<p class="form-field appointment-cancel-limit">
		<label for="_wc_appointment_cancel_limit"><?php esc_html_e( 'Cancelled at least', 'woocommerce-appointments' ); ?></label>
		<input type="number" name="_wc_appointment_cancel_limit" id="_wc_appointment_cancel_limit" value="<?php esc_html_e( $cancel_limit ); ?>" step="1" min="1" style="margin-right: 7px; width: 4em;">
		<select name="_wc_appointment_cancel_limit_unit" id="_wc_appointment_cancel_limit_unit" class="short" style="width: auto; margin-right: 7px;">
			<option value="month" <?php selected( $cancel_limit_unit, 'month' ); ?>><?php esc_html_e( 'Month(s)', 'woocommerce-appointments' ); ?></option>
			<option value="day" <?php selected( $cancel_limit_unit, 'day' ); ?>><?php esc_html_e( 'Day(s)', 'woocommerce-appointments' ); ?></option>
			<option value="hour" <?php selected( $cancel_limit_unit, 'hour' ); ?>><?php esc_html_e( 'Hour(s)', 'woocommerce-appointments' ); ?></option>
			<option value="minute" <?php selected( $cancel_limit_unit, 'minute' ); ?>><?php esc_html_e( 'Minute(s)', 'woocommerce-appointments' ); ?></option>
		</select>
		<span class="description"><?php esc_html_e( 'before the start date.', 'woocommerce-appointments' ); ?></span>
	</p>
	<?php
	woocommerce_wp_checkbox(
		[
			'id'          => '_wc_appointment_user_can_reschedule',
			'label'       => __( 'Can be rescheduled?', 'woocommerce-appointments' ),
			'value'       => $appointable_product->get_user_can_reschedule( 'edit' ) ? 'yes' : 'no',
			'description' => __( 'Check this box if appointment can be rescheduled by the customer.', 'woocommerce-appointments' ),
		]
	);

	$reschedule_limit      = max( absint( $appointable_product->get_reschedule_limit( 'edit' ) ), 1 );
	$reschedule_limit_unit = $appointable_product->get_reschedule_limit_unit( 'edit' );
	if ( '' == $reschedule_limit_unit ) {
		$reschedule_limit_unit = 'day';
	}
	?>
	<p class="form-field appointment-reschedule-limit">
		<label for="_wc_appointment_reschedule_limit"><?php esc_html_e( 'Rescheduled at least', 'woocommerce-appointments' ); ?></label>
		<input type="number" name="_wc_appointment_reschedule_limit" id="_wc_appointment_reschedule_limit" value="<?php esc_html_e( $reschedule_limit ); ?>" step="1" min="1" style="margin-right: 7px; width: 4em;">
		<select name="_wc_appointment_reschedule_limit_unit" id="_wc_appointment_reschedule_limit_unit" class="short" style="width: auto; margin-right: 7px;">
			<option value="month" <?php selected( $reschedule_limit_unit, 'month' ); ?>><?php esc_html_e( 'Month(s)', 'woocommerce-appointments' ); ?></option>
			<option value="day" <?php selected( $reschedule_limit_unit, 'day' ); ?>><?php esc_html_e( 'Day(s)', 'woocommerce-appointments' ); ?></option>
			<option value="hour" <?php selected( $reschedule_limit_unit, 'hour' ); ?>><?php esc_html_e( 'Hour(s)', 'woocommerce-appointments' ); ?></option>
			<option value="minute" <?php selected( $reschedule_limit_unit, 'minute' ); ?>><?php esc_html_e( 'Minute(s)', 'woocommerce-appointments' ); ?></option>
		</select>
		<span class="description"><?php esc_html_e( 'before the start date.', 'woocommerce-appointments' ); ?></span>
	</p>
	<?php
	woocommerce_wp_checkbox(
		[
			'id'          => '_wc_appointment_customer_timezones',
			'label'       => __( 'Customer timezones?', 'woocommerce-appointments' ),
			'value'       => $appointable_product->get_customer_timezones( 'edit' ) ? 'yes' : 'no',
			'description' => __( 'Check this box if can be converted to customer\'s timezone.', 'woocommerce-appointments' ),
		]
	);
	?>
	<script type="text/javascript">
		jQuery( '._tax_status_field' ).closest( '.show_if_simple' ).addClass( 'show_if_appointment' );
		jQuery( '#_wc_appointment_duration_unit' ).trigger( 'change' );
	</script>
</div>
<div class="options_group show_if_appointment">
	<?php
	$cal_color_val = $appointable_product->get_cal_color( 'edit' );
	if ( '' == $cal_color_val ) {
		$cal_color_val = '#0073aa'; // default color
	}
	woocommerce_wp_text_input(
		[
			'id'          => '_wc_appointment_cal_color',
			'label'       => __( 'Calendar color', 'woocommerce-appointments' ),
			'value'       => $cal_color_val,
			'description' => __( 'Pick a color that will represent this appointable product inside admin calendar.', 'woocommerce-appointments' ),
		]
	);
	?>
</div>
