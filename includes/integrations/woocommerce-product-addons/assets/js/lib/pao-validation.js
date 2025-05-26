/* global woocommerce_addons_params, jQuery */

/**
 * Addons Validation Controller.
 *
 * @param object Form
 */
function PaoValidation( Form ) {

	// Make sure is called as a constructor.
	if ( ! ( this instanceof PaoValidation ) ) {
		return new PaoValidation( Form );
	}

	if ( ! Form.$addons.length ) {
		return false;
	}

	// Holds the jQuery instance.
	this.$form   = Form.$el;
	this.$addons = Form.$addons;
	this.form    = Form;

	// An object that holds the validation state and message of each addon.
	this.validationState = this.getInitialState();

}

/**
 * Gets the initial validation state. All addons are valid in this state.
 */
PaoValidation.prototype.getInitialState = function() {

	var schema = {};

	jQuery.each( this.$addons, function() {
		schema[ jQuery(this).attr( 'id' ) ] = { validity: true, message: '' };
	} );

	return schema;
}

/**
 * Gets the current validation state.
 */
PaoValidation.prototype.getValidationState = function() {
	return this.validationState;
}

/**
 * Validates a single addon and conditionally prints a validation message.
 *
 * @param jQuery object $addon
 * @return bool
 */
PaoValidation.prototype.validateAddon = function( $addon, printMessages = false ) {

	var	validation_rules = $addon.data( 'restrictions' ),
		   id               = $addon.attr( 'id' ),
		   validity         = true;

	// Set default validation state.
   	if ( ! this.validationState[id] ) {
   		this.validationState[id] = { validity: true, message: '' };
   	}

   	// If the addon is hidden, then it is considered valid.
	if ( ! $addon.closest( '.wc-pao-addon-container' ).is(':visible') ) {
   		return this.validationState[id].validity;
   	}

	if ( ! jQuery.isEmptyObject( validation_rules ) ) {

		if ( 'required' in validation_rules ) {
			if ( 'yes' === validation_rules.required ) {
				validity = this.validateRequired( $addon );
			}
		}

		if ( validity && $addon.is( '.wc-pao-addon-custom-price' ) ) {
			validity = this.validateDecimals( $addon );
		}

		if ( validity && 'content' in validation_rules ) {
			if ( 'only_letters' === validation_rules.content ) {
				validity = this.validateLetters( $addon );
			} else if ( 'only_numbers' === validation_rules.content ) {
				validity = this.validateNumbers( $addon );
			} else if ( 'only_letters_numbers' === validation_rules.content ) {
				validity = this.validateLettersNumbers( $addon );
			} else if ( 'email' === validation_rules.content ) {
				validity = this.validateEmail( $addon );
			}
		}

		if ( validity && 'min' in validation_rules ) {
			validity = this.validateMin( $addon, validation_rules.min );
		}

		if ( validity && 'max' in validation_rules ) {
			validity = this.validateMax( $addon, validation_rules.max );
		}
	}

	if ( printMessages ) {
		this.printMessage( $addon );
	}

	return this.validationState[id].validity;
};

/**
 * Validates all addons and conditionally prints validation messages.
 *
 * @return bool
 */
PaoValidation.prototype.validate = function( printMessages = false ) {

	var validity = true,
		self     = this;

	jQuery.each( self.$addons, function() {
		if ( ! self.validateAddon( jQuery(this), printMessages ) ) {
			validity = false;
		}
	});

	return validity;
};

/**
 * Outputs validation message for specific addon.
 * @param jQuery object $addon
 */
PaoValidation.prototype.printMessage = function( $addon ) {

	var id                 = $addon.attr( 'id' ),
		element            = this.$form.find( '#' + id ),
		formattedElementID = id + '-validation-notice',
		message            = this.validationState[id].message;

	// For radio buttons, display a single notice after all radio buttons.
	if ( element.is( ':radio' ) || element.is( ':checkbox' )  ) {

		var $container_element = element.closest( '.wc-pao-addon-container .wc-pao-addon-wrap' );

		$container_element.find( '.wc-pao-validation-notice' ).remove();

		if ( ! this.validationState[id].validity ) {
			$container_element.append( '<small id="' + formattedElementID + '" class="wc-pao-validation-notice">' + message + '</small>' );
		}

		// For the rest addon types, display a notice under each addon.
	} else {
		element.closest( '.wc-pao-addon-container' ).find( '.wc-pao-validation-notice' ).remove();
		if ( ! this.validationState[id].validity ) {
			element.after( '<small id="' + formattedElementID + '" class="wc-pao-validation-notice">' + message.replace( /</g, "&lt;" ).replace( />/g, "&gt;" ) + '</small>' );
		}
	}
};

/**
 * Validates if required addons are configured.
 * @param jQuery object $element
 * @return boolean
 */
