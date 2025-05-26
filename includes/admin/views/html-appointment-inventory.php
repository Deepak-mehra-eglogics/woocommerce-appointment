<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

// In one of future versions, move to:
# $stock_quantity = max( absint( $appointable_product->get_stock_quantity( 'edit' ) ), 1 );

$stock_quantity = max( absint( $appointable_product->get_qty( 'edit' ) ), 1 );
$capacity_min   = max( absint( $appointable_product->get_qty_min( 'edit' ) ), 1 );
$capacity_max   = max( absint( $appointable_product->get_qty_max( 'edit' ) ), 1 );
$capacity_max   = 0 === $appointable_product->get_qty_max( 'edit' ) ? 1 : max( absint( $appointable_product->get_qty_max( 'edit' ) ), 1 );

echo '<div class="options_group show_if_appointment">';

woocommerce_wp_text_input(
	[
		'id'                => '_wc_appointment_qty',
		'value'             => $stock_quantity,
		'label'             => __( 'Quantity', 'woocommerce-appointments' ),
		'desc_tip'          => true,
		'description'       => __( 'The available number per time slot.', 'woocommerce-appointments' ),
		'type'              => 'number',
		'custom_attributes' => [
			'step' => 1,
			'min'  => 1,
		],
		'data_type'         => 'stock',
	]
);

woocommerce_wp_text_input(
	[
		'id'                => '_wc_appointment_qty_min',
		'value'             => $capacity_min,
		'wrapper_class'     => '_wc_appointment_customer_qty_wrap',
		'label'             => __( 'Minimum', 'woocommerce-appointments' ),
		'desc_tip'          => true,
		'description'       => __( 'The required minimum per appointment.', 'woocommerce-appointments' ),
		'type'              => 'number',
		'custom_attributes' => [
			'step' => 1,
			'min'  => 1,
			'max'  => $stock_quantity,
		],
		'data_type'         => 'stock',
	]
);

woocommerce_wp_text_input(
	[
		'id'                => '_wc_appointment_qty_max',
		'value'             => $capacity_max,
		'wrapper_class'     => '_wc_appointment_customer_qty_wrap',
		'label'             => __( 'Maximum', 'woocommerce-appointments' ),
		'desc_tip'          => true,
		'description'       => __( 'The available maximum per appointment.', 'woocommerce-appointments' ),
		'type'              => 'number',
		'custom_attributes' => [
			'step' => 1,
			'min'  => 1,
		],
		'data_type'         => 'stock',
	]
);

echo '</div>';
