<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Files.FileName

/**
 * WooCommerce Point of Sale integration class.
 */
class WC_Appointments_Integration_CRM {

    /**
	 * Constructor
	 */
	public function __construct() {
		add_filter( 'woocommerce_appointments_calendar_view_single_card', array( $this, 'wca_crm_appointent_card' ), 10, 3 );
		add_action( 'woocommerce_appointments_after_admin_dialog_script', array( $this, 'wca_crm_use_crm_customer_edit_url' ) );
	}

	/**
	 * Add Script to Appointment Edit Dialog
	 * Open CRM user profile instead of native WordPress user profile
	 */
	public function wca_crm_appointent_card( $singlecard, $datarray, $appointment ) {
		global $wpdb;

		/*
		**	Customer
		*/
		$user_id = $datarray['customer_id'];
		if ( $user_id ) {

			// 1. Add CRM Customer Profile URL
			$crm_user_id     = intval( $wpdb->get_var( "SELECT c_id FROM {$wpdb->prefix}wc_crm_customer_list WHERE user_id = {$user_id}" ) );
			$custom_data_crm = 'data-customer-crm-id="' . $crm_user_id . '"';

			// 2. Customer Notes
			$comment_ids = $wpdb->get_results( "SELECT comment_id FROM {$wpdb->prefix}commentmeta WHERE meta_value = {$crm_user_id}", ARRAY_N );
			// Show only the last 4 comments
			$comment_ids = array_slice( array_reverse( $comment_ids ), 0, 4 );
			foreach ( $comment_ids as $comment_id ) {
				$customer_note .= '<li>' . get_comment( $comment_id[0] )->comment_content . '</li>';
			}
			$customer_notes = 'data-customer-notes="' . esc_html( $customer_note ) . '"';

			// Add to beginning of singlecard
			$singlecard = substr_replace( $singlecard, $custom_data_crm . $customer_notes, 4, 0 );
		}

		/*
		**	Order
		*/
		$order_id = intval( $datarray['order_id'] );
		if ( $order_id ) {

			// 3. Orders Notes
			$comment_ids = $wpdb->get_results( "SELECT comment_id FROM {$wpdb->prefix}comments WHERE comment_post_ID = {$order_id}", ARRAY_N );
			$comment_ids = array_slice( array_reverse( $comment_ids ), 0, 4 );
			foreach ( $comment_ids as $comment_id ) {
				$order_note .= '<li>' . get_comment( $comment_id[0] )->comment_content . '</li>';
			}

			if ( '' != $order_note ) {
				$oder_notes = 'data-order-notes="' . esc_html( $order_note ) . '"';
				// Add to beginning of singlecard
				$singlecard = substr_replace( $singlecard, $oder_notes, 4, 0 );
			}
		}

		/*
		**	Product
		*/
		$product    = '<li class="appointment_product">' . $datarray['product_title'] . '</li>';
		$singlecard = str_replace( '<ul>', '<ul>' . $product, $singlecard );

		return $singlecard;
	}

	public function wca_crm_use_crm_customer_edit_url( $appointment_id ) {
	    ?>
			var customer_crm_id = appointment.attr( 'data-customer-crm-id' );
			jQuery('.wca-customer-url, .wca-edit-customer' ).attr('href','<?php echo esc_url( admin_url() ); ?>admin.php?page=wc_crm&c_id=' + customer_crm_id);

			// Customer Note
			var customer_notes =  appointment.attr( 'data-customer-notes' );
			if(customer_notes !== undefined && customer_notes !== ''){
				jQuery('#wca-detail-customer .wca-customer-status').before('&ensp;<span class="dashicons secondary dashicons-testimonial tips"></span></span>');
				jQuery('#wca-detail-customer dd .dashicons-testimonial').tipTip({content: '<u><?php echo esc_attr__( 'Customer Notes', 'woocommerce-appointments' ); ?></u><ol>' + customer_notes + '</ol>'});
			}

			// Order Note
			var order_notes =  appointment.attr( 'data-order-notes' );
			if(order_notes !== undefined && order_notes !== ''){
				jQuery('#wca-detail-staff dd').append('&ensp;<span class="dashicons secondary dashicons-testimonial tips"></span></span>');
				jQuery('#wca-detail-staff dd .dashicons-testimonial').tipTip({content: '<u><?php echo esc_attr__( 'Order Notes', 'woocommerce-appointments' ); ?></u><ol>' + order_notes + '</ol>'});
			}

		<?php
	}
}

$GLOBALS['wc_appointments_integration_crm'] = new WC_Appointments_Integration_CRM();
