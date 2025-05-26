<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Official WooCommerce Stripe Gateway integration class.
 * https://wordpress.org/plugins/woocommerce-gateway-stripe/
 *
 * Last compatibility check: 8.2.0
 */
class WC_Appointments_Integration_Stripe {

	/**
	 * Constructor
	 */
	public function __construct() {
		// Checks to make sure product type is supported.
		add_filter( 'wc_stripe_payment_request_supported_types', [ $this, 'payment_request_supported_types' ] );
		add_filter( 'wc_stripe_hide_payment_request_on_product_page', [ $this, 'hide_payment_request_on_product_page' ], 10, 3 );
	}

	/**
	 * Add appointments as supported type.
	 *
 	 * @since 4.19.1
 	 * @return array
	 */
	public function payment_request_supported_types( $supported_types ) {
		$supported_types[] = 'appointment';

		#error_log( var_export( $supported_types, true ) );

		return $supported_types;
	}

	/**
	 * Hide payment request button on product page
	 *
 	 * @since 4.19.1
 	 * @return array
	 */
	public function hide_payment_request_on_product_page( $should_show_on_product_page, $post ) {
		$appointable_product = wc_get_product( $post->ID );

		#error_log( var_export( is_wc_appointment_product( $appointable_product ), true ) );

		if ( is_wc_appointment_product( $appointable_product ) ) {
			return true;
		}

		return $should_show_on_product_page;
	}

}

$GLOBALS['wc_appointments_integration_stripe'] = new WC_Appointments_Integration_Stripe();
