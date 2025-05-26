<?php
/**
 * WC_PAO_Compatibility class
 *
 * @package WooCommerce Product Add-Ons
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 3rd-party Extensions Compatibility.
 *
 * @class    WC_PAO_Compatibility
 * @version  7.5.0
 */
class WC_PAO_Compatibility {

	/**
	 * Array of min required plugin versions.
	 *
	 * @var array
	 */
	private $required = array();

	/**
	 * Modules to load.
	 *
	 * @var array
	 */
	private $modules = array();

	/**
	 * The single instance of the class.
	 *
	 * @var WC_PAO_Compatibility
	 *
	 * @since 6.0.0
	 */
	protected static $_instance = null;

	/**
	 * Main WC_PAO_Compatibility instance.
	 *
	 * Ensures only one instance of WC_PAO_Compatibility is loaded or can be loaded.
	 *
	 * @static
	 * @return WC_PAO_Compatibility
	 * @since  6.0.0
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Cloning is forbidden.
	 *
	 * @since 6.0.0
	 */
	public function __clone() {
		_doing_it_wrong( __FUNCTION__, __( 'Foul!', 'woocommerce-appointments' ), '6.0.0' );
	}

	/**
	 * Unserializing instances of this class is forbidden.
	 *
	 * @since 6.0.0
	 */
	public function __wakeup() {
		_doing_it_wrong( __FUNCTION__, __( 'Foul!', 'woocommerce-appointments' ), '6.0.0' );
	}

	/**
	 * Constructor.
	 */
	public function __construct() {

		$this->required = array(
			'pb'     => '8.1.0',
			'cp'     => '10.1.0',
			'blocks' => '7.2.0',
		);

		// Initialize.
		$this->load_modules();
	}

	/**
	 * Initialize.
	 *
	 * @since  6.0.0
	 *
	 * @return void
	 */
	protected function load_modules() {
		if ( is_admin() ) {
			// Check plugin min versions.
			add_action( 'admin_init', array( $this, 'check_required_versions' ) );
		}

		// Load modules.
		add_action( 'plugins_loaded', array( $this, 'module_includes' ), 100 );
		add_action( 'before_woocommerce_init', array( $this, 'deferred_module_includes' ) );
	}

	/**
	 * Core compatibility functions.
	 *
	 * @since  6.1.3
	 * @return void
	 */
	public static function core_includes() {
		require_once WC_PRODUCT_ADDONS_PLUGIN_PATH . '/includes/compatibility/core/class-wc-product-addons-core-compatibility.php';
	}

	/**
	 * Load compatibility classes.
	 *
	 * @return void
	 */
	public function module_includes() {
		$module_paths = array();

		// WooCommerce Cart/Checkout Blocks support.
		if ( class_exists( 'Automattic\WooCommerce\Blocks\Package' ) && version_compare( \Automattic\WooCommerce\Blocks\Package::get_version(), $this->required['blocks'] ) >= 0 ) {
			$module_paths['blocks'] = WC_PRODUCT_ADDONS_PLUGIN_PATH . '/includes/compatibility/modules/class-wc-product-addons-blocks-compatibility.php';
		}

		// WooPayments compatibility.
		if ( class_exists( 'WC_Payments' ) ) {
			$module_paths['wcpay'] = WC_PRODUCT_ADDONS_PLUGIN_PATH . '/includes/compatibility/modules/class-wc-product-addons-wc-payments-compatibility.php';
		}

		// Stripe compatibility.
		if ( class_exists( 'WC_Stripe' ) && defined( 'WC_STRIPE_VERSION' ) ) {
			$module_paths['stripe'] = WC_PRODUCT_ADDONS_PLUGIN_PATH . '/includes/compatibility/modules/class-wc-product-addons-stripe-compatibility.php';
		}

		// PayPal compatibility.
		if ( class_exists( '\WooCommerce\PayPalCommerce\PluginModule' ) ) {
			$module_paths['paypal'] = WC_PRODUCT_ADDONS_PLUGIN_PATH . '/includes/compatibility/modules/class-wc-product-addons-paypal-compatibility.php';
		}

		// Name Your Price compatibility.
		if ( class_exists( 'WC_Name_Your_Price' ) ) {
			$module_paths['nyp'] = WC_PRODUCT_ADDONS_PLUGIN_PATH . '/includes/compatibility/modules/class-wc-product-addons-nyp-compatibility.php';
		}

		/**
		 * 'woocommerce_product_addons_compatibility_modules' filter.
		 *
		 * Use this to filter the required compatibility modules.
		 *
		 * @since  6.4.0
		 * @param  array $module_paths
		 */
		$this->modules = apply_filters( 'woocommerce_product_addons_compatibility_modules', $module_paths );

		foreach ( $this->modules as $path ) {
			require_once $path;
		}
	}

