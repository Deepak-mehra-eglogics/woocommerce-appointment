<?php
/**
 * WC_Product_Addons_Admin_Ajax class
 *
 * @package  WooCommerce Product Add-Ons
 * @since    7.3.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Product AddOns edit-order functions and filters.
 *
 * @class    WC_Product_Addons_Admin_Ajax
 * @version  7.2.0
 */
class WC_Product_Addons_Admin_Ajax {
	/**
	 * Hook in.
	 */
	public static function init() {
		/*
		 * Edit-Order screens.
		 */

		// Ajax handler used to fetch form content for populating "Configure/Edit" add-ons order item modals.
		add_action( 'wp_ajax_woocommerce_configure_addon_order_item', array( __CLASS__, 'ajax_addon_order_item_form' ) );

		// Ajax handler used to store updated order item.
		add_action( 'wp_ajax_woocommerce_edit_addon_order_item', array( __CLASS__, 'ajax_edit_addon_order_item' ) );
	}

	/**
	 * Generates a string describing the change in an addon value.
	 *
	 * @param  array        $addon         The addon.
	 * @param  string|array $posted_value  Posted value.
	 * @param  string|array $current_value Current value.
	 * @return string|null
	 */
	protected static function generate_change_string( $addon, $posted_value, $current_value ) {
		if ( isset( $addon['type'] ) && 'datepicker' === $addon['type'] ) {
			$posted_value  = $posted_value['display'] ?? '';
			$current_value = $current_value['display'] ?? '';
		}

		if ( $posted_value === $current_value ) {
			return null;
		}

		if ( is_array( $posted_value ) ) {
			$posted_value = implode( ', ', $posted_value );
		}
		if ( is_array( $current_value ) ) {
			$current_value = implode( ', ', $current_value );
		}

		if ( isset( $addon['type'] ) && 'custom_price' === $addon['type'] ) {
			$posted_value  = wc_format_localized_price( $posted_value );
			$current_value = wc_format_localized_price( $current_value );
		}

		if ( empty( $posted_value ) ) {
			$posted_value = __( 'None', 'woocommerce-appointments' );
		}
		if ( empty( $current_value ) ) {
			$current_value = __( 'None', 'woocommerce-appointments' );
		}

		/* translators: %1$s: Addon title, %2$s: Before value, %3$s: After value */
		return sprintf( _x( '%1$s changed from <em>(%2$s)</em> to <em>(%3$s)</em>', 'addon value changed note format', 'woocommerce-appointments' ), $addon['name'], $current_value, $posted_value );
	}

