<?php
/**
 * REST API controller for staff objects.
 *
 * Handles requests to the /staff endpoint.
 *
 * @package WooCommerce\Appointments\Rest\Controller
 */

/**
 * REST API Products controller class.
 */
class WC_Appointments_REST_Staff_Controller extends WC_REST_Customers_Controller {

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
	protected $rest_base = 'staff';

	/**
	 * Get all staff members.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_Error|WP_REST_Response
	 */
	public function get_items( $request ) {
		$prepared_args            = [];
		$prepared_args['exclude'] = $request['exclude'];
		$prepared_args['include'] = $request['include'];
		$prepared_args['order']   = $request['order'];
		$prepared_args['number']  = $request['per_page'];
		if ( ! empty( $request['offset'] ) ) {
			$prepared_args['offset'] = $request['offset'];
		} else {
			$prepared_args['offset'] = ( $request['page'] - 1 ) * $prepared_args['number'];
		}
		$orderby_possibles        = [
			'id'      => 'ID',
			'include' => 'include',
			'name'    => 'display_name',
			'date'    => 'registered',
		];
		$prepared_args['orderby'] = $orderby_possibles[ $request['orderby'] ];
		$prepared_args['search']  = $request['search'];

		if ( '' !== $prepared_args['search'] ) {
			$prepared_args['search'] = '*' . $prepared_args['search'] . '*';
		}

		// Filter by email.
		if ( ! empty( $request['email'] ) ) {
			$prepared_args['search']         = $request['email'];
			$prepared_args['search_columns'] = [ 'user_email' ];
		}

		// Filter by role.
		$prepared_args['role'] = 'shop_staff';

		/**
		 * Filter arguments, before passing to WP_User_Query, when querying users via the REST API.
		 *
		 * @see https://developer.wordpress.org/reference/classes/wp_user_query/
		 *
		 * @param array           $prepared_args Array of arguments for WP_User_Query.
		 * @param WP_REST_Request $request       The current request.
		 */
		$prepared_args = apply_filters( 'woocommerce_rest_staff_query', $prepared_args, $request );

		$query = new WP_User_Query( $prepared_args );

		$users = [];
		foreach ( $query->results as $user ) {
			$data    = $this->prepare_item_for_response( $user, $request );
			$users[] = $this->prepare_response_for_collection( $data );
		}

		$response = rest_ensure_response( $users );

		// Store pagination values for headers then unset for count query.
		$per_page = (int) $prepared_args['number'];
		$page     = ceil( ( ( (int) $prepared_args['offset'] ) / $per_page ) + 1 );

		$prepared_args['fields'] = 'ID';

		$total_users = $query->get_total();
		if ( $total_users < 1 ) {
			// Out-of-bounds, run the query again without LIMIT for total count.
			unset( $prepared_args['number'] );
			unset( $prepared_args['offset'] );
			$count_query = new WP_User_Query( $prepared_args );
			$total_users = $count_query->get_total();
		}
		$response->header( 'X-WP-Total', (int) $total_users );
		$max_pages = ceil( $total_users / $per_page );
		$response->header( 'X-WP-TotalPages', (int) $max_pages );

		$base = add_query_arg( $request->get_query_params(), rest_url( sprintf( '/%s/%s', $this->namespace, $this->rest_base ) ) );
		if ( $page > 1 ) {
			$prev_page = $page - 1;
			if ( $prev_page > $max_pages ) {
				$prev_page = $max_pages;
			}
			$prev_link = add_query_arg( 'page', $prev_page, $base );
			$response->link_header( 'prev', $prev_link );
		}
		if ( $max_pages > $page ) {
			$next_page = $page + 1;
			$next_link = add_query_arg( 'page', $next_page, $base );
			$response->link_header( 'next', $next_link );
		}

		return $response;
	}

	/**
	 * Prepare a single staff member output for response.
	 *
	 * @param  WP_User          $user_data User object.
	 * @param  WP_REST_Request  $request Request object.
	 * @return WP_REST_Response $response Response data.
	 */
	public function prepare_item_for_response( $user_data, $request ) {
		$staff   = new WC_Product_Appointment_Staff( $user_data );
		$context = ! empty( $request['context'] ) ? $request['context'] : 'view';
		$data    = [
			'id'           => $staff->get_id(),
			'display_name' => $staff->get_display_name( $context ),
			'full_name'    => $staff->get_full_name( $context ),
			'email'        => $staff->get_email( $context ),
			'availability' => $staff->get_availability( $context ),
			'products'     => $this->prepare_product_links( $staff, $request ),
		];

		$data     = $this->add_additional_fields_to_object( $data, $request );
		$data     = $this->filter_response_by_context( $data, $context );
		$response = rest_ensure_response( $data );
		$response->add_links( $this->prepare_links( $user_data, $request ) );

		#error_log( var_export( $user_data, true ) );

		/**
		 * Filter staff member data returned from the REST API.
		 *
		 * @param WP_REST_Response $response   The response object.
		 * @param WP_User          $user_data  User object used to create response.
		 * @param WP_REST_Request  $request    Request object.
		 */
		return apply_filters( 'woocommerce_rest_prepare_staff', $response, $user_data, $request );
	}

	/**
	 * Prepare productlinks for the request.
	 *
	 * @param WP_User $customer Customer object.
	 * @return array Links for the given customer.
	 */
	protected function prepare_product_links( $staff, $request ) {
		$links = [];

		$product_ids = $staff->get_product_ids();

		if ( $product_ids ) {
			foreach ( $product_ids as $product_id ) {
				$user_product     = wc_get_product( $product_id );
				$staff_base_costs = $user_product->get_staff_base_costs( 'edit' );
				$staff_base_cost  = $staff_base_costs[ $staff->get_id() ] ?? '';
				$staff_qtys       = $user_product->get_staff_qtys( 'edit' );
				$staff_qty        = $staff_qtys[ $staff->get_id() ] ?? '';
				$links[]          = [
					'id'         => $user_product->get_id(),
					'name'       => $user_product->get_title(),
					'price'      => $user_product->get_price(),
					'price_html' => $user_product->get_price_html(),
					'staff_cost' => $staff_base_cost,
					'staff_qty'  => $staff_qty,
					'self'       => [
						'href' => rest_url( sprintf( '/%s/%s/%d', $this->namespace, 'products', $product_id ) ),
					],
					'collection' => [
						'href' => rest_url( sprintf( '/%s/%s', $this->namespace, 'products' ) ),
					],
				];
			}
		}

		return $links;
	}

	public function get_item_schema() {
		// TODO: Implement auto documentation here.
		return parent::get_item_schema();
	}
}

new WC_Appointments_REST_Staff_Controller();
