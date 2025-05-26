<?php
/**
 * Rest API v2 Controller.
 *
 * @package Automattic\WooCommerce\ProductAddons
 * @since 6.9.0
 * @version 7.6.0
 */

/**
 * Controller class.
 */
class WC_Product_Addons_Api_V2_Controller extends WP_REST_Controller {

	/**
	 * Endpoint namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'wc-product-add-ons/v2';

	/**
	 * Validation helper.
	 *
	 * @var WC_Product_Addons_Api_V2_Validation
	 */
	protected $validation;

	/**
	 * Constructor.
	 *
	 * @param WC_Product_Addons_Api_V2_Validation $validation Validation helper instance.
	 */
	public function __construct( WC_Product_Addons_Api_V2_Validation $validation ) {
		$this->validation = $validation;
	}

	/**
	 * Register the route for groups
	 *
	 * @since 2.9.0
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/global-add-ons',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::CREATABLE ),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_all' ),
					'permission_callback' => array( $this, 'permissions_check' ),
				),
				'schema' => array( $this, 'get_item_schema' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/global-add-ons/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'permissions_check' ),
				),
				'schema' => array( $this, 'get_item_schema' ),
			)
		);
	}

	/**
	 * Retrieves an array of endpoint arguments from the item schema for the controller.
	 *
	 * @param string $method Optional. HTTP method of the request.
	 * @return array Endpoint arguments.
	 */
	public function get_endpoint_args_for_item_schema( $method = \WP_REST_Server::CREATABLE ) {
		$schema = $this->get_item_schema();
		return \rest_get_endpoint_args_for_schema( $schema, $method );
	}

