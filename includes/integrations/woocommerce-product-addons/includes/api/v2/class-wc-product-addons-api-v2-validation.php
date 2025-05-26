<?php
/**
 * Rest API v2 Validation helper.
 *
 * @package Automattic\WooCommerce\ProductAddons
 * @since 6.9.0
 * @version 7.6.0
 */

/**
 * WC_Product_Addons_Api_V2_Product_Group class.
 */
class WC_Product_Addons_Api_V2_Validation {

	public static $options_valid_for = array(
		'multiple_choice',
		'checkbox',
	);

	/**
	 * Add-on types that support default values.
	 *
	 * @var string[]
	 */
	public static $default_valid_for = array(
		'multiple_choice',
		'checkbox',
		'input_multiplier',
		'custom_price',
	);

	/**
	 * Add-on types that support min/max values.
	 *
	 * @var string[]
	 */
	public static $min_max_valid_for = array(
		'custom_text',
		'custom_textarea',
		'custom_price',
		'input_multiplier',
	);

	/**
	 * Return true if the object is getting created.
	 *
	 * If the object includes an `id` parameter, then this object is to be updated. Otherwise, assume it's a request to create the object.
	 *
	 * @param array $object Add-on or group object.
	 */
	public static function creating_object( $object ) {

		return ! self::updating_object( $object );
	}

	/**
	 * Return true if the object is getting updated.
	 *
	 * If the object includes an `id` parameter, then this object is to be updated. Otherwise, assume it's a request to create the object.
	 *
	 * @param array $object Add-on or group object.
	 */
	public static function updating_object( $object ) {

		return isset( $object['id'] );
	}

	/**
	 * Validate Name of Product Add-On.
	 *
	 * This callback will (and can) only be called from Validation::is_array_of_addons(), hence it can have some extra parameters.
	 *
	 * @param any              $value The value to validate.
	 * @param \WP_REST_Request $request The request object.
	 * @param string           $param The parameter name.
	 * @param array            $schema
	 * @param array            $all_values
	 *
	 * @return true|\WP_Error
	 */
	public function validate_addon_name( $value, $request, $param, $schema, $all_values ) {

		return $this->validate_name( $value, self::creating_object( $all_values ), __( 'Add-on', 'woocommerce-appointments' ) );
	}

	/**
	 * Validate Name of Global groups.
	 *
	 * This callback will be called from regular WP code, hence it only gets standard set of parameters.
	 *
	 * @param any              $value The value to validate.
	 * @param \WP_REST_Request $request The request object.
	 * @param string           $param The parameter name.
	 *
	 * @return true|\WP_Error
	 */
	public function validate_group_name( $value, $request, $param = '' ) {

		return $this->validate_name( $value, self::creating_object( $request ), __( 'Global group', 'woocommerce-appointments' ) );
	}

	/**
	 * Validate Name of Product Add-On and Global groups.
	 *
	 * Name is required when creating an object.
	 * Name cannot be longer than 255 characters and it's a string.
	 *
	 * @param string $name The name to validate.
	 * @param bool   $is_create_request Whether the object is being created or updated.
	 * @param string $param_name The parameter name (to be used in error messages).
	 *
	 * @return true|\WP_Error
	 */
	protected function validate_name( $name, $is_create_request, $param_name ) {

		$errors = new \WP_Error();

		// Name is required when creating an object.
		if ( $is_create_request && empty( $name ) ) {
			$errors->add(
				'rest_invalid_param',
				sprintf(
					// translators: %s object type.
					__( 'Name required when creating %s.', 'woocommerce-appointments' ),
					$param_name
				)
			);
			return $errors;
		}

		// Name has to be a string.
		if ( ! is_string( $name ) ) {
			$errors->add(
				'rest_invalid_param',
				sprintf(
					// translators: %s object type.
					__( '%s name must be a string.', 'woocommerce-appointments' ),
					$param_name
				)
			);
			return $errors;
		}

		// Name is max 255 characters long.
		// TODO: utf8?
		if ( 255 < mb_strlen( $name ) ) {
			$errors->add(
				'rest_invalid_param',
				sprintf(
					// translators: %s object type.
					__( '%s name can be maximum 255 characters long.', 'woocommerce-appointments' ),
					$param_name
				)
			);
		}

		return ! empty( $errors->errors ) ? $errors : true;
	}

