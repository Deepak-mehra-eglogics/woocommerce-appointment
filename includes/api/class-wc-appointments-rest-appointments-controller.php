<?php
/**
 * REST API for appointments objects.
 *
 * Handles requests to the /appointments endpoint.
 *
 * @package WooCommerce\Appointments\Rest\Controller
 */

/**
 * REST API Products controller class.
 */
class WC_Appointments_REST_Appointments_Controller extends WC_Appointments_REST_CRUD_Controller {

	use WC_Appointments_Rest_Permission_Check;

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'appointments';

	/**
	 * Post type.
	 *
	 * @var string
	 */
	protected $post_type = 'wc_appointment';

	/**
	 * Get object.
	 *
	 * @param int $appointment_id Object ID.
	 *
	 * @return WC_Appointment|false
	 */
	protected function get_object( $appointment_id ) {
		$post_object = $appointment_id ? get_post( $appointment_id ) : false;

		if ( ! $post_object || $this->post_type !== $post_object->post_type ) {
			return false;
		}

		return get_wc_appointment( $appointment_id );
	}

	/**
	 * Get objects (i.e. Appointments).
	 *
	 * @param array $query_args Query args.
	 *
	 * @return array Appointments data.
	 */
	protected function get_objects( $query_args ) {
		/**
		 * Get all public post statuses list and include a few.
		 * This is done to include `wc-partial-payment` for now.
		 *
		 */
		if ( ! isset( $query_args['post_status'] ) && empty( $query_args['post_status'] ) ) {
			$post_statuses             = array_values( get_post_stati( array( 'public' => true ) ) );
			$include_statuses          = array( 'wc-partial-payment' );
			$query_args['post_status'] = array_merge( $post_statuses, $include_statuses );
		}

		return parent::get_objects( $query_args );
	}

	/**
	 * Prepare objects query.
	 *
	 * @since  3.7.2
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return array
	 */
	protected function prepare_objects_query( $request ) {
		$args = parent::prepare_objects_query( $request );

		// Meta query.
		$meta_query = [];

		// Filter by product.
		if ( isset( $request['product_id'] ) ) {
			$meta_query[] = [
				'key'   => '_appointment_product_id',
				'value' => absint( $request['product_id'] ),
			];
		}

		// Filter by staff.
		if ( isset( $request['staff_id'] ) ) {
			$meta_query[] = [
				'key'   => '_appointment_staff_id',
				'value' => absint( $request['staff_id'] ),
			];
		}

		// Filter by customer.
		if ( isset( $request['customer_id'] ) ) {
			$meta_query[] = [
				'key'   => '_appointment_customer_id',
				'value' => absint( $request['customer_id'] ),
			];
		}

		// Filter by "from" date in 'YmdHis' format.
		if ( isset( $request['date_from'] ) ) {
			$meta_query[] = [
				'key'     => '_appointment_start',
				'value'   => esc_sql( date( 'YmdHis', strtotime( $request['date_from'] ) ) ),
				'compare' => '>=',
			];
		}

		// Filter by "to" date in 'YmdHis' format.
		if ( isset( $request['date_to'] ) ) {
			$meta_query[] = [
				'key'     => '_appointment_end',
				'value'   => esc_sql( date( 'YmdHis', strtotime( $request['date_to'] ) ) ),
				'compare' => '<',
			];
		}

		if ( $meta_query && ! empty( $meta_query ) ) {
			$args['meta_query'] = [
				'relation' => 'AND',
				$meta_query,
			];
		}

		return $args;
	}

	/**
	 * Prepare a single product output for response.
	 *
	 * @param WC_Appointment      $object  Object data.
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response
	 */
	public function prepare_object_for_response( $object, $request ) {
		$context = ! empty( $request['context'] ) ? $request['context'] : 'view';

		$data = [
			'id'                              => $object->get_id( $context ),
			'all_day'                         => $object->get_all_day( $context ),
			'cost'                            => $object->get_cost( $context ),
			'customer_id'                     => $object->get_customer_id( $context ),
			'date_created'                    => $object->get_date_created( $context ),
			'date_modified'                   => $object->get_date_modified( $context ),
			'start'                           => $object->get_start( $context ),
			'end'                             => $object->get_end( $context ),
			'google_calendar_event_id'        => $object->get_google_calendar_event_id( $context ),
			'google_calendar_staff_event_ids' => $object->get_google_calendar_staff_event_ids( $context ),
			'order_id'                        => $object->get_order_id( $context ),
			'order_item_id'                   => $object->get_order_item_id( $context ),
			'parent_id'                       => $object->get_parent_id( $context ),
			'product_id'                      => $object->get_product_id( $context ),
			'staff_id'                        => $object->get_staff_ids( $context ),
			'staff_ids'                       => $object->get_staff_ids( $context ),
			'status'                          => $object->get_status( $context ),
			'customer_status'                 => $object->get_customer_status( $context ),
			'qty'                             => $object->get_qty( $context ),
			'timezone'                        => $object->get_timezone( $context ),
			'local_timezone'                  => $object->get_local_timezone( $context ),
		];

		$data     = $this->add_additional_fields_to_object( $data, $request );
		$data     = $this->filter_response_by_context( $data, $context );
		$response = rest_ensure_response( $data );
		$response->add_links( $this->prepare_links( $object, $request ) );

		/**
		 * Filter the data for a response.
		 *
		 * The dynamic portion of the hook name, $this->post_type,
		 * refers to object type being prepared for the response.
		 *
		 * @param WP_REST_Response $response The response object.
		 * @param WC_Data          $object   Object data.
		 * @param WP_REST_Request  $request  Request object.
		 */
		return apply_filters( "woocommerce_rest_prepare_{$this->post_type}_object", $response, $object, $request );
	}

	/**
	 * Prepare a single product for create or update.
	 *
	 * @param  WP_REST_Request $request Request object.
	 * @param  bool            $creating If is creating a new object.
	 * @return WP_Error|WC_Data
	 */
	protected function prepare_object_for_database( $request, $creating = false ) {
		$id = isset( $request['id'] ) ? absint( $request['id'] ) : 0;

		$appointment = get_wc_appointment( $id );

		if ( isset( $request['staff_id'] ) ) {
			$appointment->get_staff_ids( $request['staff_id'] );
		}

		// TODO: Update other fields here.
		// Allow set meta_data.
		if ( is_array( $request['meta_data'] ) ) {
			foreach ( $request['meta_data'] as $meta ) {
				$appointment->update_meta_data( $meta['key'], $meta['value'], isset( $meta['id'] ) ? $meta['id'] : '' );
			}
		}

		/**
		 * Filters an object before it is inserted via the REST API.
		 *
		 * The dynamic portion of the hook name, `$this->post_type`,
		 * refers to the object type slug.
		 *
		 * @param WC_Data         $appointment  Object object.
		 * @param WP_REST_Request $request  Request object.
		 * @param bool            $creating If is creating a new object.
		 */
		return apply_filters( "woocommerce_rest_pre_insert_{$this->post_type}_object", $appointment, $request, $creating );
	}

	public function get_item_schema() {
		// TODO: Implement auto documentation here.
		return parent::get_item_schema();
	}
}
