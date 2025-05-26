/* globals wc_appointment_form_params */
jQuery( function( $ ) {
	'use strict';

	var xhr        = [];
	var form_count = 1;
	var wc_appointments_appointment_form = {
		init: function() {
			var appointment_forms = $( '.wc-appointments-appointment-form' );
			appointment_forms.show().prop( 'disabled', false );
			$( '.wc-appointments-appointment-form-button' ).show().removeClass( 'disabled' ).prop( 'disabled', true );

			form_count = appointment_forms.length;

			// Run on each form instance.
			appointment_forms.each( function() {
				var form = $( this ).closest( 'form' );

				// Run date picker on each instance.
				wc_appointments_appointment_form.appointment_form_init( form );
			} );
		},
		appointment_form_init: function( form ) {
			var picker        = form.find( '.picker:eq(0)' );
			var duration_unit = picker.attr( 'data-duration_unit' );

			// Disable submit button.
			form.find( '.wc-appointments-appointment-form-button' ).prop( 'disabled', true );

			//console.log(duration_unit);

			// if duration unit is day then there is no time picker present
			// bind cost calculation on the staff and date selection only
			if ( 'day' === duration_unit ) {
				form.on( 'date-selected', this.calculate_costs )
					.on( 'staff-selected', this.calculate_costs )
					.on( 'addon-duration-changed', this.calculate_costs )
					.on( 'addon-costs-changed', this.calculate_costs );
			// if duration unit is months then there is month picker present
			// bind cost calculation on the month selection
			} else if ( 'month' === duration_unit ) {
				form.on( 'month-selected', this.calculate_costs )
					.on( 'staff-selected', this.calculate_costs )
					.on( 'addon-duration-changed', this.calculate_costs )
					.on( 'addon-costs-changed', this.calculate_costs );
			// if duration unit is hours or minutes then there is time picker present
			// bind cost calculation on the staff and time selection
			} else {
				form.on( 'time-selected', this.calculate_costs )
				    .on( 'addon-costs-only-changed', this.calculate_costs );
			}

			form.find( '.quantity' ).on( 'change', 'input, select', this.calculate_costs );

			// Update querystring.
			// Only when 1 form is present on page.
			// console.log( form_count );
			if ( 1 >= form_count ) {
				form.on( 'month-selected', this.update_querystring )
					.on( 'date-selected', this.update_querystring )
					.on( 'time-selected', this.update_querystring )
					.on( 'staff-selected', this.update_querystring );
				//.on( 'addon-selected', this.update_querystring );
			}

			// Add-ons changed.
			form.on( 'updated_addons', this.selected_addon );

			var addons_totals = form.find( '#product-addons-total' );
			var addons_data   = addons_totals && addons_totals.data( 'price_data' ) ? addons_totals.data( 'price_data' ) : 0;

			//console.log( addons_totals );
			//console.log( addons_totals.data() );

			if ( addons_data.length ) {
				wc_appointments_appointment_form.selected_addon( 'preset', form );
			}

			// WooCommerce TM Extra Product Options integration.
			form.on( 'appointment-cost-success', this.tm_epo_integration );
		},
		selected_addon: function( type, form ) {
			if ( 'preset' !== type ) {
				form = $( this ).closest( 'form' );
			}

			// Make sure the check the button state
			// to prevent enabling the button when it should be disabled.
			var check_add_to_cart_button_state = form.find( '.single_add_to_cart_button' ).is( ':disabled' );

			form.find( '.single_add_to_cart_button' ).prop( 'disabled', true );

			// Access selected addons data.
			var addons_totals         = form.find( '#product-addons-total' );
			var addons_data           = addons_totals && addons_totals.data( 'price_data' ) ? addons_totals.data( 'price_data' ) : 0;
			var addons_field_duration = form.find( '#wc_appointments_field_addons_duration' );
			var addons_field_cost     = form.find( '#wc_appointments_field_addons_cost' );
			var product_qty           = 0 < parseInt( form.find( '.input-text.qty' ).val() ) ? parseInt( form.find( '.input-text.qty' ).val() ) : 1;
			var addons_duration       = 0;
			var addons_costs          = 0;
			var addons_duration_only  = false;
			var addons_costs_only     = false;

			//console.log( product_qty );
			//console.log( addons_data );

			// Loop through selected addons.
			if ( addons_data.length ) {
				$.each( addons_data, function( i, addon ) {
					var addon_duration = 0;
					if ( addon.duration_raw_pu && 0 !== parseInt( addon.duration_raw_pu ) ) {
						addon_duration = addon.duration_raw_pu;
					} else if ( addon.duration_raw && 0 !== parseInt( addon.duration_raw ) ) {
						addon_duration = addon.duration_raw;
					}
					if ( 0 !== parseInt( addon_duration ) ) {
						switch ( addon.duration_type ) {
							case 'flat_time':
								addons_duration += addon_duration;
								break;
							case 'quantity_based':
								addons_duration += addon_duration * product_qty;
								break;
							default:
								addons_duration += addon_duration;
								break;
						}
					}

					var addon_cost        = 0;
					var addon_cost_raw    = addon.cost_raw;
					var addon_cost_raw_pu = addon.cost_raw_pu;
					if ( addon_cost_raw_pu && 0 !== parseFloat( addon_cost_raw_pu.toString().replace( /,/g, '.' ) ) ) {
						addon_cost = addon_cost_raw_pu;
					} else if ( addon_cost_raw && 0 !== parseFloat( addon_cost_raw.toString().replace( /,/g, '.' ) ) ) {
						addon_cost = addon_cost_raw;
					}
					if ( 0 !== parseFloat( addon_cost.toString().replace( /,/g, '.' ) ) ) {
						switch ( addon.price_type ) {
							case 'flat_fee':
							case 'percentage_based':
								addons_costs += parseFloat( addon_cost / product_qty );
								break;
							case 'quantity_based':
								addons_costs += addon_cost;
								break;
							default:
								addons_costs += addon_cost;
								break;
						}
					}
				} );
			}

			// Save addon duration to input field.
			if ( 0 < addons_field_duration.length ) {
				if ( 0 !== addons_duration ) {
					addons_field_duration.val( addons_duration );
				} else {
					addons_field_duration.val( 0 );
				}
			}

			// Save addon costs to input field.
			if ( 0 < addons_field_cost.length ) {
				if ( 0 !== addons_costs ) {
					addons_field_cost.val( addons_costs );
				} else {
					addons_field_cost.val( 0 );
				}
			}

			//console.log( addons_duration );
			//console.log( addons_costs );

			// Duration change triggers.
			if ( 0 !== addons_duration && wc_appointment_form_params.duration_changed !== addons_duration ) {
				wc_appointment_form_params.duration_changed = addons_duration;
				form.triggerHandler( 'addon-duration-changed', [ addons_duration ] );
				addons_duration_only = true;
			} else if ( 0 !== addons_duration && wc_appointment_form_params.duration_changed === addons_duration ) {
				wc_appointment_form_params.duration_changed = addons_duration;
				form.triggerHandler( 'addon-duration-unchanged', [ addons_duration ] );
			} else if ( 0 === addons_duration && !wc_appointment_form_params.duration_changed ) {
				wc_appointment_form_params.duration_changed = false;
				form.triggerHandler( 'addon-duration-unchanged', [ addons_duration ] );
			} else if ( 0 === addons_duration && wc_appointment_form_params.duration_changed !== addons_duration ) {
				wc_appointment_form_params.duration_changed = false;
				form.triggerHandler( 'addon-duration-changed', [ addons_duration ] );
				addons_duration_only = true;
			} else {
				wc_appointment_form_params.duration_changed = false;
				form.triggerHandler( 'addon-duration-unchanged', [ addons_duration ] );
			}

			// Costs change triggers.
			if ( 0 !== addons_costs && wc_appointment_form_params.costs_changed !== addons_costs ) {
				wc_appointment_form_params.costs_changed = addons_costs;
				form.triggerHandler( 'addon-costs-changed', [ addons_costs ] );
				addons_costs_only = true;
			} else if ( 0 !== addons_costs && wc_appointment_form_params.costs_changed === addons_costs ) {
				wc_appointment_form_params.costs_changed = addons_costs;
				form.triggerHandler( 'addon-costs-unchanged', [ addons_costs ] );
			} else if ( 0 === addons_costs && !wc_appointment_form_params.costs_changed ) {
				wc_appointment_form_params.costs_changed = false;
				form.triggerHandler( 'addon-costs-unchanged', [ addons_costs ] );
			} else if ( 0 === addons_costs && wc_appointment_form_params.costs_changed !== addons_costs ) {
				wc_appointment_form_params.costs_changed = false;
				form.triggerHandler( 'addon-costs-changed', [ addons_costs ] );
				addons_costs_only = true;
			} else {
				wc_appointment_form_params.costs_changed = false;
				form.triggerHandler( 'addon-costs-unchanged', [ addons_costs ] );
			}

			// Only costs or only duration changed.
			if ( addons_costs_only && !addons_duration_only ) {
				form.triggerHandler( 'addon-costs-only-changed', [ addons_costs ] );
			} else if ( !addons_costs_only && addons_duration_only ) {
				form.triggerHandler( 'addon-duration-only-changed', [ addons_duration ] );
			} else if ( addons_costs_only && addons_duration_only ) {
				form.triggerHandler( 'addon-duration-and-costs-changed', [ addons_duration ] );
			}

			// Enable book now button when addons have no cost or duration.
			if ( !addons_costs_only && !addons_duration_only && !check_add_to_cart_button_state ) {
				form.find( '.single_add_to_cart_button' ).prop( 'disabled', false );
			}

			form.triggerHandler( 'addon-selected' );
		},
		calculate_costs: function() {
			var name            = $( this ).attr( 'name' );
			var form            = $( this ).closest( 'form' );
			var required_fields = form.find( 'input.required_for_calculation' );
			var filled          = true;
			var fieldset        = form.find( 'fieldset' );
			var picker          = fieldset.find( '.picker:eq(0)' );
			var index           = form.index();

			//console.log( event );

			// what does this line do?
			// when is range picker present?
			// if it is future implementation it should be removed
			if ( picker.data( 'is_range_picker_enabled' ) ) {
				if ( 'wc_appointments_field_duration' !== name ) {
					return;
				}
			}

			$.each( required_fields, function( index, field ) {
				var value = $( field ).val();

				if ( !value ) {
					filled = false;
				}
			} );

			if ( !filled ) {
				form.find( '.wc-appointments-appointment-cost' ).hide();
				form.find( '.wc-appointments-appointment-hook-after' ).hide();

				return;
			}

			form.find( '.wc-appointments-appointment-cost' ).block( { message: null, overlayCSS: { background: '#fff', backgroundSize: '16px 16px', opacity: 0.6 } } ).show();
			form.find( '.single_add_to_cart_button' ).prop( 'disabled', true );

			// Prevent multiple requests at once.
			// if ( xhr[index] ) xhr[index].abort();
			xhr[index] = $.ajax( {
				type: 'POST',
				url: wc_appointment_form_params.ajax_url,
				data: {
					action: 'wc_appointments_calculate_costs',
					form: form.serialize()
				},
				success: function( code ) {
					if ( '{' !== code.charAt( 0 ) ) {
						// console.log( code );
						code = '{' + code.split( /{(.+)?/ )[1];
					}

					var result = JSON.parse( code );

					if ( 'ERROR' === result.result ) {
						form.find( '.wc-appointments-appointment-cost' ).html( result.html );
						form.find( '.wc-appointments-appointment-cost' ).unblock();
						form.find( '.single_add_to_cart_button' ).prop( 'disabled', true );
					} else if ( 'SUCCESS' === result.result ) {
						form.find( '.wc-appointments-appointment-cost' ).html( result.html );
						form.find( '.wc-appointments-appointment-cost' ).unblock();
						form.find( '.wc-appointments-appointment-hook-after' ).show();
						form.find( '.single_add_to_cart_button' ).prop( 'disabled', false );
						form.triggerHandler( 'appointment-cost-success', [form, result.html] );
					} else {
						form.find( '.wc-appointments-appointment-cost' ).hide();
						form.find( '.wc-appointments-appointment-hook-after' ).hide();
						form.find( '.single_add_to_cart_button' ).prop( 'disabled', true );
						// console.log( code );
					}
				},
				error: function() {
					form.find( '.wc-appointments-appointment-cost' ).hide();
					form.find( '.single_add_to_cart_button' ).prop( 'disabled', true );
				},
				dataType: 'html'
			} );
		},

		update_querystring: function( action ) {
			var form = $( this ).closest( 'form' );

			if ( !wc_appointment_form_params.is_admin ) {
				wc_appointments_appointment_form.set_querystring_data( form, action );
			}
		},

		// function which changes the url querystring parameters without reloading the page (if supported in browser)
		set_querystring_data: function( form, action ) {
			// if browser supports this feature, use it
			if ( window.history && window.history.pushState ) {
				var year    = form.find( 'input.appointment_date_year' ).val();
				var month   = form.find( 'input.appointment_date_month' ).val();
				var day     = form.find( 'input.appointment_date_day' ).val();
				var time    = form.find( '#wc_appointments_field_start_date' );
				var date_m  = form.find( 'input#wc_appointments_field_start_date' ).val();
				var staff   = form.find( '#wc_appointments_field_staff' );
				var date    = date_m && !year && !month && !day ? date_m : year + '-' + month + '-' + day;
				var time_v  = 'undefined' !== typeof time.attr( 'data-value' ) ? time.attr( 'data-value' ) : '';

				// URLs.
				var date_url  = ( year && month && day ) ? date : null;
				var time_url  = ( 0 < time.length && '' !== time.val() ) ? time_v : null;
				var staff_url = ( 0 < staff.length && '' !== staff.val() ) ? staff.val() : null;

				// Date.
				var old_url = window.location.href;
				var tem_url = wc_appointments_appointment_form.replace_url_parameter( 'date', date_url, old_url );
				var new_url = date_url && new_url ? new_url : ( date_url && !new_url ? tem_url : old_url );

				// Time.
				if ( time_url ) {
					new_url = wc_appointments_appointment_form.replace_url_parameter( 'time', time_url, new_url );
				}

				// Staff.
				// trigger only when staff change occurs.
				if ( 'staff-selected' === action.type ) {
					if ( staff_url ) {
						new_url = wc_appointments_appointment_form.replace_url_parameter( 'staff', staff_url, new_url );
					} else {
						new_url = wc_appointments_appointment_form.remove_url_parameter( 'staff', new_url );
					}
				}

				// Querystring.
				window.history.replaceState( { date: date_url, time: time_url, staff: staff_url }, null, new_url );
			}
		},

		// function which checks passed url and adds or replaces querystring in it
		replace_url_parameter: function( param, value, href ) {
			// Count all parameters.
			var matches_as_qs_params = href.match( /[a-z\d]+=[a-z\d]+/gi );
			var count_qs_params 	 = matches_as_qs_params ? matches_as_qs_params.length : 0;

			// Count same parameters.
			var matches_as_qs_param = href.match( new RegExp( '[?&]' + param + '=([^&]+)' ) );
			var count_qs_param 	    = matches_as_qs_param ? matches_as_qs_param.length : 0;

		    if ( null === wca_get_querystring( param ) ) {
				href += ( 0 < count_qs_params && 2 > count_qs_param ? '&' : '?' ) + param + '=' + encodeURIComponent( value );
		    } else {
				var regex = new RegExp( '(' + param + '=)[^&]+' );
				href = href.replace( regex, '$1' + encodeURIComponent( value ) );
		    }

		    return href;
		},

		// Just pass in the param you want to remove from the URL and the original URL value,
		// and the function will strip it out for you.
		remove_url_parameter: function( key, sourceURL ) {
		    var rtn = sourceURL.split( '?' )[0];
		    var param;
		    var params_arr = [];
		    var queryString = ( -1 !== sourceURL.indexOf( '?' ) ) ? sourceURL.split( '?' )[1] : '';

			if ( '' !== queryString ) {
		        params_arr = queryString.split( '&' );
		        for ( var i = params_arr.length - 1; 0 <= i; i -= 1 ) {
		            param = params_arr[i].split( '=' )[0];
		            if ( param === key ) {
		                params_arr.splice( i, 1 );
		            }
		        }
		        rtn = rtn + '?' + params_arr.join( '&' );
		    }

		    return rtn;
		},

		// WooCommerce TM Extra Product Options integration.
		tm_epo_integration: function( event, form, result_html ) {
			if ( window.hasOwnProperty( 'tm_epo_js' ) || window.hasOwnProperty( 'TMEPOJS' ) ) {
				var tm_epo_totals_form = form.find( '.tc-totals-form' );
				var tm_epo_totals_container = form.find( '.tc-epo-totals' );

				if ( tm_epo_totals_form && tm_epo_totals_container ) {
					//var tm_epo_pp = parseFloat( tm_epo_totals_container.data( 'tc_totals_ob' ).options_total_price );
					var tm_epo_pp = parseFloat( $.epoAPI.util.unformat( $( '<div>' + result_html + '</div>' ).find( '.amount' ).text() ) );

					tm_epo_totals_container.data( 'price', tm_epo_pp );
					tm_epo_totals_form.find( '.cpf-product-price' ).val( tm_epo_pp );
					form.trigger( {
						'type': 'tm-epo-update'
					} );
				}
			}
		}
	};

	wc_appointments_appointment_form.init();
} );

// Gets parameter value from the querystring using its key.
/* exported wca_get_querystring */
function wca_get_querystring( key ) {
	'use strict';

	key = key.replace( '/[*+?^$.\\[\\]{}()|\\/]/g', '\\$&' ); // escape RegEx meta chars
	var match = location.search.match( new RegExp( '[?&]' + key + '=([^&]+)(&|$)' ) );

	return match && decodeURIComponent( match[1].replace( /\+/g, ' ' ) );
}

// Checks if date string is valid.
/* exported wca_is_valid_date */
function wca_is_valid_date( string ) {
	'use strict';

	var comp = string.split( '-' );
	var y = parseInt( comp[0], 10 );
	var m = parseInt( comp[1], 10 );
	var d = parseInt( comp[2], 10 );
	var date = new Date( y, m - 1, d );

	if ( y === date.getFullYear() && m === date.getMonth() + 1 && d === date.getDate() ) {
		return true;
	} else {
		return false;
	}
}

// Get browser cookie by name.
/* exported wca_get_cookie */
function wca_get_cookie( name ) {
	'use strict';

	var nameEQ = name + '=';
	var ca = document.cookie.split( ';' );
	for ( var i = 0; ca.length > i; i++ ) {
		var c = ca[i];
		while ( ' ' === c.charAt( 0 ) ) {
			c = c.substring( 1, c.length );
		}
		if ( 0 === c.indexOf( nameEQ ) ) {
			return c.substring( nameEQ.length, c.length );
		}
	}

	return null;
}

// Set browser cookie by name, value and expiration in days.
/* exported wca_set_cookie */
function wca_set_cookie( name, value, days ) {
	'use strict';

	var expires = '';

	if ( days ) {
		var date = new Date();
		date.setTime( date.getTime() + ( days * 24 * 60 * 60 * 1000 ) );
		expires = '; expires=' + date.toUTCString();
	}

	document.cookie = name + '=' + ( value || '' ) + expires + '; path=/';
}