PaoValidation.prototype.validateRequired = function( $element ) {

	var validity = true,
		message  = '',
		reason   = '',
		id       = $element.attr( 'id');

	if ( $element.is( ':checkbox' ) || $element.is( ':radio' ) ) {

		var $container_element = $element.closest( '.wc-pao-addon-container' ),
			$options           = $container_element.find( '.wc-pao-addon-field' ),
			self               = this;

		validity = false;

		jQuery.each( $options, function() {
			if ( jQuery( this ).is( ':checked' ) ) {
				validity = true;
				return;
			}
		} );

		if ( ! validity ) {
			message = woocommerce_addons_params.i18n_validation_required_select;
		} else {

			// For groups of options, like radio buttons/checkboxes, if at least 1 option is selected, then consider all options as valid.
			jQuery.each( $options, function() {
				var option_id = jQuery(this).attr( 'id');
				self.validationState[ option_id ] = { validity: validity, message: message, reason: reason };
			} );

			return;
		}

	} else if ( $element.hasClass( 'wc-pao-addon-image-swatch-select' ) ) {
		var $container_element = $element.closest( '.wc-pao-addon-container' );

		validity = false;

		jQuery.each( $container_element.find( '.wc-pao-addon-image-swatch' ), function() {

			if ( jQuery( this ).hasClass( 'selected' ) ) {
				validity = true;
				return;
			}
		} );

		if ( ! validity ) {
			message = woocommerce_addons_params.i18n_validation_required_select;
		}
	} else {

		if ( ! $element.val() ) {
			validity = false;

			if ( 'file' === $element.attr( 'type' ) ) {
				if ( undefined !== $element.data( 'value' ) && '' !== $element.data( 'value' ) ) {
					validity = true;
				} else {
					message = woocommerce_addons_params.i18n_validation_required_file;
				}
			} else if ( 'number' === $element.attr( 'type' ) || $element.is( '.wc-pao-addon-custom-price' ) ) {
				message = woocommerce_addons_params.i18n_validation_required_number;
			} else if ( $element.is( 'input' ) || $element.is( 'textarea' ) ) {
				message = woocommerce_addons_params.i18n_validation_required_input;
			} else if ( $element.is( 'select' ) ) {
				message = woocommerce_addons_params.i18n_validation_required_select;
			}
		}
	}

	if ( ! validity ) {
		reason = 'required';
	}

	this.validationState[id] = { validity: validity, message: message, reason: reason };

	return this.validationState[id].validity;
};

/**
 * Validates if input contains only letters.
 * @param jQuery object $element
 * @return boolean
 */
PaoValidation.prototype.validateLetters = function( $element ) {

	var validity = ! ( /[`!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?~\d]/g.test( $element.val() ) ),
		message  = '',
		reason   = '',
		id       = $element.attr( 'id' );

	if ( ! $element.val() ){
		validity = true;
	}

	if ( ! validity ) {
		message = woocommerce_addons_params.i18n_validation_letters_only;
		reason  = 'letters';
	}

	this.validationState[id] = { validity: validity, message: message, reason: reason };

	return this.validationState[id].validity;

};

/**
 * Validates if input contains only numbers.
 * @param jQuery object $element
 * @return boolean
 */
PaoValidation.prototype.validateNumbers = function( $element ) {

	var validity = /^[0-9]*$/g.test( $element.val() ),
		message  = '',
		reason   = '',
		id       = $element.attr( 'id');

	if ( ! $element.val() ){
		validity = true;
	}

	if ( ! validity ) {
		message = woocommerce_addons_params.i18n_validation_numbers_only;
		reason  = 'numbers';
	}

	this.validationState[id] = { validity: validity, message: message, reason: reason };

	return this.validationState[id].validity;
};

/**
 * Validates if input contains only letters and numbers.
 * @param jQuery object $element
 * @return boolean
 */
PaoValidation.prototype.validateLettersNumbers = function( $element ) {

	var validity = ! ( /[`!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?~]/g.test( $element.val() ) ),
		message  = '',
		reason   = '',
		id       = $element.attr( 'id');

	if ( ! $element.val() ){
		validity = true;
	}

	if ( ! validity ) {
		message = woocommerce_addons_params.i18n_validation_letters_and_numbers_only;
		reason  = 'letters_numbers';
	}

	this.validationState[id] = { validity: validity, message: message, reason: reason };

	return this.validationState[id].validity;

};

/**
 * Validates if input contains a valid email address.
 * @param jQuery object $element
 * @return boolean
 */
PaoValidation.prototype.validateEmail = function( $element ) {

	var validity = /^([a-zA-Z0-9_.+-])+\@(([a-zA-Z0-9-])+\.)+([a-zA-Z0-9]{2,4})+$/.test( $element.val() ),
		message  = '',
		reason   = '',
		id       = $element.attr( 'id');

	if ( ! $element.val() ){
		validity = true;
	}

	if ( ! validity ) {
		message = woocommerce_addons_params.i18n_validation_email_only;
		reason  = 'email';
	}

	this.validationState[id] = { validity: validity, message: message, reason: reason };

	return this.validationState[id].validity;

};

