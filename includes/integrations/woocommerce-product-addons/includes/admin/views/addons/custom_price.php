<?php
/**
 * The Template for displaying custom price field.
 *
 * @version 7.3.0
 * @package woocommerce-product-addons
 */

$field_name       = ! empty( $addon['field_name'] ) ? $addon['field_name'] : '';
$addon_key        = 'addon-' . sanitize_title( $field_name );
$has_restrictions = ! empty( $addon['restrictions'] );
$min              = $addon['min'] > 0 ? $addon['min'] : 0;
$restriction_data = WC_Product_Addons_Helper::get_restriction_data( $addon );
$value            = ! empty( $value ) ? $value : '';
$min              = ! empty( $addon['min'] ) ? $addon['min'] : '';
$max              = ! empty( $addon['max'] ) ? $addon['max'] : '';
$required         = ! empty( $addon['required'] ) ? 'required' : '';
?>

<div class="form-row form-row-wide wc-pao-addon-wrap wc-pao-addon-<?php echo esc_attr( sanitize_title( $field_name ) ); ?>">
	<input
		type="text"
		class="input-text wc-pao-addon-field wc-pao-addon-custom-price"
		name="<?php echo esc_attr( $addon_key ); ?>"
		id="<?php echo esc_attr( $addon_key ); ?>"
		data-price-type="flat_fee"
		data-restrictions="<?php echo esc_attr( json_encode( $restriction_data ) ); ?>"
		value="<?php echo esc_attr( wc_format_localized_price( $value ) ); ?>"
		min="<?php echo esc_attr( $min ); ?>"
		max="<?php echo esc_attr( $max ); ?>"
		<?php echo esc_attr( $required ); ?>
	/>
</div>
