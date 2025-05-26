/* globals wp, wc_appointments_analytics_params */

/**
 * External dependencies
 */
//import { __ } from '@wordpress/i18n';
//import { addFilter } from '@wordpress/hooks';
//import { Icon, chevronRight } from '@wordpress/icons';
//import Calendar from 'gridicons/dist/calendar';

// Avoid component compilers like Webpack for now. Too much crap.
//const { __ } = wp.i18n;
const { addFilter } = wp.hooks;

// Filter to add a new item in the Analytics dropdows.
addFilter( 'woocommerce_admin_products_report_filters', 'woocommerce-appointments/admin/analytics', ( filterConfig ) => {
	'use strict';

	let valueToPush = { };

	valueToPush.label = wc_appointments_analytics_params.i18n_appointable_products;
	valueToPush.value = 'appointments';

	filterConfig.forEach( function( obj ) {
		if ( obj.filters ) {
		  obj.filters.push( valueToPush );
		}
	} );

	return filterConfig;
}, 10 );

/*
// Filter to add a new item in the Product Type dropdowns.
// Add custom product types to Add Products onboarding list.
addFilter( 'experimental_woocommerce_tasklist_product_types', 'woocommerce-appointments', (productTypes) => [
	...productTypes,
	{
		key: 'appointment',
		title: __( 'Appointable Product', 'woocommerce-appointments' ),
		content: __( 'An item that can be scheduled. Variations include cost and availability.', 'woocommerce-appointments' ),
		//before: <Calendar />,
		//after: <Icon icon={chevronRight} />,
	},
], 10 );
*/