/**
 * Validates if the correct decimal separator is used for Price add-ons.
 * @param jQuery object $element
 * @return boolean
 */
PaoValidation.prototype.validateDecimals = function( $element ) {

	var validity = true,
		message  = '',
		reason   = '',
		id       = $element.attr( 'id' ),
		value    = $element.val(),
		regex    = new RegExp( `^-?\\d+(?:\\${woocommerce_addons_params.currency_format_decimal_sep}\\d+)?$` ); // Only numbers and the decimal separator are allowed.

	if ( ! $element.val() ) {
		validity = true;
	} else if ( ! regex.test( value ) ) {
		validity = false;
		message  = woocommerce_addons_params.i18n_validation_decimal_separator.replace( '%c', woocommerce_addons_params.currency_format_decimal_sep );
		reason   = 'decimals';
	}

	this.validationState[id] = { validity: validity, message: message, reason: reason };

	return this.validationState[id].validity;
}

/**
 * Validates if the min length and min number restrictions are violated.
 * @param jQuery object $element
 * @param int           min
 * @return boolean
 */
PaoValidation.prototype.validateMin = function( $element, min ) {

	var validity = true,
		message  = '',
		reason   = '',
		id       = $element.attr( 'id');

	if ( ! $element.val() ) {
		validity = true;
	} else if ( 'number' === $element.attr( 'type' ) || $element.is( '.wc-pao-addon-custom-price' ) ) {
		var value = $element.val();

		if ( value.includes( '.' ) ) {
			value = parseFloat( value );
		} else if ( value.includes( woocommerce_addons_params.currency_format_decimal_sep ) ) {
			// parseFloat returns an int, if a decimal without a '.' is used.
			value = parseFloat( value.replace( woocommerce_addons_params.currency_format_decimal_sep, '.' ) );
		} else {
			value = parseInt( value );
		}

		if ( value < min ) {
			validity = false;

			if ( $element.is( '.wc-pao-addon-custom-price' ) ) {
				min = accounting.formatNumber( min, {
					symbol: '',
					decimal: woocommerce_addons_params.currency_format_decimal_sep,
					precision: parseFloat( min ) % 1 === 0 ? 0 : min.toString().split( '.' )[ 1 ].length,
				} );
			}
			message = woocommerce_addons_params.i18n_validation_min_number.replace( '%c', min );
		}

	} else if ( 'text' === $element.attr( 'type' ) || $element.is( 'textarea' ) ) {

		if ( $element.val().length < min ) {
			validity = false;
			message  = woocommerce_addons_params.i18n_validation_min_characters.replace( '%c', min );
		}
	}

	if ( ! validity ) {
		reason = 'min';
	}

	this.validationState[id] = { validity: validity, message: message, reason: reason };

	return this.validationState[id].validity;
};

/**
 * Validates if the max length and max number restrictions are violated.
 * @param jQuery object $element
 * @param int           max
 * @return boolean
 */
PaoValidation.prototype.validateMax = function( $element, max ) {

	var validity = true,
		message  = '',
		reason   = reason,
		id       = $element.attr( 'id');

	if ( ! $element.val() ){
		validity = true;
	} else if ( 'number' === $element.attr( 'type' ) || $element.is( '.wc-pao-addon-custom-price' ) ) {
		var value = $element.val();

		if ( value.includes( '.' ) ) {
			value = parseFloat( value );
		} else if ( value.includes( woocommerce_addons_params.currency_format_decimal_sep ) ) {
			// parseFloat returns an int, if a decimal without a '.' is used.
			value = parseFloat( value.replace( woocommerce_addons_params.currency_format_decimal_sep, '.' ) );
		} else {
			value = parseInt( value );
		}

		if ( value > max ) {
			validity = false;

			if ( $element.is( '.wc-pao-addon-custom-price' ) ) {
				max = accounting.formatNumber( max, {
					symbol: '',
					decimal: woocommerce_addons_params.currency_format_decimal_sep,
					precision: parseFloat( max ) % 1 === 0 ? 0 : max.toString().split( '.' )[ 1 ].length
				} );
			}

			message = woocommerce_addons_params.i18n_validation_max_number.replace( '%c', max );
		}

	} else if ( 'text' === $element.attr( 'type' ) || $element.is( 'textarea' ) ) {
		if ( $element.val().length > max ) {
			validity = false;
			message  = woocommerce_addons_params.i18n_validation_max_characters.replace( '%c', max );
		}
	}

	if ( ! validity ) {
		reason = 'max';
	}

	this.validationState[id] = { validity: validity, message: message, reason: reason };

	return this.validationState[id].validity;
};
