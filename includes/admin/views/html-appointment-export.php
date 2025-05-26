<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

wp_enqueue_style( 'woocommerce_admin_styles' );
wp_enqueue_style( 'jquery-ui-style' );
wp_enqueue_script( 'wc_appointments_exporter_js' );

$exporter = new WC_Appointment_CSV_Exporter();
?>
<div class="wrap woocommerce">
	<h1><?php esc_html_e( 'Export Appointments', 'woocommerce-appointments' ); ?></h1>

	<div class="woocommerce-exporter-wrapper">
		<form class="woocommerce-exporter">
			<header>
				<span class="spinner is-active"></span>
				<h2><?php esc_html_e( 'Export appointments to a CSV file', 'woocommerce-appointments' ); ?></h2>
			</header>
			<section>
				<table class="form-table woocommerce-exporter-options">
					<tbody>
						<tr>
							<th scope="row">
								<label for="woocommerce-exporter-columns"><?php esc_html_e( 'Columns', 'woocommerce-appointments' ); ?></label>
							</th>
							<td>
								<select
									id="woocommerce-exporter-columns"
									class="woocommerce-exporter-columns wc-enhanced-select"
									style="width:100%;"
									multiple="multiple"
									data-placeholder="<?php esc_attr_e( 'Export all columns', 'woocommerce-appointments' ); ?>"
									data-allow_clear="true"
								>
									<?php
									foreach ( $exporter->get_default_column_names() as $column_id => $column_name ) {
										echo '<option value="' . esc_attr( $column_id ) . '">' . esc_html( $column_name ) . '</option>';
									}
									?>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="woocommerce-exporter-start"><?php esc_html_e( 'Start Date', 'woocommerce-appointments' ); ?></label>
							</th>
							<td>
								<div class="calendar_filter">
									<input
									    type="search"
										class="date_from date-picker"
										id="woocommerce-exporter-start"
										name="woocommerce-exporter-start"
										style="width:100%;"
										placeholder="YYYY-MM-DD"
										autocomplete="off"
									/>
								</div>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="woocommerce-exporter-end"><?php esc_html_e( 'End Date', 'woocommerce-appointments' ); ?></label>
							</th>
							<td>
								<div class="calendar_filter">
									<input
									    type="search"
										class="date_from date-picker"
										id="woocommerce-exporter-end"
										name="woocommerce-exporter-end"
										style="width:100%;"
										placeholder="YYYY-MM-DD"
										autocomplete="off"
									/>
								</div>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="woocommerce-exporter-product"><?php esc_html_e( 'Products', 'woocommerce-appointments' ); ?></label>
							</th>
							<td>
								<select
									class="woocommerce-exporter-product wc-product-search"
									id="woocommerce-exporter-product"
									name="woocommerce-exporter-product"
									style="width:100%;"
									multiple="multiple"
									data-placeholder="<?php esc_html_e( 'Export for all products', 'woocommerce-appointments' ); ?>"
									data-action="woocommerce_json_search_appointable_products"
									data-allow_clear="true"
								>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="woocommerce-exporter-staff"><?php esc_html_e( 'Staff', 'woocommerce-appointments' ); ?></label>
							</th>
							<td>
								<?php
								// Staff select.
								$appointable_staff = WC_Appointments_Admin::get_appointment_staff(); #all staff
								?>
								<select
								    class="woocommerce-exporter-staff wc-enhanced-select"
									id="woocommerce-exporter-staff"
									name="woocommerce-exporter-staff"
									style="width:100%;"
									multiple="multiple"
									data-placeholder="<?php esc_attr_e( 'Export for all staff', 'woocommerce-appointments' ); ?>"
									data-allow_clear="true"
								>
									<?php
									foreach ( $appointable_staff as $staff_member ) {
										echo '<option value="' . esc_attr( $staff_member->ID ) . '">' . esc_html( $staff_member->display_name ) . '</option>';
									}
									?>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="woocommerce-exporter-addon"><?php esc_html_e( 'Export add-on fields?', 'woocommerce-appointments' ); ?></label>
							</th>
							<td>
								<input type="checkbox" id="woocommerce-exporter-addon" value="1" />
								<label for="woocommerce-exporter-addon"><?php esc_html_e( 'Yes, export all add-on fields', 'woocommerce-appointments' ); ?></label>
							</td>
						</tr>
						<?php do_action( 'woocommerce_appointment_export_row' ); ?>
					</tbody>
				</table>
				<progress class="woocommerce-exporter-progress" max="100" value="0"></progress>
			</section>
			<div class="wc-actions">
				<button type="submit" class="woocommerce-exporter-button button button-primary" value="<?php esc_attr_e( 'Generate CSV', 'woocommerce-appointments' ); ?>"><?php esc_html_e( 'Generate CSV', 'woocommerce-appointments' ); ?></button>
			</div>
		</form>
	</div>
</div>
