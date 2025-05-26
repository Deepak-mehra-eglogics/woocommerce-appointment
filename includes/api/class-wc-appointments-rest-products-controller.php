<?php
/**
 * REST API Products controller customized for Gutenberg.
 *
 * Handles requests to the /products endpoint.
 *
 * @package WooCommerce\Appointments\Rest\Controller
 */

/**
 * REST API Products controller class.
 */
class WC_Appointments_REST_Products_Controller extends WC_REST_Products_Controller {

	use WC_Appointments_Rest_Permission_Check;

	/**
	 * Endpoint namespace.
	 *
	 * @var string
	 */
	protected $namespace = WC_Appointments_REST_API::V1_NAMESPACE;

	/**
	 * Add support for filtering by staff.
	 *
	 * @param WP_REST_Request $request Request data.
	 *
	 * @return array
	 */
	protected function prepare_objects_query( $request ) {

		$args = parent::prepare_objects_query( $request );

		if ( ! empty( $request['staff'] ) ) {
			$args['wc_appointments_staff'] = $request['staff'];

			add_filter( 'posts_join', [ $this, 'add_staff_filter' ], 10, 2 );
		}

		$args = apply_filters( 'woocommerce_appointments_products_rest_endpoint', $args );

		return $args;
	}

	/**
	 * Get objects.
	 *
	 * @param array $query_args Query args.
	 *
	 * @return array
	 */
	protected function get_objects( $query_args ) {
		$objects = parent::get_objects( $query_args );

		if ( ! empty( $query_args['wc_appointments_staff'] ) ) {
			remove_filter( 'posts_join', [ $this, 'add_staff_filter' ] );
		}

		return $objects;
	}

	/**
	 * Filters products by associated staff id(s).
	 *
	 * @param string   $join     Current join clauses.
	 * @param WP_Query $wp_query Current query object.
	 *
	 * @return string
	 */
	public function add_staff_filter( $join, $wp_query ) {
		global $wpdb;
		if ( ! empty( $wp_query->query['wc_appointments_staff'] ) ) {
			$staff_id_in = implode( ',', array_map( 'absint', (array) $wp_query->query['wc_appointments_staff'] ) );
			$join       .= " INNER JOIN
			{$wpdb->prefix}wc_appointment_relationships ON
			({$wpdb->posts}.ID = {$wpdb->prefix}wc_appointment_relationships.product_id AND
			{$wpdb->prefix}wc_appointment_relationships.staff_id IN ({$staff_id_in}))";
		}
		return $join;
	}

