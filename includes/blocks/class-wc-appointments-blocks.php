<?php
/**
 * WooCommerce Appointments Blocks Integration.
 */

namespace WooCommerce\Appointments\Blocks;

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry;
use Automattic\WooCommerce\Blocks\Registry\Container;
use Automattic\WooCommerce\Blocks\Assets\Api as AssetApi;
use Automattic\WooCommerce\Blocks\Package;
use Automattic\WooCommerce\StoreApi\StoreApi;
use Automattic\WooCommerce\StoreApi\Schemas\ExtendSchema;

/**
 * This class is responsible for integrating a new payment method when using WooCommerce Blocks.
 */
class WC_Appointments_Blocks {

	/**
	 * Constructor
	 */
	public function __construct() {
		// Include needed files.
		$this->includes();

		// Add woocommerce blocks support.
		$this->add_woocommerce_block_support();

		/**
		 * This function enables block based product list components to change
		 * the Add to cart button into a link to the product detail page.
		 */
		add_filter(
			'woocommerce_product_has_options',
			function ( $has_options, $product ) {
				if ( 'appointment' === $product->get_type() ) {
					return true;
				}
				return $has_options;
			},
			10,
			2
		);
	}

	/**
	 * Include needed files
	 */
	public function includes() {
		include_once __DIR__ . '/class-wc-appointments-gateway.php';
	}

	/**
	 * Add payment method block support
	 */
	public function add_woocommerce_block_support() {
		if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
			// Register payment method integrations.
			add_action( 'woocommerce_blocks_payment_method_type_registration', array( $this, 'register_payment_method_integrations' ) );
			$this->register_payment_methods();
			$this->register_payment_requirements();
		}
	}

	/**
	 * Register payment method integration
	 *
	 * @param PaymentMethodRegistry $payment_method_registry Payment method registry object.
	 */
	public function register_payment_method_integrations( PaymentMethodRegistry $payment_method_registry ) {
		$payment_method_registry->register(
			Package::container()->get( Appointments_Gateway::class )
		);
	}

	/**
	 * Register payment method
	 *
	 * @return void
	 */
	protected function register_payment_methods() {
		$container = Package::container();

		$container->register(
			Appointments_Gateway::class,
			function( Container $container ) {
				$asset_api = $container->get( AssetApi::class );
				return new Appointments_Gateway( $asset_api );
			}
		);
	}

	/**
	 * Register the payment requirements for blocks
	 *
	 * @return void
	 */
	public function register_payment_requirements() {
		if ( class_exists( 'Automattic\WooCommerce\StoreApi\StoreApi' ) ) {
			// Get extend class from the container.
			$extend = StoreApi::container()->get( ExtendSchema::class );

			// Add payment requirements for appointment availability carts.
			$extend->register_payment_requirements(
				array(
					'data_callback' => array( $this, 'add_appointment_availability_payment_requirement' ),
				)
			);
		}
	}

	/**
	 * Adds appointment availability payment requirement for carts that contain a product that requires it.
	 *
	 * @return array
	 */
	public function add_appointment_availability_payment_requirement() {
		if ( wc_appointment_cart_requires_confirmation() ) {
			return array( 'appointment_availability' );
		}
		return array();
	}
}

new WC_Appointments_Blocks();
