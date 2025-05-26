<?php
/**
 * The Template for displaying the Date Picker.
 *
 * @version 7.1.1
 * @package woocommerce-product-addons
 */
/**
 * Injections:
 *
 * @var array $addon
 * @var mixed $value
 */

$field_name       = ! empty( $addon['field_name'] ) ? $addon['field_name'] : '';
$addon_key        = 'addon-' . sanitize_title( $field_name );
$adjust_price     = ! empty( $addon['adjust_price'] ) ? $addon['adjust_price'] : '';
$price            = ! empty( $addon['price'] ) ? $addon['price'] : '';
$price_type       = ! empty( $addon['price_type'] ) ? $addon['price_type'] : '';
$restriction_data = WC_Product_Addons_Helper::get_restriction_data( $addon );
$price_raw        = apply_filters( 'woocommerce_product_addons_price_raw', $adjust_price && $price ? $price : '', $addon );
$adjust_duration  = ! empty( $addon['adjust_duration'] ) ? $addon['adjust_duration'] : '';
$duration         = ! empty( $addon['duration'] ) ? absint( $addon['duration'] ) : '';
$duration_type    = ! empty( $addon['duration_type'] ) ? $addon['duration_type'] : '';
$duration_raw     = apply_filters( 'woocommerce_product_addons_duration_raw', '1' == $adjust_duration && $duration ? $duration : '', $addon );
$value            = wp_parse_args(
	$value,
	array(
		'timestamp' => '',
		'offset'    => '',
		'display'   => '',
	)
);

$price_display = apply_filters(
	'woocommerce_product_addons_price',
	$adjust_price && $price_raw ? WC_Product_Addons_Helper::get_product_addon_price_for_display( $price_raw ) : '',
	$addon,
	0,
	$value,
	'datepicker'
);

$duration_display = apply_filters(
	'woocommerce_product_addons_duration',
	'1' == $adjust_duration && $duration_raw ? ' ' . wc_appointment_pretty_addon_duration( $duration_raw ) : '',
	$addon,
	0,
	$value,
	'custom_textarea'
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
		data-raw-duration="<?php esc_attr_e( $duration_raw ); ?>"
		data-duration="<?php esc_attr_e( $duration_display ); ?>"
		data-duration-type="<?php esc_attr_e( $duration_type ); ?>"
		data-restrictions="<?php echo esc_attr( json_encode( $restriction_data ) ); ?>"
		value="<?php echo esc_attr( $value['display'] ); ?>"
	/>
	<input autocomplete="off" type="hidden" name="addon-<?php echo esc_attr( sanitize_title( $field_name ) ); ?>-wc-pao-date" />
	<input autocomplete="off" type="hidden" name="addon-<?php echo esc_attr( sanitize_title( $field_name ) ); ?>-wc-pao-date-gmt-offset" />
	<?php echo wp_kses_post( apply_filters( 'woocommerce_pao_reset_date_link', '<a class="reset_date" href="#">' . esc_html__( 'Clear', 'woocommerce-appointments' ) . '</a>' ) ); ?>
</div>