	/**
	 * Validates that the passed argument is an array of add-on fields. An empty array
	 * IS acceptable. This also validates each option in the field's options array against
	 * the field type.
	 *
	 * @param array            $values The data to validate.
	 * @param \WP_REST_Request $request Request object.
	 * @param string           $param Current param being validated.
	 * @param array            $schema  Field schema.
	 * @return bool|\WP_Error
	 */
	public function is_array_of_addons( $values, $request, $param, $schema ) {
		$errors = new \WP_Error();

		$required_for_create = array(
			'name',
			'type',
		);

		if ( ! is_array( $values ) ) {
			$errors->add(
				'rest_invalid_param',
				__( 'Array expected for fields.', 'woocommerce-appointments' )
			);
			return $errors;
		}

		foreach ( $values as $value ) {

			foreach ( $schema as $property_key => $property_args ) {
				// Name and type are required for create requests, not for updates.
				if ( self::creating_object( $value ) && in_array( $property_key, $required_for_create ) ) {
					$required = true;
				} else {
					$required = $property_args['required'] ?? false;
				}

				// If field is required, but it's not set.
				if ( $required && ! isset( $value[ $property_key ] ) ) {
					$errors->add(
						'missing_required_field',
						sprintf(
							// translators: %s field name.
							__( '"%s" is required', 'woocommerce-appointments' ),
							$param . ' > ' . $property_key
						)
					);
					continue;
				}

				if ( 'options' === $property_key ) {
					// Options are required for some Add-on types, can be null for others. Since validate_callback is only
					// called for non-null values, it needs to be checked also here.
					$options_valid = $this->validate_options( $value['options'] ?? null, $request, $param, $schema, $value );
					if ( is_wp_error( $options_valid ) ) {
						$errors->add(
							$options_valid->get_error_code(),
							$options_valid->get_error_message()
						);
						continue;
					}
				}

				if ( isset( $value[ $property_key ] ) ) {
					if ( isset( $property_args['arg_options']['validate_callback'] ) && is_callable( $property_args['arg_options']['validate_callback'] ) ) {
						$result = $property_args['arg_options']['validate_callback']( $value[ $property_key ], $request, $param . ' > ' . $property_key, $property_args, $value );
					} else {
						$result = rest_validate_value_from_schema( $value[ $property_key ], $property_args, $param . ' > ' . $property_key );
					}

					if ( is_wp_error( $result ) ) {
						return $result;
					}
				}
			}
		}

		return ! empty( $errors->errors ) ? $errors : true;
	}

	/**
	 * Sanitizes fields with defaults and schema properties.
	 *
	 * @param array            $addons The data to sanitize.
	 * @param \WP_REST_Request $request Request object.
	 * @param string           $param Current param being sanitized.
	 * @param array            $schema  Field schema.
	 *
	 * @return array
	 */
	public function sanitize_array_of_addons( $addons, $request, $param, $schema ) {
		$return = array();

		foreach ( $addons as $index => $addon ) {
			foreach ( $schema as $property_key => $property_args ) {
				// In case of updating an Add-on, only change values specified in the request.
				if ( self::updating_object( $addon ) && ! isset( $addon[ $property_key ] ) ) {
					continue;
				}

				$default = $property_args['arg_options']['default'] ?? null;
				if ( isset( $property_args['arg_options']['sanitize_callback'] ) && is_callable( $property_args['arg_options']['sanitize_callback'] ) ) {
					$result = $property_args['arg_options']['sanitize_callback']( wc_clean( wp_unslash( $addon[ $property_key ] ?? $default ) ), $property_args, $param . ' > ' . $property_key );
				} else {
					$result = rest_sanitize_value_from_schema( wc_clean( wp_unslash( $addon[ $property_key ] ?? $default ) ), $property_args, $param . ' > ' . $property_key );
				}

				if ( is_wp_error( $result ) ) {
					return $result;
				}

				$return[ $index ][ $property_key ] = $result;
			}
		}

		return $return;
	}

