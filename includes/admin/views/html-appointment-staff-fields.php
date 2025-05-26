<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

$staff_product = $user_product_id ?? 0;
$user_product  = wc_get_product( $staff_product );

if ( ! $user_product || ! $user_product->is_type( 'appointment' ) ) {
	return;
}

$staff_base_costs = $user_product->get_staff_base_costs( 'edit' );
$staff_base_cost  = $staff_base_costs[ $user_id ] ?? '';
$staff_qtys       = $user_product->get_staff_qtys( 'edit' );
$staff_qty        = $staff_qtys[ $user_id ] ?? '';
?>
<tr>
	<td class="sort">&nbsp;</td>
	<td class="staff_product">
		<div class="wc_appointment_product_name">
			<a href="<?php echo esc_url( get_edit_post_link( $user_product->get_id() ) ); ?>" title="<?php esc_attr_e( 'Edit Product', 'woocommerce-appointments' ); ?>"><?php esc_html_e( $user_product->get_title() ); ?></a>
		</div>
	</td>
	<td class="product_cost">
		<div class="wc_appointment_product_cost">
			<?php echo $user_product->get_price_html(); // WPCS: XSS ok. ?>
		</div>
	</td>
	<td class="product_qty">
		<div class="wc_appointment_qty">
			<?php esc_html_e( $user_product->get_qty() ); ?>
		</div>
	</td>
	<td class="staff_cost">
		<div class="wc_appointment_staff_cost">
			<input
				type="number"
				class=""
				name="staff_base_costs[<?php esc_attr_e( $user_product_id ); ?>]"
				<?php
				if ( ! empty( $staff_base_cost ) ) {
					echo 'value="' . esc_attr( $staff_base_cost ) . '"';
				}
				?>
				placeholder="0.00"
				step="0.01"
			/>
		</div>
	</td>
	<td class="staff_qty">
		<div class="wc_appointment_staff_qty">
			<input
				type="number"
				class=""
				name="staff_qtys[<?php esc_attr_e( $user_product_id ); ?>]"
				<?php
				if ( ! empty( $staff_qty ) ) {
					echo 'value="' . esc_attr( $staff_qty ) . '"';
				}
				?>
				placeholder="<?php esc_html_e( 'N/A', 'woocommerce-appointments' ); ?>"
				step="1"
			/>
		</div>
	</td>
	<input type="hidden" class="staff_product_id" name="staff_product_id[]" value="<?php esc_attr_e( $user_product_id ); ?>" />
	<td class="remove remove_grid_row remove_product" data-product="<?php esc_attr_e( absint( $user_product_id ) ); ?>" data-staff="<?php esc_attr_e( absint( $user_id ) ); ?>">&nbsp;</td>
</tr>
