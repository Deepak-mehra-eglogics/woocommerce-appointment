<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;
?>
<div id="appointments_availability" class="panel woocommerce_options_panel wc-metaboxes-wrapper">
	<div class="options_group show_if_appointment">
		<?php
		woocommerce_wp_select(
			[
				'id'          => '_wc_appointment_availability_span',
				'label'       => __( 'Availability Check', 'woocommerce-appointments' ),
				'description' => __( 'By default availability per each slot in range is checked. You can also check availability for starting slot only.', 'woocommerce-appointments' ),
				'desc_tip'    => true,
				'value'       => $appointable_product->get_availability_span( 'edit' ),
				'options'     => [
					''      => __( 'All slots in availability range', 'woocommerce-appointments' ),
					'start' => __( 'The starting slot only', 'woocommerce-appointments' ),
				],
			]
		);

		woocommerce_wp_checkbox(
			[
				'id'          => '_wc_appointment_availability_autoselect',
				'label'       => __( 'Auto-select?', 'woocommerce-appointments' ),
				'value'       => $appointable_product->get_availability_autoselect( 'edit' ) ? 'yes' : 'no',
				'description' => __( 'Check this box if you want to auto-select first available day and/or time.', 'woocommerce-appointments' ),
			]
		);

		woocommerce_wp_checkbox(
			[
				'id'          => '_wc_appointment_has_restricted_days',
				'value'       => $appointable_product->has_restricted_days( 'edit' ) ? 'yes' : 'no',
				'label'       => __( 'Restrict start days?', 'woocommerce-appointments' ),
				'description' => __( 'Restrict appointments so that they can only start on certain days of the week. Does not affect availability.', 'woocommerce-appointments' ),
			]
		);
		?>
		<div class="appointment-day-restriction">
			<table class="widefat">
				<tbody>
					<tr>
						<td>&nbsp;</td>
						<?php
						$start_of_week = absint( get_option( 'start_of_week', 1 ) );
						for ( $i = $start_of_week; $i < $start_of_week + 7; $i ++ ) {
							$day_time   = strtotime( "next sunday +{$i} day" );
							$day_number = date_i18n( _x( 'w', 'date format', 'woocommerce-appointments' ), $day_time ); #day of week number (zero to six)
							$day_name   = date_i18n( _x( 'D', 'date format', 'woocommerce-appointments' ), $day_time ); #day of week name (Mon to Sun)
							?>
							<td>
								<label class="checkbox" for="_wc_appointment_restricted_days[<?php esc_html_e( $day_number ); ?>]"><?php esc_html_e( $day_name ); ?>&nbsp;</label>
								<input type="checkbox" class="checkbox" name="_wc_appointment_restricted_days[<?php esc_html_e( $day_number ); ?>]" id="_wc_appointment_restricted_days[<?php esc_html_e( $day_number ); ?>]" value="<?php esc_html_e( $day_number ); ?>" <?php checked( $restricted_days[ $day_number ], $day_number ); ?>>
							</td>
						<?php
						}
						?>
						<td>&nbsp;</td>
					</tr>
				</tbody>
			</table>
		</div>
	</div>
	<div class="options_group">
		<div class="toolbar">
			<h3><?php esc_html_e( 'Custom Availability', 'woocommerce-appointments' ); ?></h3>
		</div>
		<p>
			<?php
			/* translators: %s: a href to global availability setings */
			printf( __( 'Add custom availability rules to override <a href="%s">global availability</a> for this appointment only.', 'woocommerce-appointments' ), esc_url( admin_url( 'admin.php?page=wc-settings&tab=appointments' ) ) );
			?>
		</p>
		<?php
		$product_availabilities = WC_Data_Store::load( 'appointments-availability' )->get_all(
			[
				[
					'key'     => 'kind',
					'compare' => '=',
					'value'   => 'availability#product',
				],
				[
					'key'     => 'kind_id',
					'compare' => '=',
					'value'   => $appointable_product->get_id(),
				],
			]
		);
		$show_title             = false;
		?>
		<div class="table_grid availability_table_grid">
			<table class="widefat">
				<thead>
					<tr>
						<th class="sort">&nbsp;</th>
						<th class="range_type"><?php esc_html_e( 'Type', 'woocommerce-appointments' ); ?></th>
						<th class="range_name"><?php esc_html_e( 'Range', 'woocommerce-appointments' ); ?></th>
						<th class="range_name2"></th>
						<th class="range_capacity"><?php esc_html_e( 'Quantity', 'woocommerce-appointments' ); ?><?php echo wc_help_tip( esc_html__( 'The maximum number of appointments per slot. Overrides product quantity.', 'woocommerce-appointments' ) ); // WPCS: XSS ok. ?></th>
						<th class="range_priority"><?php esc_html_e( 'Priority', 'woocommerce-appointments' ); ?><?php echo wc_help_tip( esc_html__( 'Rules with lower priority numbers will override rules with a higher priority (e.g. 9 overrides 10 ). By using priority numbers you can execute rules in different orders for all three levels: Global, Product and Staff rules.', 'woocommerce-appointments' ) ); // WPCS: XSS ok. ?></th>
						<th class="range_appointable"><?php esc_html_e( 'Available', 'woocommerce-appointments' ); ?><?php echo wc_help_tip( esc_html__( 'If not available, users won\'t be able to choose slots in this range for their appointment.', 'woocommerce-appointments' ) ); // WPCS: XSS ok. ?></th>
						<?php do_action( 'woocommerce_appointments_extra_availability_fields_header' ); ?>
						<th class="remove">&nbsp;</th>
					</tr>
				</thead>
				<tfoot>
					<tr>
						<th colspan="9">
							<a
								href="#"
								class="button add_grid_row"
								<?php
								ob_start();
								require 'html-appointment-availability-fields.php';
								$html = ob_get_clean();
								echo 'data-row="' . esc_attr( $html ) . '"';
								?>
							>
								<?php esc_html_e( 'Add Rule', 'woocommerce-appointments' ); ?>
							</a>
							<span class="description"><?php esc_html_e( get_wc_appointment_rules_explanation() ); ?></span>
						</th>
					</tr>
				</tfoot>
				<tbody id="availability_rows">
					<?php
					if ( ! empty( $product_availabilities ) && is_array( $product_availabilities ) ) {
						foreach ( $product_availabilities as $availability ) {
							if ( $availability->has_past() ) {
								continue;
							}
							include 'html-appointment-availability-fields.php';
						}
					}
					?>
				</tbody>
			</table>
		</div>
		<input type="hidden" name="wc_appointment_availability_deleted" value="" class="wc-appointment-availability-deleted" />
	</div>
</div>
