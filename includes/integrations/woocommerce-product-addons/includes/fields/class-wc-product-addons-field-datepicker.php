<?php
/**
 * Date picker field
 *
 * @since   6.8.0
 * @version 7.7.0
 */
class WC_Product_Addons_Field_Datepicker extends WC_Product_Addons_Field {

	/**
	 * The addon field
	 *
	 * @var array
	 */
	public $addon;

	/**
	 * The selected value/date.
	 *
	 * @var string
	 */
	public $value;

	/**
	 * The timestamp of the selected value/date.
	 *
	 * @var int
	 */
	public $timestamp;

	/**
	 * The offset of the selected value/date.
	 *
	 * @var float
	 */
	public $offset;

	/**
	 * Constructor
	 */
	public function __construct( $addon, $value = '', $timestamp = '', $offset = '' ) {
		$this->addon     = $addon;
		$this->value     = $value;
		$this->timestamp = $timestamp;
		$this->offset    = $offset;
	}

	/**
	 * Validate an addon
	 *
	 * @return bool pass, or WP_Error
	 */
	public function validate() {
		$posted = isset( $this->value ) ? $this->value : '';

		if ( ! empty( $this->addon['required'] ) && '' === $posted ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is escaped later.
			/* translators: %s Addon name */
			return new WP_Error( 'error', sprintf( __( '"%s" is a required field.', 'woocommerce-appointments' ), $this->addon['name'] ) );
		}

		return true;
	}

	/**
	 * Process this field after being posted
	 *
	 * @return array on success, WP_ERROR on failure
	 */
	public function get_cart_item_data() {
		$cart_item_data = array();
		$adjust_price   = $this->addon['adjust_price'];
		$this_data      = array(
			'name'          => sanitize_text_field( $this->addon['name'] ),
			'price'         => '1' != $adjust_price ? 0 : floatval( sanitize_text_field( $this->addon['price'] ) ),
			'value'         => '',
			'display'       => '',
			'field_name'    => $this->addon['field_name'],
			'field_type'    => $this->addon['type'],
			'id'            => isset( $this->addon['id'] ) ? $this->addon['id'] : 0,
			'duration'      => isset( $this->addon['duration'] ) ? $this->addon['duration'] : 0,
			'duration_type' => isset( $this->addon['duration_type'] ) ? $this->addon['duration_type'] : '',
			'hide_duration' => isset( $this->addon['wc_appointment_hide_duration_label'] ) && $this->addon['wc_appointment_hide_duration_label'] ? 1 : 0,
			'hide_price'    => isset( $this->addon['wc_appointment_hide_price_label'] ) && $this->addon['wc_appointment_hide_price_label'] ? 1 : 0,
			'price_type'    => $this->addon['price_type'],
			'timestamp'     => $this->timestamp,
			'offset'        => $this->offset,
		);

		if ( ! empty( $this->value ) ) {
			$this_data['value'] = wc_clean( $this->value );

			if ( ! empty( $this->timestamp ) ) {
				$this_data['value'] = date_i18n( get_option( 'date_format' ), WC_Product_Addons_Helper::wc_pao_convert_timestamp_to_gmt_offset( $this->timestamp, -1 * (float) $this->offset ) );
			}

			$cart_item_data[] = $this_data;
		}

		return $cart_item_data;
	}
}
