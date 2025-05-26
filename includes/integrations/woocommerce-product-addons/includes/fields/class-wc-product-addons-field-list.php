<?php
/**
 * Checkbox/radios field
 *
 * @version 7.7.0
 */
class WC_Product_Addons_Field_List extends WC_Product_Addons_Field {

	/**
	 * Validate an addon
	 *
	 * @return bool|WP_Error
	 */
	 public function validate() {
 		$posted = isset( $this->value ) ? $this->value : '';

		if ( ! empty( $this->addon['required'] ) && '' === $posted ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is escaped later.
			/* translators: Add-on name */
			return new WP_Error( 'error', sprintf( __( '"%s" is a required field.', 'woocommerce-appointments' ), $this->addon['name'] ) );
		}

 		if ( '' !== $posted && isset( $this->addon['options'] ) ) {
 			if ( 'checkbox' === $this->addon['type'] ) {
 				foreach ( $this->addon['options'] as $option ) {
 					foreach ( $posted as $posted_option ) {
						if ( sanitize_title( $option['label'] ) === $posted_option && isset( $option['visibility'] ) && 0 === $option['visibility'] ) {
							// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is escaped later.
							/* translators: %s Selected add-on option label */
							return new WP_Error( 'error', sprintf( __( '"%s" is not available for purchase.', 'woocommerce-appointments' ), $option['label'] ) );
						}
 					}
 				}
 			} else {
 				foreach ( $this->addon['options'] as $option ) {
					if ( sanitize_title( $option['label'] ) === $posted && isset( $option['visibility'] ) && 0 === $option['visibility'] ) {
						// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is escaped later.
						/* translators: %s Selected add-on option label */
						return new WP_Error( 'error', sprintf( __( '"%s" is not available for purchase.', 'woocommerce-appointments' ), $option['label'] ) );
					}
 				}
 			}
 		}
 		return true;
 	}

	/**
	 * Process this field after being posted
	 *
	 * @return array|WP_Error Array on success and WP_Error on failure
	 */
	public function get_cart_item_data() {
		$cart_item_data = [];
		$value          = $this->value;

		if ( empty( $value ) ) {
			return false;
		}

		if ( ! is_array( $value ) ) {
			$value = array( $value );
		}

		if ( is_array( current( $value ) ) ) {
			$value = current( $value );
		}

		foreach ( $this->addon['options'] as $option ) {
			if ( in_array( strtolower( sanitize_title( $option['label'] ) ), array_map( 'strtolower', array_values( $value ) ) ) ) {
				$cart_item_data[] = array(
					'name'          => sanitize_text_field( $this->addon['name'] ),
					'value'         => $option['label'],
					'price'         => floatval( sanitize_text_field( $this->get_option_price( $option ) ) ),
					'field_name'    => $this->addon['field_name'],
					'field_type'    => $this->addon['type'],
					'id'            => isset( $this->addon['id'] ) ? $this->addon['id'] : 0,
					'price_type'    => $option['price_type'],
					'duration'      => isset( $option['duration'] ) ? $option['duration'] : 0,
					'duration_type' => isset( $option['duration_type'] ) ? $option['duration_type'] : '',
					'hide_duration' => isset( $this->addon['wc_appointment_hide_duration_label'] ) && $this->addon['wc_appointment_hide_duration_label'] ? 1 : 0,
					'hide_price'    => isset( $this->addon['wc_appointment_hide_price_label'] ) && $this->addon['wc_appointment_hide_price_label'] ? 1 : 0,
				);
			}
		}

		return $cart_item_data;
	}
}
