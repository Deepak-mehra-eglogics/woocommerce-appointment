/* global wc_appointments_writepanel_js_params, alert, confirm */
jQuery( function( $ ) {
	'use strict';

	var wc_appointments_writepanel = {
		init: function() {
			$( '#appointments_availability, #appointments_pricing' ).on( 'change', '.wc_appointment_availability_type select, .wc_appointment_availability_type input, .wc_appointment_pricing_type select', this.wc_appointments_table_grid );
			$( '#appointments_availability, #appointments_pricing, #appointments_products' ).on( 'focus', 'select, input, button', this.wc_appointments_table_grid_focus );
			$( 'body' ).on( 'row_added', this.wc_appointments_row_added );
			$( 'body' ).on( 'woocommerce-product-type-change', this.wc_appointments_trigger_change_events );
			$( 'input#_virtual' ).on( 'change', this.wc_appointments_trigger_change_events );
			$( 'input#_downloadable' ).on( 'change', this.wc_appointments_trigger_change_events );
			$( '#_wc_appointment_user_can_cancel' ).on( 'change', this.wc_appointments_user_cancel );
			$( '#_wc_appointment_user_can_reschedule' ).on( 'change', this.wc_appointments_user_reschedule );
			$( '#product-type' ).on( 'change', this.wc_appointments_inventory_show );
			$( '#product-type' ).on( 'change', this.wc_appointments_disable_hide );
			$( '#product-type' ).on( 'change', this.wc_appointments_addon_style );
			$( '#product-type' ).on( 'change', this.wc_appointments_virtual_default );
			$( '#product_addons_data' ).on( 'click', '.wc-pao-addon-adjust-duration', this.wc_appointments_addon_adjust_duration );
			$( '#product_addons_data' ).on( 'change', 'select.wc-pao-addon-type-select', this.wc_appointments_addon_type );
			$( '#product_addons_data' ).on( 'change', 'select.wc-pao-addon-type-select', this.wc_appointments_addon_label );
			// Inventory change magic.
			//$( '#_stock' ).on( 'change', this.wc_appointments_qty );
			$( '#_wc_appointment_qty' ).on( 'change', this.wc_appointments_qty );
			$( '#_wc_appointment_qty_min' ).on( 'change', this.wc_appointments_qty_min );
			$( '#_wc_appointment_qty_max' ).on( 'change', this.wc_appointments_qty_max );
			// Rule change magic.
			$( 'body' ).on( 'change', '.appointments-datetime-select-from :input, .appointments-datetime-select-to :input', this.wc_appointments_rule_range_change );
			$( '#_wc_appointment_has_price_label' ).on( 'change', this.wc_appointments_price_label );
			$( '#_wc_appointment_has_pricing' ).on( 'change', this.wc_appointments_pricing );
			$( '#_wc_appointment_staff_assignment' ).on( 'change', this.wc_appointments_staff_assignment );
			$( '#_wc_appointment_duration_unit' ).on( 'change', this.wc_appointment_duration_unit );
			$( '#_wc_appointment_has_restricted_days' ).on( 'change', this.wc_appointment_restricted_days );
			$( '.add_grid_row' ).on( 'click', this.wc_appointments_table_grid_add_row );
			$( 'body' ).on( 'click', '.remove_grid_row', this.wc_appointments_table_grid_remove_row );
			$( 'body' ).on( 'click', '.manual_sync', this.wc_appointments_manual_sync );
			$( 'body' ).on( 'click', '.oauth_redirect', this.wc_appointments_oauth_redirect );
			$( '#appointments_staff' ).on( 'click', 'button.add_staff', this.wc_appointments_add_staff );
			$( '#appointments_staff' ).on( 'click', 'button.remove_appointment_staff', this.wc_appointments_remove_staff );
			$( '#appointments_products' ).on( 'click', 'button.add_product', this.wc_appointments_add_product );
			$( '#appointments_products' ).on( 'click', 'td.remove_product', this.wc_appointments_remove_product );
			$( 'body' ).on( 'click', 'a.edit_billing', this.wc_appointments_edit_billing );

			wc_appointments_writepanel.wc_appointments_trigger_change_events();
			wc_appointments_writepanel.wc_appointments_price_show();
			wc_appointments_writepanel.wc_appointments_inventory_show();
			wc_appointments_writepanel.wc_appointments_disable_hide();
			wc_appointments_writepanel.wc_appointments_addon_style();
			wc_appointments_writepanel.wc_appointments_virtual_default();
			wc_appointments_writepanel.wc_appointments_sortable_rows();
			wc_appointments_writepanel.wc_appointments_pickers();
			wc_appointments_writepanel.wc_appointments_twilio_sms();
			wc_appointments_writepanel.wc_appointments_exporter_title_action();
			wc_appointments_writepanel.wc_appointments_add_appointment_actions();
		},
		wc_appointments_rule_range_change: function() {
			var input_this            = $( this );
			var input_this_val        = input_this.val();
			var input_this_val_int    = parseFloat( input_this_val.replace( /-/g, '' ).replace( /:/g, '.' ), 10 );
			var input_this_class      = input_this.attr( 'class' );
			var range_from_or_to      = input_this.closest( '.range_from' ).length ? 'from' : 'to';
			var range_oposite         = 'from' === range_from_or_to ? 'to' : 'from';
			var range_type            = input_this.closest( 'tr' ).find( '.range_type select' ).val();
			var input_other_container = input_this.closest( 'tr' ).find( '.appointments-datetime-select-' + range_oposite + '' );
			var input_other           = input_other_container.find( '[class^="' + input_this_class + '"]' );
			var input_other_val       = input_other.val();
			var input_other_val_int   = parseFloat( input_other_val.replace( /-/g, '' ).replace( /:/g, '.' ), 10 );

			// Set up from and to variables.
			var range_from         = 'from' === range_from_or_to ? input_this : input_other;
			var range_from_val_int = 'from' === range_from_or_to ? input_this_val_int : input_other_val_int;
			var range_to           = 'to' === range_from_or_to ? input_this : input_other;
			var range_to_val_int   = 'to' === range_from_or_to ? input_this_val_int : input_other_val_int;

			//console.log( input_this );
			//console.log( input_other_container );

			if ( !input_other_val_int ) {
				input_other.val( input_this_val );
				wc_appointments_writepanel.wc_appointments_input_animate( input_this, input_other );
			} else if ( 'custom:daterange' === range_type || 'time:range' === range_type ) {
				var range_from_date         = input_this.closest( 'tr' ).find( '.from_date input' );
				var range_from_date_val     = range_from_date.val();
				var range_from_date_int     = parseFloat( range_from_date_val.replace( /-/g, '' ) );
				var range_from_time         = input_this.closest( 'tr' ).find( '.from_time input' );
				var range_from_time_val     = range_from_time.val();
				var range_from_time_int     = parseFloat( range_from_time_val.replace( /:/g, '.' ) );
				var range_to_date           = input_this.closest( 'tr' ).find( '.to_date input' );
				var range_to_date_val       = range_to_date.val();
				var range_to_date_int       = parseFloat( range_to_date_val.replace( /-/g, '' ) );
				var range_to_time           = input_this.closest( 'tr' ).find( '.to_time input' );
				var range_to_time_val       = range_to_time.val();
				var range_to_time_int       = parseFloat( range_to_time_val.replace( /:/g, '.' ) );
				var range_oposite_from_time = 'to' === range_from_or_to ? range_to_time : range_from_time;
				var range_oposite_to_time   = 'to' === range_from_or_to ? range_from_time : range_to_time;
				var range_oposite_from_date = 'to' === range_from_or_to ? range_to_date : range_from_date;
				var range_oposite_to_date   = 'to' === range_from_or_to ? range_from_date : range_to_date;

				if ( input_this.hasClass( 'time-picker' ) ) {
					if ( 'time:range' === range_type && range_from_time_int > range_to_time_int ) {
						wc_appointments_writepanel.wc_appointments_input_animate( range_oposite_from_time, range_oposite_to_time, true );
					} else if ( range_from_date_int >= range_to_date_int && range_from_time_int > range_to_time_int ) {
						wc_appointments_writepanel.wc_appointments_input_animate( range_oposite_from_time, range_oposite_to_time, true );
					} else if ( range_from_date_int > range_to_date_int ) {
						wc_appointments_writepanel.wc_appointments_input_animate( range_oposite_from_time, range_oposite_to_date, true );
					} else {
						wc_appointments_writepanel.wc_appointments_input_animate( range_oposite_from_time, range_oposite_to_time );
					}
				} else if ( input_this.hasClass( 'date-picker' ) ) {
					if ( 'time:range' === range_type && range_from_time_int > range_to_time_int ) {
						wc_appointments_writepanel.wc_appointments_input_animate( range_oposite_from_date, range_oposite_to_time, true );
					} else if ( range_from_date_int > range_to_date_int ) {
						wc_appointments_writepanel.wc_appointments_input_animate( range_oposite_from_date, range_oposite_to_date, true );
					} else if ( range_from_time_int > range_to_time_int && range_from_date_int === range_to_date_int ) {
						wc_appointments_writepanel.wc_appointments_input_animate( range_oposite_from_date, range_oposite_to_time, true );
					} else {
						wc_appointments_writepanel.wc_appointments_input_animate( range_oposite_from_date );
					}
				}
			} else if ( range_from_val_int > range_to_val_int ) {
				if ( 'from' === range_from_or_to ) {
					range_to.val( input_this_val );
					wc_appointments_writepanel.wc_appointments_input_animate( input_this, range_to );
				} else {
					wc_appointments_writepanel.wc_appointments_input_animate( input_this, range_from, true );
				}
			} else if ( range_from_val_int <= range_to_val_int ) {
				wc_appointments_writepanel.wc_appointments_input_animate( input_this );
			} else {
				wc_appointments_writepanel.wc_appointments_input_animate( input_this );
			}

			return false;
		},
		wc_appointments_input_animate: function( selected, other, error ) {
			// Reset.
			selected.parents( 'tr' ).find( 'select, input' ).stop().css( {
				outlineWidth: '0'
			} );

			//console.log(selected);
			//console.log(other);
			//console.log(error);

			if ( other ) {
				other.stop().css( {
					outlineWidth: '0'
				} );

				// Update other.
				if ( error ) {
					other.stop().css( {
						outlineOffset: '-1px',
						outlineStyle: 'solid',
						outlineColor: 'red',
						outlineWidth: '1px'
					} );
				} else {
					other.stop().css( {
						outlineOffset: '-1px',
						outlineStyle: 'solid',
						outlineColor: 'black',
						outlineWidth: '1px'
					} ).animate( {
						outlineWidth: '0'
					}, 500, 'linear' );
				}
			}
		},
		wc_appointments_pickers: function() {
			// Date picker.
			$( '.date-picker' ).datepicker( {
				dateFormat: 'yy-mm-dd',
				numberOfMonths: 1,
				showOtherMonths: true,
				changeMonth: true,
				showButtonPanel: true,
				showOn: 'button',
				firstDay: wc_appointments_writepanel_js_params.firstday,
				buttonText: '☶'
			} );

			// Color picker.
			$( '#_wc_appointment_cal_color' ).wpColorPicker();

			return false;
		},
		wc_appointments_table_grid: function() {
			var value = $( this ).val();
			var tr    = $( this ).closest( 'tr' );
			var row   = $( tr );

			row.find( '.from_date, .from_day_of_week, .from_month, .from_week, .from_time, .from' ).hide();
			row.find( '.to_date, .to_day_of_week, .to_month, .to_week, .to_time, .to, .on_date' ).hide();
			row.find( '.repeating-label' ).hide();
			row.find( '.appointments-datetime-select-to' ).removeClass( 'appointments-datetime-select-both' );
			row.find( '.appointments-datetime-select-from' ).removeClass( 'appointments-datetime-select-both' );
			row.find( '.rrule' ).hide();

			if ( 'custom' === value ) {
				row.find( '.from_date, .to_date' ).show();
			}
			if ( 'custom:daterange' === value ) {
				row.find( '.from_time, .to_time' ).show();
				row.find( '.from_date, .to_date' ).show();
				row.find( '.appointments-datetime-select-to' ).addClass( 'appointments-datetime-select-both' );
				row.find( '.appointments-datetime-select-from' ).addClass( 'appointments-datetime-select-both' );
			}
			if ( 'months' === value ) {
				row.find( '.from_month, .to_month' ).show();
			}
			if ( 'weeks' === value ) {
				row.find( '.from_week, .to_week' ).show();
			}
			if ( 'days' === value ) {
				row.find( '.from_day_of_week, .to_day_of_week' ).show();
			}
			if ( value.match( '^time' ) ) {
				row.find( '.from_time, .to_time' ).show();
				//* Show the date range as well if "time range for custom dates" is selected
				if ( 'time:range' === value ) {
					row.find( '.from_date, .to_date' ).show();
					row.find( '.repeating-label' ).show();
					row.find( '.appointments-datetime-select-to' ).addClass( 'appointments-datetime-select-both' );
					row.find( '.appointments-datetime-select-from' ).addClass( 'appointments-datetime-select-both' );
				}
			}
			if ( 'duration' === value || 'slots' === value || 'quant' === value ) {
				row.find( '.from, .to' ).show();
			}
			if ( 'rrule' === value ) {
				row.find( '.rrule' ).show();
			}

			return false;
		},
		wc_appointments_table_grid_focus: function( e ) {
			var $this_body  = $( 'body' );
			var $this_table = $( this ).closest( 'table, tbody' );
			var $this_row   = $( this ).closest( 'tr' );

			//console.log( e );

			if ( 'focus' === e.type || 'focusin' === e.type || ( 'click' === e.type && $( this ).is( ':focus' ) ) ) {
				$( 'tr', $this_table ).removeClass( 'current' ).removeClass( 'last_selected' );
				$this_row.addClass( 'current' ).addClass( 'last_selected' );
				$this_body.addClass( 'row_highlighted' );
			}

			return false;
		},
		wc_appointments_sortable_rows: function() {
			$( '#availability_rows, #pricing_rows' ).sortable( {
				items: 'tr',
				cursor: 'move',
				axis: 'y',
				handle: '.sort',
				scrollSensitivity: 40,
				forcePlaceholderSize: true,
				helper: 'clone',
				opacity: 0.65,
				placeholder: {
					element: function() {
						return $( '<tr class="wc-metabox-sortable-placeholder"><td colspan=99>&nbsp;</td></tr>' )[0];
					},
					update: function() {}
				},
				start: function( event, ui ) {
					ui.item.css( 'background-color', '#f6f6f6' );
				},
				stop: function( event, ui ) {
					ui.item.prop( 'style', false );
					ui.item.show();
				}
			} );

			$( '.woocommerce_appointable_staff' ).sortable( {
				items: '.woocommerce_appointment_staff',
				cursor: 'move',
				axis: 'y',
				handle: 'h3',
				scrollSensitivity: 40,
				forcePlaceholderSize: true,
				helper: 'clone',
				opacity: 0.65,
				placeholder: 'wc-metabox-sortable-placeholder',
				start: function( event, ui ) {
					ui.item.css( 'background-color', '#f6f6f6' );
				},
				stop: function( event, ui ) {
					ui.item.prop( 'style', false );
					wc_appointments_writepanel.staff_row_indexes();
				}
			} );

			return false;
		},
		wc_appointments_row_added: function() {
			$( '.wc_appointment_availability_type select, .wc_appointment_pricing_type select' ).trigger( 'change' );
			$( '.date-picker' ).datepicker( {
				dateFormat: 'yy-mm-dd',
				numberOfMonths: 1,
				showOtherMonths: true,
				changeMonth: true,
				showButtonPanel: true,
				showOn: 'button',
				firstDay: wc_appointments_writepanel_js_params.firstday,
				buttonText: '☶'
			} );

			return false;
		},
		wc_appointments_trigger_change_events: function() {
			$( '.wc_appointment_availability_type select, .wc_appointment_availability_type input, .wc_appointment_pricing_type select, #_wc_appointment_user_can_cancel, #_wc_appointment_user_can_reschedule, #_wc_appointment_has_price_label, #_wc_appointment_has_pricing, #_wc_appointment_duration_unit, #_wc_appointment_staff_assignment, #_stock, #_wc_appointment_qty, #_wc_appointment_qty_min, #_wc_appointment_qty_max, #_wc_appointment_has_restricted_days' ).trigger( 'change' );

			return false;
		},
		wc_appointments_user_cancel: function() {
			if ( $( this ).is( ':checked' ) ) {
				$( '.form-field.appointment-cancel-limit' ).show();
			} else {
				$( '.form-field.appointment-cancel-limit' ).hide();
			}

			return false;
		},
		wc_appointments_user_reschedule: function() {
			if ( $( this ).is( ':checked' ) ) {
				$( '.form-field.appointment-reschedule-limit' ).show();
			} else {
				$( '.form-field.appointment-reschedule-limit' ).hide();
			}

			return false;
		},
		wc_appointments_qty: function() {
			var qty_this	= parseInt( $( this ).val(), 10 );
			var qty_min		= parseInt( $( '#_wc_appointment_qty_min' ).val(), 10 );

			if ( 1 < qty_this ) {
				$( '.form-field._wc_appointment_customer_qty_wrap' ).show();
				$( '#_wc_appointment_qty_min' ).prop( 'max', qty_this );
			} else {
				$( '.form-field._wc_appointment_customer_qty_wrap' ).hide();
			}

			// min.
			if ( qty_this < qty_min ) {
				$( '#_wc_appointment_qty_min' ).val( qty_this );
			}

			return false;
		},
		wc_appointments_qty_min: function() {
			var qty_this	= parseInt( $( this ).val(), 10 );
			var qty_max		= parseInt( $( '#_wc_appointment_qty_max' ).val(), 10 );

			if ( qty_this > qty_max ) {
				$( '#_wc_appointment_qty_max' ).val( qty_this );
			}

			return false;
		},
		wc_appointments_qty_max: function() {
			var qty_this	= parseInt( $( this ).val(), 10 );
			var qty_min		= parseInt( $( '#_wc_appointment_qty_min' ).val(), 10 );

			if ( qty_this < qty_min ) {
				$( '#_wc_appointment_qty_min' ).val( qty_this );
			}

			return false;
		},
		wc_appointments_price_show: function() {
			var product_type = $( 'select#product-type' ).val();

			if ( 'appointment' === product_type ) {
				$( '.options_group.pricing' ).show();
				$( '.options_group.wcpbc_pricing' ).show(); // WooCommerce Price Based on Country integration.
			}

			$( '.options_group.pricing' ).addClass( 'show_if_appointment' );
			$( '.options_group.wcpbc_pricing' ).addClass( 'show_if_appointment' ); // WooCommerce Price Based on Country integration.

			return false;
		},
		wc_appointments_inventory_show: function() {
			var product_type = $( 'select#product-type' ).val();

			if ( 'appointment' === product_type ) {
				$( '.stock_fields' ).hide();
				$( '.stock_fields' ).addClass( 'hide_if_appointment' );
				$( '._stock_status_field' ).hide();
				$( '._stock_status_field' ).addClass( 'hide_if_appointment' );

				$( '._sold_individually_field' ).parents( '.options_group' ).show();
				$( '._sold_individually_field' ).parents( '.options_group' ).addClass( 'show_if_appointment' );

				$( '#inventory_product_data ._sold_individually_field' ).show();
				$( '#inventory_product_data ._sold_individually_field' ).addClass( 'show_if_appointment' );
			}

			return false;
		},
		wc_appointments_disable_hide: function() {
			var product_type = $( 'select#product-type' ).val();
			var type_defined = 'undefined' !== typeof product_type;

			if ( 'appointment' === product_type || !type_defined ) {
				$( 'body' ).find( '.disabled_if_appointment' )
					.prop( 'hidden', true )
					.prop( 'disabled', true );
			} else {
				$( 'body' ).find( '.disabled_if_appointment' )
					.prop( 'hidden', false )
					.prop( 'disabled', false );
			}

			return false;
		},
		wc_appointments_addon_style: function() {
			var product_type = $( 'select#product-type' ).val();
			var type_defined = 'undefined' !== typeof product_type;

			if ( 'appointment' === product_type || !type_defined ) {
				$( 'body' ).addClass( 'style_if_appointment' );
			} else {
				$( 'body' ).removeClass( 'style_if_appointment' );
			}

			return false;
		},
		wc_appointments_virtual_default: function() {
			var product_type       = $( 'select#product-type' ).val();
			var virtual_input      = $( 'body' ).find( 'input#_virtual' );
			var is_new_product     = $( 'body' ).hasClass( 'post-new-php' );
			var orig_checked_state = virtual_input.attr( 'checked' );

			//console.log( orig_checked_state );

			// Only set as default virtual on new product creation.
			if ( is_new_product ) {
				// Virtual as default only for appointment product tpye.
				if ( 'appointment' === product_type ) {
					virtual_input.prop( 'checked', true );
				// For other product types, revert to default.
				} else {
					if ( 'checked' === orig_checked_state ) {
						virtual_input.prop( 'checked', true );
					} else {
						virtual_input.prop( 'checked', false );
					}
				}
			}

			return false;
		},
		wc_appointments_twilio_sms: function() {
			// admin settings toggles
			$( '.wc_twilio_sms_enable' ).each( function() {
				var notification = $( this ).data( 'notification' );

				$( this ).on( 'change', function() {
					if ( $( this ).is( ':checked' ) ) {
						$( 'input#wc_twilio_sms_appointments_' + notification + '_recipients' ).closest( 'tr' ).show();
						$( 'input#wc_twilio_sms_appointments_' + notification + '_schedule_number' ).closest( 'tr' ).show();
						$( 'textarea#wc_twilio_sms_appointments_' + notification + '_template' ).closest( 'tr' ).show();
					} else {
						$( 'input#wc_twilio_sms_appointments_' + notification + '_recipients' ).closest( 'tr' ).hide();
						$( 'input#wc_twilio_sms_appointments_' + notification + '_schedule_number' ).closest( 'tr' ).hide();
						$( 'textarea#wc_twilio_sms_appointments_' + notification + '_template' ).closest( 'tr' ).hide();
					}
				} ).trigger( 'change' );
			} );

			// appointments integration product tab

			// open / close SMS tab on product
			$( '#wc-twilio-sms-appointments-data .wc-metaboxes-wrapper' )
				.on( 'click', '.expand_all', function() {
					$( this ).closest( '.wc-metaboxes-wrapper' ).find( '.wc-metabox > .wc-metabox-content' ).show();
					return false;
				} )
				.on( 'click', '.close_all', function() {
					$( this ).closest( '.wc-metaboxes-wrapper' ).find( '.wc-metabox > .wc-metabox-content' ).hide();
					return false;
				} );

			// hide notification settings if "override" is not selected
			$( '.wc_twilio_sms_notification_toggle' ).each( function() {
				var notification   = $( this ).data( 'notification' );
				var scheduleNumber = $( 'input#wc_twilio_sms_appointments_' + notification + '_schedule_number' );

				$( this ).on( 'change', function() {
					if ( 'override' === $( this ).find( 'option:selected' ).val() ) {
						scheduleNumber.closest( 'p' ).show();
						scheduleNumber.closest( 'p' ).next( '.wc-twilio-sms-post-field' ).show();
						$( 'textarea#wc_twilio_sms_appointments_' + notification + '_template' ).closest( 'p' ).show();
					} else {
						scheduleNumber.closest( 'p' ).hide();
						scheduleNumber.closest( 'p' ).next( '.wc-twilio-sms-post-field' ).hide();
						$( 'textarea#wc_twilio_sms_appointments_' + notification + '_template' ).closest( 'p' ).hide();
					}
				} ).trigger( 'change' );
			} );

			return false;
		},
		wc_appointments_addon_adjust_duration: function() {
			if ( $( this ).is( ':checked' ) ) {
				$( this ).parents( '.wc-pao-addon-adjust-duration-container' ).find( '.wc-pao-addon-adjust-duration-settings' ).removeClass( 'hide' ).addClass( 'show' );
			} else {
				$( this ).parents( '.wc-pao-addon-adjust-duration-container' ).find( '.wc-pao-addon-adjust-duration-settings' ).removeClass( 'show' ).addClass( 'hide' );
			}
		},
		wc_appointments_addon_type: function() {
			var selectedValue = $( this ).val();
			var parent        = $( this ).parents( '.wc-pao-addon' );

			switch ( selectedValue ) {
				case 'multiple_choice':
				case 'checkbox':
					parent.find( '.wc-pao-addon-hide_duration-setting' ).removeClass( 'hide' ).addClass( 'show' );
					parent.find( '.wc-pao-addon-hide_price-setting' ).removeClass( 'hide' ).addClass( 'show' );
					parent.find( '.wc-pao-addon-adjust-duration-container' ).removeClass( 'show' ).addClass( 'hide' );
					break;
				case 'heading':
				case 'custom_price':
					parent.find( '.wc-pao-addon-adjust-duration-container' ).removeClass( 'show' ).addClass( 'hide' );
					break;
				default:
					parent.find( '.wc-pao-addon-hide_duration-setting' ).removeClass( 'show' ).addClass( 'hide' );
					parent.find( '.wc-pao-addon-hide_price-setting' ).removeClass( 'show' ).addClass( 'hide' );
					parent.find( '.wc-pao-addon-adjust-duration-container' ).removeClass( 'hide' ).addClass( 'show' );
					break;
			}

			return false;
		},
		wc_appointments_addon_label: function() {
			var product_type = $( 'select#product-type' ).val();
			var type_defined = 'undefined' !== typeof product_type;

			if ( 'appointment' === product_type || !type_defined ) {
				$( 'body' ).addClass( 'style_if_appointment' );
				$( 'body' ).addClass( 'style_if_appointment' );
			} else {
				$( 'body' ).removeClass( 'style_if_appointment' );
				$( 'body' ).removeClass( 'style_if_appointment' );
			}

			if ( 'appointment' === product_type || !type_defined ) {
				$( '.show_if_appointment' ).show();
			} else {
				$( '.show_if_appointment' ).hide();
			}

			return false;
		},
		wc_appointments_price_label: function() {
			if ( $( this ).is( ':checked' ) ) {
				$( '.form-field._wc_appointment_price_label_field' ).show();
			} else {
				$( '.form-field._wc_appointment_price_label_field' ).hide();
			}

			return false;
		},
		wc_appointments_pricing: function() {
			if ( $( this ).is( ':checked' ) ) {
				$( '#appointments_pricing' ).show();
			} else {
				$( '#appointments_pricing' ).hide();
			}

			return false;
		},
		wc_appointments_staff_assignment: function() {
			if ( 'customer' === $( this ).val() ) {
				$( '.form-field._wc_appointment_staff_label_field' ).show();
				$( '.form-field._wc_appointment_staff_nopref_field' ).show();
			} else {
				$( '.form-field._wc_appointment_staff_label_field' ).hide();
				$( '.form-field._wc_appointment_staff_nopref_field' ).hide();
			}

			return false;
		},
		wc_appointment_duration_unit: function() {
			switch ( $( this ).val() ) {
				case 'month':
					$( '.form-field._wc_appointment_interval_duration_wrap' ).hide();
					$( '.form-field._wc_appointment_padding_duration_wrap' ).hide();
					$( '.form-field._wc_appointment_customer_timezones_field' ).hide();
					break;
				case 'day':
					$( '.form-field._wc_appointment_interval_duration_wrap' ).hide();
					$( '.form-field._wc_appointment_padding_duration_wrap' ).show();
					$( '.form-field._wc_appointment_customer_timezones_field' ).hide();
					$( '#_wc_appointment_padding_duration_unit option[value="minute"]' ).hide();
					$( '#_wc_appointment_padding_duration_unit option[value="hour"]' ).hide();
					$( '#_wc_appointment_padding_duration_unit option[value="day"]' ).show();
					$( '#_wc_appointment_padding_duration_unit' ).val( 'day' );
					break;
				default: // all other.
					$( '.form-field._wc_appointment_interval_duration_wrap' ).show();
					$( '.form-field._wc_appointment_padding_duration_wrap' ).show();
					$( '.form-field._wc_appointment_customer_timezones_field' ).show();
					$( '#_wc_appointment_padding_duration_unit option[value="minute"]' ).show();
					$( '#_wc_appointment_padding_duration_unit option[value="hour"]' ).show();
					$( '#_wc_appointment_padding_duration_unit option[value="day"]' ).hide();
					break;
			}

			return false;
		},
		wc_appointment_restricted_days: function() {
			if ( $( this ).is( ':checked' ) ) {
				$( '.appointment-day-restriction' ).show();
			} else {
				$( '.appointment-day-restriction' ).hide();
			}

			return false;
		},
		wc_appointments_table_grid_add_row: function( e ) {
			var newRowIndex = $( e.target ).closest( 'table' ).find( '#pricing_rows tr' ).length;
			var newRow = $( this ).data( 'row' );
			newRow = newRow.replace( /appointments_cost_js_index_replace/ig, newRowIndex.toString() );
			// Clear out IDs.
			newRow = newRow.replace( /wc_appointment_availability_id.+/, 'wc_appointment_availability_id[]" value="" />' );
			newRow = newRow.replace( /wc_appointment_availability_kind_id.+/, 'wc_appointment_availability_kind_id[]" value="" />' );
			newRow = newRow.replace( /wc_appointment_availability_event_id.+/, 'wc_appointment_availability_event_id[]" value="" />' );
			newRow = newRow.replace( /data-id=.+/, 'data-id="">' );

			// Clear out title.
			newRow = newRow.replace( /wc_appointment_availability_title.+/, 'wc_appointment_availability_title[]" value="" />' );

			// Clear out priority.
			newRow = newRow.replace( /wc_appointment_availability_priority.+/, 'wc_appointment_availability_priority[]" value="10" placeholder="10" />' );

			$( e.target ).closest( 'table' ).find( 'tbody' ).append( newRow );
			$( 'body' ).trigger( 'row_added' );

			return false;
		},
		wc_appointments_table_grid_remove_row: function( e ) {
			var row = $( e.target ).closest( 'tr' );
			var id  = row.data( 'id' );

			// Get current deleted list.
			var deleted = $( '.wc-appointment-availability-deleted' ).val();

			// Separator.
			var separator = ( deleted ? ', ' : '' );

			// Add to deleted list.
			var deleted_ids = deleted + separator + id;

			// Add deleted id to input field.
			$( '.wc-appointment-availability-deleted' ).val( deleted_ids );

			row.remove();

			return false;
		},
		wc_appointments_manual_sync: function() {
			var el       = $( this ).closest( 'td' );
			var staff_id = $( this ).attr( 'data-staff' );

			// Block removed element.
			$( el ).block( { message: null } );

			var data = {
				action: 'woocommerce_manual_sync',
				staff_id: staff_id,
				security: wc_appointments_writepanel_js_params.nonce_manual_sync
			};

			$.post( wc_appointments_writepanel_js_params.ajax_url, data, function( response ) {
				if ( response.error ) {
					alert( response.error );
					$( el ).unblock();
				} else {
					$( '.last_synced' ).html( response.html );
					$( el ).unblock();
				}
			} );
		},
		wc_appointments_oauth_redirect: function() {
			var el       = $( this ).closest( 'td' );
			var staff_id = $( this ).attr( 'data-staff' );
			var logout   = $( this ).attr( 'data-logout' );

			// Block removed element.
			$( el ).block( { message: null } );

			var data = {
				action: 'woocommerce_oauth_redirect',
				staff_id: staff_id,
				logout: logout,
				security: wc_appointments_writepanel_js_params.nonce_oauth_redirect
			};

			$.post( wc_appointments_writepanel_js_params.ajax_url, data, function( response ) {
				if ( response.error ) {
					alert( response.error );
					$( el ).unblock();
				} else {
					top.location.replace( response.uri );
					$( el ).unblock();
				}
			} );
		},
		wc_appointments_add_staff: function() {
			var loop           = $( '.woocommerce_appointment_staff' ).length;
			var add_staff_id   = parseInt( $( 'select#add_staff_id' ).val(), 10 );
			var add_staff_name = '';

			$( '.woocommerce_appointable_staff' ).block( { message: null } );

			var data = {
				action: 'woocommerce_add_appointable_staff',
				post_id: wc_appointments_writepanel_js_params.post,
				loop: loop,
				add_staff_id: add_staff_id,
				add_staff_name: add_staff_name,
				security: wc_appointments_writepanel_js_params.nonce_add_staff
			};

			$.post( wc_appointments_writepanel_js_params.ajax_url, data, function( response ) {
				if ( response.error ) {
					alert( response.error );
					$( '.woocommerce_appointable_staff' ).unblock();
				} else {
					$( '.woocommerce_appointable_staff' ).append( response.html ).unblock();
					$( '.woocommerce_appointable_staff' ).sortable( 'refresh' );
				}
			} );

			return false;
		},
		wc_appointments_remove_staff: function( element ) {
			element.preventDefault();
			var answer = confirm( wc_appointments_writepanel_js_params.i18n_confirmation );
			if ( answer ) {
				var el    = $( this ).parent().parent();
				var staff = $( this ).attr( 'rel' );

				$( el ).block( { message: null } );

				var data = {
					action: 'woocommerce_remove_appointable_staff',
					post_id: wc_appointments_writepanel_js_params.post,
					staff_id: staff,
					security: wc_appointments_writepanel_js_params.nonce_delete_staff
				};

				$.post( wc_appointments_writepanel_js_params.ajax_url, data, function() {
					$( el ).fadeOut( '300', function() {
						$( el ).remove();
					} );
				} );
			}

			return false;
		},
		wc_appointments_add_product: function() {
			var add_product_id   = parseInt( $( 'select#add_product_id' ).val(), 10 );
			var staff 	         = parseInt( $( 'select#add_product_id' ).attr( 'data-staff' ) );
			var products         = $( '#wc_appointments_staff_product_ids' ).val();
			var add_product_name = '';

			$( '.woocommerce_staff_products' ).block( { message: null } );

			var data = {
				action: 'woocommerce_add_staff_product',
				staff_id: staff,
				assigned_products: products,
				add_product_id: add_product_id,
				add_product_name: add_product_name,
				security: wc_appointments_writepanel_js_params.nonce_add_product
			};

			$.post( wc_appointments_writepanel_js_params.ajax_url, data, function( response ) {
				if ( response.error ) {
					alert( response.error );
					$( '.woocommerce_staff_products' ).unblock();
				} else {
					$( '.woocommerce_staff_products' ).append( response.html ).unblock();
					$( '#wc_appointments_staff_product_ids' ).val( products + ',' + add_product_id );
				}
			} );

			return false;
		},
		wc_appointments_remove_product: function( element ) {
			element.preventDefault();
			var answer = confirm( wc_appointments_writepanel_js_params.i18n_confirmation );
			if ( answer ) {
				var el = $( this ).parent();

				// Remove selected product ID from comma separated list.
				var product_id 	= $( this ).attr( 'data-product' );
				var staff_id 	= $( this ).attr( 'data-staff' );

				// Remove from product IDs.
				var product_ids = $( '#wc_appointments_staff_product_ids' ).val();
				var new_product_ids = product_ids.replace( new RegExp( ',?' + product_id + ',?' ), function( match ) {
					var first_comma = ',' === match.charAt( 0 );
					var second_comma = ',' === match.charAt( match.length - 1 );
					if ( first_comma && second_comma ) {
						return ',';
					}
					return '';
			    } );
				$( '#wc_appointments_staff_product_ids' ).val( new_product_ids );

				// Block removed element.
				$( el ).block( { message: null } );

				var data = {
					action: 'woocommerce_remove_appointable_staff',
					post_id: product_id,
					staff_id: staff_id,
					security: wc_appointments_writepanel_js_params.nonce_delete_staff
				};

				$.post( wc_appointments_writepanel_js_params.ajax_url, data, function() {
					$( el ).fadeOut( '300', function() {
						$( el ).remove();
					} );
				} );
			}

			return false;
		},
		staff_row_indexes: function() {
			$( '.woocommerce_appointable_staff .woocommerce_appointment_staff' ).each( function( index, el ) {
				$( '.staff_menu_order', el ).val( parseInt( $( el ).index( '.woocommerce_appointable_staff .woocommerce_appointment_staff' ), 10 ) );
			} );

			return false;
		},
		wc_appointments_exporter_title_action: function() {
			// Add buttons to appointment screen.
			var $appointmen_screen = $( '.edit-php.post-type-wc_appointment' );
			var	$title_action      = $appointmen_screen.find( '.page-title-action:first' );
			var	$blankslate        = $appointmen_screen.find( '.woocommerce-BlankState' );

			if ( 0 === $blankslate.length ) {
				if ( wc_appointments_writepanel_js_params.exporter.permission ) {
					$title_action.after(
						'<a href="' +
						wc_appointments_writepanel_js_params.exporter.url +
						'" class="page-title-action">' +
						wc_appointments_writepanel_js_params.exporter.string +
						'</a>'
					);
				}
			} else {
				$title_action.hide();
			}
		},
		wc_appointments_add_appointment_actions: function() {
			$( '#appointment_order_id' ).filter( ':not(.enhanced)' ).each( function() {
				var select2_args = {
					allowClear: true,
					placeholder: $( this ).data( 'placeholder' ),
					minimumInputLength: 1,
					escapeMarkup: function( m ) {
						return m;
					},
					ajax: {
						url: wc_appointments_writepanel_js_params.ajax_url,
						dataType: 'json',
						quietMillis: 250,
						data: function( params ) {
							return {
								term: params.term,
								action: 'wc_appointments_json_search_order',
								security: wc_appointments_writepanel_js_params.nonce_appointment_order
							};
						},
						processResults: function( data ) {
							var terms = [];
							if ( data ) {
								$.each( data, function( id, text ) {
									terms.push( {
										id: id,
										text: text
									} );
								} );
							}
							return {
								results: terms
							};
						},
						cache: true
					},
					multiple: false
				};
				$( this ).select2( select2_args ).addClass( 'enhanced' );
			} );
			$( function() {
				var order_label_selector = $( '.appointment-order-label-select' );
				var	order_billing_row    = $( 'tr.billing_row' );
				$( '.appointment-order-selector' ).on( 'change', function() {
					var order_selector = $( this ).val();
					if ( 'existing' === order_selector ) {
						order_label_selector.show();
						order_billing_row.hide();
					} else {
						order_label_selector.hide();
						order_billing_row.show();
					}
				} );
			} );
		},
		wc_appointments_edit_billing: function( element ) {
			element.preventDefault();

			var el            = $( this );
			var	wrapper       = el.closest( '.billing_row' );
			var	edit_billing  = wrapper.find( 'div.edit_billing' );
			var	none_set      = wrapper.find( 'p.none_set' );
			var country_input = edit_billing.find( '.js_field-country' );
			var	state_input   = edit_billing.find( '.js_field-state' );

			el.parent().find( 'a' ).toggle();

			if ( !country_input.val() ) {
				country_input.val( wc_appointments_writepanel_js_params.billing.default_country ).trigger( 'change' );
				state_input.val( wc_appointments_writepanel_js_params.billing.default_state ).trigger( 'change' );
			}

			none_set.toggle();
			edit_billing.toggle();

			return false;
		}
	};

	wc_appointments_writepanel.init();
} );
