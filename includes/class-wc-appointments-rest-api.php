<?php
/**
 * WooCommerce Appointments API
 *
 * @package WooCommerce\Appointments\Rest
 */

/**
 * API class which registers all the routes.
 */
class WC_Appointments_REST_API {

	const V1_NAMESPACE = 'wc-appointments/v1';

	/**
	 * Construct.
	 */
	public function __construct() {
		// Stop here when WooCommerce is lower than 3.0.
		if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
			return;
		}
		add_action( 'rest_api_init', [ $this, 'rest_api_includes' ], 5 );
		add_action( 'rest_api_init', [ $this, 'rest_api_init' ] );
	}

	/**
	 * Include all files from the /api/ folder.
	 *
	 * @return bool
	 */
	public function rest_api_includes() {
		include_once __DIR__ . '/api/trait-wc-appointments-rest-permission-check.php';
		include_once __DIR__ . '/api/class-wc-appointments-rest-crud-controller.php';
		include_once __DIR__ . '/api/class-wc-appointments-rest-appointments-controller.php';
		include_once __DIR__ . '/api/class-wc-appointments-rest-staff-controller.php';
		include_once __DIR__ . '/api/class-wc-appointments-rest-slots-controller.php';
		include_once __DIR__ . '/api/class-wc-appointments-rest-availabilities-controller.php';
		include_once __DIR__ . '/api/class-wc-appointments-rest-products-controller.php';
		include_once __DIR__ . '/api/class-wc-appointments-rest-products-categories-controller.php';
	}

	/**
	 * Initialize the REST API.
	 */
	public function rest_api_init() {
		$controller = new WC_Appointments_REST_Appointments_Controller();
		$controller->register_routes();

		$controller = new WC_Appointments_REST_Staff_Controller();
		$controller->register_routes();

		$controller = new WC_Appointments_REST_Slots_Controller();
		$controller->register_routes();

		$controller = new WC_Appointments_REST_Availabilities_Controller();
		$controller->register_routes();

		$controller = new WC_Appointments_REST_Products_Controller();
		$controller->register_routes();

		$controller = new WC_Appointments_REST_Products_Categories_Controller();
		$controller->register_routes();
	}
}

new WC_Appointments_REST_API();
