<?php
/**
 * Plugin Name: WooCommerce Product Add-Ons
 * Plugin URI: https://woocommerce.com/products/product-add-ons/
 * Description: Add extra options to products which your customers can select from, when adding to the cart, with an optional fee for each extra option. Add-ons can be checkboxes, a select box, or custom text input.
 * Version: 7.8.1
 * Author: Woo
 * Author URI: https://woocommerce.com
 *
 * Requires PHP: 7.4
 *
 * Requires at least: 6.2
 * Tested up to: 6.8
 *
 * WC requires at least: 8.2
 * WC tested up to: 9.8
 *
 * Requires Plugins: woocommerce
 *
 * Text Domain: woocommerce-product-addons
 * Domain Path: /languages/
 * Copyright: © 2025 WooCommerce.
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package woocommerce-product-addons
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Files.FileName

// Start here when no conflict detected.
// PHP version check.
if ( ! function_exists( 'phpversion' ) || version_compare( phpversion(), '7.5.0', '<' ) ) {
	$notice = sprintf(
		/* translators: %1$s: Version %, %2$s: Update PHP doc URL */
		__(
			'Product Add-Ons require at least PHP <strong>%1$s</strong>. Learn <a href="%2$s">how to update PHP</a>.',
			'woocommerce-appointments'
		),
		'7.4.0',
		'https://woocommerce.com/document/how-to-update-your-php-version/'
	);
	if ( ! class_exists( 'WC_PAO_Admin_Notices' ) ) {
		require_once __DIR__ . '/includes/admin/class-wc-product-addons-admin-notices.php';
	}
	WC_PAO_Admin_Notices::add_notice( $notice, 'error' );
}

if ( ! function_exists( 'WC' ) || version_compare( WC()->version, '8.2.0' ) < 0 ) {
	/* translators: %1$s: Version %, %2$s: Update WC doc URL */
	$notice = __( 'Product Add-Ons require at least WooCommerce version <strong>%1$s</strong>. %2$s', 'woocommerce-appointments' );
	if ( ! function_exists( 'WC' ) ) {
		$notice = sprintf( $notice, '8.2.0', __( 'Please install and activate WooCommerce.', 'woocommerce-appointments' ) );
	} else {
		$notice = sprintf( $notice, '8.2.0', __( 'Please update WooCommerce.', 'woocommerce-appointments' ) );
	}
	if ( ! class_exists( 'WC_PAO_Admin_Notices' ) ) {
		require_once __DIR__ . '/includes/admin/class-wc-product-addons-admin-notices.php';
	}
	WC_PAO_Admin_Notices::add_notice( $notice, 'error' );

	return false;
}

