<?php
/**
 * Select field
 *
 * @version 7.7.0
 */
class WC_Product_Addons_Field_Select extends WC_Product_Addons_Field {

	/**
	 * Validate an addon
	 *
	 * @return bool pass or fail, or WP_Error
	 */
	 public function validate() {
 		$posted = isset( $this->value ) ? $this->value : '';

		if ( ! empty( $this->addon['required'] ) && '' === $posted ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is escaped later.
			/* translators: Add-on name */
			return new WP_Error( 'error', sprintf( __( '"%s" is a required field.', 'woocommerce-appointments' ), $this->addon['name'] ) );
		}

 		if ( '' !== $posted && isset( $this->addon['options'] ) ) {
 			// Example of posted value: "gold-1", which is "value-index".
 			$posted_raw_data = explode( '-', $posted );

 			if ( isset( $posted_raw_data[1] ) ) {
 				// Normalize index because in the frontend, 0 is the None value,
 				// but in the admin, 0 is the first option.
 				$selected_option_index = (int) $posted_raw_data[1] - 1;
				if ( isset( $this->addon['options'][ $selected_option_index ]['visibility'] ) && 0 === $this->addon['options'][ $selected_option_index ]['visibility'] ) {
					// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is escaped later.
					/* translators: %s Selected add-on option name */
					return new WP_Error( 'error', sprintf( __( '"%s" is not available for purchase.', 'woocommerce-appointments' ), $this->addon['options'][ $selected_option_index ]['label'] ) );
				}
 			}
 		}
 		return true;
 	}

	/**
	 * Process this field after being posted
	 *
	 * @return array on success, WP_ERROR on failure
	 */
	public function get_cart_item_data() {
		$cart_item_data = [];

		if ( empty( $this->value ) ) {
			return false;
		}

		$chosen_option = '';
		$loop          = 0;

		foreach ( $this->addon['options'] as $option ) {
			++$loop;
			if ( sanitize_title( $option['label'] ) . '-' . $loop === $this->value ) {
				$chosen_option = $option;
				break;
			}
		}

		if ( ! $chosen_option ) {
			return false;
		}

		$cart_item_data[] = array(
			'name'          => sanitize_text_field( $this->addon['name'] ),
			'value'         => $chosen_option['label'],
			'price'         => floatval( sanitize_text_field( $this->get_option_price( $chosen_option ) ) ),
			'field_name'    => $this->addon['field_name'],
			'field_type'    => $this->addon['type'],
			'id'            => isset( $this->addon['id'] ) ? $this->addon['id'] : 0,
			'price_type'    => $chosen_option['price_type'],
			'duration'      => isset( $chosen_option['duration'] ) ? $chosen_option['duration'] : 0,
			'duration_type' => isset( $chosen_option['duration_type'] ) ? $chosen_option['duration_type'] : '',
			'hide_duration' => isset( $this->addon['wc_appointment_hide_duration_label'] ) && $this->addon['wc_appointment_hide_duration_label'] ? 1 : 0,
			'hide_price'    => isset( $this->addon['wc_appointment_hide_price_label'] ) && $this->addon['wc_appointment_hide_price_label'] ? 1 : 0,
		);

		return $cart_item_data;
	}
}
