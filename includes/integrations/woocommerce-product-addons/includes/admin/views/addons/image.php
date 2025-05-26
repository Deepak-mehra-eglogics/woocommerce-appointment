<?php
/**
 * The Template for displaying image swatches field.
 *
 * @version 7.3.0
 * @package woocommerce-product-addons
 *
 * phpcs:disable WordPress.Security.NonceVerification.Missing
 */

$loop             = 0;
$field_name       = ! empty( $addon['field_name'] ) ? $addon['field_name'] : '';
$required         = ! empty( $addon['required'] ) ? $addon['required'] : '';
$restriction_data = WC_Product_Addons_Helper::get_restriction_data( $addon );
$value            = ! empty( $value ) ? $value : '';

?>

<div class="form-row form-row-wide wc-pao-addon-wrap wc-pao-addon-<?php echo esc_attr( sanitize_title( $field_name ) ); ?>">
<?php if ( empty( $required ) ) { ?>
	<a href="#" title="<?php echo esc_attr__( 'None', 'woocommerce-appointments' ); ?>" class="wc-pao-addon-image-swatch<?php echo '' === $value ? ' selected' : ''; ?>" data-value="" data-price="">
		<img src="<?php echo esc_url( WC_Product_Addons_Helper::no_image_select_placeholder_src() ); ?>" alt="<?php echo esc_attr__( 'None', 'woocommerce-appointments' ); ?>" />
	</a>
<?php } ?>

<?php
$selected_label = '';
foreach ( $addon['options'] as $i => $option ) {
	++$loop;
	$price        = ! empty( $option['price'] ) ? $option['price'] : '';
	$price_prefix = 0 < $price ? '+' : '';
	$price_type   = $option['price_type'];
	$price_raw    = apply_filters( 'woocommerce_product_addons_option_price_raw', $price, $option );
	$label        = ! empty( $option['label'] ) ? $option['label'] : '';
	$selected     = $value === $label;

	if ( 'percentage_based' === $price_type ) {
		$price_tip     = $price_raw ? $price_prefix . $price_raw . '%' : '';
		$price_display = $price_raw ? '(' . $price_prefix . $price_raw . '%)' : '';
	} else {
		$price_tip     = $price_raw ? $price_prefix . wc_price( WC_Product_Addons_Helper::get_product_addon_price_for_display( $price_raw ) ) : '';
		$price_display = $price_raw ? '(' . $price_prefix . wc_price( WC_Product_Addons_Helper::get_product_addon_price_for_display( $price_raw ) ) . ')' : '';
	}

	if ( $selected ) {
		$selected_label = $data_price;
	}

	$data_price = '<span class="wc-pao-addon-image-swatch-price">' . wptexturize( $option['label'] ) . ( ! empty( $price_display ) ? ' <span class="wc-pao-addon-price">' . wp_kses_post( $price_display ) . '</span>' : '' ) . '</span>';

	$image_src   = wp_get_attachment_image_src( $option['image'], 'thumbnail' );
	$image_title = $option['label'] . ' ' . $price_tip;
	?>
		<a
			href="#"
			title="<?php echo esc_attr( $image_title ); ?>"
			class="wc-pao-addon-image-swatch<?php echo $selected ? ' selected' : ''; ?>"
			data-value="<?php echo esc_attr( sanitize_title( $option['label'] ) . '-' . $loop ); ?>"
			data-price="<?php echo esc_attr( $data_price ); ?>"
		>
			<img src="<?php echo esc_url( is_array( $image_src ) && $image_src[0] ? $image_src[0] : wc_placeholder_img_src() ); ?>" alt="<?php echo esc_attr( wp_strip_all_tags( $image_title ) ); ?>" />
		</a>

<?php } ?>

<span class="wc-pao-addon-image-swatch-selected-swatch"><?php echo wp_kses_post( wptexturize( $selected_label ) ); ?></span>

<select class="wc-pao-addon-image-swatch-select wc-pao-addon-field" name="addon-<?php echo esc_attr( sanitize_title( $field_name ) ); ?>" id="addon-<?php echo esc_attr( sanitize_title( $field_name ) ); ?>" data-restrictions="<?php echo esc_attr( json_encode( $restriction_data ) ); ?>"
>
	<?php if ( empty( $required ) ) { ?>
		<option value=""><?php esc_html_e( 'None', 'woocommerce-appointments' ); ?></option>
	<?php } else { ?>
		<option value=""><?php esc_html_e( 'Select an option...', 'woocommerce-appointments' ); ?></option>
		<?php
	}

	$loop = 0;

	foreach ( $addon['options'] as $i => $option ) {
		++$loop;

		$price      = ! empty( $option['price'] ) ? $option['price'] : '';
		$price_raw  = apply_filters( 'woocommerce_product_addons_option_price_raw', $price, $option );
		$price_type = ! empty( $option['price_type'] ) ? $option['price_type'] : '';
		$label      = ! empty( $option['label'] ) ? $option['label'] : '';

		$price_for_display = $price_raw ? '(' . wc_price( WC_Product_Addons_Helper::get_product_addon_price_for_display( $price_raw ) ) . ')' : '';

		$price_display = WC_Product_Addons_Helper::get_product_addon_price_for_display( $price_raw );

		if ( 'percentage_based' === $price_type ) {
			$price_display = $price_raw;
		}
		?>
		<option data-raw-price="<?php echo esc_attr( $price_raw ); ?>"
				data-price="<?php echo esc_attr( $price_display ); ?>"
				data-price-type="<?php echo esc_attr( $price_type ); ?>"
				value="<?php echo esc_attr( sanitize_title( $option['label'] ) . '-' . $loop ); ?>"
				<?php selected( $value, $label ); ?>
				data-label="<?php echo esc_attr( wptexturize( $label ) ); ?>">
			<?php echo wp_kses_post( wptexturize( $label ) . ' ' . $price_for_display ); ?>
		</option>
	<?php } ?>

</select>
</div>
