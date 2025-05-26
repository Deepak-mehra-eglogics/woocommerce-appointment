<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

wp_enqueue_script( 'wc_appointments_writepanel_js' );
wp_enqueue_script( 'wc-admin-order-meta-boxes' );
?>
<div class="wrap woocommerce">
	<h2><?php esc_html_e( 'Add New Appointment', 'woocommerce-appointments' ); ?></h2>

	<p
	<?php
	esc_html_e(
		'You can add a new appointment for a customer here. Created orders will be marked as pending payment.',
		'woocommerce-appointments'
	);
	?>
	</p>

	<?php $this->show_errors(); ?>

	<form method="POST">
		<table class="form-table">
			<tbody>
				<?php do_action( 'woocommerce_appointments_before_add_appointment_page' ); ?>
				<tr valign="top">
					<th scope="row">
						<label for="appointable_product_id"><?php esc_html_e( 'Product', 'woocommerce-appointments' ); ?></label>
					</th>
					<td>
						<select
							id="appointable_product_id"
							name="appointable_product_id"
							class="wc-product-search"
							data-placeholder="<?php esc_html_e( 'Select an appointable product...', 'woocommerce-appointments' ); ?>"
							data-allow_clear="true"
							data-action="woocommerce_json_search_appointable_products"
							style="width: 300px;"
						></select>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">
						<label for="customer_id"><?php esc_html_e( 'Customer', 'woocommerce-appointments' ); ?></label>
					</th>
					<td>
						<select
							id="customer_id"
							name="customer_id"
							class="wc-customer-search"
							data-placeholder="<?php esc_html_e( 'Guest', 'woocommerce-appointments' ); ?>"
							data-allow_clear="true"
						></select>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">
						<label for="create_order"><?php esc_html_e( 'Order', 'woocommerce-appointments' ); ?></label>
					</th>
					<td>
						<p>
							<label>
								<input type="radio" name="appointment_order" value="new" class="checkbox appointment-order-selector" checked="checked" />
								<?php esc_html_e( 'Create a new order', 'woocommerce-appointments' ); ?>
								<?php echo wc_help_tip( esc_html__( 'Please note - appointment won\'t be active until order is processed/completed.', 'woocommerce-appointments' ) ); // WPCS: XSS ok. ?>
							</label>
						</p>
						<p>
							<label>
								<input type="radio" name="appointment_order" value="existing" class="checkbox appointment-order-selector" />
								<?php esc_html_e( 'Assign to an existing order', 'woocommerce-appointments' ); ?>
								<div class="appointment-order-label-select">
									<select
										id="appointment_order_id"
										name="appointment_order_id"
										data-placeholder="<?php esc_html_e( 'N/A', 'woocommerce-appointments' ); ?>"
										data-allow_clear="true"
									></select>
								</div>
							</label>
						</p>
					</td>
				</tr>
				<tr valign="top" class="billing_row">
					<th scope="row">
						<label for="billing">
							<?php esc_html_e( 'Billing', 'woocommerce-appointments' ); ?>
							<a href="#" class="edit_billing"><?php esc_html_e( 'Edit', 'woocommerce-appointments' ); ?></a>
						</label>
					</th>
					<td class="billing_column">
						<p class="none_set"><?php esc_html_e( 'No billing set.', 'woocommerce-appointments' ); ?></p>
						<div class="edit_billing" style="display:none;">
							<?php
							// Setup empty order to fill data.
							$theorder = new WC_Order();

							#error_log( var_export( self::$billing_fields, true ) );

							// Billing fields.
							$billing_fields = self::get_billing_fields();

							// Display form.
							foreach ( $billing_fields as $key => $field ) {
								if ( ! isset( $field['type'] ) ) {
									$field['type'] = 'text';
								}
								if ( ! isset( $field['id'] ) ) {
									$field['id'] = '_billing_' . $key;
								}
								if ( ! isset( $field['value'] ) ) {
									$field['value'] = '';
								}

								#$field['name'] = 'billing_' . $key;

								#error_log( var_export( $field, true ) );

								switch ( $field['type'] ) {
									case 'select':
										woocommerce_wp_select( $field, $theorder );
										break;
									default:
										woocommerce_wp_text_input( $field, $theorder );
										break;
								}
							}
							?>
							<p class="form-field form-field-wide">
								<label><?php esc_html_e( 'Payment method', 'woocommerce-appointments' ); ?></label>
								<select name="_payment_method" id="_payment_method" class="first">
									<option value=""><?php esc_html_e( 'N/A', 'woocommerce-appointments' ); ?></option>
									<?php
									$found_method = false;

									if ( WC()->payment_gateways() ) {
										$payment_gateways = WC()->payment_gateways->payment_gateways();
									} else {
										$payment_gateways = [];
									}

									foreach ( $payment_gateways as $gateway ) {
										if ( 'yes' === $gateway->enabled ) {
											// Skip the appointment gatway that is only used for
											// appointments that require confirmation.
											if ( 'wcappointmentsgateway' === $gateway->id ) {
												continue;
											}
											echo '<option value="' . esc_attr( $gateway->id ) . '">' . esc_html( $gateway->get_title() ) . '</option>';
										}
									}

									echo '<option value="other">' . esc_html__( 'Other', 'woocommerce-appointments' ) . '</option>';
									?>
								</select>
							</p>
							<?php
							woocommerce_wp_text_input(
								[
									'id'    => '_transaction_id',
									'label' => __( 'Transaction ID', 'woocommerce-appointments' ),
								],
								$theorder
							);
							?>
						</div>
					</td>
				</tr>
				<?php do_action( 'woocommerce_appointments_after_add_appointment_page' ); ?>
				<tr valign="top">
					<th scope="row">&nbsp;</th>
					<td>
						<input type="submit" name="add_appointment" class="button-primary" value="<?php esc_html_e( 'Next', 'woocommerce-appointments' ); ?>" />
						<?php wp_nonce_field( 'add_appointment_notification' ); ?>
					</td>
				</tr>
			</tbody>
		</table>
	</form>
</div>
