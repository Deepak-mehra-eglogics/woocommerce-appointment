<?php
/**
 * WC_Product_Add_Ons_Product_REST_API class
 *
 * @package  WooCommerce Product Add-Ons
 * @since    6.9.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add custom REST API fields to WooCommerce core's products endpoint.
 *
 * @class WC_Product_Addons_Api_V2_Product_Rest_Api
 */
class WC_Product_Addons_Api_V2_Product_Rest_Api {

	/**
	 * Custom REST API product field names, indicating support for getting/updating.
	 *
	 * @var array
	 */
	private static $product_fields = array(
		'exclude_global_add_ons' => array( 'get', 'update' ),
		'addons'                 => array( 'get', 'update' ),
	);

	/**
	 * Setup REST API class.
	 */
	public static function init() {

		// Register WP REST API custom product fields.
		add_action( 'rest_api_init', array( __CLASS__, 'register_product_fields' ), 1 );
	}

	/**
	 * Register custom REST API fields for product requests.
	 */
	public static function register_product_fields() {

		foreach ( self::$product_fields as $field_name => $field_supports ) {

			$args = array(
				'schema' => self::get_product_field_schema( $field_name ),
			);

			if ( in_array( 'get', $field_supports ) ) {
				$args['get_callback'] = array( __CLASS__, 'get_product_field_value' );
			}
			if ( in_array( 'update', $field_supports ) ) {
				$args['update_callback'] = array( __CLASS__, 'update_product_field_value' );
			}

			register_rest_field( 'product', $field_name, $args );
		}
	}

	/**
	 * Gets schema properties for Add-Ons Product fields.
	 *
	 * @param  string $field_name
	 * @return array
	 */
	public static function get_product_field_schema( $field_name ) {

		$extended_schema = self::get_extended_product_schema();
		return $extended_schema[ $field_name ] ?? null;
	}

	/**
	 * Gets extended (unprefixed) schema properties for products.
	 *
	 * @return array
	 */
	private static function get_extended_product_schema() {

		$validator         = new WC_Product_Addons_Api_V2_Validation();
		$v2_api_controller = new WC_Product_Addons_Api_V2_Controller( $validator );
		$addon_properties  = $v2_api_controller->get_common_addon_properties();

		return array(
			'exclude_global_add_ons' => array(
				'description' => __( 'Status indicating if the global add-on are excluded from this product', 'woocommerce-appointments' ),
				'type'        => 'boolean',
				'context'     => array( 'view', 'edit' ),
				'required'    => false, // Products without PAO don't have this
				'arg_options' => array(
					'default' => false,
				),

			),
			'addons'                 => array(
				'description' => __( 'Add-ons assigned to this product', 'woocommerce-appointments' ),
				'type'        => 'array',
				'context'     => array( 'view', 'edit' ),
				'required'    => false, // Products without PAO don't have this
				'items'       => array(
					'type'       => 'object',
					'properties' => $v2_api_controller->get_common_addon_properties(),
				),
				'arg_options' => array(
					'default'           => array(),
					'validate_callback' => function ( $values, $request, $param ) use ( $addon_properties, $validator ) {
						return $validator->is_array_of_addons( $values, $request, $param, $addon_properties );
					},
					'sanitize_callback' => function ( $values, $request, $param ) use ( $addon_properties, $validator ) {
						return $validator->sanitize_array_of_addons( $values, $request, $param, $addon_properties );
					},
				),
			),
		);
	}

	/**
	 * Gets values for Product Add-On's fields.
	 *
	 * @param  array            $response
	 * @param  string           $field_name
	 * @param  \WP_REST_Request $request
	 * @return array|\WP_Error
	 */
	public static function get_product_field_value( $response, $field_name, $request ) {

		if ( ! isset( $response['id'] ) ) {
			return null;
		}

		$product = wc_get_product( $response['id'] );

		if ( ! $product instanceof \WC_Product ) {
			return new \WP_Error(
				'woocommerce_product_add_ons_rest__error',
				sprintf(
				// translators: %d product id.
					__( 'Product with ID "%d" not found.', 'woocommerce-appointments' ),
					(int) $response['id']
				),
				array( 'status' => 404 ) // not found.
			);
		}

		$context = $request->get_param( 'context' );
		return self::get_product_field( $field_name, $product, $context );
	}

	/**
	 * Updates values for Product Add-On's fields.
	 *
	 * @param mixed  $field_value
	 * @param mixed  $response
	 * @param string $field_name
	 *
	 * @return boolean|\WP_Error
	 */
	public static function update_product_field_value( $field_value, $response, $field_name ) {

		if ( ! $response instanceof \WC_Product ) {
			return new \WP_Error(
				'woocommerce_product_add_ons_rest__error',
				__( 'Product not found.', 'woocommerce-appointments' ),
				array( 'status' => 404 ) // not found.
			);
		}

		$product_id    = $response->get_id();
		$productAddons = new \WC_Product_Addons_Api_V2_Product_Group( $product_id );

		switch ( $field_name ) {

			case 'exclude_global_add_ons':
				$productAddons->set_exclude_global_add_ons( $field_value );
				$productAddons->save();
				break;

			case 'addons':
				if ( \WC_Product_Addons_Api_V2_Product_Group::addons_already_set( $product_id ) ) {
					$productAddons->update_fields( $field_value );
				} else {
					$productAddons->set_fields( $field_value );
				}
				$productAddons->save();
				break;
		}

		return true;
	}

	/**
	 * Gets Add-on-specific product data.
	 *
	 * @param  string      $key
	 * @param  \WC_Product $product
	 * @return array
	 */
	private static function get_product_field( $key, $product, $context ) {

		$value = array();

		if ( ! in_array(
			$key,
			array_keys( self::get_extended_product_schema() )
		)
		) {
			return $value;
		}

		$addons     = WC_Product_Addons_Helper::get_product_addons( $product->get_id() );
		$has_addons = is_array( $addons ) && ! empty( $addons );

		if ( ! $has_addons ) {
			return $value;
		}

		$product_id    = $product->get_id();
		$product_group = new WC_Product_Addons_Api_V2_Product_Group( $product_id );

		switch ( $key ) {

			case 'exclude_global_add_ons':
				$value = $product_group->get_exclude_global_add_ons();
				break;

			case 'addons':
				$value = $product_group->get_fields();

				// Add global add-ons to product add-ons in view context.
				if ( 'view' === $context && ! $product_group->get_exclude_global_add_ons() ) {
					$product_categories  = $product->get_category_ids();
					$global_addon_groups = WC_Product_Addons_Api_V2_Global_Group::get_all();

					// Get only global add-ons that are not restricted to categories or are restricted to the product's categories.
					$global_addons_for_product = array_filter(
						$global_addon_groups,
						function ( $group ) use ( $product_categories ) {
							// No restrictions -> Add-On is global.
							if ( empty( $group->get_restrict_to_category_ids() ) ) {
								return true;
							}

							// Add-on's categories has common categories with the product.
							if ( array_intersect( $group->get_restrict_to_category_ids(), $product_categories ) ) {
								return true;
							}

							return false;
						}
					);

					// Add filtered global add-ons to product add-ons.
					foreach ( $global_addons_for_product as $group ) {
						$global_addons_for_product = $group->get_fields();
						$value                     = array_merge( $value, $global_addons_for_product );
					}
				}

				break;
		}

		return $value;
	}
}

WC_Product_Addons_Api_V2_Product_Rest_Api::init();
