<?php
/**
 * Product Add-ons display
 *
 * @package WC_Product_Addons/Classes/Display
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Product_Addon_Display class.
 *
 * @version 7.3.1
 */
class WC_Product_Addons_Display {
	public $show_num_chars;
	/**
	 * Initialize frontend actions.
	 */
	public function __construct() {
		// Styles.
		add_action( 'wp_enqueue_scripts', array( $this, 'styles' ) );
		add_action( 'wc_quick_view_enqueue_scripts', array( $this, 'quick_view_single_compat' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'addon_scripts' ), 9 );

		add_action( 'woocommerce_before_add_to_cart_form', array( $this, 'inject_addons' ), 8 );

		// Addon display.
		add_action( 'woocommerce_before_add_to_cart_button', array( $this, 'display' ), 10 );
		add_action( 'woocommerce_before_variations_form', array( $this, 'reposition_display_for_variable_product' ), 10 );
		add_action( 'woocommerce_product_addons_end', array( $this, 'totals' ), 10 );
		add_action( 'woocommerce_product_addons_end', array( $this, 'notices' ), 15 );

		// Change buttons/cart urls.
		add_filter( 'add_to_cart_text', array( $this, 'add_to_cart_text' ), 15 );
		add_filter( 'woocommerce_product_add_to_cart_text', array( $this, 'add_to_cart_text' ), 15, 2 );
		add_filter( 'woocommerce_product_has_options', array( $this, 'has_options' ), 15, 2 );
		add_filter( 'woocommerce_add_to_cart_url', array( $this, 'add_to_cart_url' ), 10, 1 );
		add_filter( 'woocommerce_product_add_to_cart_url', array( $this, 'add_to_cart_url' ), 10, 2 );
		add_filter( 'woocommerce_product_supports', array( $this, 'ajax_add_to_cart_supports' ), 10, 3 );
		add_filter( 'woocommerce_is_purchasable', array( $this, 'prevent_purchase_at_grouped_level' ), 10, 2 );

		// Cart
		// Removes the cost of flat fee add-ons from the displayed price in the cart before any plugin had a chance to discount it.
		add_filter( 'woocommerce_cart_item_price', array( $this, 'remove_flat_fees_from_cart_item_price' ), -PHP_INT_MAX, 3 );

		// Re-adds the cost of flat fee add-ons to the displayed price in the cart after all plugins had a chance to discount it.
		add_filter( 'woocommerce_cart_item_price', array( $this, 'add_flat_fees_to_cart_item_price' ), PHP_INT_MAX, 3 );

		// View order.
		add_filter( 'woocommerce_order_item_display_meta_value', array( $this, 'fix_file_uploaded_display' ), 10, 3 );

		// Modify permalink.
		add_filter( 'woocommerce_cart_item_permalink', array( $this, 'get_cart_item_permalink' ), 10, 3 );

		$this->show_num_chars = apply_filters( 'woocommerce_product_addons_show_num_chars', 20 );
	}