	/**
	 * Validates that the passed argument is empty or a float.
	 *
	 * @param string           $value The data to validate.
	 * @param \WP_REST_Request $request Request object.
	 * @param string           $param Current param being validated.
	 * @return bool|\WP_Error
	 */
	public function is_empty_or_float( $value, $request, $param ) {
		if ( ! empty( $value ) && ! is_numeric( wc_format_decimal( $value ) ) ) {
			return new \WP_Error(
				'rest_invalid_type',
				sprintf(
					// translators: %s field name.
					__( 'Float (or empty string) expected for "%s"', 'woocommerce-appointments' ),
					$param
				)
			);
		}

		return true;
	}

	/**
	 * Validate min and max values and the correct relation between them.
	 *
	 * This callback will (and can) only be called from Validation::is_array_of_addons(), hence it can have some extra parameters.
	 *
	 * @param string           $value The data to validate.
	 * @param \WP_REST_Request $request Request object.
	 * @param string           $param Current param being validated.
	 * @param array            $all_values All values.
	 *
	 * @return bool|\WP_Error
	 */
	public function validate_min_max( $value, $request, $param, $schema, $all_values ) {
		// Not requiring string here to allow people enter numeric values for bw compat.

		$type = null;

		if ( self::creating_object( $all_values ) ) {

			// Skip validation, if min & max aren't set in the 'create' request.
			if ( ! isset( $all_values['min'] ) && ! isset( $all_values['max'] ) ) {
				return true;
			}

			if ( ! isset( $all_values['type'] ) ) {
				return new \WP_Error(
					'rest_invalid_param',
					__( 'Type must be defined when creating an Add-on.', 'woocommerce-appointments' )
				);
			}
			$type   = $all_values['type'];
			$addons = array();
		} else {
			$id = $request->get_param( 'id' );
			if ( \WC_Product_Addons_Api_V2_Global_Group::is_a_global_group_id( $id ) ) {
				$group = new WC_Product_Addons_Api_V2_Global_Group( $id );
			} else {
				$group = new WC_Product_Addons_Api_V2_Product_Group( $id );
			}
			$addons = $group->get_fields();

			// When updating, type doesn't have to be specified in the request -> get it from the object if it's not specified in request.
			if ( isset( $all_values['type'] ) ) {
				$type = $all_values['type'];
			} else {
				// Find the correct Add-on and get type from the add-on.
				foreach ( $addons as $addon ) {
					if ( $addon['id'] === $all_values['id'] ) {
						$type = $addon['type'];
						break;
					}
				}
			}
		}

		$min = self::get_addon_prop( 'min', $all_values, $addons );
		$max = self::get_addon_prop( 'max', $all_values, $addons );

		if ( ! in_array( $type, self::$min_max_valid_for, true ) && ! empty( $value ) ) {
			return new \WP_Error(
				'rest_invalid_param',
				__( 'Min/max values can only be defined/set for "Quantity", "Price", "Short Text" and "Long Text" add-on types.', 'woocommerce-appointments' )
			);
		}

		// Allow only integers for all add-ons except custom_price add-ons.
		if (
			in_array(
				$type,
				array_diff(
					self::$min_max_valid_for,
					array( 'custom_price' )
				),
				true
			)
			&& (
				filter_var( $min, FILTER_VALIDATE_INT ) === false
				|| filter_var( $max, FILTER_VALIDATE_INT ) === false
			)
		) {
			return new \WP_Error(
				'rest_invalid_param',
				__( 'Min/max values must be integers for "Quantity", "Short Text" and "Long Text" add-on types.', 'woocommerce-appointments' )
			);
		}

		// If any of the min/max values are empty or neutral, there's nothing to compare with => valid.
		if ( empty( $min ) || empty( $max ) || 0 === (int) $min || 0 === (int) $max ) {
			return true;
		}

		if ( (float) wc_format_decimal( $min ) <= (float) wc_format_decimal( $max ) ) {
			return true;
		}

		return new \WP_Error(
			'rest_invalid_param',
			sprintf(
				// translators: 1: min value, 2: max value.
				__( 'Provided min value (%1$d) must be less than or equal to the max value (%2$d).', 'woocommerce-appointments' ),
				$min,
				$max
			)
		);
	}

