<?php
/**
 * WC_Product_Addons_Html class
 *
 * @package  WooCommerce Product Add-Ons
 * @since    7.4.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Product AddOns HTML generator
 *
 * @class    WC_Product_Addons_Html
 * @version  6.9.0
 */
class WC_Product_Addons_Html_Generator {
	/**
	 * Get add-on field HTML.
	 *
	 * @param  array      $addon  Add-on field data.
	 * @param  array|null $value  Add-on field value.
	 * @return string
	 */
	public static function get_addon_html( $addon, $value = null ) {
		ob_start();

		$method_name = 'get_' . $addon['type'] . '_html';

		if ( method_exists( __CLASS__, $method_name ) ) {
			static::$method_name( $addon, $value );
		}

		do_action( 'woocommerce_product_addons_get_' . $addon['type'] . '_html', $addon, $value );

		return ob_get_clean();
	}

	/**
	 * Get multiple choice HTML.
	 *
	 * @since 3.0.0
	 * @param  array $addon  Add-on field data.
	 * @param  mixed $value  Add-on field value.
	 */
	public static function get_multiple_choice_html( $addon, $value = '' ) {
		switch ( $addon['display'] ) {
			case 'images':
				static::get_image_html( $addon, $value );
				break;
			case 'radiobutton':
				static::get_radiobutton_html( $addon, $value );
				break;
			case 'select':
				static::get_select_html( $addon, $value );
				break;
		}
	}

	/**
	 * Get image swatches field HTML.
	 *
	 * @param  array $addon  Add-on field data.
	 * @param  mixed $value  Add-on field value.
	 */
	public static function get_image_html( $addon, $value = '' ) {

		$value = ! empty( $value ) ? $value : WC_Product_Addons_Helper::wc_pao_get_default_addon_value( $addon );

		wc_get_template(
			'addons/image.php',
			array(
				'addon' => $addon,
				'value' => $value,
			),
			'woocommerce-appointments',
			WC_PRODUCT_ADDONS_PLUGIN_PATH . '/templates/'
		);
	}

	/**
	 * Get checkbox field HTML.
	 *
	 * @param  array $addon  Add-on field data.
	 * @param  mixed $value  Add-on field value.
	 */
	public static function get_checkbox_html( $addon, $value = '' ) {

		$value = ! empty( $value ) ? $value : WC_Product_Addons_Helper::wc_pao_get_default_addon_value( $addon );

		wc_get_template(
			'addons/checkbox.php',
			array(
				'addon' => $addon,
				'value' => $value,
			),
			'woocommerce-appointments',
			WC_PRODUCT_ADDONS_PLUGIN_PATH . '/templates/'
		);
	}

	/**
	 * Get radio button field HTML.
	 *
	 * @param  array $addon  Add-on field data.
	 * @param  mixed $value  Add-on field value.
	 */
	public static function get_radiobutton_html( $addon, $value = '' ) {

		$value = ! empty( $value ) ? $value : WC_Product_Addons_Helper::wc_pao_get_default_addon_value( $addon );

		wc_get_template(
			'addons/radiobutton.php',
			array(
				'addon' => $addon,
				'value' => $value,
			),
			'woocommerce-appointments',
			WC_PRODUCT_ADDONS_PLUGIN_PATH . '/templates/'
		);
	}

	/**
	 * Get select field HTML.
	 *
	 * @param  array $addon  Add-on field data.
	 * @param  mixed $value  Add-on field value.
	 */
	public static function get_select_html( $addon, $value = '' ) {

		$value = ! empty( $value ) ? $value : WC_Product_Addons_Helper::wc_pao_get_default_addon_value( $addon );

		wc_get_template(
			'addons/select.php',
			array(
				'addon' => $addon,
				'value' => $value,
			),
			'woocommerce-appointments',
			WC_PRODUCT_ADDONS_PLUGIN_PATH . '/templates/'
		);
	}

	/**
	 * Get custom field HTML.
	 *
	 * @param  array $addon  Add-on field data.
	 * @param  mixed $value  Add-on field value.
	 */
	public static function get_custom_text_html( $addon, $value = '' ) {
		wc_get_template(
			'addons/custom-text.php',
			array(
				'addon' => $addon,
				'value' => $value,
			),
			'woocommerce-appointments',
			WC_PRODUCT_ADDONS_PLUGIN_PATH . '/templates/'
		);
	}

	/**
	 * Get custom textarea field HTML.
	 *
	 * @param  array $addon  Add-on field data.
	 * @param  mixed $value  Add-on field value.
	 */
	public static function get_custom_textarea_html( $addon, $value = '' ) {
		wc_get_template(
			'addons/custom-textarea.php',
			array(
				'addon' => $addon,
				'value' => $value,
			),
			'woocommerce-appointments',
			WC_PRODUCT_ADDONS_PLUGIN_PATH . '/templates/'
		);
	}

	/**
	 * Get file upload field HTML.
	 *
	 * @param  array $addon  Add-on field data.
	 * @param  mixed $value  Add-on field value.
	 */
	public static function get_file_upload_html( $addon, $value = '' ) {
		wc_get_template(
			'addons/file-upload.php',
			array(
				'addon'    => $addon,
				'max_size' => size_format( wp_max_upload_size() ),
				'value'    => $value,
			),
			'woocommerce-appointments',
			WC_PRODUCT_ADDONS_PLUGIN_PATH . '/templates/'
		);
	}

	/**
	 * Get custom price field HTML.
	 *
	 * @param  array $addon  Add-on field data.
	 * @param  mixed $value  Add-on field value.
	 */
	public static function get_custom_price_html( $addon, $value = '' ) {

		$value = ! empty( $value ) ? $value : WC_Product_Addons_Helper::wc_pao_get_default_addon_value( $addon );

		wc_get_template(
			'addons/custom-price.php',
			array(
				'addon' => $addon,
				'value' => wc_format_localized_price( $value ),
			),
			'woocommerce-appointments',
			WC_PRODUCT_ADDONS_PLUGIN_PATH . '/templates/'
		);
	}

	/**
	 * Get input multiplier field HTML.
	 *
	 * @param  array $addon  Add-on field data.
	 * @param  mixed $value  Add-on field value.
	 */
	public static function get_input_multiplier_html( $addon, $value = '' ) {

		$value = ! empty( $value ) ? $value : WC_Product_Addons_Helper::wc_pao_get_default_addon_value( $addon );

		wc_get_template(
			'addons/input-multiplier.php',
			array(
				'addon' => $addon,
				'value' => $value,
			),
			'woocommerce-appointments',
			WC_PRODUCT_ADDONS_PLUGIN_PATH . '/templates/'
		);
	}

	/**
	 * Get datepicker field HTML.
	 *
	 * @param  array $addon  Add-on field data.
	 * @param  mixed $value  Add-on field value.
	 */
	public static function get_datepicker_html( $addon, $value = '' ) {
		wc_get_template(
			'addons/datepicker.php',
			array(
				'addon' => $addon,
				'value' => $value,
			),
			'woocommerce-appointments',
			WC_PRODUCT_ADDONS_PLUGIN_PATH . '/templates/'
		);
	}
}
