<?php
/**
 * Product Add-ons helper
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Product_Addons_Helper class.
 *
 * @version 7.4.0
 */
class WC_Product_Addons_Helper {

	/**
	 * Array of Add-on IDs used in this request.
	 *
	 * @var array
	 */
	public static $addon_ids = array();

	/**
	 * Gets global product addons. The result is cached.
	 *
	 * @return array
	 */
	protected static function get_global_product_addons() {
		$cache_key     = 'all_products';
		$cache_group   = 'global_product_addons';
		$cache_value   = wp_cache_get( $cache_key, $cache_group );
		$last_modified = get_option( 'woocommerce_global_product_addons_last_modified' );

		if ( false === $cache_value || $last_modified !== $cache_value['last_modified'] ) {
			$args          = array(
				'posts_per_page'      => -1,
				'orderby'             => 'meta_value',
				'order'               => 'ASC',
				'meta_key'            => '_priority',
				'post_type'           => 'global_product_addon',
				'post_status'         => 'publish',
				'suppress_filters'    => true,
				'ignore_sticky_posts' => true,
				'no_found_rows'       => true,
				'fields'              => 'ids',
				'meta_query'          => array(
					array(
						'key'   => '_all_products',
						'value' => '1',
					),
				),
			);
			$return_addons = get_posts( $args );

			wp_cache_set(
				$cache_key,
				array(
					'last_modified' => $last_modified,
					'data'          => $return_addons,
				),
				$cache_group
			);
		} else {
			$return_addons = $cache_value['data'];
		}

		return (array) $return_addons;
	}

	/**
	 * Gets product addons from its terms. The result is cached.
	 *
	 * @param int $product_id The product ID.
	 * @return array
	 */
	protected static function get_product_term_addons( $product_id ) {
		$cache_key     = $product_id;
		$cache_group   = 'global_product_addons';
		$cache_value   = wp_cache_get( $cache_key, $cache_group );
		$last_modified = get_option( 'woocommerce_global_product_addons_last_modified' );

		if ( false === $cache_value || $last_modified !== $cache_value['last_modified'] ) {
			$return_addons = array();
			$product_terms = apply_filters( 'get_product_addons_product_terms', wc_get_object_terms( $product_id, 'product_cat', 'term_id' ), $product_id );

			if ( $product_terms ) {
				$args          = apply_filters(
					'get_product_addons_global_query_args',
					array(
						'posts_per_page'      => -1,
						'orderby'             => 'meta_value',
						'order'               => 'ASC',
						'meta_key'            => '_priority',
						'post_type'           => 'global_product_addon',
						'post_status'         => 'publish',
						'suppress_filters'    => true,
						'ignore_sticky_posts' => true,
						'no_found_rows'       => true,
						'fields'              => 'ids',
						'tax_query'           => array(
							array(
								'taxonomy'         => 'product_cat',
								'field'            => 'id',
								'terms'            => $product_terms,
								'include_children' => false,
							),
						),
					),
					$product_terms
				);
				$return_addons = get_posts( $args );
			}
			wp_cache_set(
				$cache_key,
				array(
					'last_modified' => $last_modified,
					'data'          => $return_addons,
				),
				$cache_group
			);
		} else {
			$return_addons = $cache_value['data'];
		}

		return (array) $return_addons;
	}

