<?php
/**
 * PLAIN Customer appointment confirmed email
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/plain/customer-appointment-confirmed.php.
 *
 * HOWEVER, on occasion we will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see         https://docs.woocommerce.com/document/template-structure/
 * @version     4.10.7
 * @since       3.4.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

echo '= ' . $email_heading . " =\n\n";

if ( $appointment->get_order() ) {
	/* translators: %s: billing first name */
	printf( __( 'Hello %s', 'woocommerce-appointments' ), ( is_callable( [ $appointment->get_order(), 'get_billing_first_name' ] ) ? $appointment->get_order()->get_billing_first_name() : $appointment->get_order()->billing_first_name ) ) . "\n\n";
}

echo __( 'Your appointment for has been confirmed. The details of your appointment are shown below.', 'woocommerce-appointments' ) . "\n\n";

echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

/* translators: %s: appointment product title */
printf( __( 'Scheduled Product: %s', 'woocommerce-appointments' ), $appointment->get_product_name() ) . "\n";
/* translators: %s: appointment ID */
printf( __( 'Appointment ID: %s', 'woocommerce-appointments' ), $appointment->get_id() ) . "\n";
/* translators: %s: appointment start date */
printf( __( 'Appointment Date: %s', 'woocommerce-appointments' ), $appointment->get_start_date() ) . "\n";
/* translators: %s: appointment duration */
printf( __( 'Appointment Duration: %s', 'woocommerce-appointments' ), $appointment->get_duration() ) . "\n";

$staff = $appointment->get_staff_members( true );
if ( $appointment->has_staff() && $staff ) {
	/* translators: %s: appointment staff names */
	printf( __( 'Appointment Providers: %s', 'woocommerce-appointments' ), $staff ) . "\n";
}

echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

$wc_order = $appointment->get_order();
if ( $wc_order ) {
	if ( 'pending' === $wc_order->get_status() && 0 < $wc_order->get_total() ) {
		/* translators: %s: checkout payment url */
		printf( __( 'To pay for this appointment please use the following link: %s', 'woocommerce-appointments' ), $wc_order->get_checkout_payment_url() ) . "\n\n";
	}

	do_action( 'woocommerce_email_before_order_table', $wc_order, $sent_to_admin, $plain_text, $email );

	$order_date = $wc_order->get_date_created() ? $wc_order->get_date_created()->date( 'Y-m-d H:i:s' ) : '';

	/* translators: %s: order number */
	printf( __( 'Order number: %s', 'woocommerce-appointments'), $wc_order->get_order_number() ) . "\n";
	/* translators: %s: order date */
	printf( __( 'Order date: %s', 'woocommerce-appointments'), date_i18n( wc_appointments_date_format(), strtotime( $order_date ) ) ) . "\n";

	do_action( 'woocommerce_email_order_meta', $wc_order, $sent_to_admin, $plain_text, $email );

	echo "\n";

	switch ( $wc_order->get_status() ) {
		case 'completed':
			echo wc_get_email_order_items(
				$wc_order,
				[
					'show_sku'   => false,
					'plain_text' => true,
				]
			); // WPCS: XSS ok.
			break;
		case 'processing':
		default:
			echo wc_get_email_order_items(
				$wc_order,
				[
					'show_sku'   => true,
					'plain_text' => true,
				]
			); // WPCS: XSS ok.
			break;
	}

	echo "==========\n\n";

	$order_totals = $wc_order->get_order_item_totals();
	if ( $order_totals ) {
		foreach ( $order_totals as $order_total ) {
			echo $order_total['label'] . "\t " . $order_total['value'] . "\n";
		}
	}

	echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

	do_action( 'woocommerce_email_after_order_table', $wc_order, $sent_to_admin, $plain_text, $email );
}

/**
 * Show user-defined additonal content - this is set in each email's settings.
 */
if ( $additional_content ) {
	esc_html_e( wp_strip_all_tags( wptexturize( $additional_content ) ) );
	echo "\n\n----------------------------------------\n\n";
}

echo apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) );
