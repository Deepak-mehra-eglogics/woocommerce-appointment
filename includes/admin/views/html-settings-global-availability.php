<?php
wp_enqueue_script( 'jquery-ui-datepicker' );
wp_enqueue_script( 'wc_appointments_writepanel_js' );

// Current view.
$view = $_REQUEST['view'] ?? 'site';

// Get availabilites.
if ( 'synced' === $view ) {
	$availability_args = [
		[
			'key'     => 'kind',
			'compare' => '=',
			'value'   => 'availability#global',
		],
		[
			'key'     => 'event_id',
			'compare' => '!=',
			'value'   => '',
		],
	];
} else {
	$availability_args = [
		[
			'key'     => 'kind',
			'compare' => '=',
			'value'   => 'availability#global',
		],
		[
			'key'     => 'event_id',
			'compare' => '==',
			'value'   => '',
		],
	];
}

$global_availabilities = WC_Data_Store::load( 'appointments-availability' )->get_all( $availability_args );
$show_title            = true;
#print '<pre>'; print_r( $global_availabilities ); print '</pre>';
?>

<div id="appointments_settings">
	<input type="hidden" name="appointments_availability_submitted" value="1" />
	<h2 class="screen-reader-text">
		<?php esc_html_e( 'Global availability', 'woocommerce-appointments' ); ?>
	</h2>
	<p><?php esc_html_e( 'The availability rules you define here will affect all appointable products. You can override them for each product, staff.', 'woocommerce-appointments' ); ?></p>
	<div class="table_grid availability_table_grid" id="appointments_availability">
		<nav class="wca-nav-wrapper">
			<a class="wca-nav<?php echo ( 'site' === $view ) ? ' wca-nav-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'view', 'site' ) ); ?>">
				<?php esc_html_e( 'Site rules', 'woocommerce-appointments' ); ?>
			</a>
			<a class="wca-nav<?php echo ( 'synced' === $view ) ? ' wca-nav-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'view', 'synced' ) ); ?>">
				<?php esc_html_e( 'Synced rules', 'woocommerce-appointments' ); ?>
			</a>
		</nav>
		<table class="widefat">
			<thead>
				<tr>
					<th class="sort">&nbsp;</th>
					<th class="range_type"><?php esc_html_e( 'Type', 'woocommerce-appointments' ); ?></th>
					<th class="range_name"><?php esc_html_e( 'Range', 'woocommerce-appointments' ); ?></th>
					<th class="range_name2"></th>
					<th class="range_title"><?php esc_html_e( 'Title', 'woocommerce-appointments' ); ?></th>
					<th class="range_priority"><?php esc_html_e( 'Priority', 'woocommerce-appointments' ); ?><?php echo wc_help_tip( esc_html__( 'Rules with lower priority numbers will override rules with a higher priority (e.g. 9 overrides 10 ). By using priority numbers you can execute rules in different orders for all three levels: Global, Product and Staff rules.', 'woocommerce-appointments' ) ); // WPCS: XSS ok. ?></th>
					<th class="range_appointable"><?php esc_html_e( 'Available', 'woocommerce-appointments' ); ?><?php echo wc_help_tip( esc_html__( 'If not available, users won\'t be able to choose slots in this range for their appointment.', 'woocommerce-appointments' ) ); // WPCS: XSS ok. ?></th>
					<?php do_action( 'woocommerce_appointments_extra_availability_fields_header' ); ?>
					<th class="remove">&nbsp;</th>
				</tr>
			</thead>
			<?php if ( 'synced' !== $view ) : ?>
				<tfoot>
					<tr>
						<th colspan="8">
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
			<?php endif; ?>
			<tbody id="availability_rows">
				<?php
				if ( ! empty( $global_availabilities ) && is_array( $global_availabilities ) ) {
					foreach ( $global_availabilities as $availability ) {
						if ( $availability->has_past() ) {
							continue;
						}
						include 'html-appointment-availability-fields.php';
					}
				} elseif ( ! $global_availabilities && 'synced' === $view ) {
					?>
					<tr>
						<td colspan="8" style="text-align:center;">
							<?php esc_html_e( 'No synced rules.', 'woocommerce-appointments' ); ?>
						</td>
					</tr>
					<?php
				}
				?>
			</tbody>
		</table>
	</div>
	<input type="hidden" name="wc_appointment_availability_deleted" value="" class="wc-appointment-availability-deleted" />
</div>
