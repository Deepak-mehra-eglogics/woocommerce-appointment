<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

use WCPay\MultiCurrency\FrontendCurrencies;
use WCPay\MultiCurrency\MultiCurrency;
use WCPay\MultiCurrency\Utils;

/**
 * WooCommerce Payments integration class.
 *
 * Last compatibility check: 9.2.0
 */
class WC_Appointments_Integration_WC_Payments {

	const ADDONS_CONVERTED_META_KEY = '_wcpay_multi_currency_addons_converted';

	/**
	 * Multi-currency currencies.
	 *
	 * @var WC_Payments_Multi_Currency class
	 */
	private $multi_currency;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->multi_currency = WC_Payments_Multi_Currency();

		if ( ! is_admin() || wp_doing_ajax() ) {

			// Convert product price?
			add_filter( 'appointment_form_calculated_appointment_cost', [ $this, 'adjust_amount_for_calculated_appointment_cost' ], 50, 1 );
			add_filter( 'appointments_calculated_product_price', [ $this, 'adjust_amount_for_calculated_appointment_cost' ], 50, 1 );
			add_filter( MultiCurrency::FILTER_PREFIX . 'should_convert_product_price', [ $this, 'should_convert_product_price' ], 50, 2 );
			add_filter( 'woocommerce_product_get_staff_base_costs', [ $this, 'get_staff_base_costs' ], 50, 2 );

			// Add support for appointments.
			add_filter( 'wcpay_payment_request_supported_types', [ $this, 'appointments_add_to_supported_types' ] );
			add_filter( 'wcpay_woopay_button_is_product_supported', [ $this, 'woopay_button_is_product_supported' ], 10, 2 );

			// Add currency to cost calculation.
			add_action( 'wp_ajax_wc_appointments_calculate_costs', [ $this, 'add_wc_price_args_filter_for_ajax' ], 9 );
			add_action( 'wp_ajax_nopriv_wc_appointments_calculate_costs', [ $this, 'add_wc_price_args_filter_for_ajax' ], 9 );

		}
	}

	/**
	 * Adjusts the calculated appointment cost for the selected currency, applying rounding and charm pricing as necessary.
	 *
	 * @param mixed $costs The original calculated appointment costs.
	 * @return mixed The appointment cost adjusted for the selected currency.
	 */
	public function adjust_amount_for_calculated_appointment_cost( $costs ) {
		/**
		 * Prevents adjustment of the calculated appointment cost during cart addition.
		 *
		 * When a appointment is added to the cart, the Booking plugin calculates the appointment cost and
		 * overrides the cart item price with this calculated amount. To avoid interfering with this process,
		 * this function skips any additional adjustments at this stage.
		 */
		if ( $this->is_call_in_backtrace( [ 'WC_Cart->add_to_cart' ] ) ) {
			return $costs;
		}

		return $this->multi_currency->adjust_amount_for_selected_currency( $costs );
	}

	/**
	 * Converts appointable product prices, if needed.
	 *
	 * @param mixed  $price   The price to be filtered.
	 *
	 * @return mixed The price as a string or float.
	 */
	public function get_appointable_product_price( $price, $product ) {
		if ( ! $price || ! $this->should_convert_product_price( true, $product ) ) {
			return $price;
		}

		/**
		 * When showing the price in HTML, the function applies currency conversion, charm pricing,
		 * and rounding. For internal calculations, it uses the raw exchange rate, with charm pricing
		 * and rounding adjustments applied only to the final calculated amount (handled in
		 * adjust_amount_for_calculated_appointment_cost).
		 */
		return $this->multi_currency->get_price(
			$price,
			$this->is_call_in_backtrace( [ 'WC_Product_Appointment->get_price_html' ] ) ? 'product' : 'exchange_rate'
		);
	}

	/**
	 * Checks to see if the product's price should be converted.
	 *
	 * @param bool   $return  Whether to convert the product's price or not. Default is true.
	 * @param object $product Product object to test.
	 *
	 * @return bool True if it should be converted.
	 */
	public function should_convert_product_price( bool $return, $product ): bool {
		// If it's already false, ignore it.
		if ( ! $return ) {
			return $return;
		}

		// If it's not WC_Product_Appointment, ignore it.
		if ( ! is_wc_appointment_product( $product ) ) {
			#wc_add_appointment_log( 'test_wcpayments', 'already false' );
			return $return;
		}

		#$backtrace = wp_debug_backtrace_summary( null, 0, false ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
		#wc_add_appointment_log( 'test_wcpayments', var_export( $backtrace, true ) );

		$calls = [
			'WC_Cart_Totals->calculate_item_totals',
			'WC_Cart->get_product_subtotal',
			'wc_get_price_excluding_tax',
			'wc_get_price_including_tax',
		];

		if ( $this->is_call_in_backtrace( $calls ) ) {
			return false;
		}

		return $return;
	}

	/**
	 * Returns the prices for a resource.
	 *
	 * @param mixed $prices The resource's prices in array format.
	 * @param object $product Product object to test.
	 *
	 * @return mixed The converted resource's prices.
	 */
	public function get_staff_base_costs( $prices, $product ) {
		if ( is_array( $prices ) ) {
			foreach ( $prices as $key => $price ) {
				$prices[ $key ] = $this->get_appointable_product_price( $price, $product );
			}
		}
		return $prices;
	}

	/**
	 * Add 'appointment' product type to the array.
	 *
	 * @param array $product_types Supported product types.
	 *
	 * @return array Supported product types.
	 */
	public function appointments_add_to_supported_types( $product_types ) {
		$product_types[] = 'appointment';

		return $product_types;
	}

	/**
	 * Whether the product page has a product compatible with the WooPay Express button.
	 *
	 * @param bool $is_supported is express button supproted.
	 * @param object $product Product object.
	 *
	 * @return boolean $is_supported.
	 */
	public function woopay_button_is_product_supported( $is_supported, $product ) {
		// WC Appointments require confirmation products are not supported.
		if ( is_wc_appointment_product( $product ) && $product->get_requires_confirmation() ) {
			$is_supported = false;
		}

		return $is_supported;
	}

	/**
	 * Adds a filter for when there is an ajax call to calculate the appointment cost.
	 *
	 * @return void
	 */
	public function add_wc_price_args_filter_for_ajax() {
		add_filter( 'wc_price_args', [ $this, 'filter_wc_price_args' ], 100 );
		add_filter( 'woocommerce_product_get_price', [ $this, 'get_appointable_product_price' ], 50, 2 );
	}

	/**
	 * Returns the formatting arguments to use when a appointment price is calculated on the product.
	 *
	 * @param array $args Original args from wc_price().
	 *
	 * @return array New arguments matching the selected currency.
	 */
	public function filter_wc_price_args( $args ): array {
		return wp_parse_args(
			[
				'currency'           => $this->multi_currency->get_selected_currency()->get_code(),
				'decimal_separator'  => $this->multi_currency->get_frontend_currencies()->get_price_decimal_separator( $args['decimal_separator'] ),
				'thousand_separator' => $this->multi_currency->get_frontend_currencies()->get_price_thousand_separator( $args['thousand_separator'] ),
				'decimals'           => $this->multi_currency->get_frontend_currencies()->get_price_decimals( $args['decimals'] ),
				'price_format'       => $this->multi_currency->get_frontend_currencies()->get_woocommerce_price_format( $args['price_format'] ),
			],
			$args
		);
	}

	/**
	 * Checks backtrace calls to see if a certain call has been made.
	 *
	 * @param array $calls Array of the calls to check for.
	 *
	 * @return bool True if found, false if not.
	 */
	public function is_call_in_backtrace( array $calls ): bool {
		$backtrace = wp_debug_backtrace_summary( null, 0, false ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
		foreach ( $calls as $call ) {
			if ( in_array( $call, $backtrace, true ) ) {
				return true;
			}
		}
		return false;
	}

}

new WC_Appointments_Integration_WC_Payments();