	/**
	 * Validates default add-on option:
	 *
	 * - default must be within the min/max limits for Quantity and Price add-ons
	 * - default option index must exist for Checkboxes and Multiple Choice add-ons
	 *
	 * This callback will (and can) only be called from Validation::is_array_of_addons(), hence it can have some extra parameters.
	 *
	 * @param array            $value The data to validate.
	 * @param \WP_REST_Request $request Request object.
	 * @param string           $param Current param being validated.
	 * @param array            $schema Field schema.
	 * @param array            $all_values All values.
	 *
	 * @return true|\WP_Error
	 */
	public function validate_default( $value, $request, $param, $schema, $all_values ) {

		$type = null;

		if ( self::creating_object( $all_values ) ) {

			// Skip validation, if default is not set in the 'create' request.
			if ( ! isset( $all_values['default'] ) ) {
				return true;
			}

			if ( ! isset( $all_values['type'] ) ) {
				return new \WP_Error(
					'rest_invalid_param',
					__( 'Type must be defined when creating an Add-on.', 'woocommerce-appointments' )
				);
			}
			$type   = $all_values['type'];
			$addons = array();
		} else {
			$id = $request->get_param( 'id' );
			if ( \WC_Product_Addons_Api_V2_Global_Group::is_a_global_group_id( $id ) ) {
				$group = new WC_Product_Addons_Api_V2_Global_Group( $id );
			} else {
				$group = new WC_Product_Addons_Api_V2_Product_Group( $id );
			}
			$addons = $group->get_fields();

			// When updating, type doesn't have to be specified in the request -> get it from the object if it's not specified in request.
			if ( isset( $all_values['type'] ) ) {
				$type = $all_values['type'];
			} else {
				// Find the correct Add-on and get type from the add-on.
				foreach ( $addons as $addon ) {
					if ( $addon['id'] === $all_values['id'] ) {
						$type = $addon['type'];
						break;
					}
				}
			}

			// Handle value correctly when updating: if it's in the request, test that, otherwise check against stored value.
			if ( ! isset( $all_values['default'] ) ) {
				foreach ( $addons as $addon ) {
					if ( $addon['id'] === $all_values['id'] ) {
						$value = $addon['default'];
						break;
					}
				}
			}
		}

		// Default values make sense only for:
		// Multiple choice, Checkbox, Price and Quantity add-ons.
		if ( ! in_array( $type, self::$default_valid_for, true ) && ! empty( $value ) ) {
			return new \WP_Error(
				'rest_invalid_param',
				__( 'Default values can only be defined/set for "Multiple Choice", "Checkboxes", "Quantity" and "Price" add-on types.', 'woocommerce-appointments' )
			);
		}

		// No default option.
		if ( '' === $value ) {
			return true;
		}

		if ( 'input_multiplier' === $type ) {

			if ( ! is_numeric( $value ) ) {
				return new \WP_Error(
					'rest_invalid_param',
					sprintf(
						// translators: 1: default qty.
						__( 'Provided default quantity (%1$s) is not a numeric string.', 'woocommerce-appointments' ),
						$value
					)
				);
			}

			if ( $value < 0 ) {
				return new \WP_Error(
					'rest_invalid_param',
					sprintf(
						// translators: 1: default qty.
						__( 'Provided default quantity (%1$s) is not a positive number.', 'woocommerce-appointments' ),
						$value
					)
				);
			}

			$min = self::get_addon_prop( 'min', $all_values, $addons );
			$max = self::get_addon_prop( 'max', $all_values, $addons );

			// If no Min/Max restrictions, nothing to check.
			if ( ! $min && ! $max ) {
				return true;
			}

			// If default < min, error!
			if ( $min && (int) $value < (int) $min ) {
				return new \WP_Error(
					'rest_invalid_param',
					sprintf(
						// translators: 1: default value, 2: min value.
						__( 'Provided default value (%1$d) must be greater than or equal to the min value (%2$d).', 'woocommerce-appointments' ),
						$value,
						$min
					)
				);
			}

			// If default > max, error!
			if ( $max && (int) $max < (int) $value ) {
				return new \WP_Error(
					'rest_invalid_param',
					sprintf(
							// translators: 1: default value, 2: max value.
						__( 'Provided default value (%1$d) must be less than or equal to the max value (%2$d).', 'woocommerce-appointments' ),
						$value,
						$max
					)
				);
			}
		} elseif ( 'custom_price' === $type ) {

			if ( ! is_numeric( $value ) ) {
				return new \WP_Error(
					'rest_invalid_param',
					sprintf(
						// translators: 1: default price.
						__( 'Provided default quantity (%1$s) is not a numeric string.', 'woocommerce-appointments' ),
						$value
					)
				);
			}

			$min = self::get_addon_prop( 'min', $all_values, $addons );
			$max = self::get_addon_prop( 'max', $all_values, $addons );

			// If no Min/Max restrictions, nothing to check.
			if ( ! $min && ! $max ) {
				return true;
			}

			// If default < min, error!
			if ( $min && (float) $value < (float) $min ) {
				return new \WP_Error(
					'rest_invalid_param',
					sprintf(
						// translators: 1: default value, 2: min value.
						__( 'Provided default value (%1$g) must be greater than or equal to the min value (%2$g).', 'woocommerce-appointments' ),
						$value,
						$min
					)
				);
			}

			// If default > max, error!
			if ( $max && (float) $max < (float) $value ) {
				return new \WP_Error(
					'rest_invalid_param',
					sprintf(
						// translators: 1: default value, 2: max value.
						__( 'Provided default value (%1$g) must be less than or equal to the max value (%2$g).', 'woocommerce-appointments' ),
						$value,
						$max
					)
				);
			}
		} elseif ( 'multiple_choice' === $type ) {

			$options = self::get_addon_prop( 'options', $all_values, $addons );

			if ( ! $options || count( $options ) < 1 ) {
				return new \WP_Error(
					'rest_invalid_param',
					__( 'At least one option is required to set a default.', 'woocommerce-appointments' )
				);
			}

			if ( ! is_numeric( $value ) || $value < 0 ) {
				return new \WP_Error(
					'rest_invalid_param',
					sprintf(
						// translators: 1: default index.
						__( 'Provided default option index (%1$s) does not exist.', 'woocommerce-appointments' ),
						$value
					)
				);
			}

			if ( (int) $value > count( $options ) - 1 ) {
				return new \WP_Error(
					'rest_invalid_param',
					sprintf(
						// translators: 1: default index, 2: max index.
						__( 'Provided default option index (%1$d) does not exist. The index of the last option is (%2$d).', 'woocommerce-appointments' ),
						$value,
						count( $options ) - 1
					)
				);
			}
		} elseif ( 'checkbox' === $type ) {

			$options = self::get_addon_prop( 'options', $all_values, $addons );

			if ( ! $options || count( $options ) < 1 ) {
				return new \WP_Error(
					'rest_invalid_param',
					__( 'At least one option is required to set a default.', 'woocommerce-appointments' )
				);
			}

			$default_indexes = explode( ',', $value );

			if ( ! is_array( $default_indexes ) || empty( $default_indexes ) ) {
				return new \WP_Error(
					'rest_invalid_param',
					__( 'The default value for "Checkboxes" add-ons must be a comma separated string of default option indexes', 'woocommerce-appointments' )
				);
			}

			$options_count = count( $options );

			foreach ( $default_indexes as $key => $default_index ) {

				if ( ! is_numeric( $default_index ) || $value < 0 ) {
					return new \WP_Error(
						'rest_invalid_param',
						sprintf(
							// translators: 1: default index.
							__( 'Provided default option index (%1$s) does not exist.', 'woocommerce-appointments' ),
							$default_index
						)
					);
				}
				if ( (int) $default_index > $options_count - 1 ) {
					return new \WP_Error(
						'rest_invalid_param',
						sprintf(
							// translators: 1: default index, 2: max index.
							__( 'Provided default option index (%1$s) does not exist. The index of the last option is (%2$d).', 'woocommerce-appointments' ),
							$default_index,
							$options_count - 1
						)
					);
				}
			}
		}

		return true;
	}

