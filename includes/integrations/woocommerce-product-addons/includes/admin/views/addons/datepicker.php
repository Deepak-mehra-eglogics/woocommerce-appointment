<?php
/**
 * The Template for displaying the Date Picker.
 *
 * @version 7.0.0
 * @package woocommerce-product-addons
 */

$field_name       = ! empty( $addon['field_name'] ) ? $addon['field_name'] : '';
$addon_key        = 'addon-' . sanitize_title( $field_name );
$adjust_price     = ! empty( $addon['adjust_price'] ) ? $addon['adjust_price'] : '';
$price            = ! empty( $addon['price'] ) ? $addon['price'] : '';
$price_type       = ! empty( $addon['price_type'] ) ? $addon['price_type'] : '';
$restriction_data = WC_Product_Addons_Helper::get_restriction_data( $addon );
$price_raw        = apply_filters( 'woocommerce_product_addons_price_raw', $adjust_price && $price ? $price : '', $addon );
$price_display    = $adjust_price && $price_raw ? WC_Product_Addons_Helper::get_product_addon_price_for_display( $price_raw ) : '';
$value            = wp_parse_args(
	$value,
	array(
		'timestamp' => '',
		'offset'    => '',
		'display'   => '',
	)
);

if ( 'percentage_based' === $price_type ) {
	$price_display = $price_raw;
}
?>
<div class="form-row form-row-wide wc-pao-addon-wrap wc-pao-addon-<?php echo esc_attr( sanitize_title( $field_name ) ); ?>">
	<input
			autocomplete="off"
			readonly
			type="text"
			class="datepicker input-text wc-pao-addon-field"
			name="addon-<?php echo esc_attr( sanitize_title( $field_name ) ); ?>"
			id="addon-<?php echo esc_attr( sanitize_title( $field_name ) ); ?>"
			data-raw-price="<?php echo esc_attr( $price_raw ); ?>"
			data-price="<?php echo esc_attr( $price_display ); ?>"
			data-price-type="<?php echo esc_attr( $price_type ); ?>"
			data-restrictions="<?php echo esc_attr( json_encode( $restriction_data ) ); ?>"
			value="<?php echo esc_attr( $value['display'] ); ?>"
	/>
	<input autocomplete="off" type="hidden" name="addon-<?php echo esc_attr( sanitize_title( $field_name ) ); ?>-wc-pao-date" value="<?php echo esc_attr( $value['timestamp'] ); ?>" />
	<input autocomplete="off" type="hidden" name="addon-<?php echo esc_attr( sanitize_title( $field_name ) ); ?>-wc-pao-date-gmt-offset" value="<?php echo esc_attr( $value['offset'] ); ?>" />
	<?php echo wp_kses_post( '<a class="reset_date" href="#">' . esc_html__( 'Clear', 'woocommerce-appointments' ) . '</a>' ); ?>
</div>
