/**
 * External dependencies
 */
//import { registerPaymentMethod } from '@woocommerce/blocks-registry';
//import { getSetting } from '@woocommerce/settings';
//import { decodeEntities } from '@wordpress/html-entities';

// Avoid component compilers like Webpack for now. Too much crap.
const { registerPaymentMethod } = wc.wcBlocksRegistry;
const { getSetting } = wc.wcSettings;
const { decodeEntities } = wp.htmlEntities;

/**
 * Internal dependencies.
 */
const PAYMENT_METHOD_NAME = 'wcappointmentsgateway';
const settings            = getSetting( 'wcappointmentsgateway_data', {} );
const label               = decodeEntities( settings.title );
const description         = decodeEntities( settings.description || '' );
const orderButtonText     = decodeEntities( settings.order_button_text );

/**
 * Content component.
 */
const Content = () => {
	return description;
};

/**
 * Label component.
 *
 * @param {*} props Props from payment API.
 */
/*
const Label = ( props ) => {
	const { PaymentMethodLabel } = props.components;
	return <PaymentMethodLabel text={ label } />;
};
*/

/**
 * React Content element.
 */
const content_react = React.createElement(
	Content,
	null,
	description
);

/**
 * React Label element.
 */
const label_react = React.createElement(
	'span',
    null,
	label
);

/**
 * Options for the new PAYMENT_METHOD_NAME.
 */
const appointmentsPaymentMethod = {
	name: PAYMENT_METHOD_NAME,
	label: label_react,
	content: content_react,
	edit: content_react,
	canMakePayment: () => true,
	ariaLabel: label,
	supports: {
		features: settings.supports,
	},
	placeOrderButtonLabel: orderButtonText,
};

// Some testing to do.
//console.log( wc.wcBlocksRegistry );
//console.log( wc.wcBlocksRegistry.getPaymentMethods() );
//console.log( wc.wcBlocksData );
//console.log( appointmentsPaymentMethod );
//console.log( settings );

if ( settings.is_enabled ) {
	registerPaymentMethod( appointmentsPaymentMethod );
}



/*
const { registerCheckoutFilters } = wc.blocksCheckout;
registerCheckoutFilters( 'appointments-store-extension', {
	proceedToCheckoutButtonLabel: ( value, extensions, { cart } ) => {
		if ( ! cart.items ) {
			return value;
		}
		const isSunglassesInCart = cart.items.some(
			( item ) => item.name === 'Sunglasses'
		);

		return 'Proceed to 😎 checkout';
	},
	itemName: ( value, extensions, { cartItem, args  } ) => {
		console.log( cartItem );
		console.log( cartItem.quantity );
		console.log( args  );

		return cartItem.quantity + 'x ' + value;
	}
} );
*/
