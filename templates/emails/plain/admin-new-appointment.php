<?php
/**
 * PLAIN Admin new appointment email
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/plain/admin-new-appointment.php.
 *
 * HOWEVER, on occasion we will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see         https://docs.woocommerce.com/document/template-structure/
 * @version     4.10.4
 * @since       3.4.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

echo '= ' . $email_heading . " =\n\n";

$appointment = $appointment ? $appointment : get_wc_appointment( 0 );
$wc_order    = $appointment->get_order();

if ( wc_appointment_order_requires_confirmation( $wc_order ) && $appointment->has_status( [ 'pending-confirmation' ] ) ) {
	/* translators: %s: billing first and last name */
	$opening_paragraph = __( 'A appointment has been made by %s and is awaiting your approval. The details of this appointment are as follows:', 'woocommerce-appointments' );
} else {
	/* translators: %s: billing first and last name */
	$opening_paragraph = __( 'A new appointment has been made by %s. The details of this appointment are as follows:', 'woocommerce-appointments' );
}

if ( $wc_order ) {
	printf( $opening_paragraph, $wc_order->get_formatted_billing_full_name() ) . "\n\n"; // WPCS: XSS ok.
}

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

if ( wc_appointment_order_requires_confirmation( $appointment_order ) && $appointment->has_status( [ 'pending-confirmation' ] ) ) {
	echo __( 'This appointment is awaiting your approval. Please check it and inform the customer if the date is available or not.', 'woocommerce-appointments' ) . "\n\n";
}

echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

if ( $wc_order ) {
	/*
	 * @hooked WC_Emails::order_details() Shows the order details table.
	 * @hooked WC_Structured_Data::generate_order_data() Generates structured data.
	 * @hooked WC_Structured_Data::output_structured_data() Outputs structured data.
	 */
	do_action( 'woocommerce_email_order_details', $wc_order, $sent_to_admin, $plain_text, $email );

	echo "\n----------------------------------------\n\n";

	/*
	 * @hooked WC_Emails::order_meta() Shows order meta data.
	 */
	do_action( 'woocommerce_email_order_meta', $wc_order, $sent_to_admin, $plain_text, $email );

	/*
	 * @hooked WC_Emails::customer_details() Shows customer details
	 * @hooked WC_Emails::email_address() Shows email address
	 */
	do_action( 'woocommerce_email_customer_details', $wc_order, $sent_to_admin, $plain_text, $email );

	echo "\n\n----------------------------------------\n\n";
}

echo apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) );