	/**
	 * Validate Add-on options:
	 *  - options are defined for multiple_choice or checkbox Add-ons
	 *  - options are not defined for other Add-ons
	 *  - options always have a name
	 *  - at least one add-on option must be visible for required add-ons
	 *
	 * This callback will (and can) only be called from Validation::is_array_of_addons(), hence it can have some extra parameters.
	 *
	 * @param array            $value The data to validate.
	 * @param \WP_REST_Request $request Request object.
	 * @param string           $param Current param being validated.
	 * @param array            $all_values All values.
	 *
	 * @return true|\WP_Error
	 */
	public function validate_options( $value, $request, $param, $schema, $all_values ) {
		$type = null;
		if ( self::creating_object( $all_values ) ) {
			if ( ! isset( $all_values['type'] ) ) {
				return new \WP_Error(
					'rest_invalid_param',
					__( 'Type must be defined when creating an Add-on.', 'woocommerce-appointments' )
				);
			}
			$type   = $all_values['type'];
			$addons = array();
		} else {
			$id = $request->get_param( 'id' );
			if ( \WC_Product_Addons_Api_V2_Global_Group::is_a_global_group_id( $id ) ) {
				$group = new WC_Product_Addons_Api_V2_Global_Group( $id );
			} else {
				$group = new WC_Product_Addons_Api_V2_Product_Group( $id );
			}
			$addons = $group->get_fields();

			// When updating, type doesn't have to be specified in the request -> get it from the object if it's not specified in request.
			if ( isset( $all_values['type'] ) ) {
				$type = $all_values['type'];
			} else {
				// Find the correct Add-on and get type from the add-on.
				foreach ( $addons as $addon ) {
					if ( $addon['id'] === $all_values['id'] ) {
						$type = $addon['type'];
						break;
					}
				}
			}

			// Handle value correctly when updating: if it's in the request, test that, otherwise check against stored value.
			if ( ! isset( $all_values['options'] ) ) {
				foreach ( $addons as $addon ) {
					if ( $addon['id'] === $all_values['id'] ) {
						$value = $addon['options'];
						break;
					}
				}
			}
		}
		// Options make sense only for multiple choice and checkbox Add-ons.
		if ( ! in_array( $type, self::$options_valid_for, true ) && ! empty( $value ) ) {
			return new \WP_Error(
				'rest_invalid_param',
				__( 'Options can only be defined/set for "Multiple Choice" and "Checkboxes" add-on types.', 'woocommerce-appointments' )
			);
		}

		// At least one option must be defined for "multiple choice" and "checkbox" Add-Ons.
		if ( in_array( $type, self::$options_valid_for, true ) && empty( $value ) ) {
			return new \WP_Error(
				'rest_invalid_param',
				__( '"Multiple Choice" and "Checkboxes" add-on types require at lease one option.', 'woocommerce-appointments' )
			);
		}

		// Options not required and they're empty -> OK.
		if ( empty( $value ) ) {
			return true;
		}

		// Each option must have a label.
		foreach ( $value as $option ) {
			if ( empty( $option['label'] ) ) {
				return new \WP_Error(
					'rest_invalid_type',
					__( 'Each "option" must have a "label" property defined.', 'woocommerce-appointments' )
				);
			}
		}

		$required = self::get_addon_prop( 'required', $all_values, $addons );
		if ( $required ) {
			$all_options    = count( $value );
			$hidden_options = 0;

			foreach ( $value as $option ) {
				if ( isset( $option['visibility'] ) && false === $option['visibility'] ) {
					++$hidden_options;
				}
			}

			if ( $all_options === $hidden_options ) {
				return new \WP_Error(
					'rest_invalid_type',
					__( 'In required add-ons, there must be at least one visible option.', 'woocommerce-appointments' )
				);
			}
		}

		return true;
	}