	/**
	 * Gets addons assigned to a product by ID.
	 *
	 * @param  int    $post_id ID of the product to get addons for.
	 * @param  string $prefix for addon field names. Defaults to postid.
	 * @param  bool   $inc_parent Set to false to not include parent product addons.
	 * @param  bool   $inc_global Set to false to not include global addons.
	 * @return array
	 */
	public static function get_product_addons( $post_id, $prefix = false, $inc_parent = true, $inc_global = true ) {
		if ( ! $post_id ) {
			return array();
		}

		$addons     = array();
		$raw_addons = array();
		$parent_id  = wp_get_post_parent_id( $post_id );

		$product = wc_get_product( $post_id );
		if ( ! $product ) {
			return array();
		}
		$exclude        = $product->get_meta( '_product_addons_exclude_global' );
		$product_addons = array_filter( (array) $product->get_meta( '_product_addons' ) );

		// Product Parent Level Addons.
		if ( $inc_parent && $parent_id ) {
			$raw_addons[10]['parent'] = apply_filters( 'get_parent_product_addons_fields', self::get_product_addons( $parent_id, $parent_id . '-', false, false ), $post_id, $parent_id );
		}

		// Product Level Addons.
		$raw_addons[10]['product'] = apply_filters( 'get_product_addons_fields', $product_addons, $post_id );

		// Generate field names with unique prefixes.
		if ( ! $prefix ) {
			$prefix = apply_filters( 'product_addons_field_prefix', "{$post_id}-", $post_id );
		}

		// Global level addons (all products and categories).
		if ( '1' !== $exclude && $inc_global ) {
			$product_id    = ( $inc_parent && $parent_id ) ? $parent_id : $product->get_id();
			$global_addons = array_merge( self::get_global_product_addons(), self::get_product_term_addons( $product_id ) );

			if ( $global_addons ) {
				foreach ( $global_addons as $global_addon_id ) {
					$priority                                    = get_post_meta( $global_addon_id, '_priority', true );
					$raw_addons[ $priority ][ $global_addon_id ] = apply_filters( 'get_product_addons_fields', array_filter( (array) get_post_meta( $global_addon_id, '_product_addons', true ) ), $global_addon_id );
					foreach ( $raw_addons[ $priority ][ $global_addon_id ] as &$raw_addon ) {
						if ( ! isset( $raw_addon['id'] ) ) {
							continue;
						}
						$raw_addon['field_name'] = $prefix . $raw_addon['id'];
					}
				}
			}
		}

		ksort( $raw_addons );

		foreach ( $raw_addons as $addon_group ) {
			if ( $addon_group ) {
				foreach ( $addon_group as $addon ) {
					$addons = array_merge( $addons, $addon );
				}
			}
		}

		// Let's avoid exceeding the suhosin default input element name limit of 64 characters.
		$max_addon_name_length = 45 - strlen( $prefix );

		// If the product_addons_field_prefix filter results in a very long prefix, then
		// go ahead and enforce sanity, exceed the default suhosin limit, and just use
		// the prefix and the field counter for the input element name.
		if ( $max_addon_name_length < 0 ) {
			$max_addon_name_length = 0;
		}

		$addon_field_counter = 0;

		foreach ( $addons as $addon_key => $addon ) {
			if ( empty( $addon['name'] ) ) {
				unset( $addons[ $addon_key ] );
				continue;
			}
			if ( empty( $addons[ $addon_key ]['field_name'] ) ) {
				$addons[ $addon_key ]['field_name'] = $prefix . $addon_field_counter;
				++$addon_field_counter;
			}
		}

		return apply_filters( 'get_product_addons', $addons );
	}

	/**
	 * Display prices according to shop settings.
	 *
	 * @version 7.0.3
	 *
	 * @param  float      $price     Price to display.
	 * @param  WC_Product $cart_item Product from cart.
	 *
	 * @return float|string
	 */
	public static function get_product_addon_price_for_display( $price, $cart_item = null ) {
		$product = ! empty( $GLOBALS['product'] ) && is_object( $GLOBALS['product'] ) ? clone $GLOBALS['product'] : null;

		if ( '' === $price || '0' == $price ) {
			return 0.0;
		}

		$neg = false;

		if ( $price < 0 ) {
			$neg    = true;
			$price *= -1;
		}

		if ( ( is_cart() || is_checkout() || WC_PAO_Core_Compatibility::is_store_api_request( 'cart' ) || WC_PAO_Core_Compatibility::is_store_api_request( 'checkout' ) ) && null !== $cart_item ) {
			$product = wc_get_product( $cart_item->get_id() );
		}

		if ( is_object( $product ) ) {
			// Support new wc_get_price_excluding_tax() and wc_get_price_excluding_tax() functions.
			if ( function_exists( 'wc_get_price_excluding_tax' ) ) {
				$display_price = self::get_product_addon_tax_display_mode() === 'incl' ? wc_get_price_including_tax(
					$product,
					array(
						'qty'   => 1,
						'price' => $price,
					)
				) : wc_get_price_excluding_tax(
					$product,
					array(
						'qty'   => 1,
						'price' => $price,
					)
				);

				/**
				 * When a user is tax exempt and product prices are exclusive of taxes, WooCommerce displays prices as follows:
				 * - Catalog and product pages: including taxes
				 * - Cart and Checkout pages: excluding taxes
				 */
				if ( ( is_cart() || is_checkout() ) && ! empty( WC()->customer ) && WC()->customer->get_is_vat_exempt() && ! wc_prices_include_tax() ) {
					$display_price = wc_get_price_excluding_tax(
						$product,
						array(
							'qty'   => 1,
							'price' => $price,
						)
					);
				}
			} else {
				$display_price = self::get_product_addon_tax_display_mode() === 'incl' ? $product->get_price_including_tax( 1, $price ) : $product->get_price_excluding_tax( 1, $price );
			}
		} else {
			$display_price = $price;
		}

		if ( $neg ) {
			$display_price = '-' . $display_price;
		}

		return $display_price;
	}