	/**
	 * Store updated product addons for an order item.
	 *
	 * @param  WC_Order_Item_Product $item    Order item.
	 * @param  WC_Product            $product Product.
	 * @param  WC_Order              $order   Order.
	 *
	 * @return bool
	 */
	public static function store_product_addons( $item, $product, $order ) {
		$product_addons = WC_Product_Addons_Helper::get_product_addons( $product->get_id() );

		if ( empty( $product_addons ) ) {
			return false;
		}

		$addon_cart = new WC_Product_Addons_Cart();
		// Nonce is verified in the parent method.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$valid = $addon_cart->validate_add_cart_item( true, $product->get_id(), 1, $_POST );
		/**
		 * 'woocommerce_product_addons_editing_in_order_validate' filter.
		 *
		 * Use this filter to validate the posted addon values.
		 *
		 * @since 6.9.0
		 *
		 * @param  bool           $valid
		 * @param  WC_Product     $product
		 * @param  WC_Order_Item  $item
		 * @param  WC_Order       $order
		 */
		$valid = apply_filters( 'woocommerce_product_addons_editing_in_order_validate', $valid, $product, $item, $order );

		if ( ! $valid ) {
			return false;
		}

		$current_values = static::get_addon_values( $item );

		// Pretend we're in some sort of cart so that we can reuse as much code as possible.
		$values = $addon_cart->add_cart_item_data( array(), $product->get_id() );

		/**
		 * 'woocommerce_product_addons_editing_in_order_values' filter.
		 *
		 * Use this filter to modify the posted addon values.
		 *
		 * @since 6.9.0
		 *
		 * @param  array          $values
		 * @param  WC_Product     $product
		 * @param  WC_Order_Item  $item
		 * @param  WC_Order       $order
		 */
		$values = apply_filters( 'woocommerce_product_addons_editing_in_order_values', $values, $product, $item, $order );

		$posted_values = static::get_addon_values( $item, $values );

		// Compare posted against current addon values.
		if ( $posted_values !== $current_values ) {
			/**
			 * 'woocommerce_product_addons_editing_in_order' action.
			 *
			 * @since  6.9.0
			 *
			 * @param  array          $values
			 * @param  WC_Order_Item  $item
			 * @param  WC_Order       $order
			 */
			do_action( 'woocommerce_product_addons_editing_in_order', $values, $item, $order );

			$changes_map = array();

			// Remove existing metadata and create order note.
			foreach ( $product_addons as $product_addon ) {
				if ( ! is_array( $product_addon ) ) {
					continue;
				}
				$changes_map[ $product_addon['id'] ] = self::generate_change_string( $product_addon, $posted_values[ $product_addon['id'] ] ?? '', $current_values[ $product_addon['id'] ] ?? '' );
				$item->delete_meta_data( $product_addon['name'] );
			}
			$changes_map = array_filter(
				$changes_map,
				static function ( $value ) {
					return ! empty( $value );
				}
			);

			$item->delete_meta_data( '_pao_ids' );
			$item->delete_meta_data( '_pao_total' );

			$item_data = array(
				'product_id'   => $item['product_id'],
				'variation_id' => $item['variation_id'],
				'quantity'     => $item['qty'],
				'data'         => $product,
			);
			$item_data = $addon_cart->get_cart_item_from_session( $item_data, $values );

			$addon_cart->order_line_item( $item, null, $item_data );
			$item['subtotal'] = wc_get_price_excluding_tax(
				$product,
				array(
					'price' => $item_data['data']->get_price(),
					'qty'   => $item['qty'],
				)
			);
			$item['total']    = $item['subtotal'];

			$item->save_meta_data();
			$item->save();

			if ( ! ( empty( $changes_map ) ) ) {
				$order->add_order_note(
					sprintf(
					/* translators: List of items */
						__( 'Adjusted product addons: %s', 'woocommerce-appointments' ),
						'<br><br>' . implode( '<br>', $changes_map )
					),
					false,
					true
				);
			}

			if ( isset( $_POST['country'], $_POST['state'], $_POST['postcode'], $_POST['city'] ) ) {

				$calculate_tax_args = array(
					'country'  => strtoupper( wc_clean( wp_unslash( $_POST['country'] ) ) ),
					'state'    => strtoupper( wc_clean( wp_unslash( $_POST['state'] ) ) ),
					'postcode' => strtoupper( wc_clean( wp_unslash( $_POST['postcode'] ) ) ),
					'city'     => strtoupper( wc_clean( wp_unslash( $_POST['city'] ) ) ),
				);

				$order->calculate_taxes( $calculate_tax_args );
				$order->calculate_totals( false );

			} else {
				$order->save();
			}
		}

		return true;
	}

	/**
	 * Ajax handler used to store updated order item.
	 *
	 * @return void
	 */
	public static function ajax_edit_addon_order_item() {
		$failure = array(
			'result' => 'failure',
		);

		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			wp_send_json( $failure );
		}

		if ( ! check_ajax_referer( 'wc_pao_edit_addon', 'security', false ) ) {
			wp_send_json( $failure );
		}

		try {
			list( $order, $item, $product ) = self::validate_request_and_fetch_data();
		} catch ( Exception $e ) {
			wp_send_json( $failure );
		}

		if ( ! ( $product instanceof WC_Product ) ) {
			wp_send_json( $failure );
		}

		$result = self::store_product_addons( $item, $product, $order );

		if ( ! $result ) {
			wp_send_json( $failure );
		}

		ob_start();
		include WC_ABSPATH . 'includes/admin/meta-boxes/views/html-order-items.php';
		$html = ob_get_clean();

		ob_start();
		$notes = wc_get_order_notes( array( 'order_id' => $order->get_id() ) );
		include WC_ABSPATH . 'includes/admin/meta-boxes/views/html-order-notes.php';
		$notes_html = ob_get_clean();

		$response = array(
			'result'     => 'success',
			'html'       => $html,
			'notes_html' => $notes_html,
		);