	/**
	 * Validate Add-on required option:
	 *  - at least one add-on option must be visible for required add-ons
	 *
	 * This callback will (and can) only be called from Validation::is_array_of_addons(), hence it can have some extra parameters.
	 *
	 * @param array            $value The data to validate.
	 * @param \WP_REST_Request $request Request object.
	 * @param string           $param Current param being validated.
	 * @param array            $schema Field schema.
	 * @param array            $all_values All values.
	 *
	 * @return true|\WP_Error
	 */
	public function validate_required( $value, $request, $param, $schema, $all_values ) {
		$type = null;
		if ( self::creating_object( $all_values ) ) {
			if ( ! isset( $all_values['type'] ) ) {
				return new \WP_Error(
					'rest_invalid_param',
					__( 'Type must be defined when creating an Add-on.', 'woocommerce-appointments' )
				);
			}
			$type   = $all_values['type'];
			$addons = array();
		} else {
			$id = $request->get_param( 'id' );
			if ( \WC_Product_Addons_Api_V2_Global_Group::is_a_global_group_id( $id ) ) {
				$group = new WC_Product_Addons_Api_V2_Global_Group( $id );
			} else {
				$group = new WC_Product_Addons_Api_V2_Product_Group( $id );
			}
			$addons = $group->get_fields();

			// When updating, type doesn't have to be specified in the request -> get it from the object if it's not specified in request.
			if ( isset( $all_values['type'] ) ) {
				$type = $all_values['type'];
			} else {
				// Find the correct Add-on and get type from the add-on.
				foreach ( $addons as $addon ) {
					if ( $addon['id'] === $all_values['id'] ) {
						$type = $addon['type'];
						break;
					}
				}
			}

			// Handle value correctly when updating: if it's in the request, test that, otherwise check against stored value.
			if ( ! isset( $all_values['required'] ) ) {
				foreach ( $addons as $addon ) {
					if ( $addon['id'] === $all_values['id'] ) {
						$value = $addon['required'];
						break;
					}
				}
			}
		}
		// Options make sense only for multiple choice and checkbox Add-ons.
		if ( ! in_array( $type, self::$options_valid_for, true ) ) {
			return true;
		}

		// Not set value is OK.
		if ( '' === $value ) {
			return true;
		}

		// If the add-on is required.
		if ( $value ) {
			$options        = self::get_addon_prop( 'options', $all_values, $addons );
			$all_options    = count( $options );
			$hidden_options = 0;

			foreach ( $options as $option ) {
				if ( isset( $option['visibility'] ) && false === $option['visibility'] ) {
					++$hidden_options;
				}
			}

			if ( $all_options === $hidden_options ) {
				return new \WP_Error(
					'rest_invalid_type',
					__( 'In required add-ons, there must be at least one visible option.', 'woocommerce-appointments' )
				);
			}
		}

		return true;
	}

	/**
	 * Sanitize an empty string or float.
	 *
	 * @param float|string $value The data to sanitize.
	 *
	 * @return float|string
	 */
	public function sanitize_empty_or_float( $value ) {
		if ( '' !== $value ) {
			return (float) wc_format_decimal( $value );
		}

		return '';
	}

	/**
	 * Gets the prop value either from the request or the add-on object.
	 *
	 * @param string $prop        Prop name.
	 * @param array  $all_values  Request values.
	 * @param array  $addons      Product add-ons.
	 *
	 * @return mixed|string
	 */
	public static function get_addon_prop( $prop, $all_values, $addons ) {
		$value = '';

		if ( isset( $all_values[ $prop ] ) ) {
			$value = $all_values[ $prop ];
		} elseif ( ! self::creating_object( $all_values ) ) {
			// Check against stored value.
			foreach ( $addons as $addon ) {
				if ( $addon['id'] === $all_values['id'] ) {
					$value = $addon[ $prop ];
					break;
				}
			}
		}

		return $value;
	}
}