	/**
	 * Returns the full item schema.
	 *
	 * @return array
	 */
	public function get_item_schema() {
		return array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'product-addons',
			'type'       => 'object',
			'properties' => $this->get_global_addons_properties(),
		);
	}

	/**
	 * Addon schema properties.
	 *
	 * @return array
	 */
	protected function get_global_addons_properties() {
		$field_properties = $this->get_common_addon_properties();

		return array(
			'id'                     => array(
				'description' => __( 'Global group ID', 'woocommerce-appointments' ),
				'type'        => 'integer',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
			),
			'name'                   => array(
				'description' => __( 'Name of the global group', 'woocommerce-appointments' ),
				'type'        => 'string',
				'context'     => array( 'view', 'edit' ),
				'arg_options' => array(
					'sanitize_callback' => 'sanitize_text_field',
					'validate_callback' => array( $this->validation, 'validate_group_name' ),
				),
			),
			'priority'               => array(
				'description' => __( 'Priority of the group', 'woocommerce-appointments' ),
				'type'        => 'integer',
				'context'     => array( 'view', 'edit' ),
				'required'    => false,
				'arg_options' => array(
					'default' => 1,
				),
			),
			'restrict_to_categories' => array(
				'description' => __( 'Product categories this group applies to, or an empty array if it applies to all products', 'woocommerce-appointments' ),
				'type'        => 'array',
				'context'     => array( 'view', 'edit' ),
				'required'    => false,
				'arg_options' => array(
					'default' => array(),
				),
				'items'       => array(
					'type'    => array( 'integer' ),
					'context' => array( 'view', 'edit' ),
				),
			),

			'fields'                 => array(
				'description' => __( 'Fields containing the add-ons and their options for the group.', 'woocommerce-appointments' ),
				'type'        => 'array',
				'context'     => array( 'view', 'edit' ),
				'required'    => false,
				'items'       => array(
					'type'       => 'object',
					'properties' => $field_properties,
				),
				'arg_options' => array(
					'default'           => array(),
					'validate_callback' => function ( $values, $request, $param ) use ( $field_properties ) {
						return $this->validation->is_array_of_addons( $values, $request, $param, $field_properties );
					},
					'sanitize_callback' => function ( $values, $request, $param ) use ( $field_properties ) {
						return $this->validation->sanitize_array_of_addons( $values, $request, $param, $field_properties );
					},
				),
			),
		);
	}

	/**
	 * Addon field schema properties. Note, all properties are required but we set defaults for non-required props when
	 * sanitizing the request.
	 *
	 * Sanitization inspired by WC_Product_Addons_Admin::ajax_get_addon_field().
	 *
	 * @return array
	 */
	public function get_common_addon_properties() {
		return array(
			'id'                 => array(
				'description' => __( 'Unique identifier for the add-on', 'woocommerce-appointments' ),
				'type'        => 'integer',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
				'required'    => false, // Auto-generated.
			),
			'name'               => array(
				'description' => __( 'Name of the product add-on', 'woocommerce-appointments' ),
				'type'        => 'string',
				'context'     => array( 'view', 'edit' ),
				'arg_options' => array(
					'validate_callback' => array( $this->validation, 'validate_addon_name' ),
					'sanitize_callback' => 'sanitize_text_field',
				),
			),
			'title_format'       => array(
				'description' => __( 'Format of the add-on title', 'woocommerce-appointments' ),
				'type'        => 'string',
				'enum'        => array( 'label', 'heading', 'hide' ),
				'context'     => array( 'view', 'edit' ),
				'arg_options' => array(
					'default'           => 'label',
					'sanitize_callback' => 'sanitize_text_field',
				),
			),
			'default'            => array(
				'description' => __( 'Default add-on value', 'woocommerce-appointments' ),
				'type'        => 'string',
				'context'     => array( 'view', 'edit' ),
				'arg_options' => array(
					'default'           => '',
					'sanitize_callback' => 'sanitize_text_field',
					'validate_callback' => array( $this->validation, 'validate_default' ),
				),
			),
			'description_enable' => array(
				'description' => __( 'Status indicating if the add-on description is enabled', 'woocommerce-appointments' ),
				'type'        => 'boolean',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
				'arg_options' => array(
					'default' => false,
				),
			),
			'description'        => array(
				'description' => __( 'Description of the add-on', 'woocommerce-appointments' ),
				'type'        => 'string',
				'context'     => array( 'view', 'edit' ),
				'arg_options' => array(
					'default'           => '',
					'sanitize_callback' => 'wp_kses_post',
				),
			),
			'placeholder_enable' => array(
				'description' => __( 'Status indicating if the add-on placeholder is enabled', 'woocommerce-appointments' ),
				'type'        => 'boolean',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
				'arg_options' => array(
					'default' => false,
				),
			),
			'placeholder'        => array(
				'description' => __( 'Placeholder text for the add-on, applicable for custom_text and custom_textarea add-on types', 'woocommerce-appointments' ),
				'type'        => 'string',
				'context'     => array( 'view', 'edit' ),
				'arg_options' => array(
					'default'           => '',
					'sanitize_callback' => 'sanitize_text_field',
				),
			),
			'type'               => array(
				'description' => __( 'Type of the add-on', 'woocommerce-appointments' ),
				'type'        => 'string',
				'context'     => array( 'view', 'edit' ),
				'enum'        => array(
					'multiple_choice',
					'checkbox',
					'custom_text',
					'custom_textarea',
					'file_upload',
					'custom_price',
					'input_multiplier',
					'heading',
					'datepicker',
				),
			),
			'display'            => array(
				'description' => __( 'Display options for multiple_choice type add-ons', 'woocommerce-appointments' ),
				'type'        => 'string',
				'context'     => array( 'view', 'edit' ),
				'enum'        => array( 'select', 'radiobutton', 'images' ),
				'arg_options' => array(
					'default' => 'select',
				),
			),
			'position'           => array(
				'description' => __( 'Position of the add-on in the product', 'woocommerce-appointments' ),
				'type'        => 'integer',
				'context'     => array( 'view', 'edit' ),
				'arg_options' => array(
					'default' => 10,
				),
				'minimum'     => 0,
			),
			'required'           => array(
				'description' => __( 'Status indicating if the add-on is required', 'woocommerce-appointments' ),
				'type'        => 'boolean',
				'context'     => array( 'view', 'edit' ),
				'arg_options' => array(
					'default'           => false,
					'validate_callback' => array( $this->validation, 'validate_required' ),
				),
			),
			'restrictions'       => array(
				'description' => __( 'Status indicating if min/max limits for price or text length are enabled', 'woocommerce-appointments' ),
				'type'        => 'boolean',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
				'arg_options' => array(
					'default' => false,
				),
			),
			'restrictions_type'  => array(
				'description' => __( 'Input restrictions for custom_text type add-ons', 'woocommerce-appointments' ),
				'type'        => 'string',
				'context'     => array( 'view', 'edit' ),
				'enum'        => array(
					'',
					'any_text',
					'only_letters',
					'only_numbers',
					'only_letters_numbers',
					'email',
				),
				'arg_options' => array(
					'default' => 'any_text',
				),
			),
			'adjust_price'       => array(
				'description' => __( 'Status indicating if the add-on adjusts the product price', 'woocommerce-appointments' ),
				'type'        => 'boolean',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
				'arg_options' => array(
					'default' => false,
				),
			),
			'price_type'         => array(
				'description' => __( 'Type of price adjustment for the add-on', 'woocommerce-appointments' ),
				'type'        => 'string',
				'context'     => array( 'view', 'edit' ),
				'enum'        => array( 'flat_fee', 'quantity_based', 'percentage_based' ),
				'arg_options' => array(
					'default' => 'flat_fee',
				),
			),
			'price'              => array(
				'description' => __( 'Price of the add-on, if applicable (numeric string)', 'woocommerce-appointments' ),
				'type'        => 'string',
				'context'     => array( 'view', 'edit' ),
				'arg_options' => array(
					'default'           => '',
					'validate_callback' => array( $this->validation, 'is_empty_or_float' ),
					// can't directly use wc_format_decimal to sanitize because when this callback gets called, it gets parameters that can't be overriden and cause wrong output.
					'sanitize_callback' => array( $this->validation, 'sanitize_empty_or_float' ),
				),
			),
			'min'                => array(
				'description' => __( 'Minimum allowed input for custom_text, custom_textarea, custom_price, and input_multiplier add-ons', 'woocommerce-appointments' ),
				'type'        => 'string',
				'context'     => array( 'view', 'edit' ),
				'arg_options' => array(
					'default'           => '',
					'validate_callback' => array( $this->validation, 'validate_min_max' ),
					// can't directly use wc_format_decimal to sanitize because when this callback gets called, it gets parameters that can't be overriden and cause wrong output.
					'sanitize_callback' => array( $this->validation, 'sanitize_empty_or_float' ),
				),
			),
			'max'                => array(
				'description' => __( 'Maximum allowed input for custom_text, custom_textarea, custom_price, and input_multiplier add-ons', 'woocommerce-appointments' ),
				'type'        => 'string',
				'context'     => array( 'view', 'edit' ),
				'arg_options' => array(
					'default'           => '',
					'validate_callback' => array( $this->validation, 'validate_min_max' ),
					// can't directly use wc_format_decimal to sanitize because when this callback gets called, it gets parameters that can't be overriden and cause wrong output.
					'sanitize_callback' => array( $this->validation, 'sanitize_empty_or_float' ),
				),
			),
			'options'            => array(
				'description' => __( 'List of options for multiple_choice and checkbox type add-ons', 'woocommerce-appointments' ),
				'type'        => 'array',
				'context'     => array( 'view', 'edit' ),
				'arg_options' => array(
					'default'           => array(),
					'validate_callback' => array( $this->validation, 'validate_options' ),
				),
				'items'       => array(
					'type'       => 'object',
					'properties' => array(
						'label'      => array(
							'description' => __( 'Label for the add-on option', 'woocommerce-appointments' ),
							'type'        => 'string',
							'context'     => array( 'view', 'edit' ),
							'required'    => true,
						),
						'price'      => array(
							'description' => __( 'Price of the add-on option, if applicable (numeric string)', 'woocommerce-appointments' ),
							'type'        => 'string',
							'context'     => array( 'view', 'edit' ),
							'required'    => false,
							'arg_options' => array(
								'validate_callback' => array( $this->validation, 'is_empty_or_float' ),
								'sanitize_callback' => 'wc_format_decimal',
							),
						),
						'price_type' => array(
							'description' => __( 'Type of price adjustment for the add-on option', 'woocommerce-appointments' ),
							'type'        => 'string',
							'context'     => array( 'view', 'edit' ),
							'enum'        => array( 'flat_fee', 'quantity_based', 'percentage_based' ),
							'required'    => false,
						),
						'image'      => array(
							'description' => __( 'Image ID for the add-on option for image display type, or 0', 'woocommerce-appointments' ),
							'type'        => 'integer',
							'context'     => array( 'view', 'edit' ),
							'required'    => false,
						),
						'visibility' => array(
							'description' => __( 'Visibility of the add-on option', 'woocommerce-product-addons' ),
							'type'        => 'boolean',
							'context'     => array( 'view', 'edit' ),
							'required'    => false,
						),
					),
				),
			),
		);
	}

	/**
	 * Get (GET/READABLE) all global Add-On groups
	 *
	 * @return \WP_REST_Response
	 */
	public function get_all() {
		return rest_ensure_response( array_map( array( $this, 'format_response' ), WC_Product_Addons_Api_V2_Global_Group::get_all() ) );
	}

	/**
	 * Get (GET/READABLE) a single group
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_item( $request ) {
		try {
			$result = new WC_Product_Addons_Api_V2_Global_Group( wc_clean( $request['id'] ) );
		} catch ( \Exception $e ) {
			return new \WP_Error(
				'woocommerce_product_add_ons_rest__error',
				$e->getMessage(),
				array( 'status' => 404 ) // not found.
			);
		}

		return rest_ensure_response( $this->format_response( $result ) );
	}

	/**
	 * Format WC_Product_Addons_Api_V2_Global_Group object for the response.
	 *
	 * @param WC_Product_Addons_Api_V2_Global_Group $result Object to format.
	 *
	 * @return array
	 */
	protected function format_response( WC_Product_Addons_Api_V2_Global_Group $result ) {
		return array(
			'id'                     => $result->get_id(),
			'name'                   => $result->get_name(),
			'priority'               => $result->get_priority(),
			'restrict_to_categories' => $result->get_restrict_to_categories(),
			'fields'                 => $result->get_fields(),
		);
	}

	/**
	 * Add (POST/CREATABLE) a global Add-On group
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create_item( $request ) {
		// First need to validate optionally required 'name' property,
		// as validation callback is only called on non-null values.
		$name_validation = $this->validation->validate_group_name( $request->get_param( 'name' ), $request, 'name' );
		if ( is_wp_error( $name_validation ) ) {
			return new \WP_Error(
				'rest_invalid_param',
				esc_html__( 'Add-on Group\'s Name parameter is required when creating Add-on Group.', 'woocommerce-appointments' ),
				array( 'status' => 400 )
			);
		}

		try {
			$result = WC_Product_Addons_Api_V2_Global_Group::create_group( $request );
		} catch ( \Exception $e ) {
			return new \WP_Error(
				'woocommerce_product_add_ons_rest__error',
				$e->getMessage(),
				array( 'status' => 400 ) // bad request.
			);
		}

		$response = new \WP_REST_Response( $this->format_response( $result ) );
		$response->set_status( 201 );
		return rest_ensure_response( $response );
	}

	/**
	 * Update (PUT/EDITABLE) a global Add-On group
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_item( $request ) {
		$group_id = absint( wp_unslash( $request['id'] ) );

		if ( ! $group_id ) {
			return new \WP_Error(
				'woocommerce_product_add_ons_rest__invalid_id',
				esc_html__( 'Invalid add-on ID provided.', 'woocommerce-appointments' ),
				array( 'status' => 404 )
			);
		}

		try {
			$group = new WC_Product_Addons_Api_V2_Global_Group( wc_clean( $request['id'] ) );
		} catch ( \Exception $e ) {
			return new \WP_Error(
				'woocommerce_product_add_ons_rest__invalid_id',
				$e->getMessage(),
				array( 'status' => 404 )
			);
		}

		$result = $group->update_group( $request );
		return rest_ensure_response( $this->format_response( $result ) );
	}

	/**
	 * Delete (DELETE/DELETABLE) a global Add-On group
	 *
	 * @since 2.9.0
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function delete_item( $request ) {
		$group_id = absint( wp_unslash( $request['id'] ) );
		try {
			$group  = new WC_Product_Addons_Api_V2_Global_Group( $group_id );
			$result = $group->delete_group( $group_id );
		} catch ( \Exception $e ) {
			return new \WP_Error(
				'woocommerce_product_add_ons_rest__not_found',
				$e->getMessage(),
				array( 'status' => 404 ) // not found.
			);
		}

		if ( is_wp_error( $result ) ) {
			return new \WP_Error(
				'woocommerce_product_add_ons_rest__' . $result->get_error_code(),
				$result->get_error_message(),
				array( 'status' => 404 ) // not found.
			);
		}

		return new \WP_REST_Response( null, 204 ); // no content.
	}

	/**
	 * Validate the requester's permissions
	 *
	 * @return boolean|\WP_Error
	 */
	public function permissions_check() {
		if ( current_user_can( 'manage_woocommerce' ) || current_user_can( 'manage_options' ) ) {
			return true;
		}

		return new \WP_Error(
			'woocommerce_product_add_ons_rest__unauthorized',
			'You do not have permission to access this resource.',
			array( 'status' => is_user_logged_in() ? 403 : 401 )
		);
	}
}
