<?php
/**
 * WC_Product_Addons_Admin_Order class
 *
 * @package  WooCommerce Product Add-Ons
 * @since    6.9.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Product AddOns edit-order functions and filters.
 *
 * @class    WC_Product_Addons_Admin_Order
 * @version  7.2.1
 */
class WC_Product_Addons_Admin_Order {

	/**
	 * Order object to use in 'display_edit_button'.
	 *
	 * @var WC_Order
	 */
	protected static $order;

	/**
	 * Setup Admin class.
	 */
	public static function init() {
		// Save order object to use in 'display_edit_button'.
		add_action( 'woocommerce_admin_order_item_headers', array( __CLASS__, 'set_order' ) );

		// Display "Configure/Edit" button next to configurable add-ons container items in the edit-order screen.
		add_action( 'woocommerce_after_order_itemmeta', array( __CLASS__, 'display_edit_button' ), 10, 3 );

		// Add JS template.
		add_action( 'admin_footer', array( __CLASS__, 'add_js_template' ) );
	}

	/**
	 * Save order object to use in 'display_edit_button'.
	 *
	 * Although the order object can be retrieved via 'WC_Order_Item::get_order', we've seen a significant performance hit when using that method.
	 *
	 * @param  WC_Order $order Order object.
	 */
	public static function set_order( $order ) {
		self::$order = $order;
	}

	/**
	 * Display "Configure/Edit" button next to configurable addons in the edit-order screen.
	 *
	 * @param  int           $item_id Item ID.
	 * @param  WC_Order_Item $item    Order item object.
	 * @param  WC_Product    $product Product object.
	 * @return void
	 */
	public static function display_edit_button( $item_id, $item, $product ) {
		if ( ! ( self::$order && self::$order->is_editable() && 'line_item' === $item->get_type() ) ) {
			return;
		}

		if ( ! is_a( $product, 'WC_Product' ) ) {
			return;
		}

		if ( ! in_array( $product->get_type(), array( 'simple', 'variation' ), true ) ) {
			return;
		}

		$product_addons = WC_Product_Addons_Helper::get_product_addons( $product->get_id() );

		if ( empty( $product_addons ) ) {
			return;
		}

		// If item has addons, but the `_pao_total` meta doesn't exist, then the
		// order is a legacy order and doesn't have the data needed to allow editing.
		if ( $item->meta_exists( '_pao_ids' ) && ! $item->meta_exists( '_pao_total' ) ) {
			return;
		}

		/**
		 * Filter to display the "Configure/Edit" button next to configurable items in the edit-order screen.
		 *
		 * @since 7.1.0
		 *
		 * @param bool          $display_editing_in_order_button Whether to display the "Configure/Edit" button.
		 * @param WC_Order_Item $item                          Order item object.
		 * @param WC_Product    $product                       Product object.
		 * @param array         $product_addons                Product addons.
		 */
		if ( false === apply_filters( 'woocommerce_product_addons_display_editing_in_order_button', true, $item, $product, $product_addons ) ) {
			return;
		}

		$configured_addons = $item->get_meta( '_pao_ids', true );
		?>
		<div class="configure_order_item">
			<button class="<?php echo empty( $configured_addons ) ? 'configure_addons' : 'edit_addons'; ?> button">
				<?php
				if ( empty( $configured_addons ) ) {
					esc_html_e( 'Configure', 'woocommerce-appointments' );
				} else {
					esc_html_e( 'Edit', 'woocommerce-appointments' );
				}
				?>
			</button>
		</div>
		<?php
	}

	/**
	 * JS template of modal for configuring/editing add-ons.
	 */
	public static function add_js_template() {

		if ( wp_script_is( 'woocommerce_pao-admin-order-panel' ) ) {
			?>
			<script type="text/template" id="tmpl-wc-modal-edit-addon">
				<div class="wc-backbone-modal">
					<div class="wc-backbone-modal-content">
						<section class="wc-backbone-modal-main" role="main">
							<header class="wc-backbone-modal-header">
								<h1>{{{ data.action }}}</h1>
								<button class="modal-close modal-close-link dashicons dashicons-no-alt">
									<span class="screen-reader-text">Close modal panel</span>
								</button>
							</header>
							<article>
								<form action="" method="post" class="wc-pao-addons-container">
								</form>
							</article>
							<footer>
								<div class="inner">
									<button id="btn-ok" class="button button-primary button-large"><?php esc_html_e( 'Done', 'woocommerce-appointments' ); ?></button>
								</div>
							</footer>
						</section>
					</div>
				</div>
				<div class="wc-backbone-modal-backdrop modal-close"></div>
			</script>
			<?php
		}
	}
}

WC_Product_Addons_Admin_Order::init();
