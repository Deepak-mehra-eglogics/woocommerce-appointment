<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

class WC_Appointment_Meta_Box_Customer {

	/**
	 * Meta box ID.
	 *
	 * @var string
	 */
	public $id;

	/**
	 * Meta box title.
	 *
	 * @var string
	 */
	public $title;

	/**
	 * Meta box context.
	 *
	 * @var string
	 */
	public $context;

	/**
	 * Meta box priority.
	 *
	 * @var string
	 */
	public $priority;

	/**
	 * Meta box post types.
	 * @var array
	 */
	public $post_types;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id         = 'woocommerce-customer-data';
		$this->title      = __( 'Customer details', 'woocommerce-appointments' );
		$this->context    = 'side';
		$this->priority   = 'default';
		$this->post_types = [ 'wc_appointment' ];
	}

	/**
	 * Meta box content.
	 */
	public function meta_box_inner( $post ) {
 		global $appointment;

 		if ( ! is_a( $appointment, 'WC_Appointment' ) || $appointment->get_id() !== $post->ID ) {
 			$appointment = get_wc_appointment( $post->ID );
 		}
 		?>
		<div class="customer_data">
 			<?php
			$appointment_customer = $appointment->get_customer();
			#print '<pre>'; print_r( $appointment_customer ); print '</pre>';
			?>
			<?php if ( $appointment_customer->billing_address ) { ?>
				<p>
					<strong><?php esc_html_e( 'Billing:', 'woocommerce-appointments' ); ?></strong>
					<?php echo wp_kses( $appointment_customer->billing_address, [ 'br' => [] ] ); ?>
				</p>
			<?php } elseif ( $appointment_customer->shipping_address ) { ?>
				<p>
					<strong><?php esc_html_e( 'Shipping:', 'woocommerce-appointments' ); ?></strong>
					<?php echo wp_kses( $appointment_customer->shipping_address, [ 'br' => [] ] ); ?>
				</p>
			<?php } else { ?>
				<p><?php esc_html_e( $appointment_customer->full_name ); ?></p>
			<?php } ?>
			<?php if ( $appointment_customer->email ) { ?>
				<p>
					<strong><?php esc_html_e( 'Email:', 'woocommerce-appointments' ); ?></strong>
					<?php echo make_clickable( sanitize_email( $appointment_customer->email ) ); // WPCS: XSS ok. ?>
				</p>
			<?php } ?>
			<?php if ( $appointment_customer->phone ) { ?>
				<p>
					<strong><?php esc_html_e( 'Phone:', 'woocommerce-appointments' ); ?></strong>
					<?php echo wc_make_phone_clickable( $appointment_customer->phone ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Handled in function. ?>
				</p>
			<?php } ?>
			<?php if ( $appointment_customer->user_id ) { ?>
				<p class="view">
					<a class="button button-small" target="_blank" href="<?php echo esc_url( admin_url( 'user-edit.php?user_id=' . absint( $appointment_customer->user_id ) ) ); ?>"><?php esc_html_e( 'View User', 'woocommerce-appointments' ); ?></a>
				</p>
			<?php } ?>
			<?php do_action( 'woocommerce_admin_appointment_data_after_customer_details', $post->ID ); ?>
 		</div>
 		<?php
 	}
}

return new WC_Appointment_Meta_Box_Customer();
