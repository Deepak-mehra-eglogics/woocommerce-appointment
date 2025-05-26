<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

$GLOBALS['post']    = get_post( $product->get_id() ); #Deposits integration.
$GLOBALS['product'] = $product; #Add-ons integration.
wp_dequeue_script( 'selectWoo' );
?>
<div class="wrap woocommerce">
	<h2><?php esc_html_e( 'Add New Appointment', 'woocommerce-appointments' ); ?></h2>

	<?php $this->show_errors(); ?>

	<form method="POST" class="wc-appointments-appointment-form-wrap cart" enctype="multipart/form-data" action="<?php echo esc_url( apply_filters( 'woocommerce_add_to_cart_form_action', '' ) ); ?>" autocomplete="off">
		<table class="form-table">
			<tbody>
				<tr>
					<th scope="row">
						<label><?php esc_html_e( 'Appointment Data', 'woocommerce-appointments' ); ?></label>
					</th>
					<td>
						<div class="wc-appointments-appointment-form">
							<div class="wc-appointments-appointment-hook wc-appointments-appointment-hook-before"><?php do_action( 'woocommerce_before_appointment_form_output', 'before', $product->get_id() ); ?></div>
							<?php $appointment_form->output(); ?>
							<div class="wc-appointments-appointment-hook wc-appointments-appointment-hook-after"><?php do_action( 'woocommerce_after_appointment_form_output', 'after', $product->get_id() ); ?></div>
							<div class="wc-appointments-appointment-cost"></div>
						</div>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">&nbsp;</th>
					<td>
						<?php do_action( 'woocommerce_before_add_to_cart_button', $product->get_id() ); ?>
						<?php
						// Show quantity only when maximum qty is larger than 1 ... duuuuuuh
						// Condition commented in 4.17.5.
						#if ( $product->get_qty() > 1 && $product->get_qty_max() > 1 && ! $product->is_sold_individually() ) {
							do_action( 'woocommerce_before_add_to_cart_quantity', $product->get_id() );

							woocommerce_quantity_input(
								[
									'min_value'   => apply_filters( 'woocommerce_quantity_input_min', $product->get_qty_min(), $product ),
									'max_value'   => apply_filters( 'woocommerce_quantity_input_max', $product->get_qty_max(), $product ),
									'input_value' => isset( $_POST['quantity'] ) ? wc_stock_amount( $_POST['quantity'] ) : 1,
								]
							);

							do_action( 'woocommerce_after_add_to_cart_quantity', $product->get_id() );
						#}
						?>
						<button type="submit" class="button-primary wc-appointments-appointment-form-button single_add_to_cart_button disabled">
							<?php esc_html_e( 'Add New Appointment', 'woocommerce-appointments' ); ?>
						</button>
						<input type="hidden" name="add_appointment_2" value="1" />
						<input type="hidden" name="customer_id" value="<?php esc_attr_e( $customer_id ); ?>" />
						<input type="hidden" name="appointable_product_id" value="<?php esc_attr_e( $appointable_product_id ); ?>" />
						<input type="hidden" name="add-to-cart" value="<?php esc_attr_e( $appointable_product_id ); ?>" />
						<input type="hidden" name="appointment_order" value="<?php esc_attr_e( $appointment_order ); ?>" />
						<input type="hidden" name="_billing_first_name" value="<?php esc_attr_e( $_billing_first_name ); ?>" />
						<input type="hidden" name="_billing_last_name" value="<?php esc_attr_e( $_billing_last_name ); ?>" />
						<input type="hidden" name="_billing_company" value="<?php esc_attr_e( $_billing_company ); ?>" />
						<input type="hidden" name="_billing_address_1" value="<?php esc_attr_e( $_billing_address_1 ); ?>" />
						<input type="hidden" name="_billing_address_2" value="<?php esc_attr_e( $_billing_address_2 ); ?>" />
						<input type="hidden" name="_billing_city" value="<?php esc_attr_e( $_billing_city ); ?>" />
						<input type="hidden" name="_billing_postcode" value="<?php esc_attr_e( $_billing_postcode ); ?>" />
						<input type="hidden" name="_billing_country" value="<?php esc_attr_e( $_billing_country ); ?>" />
						<input type="hidden" name="_billing_state" value="<?php esc_attr_e( $_billing_state ); ?>" />
						<input type="hidden" name="_billing_email" value="<?php esc_attr_e( $_billing_email ); ?>" />
						<input type="hidden" name="_billing_phone" value="<?php esc_attr_e( $_billing_phone ); ?>" />
						<input type="hidden" name="_payment_method" value="<?php esc_attr_e( $_payment_method ); ?>" />
						<input type="hidden" name="_transaction_id" value="<?php esc_attr_e( $_transaction_id ); ?>" />

						<?php do_action( 'woocommerce_after_add_to_cart_button', $product->get_id() ); ?>

						<?php wp_nonce_field( 'add_appointment_notification' ); ?>
					</td>
				</tr>
			</tbody>
		</table>
	</form>
</div>
