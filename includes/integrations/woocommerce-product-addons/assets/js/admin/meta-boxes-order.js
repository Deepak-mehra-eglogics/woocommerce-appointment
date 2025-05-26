/* global wc_pao_admin_order_params */
/* global woocommerce_admin_meta_boxes */
jQuery( function( $ ) {

	function debounce( callback, ms ) {
		var timer = 0;

		clearTimeout( timer );
		timer = setTimeout( callback, ms );
	}

	var $order_items = $( '#woocommerce-order-items' ),
		view         = false,
		functions    = {

			handle_events: function() {

				$order_items
					.on( 'click', 'button.configure_addons', { action: 'configure' }, this.clicked_edit_button )
					.on( 'click', 'button.edit_addons', { action: 'edit' }, this.clicked_edit_button )
					.on( 'wc_pb_populate_form wc_cp_populate_form', (e, pb_view) => {
						view = pb_view;
						functions.render_form();
					})
					.on('wc_pb_validate_form wc_cp_validate_form', ( event, view ) => {
						if ( ! view.is_valid ) {
							return;
						}

						view.is_valid = view.validation.validate( true );
					});

			},

			clicked_edit_button: function( event ) {

				var WCPAOBackboneModal = $.WCBackboneModal.View.extend( {
					addButton: functions.clicked_done_button
				});

				var $item   = $( this ).closest( 'tr.item' ),
					item_id = $item.attr( 'data-order_item_id' );

				view = new WCPAOBackboneModal( {
					target: 'wc-modal-edit-addon',
					string: {
						action: 'configure' === event.data.action ? wc_pao_admin_order_params.i18n_configure : wc_pao_admin_order_params.i18n_edit,
						item_id: item_id
					}
				} );

				functions.populate_form();

				return false;
			},

			clicked_done_button: function( event ) {

				const data = $.extend( {}, functions.get_taxable_address(), {
					action:    'woocommerce_edit_addon_order_item',
					item_id:   view._string.item_id,
					dataType:  'json',
					order_id:  woocommerce_admin_meta_boxes.post_id,
					security:  wc_pao_admin_order_params.edit_pao_nonce
				} );
				const form = view.$el.find( 'form' )[0];

				if ( !view.validation.validate( true ) ) {
					return;
				}

				const form_data = new FormData( form );
				for (const property in data) {
					form_data.append( property, data[property] );
				}

				functions.block( view.$el.find( '.wc-backbone-modal-content' ) );

				$.post( {
					url: woocommerce_admin_meta_boxes.ajax_url,
					type: "POST",
					data : form_data,
					processData: false,
					contentType: false,
					cache: false,
					success: function( response ) {
						if ( response.result && 'success' === response.result ) {

							$order_items.find( '.inside' ).empty();
							$order_items.find( '.inside' ).append( response.html );

							$order_items.trigger( 'wc_order_items_reloaded' );

							// Update notes.
							if ( response.notes_html ) {
								$( 'ul.order_notes' ).empty();
								$( 'ul.order_notes' ).append( $( response.notes_html ).find( 'li' ) );
							}

							functions.unblock( view.$el.find( '.wc-backbone-modal-content' ) );

							// Make it look like something changed.
							functions.block( $order_items, { fadeIn: 0 } );
							setTimeout( function() {
								functions.unblock( $order_items );
							}, 250 );

							view.closeButton( event );

						} else {
							window.alert( response.error ? response.error : wc_pao_admin_order_params.i18n_validation_error );
							functions.unblock( view.$el.find( '.wc-backbone-modal-content' ) );
						}
					},
					error: function () {
						window.alert( wc_pao_admin_order_params.i18n_validation_error );
						functions.unblock( view.$el.find( '.wc-backbone-modal-content' ) );
					}
				});
			},

			populate_form: function() {

				functions.block( view.$el.find( '.wc-backbone-modal-content' ) );

				var data = {
					action:    'woocommerce_configure_addon_order_item',
					item_id:   view._string.item_id,
					dataType:  'json',
					order_id:  woocommerce_admin_meta_boxes.post_id,
					security:  wc_pao_admin_order_params.edit_pao_nonce
				};

				$.post( woocommerce_admin_meta_boxes.ajax_url, data, function( response ) {
					if ( response.result && 'success' === response.result ) {
						view.$el.find( 'form' ).html( response.html );
						functions.unblock( view.$el.find( '.wc-backbone-modal-content' ) );
						functions.render_form();
					} else {
						window.alert( wc_pao_admin_order_params.i18n_form_error );
						functions.unblock( view.$el.find( '.wc-backbone-modal-content' ) );
						view.$el.find( '.modal-close' ).trigger( 'click' );
					}
				} );
			},

			render_form: function() {
				// Special handling for image swatches.
				// When a user clicks on an image swatch, the selection is transferred to a hidden select element.
				var touchTime;

				/**
				 * Addons value changed.
				 */
				view.$el.on(
					'blur change',
					'.wc-pao-addon input, .wc-pao-addon textarea, .wc-pao-addon select, .wc-pao-addon-custom-text',
					function () {
						view.validation.validateAddon( $(this), true );
					}
				);

				view.$el.on(
					'keyup',
					'.wc-pao-addon input, .wc-pao-addon textarea, .wc-pao-addon-custom-text',
					function () {
						var $addon = $(this);

						debounce( function() {
							view.validation.validateAddon( $addon, true );
						}, 300 );
					}
				);

				view.$el.on(
					'touchstart',
					'.wc-pao-addon-image-swatch',
					function (e) {
						touchTime = new Date();
					}
				);

				view.$el.on(
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

						view.validation.validateAddon( $parent.find( 'select.wc-pao-addon-field' ), true );
					}
				);

				/**
				 * Add-ons Datepicker extend.
				 */
				var $datepickers = view.$el.find( '.datepicker' );

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
						gmt_offset    = parseFloat( wc_pao_admin_order_params.gmt_offset, 10 ),
						client_offset = now.getTimezoneOffset() / 60,
						diff          = client_offset - gmt_offset;

					if ( 'default' === wc_pao_admin_order_params.date_input_timezone_reference ) {

						$offset_gmt_input.val( client_offset );
					} else if ( 'store' === wc_pao_admin_order_params.date_input_timezone_reference ) {

						var hours_now  = now.getHours() + now.getMinutes() / 60,
							day_factor = hours_now + diff;

						$offset_gmt_input.val( gmt_offset );
					}

					// Init datepicker.
					$datepicker.datepicker( {
						beforeShow: function( input, el ) {
							if ( wc_pao_admin_order_params.datepicker_class ) {
								$('#ui-datepicker-div').removeClass( wc_pao_admin_order_params.datepicker_class );
								$('#ui-datepicker-div').addClass( wc_pao_admin_order_params.datepicker_class );
							}
						},
						dateFormat: wc_pao_admin_order_params.datepicker_date_format,
						changeMonth: true,
						changeYear: true,
						yearRange: "c-100:c+10",
					} );

					// Fill hidden inputs with selected date if any.
					var currentDate = $datepicker.datepicker( 'getDate' );

					if ( null !== currentDate && typeof currentDate.getTime === 'function' ) {

						// Append current time.
						currentDate.setHours( now.getHours(), now.getMinutes() );

						if ( 'store' === wc_pao_admin_order_params.date_input_timezone_reference ) {
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

							if ( 'store' === wc_pao_admin_order_params.date_input_timezone_reference ) {
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

				view.$addons = view.$el.find( '.wc-pao-addon-field' );
				view.validation = new PaoValidation( view );
				view.validation.validate();
			},

			get_taxable_address: function() {

				var country          = '';
				var state            = '';
				var postcode         = '';
				var city             = '';

				if ( 'shipping' === woocommerce_admin_meta_boxes.tax_based_on ) {
					country  = $( '#_shipping_country' ).val();
					state    = $( '#_shipping_state' ).val();
					postcode = $( '#_shipping_postcode' ).val();
					city     = $( '#_shipping_city' ).val();
				}

				if ( 'billing' === woocommerce_admin_meta_boxes.tax_based_on || ! country ) {
					country  = $( '#_billing_country' ).val();
					state    = $( '#_billing_state' ).val();
					postcode = $( '#_billing_postcode' ).val();
					city     = $( '#_billing_city' ).val();
				}

				return {
					country:  country,
					state:    state,
					postcode: postcode,
					city:     city
				};
			},

			block: function( $target, params ) {

				var defaults = {
					message: null,
					overlayCSS: {
						background: '#fff',
						opacity:    0.6
					}
				};

				var opts = $.extend( {}, defaults, params || {} );

				$target.block( opts );
			},

			unblock: function( $target ) {
				$target.unblock();
			}

		};

	/*
	 * Initialize.
	 */
	functions.handle_events();

} );
