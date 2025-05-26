<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce One Page Checkout integration class.
 * https://woocommerce.com/products/woocommerce-one-page-checkout/
 *
 * Last compatibility check: 2.8.2
 */
class WC_Appointments_Integration_WCOPC {

	/**
	 * Constructor
	 */
	public function __construct() {
 		add_action( 'wcopc_appointment_add_to_cart', array( __CLASS__, 'opc_single_add_to_cart_appointment' ) );

 		// Unhook 'WC_Appointments_Cart::add_to_cart_redirect'
		// from 'add_to_cart_redirect' in OPC pages, to prevent redirection
		// to the default cart when checking appointment availability
 		if (
			isset( $_POST['is_opc'] )
			&& (
				( isset( $_REQUEST['action'] ) && 'woocommerce_checkout' === $_REQUEST['action'] )
				||
				( isset( $_REQUEST['wc-ajax'] ) && 'checkout' === $_REQUEST['wc-ajax'] )
			)
		) {
 			remove_action( 'add_to_cart_redirect', 'WC_Appointments_Cart::add_to_cart_redirect' );
 		}
 	}

 	/**
 	 * OPC Single-product appointments add-to-cart template
 	 *
 	 * @param  int  $opc_post_id
 	 * @return void
 	 */
 	public static function opc_single_add_to_cart_appointment( $opc_post_id ) {
 		global $product;

 		ob_start();

 		// Prepare form
 		$appointment_form = new WC_Appointment_Form( $product );

		// Get template
		wc_get_template(
			'single-product/add-to-cart/appointment.php',
			array(
				'appointment_form' => $appointment_form,
			),
			'',
			WC_APPOINTMENTS_TEMPLATE_PATH
		);

 		echo str_replace(
			array( '<form class="cart" method="post" enctype=\'multipart/form-data\'', '</form>' ),
			array( '<div class="cart" ', '</div>' ),
			ob_get_clean()
		); // WPCS: XSS ok.
 	}
}

$GLOBALS['wc_appointments_integration_wcopc'] = new WC_Appointments_Integration_WCOPC();
