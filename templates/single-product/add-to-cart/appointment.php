<?php
/**
 * Appointment product add to cart
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/single-product/add-to-cart/appointment.php.
 *
 * HOWEVER, on occasion we will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see         https://docs.woocommerce.com/document/template-structure/
 * @version     4.17.5
 * @since       1.0.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

global $product;

if ( ! $product->is_purchasable() ) {
	return;
}

do_action( 'woocommerce_before_add_to_cart_form', $product->get_id() ); ?>
<noscript>
	<?php
	esc_html_e(
		'Your browser must support JavaScript in order to schedule an appointment.',
		'woocommerce-appointments'
	);
	?>
</noscript>
<form
	class="wc-appointments-appointment-form-wrap cart"
	action="<?php echo esc_url( apply_filters( 'woocommerce_add_to_cart_form_action', '' ) ); ?>"
	method="post"
	enctype='multipart/form-data'
	autocomplete="off"
>
 	<div id="wc-appointments-appointment-form" class="wc-appointments-appointment-form" style="display:none">
		<div class="wc-appointments-appointment-hook wc-appointments-appointment-hook-before">
			<?php do_action( 'woocommerce_before_appointment_form_output', 'before', $product->get_id() ); ?>
		</div>
		<?php $appointment_form->output(); ?>
		<div class="wc-appointments-appointment-hook wc-appointments-appointment-hook-after">
			<?php do_action( 'woocommerce_after_appointment_form_output', 'after', $product->get_id() ); ?>
		</div>
		<div class="wc-appointments-appointment-cost price"></div>
	</div>
	<?php do_action( 'woocommerce_before_add_to_cart_button', $product->get_id() ); ?>
	<input
		type="hidden"
		name="add-to-cart"
		value="<?php esc_attr_e( $product->get_id() ); ?>"
		class="wc-appointment-product-id"
	/>
	<?php
	// Show quantity only when maximum qty is larger than 1 ... duuuuuuh
	// Condition commented in 4.17.5.
	#if ( $product->get_qty() > 1 && $product->get_qty_max() > 1 && ! $product->is_sold_individually() ) {
		do_action( 'woocommerce_before_add_to_cart_quantity', $product->get_id() );

		woocommerce_quantity_input(
			[
				'min_value'   => apply_filters( 'woocommerce_quantity_input_min', $product->get_qty_min(), $product ),
				'max_value'   => apply_filters( 'woocommerce_quantity_input_max', $product->get_qty_max(), $product ),
				'input_value' => ( isset( $_POST['quantity'] ) ? wc_stock_amount( wc_clean( wp_unslash( $_POST['quantity'] ) ) ) : 1 ), // WPCS: CSRF ok, input var ok.
			]
		);

		do_action( 'woocommerce_after_add_to_cart_quantity', $product->get_id() );
	#}
	?>
	<button
		type="submit"
		class="wc-appointments-appointment-form-button single_add_to_cart_button button alt disabled"
		style="display:none"
	>
		<?php esc_html_e( $product->single_add_to_cart_text() ); ?>
	</button>
 	<?php do_action( 'woocommerce_after_add_to_cart_button', $product->get_id() ); ?>
</form>
<?php do_action( 'woocommerce_after_add_to_cart_form', $product->get_id() ); ?>
