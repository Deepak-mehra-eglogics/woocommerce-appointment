<?php
/**
 * Rest API v2 Abstract Group helper.
 *
 * @package Automattic\WooCommerce\ProductAddons
 * @since 7.0.3
 * @version 7.6.0
 */

/**
 * WC_Product_Addons_Api_V2_Abstract_Group class.
 */
abstract class WC_Product_Addons_Api_V2_Abstract_Group {
	/**
	 * Group ID.
	 *
	 * @var int
	 */
	protected $id = 0;

	/**
	 * Group name.
	 *
	 * @var int
	 */
	protected $name = '';

	/**
	 * Group priority.
	 *
	 * @var int
	 */
	protected $priority = 1;

	/**
	 * Group fields (add-ons).
	 *
	 * @var array
	 */
	protected $fields = array();

	/**
	 * Persist data.
	 */
	abstract public function save();

	/**
	 * Get id.
	 *
	 * @return int
	 */
	public function get_id() {
		return $this->id;
	}

	/**
	 * Get name.
	 *
	 * @return string
	 */
	public function get_name() {
		return $this->name;
	}

	/**
	 * Set name.
	 *
	 * @param string $value Value to set.
	 */
	public function set_name( string $value ) {
		$this->name = $value;
	}

	/**
	 * Get priority.
	 *
	 * @return string
	 */
	public function get_priority() {
		return $this->priority;
	}

	/**
	 * Set priority.
	 *
	 * @param int $value Value to set.
	 */
	public function set_priority( int $value ) {
		$this->priority = $value;
	}

	/**
	 * Get fields.
	 *
	 * @return array
	 */
	public function get_fields() {
		return $this->fields;
	}

	/**
	 * Set fields.
	 *
	 * @param array $value Value to set.
	 */
	public function set_fields( array $value ) {
		$this->fields = $value;
	}

	/**
	 * Update fields.
	 *
	 * Blend in Add-Ons with matching id, create new Add-Ons if not found.
	 *
	 * @param array $values Values to update.
	 */
	public function update_fields( array $values ) {
		if ( empty( $this->fields ) ) {
			$this->fields = $values;
			return;
		}

		$updated_ids = array();

		// Search for a matching id and update the Add-on if found. Otherwise, create a new Add-On.
		foreach ( $values as $key => $value ) {
			$found_id = false;
			foreach ( $this->fields as $existing_field_key => $existing_field_value ) {
				// Found a matching Add-On key -> update the values.
				if ( isset( $value['id'] ) && $existing_field_value['id'] === $value['id'] ) {
					foreach ( $value as $sub_key => $new_value ) {
						$this->fields[ $existing_field_key ][ $sub_key ] = $new_value;
					}
					$found_id                                   = true;
					$updated_ids[ $existing_field_value['id'] ] = 1;
				}
			}
			// No Add-On with matching id -> create a new Add-On.
			if ( ! $found_id ) {
				$this->fields[] = array();
				end( $this->fields );
				$last_key = key( $this->fields );
				foreach ( $value as $sub_key => $new_value ) {
					$this->fields[ $last_key ][ $sub_key ] = $new_value;
				}
			}
		}

		// Delete Add-ons not referenced in the update (except for those just created).
		foreach ( $this->fields as $existing_field_key => $existing_field_value ) {
			if ( 0 !== $existing_field_value['id'] && ! array_key_exists( $existing_field_value['id'], $updated_ids ) ) {
				unset( $this->fields[ $existing_field_key ] );
			}
		}

		// Reindex.
		$this->fields = array_values( $this->fields );
	}

	/**
	 * Convert fields from the DB format to internal representation which is aligned with the API types.
	 *
	 * - Numeric values converted to their correct types (int/float).
	 * - Bool values stored as 0/1 converted to true/false.
	 * - Options are normalized using normalize_field_options_from_db.
	 * - Defaults converted to string (as it's a complex type, can be both an int/float number or a list of numbers)
	 *
	 * @param string|array $fields Fields to format.
	 */
	protected function set_fields_from_db( $fields ): void {
		$fields = array_filter( (array) $fields );

		$default_values = array(
			'name'               => '',
			'type'               => 'multiple_choice',
			'position'           => '',
			'required'           => '',
			'title_format'       => 'label',
			'default'            => '',
			'description_enable' => '',
			'description'        => '',
			'placeholder_enable' => '',
			'placeholder'        => '',
			'display'            => 'select',
			'restrictions_type'  => 'any_text',
			'adjust_price'       => '',
			'price'              => '',
			'price_type'         => '',
			'restrictions'       => '',
			'min'                => 0,
			'max'                => 0,
			'options'            => array(),
		);

		foreach ( $fields as $key => $field ) {
			$field          = wp_parse_args( $field, $default_values );
			$fields[ $key ] = array(
				'name'               => $field['name'],
				'type'               => $field['type'],
				'position'           => (int) $field['position'],
				'required'           => wc_string_to_bool( $field['required'] ),
				'title_format'       => $field['title_format'],
				'default'            => in_array(
					$field['type'],
					WC_Product_Addons_Api_V2_Validation::$default_valid_for,
					true
				) ? (string) $field['default'] :
					'',
				'description_enable' => wc_string_to_bool( $field['description_enable'] ),
				'description'        => $field['description'],
				'placeholder_enable' => wc_string_to_bool( $field['placeholder_enable'] ),
				'placeholder'        => $field['placeholder'],
				'display'            => $field['display'],
				'restrictions_type'  => $field['restrictions_type'],
				'adjust_price'       => wc_string_to_bool( $field['adjust_price'] ),
				'price'              => (string) $field['price'], // aligned with the schema.
				'price_type'         => $field['price_type'],
				'restrictions'       => wc_string_to_bool( $field['restrictions'] ),
				'min'                => (string) $field['min'], // needs to be aligned with the schema type.
				'max'                => (string) $field['max'], // needs to be aligned with the schema type.
				'options'            => in_array(
					$field['type'],
					array(
						'multiple_choice',
						'checkbox',
					),
					true
				) ? array_map(
					array(
						__CLASS__,
						'normalize_field_options_from_db',
					),
					array_filter( (array) $field['options'] )
				) : array(),
			);

			if ( isset( $field['id'] ) ) {
				$fields[ $key ]['id'] = (int) $field['id'];
			}
		}

		$this->set_fields( $fields );
	}

