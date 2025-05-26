/* global wc_appointments_availability_filter_params, moment */
jQuery( function( $ ) {
	'use strict';

	/*
	if ( !window.console ) {
		window.console = {
			log : function(str) {
				alert(str);
			}
		};
	}
	*/

	var wc_appointments_availability_filter = {
		init: function() {
			var date_pickers = $( '.widget_availability_filter .date-picker' );

			if ( !date_pickers.length ) {
				return;
			}

			date_pickers.each( function() {
				var picker = $( this );

				picker.datepicker( {
					dateFormat: 'yy-mm-dd',
					numberOfMonths: 1,
					showOtherMonths: true,
					changeMonth: true,
					showButtonPanel: false,
					minDate: 0,
					onSelect: wc_appointments_availability_filter.onSelect,
					firstDay: wc_appointments_availability_filter_params.firstDay,
					closeText: wc_appointments_availability_filter_params.closeText,
					currentText: wc_appointments_availability_filter_params.currentText,
					prevText: wc_appointments_availability_filter_params.prevText,
					nextText: wc_appointments_availability_filter_params.nextText,
					monthNames: wc_appointments_availability_filter_params.monthNames,
					monthNamesShort: wc_appointments_availability_filter_params.monthNamesShort,
					dayNames: wc_appointments_availability_filter_params.dayNames,
					dayNamesShort: wc_appointments_availability_filter_params.dayNamesShort,
					dayNamesMin: wc_appointments_availability_filter_params.dayNamesMin,
					/*dayNamesMin: wc_appointments_availability_filter_params.dayNamesShort,*/
					isRTL: wc_appointments_availability_filter_params.isRTL
				} );
			} );
		},

		onSelect: function( date ) {
			var form_field  = $( this ).closest( '.date_picker_inner' );
			var parsed_date = date.split( '-' );
			var year        = parseInt( parsed_date[0], 10 );
			var month       = parsed_date[1];
			var day         = parsed_date[2];
			var ymdIndex    = year + '-' + month + '-' + day;

			//console.log( ymdIndex );

			// Localize with Moment.
			var moment_date = moment.utc( date );
			var local_date  = moment_date.format( wc_appointments_availability_filter_params.dateFormat );
			//console.log( local_date );

			// Set fields
			form_field.find( 'input.date-picker' ).val( '' );
			form_field.find( 'input.date-picker-field' ).val( '' );
			form_field.find( 'input.date-picker' ).val( local_date ).trigger( 'change' );
			form_field.find( 'input.date-picker-field' ).val( ymdIndex ).trigger( 'change' );
		}
	};

	wc_appointments_availability_filter.init();
} );