	/**
	 * Get product data.
	 *
	 * @param  WC_Product_Appointment $product Product instance.
	 * @param  string             $context Request context.
	 *                                     Options: 'view' and 'edit'.
	 * @return array
	 */
	protected function get_product_data( $product, $context = 'view' ) {
		$is_vaild_rest_type = 'appointment' === $product->get_type();
		$is_vaild_rest_type = apply_filters( "woocommerce_appointments_product_type_rest_check", $is_vaild_rest_type, $product );
		if ( ! $is_vaild_rest_type ) {
			wp_send_json( __( 'Not an appointable product', 'woocommerce-appointments' ), 400 );
		}

		$data = parent::get_product_data( $product, $context );

		$appointable_data = [
			'has_price_label'         => $product->get_has_price_label( $context ),
			'price_label'             => $product->get_price_label( $context ),
			'has_pricing'             => $product->get_has_pricing( $context ),
			'pricing'                 => $product->get_pricing( $context ),
			'qty'                     => $product->get_qty( $context ),
			'qty_min'                 => $product->get_qty_min( $context ),
			'qty_max'                 => $product->get_qty_max( $context ),
			'duration_unit'           => $product->get_duration_unit( $context ),
			'duration'                => $product->get_duration( $context ),
			'interval_unit'           => $product->get_interval_unit( $context ),
			'interval'                => $product->get_interval( $context ),
			'padding_duration_unit'   => $product->get_padding_duration_unit( $context ),
			'padding_duration'        => $product->get_padding_duration( $context ),
			'min_date_unit'           => $product->get_min_date_unit( $context ),
			'min_date'                => $product->get_min_date( $context ),
			'max_date_unit'           => $product->get_max_date_unit( $context ),
			'max_date'                => $product->get_max_date( $context ),
			'user_can_cancel'         => $product->get_user_can_cancel(),
			'cancel_limit_unit'       => $product->get_cancel_limit_unit( $context ),
			'cancel_limit'            => $product->get_cancel_limit( $context ),
			'user_can_reschedule'     => $product->get_user_can_reschedule(),
			'reschedule_limit_unit'   => $product->get_reschedule_limit_unit( $context ),
			'reschedule_limit'        => $product->get_reschedule_limit( $context ),
			'requires_confirmation'   => $product->get_requires_confirmation( $context ),
			'customer_timezones'      => $product->get_customer_timezones( $context ),
			'cal_color'               => $product->get_cal_color(),
			'availability_span'       => $product->get_availability_span( $context ),
			'availability_autoselect' => $product->get_availability_autoselect( $context ),
			'availability'            => $product->get_availability( $context ),
			'has_restricted_days'     => $product->get_has_restricted_days( $context ),
			'restricted_days'         => $product->get_restricted_days( $context ),
			'staff_label'             => $product->get_staff_label( $context ),
			'staff_assignment'        => $product->get_staff_assignment( $context ),
			'staff_nopref'            => $product->get_staff_nopref( $context ),
			'staff_ids'               => $product->get_staff_ids( $context ),
			'staff_base_costs'        => $product->get_staff_base_costs( $context ),
			'staff_qtys'              => $product->get_staff_qtys( $context ),
		];

		return array_merge( $data, $appointable_data );
	}

	/**
	 * Update the collection params.
	 *
	 * Adds new options for 'orderby', and new parameters 'cat_operator', 'attr_operator'.
	 *
	 * @return array
	 */
	public function get_collection_params() {
		$params          = parent::get_collection_params();
		$params['staff'] = [
			'description'       => __( 'Limit result set to products assigned a specific staff ID.', 'woocommerce-appointments' ),
			'type'              => 'array',
			'items'             => [
				'type' => 'integer',
			],
			'sanitize_callback' => 'wp_parse_id_list',
			'validate_callback' => 'rest_validate_request_arg',
		];

		$params['status']['default'] = 'publish';
		$params['type']['default']   = 'appointment';
		$params['type']['enum']      = [ 'appointment' ];

		return $params;
	}

	/**
	 * @param WP_REST_Request $request
	 * @param bool $creating
	 *
	 * @return WC_Data|WP_Error
	 */
	protected function prepare_object_for_database( $request, $creating = false ) {
		$request['type'] = 'appointment';

		$product = parent::prepare_object_for_database( $request, $creating );

		if ( ! $product instanceof WC_Product_Appointment ) {
			wp_send_json( __( 'Not an appointable product', 'woocommerce-appointments' ), 400 );
		}

		foreach ( array_keys( $this->get_appointment_product_properties() ) as $prop ) {
			$method = 'set_' . $prop;
			if ( isset( $request[ $prop ] ) && is_callable( [ $product, $method ] ) ) {
				$product->$method( $request[ $prop ] );
			}
		}

		if ( isset( $request['can_be_cancelled'] ) ) {
			$product->set_user_can_cancel( $request['can_be_cancelled'] );
		}

		if ( isset( $request['can_be_rescheduled'] ) ) {
			$product->set_user_can_reschedule( $request['can_be_rescheduled'] );
		}

		return $product;
	}

	/**
	 * Get the Product's schema, conforming to JSON Schema.
	 *
	 * @return array
	 */
	public function get_item_schema() {
		// TODO: Implement auto documentation here.
		return parent::get_item_schema();
	}
}