	/**
	 * Return tax display mode depending on context.
	 *
	 * @return string
	 */
	public static function get_product_addon_tax_display_mode() {
		if ( is_cart() || is_checkout() ) {
			return get_option( 'woocommerce_tax_display_cart' );
		}

		return get_option( 'woocommerce_tax_display_shop' );
	}

	/**
	 * Checks if addon field is required.
	 *
	 * @since 3.0.0
	 * @param array $addon
	 * @return bool
	 */
	public static function is_addon_required( $addon = array() ) {
		if ( empty( $addon ) ) {
			return false;
		}

		$type     = ! empty( $addon['type'] ) ? $addon['type'] : '';
		$required = ! empty( $addon['required'] ) ? $addon['required'] : '';

		switch ( $type ) {
			case 'heading':
				return false;
				break;
			case 'multiple_choice':
			case 'checkbox':
			case 'file_upload':
				return '1' == $required;
				break;
			default:
				return '1' == $required;
				break;
		}
	}

	/**
	 * Checks if addon should display description.
	 *
	 * @since 3.07.28
	 * @param  array $addon  Current add-on.
	 * @return bool          True if should display description.
	 */
	public static function should_display_description( $addon = array() ) {
		if ( empty( $addon ) || empty( $addon['description_enable'] ) ) {
			return false;
		}

		// True if description enabled and there is a description.
		return ( ( ! empty( $addon['description'] ) && $addon['description_enable'] ) ? true : false );
	}

	/**
	 * Get addon restriction data
	 *
	 * @param array $addon Add-on field data.
	 * @version 7.0.3
	 */
	public static function get_restriction_data( $addon ) {

		$restriction_data = array();

		if ( isset( $addon['required'] ) && 1 === $addon['required'] ) {
			$restriction_data['required'] = 'yes';
		}

		if ( isset( $addon['restrictions_type'] ) && 'any_text' !== $addon['restrictions_type'] ) {
			$restriction_data['content'] = $addon['restrictions_type'];
		}

		if ( isset( $addon['restrictions'] ) && 1 === $addon['restrictions'] ) {
			if ( isset( $addon['min'] ) && '' !== $addon['min'] && $addon['min'] >= 0 ) {
				$restriction_data['min'] = $addon['min'];
			}

			if ( isset( $addon['max'] ) && '' !== $addon['max'] && $addon['max'] > 0 ) {
				$restriction_data['max'] = $addon['max'];
			}
		}

		// Negative quantities and custom prices are not allowed.
		// Quantity and Custom Price fields are freely set by shoppers and can
		// be used to discount 100% of the product value.
		// Only negative prices defined by merchants via the 'Adjust Price'
		// option are allowed.
		if ( empty( $restriction_data['min'] ) && ( 'input_multiplier' === $addon['type'] || 'custom_price' === $addon['type'] ) ) {
			$restriction_data['min'] = 0;
		}

		/**
		 * Use this filter to modify the addon restriction data.
		 *
		 * @since 6.0.0
		 *
		 * @param  array  $restriction_data
		 * @param  array  $addon
		 */
		return apply_filters( 'wc_pao_restriction_data', $restriction_data, $addon );
	}

