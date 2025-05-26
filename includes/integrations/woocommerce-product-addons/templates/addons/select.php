<?php
/**
 * The Template for displaying select field.
 *
 * @version 7.6.0
 * @package woocommerce-product-addons
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
$required         = ! empty( $addon['required'] ) ? $addon['required'] : '';
$restriction_data = WC_Product_Addons_Helper::get_restriction_data( $addon );
$value            = ! empty( $value ) ? $value : '';
?>
<div class="form-row form-row-wide wc-pao-addon-wrap wc-pao-addon-<?php echo esc_attr( sanitize_title( $field_name ) ); ?>">
	<select
		class="wc-pao-addon-field wc-pao-addon-select"
		name="addon-<?php echo esc_attr( sanitize_title( $field_name ) ); ?>"
		id="addon-<?php echo esc_attr( sanitize_title( $field_name ) ); ?>"
		data-restrictions="<?php echo esc_attr( json_encode( $restriction_data ) ); ?>"
		>
		<?php if ( empty( $required ) ) { ?>
			<option value=""><?php esc_html_e( 'None', 'woocommerce-appointments' ); ?></option>
		<?php } else { ?>
			<option value=""><?php esc_html_e( 'Select an option...', 'woocommerce-appointments' ); ?></option>
		<?php } ?>

		<?php
		foreach ( $addon['options'] as $i => $option ) {
			++$loop;

			if ( isset( $option['visibility'] ) && 0 === $option['visibility'] ) {
				continue;
			}

			$price         = ! empty( $option['price'] ) ? $option['price'] : '';
			$price_prefix  = 0 < $price ? '+' : '';
			$price_type    = ! empty( $option['price_type'] ) ? $option['price_type'] : '';
			$price_raw     = apply_filters( 'woocommerce_product_addons_option_price_raw', $price, $option );
			$duration      = ! empty( $option['duration'] ) ? absint( $option['duration'] ) : '';
			$duration_type = ! empty( $option['duration_type'] ) ? $option['duration_type'] : '';
			$duration_raw  = apply_filters( 'woocommerce_product_addons_option_duration_raw', $duration, $option );
			$label         = ( '0' === $option['label'] ) || ! empty( $option['label'] ) ? $option['label'] : '';

			if ( 'percentage_based' === $price_type ) {
				$add_price_to_value = apply_filters( 'woocommerce_addons_add_product_price_to_value', true, $product );

				$price_for_display = $add_price_to_value ? apply_filters(
					'woocommerce_product_addons_option_price',
					$price_raw ? '(' . $price_prefix . $price_raw . '%)' : '',
					$option,
					$i,
					$addon,
					'select'
				) : '';
			} else {
				$add_price_to_value = apply_filters( 'woocommerce_addons_add_product_price_to_value', true, $product );

				$price_for_display = $add_price_to_value ? apply_filters(
					'woocommerce_product_addons_option_price',
					$price_raw ? '(' . $price_prefix . wc_price( WC_Product_Addons_Helper::get_product_addon_price_for_display( $price_raw ) ) . ')' : '',
					$option,
					$i,
					$addon,
					'select'
				) : '';
			}

			$price_display = WC_Product_Addons_Helper::get_product_addon_price_for_display( $price_raw );

			if ( 'percentage_based' === $price_type ) {
				$price_display = $price_raw;
			}

			$duration_display = apply_filters(
				'woocommerce_product_addons_option_duration',
				$duration_raw ? ' ' . wc_appointment_pretty_addon_duration( $duration_raw ) : '',
				$option,
				$i,
				$addon,
				'select'
			);

			$option_value = sanitize_title( $label ) . '-' . $loop;
			?>
			<option
				data-raw-price="<?php echo esc_attr( $price_raw ); ?>"
				data-price="<?php echo esc_attr( $price_display ); ?>"
				data-price-type="<?php echo esc_attr( $price_type ); ?>"
				data-raw-duration="<?php esc_attr_e( $duration_raw ); ?>"
				data-duration="<?php esc_attr_e( $duration_display ); ?>"
				data-duration-type="<?php esc_attr_e( $duration_type ); ?>"
				value="<?php echo esc_attr( $option_value ); ?>"
				data-label="<?php echo esc_attr( wptexturize( $label ) ); ?>"
				<?php selected( $value, $option_value ); ?>
			><?php echo wp_kses_post( wptexturize( $label ) . ' ' . $price_for_display . $duration_display ); ?></option>
		<?php } ?>
	</select>
</div>
