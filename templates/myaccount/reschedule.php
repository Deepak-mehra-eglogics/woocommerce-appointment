<?php
/**
 * Rescheduiling appointment form
 *
 * Shows customer appointment rescheduling form on the My Account > Reschedule page
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/myaccount/reschedule.php.
 *
 * HOWEVER, on occasion we will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see         https://docs.woocommerce.com/document/template-structure/
 * @version     4.14.0
 * @since       4.9.8
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
<form
	class="wc-appointments-appointment-form-wrap cart"
	action="<?php echo esc_url( apply_filters( 'woocommerce_add_to_cart_form_action', '' ) ); ?>"
	method="post"
	enctype="multipart/form-data"
	autocomplete="off"
>
	<div id="wc-appointments-appointment-form" class="wc-appointments-appointment-form" style="display:none">
		<?php $appointment_form->output(); ?>
	</div>
	<input type="hidden" name="appointment-id" value="<?php esc_attr_e( $appointment_id ); ?>" />
	<input type="hidden" name="appointable-product-id" value="<?php esc_attr_e( $product->get_id() ); ?>" />
	<input type="hidden" name="reschedule-appointment" value="1" />
	<?php wp_nonce_field( 'woocommerce-appointments-reschedule_appointment' ); ?>
	<button
		type="submit"
		name="reschedule_appointment_form"
		class="wc-appointments-appointment-form-button single_add_to_cart_button button alt disabled"
		style="display:none"
	>
		<?php esc_html_e( 'Reschedule', 'woocommerce-appointments' ); ?>
	</button>

</form>