		wp_send_json( $response );
	}

	/**
	 * Render the form content for populating "Configure/Edit" addon order item modals.
	 *
	 * @param  WC_Order|null      $order    Order.
	 * @param  WC_Order_Item|null $item     Order item.
	 * @param  WC_Product         $product  Product.
	 *
	 * @return string|false
	 */
	public static function render_form( $order, $item, $product ) {
		$product_addons = WC_Product_Addons_Helper::get_product_addons( $product->get_id() );

		if ( empty( $product_addons ) ) {
			return false;
		}

		$addon_values = static::get_addon_values( $item );

		/**
		 * 'woocommerce_product_addons_editing_in_order_form_values' filter.
		 *
		 * Use this filter to modify the addon values before rendering the form.
		 *
		 * @since 6.9.0
		 *
		 * @param  array          $addon_values
		 * @param  WC_Product     $product
		 * @param  WC_Order_Item|null  $item
		 * @param  WC_Order|null       $order
		 */
		$addon_values = apply_filters( 'woocommerce_product_addons_editing_in_order_form_values', $addon_values, $product, $item, $order );

		/**
		 * 'woocommerce_product_addons_editing_in_order_form' action.
		 *
		 * Fired before the addon form is rendered.
		 *
		 * @since  6.9.0
		 *
		 * @param  array          $addon_values
		 * @param  WC_Order_Item|null  $item
		 * @param  WC_Order|null       $order
		 */
		do_action( 'woocommerce_product_addons_editing_in_order_form', $addon_values, $item, $order );

		ob_start();
		include WC_PRODUCT_ADDONS_PLUGIN_PATH . '/includes/admin/views/html-addon-order-edit-form.php';

		return ob_get_clean();
	}

	/**
	 * Form content used to populate "Configure/Edit" addon order item modals.
	 *
	 * @return void
	 */
	public static function ajax_addon_order_item_form() {
		$failure = array(
			'result' => 'failure',
		);

		if ( ! check_ajax_referer( 'wc_pao_edit_addon', 'security', false ) ) {
			wp_send_json( $failure );
		}

		try {
			list( $order, $item, $product ) = self::validate_request_and_fetch_data();
		} catch ( Exception $e ) {
			wp_send_json( $failure );
		}

		$html = self::render_form( $order, $item, $product );

		if ( false === $html ) {
			wp_send_json( $failure );
		}

		$response = array(
			'result' => 'success',
			'html'   => $html,
		);

		wp_send_json( $response );
	}

	/**
	 * Return the addon values for a cart item.
	 *
	 * @param  array|null $item         Cart item.
	 * @param  array|null $addon_values Addon values to use (or load from item).
	 *
	 * @return array Cart item data
	 */
	protected static function get_addon_values( $item, $addon_values = null ): array {
		if ( null === $item ) {
			return array();
		}

		if ( null === $addon_values ) {
			$addons_cart  = new WC_Product_Addons_Cart();
			$addon_values = $addons_cart->re_add_cart_item_data( array(), $item, null );
		}

		$addon_value_map = array();
		foreach ( $addon_values['addons'] as $addon_value ) {
			switch ( $addon_value['field_type'] ) {
				case 'custom_price':
					$value = $addon_value['price'];
					break;
				case 'datepicker':
					$value = array(
						'display'   => $addon_value['value'],
						'timestamp' => $addon_value['timestamp'],
						'offset'    => $addon_value['offset'],
					);
					break;
				default:
					$value = $addon_value['value'];
			}
			$addon_id = isset( $addon_value['id'] ) && 0 !== $addon_value['id'] ? $addon_value['id'] : sanitize_text_field( $addon_value['name'] );

			if ( isset( $addon_value_map[ $addon_id ] ) ) {
				$addon_value_map[ $addon_id ] = array_merge( (array) $addon_value_map[ $addon_id ], (array) $value );
				continue;
			}
			$addon_value_map[ $addon_id ] = $value;
		}

		return $addon_value_map;
	}

	/**
	 * Validate the request and fetch the order, item, and product.
	 *
	 * @throws Exception If the request is invalid.
	 *
	 * @return array
	 */
	public static function validate_request_and_fetch_data() : array {
 		if ( empty( $_POST['order_id'] ) || empty( $_POST['item_id'] ) ) {
 			throw new Exception( __( 'Missing order_id or item_id', 'woocommerce-appointments' ) );
 		}

		$order   = wc_get_order( wc_clean( wp_unslash( $_POST['order_id'] ) ) );
		$item_id = absint( wc_clean( wp_unslash( $_POST['item_id'] ) ) );

		if ( ! ( $order instanceof WC_Order ) ) {
			throw new Exception( __( 'Invalid order', 'woocommerce-appointments' ) );
		}

		$item = $order->get_item( $item_id );

		if ( ! ( $item instanceof WC_Order_Item ) || ( $item->meta_exists( '_pao_ids' ) && ! $item->meta_exists( '_pao_total' ) ) ) {
			throw new Exception( __( 'Invalid item', 'woocommerce-appointments' ) );
		}

		$product = $item->get_product();

		return array( $order, $item, $product );
	}
}

WC_Product_Addons_Admin_Ajax::init();
