<?php
/**
 * Product Add-ons cart
 *
 * @package WC_Product_Addons/Classes/Cart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Automattic\WooCommerce\StoreApi\Exceptions\RouteException;

/**
 * WC_Product_Addons_Cart class.
 *
 * @class    WC_Product_Addons_Cart
 * @version  7.7.0
 */
class WC_Product_Addons_Cart {
	/**
	 * Constructor.
	 */
	public function __construct() {
		// Load cart data per page load.
		add_filter( 'woocommerce_get_cart_item_from_session', array( $this, 'get_cart_item_from_session' ), 20, 2 );

		// Add item data to the cart.
		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'add_cart_item_data' ), 10, 2 );
		add_filter( 'woocommerce_add_cart_item', array( $this, 'add_cart_item' ), 20 );

		add_action( 'woocommerce_after_cart_item_quantity_update', array( $this, 'update_price_on_quantity_update' ), 20, 4 );

		// Get item data to display.
		add_filter( 'woocommerce_get_item_data', array( $this, 'get_item_data' ), 10, 2 );

		// Validate when adding to cart.
		add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'validate_add_cart_item' ), 999, 3 );

		// Add meta to order.
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'order_line_item' ), 10, 3 );
		add_filter( 'woocommerce_order_item_display_meta_value', array( $this, 'order_item_display_meta_value' ), 10, 3 );

		// Order again functionality.
		add_filter( 'woocommerce_order_again_cart_item_data', array( $this, 're_add_cart_item_data' ), 10, 3 );

		// Handle Store API add-to-cart requests.
		add_filter( 'woocommerce_store_api_add_to_cart_data', array( $this, 'handle_store_api_add_to_cart_request' ), 10, 2 );

		// Add Store API validation hooks.
		add_action( 'rest_pre_dispatch', array( $this, 'add_store_api_validation_hooks' ), 10, 0 );
	}

	/**
	 * Handle Store API add to cart request.
	 *
	 * @since 7.7.0
	 *
	 * @param array           $add_to_cart_data Add to cart data.
	 * @param WP_REST_Request $wp_rest_request WP REST Request.
	 *
	 * @throws RouteException When invalid add-on ID is provided.
	 *
	 * @return array
	 */
	public function handle_store_api_add_to_cart_request( array $add_to_cart_data, WP_REST_Request $wp_rest_request ): array {
		$params = $wp_rest_request->get_json_params();

		if ( ! isset( $params['addons_configuration'] ) || ! isset( $params['id'] ) ) {
			return $add_to_cart_data;
		}

		$product_id           = $params['id'];
		$addons_configuration = $params['addons_configuration'];
		$cart_item_data       = $add_to_cart_data['cart_item_data'];

		$product_addons = WC_Product_Addons_Helper::get_product_addons( $product_id );

		foreach ( $addons_configuration as $key => $value ) {
			$addon = array();
			foreach ( $product_addons as $product_addon ) {
				if ( (int) $product_addon['id'] === $key ) {
					$addon = $product_addon;
					break;
				}
			}

			if ( empty( $addon ) ) {
				throw new RouteException( 'woocommerce_pao_invalid_addon_id', esc_html( "Invalid add-on ID: $key" ), 400 );
			}

			if ( ! isset( $addon['type'] ) ) {
				continue;
			}

			switch ( $addon['type'] ) {
				case 'checkbox':
					if ( ! is_array( $value ) ) {
						throw new RouteException( 'woocommerce_pao_invalid_addon_value', esc_html( "Invalid value for add-on ID: $key, should be an array" ), 400 );
					}
					foreach ( $value as $val ) {
						if ( ! is_int( $val ) || $val < 0 || $val >= count( $addon['options'] ) ) {
							throw new RouteException( 'woocommerce_pao_invalid_addon_value', esc_html( "Invalid value for add-on ID: $key, index $val does not exist" ), 400 );
						}
					}
					$cart_item_data[ 'addon-' . $addon['field_name'] ] = array_map( fn( $val ) => sanitize_title( $addon['options'][ $val ]['label'] ), $value );
					break;
				case 'multiple_choice':
					if ( ! is_int( $value ) || $value < 0 || $value >= count( $addon['options'] ) ) {
						throw new RouteException( 'woocommerce_pao_invalid_addon_value', esc_html( "Invalid value for add-on ID: $key, index $value does not exist" ), 400 );
					}
					$cart_item_data[ 'addon-' . $addon['field_name'] ] = sanitize_title( $addon['options'][ $value ]['label'] . '-' . ( $value + 1 ) );

					break;
				case 'datepicker':
					$date = new WC_DateTime( $value );
					$cart_item_data[ 'addon-' . $addon['field_name'] . '-wc-pao-date' ] = $date->getOffsetTimestamp();
					$cart_item_data[ 'addon-' . $addon['field_name'] ]                  = $date->date_i18n( get_option( 'date_format' ) );
					break;
				case 'input_multiplier':
				case 'custom_price':
					if ( ! is_numeric( $value ) ) {
						throw new RouteException( 'woocommerce_pao_invalid_addon_value', esc_html( "Invalid value for add-on ID: $key, should be a number" ), 400 );
					}
					$cart_item_data[ 'addon-' . $addon['field_name'] ] = $value;
					break;
				case 'custom_text':
				case 'custom_textarea':
					if ( ! is_string( $value ) ) {
						throw new RouteException( 'woocommerce_pao_invalid_addon_value', esc_html( "Invalid value for add-on ID: $key, should be a string" ), 400 );
					}
					$cart_item_data[ 'addon-' . $addon['field_name'] ] = $value;
					break;
				case 'file_upload':
					if ( ! wp_http_validate_url( $value ) ) {
						throw new RouteException( 'woocommerce_pao_invalid_addon_value', esc_html( "Invalid value for add-on ID: $key, should be a valid URL" ), 400 );
					} else {
						$cart_item_data[ 'addon-' . $addon['field_name'] ] = $value;
					}
					break;
				default:
					$cart_item_data[ 'addon-' . $addon['field_name'] ] = $value;
					break;
			}
		}

		$add_to_cart_data['cart_item_data'] = $cart_item_data;

		return $add_to_cart_data;
	}

	/**
	 * Add Store API validation hooks.
	 *
	 * @since 7.7.0
	 */
	public function add_store_api_validation_hooks(): void {
		if ( WC_PAO_Core_Compatibility::is_store_api_request() ) {
			add_action( 'woocommerce_store_api_validate_add_to_cart', array( $this, 'validate_add_to_cart_store_api' ), 10, 2 );
		}
	}

	/**
	 * Validate add to cart action for Store API. Throws an exception if add to cart isn't valid.
	 *
	 * @since 7.7.0
	 *
	 * @param WC_Product $product Product being added to cart.
	 * @param array      $request Simple array with id, quantity, variation and cart_item_data keys. Not WP_REST_Request.
	 *
	 * @return void
	 * @throws RouteException When add to cart isn't valid.
	 */
	public function validate_add_to_cart_store_api( WC_Product $product, array $request ): void {

		$cart_item_data = $request['cart_item_data'] ?? array();
		$error          = $this->validate_cart_item_data( $product->get_id(), $cart_item_data );
		if ( is_wp_error( $error ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is escaped later.
			throw new RouteException( 'woocommerce_rest_cart_invalid_product_addons', $error->get_error_message() );
		}
	}

	/**
	 * Validate add cart item. Note: Fires before add_cart_item_data.
	 *
	 * @param  bool $passed      If passed validation.
	 * @param  int  $product_id  Product ID.
	 * @param  int  $qty         Quantity.
	 * @return bool
	 */
	 public function validate_add_cart_item( $passed, $product_id, $qty, $post_data = null ) {

 		// This needs to stay here to support WC core < 9.5. See https://github.com/woocommerce/woocommerce/pull/52486.
 		if ( WC_PAO_Core_Compatibility::is_store_api_request() ) {
 			return $passed;
 		}

 		$maybe_error = $this->validate_cart_item_data( $product_id, $post_data );

 		if ( is_wp_error( $maybe_error ) ) {
 			return false;
 		}

 		return $passed;
 	}

	/**
	 * Validate cart item data. Note: Fires before add_cart_item_data.
	 *
	 * @param  int   $product_id  Product ID.
	 * @param  array $post_data   Post data.
	 */
	public function validate_cart_item_data( $product_id, $post_data = null ) {
		if ( is_null( $post_data ) && isset( $_POST ) ) {
			$post_data = $_POST;
		}

		$product_addons = WC_Product_Addons_Helper::get_product_addons( $product_id );

		if ( is_array( $product_addons ) && ! empty( $product_addons ) ) {
			include_once __DIR__ . '/fields/abstract-wc-product-addons-field.php';

			foreach ( $product_addons as $addon ) {
				// If type is heading, skip.
				if ( ! is_array( $addon ) || 'heading' === $addon['type'] ) {
					continue;
				}

				$value = wp_unslash( isset( $post_data[ 'addon-' . $addon['field_name'] ] ) ? $post_data[ 'addon-' . $addon['field_name'] ] : '' );

				switch ( $addon['type'] ) {
					case 'checkbox':
						include_once __DIR__ . '/fields/class-wc-product-addons-field-list.php';
						$field = new WC_Product_Addons_Field_List( $addon, $value );
						break;
					case 'multiple_choice':
						switch ( $addon['display'] ) {
							case 'radiobutton':
								include_once __DIR__ . '/fields/class-wc-product-addons-field-list.php';
								$field = new WC_Product_Addons_Field_List( $addon, $value );
								break;
							case 'images':
							case 'select':
								include_once __DIR__ . '/fields/class-wc-product-addons-field-select.php';
								$field = new WC_Product_Addons_Field_Select( $addon, $value );
								break;
						}
						break;
					case 'custom_text':
					case 'custom_textarea':
					case 'input_multiplier':
						include_once __DIR__ . '/fields/class-wc-product-addons-field-custom.php';
						$field = new WC_Product_Addons_Field_Custom( $addon, $value );
						break;
					case 'custom_price':
						// Convert comma separated decimals to dot separated,
						// as this is the format expected in cart/order item data and DB.
						$value = wc_format_decimal( $value );

						include_once __DIR__ . '/fields/class-wc-product-addons-field-custom.php';
						$field = new WC_Product_Addons_Field_Custom( $addon, $value );
						break;
					case 'file_upload':
						include_once __DIR__ . '/fields/class-wc-product-addons-field-file-upload.php';
						$field = new WC_Product_Addons_Field_File_Upload( $addon, $value );
						break;
					case 'datepicker':
						include_once __DIR__ . '/fields/class-wc-product-addons-field-datepicker.php';
						$timestamp = isset( $post_data[ 'addon-' . $addon['field_name'] . '-wc-pao-date' ] ) ? absint( $post_data[ 'addon-' . $addon['field_name'] . '-wc-pao-date' ] ) : '';
						$offset    = isset( $post_data[ 'addon-' . $addon['field_name'] . '-wc-pao-date-gmt-offset' ] ) ? (float) $post_data[ 'addon-' . $addon['field_name'] . '-wc-pao-date-gmt-offset' ] : 0;
						$field     = new WC_Product_Addons_Field_Datepicker( $addon, $value, $timestamp, $offset );
						break;
					default:
						// Continue to the next field in case the type is not recognized (instead of causing a fatal error)
						continue 2;
						break;
				}

				$data = $field->validate();

				if ( is_wp_error( $data ) ) {
					wc_add_notice( esc_html( $data->get_error_message() ), 'error' );
					return $data;
				}

				do_action( 'woocommerce_validate_posted_addon_data', $addon );
			}
		}
	}

	/**
	 * Add cart item data action. Fires before add to cart action and add cart item filter.
	 *
	 * @throws Exception
	 *
	 * @param  int $product_id      Product ID.
	 * @param  int $variation_id
	 * @param  int $quantity
	 *
	 * @param  array $cart_item_data  Cart item meta data.
	 * @return array
	 */
	public function add_cart_item_data( $cart_item_data, $product_id, $post_data = null ) {
		if ( isset( $_POST ) && ! empty( $product_id ) ) {
			if ( empty( $cart_item_data['addons'] ) ) {
				$cart_item_data['addons'] = array();
			}
			// For Store API, add-on data comes from cart_item_data.
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$post_data = array_merge( $_POST, $cart_item_data );
		} else {
			return $cart_item_data;
		}

		$product_addons = WC_Product_Addons_Helper::get_product_addons( $product_id );

		// Yith Gift Card issue.
		if ( is_object( $cart_item_data ) && is_a( $cart_item_data, 'YWGC_Gift_Card' ) ) {
			$cart_item_data = [];
		}

		if ( is_array( $product_addons ) && ! empty( $product_addons ) ) {
			include_once __DIR__ . '/fields/abstract-wc-product-addons-field.php';

			foreach ( $product_addons as $addon ) {
				// If type is heading, skip.
				if ( ! is_array( $addon ) || 'heading' === $addon['type'] ) {
					continue;
				}

				$value = wp_unslash( isset( $post_data[ 'addon-' . $addon['field_name'] ] ) ? $post_data[ 'addon-' . $addon['field_name'] ] : '' );

				switch ( $addon['type'] ) {
					case 'checkbox':
						include_once __DIR__ . '/fields/class-wc-product-addons-field-list.php';
						$field = new WC_Product_Addons_Field_List( $addon, $value );
						break;
					case 'multiple_choice':
						switch ( $addon['display'] ) {
							case 'radiobutton':
								include_once __DIR__ . '/fields/class-wc-product-addons-field-list.php';
								$field = new WC_Product_Addons_Field_List( $addon, $value );
								break;
							case 'images':
							case 'select':
								include_once __DIR__ . '/fields/class-wc-product-addons-field-select.php';
								$field = new WC_Product_Addons_Field_Select( $addon, $value );
								break;
						}
						break;
					case 'custom_text':
					case 'custom_textarea':
					case 'custom_price':
					case 'input_multiplier':
						include_once __DIR__ . '/fields/class-wc-product-addons-field-custom.php';
						$field = new WC_Product_Addons_Field_Custom( $addon, $value );
						break;
					case 'file_upload':
						include_once __DIR__ . '/fields/class-wc-product-addons-field-file-upload.php';
						$field = new WC_Product_Addons_Field_File_Upload( $addon, $value );
						break;
					case 'datepicker':
						include_once __DIR__ . '/fields/class-wc-product-addons-field-datepicker.php';
						$timestamp = isset( $post_data[ 'addon-' . $addon['field_name'] . '-wc-pao-date' ] ) ? (int) $post_data[ 'addon-' . $addon['field_name'] . '-wc-pao-date' ] : '';
						$offset    = isset( $post_data[ 'addon-' . $addon['field_name'] . '-wc-pao-date-gmt-offset' ] ) ? (float) $post_data[ 'addon-' . $addon['field_name'] . '-wc-pao-date-gmt-offset' ] : 0;
						$field     = new WC_Product_Addons_Field_Datepicker( $addon, $value, $timestamp, $offset );
						break;
				}

				$data = $field->get_cart_item_data();

				if ( is_wp_error( $data ) ) {
					// Throw exception for add_to_cart to pickup.
					throw new Exception( $data->get_error_message() );
				} elseif ( $data ) {
					$cart_item_data['addons'] = array_merge( $cart_item_data['addons'], apply_filters( 'woocommerce_product_addon_cart_item_data', $data, $addon, $product_id, $post_data ) );
				}
			}
		}

		return $cart_item_data;
	}

	/**
	 * Include add-ons line item meta.
	 *
	 * @param  WC_Order_Item_Product $item           Order item data.
	 * @param  string                $cart_item_key  Cart item key.
	 * @param  array                 $values         Order item values.
	 */
	public function order_line_item( $item, $cart_item_key, $values ) {
		if ( ! empty( $values['addons'] ) && ! isset( $values['addons']['epo'] ) ) { #TM Extra Product Options extension

			$ids   = array();
			$total = 0;

			foreach ( $values['addons'] as $addon ) {

				if ( ! is_array( $addon ) ) {
					continue;
				}

				$value      = $addon['value'];
				$raw_value  = $addon['value'];
				$price_type = $addon['price_type'];
				$product    = $item->get_product();

				/**
				 * Get the line item price
				 *
				 * @since 7.0.3
				 */
				$product_price = apply_filters( 'woocommerce_product_addons_get_order_item_price', $product->get_price(), $values );

				// Pass the timestamp as the add-on value in order to save the timestamp to the DB.
				if ( isset( $addon['timestamp'] ) ) {
					$value = $addon['timestamp'];
				}

				/*
				 * Create a clone of the current cart item and set its price equal to the add-on price.
				 * This will allow extensions to discount the add-on price.
				 */
				$cloned_product = WC_Product_Addons_Helper::create_product_with_filtered_addon_prices( $values['data'], $addon['price'] );
				$addon['price'] = $cloned_product->get_price();

				$add_price_to_value = apply_filters( 'woocommerce_addons_add_order_price_to_value', false, $item );

				/*
				 * For percentage based price type we want
				 * to show the calculated price instead of
				 * the price of the add-on itself and in this
				 * case its not a price but a percentage.
				 * Also if the product price is zero, then there
				 * is nothing to calculate for percentage so
				 * don't show any price.
				 */
				if ( $addon['price'] && 'percentage_based' === $price_type && 0 != $product_price ) {
					$addon_price = (float) $product_price * ( $addon['price'] / 100 );
				} else {
					$addon_price = $addon['price'];
				}

				$prev_product = null;

				if ( isset( $GLOBALS['product'] ) && ! is_null( $GLOBALS['product'] ) ) {
					$prev_product = $GLOBALS['product'];
				}

				$GLOBALS['product'] = $cloned_product;

				$price = html_entity_decode(
					strip_tags( wc_price( WC_Product_Addons_Helper::get_product_addon_price_for_display( $addon_price ) ) ),
					ENT_QUOTES,
					get_bloginfo( 'charset' )
				);

				if ( ! is_null( $prev_product ) ) {
					$GLOBALS['product'] = $prev_product;
				} else {
					unset( $GLOBALS['product'] );
				}

				/*
				 * If there is an add-on price, add the price of the add-on
				 * to the selected option.
				 */
				if ( 'flat_fee' === $price_type && $addon['price'] && $add_price_to_value ) {
					$price = 0 <= $price ? '+ ' . $price : $price;
					/* translators: %1$s flat fee addon price in order */
					$value .= sprintf( _x( ' (%1$s)', 'flat fee addon price in order', 'woocommerce-appointments' ), $price );
				} elseif ( ( 'quantity_based' === $price_type || 'percentage_based' === $price_type ) && $addon['price'] && $add_price_to_value ) {
					/* translators: %1$s addon price in order */
					$value .= sprintf( _x( ' (%1$s)', 'addon price in order', 'woocommerce-appointments' ), $price );
				} elseif ( 'custom_price' === $addon['field_type'] ) {
					/* translators: %1$s custom addon price in order */
					$value     = sprintf( _x( '%1$s', 'custom addon price in order', 'woocommerce-appointments' ), $price ); // phpcs:ignore WordPress.WP.I18n.NoEmptyStrings
					$raw_value = $addon_price;
				}

				$meta_data = [
					'key'        => $addon['name'],
					'value'      => $value,
					'id'         => $addon['id'],
					'raw_value'  => $raw_value,
					'raw_price'  => (float) $addon['price'],
					'price_type' => $price_type,
				];

				// In case the add-on is a datepicker, save the offset and the timestamp for display transformations.
				if ( isset( $addon['offset'] ) ) {
					$meta_data['offset'] = $addon['offset'];
				}

				if ( isset( $addon['timestamp'] ) ) {
					$meta_data['timestamp'] = $addon['timestamp'];
				}

				$meta_data = apply_filters( 'woocommerce_product_addons_order_line_item_meta', $meta_data, $addon, $item, $values );

				$item->add_meta_data( $meta_data['key'], $meta_data['value'] );

				$ids[] = $meta_data;

				if ( 'quantity_based' === $price_type || 'percentage_based' === $price_type ) {
					$addon_price = (float) $addon_price * $item->get_quantity();
				}

				$total += (float) $addon_price;
			}

			$item->add_meta_data( '_pao_ids', $ids );
			$item->add_meta_data( '_pao_total', $total );
		}
	}

	/**
	 * Filter the order item's meta display value if needed.
	 *
	 * @param  string        $display_value
	 * @param  stdObject     $meta
	 * @param  WC_Order_Item $order_item
	 * @return string
	 */
	public function order_item_display_meta_value( $display_value, $meta = null, $order_item = null ) {
		if ( is_null( $meta ) || is_null( $order_item ) ) {
			return $display_value;
		}

		$addons = $order_item->get_meta( '_pao_ids', true );

		if ( empty( $addons ) ) {
			return $display_value;
		}

		foreach ( $addons as $addon ) {
			if ( isset( $addon['timestamp'] ) && isset( $addon['offset'] ) && $addon['key'] === $meta->key ) {
				$converted_date = date_i18n( get_option( 'date_format' ), WC_Product_Addons_Helper::wc_pao_convert_timestamp_to_gmt_offset( (int) $addon['timestamp'], is_admin() ? null : -1 * $addon['offset'] ) );
				$display_value  = str_replace( $addon['timestamp'], $converted_date, $display_value );
			}
		}

		return $display_value;
	}

	/**
	 * Re-order.
	 *
	 * @since 3.0.0
	 * @param  array    $cart_item_meta  Cart item data.
	 * @param  array    $item            Cart item.
	 * @param  WC_order $order           Order object.
	 *
	 * @return array Cart item data
	 */
	public function re_add_cart_item_data( $cart_item_data, $item, $order = null ) {
		// Disable validation.
		remove_filter( 'woocommerce_add_to_cart_validation', array( $this, 'validate_add_cart_item' ), 999, 3 );

		// When renewing a subscription, add-on data are already part of $cart_item_data[ 'subscription_renewal' ][ 'custom_line_item_meta' ][ '_pao_ids' ].
		// WooCommerce Subscriptions is responsible for rendering these add-ons.
		if ( isset( $cart_item_data['subscription_renewal'] ) ) {
			return $cart_item_data;
		}

		// When paying for a subscription's initial payment, add-on data is automatically copied in $cart_item_data[ 'subscription_initial_payment' ][ 'custom_line_item_meta' ].
		// Unlike renewals, WooCommerce Subscriptions doesn't grandfather item prices for initial payments.
		// We need to remove the add-on data WooCommerce Subscriptions copied to the cart item data and re-add it to the cart item data.
		if ( isset( $cart_item_data['subscription_initial_payment']['custom_line_item_meta']['_pao_ids'] ) ) {
			foreach ( $cart_item_data['subscription_initial_payment']['custom_line_item_meta']['_pao_ids'] as $addon ) {
				unset( $cart_item_data['subscription_initial_payment']['custom_line_item_meta'][ $addon['key'] ] );
			}

			unset( $cart_item_data['subscription_initial_payment']['custom_line_item_meta']['_pao_ids'] );
		}

		// Get addon data.
		$product_addons = WC_Product_Addons_Helper::get_product_addons( $item['product_id'] );
		$ids            = $item->get_meta( '_pao_ids', true );

		// Backwards compatibility for orders with Addons without ID.
		if ( empty( $ids ) ) {
			$ids = $item->get_meta_data();
		}

		if ( empty( $cart_item_data['addons'] ) ) {
			$cart_item_data['addons'] = [];
		}

		if ( is_array( $product_addons ) && ! empty( $product_addons ) ) {
			include_once WC_PRODUCT_ADDONS_PLUGIN_PATH . '/includes/fields/abstract-wc-product-addons-field.php';

			foreach ( $product_addons as $addon ) {
				$value = '';
				$field = '';

				// If type is heading, skip.
				if ( ! is_array( $addon ) || 'heading' === $addon['type'] ) {
					continue;
				}

				switch ( $addon['type'] ) {
					case 'checkbox':
						include_once WC_PRODUCT_ADDONS_PLUGIN_PATH . '/includes/fields/class-wc-product-addons-field-list.php';

						$value = $this->get_addon_meta_value( $ids, $addon, 'checkbox' );

						if ( empty( $value ) ) {
							continue 2;
						}

						$field = new WC_Product_Addons_Field_List( $addon, $value );
						break;
					case 'multiple_choice':
						$value = [];
						switch ( $addon['display'] ) {
							case 'radiobutton':
								include_once WC_PRODUCT_ADDONS_PLUGIN_PATH . '/includes/fields/class-wc-product-addons-field-list.php';

								$value = $this->get_addon_meta_value( $ids, $addon, 'radiobutton' );

								if ( empty( $value ) ) {
									continue 3;
								}

								$field = new WC_Product_Addons_Field_List( $addon, $value );
								break;
							case 'images':
							case 'select':
								include_once WC_PRODUCT_ADDONS_PLUGIN_PATH . '/includes/fields/class-wc-product-addons-field-select.php';

								$value = $this->get_addon_meta_value( $ids, $addon, 'select' );

								if ( empty( $value ) ) {
									continue 3;
								}

								$loop = 0;

								foreach ( $addon['options'] as $option ) {
									++$loop;

									if ( sanitize_title( $option['label'] ) == $value ) {
										$value = $value . '-' . $loop;
										break;
									}
								}

								$field = new WC_Product_Addons_Field_Select( $addon, $value );
								break;
						}
						break;
					case 'select':
						include_once WC_PRODUCT_ADDONS_PLUGIN_PATH . '/includes/fields/class-wc-product-addons-field-select.php';

						$value = $this->get_addon_meta_value( $ids, $addon, 'select' );

						if ( empty( $value ) ) {
							continue 2;
						}

						$loop = 0;

						foreach ( $addon['options'] as $option ) {
							++$loop;

							if ( sanitize_title( $option['label'] ) == $value ) {
								$value = $value . '-' . $loop;
								break;
							}
						}

						$field = new WC_Product_Addons_Field_Select( $addon, $value );
						break;
					case 'custom_text':
					case 'custom_textarea':
					case 'custom_price':
					case 'input_multiplier':
						include_once WC_PRODUCT_ADDONS_PLUGIN_PATH . '/includes/fields/class-wc-product-addons-field-custom.php';

						$value = $this->get_addon_meta_value( $ids, $addon, 'input_multiplier' );

						if ( empty( $value ) ) {
							continue 2;
						}

						$field = new WC_Product_Addons_Field_Custom( $addon, $value );
						break;
					case 'file_upload':
						include_once WC_PRODUCT_ADDONS_PLUGIN_PATH . '/includes/fields/class-wc-product-addons-field-file-upload.php';

						$value = $this->get_addon_meta_value( $ids, $addon, 'file_upload' );

						if ( empty( $value ) ) {
							continue 2;
						}

						$field = new WC_Product_Addons_Field_File_Upload( $addon, $value, true );
						break;
					case 'datepicker':
						include_once WC_PRODUCT_ADDONS_PLUGIN_PATH . '/includes/fields/class-wc-product-addons-field-datepicker.php';

						$value = $this->get_addon_meta_value( $ids, $addon, 'datepicker' );

						if ( empty( $value ) ) {
							continue 2; // Skip to next addon in foreach loop.
						}

						foreach ( $ids as $meta ) {
							if ( $this->is_matching_addon( $addon, $meta ) ) {
								$timestamp = $meta['timestamp'];
								$offset    = $meta['offset'];
								break;
							}
						}

						$field = new WC_Product_Addons_Field_Datepicker( $addon, $value, $timestamp, $offset );
						break;
				}

				// Make sure a field is set (if not it could be product with no add-ons).
				if ( $field ) {

					$data = $field->get_cart_item_data();

					if ( is_wp_error( $data ) ) {
						wc_add_notice( $data->get_error_message(), 'error' );
					} elseif ( $data ) {
						// Get the post data.
						$post_data = $_POST;

						$cart_item_data['addons'] = array_merge( $cart_item_data['addons'], apply_filters( 'woocommerce_product_addon_reorder_cart_item_data', $data, $addon, $item['product_id'], $post_data ) );
					}
				}
			}
		}

		return $cart_item_data;
	}

	/**
	 * Updates the product price based on the add-ons and the quantity.
	 *
	 * Important: A replica of this function exists in WCPay\MultiCurrency\Compatibility\WooCommerceProductAddOns. Any changes must apply to that function too.
	 *
	 * @param  array $cart_item_data  Cart item meta data.
	 * @param  int   $quantity        Quantity of products in that cart item.
	 * @param  array $prices          Array of prices for that product to use in
	 *                                calculations.
	 *
	 * @return array
	 */
	public function update_product_price( $cart_item_data, $quantity, $prices ) {
		if ( ! empty( $cart_item_data['addons'] ) && apply_filters( 'woocommerce_product_addons_adjust_price', true, $cart_item_data ) ) {
			$price         = $prices['price'];
			$regular_price = $prices['regular_price'];
			$sale_price    = $prices['sale_price'];

			// Compatibility with Smart Coupons self declared gift amount purchase.
			if ( empty( $price ) && ! empty( $_POST['credit_called'] ) ) {
				// $_POST['credit_called'] is an array.
				if ( isset( $_POST['credit_called'][ $cart_item_data['data']->get_id() ] ) ) {
					$price         = (float) $_POST['credit_called'][ $cart_item_data['data']->get_id() ];
					$regular_price = $price;
					$sale_price    = $price;
				}
			}

			if ( empty( $price ) && ! empty( $cart_item_data['credit_amount'] ) ) {
				$price         = (float) $cart_item_data['credit_amount'];
				$regular_price = $price;
				$sale_price    = $price;
			}

			// Save the price before price type calculations to be used later.
			$cart_item_data['addons_price_before_calc']         = (float) $price;
			$cart_item_data['addons_regular_price_before_calc'] = (float) $regular_price;
			$cart_item_data['addons_sale_price_before_calc']    = (float) $sale_price;
			$cart_item_data['addons_flat_fees_sum']             = 0;

			foreach ( $cart_item_data['addons'] as $addon ) {
				if ( ! isset( $addon['price_type'] ) ) {
					continue;
				}
				$price_type  = $addon['price_type'];
				$addon_price = $addon['price'];

				switch ( $price_type ) {
					case 'percentage_based':
						$price         += (float) ( $cart_item_data['addons_price_before_calc'] * ( $addon_price / 100 ) );
						$regular_price += (float) ( $cart_item_data['addons_regular_price_before_calc'] * ( $addon_price / 100 ) );
						$sale_price    += (float) ( $cart_item_data['addons_sale_price_before_calc'] * ( $addon_price / 100 ) );
						break;
					case 'flat_fee':
						$flat_fee                                = $quantity > 0 ? (float) ( $addon_price / $quantity ) : 0;
						$price                                  += $flat_fee;
						$regular_price                          += $flat_fee;
						$sale_price                             += $flat_fee;
						$cart_item_data['addons_flat_fees_sum'] += $flat_fee;
						break;
					default:
						$price         += (float) $addon_price;
						$regular_price += (float) $addon_price;
						$sale_price    += (float) $addon_price;
						break;
				}
			}

			$updated_product_prices = [
				'price'                => $price,
				'regular_price'        => $regular_price,
				'sale_price'           => $sale_price,
				'addons_flat_fees_sum' => $cart_item_data['addons_flat_fees_sum'],
			];
			$updated_product_prices = apply_filters( 'woocommerce_product_addons_update_product_price', $updated_product_prices, $cart_item_data, $prices );

			$cart_item_data['data']->set_price( $updated_product_prices['price'] );

			$cart_item_data['addons_flat_fees_sum'] = $updated_product_prices['addons_flat_fees_sum'];

			// Only update regular price if it was defined.
			$has_regular_price = is_numeric( $cart_item_data['data']->get_regular_price( 'edit' ) );
			if ( $has_regular_price ) {
				$cart_item_data['data']->set_regular_price( $updated_product_prices['regular_price'] );
			}

			// Only update sale price if it was defined.
			$has_sale_price = is_numeric( $cart_item_data['data']->get_sale_price( 'edit' ) );
			if ( $has_sale_price ) {
				$cart_item_data['data']->set_sale_price( $updated_product_prices['sale_price'] );
			}
		}

		return $cart_item_data;
	}

	/**
	 * Add cart item. Fires after add cart item data filter.
	 *
	 * @since 3.0.0
	 * @param  array $cart_item_data  Cart item meta data.
	 *
	 * @return array
	 */
	public function add_cart_item( $cart_item_data ) {
 		$prices = [
 			'price'         => (float) $cart_item_data['data']->get_price( 'edit' ),
 			'regular_price' => (float) $cart_item_data['data']->get_regular_price( 'edit' ),
 			'sale_price'    => (float) $cart_item_data['data']->get_sale_price( 'edit' ),
 		];

 		return $this->update_product_price( $cart_item_data, $cart_item_data['quantity'], $prices );
 	}

	/**
	 * Update cart item quantity.
	 *
	 * @param  array    $cart_item_key  Cart item key.
	 * @param  integer  $quantity       New quantity of the product.
	 * @param  integer  $old_quantity   Old quantity of the product.
	 * @param  \WC_Cart $cart           WC Cart object.
	 *
	 * @return array
	 */
	public function update_price_on_quantity_update( $cart_item_key, $quantity, $old_quantity, $cart ) {
 		$cart_item_data = $cart->get_cart_item( $cart_item_key );

		#error_log( var_export( $cart_item_data, true ) );

		if ( ! empty( $cart_item_data['addons'] ) ) {
			$prices = array(
				'price'         => $cart_item_data['addons_price_before_calc'],
				'regular_price' => $cart_item_data['addons_regular_price_before_calc'],
				'sale_price'    => $cart_item_data['addons_sale_price_before_calc'],
			);

			// Set new cart item data, when cart item quantity changes.
			$cart_item_data             = $this->update_product_price( $cart_item_data, $quantity, $prices );
			$contents                   = $cart->get_cart_contents();
			$contents[ $cart_item_key ] = $cart_item_data;
			$cart->set_cart_contents( $contents );
		}
 	}

	/**
	 * Get cart item from session.
	 *
	 * @param  array $cart_item  Cart item data.
	 * @param  array $values     Cart item values.
	 *
	 * @return array
	 */
	public function get_cart_item_from_session( $cart_item, $values ) {
		if ( ! empty( $values['addons'] ) && ! isset( $values['addons']['epo'] ) ) { #TM Extra Product Options extension
			$prices              = [
				'price'         => (float) $cart_item['data']->get_price( 'edit' ),
				'regular_price' => (float) $cart_item['data']->get_regular_price( 'edit' ),
				'sale_price'    => (float) $cart_item['data']->get_sale_price( 'edit' ),
			];
			$cart_item['addons'] = $values['addons'];
			$cart_item           = $this->update_product_price( $cart_item, $cart_item['quantity'], $prices );
		}

		return $cart_item;
	}

	/**
	 * Get item data.
	 *
	 * Important: A replica of this function exists in WCPay\MultiCurrency\Compatibility\WooCommerceProductAddOns. Any changes must apply to that function too.
	 *
	 * @param  array $other_data  Other data.
	 * @param  array $cart_item   Cart item data.
	 *
	 * @return array
	 */
	public function get_item_data( $other_data, $cart_item ) {
 		if ( ! empty( $cart_item['addons'] ) && ! isset( $cart_item['addons']['epo'] ) ) { #TM Extra Product Options extension
 			foreach ( $cart_item['addons'] as $addon ) {

 				$price = isset( $cart_item['addons_price_before_calc'] ) ? $cart_item['addons_price_before_calc'] : $addon['price'];
 				$value = $addon['value'];

 				/*
 				 * Create a clone of the current cart item and set its price equal to the add-on price.
 				 * This will allow extensions to discount the add-on price.
 				 */
 				$cloned_cart_item = WC_Product_Addons_Helper::create_product_with_filtered_addon_prices( $cart_item['data'], $addon['price'] );
 				$addon['price']   = $cloned_cart_item->get_price();

 				$add_price_to_value = apply_filters( 'woocommerce_addons_add_cart_price_to_value', false, $cart_item );

				$prev_product = null;

				if ( isset( $GLOBALS['product'] ) && ! is_null( $GLOBALS['product'] ) ) {
					$prev_product = $GLOBALS['product'];
				}

 				$GLOBALS['product'] = $cloned_cart_item;

 				if ( 0 == $addon['price'] ) {
 					$value .= '';
 				} elseif ( 'percentage_based' === $addon['price_type'] && 0 == $price ) {
 					$value .= '';
 				} elseif ( 'flat_fee' === $addon['price_type'] && $addon['price'] ) {
 					$addon_price = wc_price( WC_Product_Addons_Helper::get_product_addon_price_for_display( $addon['price'], $cart_item['data'], true ) );
					$addon_price = 0 < $price ? '+ ' . $addon_price : $addon_price;
					/* translators: %1$s flat fee addon price in cart */
					$value .= sprintf( _x( ' (%1$s)', 'flat fee addon price in cart', 'woocommerce-appointments' ), $addon_price );
 				} elseif ( 'custom_price' === $addon['field_type'] && $addon['price'] ) {
 					$addon_price = wc_price( WC_Product_Addons_Helper::get_product_addon_price_for_display( $addon['price'], $cart_item['data'], true ) );
					/* translators: %1$s custom addon price in cart */
					$value           .= sprintf( _x( ' (%1$s)', 'custom price addon price in cart', 'woocommerce-appointments' ), $addon_price );
 					$addon['display'] = $value;
				} elseif ( 'quantity_based' === $addon['price_type'] && $addon['price'] && $add_price_to_value ) {
 					$addon_price = wc_price( WC_Product_Addons_Helper::get_product_addon_price_for_display( $addon['price'], $cart_item['data'], true ) );
					/* translators: %1$s quantity based addon price in cart */
					$value .= sprintf( _x( ' (%1$s)', 'quantity based addon price in cart', 'woocommerce-appointments' ), $addon_price );
				} elseif ( 'percentage_based' === $addon['price_type'] && $addon['price'] && $add_price_to_value ) {
 					$_product = wc_get_product( $cart_item['product_id'] );
 					$_product->set_price( $price * ( $addon['price'] / 100 ) );
 					$addon_price = WC()->cart->get_product_price( $_product );
					/* translators: %1$s percentage based addon price in cart */
					$value .= sprintf( _x( ' (%1$s)', 'percentage based addon price in cart', 'woocommerce-appointments' ), $addon_price );
 				}

				if ( ! is_null( $prev_product ) ) {
					$GLOBALS['product'] = $prev_product;
				} else {
					unset( $GLOBALS['product'] );
				}

 				$addon_data = array(
 					'name'    => $addon['name'],
 					'value'   => $value,
 					'display' => isset( $addon['display'] ) ? $addon['display'] : '',
 				);

 				$other_data[] = apply_filters( 'woocommerce_product_addons_get_item_data', $addon_data, $addon, $cart_item );
 			}
 		}

 		return $other_data;
 	}

	/**
	 * This gets the value for an add-on while considering backward compatibility for orders created before the `raw_value` field addition.
	 *
	 * @since 6.9.0
	 * @param  array|object $meta  Meta data.
	 *
	 * @return mixed
	 */
	private function extract_meta_value( $meta ) {
		if ( is_object( $meta ) ) {
			return $meta->raw_value ?? $meta->value;
		}

		return $meta['raw_value'] ?? $meta['value'];
	}

	/**
	 * Grabs the value of a product addon from order item meta.
	 *
	 * @param  array  $ids   Array of addon meta that include id, name and value.
	 * @param  array  $addon
	 * @param  string $type  Addon type.
	 *
	 * @return array
	 */
	public function get_addon_meta_value( $ids, $addon, $type ) {
		$value = array();

		if ( 'checkbox' === $type || 'radiobutton' === $type ) {
			foreach ( $ids as $meta ) {
				if ( $this->is_matching_addon( $addon, $meta ) ) {
					$meta_value = is_object( $meta ) ? $meta->value : $meta['value'];
					if ( is_array( $meta_value ) && ! empty( $meta_value ) ) {
						$value[] = array_map( 'sanitize_title', $meta_value );
					} else {
						$value[] = sanitize_title( $meta_value );
					}
				}
			}
		} elseif ( 'select' === $type ) {
			foreach ( $ids as $meta ) {
				if ( $this->is_matching_addon( $addon, $meta ) ) {
					$meta_value = $this->extract_meta_value( $meta );
					$value      = sanitize_title( $meta_value );
					break;
				}
			}
		} elseif ( 'datepicker' === $type ) {
			foreach ( $ids as $meta ) {
				if ( $this->is_matching_addon( $addon, $meta ) ) {
					$meta_value     = $this->extract_meta_value( $meta );
					$converted_date = date_i18n( get_option( 'date_format' ), WC_Product_Addons_Helper::wc_pao_convert_timestamp_to_gmt_offset( (int) $meta['timestamp'], is_admin() ? null : - 1 * $meta['offset'] ) );
					$display_value  = str_replace( $meta_value, $converted_date, $meta_value );
					$value          = sanitize_title( $display_value );
					break;
				}
			}
		} elseif ( 'custom_price' === $type ) {
			foreach ( $ids as $meta ) {
				if ( $this->is_matching_addon( $addon, $meta ) ) {
					$meta_value = $meta['price'] ?? $meta['value'];
					$value      = wc_clean( $meta_value );
					break;
				}
			}
		} else {
			foreach ( $ids as $meta ) {
				if ( $this->is_matching_addon( $addon, $meta ) ) {
					$meta_value = $this->extract_meta_value( $meta );
					$value      = wc_clean( $meta_value );
					break;
				}
			}
		}

		return $value;
	}

	/**
	 * Checks if an order item addon meta matches a product level addon.
	 *
	 * @param  array        $addon
	 * @param  array|object $meta
	 *
	 * @return boolean
	 */
	public function is_matching_addon( $addon, $meta ) {
		if (
			is_array( $meta )
			&& isset( $addon['id'] )
			&& isset( $meta['id'] )
			&& 0 !== $addon['id']
			&& 0 !== $meta['id'] ) {
				$match = (string) $addon['id'] === (string) $meta['id'] && stripos( $meta['key'], $addon['name'] ) === 0;
		} else {
			// Backwards compatibility for addons without ID.
			$meta_key = is_object( $meta ) ? $meta->key : $meta['key'];
			$match    = stripos( $meta_key, $addon['name'] ) === 0;
		}

		return $match;
	}

	/**
	 * Checks if the added product is a grouped product.
	 *
	 * @param  int $product_id  Product ID.
	 *
	 * @return bool
	 */
	public function is_grouped_product( $product_id ) {
		$product = wc_get_product( $product_id );

		return $product->is_type( 'grouped' );
	}
}
