<?php

namespace WooCommerce\Appointments\Blocks;

use Automattic\WooCommerce\Blocks\Assets\Api;
use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * Appointments Gateway method integration
 *
 * @since 0.1
 */
final class Appointments_Gateway extends AbstractPaymentMethodType {
	/**
	 * Payment method name defined by payment methods extending this class.
	 *
	 * @var string
	 */
	protected $name = 'wcappointmentsgateway';

	/**
	 * An instance of the Asset Api
	 *
	 * @var Api
	 */
	private $asset_api;

	/**
	 * Is the payment method enabled
	 *
	 * @var Api
	 */
	private $enabled;

	/**
	 * Constructor
	 *
	 * @param Api $asset_api An instance of Api.
	 */
	public function __construct( Api $asset_api ) {
		$this->asset_api = $asset_api;
	}

	/**
	 * Initializes the payment method type.
	 * Make sure Require checkout payment gatway is always enabled
	 * as the gateway settings are hidden with not setup available.
	 */
	public function initialize() {
		#$payment_gateways = WC()->payment_gateways() ? WC()->payment_gateways->payment_gateways() : [];
		#$is_enabled       = isset( $payment_gateways['wcappointmentsgateway'] ) ? $payment_gateways['wcappointmentsgateway']->enabled : 'no';

		#error_log( var_export( $payment_gateways, true ) );

		$this->enabled = 'yes';
	}

	/**
	 * Returns if this payment method should be active. If false, the scripts will not be enqueued.
	 *
	 * @return boolean
	 */
	public function is_active() {
		if ( 'yes' !== $this->enabled ) {
			return false;
		}

		return true;
	}

	/**
	 * Returns an array of scripts/handles to be registered for this payment method.
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles() {
		// JS suffix.
		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		wp_register_script(
			'wcappointmentsgateway',
			WC_APPOINTMENTS_PLUGIN_URL . '/assets/js/blocks/appointments-gateway' . $suffix . '.js',
			[
				'wc-blocks-registry',
				'wc-settings',
				'wp-html-entities',
				'wp-data',
				'wp-element',
				'wp-hooks',
			],
			WC_APPOINTMENTS_VERSION,
			true
		);

		return array( 'wcappointmentsgateway' );
	}

	/**
	 * Returns an array of key=>value pairs of data made available to the payment methods script.
	 *
	 * @return array
	 */
	public function get_payment_method_data() {
		return array(
			'title'             => __( 'Check appointment availability', 'woocommerce-appointments' ),
			'description'       => __( 'Get confirmation for the order.', 'woocommerce-appointments' ),
			'order_button_text' => __( 'Request Confirmation', 'woocommerce-appointments' ),
			'supports'          => $this->get_supported_features(),
			'is_enabled'        => wc_appointment_cart_requires_confirmation(),
		);
	}

	/**
	 * Gets payment method supported features.
	 *
	 * @return array
	 */
	public function get_supported_features() {
		return array(
			'products',
			'appointment_availability',
		);
	}
}