	/**
	 * Normalize options of a field.
	 *
	 * @param array $option Field option.
	 * @return array
	 */
	protected static function normalize_field_options_from_db( array $option ): array {
		return array(
			'label'      => $option['label'] ?? '',
			'price'      => $option['price'] ?? '',
			'price_type' => $option['price_type'] ?? '',
			'image'      => absint( $option['image'] ?? 0 ), // Image ID.
			'visibility' => wc_string_to_bool( $option['visibility'] ?? 1 ),
		);
	}

	/**
	 * Normalize default option.
	 *
	 * @param string $default_option_value Default option.
	 * @param string $type  Add-on type.
	 * @return string
	 */
	protected static function normalize_default_options_for_db( string $default_option_value, string $type ) {

		// For Multiple Choice add-ons, store index of default option.
		// For Quantity add-ons, store default qty value.
		if ( in_array( $type, array( 'multiple_choice', 'input_multiplier' ), true ) ) {
			$default_option_value = (int) $default_option_value;

			// For Price add-ons, store default price value.
		} elseif ( 'custom_price' === $type ) {
			$default_option_value = (float) $default_option_value;

			// For Checkboxes, we store a comma separated string with default indexes.
		} elseif ( 'checkboxes' === $type ) {

			// Cast all indexes to int.
			$default_option_values = explode( ',', $default_option_value );
			$default_option_values = array_unique( array_map( 'intval', $default_option_values ), SORT_NUMERIC );

			// Re-create comma separated string, after casting all values to int.
			$default_option_value = implode( ',', $default_option_values );
		}

		return (string) $default_option_value;
	}

	/**
	 * Normalize min/max for storing in the db based on the add-on type.
	 *
	 * @param string $value Min or max value to normalize.
	 * @param string $addon_type Type of add-on.
	 *
	 * @return float|int|string
	 */
	protected static function normalize_min_max_for_db( string $value, string $addon_type ) {
		// Price can float.
		if ( 'custom_price' === $addon_type ) {
			$value = (float) $value;

		} elseif ( in_array( $addon_type, array( 'custom_text', 'custom_textarea', 'input_multiplier' ), true ) ) {
			$value = (int) $value;
		}

		return $value;
	}

	/**
	 * Get fields to save to DB.
	 *
	 * @return array
	 */
	protected function get_fields_for_db(): array {
		// This will be used to store in the database.
		$fields = $this->get_fields();

		foreach ( $fields as $key => $field ) {
			$fields[ $key ]['id']                 = isset( $field['id'] ) && $field['id'] ? $field['id'] : WC_Product_Addons_Helper::generate_id();
			$fields[ $key ]['description_enable'] = ! empty( $field['description'] ) ? 1 : 0; // Set description_enable if description is set.
			$fields[ $key ]['required']           = ! empty( $field['required'] ) ? 1 : 0;
			$fields[ $key ]['restrictions']       = ( ! empty( $field['min'] ) || ! empty( $field['max'] ) ) ? 1 : 0; // Set restrictions if min or max is set.
			$fields[ $key ]['adjust_price']       = ! empty( $field['price'] ) ? 1 : 0; // Set adjust_price if price is set.
			$fields[ $key ]['placeholder_enable'] = ! empty( $field['placeholder'] ) ? 1 : 0; // Set placeholder_enable if placeholder is set.

			if ( ! in_array( $field['type'], WC_Product_Addons_Api_V2_Validation::$options_valid_for ) ) {
				$fields[ $key ]['options'] = array();
			} else {
				foreach ( $fields[ $key ]['options'] as $index => $option ) {
					$fields[ $key ]['options'][ $index ]['visibility'] = isset( $fields[ $key ]['options'][ $index ]['visibility'] ) && false === $fields[ $key ]['options'][ $index ]['visibility'] ? 0 : 1;
				}
			}

			if ( ! in_array( $field['type'], WC_Product_Addons_Api_V2_Validation::$default_valid_for, true ) || '' === $field['default'] ) {
				$fields[ $key ]['default'] = '';
			} else {
				$fields[ $key ]['default'] = self::normalize_default_options_for_db( $field['default'], $field['type'] );
			}

			// Min and max have been historically stored as numeric types while price as a string -> convert to correct types.
			$fields[ $key ]['price'] = ! empty( $field['price'] ) ? (string) $field['price'] : '';
			$fields[ $key ]['min']   = ! empty( $field['min'] ) ? self::normalize_min_max_for_db( $field['min'], $field['type'] ) : 0;
			$fields[ $key ]['max']   = ! empty( $field['max'] ) ? self::normalize_min_max_for_db( $field['max'], $field['type'] ) : 0;
		}

		// Update local object copy without re-read from the db.
		$this->set_fields_from_db( $fields );

		return $fields;
	}
}
