<?php
/**
 * REST API for availabilities objects.
 *
 * Handles requests to the /availabilities endpoint.
 *
 * @package WooCommerce\Appointments\Rest\Controller
 */

/**
 * REST API Products controller class.
 */
class WC_Appointments_REST_Availabilities_Controller extends WC_REST_Controller {

	use WC_Appointments_Rest_Permission_Check;

	/**
	 * Endpoint namespace.
	 *
	 * @var string
	 */
	protected $namespace = WC_Appointments_REST_API::V1_NAMESPACE;

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'availabilities';

	/**
	 * Register the route for availabilities slots.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_items' ],
					'permission_callback' => '__return_true',
				],
			]
		);
	}

	/**
	 * Get all staff members.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_Error|WP_REST_Response
	 */
	public function get_items( $request ) {
		$prepared_args = [];

		// Set up arguments if passed.
		if ( ! empty( $request['filter'] ) ) {
			$prepared_args = $request['filter'];
			#error_log( var_export( $prepared_args, true ) );
		}

		// Example.
		#filter[0][key]=kind&filter[0][compare]=IN&filter[0][value][]=availability%23global&filter[0][value][]=availability%23staff

		/**
		 * Filter arguments, before passing to WC_Appointments_Availability_Data_Store, when querying availabilites via the REST API.
		 *
		 * @param array           $prepared_args Array of arguments for WC_Appointments_Availability_Data_Store.
		 * @param WP_REST_Request $request       The current request.
		 */
		$prepared_args = apply_filters( 'woocommerce_rest_availabilities_query', $prepared_args, $request );

		$availabilities = WC_Data_Store::load( 'appointments-availability' )->get_all_as_array( $prepared_args );

		$response = rest_ensure_response( $availabilities );

		return $response;
	}

	public function get_item_schema() {
		// TODO: Implement auto documentation here.
		return parent::get_item_schema();
	}
}
