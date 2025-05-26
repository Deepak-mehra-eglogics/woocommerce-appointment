/* globals wc_appointments_my_account_params */

// Fire when all content is fully loaded.
document.addEventListener( 'DOMContentLoaded', function() {
	'use strict';

	// Make sure customer confirms cancellation.
	// Both classes (.woocommerce-button and .cancel) are required.
	var cancelButtons = document.getElementsByClassName( 'woocommerce-button cancel' );
	if ( cancelButtons ) {
		// Loop through all buttons on document.
		for ( var i = 0, l = cancelButtons.length; i < l; i++ ) {
			cancelButtons[i].addEventListener( 'click', function( ev ) {
				if ( !confirm( wc_appointments_my_account_params.i18n_confirmation ) ) {
					ev.preventDefault();
				}
			} );
	    }
	}
} );
