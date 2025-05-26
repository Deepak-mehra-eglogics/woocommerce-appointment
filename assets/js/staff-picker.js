/* globals wca_get_querystring */
jQuery( function( $ ) {
	'use strict';

	var wc_appointments_staff_picker = {
		init: function() {
			$( 'body' ).on( 'change', '#wc_appointments_field_staff', this.select_staff );

			// Run on each form instance.
			$( '.wc-appointments-appointment-form' ).each( function() {
				var form         = $( this ).closest( 'form' );
				var staff_label  = form.find( '.wc_appointments_field_staff' );
				var staff_field  = staff_label.find( 'select' );
				var staff_select = staff_field.select2( {
					escapeMarkup: function( markup ) {
						// Do not escape HTML in the select options text.
						return markup;
					},
					templateResult: function( data ) {
						//console.log( wc_appointments_staff_picker.template_staff(data) );
						return wc_appointments_staff_picker.template_staff( data );
					}
					/*
					templateSelection: function( data ) {
						//console.log( wc_appointments_staff_picker.template_staff(data) );
						return wc_appointments_staff_picker.template_staff( data );
					},
					*/
					//templateResult: wc_appointments_staff_picker.template_staff,
					//templateSelection: wc_appointments_staff_picker.template_staff,
					//minimumResultsForSearch: 6 // I only want the search box if there are enough results
				} );

				// if staff is in querystring, then set it.
				if ( null !== wca_get_querystring( 'staff' ) ) {
					staff_select.val( wca_get_querystring( 'staff' ) ).trigger( 'change' );
				}
			} );
		},
		template_staff: function( state ) {
			if ( !state.id ) {
				return state.text;
			}

			var html5data = state.element;

			if ( $( html5data ).data( 'avatar' ) ) {
				var template = '<div><img class="staff-avatar" src="' + $( html5data ).data( 'avatar' ) + '" alt="' + state.text + '" />' + state.text + '</div>';
				return template;
			}

			return state.text;
		},
		select_staff: function() {
			var form = $( this ).closest( 'form' );

			form.find( '.wc-appointments-appointment-form-button' ).prop( 'disabled', true );

			form.triggerHandler( 'staff-selected' );
		}
	};

	wc_appointments_staff_picker.init();
} );