	/**
	 * Load time-sensitive compatibility classes.
	 *
	 * Runs on 'before_woocommerce_init' which runs on init with priority 0. This is to ensure that i10n needs of the FeaturesUtil class are met.
	 *
	 * @since 7.2.0
	 *
	 * @return void
	 */
	public static function deferred_module_includes() {
		$module_paths = array();

		// Woo Privacy Controller.
		if ( is_admin() ) {
			$module_paths['privacy'] = WC_PRODUCT_ADDONS_PLUGIN_PATH . '/includes/admin/class-wc-product-addons-privacy.php';
		}

		foreach ( $module_paths as $path ) {
			require_once $path;
		}
	}

	/**
	 * Get min module version.
	 *
	 * @since  6.0.0
	 * @return bool
	 */
	public function get_required_module_version( $module ) {
		return isset( $this->required[ $module ] ) ? $this->required[ $module ] : null;
	}

	/**
	 * Checks minimum required versions of compatible/integrated extensions.
	 */
	public function check_required_versions() {

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		// PB version check.
		if ( class_exists( 'WC_Bundles' ) && function_exists( 'WC_PB' ) ) {
			$required_version = $this->required[ 'pb' ];
			if ( version_compare( WC_PB()->version, $required_version ) < 0 ) {
				$extension      = __( 'Product Bundles', 'woocommerce-appointments' );
				$extension_full = __( 'WooCommerce Product Bundles', 'woocommerce-appointments' );
				$extension_url  = 'https://woocommerce.com/products/product-bundles/';
				/* translators: %1$s: Extension, %2$s: Extension URL, %3$s: Extension full name, %4$s: Required version. */
				$notice = sprintf( __( 'The installed version of <strong>%1$s</strong> is not supported by <strong>Product Add-ons</strong>. Please update <a href="%2$s" target="_blank">%3$s</a> to version <strong>%4$s</strong> or higher.', 'woocommerce-appointments' ), $extension, $extension_url, $extension_full, $required_version );
				WC_PAO_Admin_Notices::add_dismissible_notice(
					$notice,
					array(
						'dismiss_class' => 'pb_lt_' . $required_version,
						'type'          => 'warning',
					)
				);
			}
		}

		// CI version check.
		if ( class_exists( 'WC_Composite_Products' ) && function_exists( 'WC_CP' ) ) {
			$required_version = $this->required['cp'];
			if ( version_compare( WC_CP()->version, $required_version ) < 0 ) {
				$extension      = __( 'Composite Products', 'woocommerce-appointments' );
				$extension_full = __( 'WooCommerce Composite Products', 'woocommerce-appointments' );
				$extension_url  = 'https://woocommerce.com/products/composite-products/';
				$notice         = sprintf( __( 'The installed version of <strong>%1$s</strong> is not supported by <strong>Product Add-ons</strong>. Please update <a href="%2$s" target="_blank">%3$s</a> to version <strong>%4$s</strong> or higher.', 'woocommerce-appointments' ), $extension, $extension_url, $extension_full, $required_version );
				WC_PAO_Admin_Notices::add_dismissible_notice(
					$notice,
					array(
						'dismiss_class' => 'cp_lt_' . $required_version,
						'type'          => 'warning',
					)
				);
			}
		}

		// Blocks feature plugin check.
		if ( defined( 'WC_BLOCKS_IS_FEATURE_PLUGIN' ) ) {
			$required_version = $this->required[ 'blocks' ];
			if ( class_exists( 'Automattic\WooCommerce\Blocks\Package' ) && version_compare( \Automattic\WooCommerce\Blocks\Package::get_version(), $this->required[ 'blocks' ] ) < 0 ) {

				$plugin     = __( 'WooCommerce Blocks', 'woocommerce-appointments' );
				$plugin_url = 'https://woocommerce.com/products/woocommerce-gutenberg-products-block/';
				/* translators: %1$s: Plugin name, %2$s: Plugin URL, %3$s: Plugin name full, %4$s: Plugin version */
				$notice = sprintf( __( 'The installed version of <strong>%1$s</strong> does not support <strong>Product Add-ons</strong>. Please update <a href="%2$s" target="_blank">%3$s</a> to version <strong>%4$s</strong> or higher.', 'woocommerce-appointments' ), $plugin, $plugin_url, $plugin, $required_version );

				WC_PAO_Admin_Notices::add_dismissible_notice(
					$notice,
					array(
						'dismiss_class' => 'blocks_lt_' . $required_version,
						'type'          => 'warning',
					)
				);
			}
		}
	}
}

WC_PAO_Compatibility::core_includes();
