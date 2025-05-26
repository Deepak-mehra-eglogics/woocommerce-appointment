<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Temporary fix.
if ( ! isset( $option ) && isset( $addon['options'][0] ) ) {
	$option = $addon['options'][0];
}

#error_log( var_export( $option, true ) );

$opt_price_type        = ! empty( $option['price_type'] ) ? $option['price_type'] : 'flat_fee';
$opt_display_image     = ( ! empty( $addon['display'] ) && 'images' === $addon['display'] ) ? 'show' : 'hide';
$opt_display_default   = ( ! empty( $addon['type'] ) && 'checkbox' === $addon['type'] ) ? 'show' : 'hide';
$opt_label_column      = ( ! empty( $addon['display'] ) && 'images' === $addon['display'] ) ? '' : 'full';
$opt_image             = ! empty( $option['image'] ) ? $option['image'] : '';
$opt_show_image_swatch = ! empty( $opt_image ) ? 'show' : 'hide';
$opt_show_add_image    = ! empty( $opt_image ) ? 'hide' : 'show';
$opt_label             = ( '0' === $option['label'] ) || ! empty( $option['label'] ) ? wp_kses_post( stripslashes( $option['label'] ) ) : '';
$opt_price             = ! empty( $option['price'] ) ? $option['price'] : '';
$opt_defaults          = ! empty( $addon['default'] ) ? array_map( 'intval', explode( ',', $addon['default'] ) ) : array();
$opt_visible           = isset( $option['visibility'] ) && 0 === $option['visibility'] ? 0 : 1;
$opt_image_thumb       = '<img />';
$opt_decimal_separator = wc_get_price_decimal_separator();

if ( 'show' === $opt_show_image_swatch ) {
	$opt_image_thumb = wp_get_attachment_image_src( $opt_image, 'thumbnail' );
	if ( $opt_image_thumb ) {
		$opt_image_thumb = '<img src="' . esc_url( $opt_image_thumb[0] ) . '" />';
	} else {
		$opt_image_thumb = wc_placeholder_img();
	}
}
?>
<div class="wc-pao-addon-option-row">
	<div class="wc-pao-addon-content-image <?php esc_attr_e( $opt_display_image ); ?>">
		<span class="dashicons dashicons-format-image wc-pao-addon-add-image <?php esc_attr_e( $opt_show_add_image ); ?>">
			<input type="hidden" name="product_addon_option_image[<?php echo esc_attr( $loop ); ?>][]" value="<?php esc_attr_e( $opt_image ); ?>" class="wc-pao-addon-option-image-id" />
		</span>
		<span class="dashicons dashicons-plus <?php esc_attr_e( $opt_show_add_image ); ?>"></span>
		<a href="#" class="wc-pao-addon-image-swatch <?php esc_attr_e( $opt_show_image_swatch ); ?>"><?php echo wp_kses_post( $opt_image_thumb ); ?><span class="dashicons dashicons-dismiss"></span></a>
	</div>

	<div class="wc-pao-addon-content-label <?php esc_attr_e( $opt_label_column ); ?>">
		<input type="text" name="product_addon_option_label[<?php echo esc_attr( $loop ); ?>][]" value="<?php echo esc_attr( $opt_label ); ?>" />
	</div>

	<div class="wc-pao-addon-content-price-type">
		<select name="product_addon_option_price_type[<?php echo esc_attr( $loop ); ?>][]" class="wc-pao-addon-option-price-type">
			<option <?php selected( 'flat_fee', $opt_price_type ); ?> value="flat_fee"><?php esc_html_e( 'Flat Fee', 'woocommerce-appointments' ); ?></option>
			<option <?php selected( 'quantity_based', $opt_price_type ); ?> value="quantity_based"><?php esc_html_e( 'Quantity Based', 'woocommerce-appointments' ); ?></option>
			<option <?php selected( 'percentage_based', $opt_price_type ); ?> value="percentage_based" class="disabled_if_appointment"><?php esc_html_e( 'Percentage Based', 'woocommerce-appointments' ); ?></option>
		</select>
	</div>

	<div class="wc-pao-addon-content-price">
		<input type="text" name="product_addon_option_price[<?php echo esc_attr( $loop ); ?>][]" value="<?php esc_attr_e( wc_format_localized_price( $opt_price ) ); ?>" placeholder="0<?php esc_attr_e( $opt_decimal_separator ); ?>00" class="wc_input_price" />
	</div>

	<div class="wc-pao-addon-content-default <?php echo esc_attr( $opt_display_default ); ?>">
		<input type="checkbox" name="product_addon_option_default[<?php echo esc_attr( $loop ); ?>][<?php echo esc_attr( $index ); ?>]" id="product_addon_option_default_<?php echo esc_attr( $loop . '_' . $index ); ?>" <?php echo in_array( $index, $opt_defaults, true ) ? 'checked' : ''; ?> class="wc-pao-addon-option-default" />
		<label for="product_addon_option_default_<?php echo esc_attr( $loop . '_' . $index ); ?>"><?php esc_html_e( 'Pre-selected by default', 'woocommerce-appointments' ); ?></label>
	</div>

	<?php do_action( 'woocommerce_product_addons_panel_option_row', isset( $post ) ? $post : null, $product_addons, $loop, $option ); ?>

	<span class="wc-pao-addon-sort-handle"></span>
	<div class="wc-pao-addon-content-visibility">
		<button type="button"
				class="wc-pao-addon-option-visibility-toggle<?php echo 0 === $opt_visible ? ' is-hidden' : ''; ?>"
				aria-pressed="<?php echo esc_attr( $opt_visible ? 'false' : 'true' ); ?>"
				aria-label="<?php echo esc_attr( $opt_visible ? __( 'Click to hide this option', 'woocommerce-appointments' ) : __( 'Click to make this option visible', 'woocommerce-appointments' ) ); ?>"
				data-loop="<?php echo esc_attr( $loop ); ?>"
				data-index="<?php echo esc_attr( $index ); ?>">
		</button>
		<input type="hidden" name="product_addon_option_visibility[<?php echo esc_attr( $loop ); ?>][<?php echo esc_attr( $index ); ?>]"
				id="product_addon_option_visibility_<?php echo esc_attr( $loop . '_' . $index ); ?>"
				value="<?php echo esc_attr( $opt_visible ); ?>"
				class="wc-pao-addon-option-visibility" />
	</div>
	<div class="wc-pao-addon-content-remove">
		<a href="#" class="wc-pao-remove-option button wc-action-button delete" aria-label="<?php esc_attr_e( 'Delete option', 'woocommerce-appointments' ); ?>"></a>
	</div>
</div>
