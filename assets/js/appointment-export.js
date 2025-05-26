/*global wc_appointments_exporter_js_params */
( function( $, window ) {
	'use strict';

	/**
	 * appointmentExportForm handles the export process.
	 */
	var appointmentExportForm = function( $form ) {
		this.$form = $form;
		this.xhr   = false;

		// Initial state.
		this.$form.find( '.woocommerce-exporter-progress' ).val( 0 );

		// Methods.
		this.processStep = this.processStep.bind( this );

		// Events.
		$form.on( 'submit', { appointmentExportForm: this }, this.onSubmit );
	};

	/**
	 * Handle export form submission.
	 */
	appointmentExportForm.prototype.onSubmit = function( event ) {
		event.preventDefault();

		var currentDate    = new Date();
		var	day            = currentDate.getDate();
		var	month          = currentDate.getMonth() + 1;
		var	year           = currentDate.getFullYear();
		var	timestamp      = currentDate.getTime();
		var	filename       = 'wc-appointment-export-' + day + '-' + month + '-' + year + '-' + timestamp + '.csv';

		event.data.appointmentExportForm.$form.addClass( 'woocommerce-exporter__exporting' );
		event.data.appointmentExportForm.$form.find( '.woocommerce-exporter-progress' ).val( 0 );
		event.data.appointmentExportForm.$form.find( '.woocommerce-exporter-button' ).prop( 'disabled', true );
		event.data.appointmentExportForm.processStep( 1, $( this ).serialize(), '', filename );
	};

	/**
	 * Process the current export step.
	 */
	appointmentExportForm.prototype.processStep = function( step, data, columns, filename ) {
		var $this            = this;
		var	selected_columns = $( '#woocommerce-exporter-columns' ).val();
		var	export_start     = $( '#woocommerce-exporter-start' ).val();
		var	export_end       = $( '#woocommerce-exporter-end' ).val();
		var	export_product   = $( '#woocommerce-exporter-product' ).val();
		var	export_staff     = $( '#woocommerce-exporter-staff' ).val();
		var	export_addon     = $( '#woocommerce-exporter-addon:checked' ).length ? 1 : 0;

		$.ajax( {
			type: 'POST',
			url: wc_appointments_exporter_js_params.ajax_url,
			data: {
				form: data,
				action: 'woocommerce_do_ajax_appointment_export',
				step: step,
				columns: columns,
				selected_columns: selected_columns,
				export_start: export_start,
				export_end: export_end,
				export_product: export_product,
				export_staff: export_staff,
				export_addon: export_addon,
				filename: filename,
				security: wc_appointments_exporter_js_params.nonce_export_appointemnts
			},
			dataType: 'json',
			success: function( response ) {
				if ( response.success ) {
					if ( 'done' === response.data.step ) {
						$this.$form.find( '.woocommerce-exporter-progress' ).val( response.data.percentage );
						window.location = response.data.url;
						setTimeout( function() {
							$this.$form.removeClass( 'woocommerce-exporter__exporting' );
							$this.$form.find( '.woocommerce-exporter-button' ).prop( 'disabled', false );
						}, 2000 );
					} else {
						$this.$form.find( '.woocommerce-exporter-progress' ).val( response.data.percentage );
						$this.processStep( parseInt( response.data.step, 10 ), data, response.data.columns, filename );
					}
				}
			}
		} ).fail( function( response ) {
			window.console.log( response );
		} );
	};

	/**
	 * Function to call appointmentExportForm on jquery selector.
	 */
	$.fn.wc_appointment_export_form = function() {
		new appointmentExportForm( this ); // eslint-disable-line no-new, new-cap
		return this;
	};

	$( '.woocommerce-exporter' ).wc_appointment_export_form();

	// Date picker.
	$( '.date-picker' ).datepicker( {
		dateFormat: 'yy-mm-dd',
		numberOfMonths: 1,
		showOtherMonths: true,
		changeMonth: true,
		showButtonPanel: false,
		showOn: 'both',
		firstDay: wc_appointments_exporter_js_params.firstday,
		buttonText: '☶'
	} );
} )( jQuery, window );
