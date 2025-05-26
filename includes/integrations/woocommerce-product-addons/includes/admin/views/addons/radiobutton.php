<?php
/**
 * The Template for displaying radio button field.
 *
 * @version 7.3.0
 * @package woocommerce-product-addons
 *
 * phpcs:disable WordPress.Security.NonceVerification.Missing
 */

$loop             = 0;
$field_name       = ! empty( $addon['field_name'] ) ? $addon['field_name'] : '';
$addon_key        = 'addon-' . sanitize_title( $field_name );
$required         = ! empty( $addon['required'] ) ? $addon['required'] : '';
$restriction_data = WC_Product_Addons_Helper::get_restriction_data( $addon );
$current_value    = isset( $_POST[ $addon_key ], $_POST[ $addon_key ][0] ) ? wc_clean( wp_unslash( $_POST[ $addon_key ][0] ) ) : ( ! empty( $value ) ? $value : '' );
?>

<div class="form-row form-row-wide wc-pao-addon-wrap">

<?php if ( empty( $required ) ) { ?>
	<div class="wc-pao-addon-item wc-pao-addon-<?php echo esc_attr( sanitize_title( $field_name ) ); ?>">
		<input
			type="radio"
			class="wc-pao-addon-field wc-pao-addon-radio"
			value=""
			<?php checked( $current_value, '' ); ?>
			name="addon-<?php echo esc_attr( sanitize_title( $field_name ) ); ?>"
			id="addon-<?php echo esc_attr( sanitize_title( $field_name ) ); ?>-none"
		/>
		<label for="addon-<?php echo esc_attr( sanitize_title( $field_name ) ); ?>-none">
			<?php esc_html_e( 'None', 'woocommerce-appointments' ); ?>
		</label>
	</div>
<?php } ?>

<?php
foreach ( $addon['options'] as $i => $option ) {
	++$loop;

	$price        = ! empty( $option['price'] ) ? $option['price'] : '';
	$price_prefix = 0 < $price ? '+' : '';
	$price_type   = ! empty( $option['price_type'] ) ? $option['price_type'] : '';
	$price_raw    = apply_filters( 'woocommerce_product_addons_option_price_raw', $price, $option );
	$label        = ( '0' === $option['label'] ) || ! empty( $option['label'] ) ? $option['label'] : '';

	if ( 'percentage_based' === $price_type ) {
		$price_for_display = $price_raw ? '(' . $price_prefix . $price_raw . '%)' : '';
	} else {
		$price_for_display = $price_raw ? '(' . $price_prefix . wc_price( WC_Product_Addons_Helper::get_product_addon_price_for_display( $price_raw ) ) . ')' : '';
	}

	$price_display = WC_Product_Addons_Helper::get_product_addon_price_for_display( $price_raw );

	if ( 'percentage_based' === $price_type ) {
		$price_display = $price_raw;
	}

	$option_id = $addon_key . '-' . $i;
	?>
	<div class="wc-pao-addon-item wc-pao-addon-<?php echo esc_attr( sanitize_title( $field_name ) ); ?>">
		<input
			type="radio"
			id="<?php echo esc_attr( $option_id ); ?>"
			class="wc-pao-addon-field wc-pao-addon-radio"
			name="addon-<?php echo esc_attr( sanitize_title( $field_name ) ); ?>"
			data-raw-price="<?php echo esc_attr( $price_raw ); ?>"
			data-price="<?php echo esc_attr( $price_display ); ?>"
			data-price-type="<?php echo esc_attr( $price_type ); ?>"
			value="<?php echo esc_attr( sanitize_title( $label ) ); ?>"
			<?php checked( $current_value, $label ); ?>
			data-restrictions="<?php echo esc_attr( json_encode( $restriction_data ) ); ?>"
			data-label="<?php echo esc_attr( wptexturize( $label ) ); ?>"
		/>
		<label for="<?php echo esc_attr( $option_id ); ?>">
			<?php echo wp_kses_post( wptexturize( $label ) ); ?> <?php echo ! empty( $price_for_display ) ? '<span class="wc-pao-addon-price">' . wp_kses_post( $price_for_display ) . '</span>' : ''; ?>
		</label>
	</div>
<?php } ?>

</div>
