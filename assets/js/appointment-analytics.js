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

/**
 * Add a PRODUCTS item to a dropdown to a report.
 *
 * @param {Array} filterConfig - set of filters in a report.
 * @return {Array} amended set of filters.
 */
const addProductsFilters = ( filterConfig ) => {
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
};

addFilter( 'woocommerce_admin_products_report_filters', 'woocommerce-appointments', addProductsFilters );

//////////////////////////////////////////////////////////

/**
 * Add a MAIN dropdown to a report.
 *
 * @param {Array} filters - set of filters in a report.
 * @return {Array} amended set of filters.
 */
/*
const addStaffFilters = ( filters ) => {
	'use strict';

	return [
		{
			label: wc_appointments_analytics_params.i18n_staff,
			staticParams: [],
			param: 'staff',
			showFilters: () => true,
			defaultValue: 'all',
			filters: [ ...( wcSettings.multiStaff || [] ) ]
		},
		...filters
	];
};

addFilter( 'woocommerce_admin_revenue_report_filters', 'woocommerce-appointments', addStaffFilters );
addFilter( 'woocommerce_admin_orders_report_filters', 'woocommerce-appointments', addStaffFilters );
addFilter( 'woocommerce_admin_products_report_filters', 'woocommerce-appointments', addStaffFilters );
addFilter( 'woocommerce_admin_categories_report_filters', 'woocommerce-appointments', addStaffFilters );
addFilter( 'woocommerce_admin_coupons_report_filters', 'woocommerce-appointments', addStaffFilters );
addFilter( 'woocommerce_admin_taxes_report_filters', 'woocommerce-appointments', addStaffFilters );
addFilter( 'woocommerce_admin_dashboard_filters', 'woocommerce-appointments', addStaffFilters );
*/
/**
 * Add 'staff' to the list of persisted queries so that the parameter remains
 * when navigating from report to report.
 *
 * @param {Array} params - array of report slugs.
 * @return {Array} - array of report slugs including 'staff'.
 */
const persistQueries = ( params ) => {
	'use strict';

	params.push( 'staff' );
	return params;
};

addFilter( 'woocommerce_admin_persisted_queries', 'woocommerce-appointments', persistQueries );
