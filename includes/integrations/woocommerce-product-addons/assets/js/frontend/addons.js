/* eslint camelcase: [2, {properties: "never"}] */
/* global woocommerce_addons_params, jQuery, accounting */
( function( $, window ) {

	const Validation = PaoValidation;

	// This script is not yet ready to be publicly stored in the window.
	WC_PAO = window.WC_PAO || {};

	WC_PAO.initialized_forms = [];

	WC_PAO.Helper = {

		/**
		 * Escapes HTML.
		 *
		 * @param html
		 * @returns {*}
		 */
		escapeHtml: function( html ) {
			return document
				.createElement( 'div' )
				.appendChild(document.createTextNode( html ) ).parentNode
				.innerHTML;
		},

		/**
		 * Determines if a subscription is selected in a grouped product.
		 *
		 * @returns {boolean}
		 */
		isGroupedSubsSelected: function () {
			var group = $( '.product-type-grouped' ),
				subs  = false;

			if ( group.length ) {
				group.find( '.group_table tr.product' ).each( function () {
					if ( 0 < $(this).find( '.input-text.qty' ).val() ) {
						if (
							$(this).find( '.entry-summary .subscription-details' )
								.length
						) {
							subs = true;
							return false;
						}
					}
				});
			}

			return subs;
		},

		/**
		 * Function to add minutes on a given Date object.
		 *
		 * @param  {Date} date The Date object to be converted.
		 * @param  {integer} minutes Number of minutes.
		 * @return {Date} The new Date including added minutes.
		 */
		addMinutes: function( date, minutes ) {
			return new Date( date.getTime() + minutes * 60000 );
		},

		/**
		 * Determines if a product is a mixed or grouped product type.
		 *
		 * @returns {boolean}
		 */
		isGroupedMixedProductType: function() {
			var group  = $( '.product-type-grouped' ),
				subs   = 0,
				simple = 0;

			if ( group.length ) {
				group.find( '.group_table tr.product' ).each( function () {
					if ( 0 < $(this).find( '.input-text.qty' ).val() ) {
						// For now only checking between simple and subs.
						if (
							$(this).find( '.entry-summary .subscription-details' )
								.length
						) {
							subs++;
						} else {
							simple++;
						}
					}
				});

				if ( 0 < subs && 0 < simple ) {
					return true;
				}
			}

			return false;
		},

		/**
		 * Delays the execution of the callback function by ms.
		 *
		 * @param callback
		 * @param ms
		 */
		delay: function( callback, ms ) {
			var timer = 0;

			clearTimeout( timer );
			timer = setTimeout( callback, ms );
		},
	};

	WC_PAO.Form = ( function () {

		/**
		 * Addons Form Controller.
		 *
		 * @param object $form
		 */
		function Form( $form ) {
			// Make sure is called as a constructor.
			if ( ! ( this instanceof Form ) ) {
				return new Form( $form );
			}

			if ( ! $form.length ) {
				return false;
			}

			// Holds the jQuery instance.
			this.$el     = $form;
			this.$addons = this.$el.find( '.wc-pao-addon-field' );

			if ( ! this.$addons.length ) {
				this.$addons = false;
				return false;
			}

			this.is_rtl                    = 'rtl' === document.documentElement.dir;
			this.validation                = new Validation( this );
			this.totals                    = new Totals( this );
			this.show_incomplete_subtotals = this.totals.showIncompleteSubtotals();
			this.contains_required         = this.containsRequired();

			this.setupEvents();

			this.validation.validate();
			this.updateTotals();

			$( '.wc-pao-addon-image-swatch' ).tipTip({ delay: 200 } );

			WC_PAO.initialized_forms.push( this );
		}

		/**
		 * Sets up event listeners.
		 */
		Form.prototype.setupEvents = function() {

			var self = this;

			// Validate addons on form submit.
			self.$el.find( 'button[type="submit"]' ).on( 'click', function () {

				if ( self.validation.validate( true ) ) {
					return true;
				}

				// Scroll viewport to the first invalid configured addon, if it not currently in viewport.
				var $messages = self.$el.find( '.wc-pao-validation-notice' );

				if( $messages.length > 0 ) {
					var $first_invalid_addon = self.$el.find( $messages[0].closest( '.wc-pao-addon-container' ) );

					if ( $first_invalid_addon.length > 0 && ! self.is_in_viewport( $first_invalid_addon ) ) {
						$first_invalid_addon[0].scrollIntoView();
					}
				}

				return false;
			});

			/**
			 * Addons value changed.
			 */
			self.$el.on(
				'blur change',
				'.wc-pao-addon input:not(.wc-pao-addon-file-upload), .wc-pao-addon textarea, .wc-pao-addon select, .wc-pao-addon-custom-text',
				function () {
					self.validation.validateAddon( $( this ), true );
					self.updateTotals();
				}
			);

			// Treat file fields slightly differently since they can have reset links.
			self.$el.on(
				'change',
				'.wc-pao-addon input.wc-pao-addon-file-upload',
				function () {
					const $this = $( this );
					const $parent = $this.closest('.wc-pao-addon-container');
					const $reset = $parent.find( '.reset_file' );
					const $selected_file = $parent.find( '.wc-pao-addon-file-name' );

					// When a new file is uploaded, reveal the reset button.
					if ($reset.length) {
						$reset.addClass( 'active' );
					}

					// When the add-on field is pre-populated from a cart item, the selected file is displayed
					// as a note below the input. When a new file is selected, clear the selected file name.
					if ( $selected_file.length ) {
						$selected_file.hide( 250, function() {
							// Remove note with the name of the selected file.
							$( this ).remove();

							// Clear the file upload input field.
							$this.data( 'value', '' );
						} );
					}

					self.validation.validateAddon( $this, true );
					self.updateTotals();
				}
			);

			self.$el.on(
				'keyup',
				'.wc-pao-addon input, .wc-pao-addon textarea, .wc-pao-addon-custom-text',
				function () {
					var $addon = $(this);

					WC_PAO.Helper.delay( function() {
						self.validation.validateAddon( $addon, true );
						self.updateTotals();
					}, 300 );
				}
			);

			// Product quantity changed.
			self.$el.on(
				'change',
				'input.qty',
				function () {
					self.updateTotals();
				}
			);

			// Special handling for image swatches.
			// When a user clicks on an image swatch, the selection is transferred to a hidden select element.
			var touchTime;

			self.$el.on(
				'touchstart',
				'.wc-pao-addon-image-swatch',
				function (e) {
					touchTime = new Date();
				}
			);

			self.$el.on(
				'click touchend',
				'.wc-pao-addon-image-swatch',
				function (e) {
					e.preventDefault();

					if ( 'touchend' === e.type && touchTime ) {
						var diff = new Date() - touchTime;

						if ( diff > 100 ) {
							// This is a scroll event and not a tap, so skip.
							return;
						}
					}

					var selected_value = $(this).data( 'value' ),
						$parent        = $(this).parents( '.wc-pao-addon-wrap' ),
						label          = $.parseHTML( $(this).data( 'price' ) ),
						$selected      = $parent.find( '.wc-pao-addon-image-swatch-selected-swatch' );

					// Clear selected swatch.
					$selected.html( '' );

					// Clear all selected.
					$parent
						.find( '.wc-pao-addon-image-swatch' )
						.removeClass( 'selected' );

					// Select this swatch.
					$(this).addClass( 'selected' );

					// Set the value in hidden select field.
					$parent
						.find( '.wc-pao-addon-image-swatch-select' )
						.val( selected_value );

					// Display selected label below swatches.
					$selected.html( label );

					self.validation.validateAddon( $parent.find( 'select.wc-pao-addon-field' ), true );
					self.updateTotals();
				}
			);

			/**
			 * Variable Products.
			 */

			// Reset addon totals when the variation selection is cleared. The form is not valid until a variation is selected.
			self.$el.on( 'click', '.reset_variations', function () {
				self.totals.reset();
			});

			// When the variation form initially loads.
			self.$el.on( 'wc_variation_form', function () {
				self.validation.validate();
				self.updateTotals();
			});

			// When a new variation is selected, validate the form and update the addons totals.
			self.$el.on( 'found_variation', function ( event, variation ) {
				self.totals.updateVariation( variation );
				self.validation.validate();
				self.updateTotals();
			});

			// When a variation selection is cleared by selecting "Choose an option...", reset totals as the form becomes invalid.
			self.$el.on( 'hide_variation', function ( event ) {
				self.updateTotals();
			});

			self.$el.on( 'woocommerce-product-addons-update', function () {
				self.validation.validate();
				self.updateTotals();
			});

			/**
			 * Add-ons Datepicker extend.
			 */
			var $datepickers = self.$el.find( '.datepicker' );

			$datepickers.each( function() {

				// Cache local instances.
				var $datepicker       = $( this ),
					$container        = $datepicker.parent(),
					$clear_button     = $container.find( '.reset_date' ),
					$timestamp_input  = $container.find( 'input[name="' + $datepicker.attr( 'name' ) + '-wc-pao-date"]' ),
					$offset_gmt_input = $container.find( 'input[name="' + $datepicker.attr( 'name' ) + '-wc-pao-date-gmt-offset"]' );

				// Make Template backwards compatible.
				if ( ! $offset_gmt_input.length ) {
					$offset_gmt_input = $( '<input/>' );
					$offset_gmt_input.attr( 'type', 'hidden' );
					$offset_gmt_input.attr( 'name', $datepicker.attr( 'name' ) + '-wc-pao-date-gmt-offset' );
					$container.append( $offset_gmt_input );
				}

				// Fill GMT offset.
				var now           = new Date(),
					gmt_offset    = parseFloat( woocommerce_addons_params.gmt_offset, 10 ),
					client_offset = now.getTimezoneOffset() / 60,
					diff          = client_offset - gmt_offset;

				if ( 'default' === woocommerce_addons_params.date_input_timezone_reference ) {

					$offset_gmt_input.val( client_offset );
				} else if ( 'store' === woocommerce_addons_params.date_input_timezone_reference ) {

					var hours_now  = now.getHours() + now.getMinutes() / 60,
						day_factor = hours_now + diff;

					$offset_gmt_input.val( gmt_offset );
				}

				// Init datepicker.
				$datepicker.datepicker( {
					beforeShow: function( input, el ) {
						if ( woocommerce_addons_params.datepicker_class ) {
							$('#ui-datepicker-div').removeClass( woocommerce_addons_params.datepicker_class );
							$('#ui-datepicker-div').addClass( woocommerce_addons_params.datepicker_class );
						}
					},
					dateFormat: woocommerce_addons_params.datepicker_date_format,
					changeMonth: true,
					changeYear: true,
					yearRange: "c-100:c+10",
				} );

				// Fill hidden inputs with selected date if any.
				var currentDate = $datepicker.datepicker( 'getDate' );
				if ( null !== currentDate && typeof currentDate.getTime === 'function' ) {

					// Append current time.
					currentDate.setHours( now.getHours(), now.getMinutes() );

					if ( 'store' === woocommerce_addons_params.date_input_timezone_reference ) {
						currentDate = WC_PAO.Helper.addMinutes( currentDate, -1 * client_offset * 60 );
						currentDate = WC_PAO.Helper.addMinutes( currentDate, gmt_offset * 60 );
					}

					$timestamp_input.val( currentDate.getTime() / 1000 );
					$clear_button.show();
				}

				// On Change.
				$datepicker.on( 'change', function() {

					var selectedDate = $datepicker.datepicker( 'getDate' );
					if ( null !== selectedDate && typeof selectedDate.getTime === 'function' ) {

						// Append current time.
						var now = new Date();
						selectedDate.setHours( now.getHours(), now.getMinutes() );

						if ( 'store' === woocommerce_addons_params.date_input_timezone_reference ) {
							selectedDate = WC_PAO.Helper.addMinutes( selectedDate, -1 * client_offset * 60 );
							selectedDate = WC_PAO.Helper.addMinutes( selectedDate, gmt_offset * 60 );
						}

						$timestamp_input.val( selectedDate.getTime() / 1000 );
						$clear_button.show();

					} else {
						$clear_button.hide();
						$timestamp_input.val( '' );
					}

				} );

				// On clear date.
				$clear_button.on( 'click', function( event ) {
					event.preventDefault();
					// Sanity clear.
					$timestamp_input.val( '' );
					// Trigger change.
					$datepicker.val( '' ).trigger( 'change' );
				} );

			} );

			self.$el.on( 'click', '.reset_file', function( e ) {
				e.preventDefault();
				const $parent = $( this ).closest('.wc-pao-addon-container');
				const $file_field = $parent.find( 'input.wc-pao-addon-field' );
				const $selected_file = $parent.find( '.wc-pao-addon-file-name' );

				// When the add-on field is pre-populated from a cart item, the selected file is displayed
				// as a note below the input. When the reset link is clicked, also clear the selected file name.
				if ( $selected_file.length ) {
					$selected_file.hide( 250, function() {
						// Remove note with the name of the selected file.
						$( this ).remove();
					} );
				}

				// Remove the reset link.
				$( this ).removeClass( 'active' ).addClass( 'inactive' );

				// Clear the file upload input.
				$file_field.data( 'value', '' );
				$file_field.val( '' );

				// Validate add-on and re-calculate totals.
				self.validation.validateAddon( $file_field, true );
				self.updateTotals();
			} );

			/**
			 * Integrations.
			 */

			// Compatibility with Smart Coupons self declared gift amount purchase.
			//
			// CAUTION: This code is unstable.
			$( '#credit_called' ).on( 'keyup', function () {
				self.validation.validate();
				self.updateTotals();
			});
		}

		/**
		 * Updates addons totals if the form is valid or resets them otherwise.
		 */
		Form.prototype.updateTotals = function() {

			this.totals.calculate();

			// Hide totals the product contains only optional items and none have been selected or when the form is invalid.
			var display_totals = ( this.show_incomplete_subtotals || this.isValid() ) && ( this.contains_required || this.totals.$totals.data( 'price_data' ).length );

			if ( display_totals ) {
				this.totals.render();
			} else {
				this.totals.reset()
			}
		}

		/**
		 * Determines if the form is valid.
		 * @returns boolean
		 */
		Form.prototype.isValid = function() {

			var valid               = true,
				$add_to_cart_button = this.$el.find( 'button.single_add_to_cart_button' );

			if ( $add_to_cart_button.is( '.disabled' ) ) {
				valid = false;
				return valid;
			}

			$.each( this.validation.getValidationState(), function() {

				if ( ! this.validity ) {
					valid = false;
					return false;
				}
			});

			return valid;
		}

		/**
		 * Determines if the product contains required add-ons
		 * @returns boolean
		 */
		Form.prototype.containsRequired = function() {

			var contains_required = false;

			this.$addons.each( function () {
				var	validation_rules  = $(this).data( 'restrictions' )

				if ( ! $.isEmptyObject( validation_rules ) ) {

					if ( 'required' in validation_rules ) {
						if ( 'yes' === validation_rules.required ) {
							return contains_required = true;
						}
					}
				}
			} );

			return contains_required;
		}

		/**
		 * Element-in-viewport check with partial element detection & direction support.
		 * Credit: Sam Sehnert - https://github.com/customd/jquery-visible
		 */
		Form.prototype.is_in_viewport = function( element, partial, hidden, direction ) {

			var $w = $( window );

			if ( element.length < 1 ) {
				return;
			}

			var $t         = element.length > 1 ? element.eq(0) : element,
				t          = $t.get(0),
				vpWidth    = $w.width(),
				vpHeight   = $w.height(),
				clientSize = hidden === true ? t.offsetWidth * t.offsetHeight : true;

			direction = (direction) ? direction : 'vertical';

			if ( typeof t.getBoundingClientRect === 'function' ) {

				// Use this native browser method, if available.
				var rec      = t.getBoundingClientRect(),
					tViz     = rec.top    >= 0 && rec.top    <  vpHeight,
					bViz     = rec.bottom >  0 && rec.bottom <= vpHeight,
					mViz     = rec.top    <  0 && rec.bottom >  vpHeight,
					lViz     = rec.left   >= 0 && rec.left   <  vpWidth,
					rViz     = rec.right  >  0 && rec.right  <= vpWidth,
					vVisible = partial ? tViz || bViz || mViz : tViz && bViz,
					hVisible = partial ? lViz || rViz : lViz && rViz;

				if ( direction === 'both' ) {
					return clientSize && vVisible && hVisible;
				} else if ( direction === 'vertical' ) {
					return clientSize && vVisible;
				} else if ( direction === 'horizontal' ) {
					return clientSize && hVisible;
				}

			} else {

				var viewTop       = $w.scrollTop(),
					viewBottom    = viewTop + vpHeight,
					viewLeft      = $w.scrollLeft(),
					viewRight     = viewLeft + vpWidth,
					offset        = $t.offset(),
					_top          = offset.top,
					_bottom       = _top + $t.height(),
					_left         = offset.left,
					_right        = _left + $t.width(),
					compareTop    = partial === true ? _bottom : _top,
					compareBottom = partial === true ? _top : _bottom,
					compareLeft   = partial === true ? _right : _left,
					compareRight  = partial === true ? _left : _right;

				if ( direction === 'both' ) {
					return !!clientSize && ( ( compareBottom <= viewBottom ) && ( compareTop >= viewTop ) ) && ( ( compareRight <= viewRight ) && ( compareLeft >= viewLeft ) );
				} else if ( direction === 'vertical' ) {
					return !!clientSize && ( ( compareBottom <= viewBottom ) && ( compareTop >= viewTop ) );
				} else if ( direction === 'horizontal' ) {
					return !!clientSize && ( ( compareRight <= viewRight ) && ( compareLeft >= viewLeft ) );
				}
			}
		};

		/**
		 * Addons Totals Controller.
		 *
		 * @param object Form
		 */
		function Totals( Form ) {
			// Make sure is called as a constructor.
			if ( ! ( this instanceof Totals ) ) {
				return new Totals( Form );
			}

			if ( $.isEmptyObject( Form ) ) {
				return false;
			}

			// Holds the jQuery instance.
			this.$form   = Form.$el;
			this.$addons = Form.$addons;

			// Parameters.
			this.$variation_input = this.$form.hasClass( 'variations_form' )
				? this.$form.find(
					'input[name="variation_id"], input.variation_id'
				)
				: false,
			this.is_variable      = this.$variation_input && this.$variation_input.length > 0,
			this.$totals          = this.$form.find( '#product-addons-total' ),
			this.product_id       = this.is_variable ? this.$variation_input.val() : this.$totals.data( 'product-id' );

			if ( ! this.product_id ) {
				return false;
			}

			// The product base price. For Variable Products, this is the minimum variation price.
			this.base_price             = this.$totals.data( 'price' ),
			this.raw_price              = this.$totals.data( 'raw-price' ),
			this.product_type           = this.$totals.data( 'type' ),
			this.qty                    = parseFloat( this.$form.find( 'input.qty' ).val() ),
			this.addons_price_data      = [];
			this.$subscription_plans    = this.$form.find( '.wcsatt-options-product' ),
			this.has_subscription_plans = this.$subscription_plans.length > 0;
			this.is_rtl                 = Form.is_rtl;
			this.total                  = 0;
			this.total_raw              = 0;
			this.show_subtotal_panel    = true;
			this.price_request          = null;
		}

		/**
		 * Determines if addons subtotals should be visible even if validation fails.
		 *
		 * @returns boolean
		 */
		Totals.prototype.showIncompleteSubtotals = function() {
			return this.$totals.data( 'show-incomplete-sub-total' ) === 1;
		}

		/**
		 * Update addon totals when a new variation is selected.
		 *
		 * @param variation
		 */
		Totals.prototype.updateVariation = function( variation ) {

			// Handle multiple variation dropdowns in a single form -- for example, a Bundle with many Variable bundled items.
			this.$variation_input = this.$form.hasClass( 'variations_form' )
				? this.$form.find(
					'input[name="variation_id"], input.variation_id'
				)
				: false;
			this.product_id       = variation.variation_id;

			this.$totals.data( 'product-id', this.product_id );

			if ( typeof variation.display_price !== 'undefined' ) {
				this.base_price = variation.display_price;
			} else if (
				$( variation.price_html ).find( '.amount' ).last().length
			) {

				this.base_price = $( variation.price_html )
					.find( '.amount' )
					.last()
					.text();

				this.base_price = this.base_price.replace(
					woocommerce_addons_params.currency_format_symbol,
					''
				);

				this.base_price = this.base_price.replace(
					woocommerce_addons_params.currency_format_thousand_sep,
					''
				);

				this.base_price = this.base_price.replace(
					woocommerce_addons_params.currency_format_decimal_sep,
					'.'
				);

				this.base_price = this.base_price.replace( /[^0-9\.]/g, '' );
				this.base_price = parseFloat( this.base_price );

			}

			this.$totals.data( 'price', this.base_price );
		};

		/**
		 * Calculates addon totals based on configured addons.
		 */
		Totals.prototype.calculate = function() {

			var self = this;

			self.qty               = parseFloat( self.$form.find( 'input.qty' ).val() );
			self.addons_price_data = [];
			self.total             = 0;
			self.total_raw         = 0;
			self.base_price        = self.$totals.data( 'price' );
			self.raw_price         = self.$totals.data( 'raw-price' );
			self.product_id        = self.is_variable ? self.$variation_input.val() : self.$totals.data( 'product-id' );

			/**
			 * Compatibility with Smart Coupons self declared gift amount purchase.
			 *
			 * CAUTION: This code is unstable.
			 * A dedicated Smart Coupons event should be used to change the base price based on the gift card amount.
			 */
			if (
				'' === self.base_price &&
				'undefined' !== typeof custom_gift_card_amount &&
				custom_gift_card_amount.length &&
				0 < custom_gift_card_amount.val()
			) {
				self.base_price = custom_gift_card_amount.val();
			}

			/**
			 * Compatibility with Bookings.
			 *
			 * CAUTION: This code is unstable.
			 * A dedicated Bookings event should be used to change the base price based on the bookings cost.
			 */
			if (
				woocommerce_addons_params.is_bookings &&
				$( '.wc-bookings-booking-cost' ).length
			) {
				self.base_price = parseFloat(
					$( '.wc-bookings-booking-cost' ).attr(
						'data-raw-price'
					)
				);
			}

			/**
			 * Calculates totals of selected addons.
			 *
			 */
			this.$addons.each( function () {

				var $addon                 = $( this ),
					parent_container       = $addon.parents( '.wc-pao-addon' );

				if (
					(
						$addon.is( '.wc-pao-addon-file-upload' ) &&
						parent_container.find( '.wc-pao-addon-file-name input' ).length &&
						! parent_container.find( '.wc-pao-addon-file-name input' ).val()
					) || (
						! (
							$addon.is( '.wc-pao-addon-file-upload' ) &&
							parent_container.find( '.wc-pao-addon-file-name input' ).length
						) &&
						! $addon.val()
					)
				) {
					return;
				}

				var name                   = parent_container.find( '.wc-pao-addon-name' )
						.length
						? parent_container
							.find( '.wc-pao-addon-name' )
							.data( 'addon-name' )
						: '',
					value_label            = '',
					addon_cost             = 0,
					addon_cost_raw         = 0,
					price_type             = $addon.data( 'price-type' ),
					addon_duration         = 0, //appointments
					addon_duration_raw     = 0, //appointments
					duration_type          = $addon.data( 'duration-type' ), //appointments
					is_custom_price        = false,
					addon_data             = {},
					has_per_person_pricing = parent_container.find(
						'.wc-pao-addon-name'
					).length
						? parent_container
							.find( '.wc-pao-addon-name' )
							.data( 'has-per-person-pricing' )
						: false,
					has_per_block_pricing  = parent_container.find(
						'.wc-pao-addon-name'
					).length
						? parent_container
							.find( '.wc-pao-addon-name' )
							.data( 'has-per-block-pricing' )
						: false;

				if ( $addon.is( '.wc-pao-addon-custom-price' ) ) {

					is_custom_price    = true;
					addon_cost         = $addon.val();
					addon_cost_raw     = $addon.val();
					price_type         = 'quantity_based';

					// Replace decimal separator with '.', as parseFloat rejects decimals with comma.
					if ( '.' !== woocommerce_addons_params.currency_format_decimal_sep ) {
						addon_cost     = addon_cost.replace( woocommerce_addons_params.currency_format_decimal_sep, '.' );
						addon_cost_raw = addon_cost_raw.replace( woocommerce_addons_params.currency_format_decimal_sep, '.' );
					}

				} else if (
					$addon.is( '.wc-pao-addon-input-multiplier' )
				) {
					// Avoid converting empty strings to 0.
					if ( '' !== $addon.val() ) {
						$addon.val( Math.ceil( $addon.val() ) );

						addon_cost         = $addon.data( 'price' ) * $addon.val();
						addon_cost_raw     = $addon.data('raw-price') * $addon.val();
						addon_duration     = $addon.data( 'duration' ) * $addon.val(); //appointments
						addon_duration_raw = $addon.data( 'raw-duration' ) * $addon.val(); //appointments
					}
				} else if (
					$addon.is(
						'.wc-pao-addon-checkbox, .wc-pao-addon-radio'
					)
				) {

					if ( ! $addon.is( ':checked' ) ) {
						return;
					}

					value_label        = $addon.data( 'label' );
					addon_cost         = $addon.data( 'price' );
					addon_cost_raw     = $addon.data('raw-price');
					addon_duration     = $addon.data( 'duration' ); //appointments
					addon_duration_raw = $addon.data( 'raw-duration' ); //appointments
				} else if (
					$addon.is(
						'.wc-pao-addon-image-swatch-select, .wc-pao-addon-select'
					)
				) {

					if (
						! $addon.find( 'option:selected' ) ||
						'' === $addon.find( 'option:selected' ).val()
					) {
						return;
					}

					price_type         = $addon.find( 'option:selected' ).data( 'price-type' );
					duration_type      = $addon.find( 'option:selected' ).data( 'duration-type' ); //appointments
					value_label        = $addon.find( 'option:selected' ).data( 'label' );
					addon_cost         = $addon.find( 'option:selected' ).data( 'price' );
					addon_cost_raw     = $addon.find( 'option:selected' ).data( 'raw-price' );
					addon_duration     = $addon.find( 'option:selected' ).data( 'duration' ); //appointments
					addon_duration_raw = $addon.find( 'option:selected' ).data( 'raw-duration' ); //appointments

				} else if (
						$addon.is(
							'.wc-pao-addon-file-upload'
						)
				) {
					addon_cost     = $addon.data('price');
					addon_cost_raw = $addon.data('raw-price');
				} else {

					if ( ! $addon.val() ) {
						return;
					}

					addon_cost         = $addon.data('price');
					addon_cost_raw     = $addon.data('raw-price');
					addon_duration     = $addon.data( 'duration' ); //appointments
					addon_duration_raw = $addon.data( 'raw-duration' ); //appointments
				}

				if ( ! addon_cost ) {
					addon_cost = 0;
				}
				if ( ! addon_cost_raw ) {
					addon_cost_raw = 0;
				}
				if ( ! addon_duration ) { //appointments
					addon_duration = 0;
				}
				if ( ! addon_duration_raw ) { //appointments
					addon_duration_raw = 0;
				}

				/**
				 * Compatibility with Bookings.
				 *
				 * CAUTION: This code is unstable.
				 * A dedicated Bookings/Accomodation Bookings event should be used to change the base price based on the bookings duration, persons and cost.
				 */
				if (
					( 'booking' === self.product_type ||
						'accommodation-booking' === self.product_type ) &&
					woocommerce_addons_params.is_bookings
				) {
					self.qty = 0;

					// Duration field.
					var block_qty = 0;
					if (
						'undefined' !==
						typeof $( '#wc_bookings_field_duration' ) &&
						0 < $( '#wc_bookings_field_duration' ).val()
					) {
						block_qty = $(
							'#wc_bookings_field_duration'
						).val();
					}

					// Duration fields with start and end time.
					if (
						'undefined' !==
						typeof $( '#wc-bookings-form-end-time' ) &&
						0 < $( '#wc-bookings-form-end-time' ).val()
					) {
						block_qty = $(
							'#wc-bookings-form-end-time'
						).val();
					}

					// Persons field(s).
					var single_persons_input = $( '#wc_bookings_field_persons' ),
						person_qty           = 0;

					if ( 1 === single_persons_input.length ) {
						// Persons field when person types is disabled.
						person_qty =
							parseInt( person_qty, 10 ) +
							parseInt( single_persons_input.val(), 10 );
					} else {
						// Persons fields for multiple person types.
						$( '.wc-bookings-booking-form' )
							.find( 'input' )
							.each( function () {
								// There could be more than one persons field.
								var field = this.id.match(
									/wc_bookings_field_persons_(\d+)/
								);

								if (
									null !== field &&
									'undefined' !== typeof field &&
									$( '#' + field[0] ).length
								) {
									person_qty =
										parseInt( person_qty, 10 ) +
										parseInt(
											$( '#' + field[0]).val(),
											10
										);
								}
							});
					}

					if (
						0 === self.qty &&
						$( '.wc-bookings-booking-cost' ).length
					) {
						self.qty = 1;
					}

					// Apply person/block quantities.
					if ( has_per_person_pricing && person_qty ) {
						self.qty *= person_qty;
					}
					if ( has_per_block_pricing && block_qty ) {
						self.qty *= block_qty;
					}
				}

				// Format addon totals based on their type.
				switch ( price_type ) {
					case 'flat_fee':
						addon_data.cost     = parseFloat( addon_cost );
						addon_data.cost_raw = parseFloat( addon_cost_raw );
						break;
					case 'quantity_based':
						addon_data.cost_pu     = parseFloat( addon_cost );
						addon_data.cost_raw_pu = parseFloat( addon_cost_raw );
						addon_data.cost        = addon_data.cost_pu * self.qty;
						addon_data.cost_raw    = addon_data.cost_raw_pu * self.qty;
						break;
					case 'percentage_based':
						addon_data.cost_pct     = parseFloat( addon_cost ) / 100;
						addon_data.cost_raw_pct = parseFloat( addon_cost_raw ) / 100;
						addon_data.cost         =
							parseFloat( self.base_price ) *
							addon_data.cost_pct *
							self.qty;
						addon_data.cost_raw     =
							parseFloat( self.raw_price ) *
							addon_data.cost_raw_pct *
							self.qty;
						break;
				}

				self.total     += addon_data.cost || 0;
				self.total_raw += addon_data.cost_raw || 0;

				switch ( duration_type ) { //appointments
					case 'flat_time':
					case '':
						addon_data.duration = addon_duration_raw;
						addon_data.duration_raw = addon_duration_raw;
						break;
					case 'quantity_based':
						addon_data.duration_pu = addon_duration_raw;
						addon_data.duration_raw_pu = addon_duration_raw;
						addon_data.duration = addon_data.duration_pu * self.qty;
						addon_data.duration_raw = addon_data.duration_raw_pu * self.qty;
						break;
				}

				/**
				 * Formats addon names to include user input.
				 * The formatted addon name will be displayed in the addons subtotal table.
				 */
				if ( 'undefined' !== typeof value_label ) {
					if (
						'number' === typeof value_label ||
						value_label.length
					) {
						addon_data.name =
							name +
							( value_label ? ' - ' + value_label : '' );

						addon_data.nameFormattedHTML =
							'<span class="wc-pao-addon-name">' +
							name +
							'</span>' +
							( value_label ? ' - ' + '<span class="wc-pao-addon-value">' + value_label + '</span>' : '' );
					} else {
						var user_input     = $addon.val(),
							trimCharacters = parseInt(
								woocommerce_addons_params.trim_user_input_characters,
								10
							);

						// Check if type is file upload.
						if ( $addon.is( '.wc-pao-addon-file-upload' ) ) {
							if ( parent_container.find( '.wc-pao-addon-file-name' ).length ) {
								user_input = parent_container.find( '.wc-pao-addon-file-name' ).data('value');
							}
							user_input = user_input.replace(
								/^.*[\\\/]/,
								''
							);
						} else if ( $addon.is( '.wc-pao-addon-custom-price' ) ) {
							// Replace decimal separator with '.', as formatMoney expects decimals with '.'.
							if ( '.' !== woocommerce_addons_params.currency_format_decimal_sep ) {
								user_input = user_input.replace( woocommerce_addons_params.currency_format_decimal_sep, '.' );
							}

							user_input = accounting.formatNumber( user_input, {
								symbol: '',
								decimal: woocommerce_addons_params.currency_format_decimal_sep,
								precision: parseFloat( user_input ) % 1 === 0 ? 0 : user_input.toString().split( '.' )[ 1 ].length,
							} );
						}

						if ( trimCharacters < user_input.length ) {
							user_input =
								user_input.slice( 0, trimCharacters ) +
								'...';
						}

						addon_data.name =
							name +
							' - ' +
							WC_PAO.Helper.escapeHtml( user_input );

						addon_data.nameFormattedHTML =
							'<span class="wc-pao-addon-name">' +
							name +
							'</span>' +
							' - ' +
							'<span class="wc-pao-addon-value">' +
							WC_PAO.Helper.escapeHtml( user_input ) +
							'</span>';
					}

					addon_data.is_custom_price = is_custom_price;
					addon_data.price_type      = price_type;
					addon_data.duration_type   = duration_type; //appointments

					self.addons_price_data.push( addon_data );
				}
			});

			// Save prices for 3rd party access.
			self.$totals.data( 'price_data', self.addons_price_data );
			self.$form.trigger( 'updated_addons' );
		};

		/**
		 * Renders addon totals.
		 */
		Totals.prototype.render = function() {

			var self = this;

			// Early exit if another plugin has determined that Product Addon totals should remain hidden.
			if ( ! self.$totals.data( 'show-sub-total' ) ) {
				self.$totals.empty();
				self.$form.trigger( 'updated_addons' );
				return;
			}

			if ( self.qty ) {

				var product_total_price,
					formatted_sub_total,
					$subscription_details,
					subscription_details_html,
					html,
					formatted_addon_total       = self.formatMoney( self.total ),
					has_custom_price_with_taxes = false;

				if ( 'undefined' !== typeof self.base_price && self.product_id ) {
					// If it is a bookable product.
					if ( $( '.wc-bookings-booking-form' ).length ) {
						product_total_price = ! isNaN( self.base_price ) ? parseFloat( self.base_price ) : 0;
					} else if ( $( '.wc-appointments-appointment-cost' ).length ) { //appointments
						product_total_price = ! isNaN( self.base_price ) ? parseFloat( self.base_price ) : 0;
					} else {
						product_total_price = parseFloat( self.base_price * self.qty );
					}

					formatted_sub_total = self.formatMoney( product_total_price + self.total );
				}

				/**
				 * Compatibility with Subscribe All The Things/All Products for WooCommerce Subscriptions.
				 *
				 * CAUTION: This code is unstable.
				 * An All Products for WooCommerce Subscriptions specific event should be used to get
				 * subscription details when a new subscription plan is selected.
				 */
				if ( self.has_subscription_plans ) {
					var satt = self.$form.data( 'satt_script' );

					if ( satt && satt.schemes_model.get_active_scheme_key() ) {
						var $selected_plan = self.$subscription_plans.find( 'input:checked' );

						if ( $selected_plan.val() ) {
							$subscription_details = $selected_plan.parent().find( '.subscription-details' );
						}
					}
				} else if ( self.$form.parent().find( '.subscription-details' ).length ) {
					// Add-Ons added at bundle level only affect the up-front price.
					if ( ! self.$form.hasClass( 'bundle_data' ) ) {
						$subscription_details = self.$form.parent().find( '.subscription-details' );

						/*
						 * Check if product is a variable
						 * because the subscription_details HTML element will
						 * be located in different area.
						 */
						if ( self.$variation_input && self.$variation_input.length > 0 ) {
							$subscription_details = self.$form.parent().find( '.woocommerce-variation .subscription-details' );
						}
					}
				}

				if ( $subscription_details && $subscription_details.length > 0 ) {
					// Space is needed here in some cases.
					subscription_details_html =
						' ' +
						$subscription_details
							.clone()
							.wrap( '<p>' )
							.parent()
							.html();
				}

				/**
				 * Compatibility with Grouped and subscription products.
				 *
				 * CAUTION: This code is unstable.
				 * This code needs to be moved to a grouped/subscription-specific function.
				 */
				if ( 'grouped' === self.product_type ) {
					if ( subscription_details_html && ! WC_PAO.Helper.isGroupedMixedProductType() && WC_PAO.Helper.isGroupedSubsSelected() ) {
						formatted_addon_total += subscription_details_html;

						if ( formatted_sub_total ) {
							formatted_sub_total += subscription_details_html;
						}
					}
				} else if ( subscription_details_html ) {
					if ( formatted_sub_total ) {
						formatted_sub_total += subscription_details_html;
					}
				}

				/**
				 * Render addon subtotals in a table-like format above the Add to Cart button.
				 * As the first line item, display the main product followed by each total price (base price * quantity).
				 * Then, display one line item for each selected addon followed by each price (given that one exists).
				 */
				if ( formatted_sub_total ) {
					var product_name       = self.$form.find( '.wc-pao-addon-container' ).data( 'product-name' ),
						product_price      = self.formatMoney( product_total_price ),
						product_tax_status = self.$form.find( '.wc-pao-addon-container' ).data( 'product-tax-status' );

					/**
					 * Bookings compatibility code.
					 *
					 * CAUTION: This code is unstable.
					 * This code does not change addon totals for booking products if the form is right to left.
					 */
					if ( $( '.wc-bookings-booking-form' ).length ) {
						html =
							'<div class="product-addon-totals"><ul><li><div class="wc-pao-col1"><strong>' +
							product_name +
							'</strong></div><div class="wc-pao-col2"><strong><span class="amount">' +
							product_price +
							'</span></strong></div></li>';
					} else {
						// Display the base product as the first line item in the addons subtotals table.
						var quantity_string = self.is_rtl
							? woocommerce_addons_params.quantity_symbol + self.qty
							: self.qty + woocommerce_addons_params.quantity_symbol;

						html =
							'<div class="product-addon-totals"><ul><li><div class="wc-pao-col1"><strong><span>' +
							quantity_string +
							'</span> ' +
							product_name +
							'</strong></div><div class="wc-pao-col2"><strong><span class="amount">' +
							product_price +
							'</span></strong></div></li>';
					}

					if ( self.addons_price_data.length ) {
						$.each( self.addons_price_data, function ( i, addon ) {
							var cost = addon.cost,
								formatted_value;

							if ( 0 === cost ) {
								formatted_value = '-';
							} else if ( cost > 0 ) {
								formatted_value = self.formatMoney( cost );
							} else {
								formatted_value = '-' + self.formatMoney( Math.abs(cost) );
							}
							html =
								html +
								'<li><div class="wc-pao-col1"><strong>' +
								addon.nameFormattedHTML +
								'</strong></div><div class="wc-pao-col2"><span class="amount">' +
								formatted_value +
								'</span></div></li>';

							if ( woocommerce_addons_params.tax_enabled && addon.is_custom_price ) {
								has_custom_price_with_taxes = true;
							}
						});
					}

					// To show our "price display suffix" we have to do some magic since the string can contain variables (excl/incl tax values)
					// so we have to take our sub total and find out what the tax value is, which we can do via an ajax call
					// if its a simple string, or no string at all, we can output the string without an extra call
					var price_display_suffix = '',
						sub_total_string     =
							typeof self.$totals.data( 'i18n_sub_total' ) === 'undefined'
								? woocommerce_addons_params.i18n_sub_total
								: self.$totals.data( 'i18n_sub_total' );

					// No suffix is present, so we can just output the total.
					if (
						! has_custom_price_with_taxes &&
						( ! woocommerce_addons_params.price_display_suffix ||
						! woocommerce_addons_params.tax_enabled )
					) {
						html =
							html +
							'<li class="wc-pao-subtotal-line"><p class="price">' +
							sub_total_string +
							' <span class="amount">' +
							formatted_sub_total +
							'</span></p></li></ul></div>';

						if ( self.show_subtotal_panel ) {
							self.$totals.html( html );
						} else {
							self.$totals.html( '' );
						}

						self.$form.trigger( 'updated_addons' );
						return;
					}

					// A suffix is present, but no special labels are used - meaning we don't need to figure out any other special values - just display the plain text value
					if (
						'taxable' === product_tax_status &&
						! has_custom_price_with_taxes &&
						false === woocommerce_addons_params.price_display_suffix.indexOf( '{price_including_tax}' ) > -1 &&
						false === woocommerce_addons_params.price_display_suffix.indexOf( '{price_excluding_tax}' ) > -1
					) {
						html =
							html +
							'<li class="wc-pao-subtotal-line"><strong>' +
							sub_total_string +
							' <span class="amount">' +
							formatted_sub_total +
							'</span> ' +
							woocommerce_addons_params.price_display_suffix +
							'</strong></li></ul></div>';

						if ( self.show_subtotal_panel ) {
							self.$totals.html( html );
						} else {
							self.$totals.html( '' );
						}

						self.$form.trigger( 'updated_addons' );
						return;
					}

					// Based on the totals/info and settings we have, we need to use the get_price_*_tax functions
					// to get accurate totals. We can get these values with a special Ajax function
					self.price_request = $.ajax({
						type: 'POST',
						url: woocommerce_addons_params.ajax_url,
						data: {
							action: 'wc_product_addons_calculate_tax',
							product_id: self.product_id,
							add_on_total: self.total,
							add_on_total_raw: self.total_raw,
							qty: self.qty,
						},
						beforeSend : function () {
							if(self.price_request != null) {
								self.price_request.abort();
							}
						},
						complete: function () {
							self.price_request = null;
						},
						success: function ( result ) {
							if ( result.result == 'SUCCESS' ) {
								price_display_suffix =
									'<small class="woocommerce-price-suffix">' +
									woocommerce_addons_params.price_display_suffix +
									'</small>';

								var formatted_price_including_tax = self.formatMoney( result.price_including_tax ),
									formatted_price_excluding_tax = self.formatMoney( result.price_excluding_tax );

								price_display_suffix =
									price_display_suffix.replace(
										'{price_including_tax}',
										'<span class="amount">' +
										formatted_price_including_tax +
										'</span>'
									);
								price_display_suffix =
									price_display_suffix.replace(
										'{price_excluding_tax}',
										'<span class="amount">' +
										formatted_price_excluding_tax +
										'</span>'
									);

								var subtotal = woocommerce_addons_params.display_include_tax
									? formatted_price_including_tax
									: formatted_price_excluding_tax;

								html =
									html +
									'<li class="wc-pao-subtotal-line"><p class="price">' +
									sub_total_string +
									' <span class="amount">' +
									subtotal +
									'</span> ' +
									price_display_suffix +
									' </p></li></ul></div>';

								if ( self.show_subtotal_panel ) {
									self.$totals.html( html );
								} else {
									self.$totals.html( '' );
								}

								self.$form.trigger( 'updated_addons' );
							} else {
								html =
									html +
									'<li class="wc-pao-subtotal-line"><p class="price">' +
									sub_total_string +
									' <span class="amount">' +
									formatted_sub_total +
									'</span></p></li></ul></div>';
								if ( self.show_subtotal_panel ) {
									self.$totals.html( html );
								} else {
									self.$totals.html( '' );
								}
								self.$form.trigger( 'updated_addons' );
							}
						},
						error: function () {
							html =
								html +
								'<li class="wc-pao-subtotal-line"><p class="price">' +
								sub_total_string +
								' <span class="amount">' +
								formatted_sub_total +
								'</span></p></li></ul></div>';

							if ( self.show_subtotal_panel ) {
								self.$totals.html( html );
							} else {
								self.$totals.html( '' );
							}
							self.$form.trigger( 'updated_addons' );
						},
					});
				} else {
					self.$totals.empty();
					self.$form.trigger( 'updated_addons' );
				}
			} else {
				self.$totals.empty();
				self.$form.trigger( 'updated_addons' );
			}
		};

		/**
		 * Resets and hides addon totals.
		 */
		Totals.prototype.reset = function() {
			this.$totals.empty();
			this.$totals.html( '' );
			this.$form.trigger( 'updated_addons' );
		}

		/**
		 * Formats addon prices.
		 *
		 * @param amount
		 * @returns {[]|*}
		 */
		Totals.prototype.formatMoney = function ( amount ) {
			let formatNumDecimal = woocommerce_addons_params.currency_format_num_decimals;

			// Remove trailing zeros.
			if ( woocommerce_addons_params.trim_trailing_zeros ) {
				const amountIsInteger = parseFloat( amount ) % 1 === 0;

				// Remove zeros.
				// if float, 4.6500 => 4.65
				// if integer, 4.0000 => 4
				amount = parseFloat( amount );

				// Set precision value (mandatory to be passed).
				if ( amountIsInteger ) {
					// Set 0 decimal precision for integers.
					formatNumDecimal = 0;
				} else {
					// Count decimal from amount (zeros skipped already) and set as precision.
					// 4.655 => 3 digits after decimal point.
					formatNumDecimal = amount.toString().split( '.' )[ 1 ].length;
				}
			}

			return accounting.formatMoney(amount, {
				symbol: woocommerce_addons_params.currency_format_symbol,
				decimal: woocommerce_addons_params.currency_format_decimal_sep,
				thousand: woocommerce_addons_params.currency_format_thousand_sep,
				precision: formatNumDecimal,
				format: woocommerce_addons_params.currency_format,
			});
		};

		return Form;
	} )();

	$(function () {
		// Quick view.
		$('body').on('quick-view-displayed', function () {
			$(this)
				.find('.cart:not(.cart_group)')
				.each(function () {
					var $form = new WC_PAO.Form( $(this) );
				});
		});

		// Initialize addon totals.
		$('body')
			.find('.cart:not(.cart_group)')
			.each(function () {
				var $form = new WC_PAO.Form( $(this) );
			});
	});

})( jQuery, window );
