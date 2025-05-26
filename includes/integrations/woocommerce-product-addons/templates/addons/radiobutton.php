<?php
/**
 * The Template for displaying radio button field.
 *
 * @version 7.6.0
 * @package woocommerce-product-addons
 *
 */
/**
 * Injections:
 *
 * @var array $addon
 * @var mixed $value
 */

global $product;

$loop             = 0;
$field_name       = ! empty( $addon['field_name'] ) ? $addon['field_name'] : '';
$addon_key        = 'addon-' . sanitize_title( $field_name );
$required         = ! empty( $addon['required'] ) ? $addon['required'] : '';
$restriction_data = WC_Product_Addons_Helper::get_restriction_data( $addon );
$value            = ! empty( $value ) ? $value : '';
?>

<div class="form-row form-row-wide wc-pao-addon-wrap">

<!--
// Commented with 4.17.2.
<?php if ( empty( $required ) ) { ?>
<div class="wc-pao-addon-<?php echo esc_attr( sanitize_title( $field_name ) ); ?>">
	<input
		type="radio"
		class="wc-pao-addon-field wc-pao-addon-radio"
		value=""
		<?php checked( $value, '' ); ?>
		name="addon-<?php echo esc_attr( sanitize_title( $field_name ) ); ?>"
		id="addon-<?php echo esc_attr( sanitize_title( $field_name ) ); ?>-none"
	/>
	<label for="addon-<?php echo esc_attr( sanitize_title( $field_name ) ); ?>-none">
		<?php esc_html_e( 'None', 'woocommerce-appointments' ); ?>
	</label>
</div>
<?php } ?>
-->

<?php
foreach ( $addon['options'] as $i => $option ) {
	++$loop;

	if ( isset( $option['visibility'] ) && 0 === $option['visibility'] ) {
		continue;
	}

	$option_price         = ! empty( $option['price'] ) ? $option['price'] : '';
	$option_price_type    = ! empty( $option['price_type'] ) ? $option['price_type'] : '';
	$price_prefix         = 0 < $option_price ? '+' : '';
	$price_type           = $option_price_type;
	$price_raw            = apply_filters( 'woocommerce_product_addons_option_price_raw', $option_price, $option );
	$option_duration      = ! empty( $option['duration'] ) ? $option['duration'] : '';
	$option_duration_type = ! empty( $option['duration_type'] ) ? $option['duration_type'] : '';
	$duration_type        = $option_duration_type;
	$duration_raw         = apply_filters( 'woocommerce_product_addons_option_duration_raw', $option_duration, $option );
	$option_label         = ( '0' === $option['label'] ) || ! empty( $option['label'] ) ? $option['label'] : '';
	$price_display        = WC_Product_Addons_Helper::get_product_addon_price_for_display( $price_raw );

	if ( 'percentage_based' === $price_type ) {
		$price_display = $price_raw;

		$add_price_to_value = apply_filters( 'woocommerce_addons_add_product_price_to_value', true, $product );

		$price_for_display = $add_price_to_value ? apply_filters(
			'woocommerce_product_addons_option_price',
			$price_raw ? '(' . $price_prefix . $price_raw . '%)' : '',
			$option,
			$i,
			$addon,
			'radiobutton'
		) : '';
	} else {
		$add_price_to_value = apply_filters( 'woocommerce_addons_add_product_price_to_value', true, $product );

		$price_for_display = $add_price_to_value ? apply_filters(
			'woocommerce_product_addons_option_price',
			$price_raw ? '(' . $price_prefix . wc_price( WC_Product_Addons_Helper::get_product_addon_price_for_display( $price_raw ) ) . ')' : '',
			$option,
			$i,
			$addon,
			'radiobutton'
		) : '';
	}

	$duration_display     = $duration_raw;
	$duration_for_display = apply_filters(
		'woocommerce_product_addons_option_duration',
		$duration_raw ? ' ' . wc_appointment_pretty_addon_duration( $duration_raw ) : '',
		$option,
		$i,
		$addon,
		'radiobutton'
	);

	$option_id = $addon_key . '-' . $i;
	?>
	<div class="wc-pao-addon-<?php echo esc_attr( sanitize_title( $field_name ) ); ?>">
		<input
			type="radio"
			id="<?php echo esc_attr( $option_id ); ?>"
			class="wc-pao-addon-field wc-pao-addon-radio"
			name="addon-<?php echo esc_attr( sanitize_title( $field_name ) ); ?>"
			data-raw-price="<?php esc_attr_e( $price_raw ); ?>"
			data-price="<?php esc_attr_e( $price_display ); ?>"
			data-price-type="<?php esc_attr_e( $price_type ); ?>"
			data-raw-duration="<?php esc_attr_e( $duration_raw ); ?>"
			data-duration="<?php esc_attr_e( $duration_display ); ?>"
			data-duration-type="<?php esc_attr_e( $duration_type ); ?>"
			value="<?php echo esc_attr( sanitize_title( $option_label ) ); ?>"
			<?php checked( $value, sanitize_title( $option_label ) ); ?>
			data-restrictions="<?php echo esc_attr( json_encode( $restriction_data ) ); ?>"
			data-label="<?php esc_attr_e( wptexturize( $option_label ) ); ?>"
		/>
		<label for="<?php echo esc_attr( $option_id ); ?>">
			<?php echo wp_kses_post( wptexturize( $option_label ) ); ?> <?php echo ! empty( $price_for_display ) ? '<span class="wc-pao-addon-price">' . wp_kses_post( $price_for_display ) . '</span>' : ''; ?><?php echo ! empty( $duration_for_display ) ? '<span class="wc-pao-addon-duration">' . wp_kses_post( $duration_for_display ) . '</span>' : ''; ?>
		</label>
	</div>
<?php
}
?>

</div>
