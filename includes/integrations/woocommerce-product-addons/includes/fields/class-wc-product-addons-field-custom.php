<?php
/**
 * WC_Product_Addons_Field_Custom class
 *
 * @version 7.7.0
 */
class WC_Product_Addons_Field_Custom extends WC_Product_Addons_Field {
	/**
	 * Validate an addon
	 * @return bool pass, or WP_Error
	 */
	public function validate() {
		$posted = isset( $this->value ) ? $this->value : '';
		$posted = apply_filters( 'woocommerce_product_addons_validate_value', $posted, $this );

		// Required addon checks.
		if ( ! empty( $this->addon['required'] ) && '' === $posted ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is escaped later.
			/* translators: %s Addon name */
			return new WP_Error( 'error', sprintf( __( '"%s" is a required field.', 'woocommerce-appointments' ), $this->addon['name'] ) );
		}

		if ( '1' == $this->addon['restrictions'] ) {
			// Min, max checks
			switch ( $this->addon['type'] ) {
				case 'custom_text':
				case 'custom_textarea':
				if ( ! empty( $this->addon['min'] ) && '' !== $posted && mb_strlen( $posted, 'UTF-8' ) < $this->addon['min'] ) {
					// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is escaped later.
					/* translators: 1 Addon name 2 Minimum amount */
					return new WP_Error( 'error', sprintf( __( 'The minimum characters required for "%1$s" is %2$s.', 'woocommerce-appointments' ), $this->addon['name'], $this->addon['min'] ) );
				}

				if ( ! empty( $this->addon['max'] ) && '' !== $posted && mb_strlen( $posted, 'UTF-8' ) > $this->addon['max'] ) {
					// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is escaped later.
					/* translators: 1 Addon name 2 Maximum amount */
					return new WP_Error( 'error', sprintf( __( 'The maximum allowed characters for "%1$s" is %2$s.', 'woocommerce-appointments' ), $this->addon['name'], $this->addon['max'] ) );
				}
					break;
				case 'custom_price':
				case 'input_multiplier':
					// Convert comma separated decimals to dot separated to
					// support comparisons.
					$posted = wc_format_decimal( $posted );

					if ( ! empty( $this->addon['min'] ) && '' !== $posted && $posted < $this->addon['min'] ) {
						// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is escaped later.
						/* translators: 1 Addon name 2 minimum amount */
						return new WP_Error( 'error', sprintf( __( 'The minimum amount required for "%1$s" is %2$s.', 'woocommerce-appointments' ), $this->addon['name'], $this->addon['min'] ) );
					}

					if ( ! empty( $this->addon['max'] ) && '' !== $posted && $posted > $this->addon['max'] ) {
						// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is escaped later.
						/* translators: 1 Addon name 2 Maximum amount */
						return new WP_Error( 'error', sprintf( __( 'The maximum allowed amount for "%1$s" is %2$s.', 'woocommerce-appointments' ), $this->addon['name'], $this->addon['max'] ) );
					}
					break;
			}
		}

		// Other option specific checks.
		switch ( $this->addon['type'] ) {
			case 'input_multiplier':
				$posted = absint( $posted );
				if ( $posted < 0 ) {
					// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is escaped later.
					/* translators: %s Addon name */
					return new WP_Error( 'error', sprintf( __( 'Please enter a value greater than 0 for "%s".', 'woocommerce-appointments' ), $this->addon['name'] ) );
				}
				break;
		}

		return true;
	}

	/**
	 * Process this field after being posted
	 * @return array on success, WP_ERROR on failure
	 */
	public function get_cart_item_data() {
		$cart_item_data = [];
		$posted         = isset( $this->value ) ? $this->value : '';

		if ( '' === $posted ) {
			return $cart_item_data;
		}

		$label           = sanitize_text_field( $this->addon['name'] );
		$price           = floatval( sanitize_text_field( $this->addon['price'] ) );
		$adjust_price    = $this->addon['adjust_price'];
		$adjust_duration = isset( $this->addon['adjust_duration'] ) && $this->addon['adjust_duration'] ? $this->addon['adjust_duration'] : 0;

		switch ( $this->addon['type'] ) {
			case 'custom_price':
				$price  = floatval( sanitize_text_field( $posted ) );
				$posted = absint( $posted );

				if ( 0 <= $price ) {
					$cart_item_data[] = array(
						'name'          => $label,
						'value'         => $posted,
						'price'         => floatval( sanitize_text_field( $price ) ),
						'display'       => wp_strip_all_tags( wc_price( $price ) ),
						'field_name'    => $this->addon['field_name'],
						'id'            => isset( $this->addon['id'] ) ? $this->addon['id'] : 0,
						'field_type'    => $this->addon['type'],
						'price_type'    => $this->addon['price_type'],
						'duration'      => isset( $this->addon['duration'] ) ? $this->addon['duration'] : 0,
						'duration_type' => isset( $this->addon['duration_type'] ) ? $this->addon['duration_type'] : '',
						'hide_duration' => isset( $this->addon['wc_appointment_hide_duration_label'] ) && $this->addon['wc_appointment_hide_duration_label'] ? 1 : 0,
						'hide_price'    => isset( $this->addon['wc_appointment_hide_price_label'] ) && $this->addon['wc_appointment_hide_price_label'] ? 1 : 0,
					);
				}
				break;
			case 'input_multiplier':
				$posted = absint( $posted );

				if ( 0 < $posted ) {
					$cart_item_data[] = array(
						'name'          => $label,
						'value'         => $posted,
						'price'         => '1' != $adjust_price ? 0 : floatval( sanitize_text_field( $price * $posted ) ),
						'field_name'    => $this->addon['field_name'],
						'field_type'    => $this->addon['type'],
						'id'            => isset( $this->addon['id'] ) ? $this->addon['id'] : 0,
						'price_type'    => $this->addon['price_type'],
						'duration'      => '1' == $adjust_duration && isset( $this->addon['duration'] ) ? $this->addon['duration'] * $posted : 0,
						'duration_type' => isset( $this->addon['duration_type'] ) ? $this->addon['duration_type'] : '',
						'hide_duration' => isset( $this->addon['wc_appointment_hide_duration_label'] ) && $this->addon['wc_appointment_hide_duration_label'] ? 1 : 0,
						'hide_price'    => isset( $this->addon['wc_appointment_hide_price_label'] ) && $this->addon['wc_appointment_hide_price_label'] ? 1 : 0,
					);
				}
				break;
			default:
				$cart_item_data[] = array(
					'name'          => $label,
					'value'         => wp_kses_post( $posted ),
					'price'         => '1' != $adjust_price ? 0 : floatval( sanitize_text_field( $price ) ),
					'field_name'    => $this->addon['field_name'],
					'field_type'    => $this->addon['type'],
					'id'            => isset( $this->addon['id'] ) ? $this->addon['id'] : 0,
					'price_type'    => $this->addon['price_type'],
					'duration'      => '1' == $adjust_duration && isset( $this->addon['duration'] ) ? $this->addon['duration'] : 0,
					'duration_type' => isset( $this->addon['duration_type'] ) ? $this->addon['duration_type'] : '',
					'hide_duration' => isset( $this->addon['wc_appointment_hide_duration_label'] ) && $this->addon['wc_appointment_hide_duration_label'] ? 1 : 0,
					'hide_price'    => isset( $this->addon['wc_appointment_hide_price_label'] ) && $this->addon['wc_appointment_hide_price_label'] ? 1 : 0,
				);
				break;
		}

		return $cart_item_data;
	}
}
