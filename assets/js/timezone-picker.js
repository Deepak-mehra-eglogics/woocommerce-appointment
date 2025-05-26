/* globals wc_appointment_form_params, wc_appointments_timezone_picker_args, moment, wca_get_cookie, wca_set_cookie */

// globally accessible for tests
var wc_appointments_timezone_picker = {};

jQuery( function( $ ) {
	'use strict';

	var timezone_cookie                         = wca_get_cookie( 'appointments_time_zone' );
	var wc_appointments_timezone_picker_object  = {
		init: function() {
			$( 'body' ).on( 'change', '#wc_appointments_field_timezone', this.select_timezone );
			$( 'a.selected-timezone' ).on( 'click', this.show_timezone_select );
			$( 'select[name="wc_appointments_field_timezone"]' ).on( 'select2:close', this.hide_timezone_select );

			// Run on each form instance.
			$( '.wc-appointments-appointment-form' ).each( function() {
				var form                = $( this ).closest( 'form' );
				var picker              = form.find( '.picker:eq(0)' );
				var timezone_label      = form.find( '.wc_appointments_field_timezone' );
				var timezone_field      = timezone_label.find( 'select' );
				var local_timezone      = moment.tz.guess( true ) || wc_appointment_form_params.server_timezone;
				var default_timezone    = timezone_field.val();
				var timezone_conversion = picker.attr( 'data-timezone_conversion' );

				// Init Select2 for timezone field.
				timezone_field.select2();

				if ( timezone_conversion && null === timezone_cookie ) {
					// Automatically set to customer timezones
					// when local timezone is different from site timezone.
					if ( local_timezone !== default_timezone ) {
						wca_set_cookie( 'appointments_time_zone', local_timezone, 30 );

						// Set label.
						timezone_label.find( '.selected-timezone' ).text( local_timezone.replace( '_', ' ' ) );
						// Set selected option.
						timezone_field.val( local_timezone );

						form.triggerHandler( 'timezone-selected' );
					}
					//console.log( local_timezone );
					//console.log( local_timezone.replace( '_', ' ' ) );
					//console.log( default_timezone );
					//console.log( timezone_cookie );
				}
			} );
		},
		show_timezone_select: function( event ) {
			var selection = $( this ).closest( '.wc_appointments_field_timezone' );

			if ( $( event.target ).hasClass( 'selected-timezone' ) ) {
				//console.log( "you clicked inside the box" );
				selection.find( 'select, .select2' ).css( 'display', 'block' );
				selection.find( 'select' ).select2( 'open' );
			} else {
				//console.log( "you clicked outside the box" );
				selection.find( 'select, .select2' ).css( 'display', 'none' );
			}
		},
		hide_timezone_select: function( event ) {
			var selection = $( this ).closest( '.wc_appointments_field_timezone' );

			//console.log( event );

			selection.find( 'select, .select2' ).css( 'display', 'none' );

			event.preventDefault();
		},
		select_timezone: function() {
			var timezone = $( this ).val();
			var form = $( this ).closest( 'form' );
			var params = {
				'wc-ajax': 'wc_appointments_set_timezone_cookie',
				'timezone': timezone,
				'security': wc_appointment_form_params.nonce_set_timezone_cookie
			};

			form.find( '.wc-appointments-appointment-form-button' ).prop( 'disabled', true );

			//console.log(timezone);

			// Select timezone.
			$.ajax( {
				context: this,
				url: wc_appointments_timezone_picker_args.ajax_url,
				method: 'POST',
				data: params
			} )
				.done( function( data ) {
					$( '.wc-appointments-appointment-form' ).each( function() {
						var timezone_form  = $( this ).closest( 'form' );
						var timezone_field = timezone_form.find( '.wc_appointments_field_timezone' );

						timezone_field.find( '.selected-timezone' ).text( data.replace( '_', ' ' ) );
						timezone_field.find( 'select, .select2' ).css( 'display', 'none' );

						//timezone_form.triggerHandler( 'timezone-selected' );
					} );

					form.triggerHandler( 'timezone-selected' );
				} );
		}
	};

	// export globally
	wc_appointments_timezone_picker = wc_appointments_timezone_picker_object;
	wc_appointments_timezone_picker.init();
} );
