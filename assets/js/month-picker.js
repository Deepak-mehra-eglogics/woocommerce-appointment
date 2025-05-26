/* globals wca_get_querystring */
jQuery( function( $ ) {
	'use strict';

	var wc_appointments_month_picker = {
		init: function() {
			// Run on each form instance.
			$( '.wc-appointments-appointment-form' ).each( function() {
				var form   = $( this ).closest( 'form' );
				var picker = form.find( '.picker:eq(0)' );

				form.on( 'addon-duration-changed', function( event, duration ) {
					// Update 'data-combined_duration'.
					var appointment_duration = parseInt( picker.attr( 'data-appointment_duration' ), 10 );
					var addon_duration = parseInt( duration, 10 );
					var combined_duration = parseInt( appointment_duration + addon_duration, 10 );

					picker.data( 'combined_duration', combined_duration );
					picker.data( 'addon_duration', duration );

					// Highlight next selected months.
					wc_appointments_month_picker.highlight_months( form );
				} );

				// Init picker.
				wc_appointments_month_picker.month_picker_init( form );
			} );
		},
		month_picker_init: function( form ) {
			var picker        = form.find( '.picker:eq(0)' );
			var is_autoselect = picker.attr( 'data-is_autoselect' );

			// Month manually selected.
			picker.on( 'click', 'a', function( event ) {
				// Prevent # href link action.
				event.preventDefault();

				// Get selected slot 'data-slot' value.
				var value = $( this ).parent().data( 'slot' );

				// Set selected month on slot picker.
				wc_appointments_month_picker.set_selected_month( form, value );
			} );

			// if month is in querystring, select it instead of the first month
			// it overrides autoselect setting
			if ( null !== wca_get_querystring( 'date' ) ) {
				var selected_slot = picker.find( 'li.slot a[data-value="' + wca_get_querystring( 'date' ) + '"]' ).not( '.slot_empty' );

				if ( 0 < selected_slot.length ) {
					// Auto click selected slot.
					selected_slot.trigger( 'click' );

					// Highlight next selected months.
					wc_appointments_month_picker.highlight_months( form );
				} else {
					wc_appointments_month_picker.autoselect_first_available_month( form );
				}
			// Auto select first available time
			} else if ( is_autoselect ) {
				wc_appointments_month_picker.autoselect_first_available_month( form );
			}
		},
		set_selected_month: function( form, value ) {
			var submit_button       = form.find( '.wc-appointments-appointment-form-button' );
			var picker              = form.find( '.picker:eq(0)' );
			var target              = form.find( '.picker:eq(0)' ).parent( 'div' ).find( 'input' );
			var selected_slot       = picker.find( '[data-slot="' + value + '"]' );
			var selected_slot_value = selected_slot.find( 'a' ).data( 'value' );

			// Fill selected slot value.
			target.val( selected_slot_value );

			// Disable submit button.
			submit_button.prop( 'disabled', true );

			// Fire up 'month-selected' trigger.
			form.triggerHandler( 'month-selected' );

			// Empty previous selected slot.
			picker.find( 'li' ).removeClass( 'selected' );

			// Add .selected class to selected slot.
			selected_slot.addClass( 'selected' );

			// Highlight next selected months.
			wc_appointments_month_picker.highlight_months( form );
		},
		autoselect_first_available_month: function( form ) {
			var picker     = form.find( '.picker:eq(0)' );
			var first_slot = picker.find( 'li.slot:not(".slot_empty,.fully_scheduled"):first' );

			if ( 0 < first_slot.length && first_slot.has( 'a' ) ) {
				// Auto click fist availale slot.
				first_slot.find( 'a' ).trigger( 'click' );

				// Highlight next selected months.
				wc_appointments_month_picker.highlight_months( form );
			}
		},
		highlight_months: function( form ) {
			var picker            = form.find( '.picker:eq(0)' );
			var selected_slot     = picker.find( 'li.selected' ).not( '.slot_empty' );
			var duration          = picker.attr( 'data-appointment_duration' );
			var combined_duration = picker.data( 'combined_duration' ) ? picker.data( 'combined_duration' ) : duration;
			var days_needed       = ( 1 > combined_duration ) ? 1 : combined_duration;
			var days_highlighted  = days_needed - 1;

			// Highlight next selected slots,
			// when duration is above 0.
			if ( 0 < days_needed ) {
				// Empty previous selected months.
				picker.find( 'li' ).removeClass( 'selected-month' );

				// Add .selected-month class to selected months.
				selected_slot.nextAll().slice( 0, days_highlighted ).addClass( 'selected-month' );
			}
		}
	};

	wc_appointments_month_picker.init();
} );