if ( is_woocommerce_active() && ! class_exists( 'WC_Product_Addons' ) ) :

	define( 'WC_PRODUCT_ADDONS_VERSION', '7.8.1' );
	define( 'WC_PRODUCT_ADDONS_MAIN_FILE', __FILE__ );
	define( 'WC_PRODUCT_ADDONS_PLUGIN_URL', WC_APPOINTMENTS_PLUGIN_URL . '/includes/integrations/woocommerce-product-addons' );
	define( 'WC_PRODUCT_ADDONS_PLUGIN_PATH', WC_APPOINTMENTS_ABSPATH . 'includes/integrations/woocommerce-product-addons' );

	/**
	 * Main class.
	 */
	class WC_Product_Addons {
		/**
		 * Groups controller instance (legacy).
		 *
		 * @var WC_Product_Add_Ons_Groups_Controller
		 */
		protected $groups_controller;

		/**
		 * The single instance of the class.
		 *
		 * @var WC_Product_Addons
		 */
		protected static $_instance = null;

		/**
		 * Main WC_Product_Addons instance. Ensures only one instance is loaded or can be loaded - @see 'WC_PAO()'.
		 *
		 * @static
		 * @return  WC_Product_Addons
		 */
		public static function instance() {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self();
			}
			return self::$_instance;
		}

		/**
		 * Constructor.
		 */
		public function __construct() {
			$this->init();
			add_action( 'init', array( $this, 'init_post_types' ), 20 );
			add_action( 'init', array( 'WC_Product_Addons_Install', 'init' ) );
			add_action( 'rest_api_init', array( $this, 'rest_api_init' ), 0 );

			// Declare HPOS compatibility.
			add_action( 'before_woocommerce_init', array( $this, 'declare_hpos_compatibility' ) );

			// Declare Blocks compatibility.
			add_action( 'before_woocommerce_init', array( $this, 'declare_blocks_compatibility' ) );
		}

		/**
		 * Initializes plugin classes.
		 *
		 * @version 2.9.0
		 */
		public function init() {
			require_once __DIR__ . '/includes/class-wc-product-addons-helper.php';
			require_once __DIR__ . '/includes/class-wc-product-addons-html-generator.php';

			// Pre 3.0 conversion helper to be remove in future.
			require_once __DIR__ . '/includes/updates/class-wc-product-addons-3-0-conversion-helper.php';

			require_once __DIR__ . '/includes/class-wc-product-addons-install.php';

			// Core (models).
			require_once __DIR__ . '/includes/groups/class-wc-product-addons-group-validator.php';
			require_once __DIR__ . '/includes/groups/class-wc-product-addons-global-group.php';
			require_once __DIR__ . '/includes/groups/class-wc-product-addons-product-group.php';
			require_once __DIR__ . '/includes/groups/class-wc-product-addons-groups.php';

			// Admin
			if ( is_admin() ) {
				require_once __DIR__ . '/includes/admin/class-wc-product-addons-admin.php';
				require_once __DIR__ . '/includes/admin/class-wc-product-addons-admin-list-table.php';

				$GLOBALS['Product_Addon_Admin'] = new WC_Product_Addons_Admin();
			}

			// Handle WooCommerce 3.0 compatibility.
			require_once __DIR__ . '/includes/class-wc-product-addons-display.php';
			require_once __DIR__ . '/includes/class-wc-product-addons-cart.php';
			require_once __DIR__ . '/includes/class-wc-product-addons-ajax.php';
			require_once __DIR__ . '/includes/class-wc-product-addons-notices.php';
			require_once __DIR__ . '/includes/class-wc-product-addons-compatibility.php';

			WC_PAO_Compatibility::instance();

			$GLOBALS['Product_Addon_Display'] = new WC_Product_Addons_Display();
			$GLOBALS['Product_Addon_Cart']    = new WC_Product_Addons_Cart();
			new WC_Product_Addons_Cart_Ajax();
		}

		/**
		 * Init post types used for addons.
		 */
		public function init_post_types() {
			register_post_type(
				'global_product_addon',
				array(
					'public'              => false,
					'show_ui'             => false,
					'capability_type'     => 'product',
					'map_meta_cap'        => true,
					'publicly_queryable'  => false,
					'exclude_from_search' => true,
					'hierarchical'        => false,
					'rewrite'             => false,
					'query_var'           => false,
					'supports'            => array( 'title' ),
					'show_in_nav_menus'   => false,
				)
			);

			register_taxonomy_for_object_type( 'product_cat', 'global_product_addon' );
		}

		/**
		 * Initialize the REST API
		 *
		 * @since 2.9.0
		 * @param WP_Rest_Server $wp_rest_server Rest server class.
		 */
		public function rest_api_init( $wp_rest_server ) {
			// v1 controller.
			require_once __DIR__ . '/includes/api/class-wc-product-add-ons-groups-controller.php';
			( new WC_Product_Add_Ons_Groups_Controller() )->register_routes();

			// v2 API: common code.
			require_once __DIR__ . '/includes/api/v2/class-wc-product-addons-api-v2-abstract-group.php';
			require_once __DIR__ . '/includes/api/v2/class-wc-product-addons-api-v2-global-group.php';
			require_once __DIR__ . '/includes/api/v2/class-wc-product-addons-api-v2-product-group.php';
			require_once __DIR__ . '/includes/api/v2/class-wc-product-addons-api-v2-validation.php';

			// v2 API: Global Add-Ons.
			require_once __DIR__ . '/includes/api/v2/class-wc-product-addons-api-v2-controller.php';
			$controller_v2 = new WC_Product_Addons_Api_V2_Controller(
				new WC_Product_Addons_Api_V2_Validation()
			);
			$controller_v2->register_routes();

			// v2 API: WC Products endpoint modification.
			require_once __DIR__ . '/includes/api/v2/class-wc-product-addons-api-v2-product-rest-api.php';
		}

		/**
		 * Declare HPOS( Custom Order tables) compatibility.
		 *
		 * @since 5.0.1
		 */
		public function declare_hpos_compatibility() {
			if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
				\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			}
		}

		/**
		 * Declare cart/checkout Blocks compatibility.
		 *
		 * @since 6.5.0
		 */
		public function declare_blocks_compatibility() {
			if ( ! class_exists( 'Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
				return;
			}

			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		}

		/**
		 * Get PAO screen ids.
		 */
		public function get_screen_ids() {
			$screens   = array();
			$screens[] = 'product_page_addons';

			/*
			 * 'woocommerce_pao_screen_ids' filter.
			 *
			 * @param array $screens
			 */
			return (array) apply_filters( 'woocommerce_pao_screen_ids', $screens );
		}

		/**
		 * Checks if the current admin screen belongs to extension.
		 *
		 * @param   array  $extra_screens_to_check (Optional)
		 * @return  bool
		 */
		public function is_current_screen( $extra_screens_to_check = array() ) {
			global $current_screen;

			$screen_id = $current_screen ? $current_screen->id : '';

			if ( in_array( $screen_id, $this->get_screen_ids(), true ) ) {
				return true;
			}

			if ( ! empty( $extra_screens_to_check ) && in_array( $screen_id, $extra_screens_to_check ) ) {
				return true;
			}

			return false;
		}

		/**
		 * Get formatted screen id.
		 *
		 *
		 * @param  string $key
		 * @return string
		 */
		public function get_formatted_screen_id( $screen_id ) {
			if ( version_compare( WC()->version, '7.3.0' ) < 0 ) {
				$prefix = sanitize_title( __( 'WooCommerce', 'woocommerce' ) );
			} else {
				$prefix = 'woocommerce';
			}

			if ( 0 === strpos( $screen_id, 'woocommerce_' ) ) {
				$screen_id = str_replace( 'woocommerce_', $prefix . '_', $screen_id );
			}

			return $screen_id;
		}

		/**
		 * Plugin version getter.
		 *
		 * @since  6.9.0
		 *
		 * @param  boolean  $base
		 * @param  string   $version
		 * @return string
		 */
		public function plugin_version( $base = false, $version = '' ) {

			$version = $version ? $version : WC_PRODUCT_ADDONS_VERSION;

			if ( $base ) {
				$version_parts = explode( '-', $version );
				$version       = count( $version_parts ) > 1 ? $version_parts[ 0 ] : $version;
			}

			return $version;
		}

	}

	/**
	 * Returns the main instance of WC_Product_Addons to prevent the need to use globals.
	 *
	 * @return  WC_Product_Addons
	 */
	function WC_PAO() {
		return WC_Product_Addons::instance();
	}

	WC_PAO();

endif;