	/**
	 * Enqueue add-ons styles.
	 */
	public function styles() {
		if ( is_admin() ) {
			return;
		}

		global $post;

		if (
			is_singular( 'product' ) ||
			class_exists( 'WC_Quick_View' ) ||
			is_cart() ||
			is_checkout() ||
			is_account_page() ||
			is_shop() ||
			( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'product_page' ) ) ||
			( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'appointment_form' ) ) ||
			has_block( 'woocommerce/single-product' )
		) {
			wp_enqueue_style( 'woocommerce-addons-css', WC_PRODUCT_ADDONS_PLUGIN_URL . "/assets/css/frontend/frontend.css", null, WC_PRODUCT_ADDONS_VERSION );
			#wp_enqueue_script( 'jquery-tiptip', WC()->plugin_url() . '/assets/js/jquery-tiptip/jquery.tipTip.min.js', array( 'jquery' ), WC_VERSION, true );

			if ( WC_PAO_Core_Compatibility::wc_current_theme_is_fse_theme() ) {
				wp_register_style( 'wc-pao-blocks-style', WC_PRODUCT_ADDONS_PLUGIN_URL . '/assets/css/frontend/blocktheme.css', false, WC_PRODUCT_ADDONS_VERSION );
				wp_enqueue_style( 'wc-pao-blocks-style' );
			}
		}
	}

	/**
	 * Enqueue add-ons scripts.
	 */
	public function addon_scripts() {
		$suffix       = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
		$dependencies = array( 'jquery', 'accounting', 'wc-add-to-cart-variation', 'jquery-ui-datepicker' );

		/**
		 * Filter to allow adding custom script dependencies here.
		 *
		 * @since  6.8.0
		 * @param  array  $dependencies
		 */
		$dependencies = apply_filters( 'woocommerce_pao_script_dependencies', $dependencies );

		// Register if neccessary.
		if ( ! wp_script_is( 'jquery-tiptip', 'registered' ) ) {
			wp_register_script( 'jquery-tiptip', WC()->plugin_url() . '/assets/js/jquery-tiptip/jquery.tipTip.min.js', array( 'jquery' ), WC_VERSION, true );
        }
		if ( ! wp_script_is( 'accounting', 'registered' ) ) {
			wp_register_script( 'accounting', WC()->plugin_url() . '/assets/js/accounting/accounting' . $suffix . '.js', array( 'jquery' ), '0.4.2', true );
        }
		if ( ! wp_script_is( 'wc-add-to-cart-variation', 'registered' ) ) {
			wp_register_script( 'wc-add-to-cart-variation', WC()->plugin_url() . '/assets/js/frontend/add-to-cart-variation' . $suffix . '.js', array( 'jquery', 'wp-util', 'jquery-blockui' ), '0.4.2', true );
        }

		wp_enqueue_script( 'jquery-tiptip' );

		wp_register_script( 'woocommerce-addons-validation', WC_PRODUCT_ADDONS_PLUGIN_URL . '/assets/js/lib/pao-validation' . $suffix . '.js', $dependencies, WC_PRODUCT_ADDONS_VERSION, true );
		wp_register_script( 'woocommerce-addons', WC_PRODUCT_ADDONS_PLUGIN_URL . '/assets/js/frontend/addons' . $suffix . '.js', array_merge( $dependencies, array( 'woocommerce-addons-validation' ) ), WC_PRODUCT_ADDONS_VERSION, true );
		wp_script_add_data( 'woocommerce-addons', 'strategy', 'defer' );
		wp_enqueue_script( 'woocommerce-addons' );

		$params = array(
			'price_display_suffix'                     => esc_attr( get_option( 'woocommerce_price_display_suffix' ) ),
			'tax_enabled'                              => wc_tax_enabled(),
			'price_include_tax'                        => 'yes' === esc_attr( get_option( 'woocommerce_prices_include_tax' ) ),
			'display_include_tax'                      => ( wc_tax_enabled() && 'incl' === esc_attr( get_option( 'woocommerce_tax_display_shop' ) ) ) ? true : false,
			'ajax_url'                                 => WC()->ajax_url(),
			'i18n_validation_required_select'          => __( 'Please choose an option.', 'woocommerce-appointments' ),
			'i18n_validation_required_input'           => __( 'Please enter some text in this field.', 'woocommerce-appointments' ),
			'i18n_validation_required_number'          => __( 'Please enter a number in this field.', 'woocommerce-appointments' ),
			'i18n_validation_required_file'            => __( 'Please upload a file.', 'woocommerce-appointments' ),
			'i18n_validation_letters_only'             => __( 'Please enter letters only.', 'woocommerce-appointments' ),
			'i18n_validation_numbers_only'             => __( 'Please enter numbers only.', 'woocommerce-appointments' ),
			'i18n_validation_letters_and_numbers_only' => __( 'Please enter letters and numbers only.', 'woocommerce-appointments' ),
			'i18n_validation_email_only'               => __( 'Please enter a valid email address.', 'woocommerce-appointments' ),
			/* translators: %1$s min number of characters */
			'i18n_validation_min_characters'           => sprintf( __( 'Please enter at least %1$s characters.', 'woocommerce-appointments' ), '%c' ),
			/* translators: %1$s max number of characters */
			'i18n_validation_max_characters'           => sprintf( __( 'Please enter up to %1$s characters.', 'woocommerce-appointments' ), '%c' ),
			/* translators: %1$s min number */
			'i18n_validation_min_number'               => sprintf( __( 'Please enter %1$s or more.', 'woocommerce-appointments' ), '%c' ),
			/* translators: %1$s max number */
			'i18n_validation_max_number'               => sprintf( __( 'Please enter %1$s or less.', 'woocommerce-appointments' ), '%c' ),
			/* translators: %1$s decimal separator */
			'i18n_validation_decimal_separator'        => sprintf( __( 'Please enter a price with one monetary decimal point (%1$s) without thousand separators.', 'woocommerce-appointments' ), '%c' ),
			'i18n_sub_total'                           => esc_attr__( 'Subtotal', 'woocommerce-appointments' ),
			/* translators: %s remaining characters */
			'i18n_remaining'                           => esc_attr( sprintf( __( '%s characters remaining', 'woocommerce-appointments' ), '<span></span>' ) ),
			'currency_format_num_decimals'             => absint( get_option( 'woocommerce_price_num_decimals' ) ),
			'currency_format_symbol'                   => get_woocommerce_currency_symbol(),
			'currency_format_decimal_sep'              => wc_get_price_decimal_separator(),
			'currency_format_thousand_sep'             => wc_get_price_thousand_separator(),
			'trim_trailing_zeros'                      => apply_filters( 'woocommerce_price_trim_zeros', false ),
			'is_bookings'                              => class_exists( 'WC_Bookings' ),
			'trim_user_input_characters'               => $this->show_num_chars,
			'quantity_symbol'                          => 'x ',
			'datepicker_class'                         => ! apply_filters( 'woocommerce_pao_disable_datepicker_styles', false ) ? 'wc_pao_datepicker' : '',
			'datepicker_date_format'                   => WC_Product_Addons_Helper::wc_pao_get_js_date_format(),
			'gmt_offset'                               => -1 * WC_Product_Addons_Helper::wc_pao_get_gmt_offset(), // Revert value to match JS.
			'date_input_timezone_reference'            => WC_Product_Addons_Helper::wc_pao_get_date_input_timezone_reference(),
		);

		// If the customer is tax exempt then always display prices excluding tax.
		// When prices are exclusive tax WooCommerce handles this differently (so only change for including tax).
		if ( ! empty( WC()->customer ) && WC()->customer->get_is_vat_exempt() && wc_prices_include_tax() ) {
			$params['display_include_tax'] = false;
		}

		if ( ! function_exists( 'get_woocommerce_price_format' ) ) {
			$currency_pos = get_option( 'woocommerce_currency_pos' );

			switch ( $currency_pos ) {
				case 'left':
					$format = '%1$s%2$s';
					break;
				case 'right':
					$format = '%2$s%1$s';
					break;
				case 'left_space':
					$format = '%1$s&nbsp;%2$s';
					break;
				case 'right_space':
					$format = '%2$s&nbsp;%1$s';
					break;
			}

			$params['currency_format'] = esc_attr( str_replace( array( '%1$s', '%2$s' ), array( '%s', '%v' ), $format ) );
		} else {
			$params['currency_format'] = esc_attr( str_replace( array( '%1$s', '%2$s' ), array( '%s', '%v' ), get_woocommerce_price_format() ) );
		}

		wp_localize_script( 'woocommerce-addons', 'woocommerce_addons_params', apply_filters( 'woocommerce_product_addons_params', $params ) );
		wp_localize_script( 'woocommerce-addons-validation', 'woocommerce_addons_params', apply_filters( 'woocommerce_product_addons_params', $params ) );
	}

	/**
	 * Adds support for WooCommerce Quick View adding a new script.
	 */
	public function quick_view_single_compat() {
		if ( is_singular( 'product' ) && class_exists( 'WC_Quick_View' ) ) {
			$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
			wp_enqueue_script( 'woocommerce-addons-quickview-compat', WC_PRODUCT_ADDONS_PLUGIN_URL . '/assets/js/frontend/quickview' . $suffix . '.js', array( 'jquery' ), WC_PRODUCT_ADDONS_VERSION, true );
		}
	}

	/**
	 * Display add-ons.
	 *
	 * @param int|bool    $post_id Post ID (default: false).
	 * @param string|bool $prefix  Add-on prefix.
	 */
	public function display( $post_id = false, $prefix = false ) {
		global $product;

		if ( ! $post_id ) {
			$post_id = $product->get_id();
		}

		if ( ! $post_id ) {
			$post_id = get_the_ID();
		}

		if ( ! $product && isset( $GLOBALS['product'] ) ) {
			$product = $GLOBALS['product'];
		}

		// We do not currently support grouped or external products.
		if ( 'grouped' === $product->get_type() || 'external' === $product->get_type() ) {
			return;
		}

		// Get product addons from global variable.
		if ( ! isset( $GLOBALS['get_product_addons'][ $post_id ] ) ) {
			$GLOBALS['get_product_addons'][ $post_id ] = WC_Product_Addons_Helper::get_product_addons( $post_id, $prefix );
		}
		$product_addons = $GLOBALS['get_product_addons'][ $post_id ] ? $GLOBALS['get_product_addons'][ $post_id ] : false;

		// Stop here if no addons for the product.
		if ( ! $product_addons ) {
			return;
		}

		$this->addon_scripts();

		if ( is_array( $product_addons ) && count( $product_addons ) > 0 ) {
			echo '<div class="wc-pao-addons-container">';

			do_action( 'woocommerce_product_addons_start', $post_id );

			foreach ( $product_addons as $addon ) {
				if ( ! isset( $addon['field_name'] ) ) {
					continue;
				}

				wc_get_template(
					'addons/addon-start.php',
					array(
						'addons'              => $product_addons,
						'addon'               => $addon,
						'required'            => WC_Product_Addons_Helper::is_addon_required( $addon ),
						'name'                => $addon['name'],
						'description'         => $addon['description'],
						'display_description' => WC_Product_Addons_Helper::should_display_description( $addon ),
						'type'                => $addon['type'],
						'product'             => $product,
					),
					'woocommerce-appointments',
					WC_PRODUCT_ADDONS_PLUGIN_PATH . '/templates/'
				);

				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This is only using the value for display purposes.
				$value = wc_clean( wp_unslash( $_REQUEST[ 'addon-' . sanitize_title( $addon['field_name'] ) ] ?? null ) );

				// This is sanitised in the template files and earlier functions.
				echo WC_Product_Addons_Html_Generator::get_addon_html( $addon, $value, $product_addons ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

				wc_get_template(
					'addons/addon-end.php',
					array(
						'addon' => $addon,
					),
					'woocommerce-appointments',
					WC_PRODUCT_ADDONS_PLUGIN_PATH . '/templates/'
				);
			}

			do_action( 'woocommerce_product_addons_end', $post_id );

			echo '</div>';
		}
	}

	/**
	 * Update totals to include product add-ons.
	 *
	 * @param int $post_id Post ID.
	 */
	public function totals( $post_id ) {
		global $product;

		if ( ! isset( $product ) || $product->get_id() != $post_id ) {
			$the_product = wc_get_product( $post_id );
		} else {
			$the_product = $product;
		}

		if ( is_object( $the_product ) ) {
			$tax_display_mode = get_option( 'woocommerce_tax_display_shop' );
			$display_price    = 'incl' === $tax_display_mode ? wc_get_price_including_tax( $the_product ) : wc_get_price_excluding_tax( $the_product );
		} else {
			$display_price = '';
			$raw_price     = 0;
		}

		if ( 'no' === get_option( 'woocommerce_prices_include_tax' ) ) {
			$tax_mode  = 'excl';
			$raw_price = wc_get_price_excluding_tax( $the_product );
		} else {
			$tax_mode  = 'incl';
			$raw_price = wc_get_price_including_tax( $the_product );
		}

		$product_addons_options   = get_option( 'product_addons_options', array() );
		$show_incomplete_subtotal = '';

		// Backwards compatibility.
		if ( isset( $product_addons_options['show-incomplete-subtotal'] ) ) {
			if ( 'yes' === $product_addons_options['show-incomplete-subtotal'] || 1 === absint( $product_addons_options['show-incomplete-subtotal'] ) ) {
				$show_incomplete_subtotal = 1;
			}
		}

		echo '<div
				id="product-addons-total"
				data-show-incomplete-sub-total="' . esc_attr( $show_incomplete_subtotal ) . '"
				data-show-sub-total="' . ( apply_filters( 'woocommerce_product_addons_show_grand_total', true, $the_product ) ? 1 : 0 ) . '"
				data-type="' . esc_attr( $the_product->get_type() ) . '"
				data-tax-mode="' . esc_attr( $tax_mode ) . '"
				data-tax-display-mode="' . esc_attr( $tax_display_mode ) . '"
				data-price="' . esc_attr( $display_price ) . '"
				data-raw-price="' . esc_attr( $raw_price ) . '"
				data-product-id="' . esc_attr( $post_id ) . '"
			></div>';
	}

	/**
	 * Render placeholder for addons notices
	 *
	 * @param int $post_id Post ID.
	 */
	public function notices( $post_id ) {
		echo '<div class="validation_message woocommerce-info" id="required_addons_validation_message"></div>';
	}

	/**
	 * Checks if the product has any required add-ons.
	 *
	 * Since this is an expensive operation, the result is cached.
	 *
	 * @param int $product_id Product ID.
	 * @return bool
	 */
	protected function check_required_addons( $product_id ) {
		$cache_group   = 'product_check_required_addons';
		$cache_value   = wp_cache_get( $product_id, $cache_group );
		$last_modified = get_the_modified_date( 'U', $product_id );

		if ( false === $cache_value || $last_modified !== $cache_value['last_modified'] ) {
			// No parent add-ons, but yes to global.
			$addons              = WC_Product_Addons_Helper::get_product_addons( $product_id, false, false, true );
			$has_required_addons = false;

			if ( $addons && ! empty( $addons ) ) {
				foreach ( $addons as $addon ) {
					if ( is_array( $addon ) && ! empty( $addon['required'] ) ) {
						$has_required_addons = true;
						break;
					}
				}
			}

			wp_cache_set(
				$product_id,
				array(
					'last_modified' => $last_modified,
					'data'          => $has_required_addons,
				),
				$cache_group
			);
		} else {
			$has_required_addons = (bool) $cache_value['data'];
		}

		return $has_required_addons;
	}

	/**
	 * Add to cart text.
	 *
	 * @since 1.0.0
	 * @version 2.9.0
	 * @param string $text Add to cart text.
	 * @param object $product
	 * @return string
	 */
	public function add_to_cart_text( $text, $product = null ) {
		if ( null === $product ) {
			global $product;
		}

		if ( ! is_a( $product, 'WC_Product' ) ) {
			return $text;
		}

		if ( ! is_single( $product->get_id() ) ) {
			if ( $this->check_required_addons( $product->get_id() ) ) {
				$text = apply_filters( 'addons_add_to_cart_text', __( 'Select options', 'woocommerce-appointments' ) );
			}
		}

		return $text;
	}

	/**
	 * Product has options filter. This tells WooCommerce if a product has required options. This will prevent the user
	 * adding the item to the cart without first selecting them on the product page.
	 *
	 * @param boolean $has_options True if the product has options.
	 * @param object  $product Product object.
	 * @return boolean
	 */
	public function has_options( $has_options, $product ) {
		if ( $product && $this->check_required_addons( $product->get_id() ) ) {
			$has_options = true;
		}
		return $has_options;
	}

	/**
	 * Removes ajax-add-to-cart functionality in WC 2.5 when a product has required add-ons.
	 *
	 * @param  bool       $supports If support a feature.
	 * @param  string     $feature  Feature to support.
	 * @param  WC_Product $product  Product data.
	 * @return bool
	 */
	public function ajax_add_to_cart_supports( $supports, $feature, $product ) {
		if ( 'ajax_add_to_cart' === $feature && $this->check_required_addons( $product->get_id() ) ) {
			$supports = false;
		}

		return $supports;
	}

	/**
	 * Include product add-ons to add to cart URL.
	 *
	 * @since 1.0.0
	 * @version 2.9.0
	 * @param string $url Add to cart URL.
	 * @param object $product
	 * @return string
	 */
	public function add_to_cart_url( $url, $product = null ) {
		if ( null === $product ) {
			global $product;
		}

		if ( ! is_a( $product, 'WC_Product' ) ) {
			return $url;
		}

		if ( ! is_single( $product->get_id() ) && in_array( $product->get_type(), apply_filters( 'woocommerce_product_addons_add_to_cart_product_types', array( 'subscription', 'simple' ) ) ) && ( ! isset( $_GET['wc-api'] ) || 'WC_Quick_View' !== $_GET['wc-api'] ) ) {
			if ( $this->check_required_addons( $product->get_id() ) ) {
				$url = apply_filters( 'addons_add_to_cart_url', get_permalink( $product->get_id() ) );
			}
		}

		return $url;
	}

	/**
	 * Don't let products with required addons be added to cart when viewing grouped products.
	 *
	 * @param  bool       $purchasable If product is purchasable.
	 * @param  WC_Product $product     Product data.
	 * @return bool
	 */
	public function prevent_purchase_at_grouped_level( $purchasable, $product ) {
		$product_id = $product->get_parent_id();

		if ( $product && ! $product->is_type( 'variation' ) && $product_id && is_single( $product_id ) && $this->check_required_addons( $product->get_id() ) ) {
			$purchasable = false;
		}
		return $purchasable;
	}

	/**
	 * Removes the cost of flat fee add-ons from the displayed price in the cart.
	 *
	 * @param string $price_html
	 * @param array  $cart_item
	 * @param string $cart_item_key
	 * @return bool
	 */
	public function remove_flat_fees_from_cart_item_price( $price_html, $cart_item, $cart_item_key ) {

		if ( isset( $cart_item['addons_flat_fees_sum'] ) && ! empty( $cart_item['addons_flat_fees_sum'] ) ) {

			// Composite Products compatibility: remove flat fees from the price offset that Composite Products uses to calculate discounted prices.
			if ( class_exists( 'WC_CP_Addons_Compatibility' ) && class_exists( 'WC_CP_Product_Data' ) && ! is_null( WC_CP()->product_data->get( $cart_item['data'], 'composited_price_offset' ) ) ) {

				$composited_price_offset  = WC_CP()->product_data->get( $cart_item['data'], 'composited_price_offset' );
				$composited_price_offset -= $cart_item['addons_flat_fees_sum'];

				WC_CP()->product_data->set( $cart_item['data'], 'composited_price_offset', $composited_price_offset );

			// Product Bundles compatibility: remove flat fees from the price offset that Product Bundles uses to calculate discounted prices.
			} elseif ( class_exists( 'WC_PB_Addons_Compatibility' ) && class_exists( 'WC_PB_Product_Data' ) && ! is_null( WC_PB()->product_data->get( $cart_item['data'], 'bundled_price_offset' ) ) ) {

				$bundled_price_offset  = WC_PB()->product_data->get( $cart_item['data'], 'bundled_price_offset' );
				$bundled_price_offset -= $cart_item['addons_flat_fees_sum'];

				WC_PB()->product_data->set( $cart_item['data'], 'bundled_price_offset', $bundled_price_offset );

			} else {

				// Get price data.
				$product_price         = $cart_item['data']->get_price( 'edit' );
				$product_regular_price = $cart_item['data']->get_regular_price( 'edit' );
				$product_sale_price    = $cart_item['data']->get_sale_price( 'edit' );

				// Subtract flat fees from product prices and set new prices to the product object.
				$product_price = $product_price - $cart_item['addons_flat_fees_sum'];
				$cart_item['data']->set_price( $product_price );

				if ( is_numeric( $product_regular_price ) ) {
					$product_regular_price = $product_regular_price - $cart_item['addons_flat_fees_sum'];
					$cart_item['data']->set_regular_price( $product_regular_price );
				}

				if ( is_numeric( $product_sale_price ) ) {
					$product_sale_price = $product_sale_price - $cart_item['addons_flat_fees_sum'];
					$cart_item['data']->set_sale_price( $product_sale_price );
				}
			}

			/**
			 * All Products for WooCommerce Subscriptions compatibility.
			 *
			 * If All Products for WooCommerce Subscriptions shouldn't discount add-ons, then remove flat fees from the price offset used to
			 * calculate discounts.
			 */
			if ( class_exists( 'WCS_ATT_Integration_PAO' ) && class_exists( 'WCS_ATT_Product' ) ) {
				if ( ! WCS_ATT_Integration_PAO::discount_addons( $cart_item['data'] ) ) {
					$runtime_meta = WCS_ATT_Product::get_runtime_meta( $cart_item['data'], 'price_offset' );
					if ( '' !== $runtime_meta ) {
						WCS_ATT_Product::set_runtime_meta( $cart_item['data'], 'price_offset', $runtime_meta - $cart_item['addons_flat_fees_sum'] );
					}
				}
			}

			// Generate a new cart item price HTML output based on prices excluding flat fees.
			$price_html = WC()->cart->get_product_price( $cart_item['data'] );
		}

		return $price_html;
	}

	/**
	 * Re-adds the cost of flat fee add-ons to the cart item price.
	 *
	 * @param string $price_html
	 * @param array  $cart_item
	 * @param string $cart_item_key
	 * @return bool
	 */
	public function add_flat_fees_to_cart_item_price( $price_html, $cart_item, $cart_item_key ) {

		if ( isset( $cart_item['addons_flat_fees_sum'] ) && ! empty( $cart_item['addons_flat_fees_sum'] ) ) {

			// Composite Products compatibility: re-add flat fees to the price offset that Composite Products uses to calculate discounted prices.
			if ( class_exists( 'WC_CP_Addons_Compatibility' ) && class_exists( 'WC_CP_Product_Data' ) && ! is_null( WC_CP()->product_data->get( $cart_item['data'], 'composited_price_offset' ) ) ) {

				$composited_price_offset  = WC_CP()->product_data->get( $cart_item['data'], 'composited_price_offset' );
				$composited_price_offset += $cart_item['addons_flat_fees_sum'];

				WC_CP()->product_data->set( $cart_item['data'], 'composited_price_offset', $composited_price_offset );

			// Product Bundles compatibility: re-add flat fees to the price offset that Product Bundles uses to calculate discounted prices.
			} elseif ( class_exists( 'WC_PB_Addons_Compatibility' ) && class_exists( 'WC_PB_Product_Data' ) && ! is_null( WC_PB()->product_data->get( $cart_item['data'], 'bundled_price_offset' ) ) ) {

				$bundled_price_offset  = WC_PB()->product_data->get( $cart_item['data'], 'bundled_price_offset' );
				$bundled_price_offset += $cart_item['addons_flat_fees_sum'];

				WC_PB()->product_data->set( $cart_item['data'], 'bundled_price_offset', $bundled_price_offset );

			} else {

				// Get price data.
				$product_price         = $cart_item['data']->get_price( 'edit' );
				$product_regular_price = $cart_item['data']->get_regular_price( 'edit' );
				$product_sale_price    = $cart_item['data']->get_sale_price( 'edit' );

				// Re-add flat fees to product prices and set new prices to the product object.
				$product_price = $product_price + $cart_item['addons_flat_fees_sum'];
				$cart_item['data']->set_price( $product_price );

				if ( is_numeric( $product_regular_price ) ) {
					$product_regular_price = $product_regular_price + $cart_item['addons_flat_fees_sum'];
					$cart_item['data']->set_regular_price( $product_regular_price );
				}

				if ( is_numeric( $product_sale_price ) ) {
					$product_sale_price = $product_sale_price + $cart_item['addons_flat_fees_sum'];
					$cart_item['data']->set_sale_price( $product_sale_price );
				}
			}

			/**
			 * All Products for WooCommerce Subscriptions compatibility.
			 *
			 * If All Products for WooCommerce Subscriptions shouldn't discount add-ons, then
			 * re-add flat fees to the price offset All Products for WooCommerce Subscriptions uses to calculate discounts, after
			 * all discounts have been calculated.
			 *
			 */
			if ( class_exists( 'WCS_ATT_Integration_PAO' ) && class_exists( 'WCS_ATT_Product' ) ) {
				if ( ! WCS_ATT_Integration_PAO::discount_addons( $cart_item['data'] ) ) {
					$runtime_meta = WCS_ATT_Product::get_runtime_meta( $cart_item['data'], 'price_offset' );
					if ( '' !== $runtime_meta ) {
						WCS_ATT_Product::set_runtime_meta( $cart_item['data'], 'price_offset', $runtime_meta + $cart_item['addons_flat_fees_sum'] );
					}
				}
			}
		}

		return $price_html;
	}

	/**
	 * Fix the display of uploaded files.
	 *
	 * @param  string $meta_value Meta value.
	 * @return string
	 */
	public function fix_file_uploaded_display( $meta_value, $meta = null, $item = null ) {
 		global $wp;

 		// If the value is a string, is a URL to an uploaded file, and we're not in the WC API, reformat this string as an anchor tag.
 		if ( is_string( $meta_value ) && ! isset( $wp->query_vars['wc-api'] ) && false !== strpos( $meta_value, '/product_addons_uploads/' ) ) {
 			$file_url   = $meta_value;
 			$meta_value = basename( $meta_value );
 			$meta_value = '<a href="' . esc_url( $file_url ) . '">' . esc_html( $meta_value ) . '</a>';
 		}

 		if ( is_null( $meta ) || is_null( $item ) ) {
 			return $meta_value;
 		}

		// Before fixing newlines issue for textarea, ensure we're dealing with textarea type
		$product_fields = is_callable( array( $item, 'get_product_id' ) ) ?
			get_post_meta( $item->get_product_id(), '_product_addons', true ) :
			[];

		if ( ! empty( $product_fields ) && is_callable( array( $meta, 'get_data' ) ) ) {
			$meta_data      = $meta->get_data();
			$product_fields = array_filter(
				$product_fields,
				function ( $field ) use ( $meta_data ) {
					return isset( $field['name'] ) && isset( $field['type'] )
					&& $field['name'] == $meta_data['key'] && 'custom_textarea' == $field['type'];
				}
			);

			if ( ! empty( $product_fields ) ) {
				// Overwrite display value since core has already removed newlines
				$meta_value = $meta->value;
			}
		}

		return $meta_value;
	}

	/**
	 * Fix product addons position on variable products - show them after a single variation description
	 * or out of stock message.
	 */
	public function reposition_display_for_variable_product() {
		remove_action( 'woocommerce_before_add_to_cart_button', array( $this, 'display' ), 10 );
		add_action( 'woocommerce_single_variation', array( $this, 'display' ), 15 );
	}

	/**
	 * Retrieves the permalink for a product with add-ons.
	 *
	 * @param  null|string $permalink The content.
	 * @param  null|array  $cart_item The cart item.
	 * @param  null|string $cart_item_key The cart item key.
	 * @return null|string
	 */
	public function get_cart_item_permalink( ?string $permalink, ?array $cart_item, ?string $cart_item_key ): ?string {
		return WC_Product_Addons_Helper::get_permalink( $permalink, $cart_item, $cart_item_key );
	}

	/**
	 * Parses cart-item addons into a format usable for injecting into request parameters
	 *
	 * @since 7.2.0
	 * @param array $product_addons   Product addons.
	 * @param array $cart_item_addons Cart item addons.
	 *
	 * @return array
	 */
	public function parse_cart_addons( array $product_addons, array $cart_item_addons ): array {
		// phpcs:disable WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase
		global $Product_Addon_Cart;

		// We want to reuse code, the code expects a `key` field.
		array_walk(
			$cart_item_addons,
			static function ( &$addon ) {
				$addon['key'] = $addon['name'];
			}
		);

		$parsed_addons = array();
		foreach ( $product_addons as $product_addon ) {
			$value_type   = '';
			$post_process = null;

			switch ( $product_addon['type'] ) {
				case 'checkbox':
					$value_type = 'checkbox';
					break;
				case 'multiple_choice':
					switch ( $product_addon['display'] ) {
						case 'radiobutton':
							$value_type   = 'radiobutton';
							$post_process = static function ( $value ) {
								return reset( $value );
							};
							break;
						case 'images':
						case 'select':
							$value_type   = 'select';
							$post_process = static function ( $value ) use ( $product_addon ) {
								$loop = 0;

								foreach ( $product_addon['options'] as $option ) {
									++$loop;

									if ( sanitize_title( $option['label'] ) === $value ) {
										$value .= '-' . $loop;
										break;
									}
								}
								return $value;
							};
							break;
					}
					break;
				case 'select':
					$value_type   = 'select';
					$post_process = static function ( $value ) use ( $product_addon ) {
						$loop = 0;

						foreach ( $product_addon['options'] as $option ) {
							++$loop;

							if ( sanitize_title( $option['label'] ) === $value ) {
								$value .= '-' . $loop;
								break;
							}
						}
						return $value;
					};
					break;
				case 'custom_price':
					$value_type = 'custom_price';
					break;
				case 'custom_text':
				case 'custom_textarea':
				case 'input_multiplier':
					$value_type = 'input_multiplier';
					break;
				case 'file_upload':
					$value_type = 'file_upload';
					break;
				case 'datepicker':
					$value_type   = 'datepicker';
					$post_process = static function ( $value ) use ( $Product_Addon_Cart, $cart_item_addons, $product_addon ) {
						foreach ( $cart_item_addons as $meta ) {
							if ( ! $Product_Addon_Cart->is_matching_addon( $product_addon, $meta ) ) {
								continue;
							}

							$timestamp = $meta['timestamp'];
							$offset    = $meta['offset'];
							$value     = array(
								'display'   => date_i18n( get_option( 'date_format' ), WC_Product_Addons_Helper::wc_pao_convert_timestamp_to_gmt_offset( $timestamp, -1 * (float) $offset ) ),
								'timestamp' => $timestamp,
								'offset'    => $offset,
							);
							break;
						}
						return $value;
					};
					break;
			}
			$value = $Product_Addon_Cart->get_addon_meta_value( $cart_item_addons, $product_addon, $value_type );

			// Skip add-ons that haven't been configured.
			if ( empty( $value ) ) {
				continue;
			}

			if ( null !== $post_process ) {
				$value = $post_process( $value );
			}

			$parsed_addons[ 'addon-' . sanitize_title( $product_addon['field_name'] ) ] = $value;
		}

		return $parsed_addons;
		// phpcs:enable WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase
	}

	/**
	 * Retrieves the addons for a product from the cart item.
	 *
	 * @since 7.2.0
	 *
	 * @param WC_Product $product   The product.
	 * @param array      $cart_item The cart item.
	 *
	 * @return array
	 */
	protected function get_cart_addons( WC_Product $product, array $cart_item ): array {
		if ( empty( $cart_item['addons'] ) ) {
			return array();
		}

		$product_addons = WC_Product_Addons_Helper::get_product_addons( $product->get_id() );

		if ( empty( $product_addons ) ) {
			return array();
		}

		return $this->parse_cart_addons( $product_addons, $cart_item['addons'] );
	}

	/**
	 * Fetches the cart item based on the pao_key query parameter.
	 *
	 * @return array|null
	 */
	protected function get_cart_item_from_query(): ?array {
		global $product;

		if ( ! is_a( $product, 'WC_Product' ) ) {
			return null;
		}

		// phpcs:ignore WordPress.Security.NonceVerification  -- Skipping nonce verification since this doesn't result in any specific action.
		if ( ! isset( $_GET['pao_key'], $_GET['pao_edit'] ) || '1' !== $_GET['pao_edit'] ) {
			return null;
		}

		// phpcs:ignore WordPress.Security.NonceVerification
		$cart_item_key = wc_clean( wp_unslash( $_GET['pao_key'] ) );

		$cart_contents = wc()->cart->get_cart_contents();

		return $cart_contents[ $cart_item_key ] ?? null;
	}

	/**
	 * Retrieves the add-on data from a cart item and injects it into the $_REQUEST global.
	 *
	 * @since 7.2.0
	 * @return void
	 */
	public function inject_addons(): void {
		global $product;

		$cart_item = $this->get_cart_item_from_query();

		if ( ! $cart_item || empty( $cart_item['product_id'] ) || $cart_item['product_id'] !== $product->get_id() ) {
			return;
		}

		$parsed_addons = $this->get_cart_addons( $product, $cart_item );

		/**
		 * Filters the parsed addons before they are injected into the request.
		 *
		 * @since 7.2.0
		 *
		 * @param array       $parsed_addons The parsed addons.
		 * @param WC_Product  $product       The product.
		 * @param array       $cart_item     The cart item.
		 */
		$parsed_addons = apply_filters( 'woocommerce_product_addons_parse_cart_addons', $parsed_addons, $product, $cart_item );

		foreach ( $parsed_addons as $key => $value ) {
			$_REQUEST[ $key ] = $value;
		}
	}
}
