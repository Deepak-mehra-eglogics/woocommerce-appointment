<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * WC_Appointments_Shortcodes class.
 *
 * @class   WC_Appointments_Shortcodes
 * @version 4.5.15
 */
class WC_Appointments_Shortcodes {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$shortcodes = [
			'appointment_form' => __CLASS__ . '::appointment_form',
		];

		foreach ( $shortcodes as $shortcode => $function ) {
			add_shortcode( apply_filters( "{$shortcode}_shortcode_tag", $shortcode ), $function );
		}
	}

	/**
	 * @param array $atts
	 * @return string
	 */
	public static function appointment_form( $atts = [] ) {
		if ( ! isset( $atts['id'] ) && ! isset( $atts['sku'] ) ) {
			if ( is_singular() && 'product' === get_post_type() ) {
				$atts       = is_array( $atts ) ? $atts : [];
				$atts['id'] = get_the_ID();
			} else {
				return '';
			}
		}

		if ( empty( $atts ) ) {
			return '';
		}

		// Attributes.
		$atts = shortcode_atts(
			[
				'id'                      => '',
				'sku'                     => '',
				'show_title'              => 1,
				'show_rating'             => 1,
				'show_price'              => 1,
				'show_excerpt'            => 1,
				'show_meta'               => 1,
				'show_sharing'            => 1,
				'availability_autoselect' => null,
				'customer_timezones'      => null,
			],
			$atts
		);

		// Query arguments.
		$args = [
			'posts_per_page'      => 1,
			'post_type'           => 'product',
			'post_status'         => 'publish',
			'ignore_sticky_posts' => 1,
			'no_found_rows'       => 1,
		];

		// SKU.
		if ( isset( $atts['sku'] ) && '' !== $atts['sku'] ) {
			$args['meta_query'][] = [
				'key'     => '_sku',
				'value'   => sanitize_text_field( $atts['sku'] ),
				'compare' => '=',
			];

			$args['post_type'] = [ 'product', 'product_variation' ];
		}

		// ID.
		if ( isset( $atts['id'] ) ) {
			$args['p'] = absint( $atts['id'] );
		}

		$single_product = new WP_Query( $args );

		// Stop here if there is no product.
		if ( ! $single_product->have_posts() ) {
			return '';
		}

		ob_start();

		global $wp_query;

		// Backup query object so following loops think this is a product page.
		$previous_wp_query = $wp_query;
		// @codingStandardsIgnoreStart
		$wp_query          = $single_product;

		// Set up post object.
		$single_product->the_post();

		// Enqueue single product script.
		wp_enqueue_script( 'wc-single-product' );

		/**
		 * woocommerce_before_single_product hook
		 *
		 * @hooked wc_print_notices - 10
		 */
		do_action( 'woocommerce_before_single_product' );

		/**
		 * woocommerce_single_product_summary chunks
		*/
		if ( $atts['show_title'] ) {
			woocommerce_template_single_title();
		}
		if ( $atts['show_rating'] ) {
			woocommerce_template_single_rating();
		}
		if ( $atts['show_price'] ) {
			woocommerce_template_single_price();
		}
		if ( $atts['show_excerpt'] ) {
			woocommerce_template_single_excerpt();
		}

		// Get product object.
		$single_product_obj = get_wc_product_appointment( $single_product->post->ID );

		/**
		 * Product filters
		*/
		if ( isset( $atts['availability_autoselect'] ) && null !== $atts['availability_autoselect'] ) {
			$single_product_obj->set_availability_autoselect( $atts['availability_autoselect'] );
		}
		if ( isset( $atts['customer_timezones'] ) && null !== $atts['customer_timezones'] ) {
			$single_product_obj->set_customer_timezones( $atts['customer_timezones'] );
		}

		// Prepare appointment form.
		$appointment_form = new WC_Appointment_Form( $single_product_obj );

		// Get template.
		wc_get_template(
			'single-product/add-to-cart/appointment.php',
			[
				'appointment_form' => $appointment_form,
			],
			'',
			WC_APPOINTMENTS_TEMPLATE_PATH
		);

		if ( $atts['show_meta'] ) {
			woocommerce_template_single_meta();
		}
		if ( $atts['show_sharing'] ) {
			woocommerce_template_single_sharing();
		}

		// Restore $previous_wp_query and reset post data.
		// @codingStandardsIgnoreStart
		$wp_query = $previous_wp_query;
		// @codingStandardsIgnoreEnd
		wp_reset_postdata();

		return '<div class="woocommerce"><div class="product">' . ob_get_clean() . '</div></div>';
	}

}

$GLOBALS['wc_appointments_shortcodes'] = new WC_Appointments_Shortcodes();