	/**
	 * Checks WC version for backwards compatibility.
	 *
	 * @since 3.0.0
	 * @param string $version
	 */
	public static function is_wc_gte( $version ) {
		return version_compare( WC_VERSION, $version, '>=' );
	}

	/**
	 * Checks WC version for backwards compatibility.
	 *
	 * @since 3.0.0
	 * @param string $version
	 */
	public static function is_wc_gt( $version ) {
		return version_compare( WC_VERSION, $version, '>' );
	}

	/**
	 * Checks if server can handle upload filesize.
	 *
	 * @since 3.0.0
	 * @return bool
	 */
	public static function can_upload( $file ) {
		return $file < wp_max_upload_size();
	}

	/**
	 * Checks if file exceeds upload size limit.
	 *
	 * @since 3.0.33
	 * @param  array $post_file File from $_FILES.
	 * @return bool             True if over size limit.
	 */
	public static function is_filesize_over_limit( $post_file ) {
		$php_size_upload_errors = array( 1, 2 );

		if ( ! empty( $post_file['error'] ) && in_array( $post_file['error'], $php_size_upload_errors, true ) ) {
			return true;
		}

		if ( ! self::can_upload( $post_file['size'] ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Get the placeholder image URL for image swatch
	 * with no selection.
	 *
	 * @return string
	 */
	public static function no_image_select_placeholder_src() {
		$src = WC_PRODUCT_ADDONS_PLUGIN_URL . '/assets/images/no-image-select-placeholder.png';

		return apply_filters( 'woocommerce_product_addons_no_image_select_placeholder_src', $src );
	}

	/**
	 * Create a clone of the current product/cart item/order item and set its price equal to the add-on price.
	 * This will allow extensions to discount the add-on price.
	 *
	 * @param WC_Product $product
	 * @param float      $price
	 *
	 * @return WC_Product
	 */
	public static function create_product_with_filtered_addon_prices( $product, $price ) {

		$cloned_product = clone $product;
		$cloned_product->set_price( $price );
		$cloned_product->set_regular_price( $price );
		$cloned_product->set_sale_price( $price );

		// Prevent Product Bundles from changing add-on prices.
		if ( class_exists( 'WC_PB_Addons_Compatibility' ) && class_exists( 'WC_PB_Product_Data' ) && ! is_null( WC_PB()->product_data->get( $cloned_product, 'bundled_cart_item' ) ) ) {
			WC_PB()->product_data->delete( $cloned_product, 'bundled_cart_item' );
		}

		// Prevent Composite Products from changing add-on prices.
		if ( class_exists( 'WC_CP_Addons_Compatibility' ) && class_exists( 'WC_CP_Product_Data' ) && ! is_null( WC_CP()->product_data->get( $cloned_product, 'composited_cart_item' ) ) ) {
			WC_CP()->product_data->delete( $cloned_product, 'composited_cart_item' );
		}

		/**
		 * All Products for WooCommerce Subscriptions compatibility.
		 *
		 * If All Products for WooCommerce Subscriptions shouldn't discount add-ons, then remove flat fees from the price offset used to
		 * calculate discounts.
		 */
		if ( class_exists( 'WCS_ATT_Integration_PAO' ) && class_exists( 'WCS_ATT_Product' ) && class_exists( 'WCS_ATT_Product_Data' ) ) {
 			if ( ! WCS_ATT_Integration_PAO::discount_addons( $cloned_product ) ) {
 				$instance = WCS_ATT()->product_data->get( $cloned_product, 'wcsatt_instance' );

 				// Create new SATT instance to avoid price offsets changing in the original product too.
 				if ( ! is_null( $instance ) ) {
 					$instance += 1;
 					WCS_ATT()->product_data->set( $cloned_product, 'wcsatt_instance', $instance );
 				}

 				WCS_ATT_Product::set_runtime_meta( $cloned_product, 'price_offset', $price );
 			}
 		}

		/*
		 * 'woocommerce_addons_cloned_product_with_filtered_price'
		 *
		 * Product Add-ons creates a dummy product with a price equal to the add-on prices.
		 * Then, it passes it through `get_price` to allow discount/price-related plugins to apply discounts.
		 * Use this filter to add a unique identifier to the dummy product, if you'd like to prevent discounts from applying to add-on prices.
		 *
		 * @since 6.5.2
		 *
		 * @param WC_Product $cloned_product
		 * @param WC_Product $product
		 * @param float      $price
		 */
		return apply_filters( 'woocommerce_addons_cloned_product_with_filtered_price', $cloned_product, $product, $price );
	}

	/**
	 * Get the date format for JS.
	 *
	 * Hint: Doesn't support time formats.
	 *
	 * @since  6.8.0
	 *
	 * @param  string $date_format (Optional) Date format in PHP
	 * @return string The date format for JS
	 */
	public static function wc_pao_get_js_date_format( $date_format = null ) {

		$format       = ! empty( $date_format ) ? $date_format : get_option( 'date_format' );
		$replacements = array(
			'A' => '',
			'a' => '',
			'B' => '',
			'b' => '',
			'C' => '',
			'c' => '',
			'D' => 'D',
			'd' => 'dd',
			'E' => '',
			'e' => '',
			'F' => 'MM',
			'f' => '',
			'G' => '',
			'g' => '',
			'H' => '',
			'h' => '',
			'I' => '',
			'i' => '',
			'J' => '',
			'j' => 'd',
			'K' => '',
			'k' => '',
			'L' => '',
			'l' => 'DD',
			'M' => 'M',
			'm' => 'mm',
			'N' => '',
			'n' => 'm',
			'O' => '',
			'o' => 'yy',
			'P' => '',
			'p' => '',
			'Q' => '',
			'q' => '',
			'R' => '',
			'r' => '', // RFC 2822, No equivalent.
			'S' => '',
			's' => '',
			'T' => 'z',
			't' => '',
			'U' => '@', // Unix timestamp.
			'u' => '',
			'V' => '',
			'v' => '',
			'W' => '',
			'w' => '',
			'X' => '',
			'x' => '',
			'Y' => 'yy',
			'y' => 'y',
			'Z' => '',
			'z' => '',
		);

		// Converts escaped characters.
		foreach ( $replacements as $from => $to ) {
			$replacements[ '\\' . $from ] = '\'' . $from . '\'';
		}

		$js_format = strtr( $format, $replacements );
		// Remove single quotes doubling up -- Hint: This action will not allow single quotes in date format strings.
		$js_format = str_replace( '\'\'', '', $js_format );

		return $js_format;
	}

	/**
	 * Get the timestamp type.
	 *
	 * @since  6.8.0
	 *
	 * @return string
	 */
	public static function wc_pao_get_date_input_timezone_reference() {

		/**
		 * `woocommerce_pao_date_input_timezone_reference` filter.
		 *
		 * How should the time be set. Available options are:
		 *
		 * @param  string  $reference  {
		 *
		 *     - 'store'  : Show UI with Store's Timezone and keep the date to the Store's timezone.
		 *                  Users select the time based on Store's clock. Foreign visitors need to be warned about this.
		 *
		 *     - 'default': Show UI with Clients's Timezone and convert the date to the Store's timezone.
		 *                  Works best when users are sending gift cards in the same timezone (Default)
		 * }
		 *
		 * @return string
		 */
		return apply_filters( 'woocommerce_pao_date_input_timezone_reference', 'default' );
	}

	/**
	 * Get the store's GMT offset.
	 *
	 * @since  6.8.0
	 *
	 * @return float
	 */
	public static function wc_pao_get_gmt_offset() {
		return (float) get_option( 'gmt_offset' );
	}

	/**
	 * Takes a timestamp in GMT and converts it to store's timezone.
	 *
	 * @since 6.8.0
	 *
	 * @param  int   $timestamp
	 * @param  float $offset
	 * @return int
	 */
	public static function wc_pao_convert_timestamp_to_gmt_offset( $timestamp, $gmt_offset = null ) {

		$store_timestamp = new DateTime();
		$store_timestamp->setTimestamp( $timestamp );

		// Get the Store's offset.
		if ( is_null( $gmt_offset ) ) {
			$gmt_offset = self::wc_pao_get_gmt_offset();
		}

		$store_timestamp->modify( $gmt_offset * 60 . ' minutes' );

		return $store_timestamp->getTimestamp();
	}

	/**
	 * Gets the default value for an addon.
	 *
	 * @param array $addon Add-on.
	 *
	 * @return mixed
	 */
	public static function wc_pao_get_default_addon_value( $addon ) {
		$value = isset( $addon['default'] ) ? $addon['default'] : '';

		if ( '' !== $value && isset( $addon['type'] ) ) {
			switch ( $addon['type'] ) {
				case 'input_multiplier':
					$value = (int) $value;
					break;
				case 'custom_price':
					$value = (float) $value;
					break;
				case 'multiple_choice':
					$value = (int) $value;

					// Get the label of the default option, based on the ID.
					$default_option_label = isset( $addon['options'][ $value ] ) ? $addon['options'][ $value ]['label'] : '';

					if ( in_array( $addon['display'], array( 'select', 'images' ), true ) ) {
						$value = '' !== $default_option_label ? sanitize_title( $default_option_label ) . '-' . ( $value + 1 ) : '';
					} elseif ( 'radiobutton' === $addon['display'] ) {
						$value = '' !== $default_option_label ? sanitize_title( $default_option_label ) : '';
					}
					break;
				case 'checkbox':
					$default_option_ids = array_map( 'intval', explode( ',', $value ) );
					$default_options    = array();
					foreach ( $default_option_ids as $default_option_id ) {
						if ( isset( $addon['options'][ $default_option_id ] ) ) {
							$default_options[] = sanitize_title( $addon['options'][ $default_option_id ]['label'] );
						}
					}
					$value = $default_options;
					break;
			}
		}

		return $value;
	}

	/**
	 * Generate a unique timestamp and use it as id.
	 *
	 * @since  6.9.0
	 *
	 * @param  array $existing_ids
	 * @return int
	 */
	public static function generate_id( $existing_ids = array() ) {
		$generated_id    = current_time( 'timestamp' );
		$blacklisted_ids = array_merge( $existing_ids, self::$addon_ids );
		$found_unique_id = false;

		while ( ! $found_unique_id ) {
			++$generated_id;
			if ( ! in_array( $generated_id, $blacklisted_ids ) ) {
				$found_unique_id = true;
			}
		}

		self::$addon_ids[] = $generated_id;

		return $generated_id;
	}

	/**
	 * Retrieves the permalink for a product with add-ons.
	 *
	 * @since 7.2.0
	 * @param  null|string $permalink The content.
	 * @param  null|array  $cart_item The cart item.
	 * @param  null|string $cart_item_key The cart item key.
	 * @return null|string
	 */
	public static function get_permalink( ?string $permalink, ?array $cart_item, ?string $cart_item_key ): ?string {
 		if ( empty( $permalink ) || empty( $cart_item ) || empty( $cart_item_key ) ) {
 			return $permalink;
 		}

 		$product = $cart_item['data'] ?? null;

 		if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
 			return $permalink;
 		}

		$product_addons = self::get_product_addons( $product->get_id() );

		/**
		 * Filter to allow pre-populating add-ons for products with add-ons from the cart item.
		 *
		 * @since 7.2.0
		 *
		 * @param  bool   $allow_cart_key  Whether to include the cart key in the permalink.
		 * @param  array  $cart_item       Cart item.
		 */
		if ( false === apply_filters( 'woocommerce_product_addons_cart_permalink', ! empty( $product_addons ), $cart_item ) ) {
			return $permalink;
		}

		return add_query_arg(
			array(
				'pao_key'  => $cart_item_key,
				'pao_edit' => 1,
			),
			$permalink
		);
	}
}
