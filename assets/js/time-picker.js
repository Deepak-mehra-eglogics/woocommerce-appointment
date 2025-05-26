/* globals wc_appointment_form_params, wca_get_querystring */
jQuery( function( $ ) {
	'use strict';

	var wc_appointments_time_picker = {
		init: function() {
			var appointment_forms = $( '.wc-appointments-appointment-form' );
			// Run on each form instance.
			appointment_forms.each( function() {
				var form   = $( this ).closest( 'form' );
				var picker = form.find( '.picker:eq(0)' );

				// Show updated time slots.
				form.on( 'addon-duration-changed', function( event, duration ) {
					// Update 'data-combined_duration'.
					var appointment_duration = parseInt( picker.attr( 'data-appointment_duration' ), 10 );
					var addon_duration = parseInt( duration, 10 );
					var combined_duration = parseInt( appointment_duration + addon_duration, 10 );

					picker.data( 'combined_duration', combined_duration );
					picker.data( 'addon_duration', duration );

					wc_appointments_time_picker.show_available_time_slots( this );
				} );

				// Init picker.
				wc_appointments_time_picker.time_picker_init( form );
			} );

			// Update time slots, when timezone changes.
			$( 'body' ).on( 'change', '#wc_appointments_field_timezone', function() {
				wc_appointments_time_picker.show_available_time_slots( this );
			} );

			// Update time slots, when selected date changes.
			appointment_forms.parents( 'form' ).on( 'date-selected', function() {
				wc_appointments_time_picker.show_available_time_slots( this );
			} );

			// Update time slots, when month or year changes.
			appointment_forms.parents( 'form' ).on( 'month-year-changed', function() {
				wc_appointments_time_picker.reset_time_slots( this );
			} );

			// Reset time slots, when selected staff changes.
			$( 'body' ).on( 'change', '#wc_appointments_field_staff', function() {
				wc_appointments_time_picker.hide_time_slots( this );
			} );
		},
		time_picker_init: function( form ) {
			var picker = form.find( '.slot-picker' );
			// Time slot manually selected.
			picker.on( 'click', 'a', function( event ) {
				// Prevent # href link action.
				event.preventDefault();

				// Get selected slot 'data-slot' value.
				var time_value = $( this ).parent().data( 'slot' );

				// Set selected time on slot picker.
				wc_appointments_time_picker.set_selected_time( form, time_value );
			} );
		},
		set_selected_time: function( form, time_value ) {
			var submit_button = form.find( '.wc-appointments-appointment-form-button' );
			var slot_picker   = form.find( '.slot-picker' );

			// Disable submit button.
			submit_button.prop( 'disabled', true );

			// Fire up 'time-selected' trigger.
			if ( undefined === time_value ) {
				//form.triggerHandler( 'time-selected' );
				return;
			}

			var selected_slot = slot_picker.find( '[data-slot="' + time_value + '"]' );
			var selected_slot_value = selected_slot.find( 'a' ).data( 'value' );

			// Fire up 'time-selected' trigger.
			if ( undefined === selected_slot.data( 'slot' ) ) {
				//form.triggerHandler( 'time-selected' );
				return;
			}

			var target = form.find( '.slot-picker' ).parent( 'div' ).find( 'input' );

			// Add selected time value to input.
			// target.val( value ).trigger( 'change' );
			target.val( selected_slot_value ); // timeslot in local format
			target.attr( 'data-value', time_value ); // timeslot in Hi format

			// Fire up 'time-selected' trigger.
			form.triggerHandler( 'time-selected' );

			// Empty previous selected slot.
			slot_picker.find( 'li' ).removeClass( 'selected' );

			// Add .selected class to selected slot.
			selected_slot.addClass( 'selected' );

			return 'selected';
		},
		unset_selected_time: function( form ) {
			var submit_button = form.find( '.wc-appointments-appointment-form-button' );
			var slot_picker   = form.find( '.slot-picker' );

			// Disable submit button.
			submit_button.prop( 'disabled', true );

			// Empty previous selected slot.
			slot_picker.find( 'li' ).removeClass( 'selected' );
		},
		show_available_time_slots: function( element ) {
			var form            = $( element ).closest( 'form' );
			var picker          = form.find( '.picker:eq(0)' );
			var slot_picker     = form.find( '.slot-picker' );
			var fieldset        = form.find( 'fieldset' );
			var year            = parseInt( fieldset.find( 'input.appointment_date_year' ).val(), 10 );
			var month           = parseInt( fieldset.find( 'input.appointment_date_month' ).val(), 10 );
			var day             = parseInt( fieldset.find( 'input.appointment_date_day' ).val(), 10 );
			var selected_slot   = slot_picker.find( '.selected' );
			var time_value      = selected_slot.data( 'slot' );
			var is_autoselect   = picker.attr( 'data-is_autoselect' );
			var addon_duration  = picker.data( 'addon_duration' ) ? picker.data( 'addon_duration' ) : 0;
			var timezone_change = 0;

			// Detect time zone change?
			if ( 'wc_appointments_field_timezone' === $( element ).attr( 'name' ) ) {
				timezone_change = 1;
			}

			if ( !year || !month || !day ) {
				return;
			}

			// clear slots
			slot_picker.closest( 'div' ).find( 'input' ).val( '' ).trigger( 'change' );
			slot_picker.closest( 'div' ).block( {message: null, overlayCSS: {background: '#fff', backgroundSize: '16px 16px', opacity: 0.6}} ).show();

			// Get slots.
			$.ajax( {
				type: 'POST',
				url: wc_appointment_form_params.ajax_url,
				data: {
					action: 'wc_appointments_get_slots',
					form: form.serialize(),
					duration: addon_duration
				},
				success: function( code ) {
					slot_picker.html( code );
					slot_picker.closest( 'div' ).unblock();

					if ( timezone_change ) {
						wc_appointments_time_picker.unset_selected_time( form );

						return;
					}

					var set_selected = wc_appointments_time_picker.set_selected_time( form, time_value );

					// if time is in querystring, select it instead of the first time
					// it overrides autoselect setting
					if ( null !== wca_get_querystring( 'time' ) && undefined === set_selected ) {
						var selected_time = slot_picker.find( 'li.slot[data-slot="' + wca_get_querystring( 'time' ) + '"]' ).not( '.slot_empty' );

						if ( 0 < selected_time.length ) {
							form.triggerHandler( 'selected-time-unavailable', selected_time );
							selected_time.find( 'a' ).trigger( 'click' );
						} else {
							// window.alert( wc_appointment_form_params.i18n_time_unavailable );
							wc_appointments_time_picker.autoselect_first_available_time( form );
						}
					// Auto select first available time
					} else if ( is_autoselect && undefined === set_selected ) {
						wc_appointments_time_picker.autoselect_first_available_time( form );
					}
				},
				dataType: 'html'
			} );
		},
		autoselect_first_available_time: function( form ) {
			var picker        = form.find( '.picker:eq(0)' );
			var slot_picker   = form.find( '.slot-picker' );
			var selected_day  = picker.find( 'td.ui-datepicker-current-day' ).not( '.ui-state-disabled' );
			var first_slot    = slot_picker.find( 'li.slot:not(".slot_empty"):first' );
			var is_autoselect = picker.attr( 'data-is_autoselect' );

			//console.log( '3' );

			// Auto click fist availale slot.
			if ( 0 < first_slot.length && first_slot.has( 'a' ) ) {
				//first_slot.find( 'a' ).trigger( 'click' );

				// Get selected slot 'data-slot' value.
				var time_value = first_slot.data( 'slot' );

				// Set selected time on slot picker.
				wc_appointments_time_picker.set_selected_time( form, time_value );

			// Go to next day when autoselect is active.
			} else if ( is_autoselect ) {
				selected_day.nextAll( 'td:not(.ui-state-disabled)' ).add( selected_day.closest( 'tr' ).nextAll().find( 'td' ) ).slice( 0, 1 ).trigger( 'click' );

				//selected_day.prop( 'title', wc_appointment_form_params.i18n_date_unavailable );
				//selected_day.addClass( 'not_appointable' );
				//selected_day.addClass( 'ui-datepicker-unselectable' );
				//selected_day.addClass( 'ui-state-disabled' );
			}
		},
		hide_time_slots: function( element ) {
			var form            = $( element ).closest( 'form' );
			var slot_picker     = form.find( '.slot-picker' );
			var fieldset        = form.find( 'fieldset' );
			var year            = parseInt( fieldset.find( 'input.appointment_date_year' ).val(), 10 );
			var month           = parseInt( fieldset.find( 'input.appointment_date_month' ).val(), 10 );
			var day             = parseInt( fieldset.find( 'input.appointment_date_day' ).val(), 10 );

			if ( !year || !month || !day ) {
				return;
			}

			// hide slots
			slot_picker.hide();
		},
		reset_time_slots: function( element ) {
			var form            = $( element ).closest( 'form' );
			var slot_picker     = form.find( '.slot-picker' );

			// Disable submit button.
			form.find( '.wc-appointments-appointment-form-button' ).prop( 'disabled', true );

			// Hide addons.
			form.find( '.wc-appointments-appointment-hook-after' ).hide();

			// Empty previous selected slot.
			slot_picker.find( 'li' ).removeClass( 'selected' );

			// hide slots
			slot_picker.hide();
		}
	};

	wc_appointments_time_picker.init();
} );
