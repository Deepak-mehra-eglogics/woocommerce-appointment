<?php
/**
 * The Template for displaying upload field.
 *
 * @version 7.1.0
 * @package woocommerce-product-addons
 */

$field_name       = ! empty( $addon['field_name'] ) ? $addon['field_name'] : '';
$addon_key        = 'addon-' . sanitize_title( $field_name );
$adjust_price     = ! empty( $addon['adjust_price'] ) ? $addon['adjust_price'] : '';
$price            = ! empty( $addon['price'] ) ? $addon['price'] : '';
$price_type       = ! empty( $addon['price_type'] ) ? $addon['price_type'] : '';
$restriction_data = WC_Product_Addons_Helper::get_restriction_data( $addon );
$max_size         = size_format( wp_max_upload_size() );
$price_raw        = apply_filters( 'woocommerce_product_addons_price_raw', $adjust_price && $price ? $price : '', $addon );
$price_display    = $adjust_price && $price_raw ? WC_Product_Addons_Helper::get_product_addon_price_for_display( $price_raw ) : '';
$value            = ! empty( $value ) ? $value : '';

if ( 'percentage_based' === $price_type ) {
	$price_display = $price_raw;
}
?>

<div class="form-row form-row-wide wc-pao-addon-wrap wc-pao-addon-<?php echo esc_attr( sanitize_title( $field_name ) ); ?>">
	<?php
	if ( ! empty( $value ) ) {
		$filename = basename( $value );
		?>
		<div class="wc-pao-addon-file-name">
			<?php
			$filelink = '<a href="' . esc_url( $value ) . '" target="_blank">' . esc_html( $filename ) . '</a>';
			// translators: %s existing filename.
			echo wp_kses_post( sprintf( __( 'Existing file: %s', 'woocommerce-appointments' ), $filelink ) );
			?>
			<input
				type="hidden"
				name="addon-<?php echo esc_attr( sanitize_title( $field_name ) ); ?>"
				id="addon-<?php echo esc_attr( sanitize_title( $field_name ) ); ?>-value"
				value="<?php echo esc_attr( $value ); ?>" />
		</div>
		<?php
	}
	?>
	<input
		type="file"
		class="wc-pao-addon-file-upload input-text wc-pao-addon-field"
		data-raw-price="<?php echo esc_attr( $price_raw ); ?>"
		data-price="<?php echo esc_attr( $price_display ); ?>"
		data-price-type="<?php echo esc_attr( $price_type ); ?>"
		name="addon-<?php echo esc_attr( sanitize_title( $field_name ) ); ?>"
		id="addon-<?php echo esc_attr( sanitize_title( $field_name ) ); ?>"
		data-restrictions="<?php echo esc_attr( json_encode( $restriction_data ) ); ?>"
		data-value="<?php echo esc_attr( $value ); ?>"
		/> <small>
		<?php
			// translators: %s file size.
			echo wp_kses_post( sprintf( __( '(max file size %s)', 'woocommerce-appointments' ), $max_size ) );
		?>
		</small>
</div>
