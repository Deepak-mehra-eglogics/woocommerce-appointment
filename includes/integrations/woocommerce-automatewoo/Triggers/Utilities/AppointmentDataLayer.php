<?php

namespace AutomateWoo\Triggers\Utilities;

use AutomateWoo\Customer_Factory;
use AutomateWoo\Data_Layer;
use AutomateWoo\DataTypes\DataTypes;
use AutomateWoo\Exceptions\InvalidValue;
use WC_Appointment;
use WC_Order;

/**
 * Trait AppointmentDataLayer
 *
 * @since 4.18.1
 */
trait AppointmentDataLayer {

	/**
	 * Get the supplied data items for a appointment.
	 *
	 * @return string[]
	 */
	protected function get_supplied_data_items_for_appointment(): array {
		return [ 'appointment', DataTypes::CUSTOMER, DataTypes::PRODUCT ];
		return [ 'appointment', DataTypes::CUSTOMER, DataTypes::PRODUCT, DataTypes::ORDER ];
	}

	/**
	 * Generate a appointment data layer from a appointment object.
	 *
	 * Includes appointment, customer, appointment product and order data types.
	 *
	 * @param WC_Appointment $appointment
	 *
	 * @return Data_Layer
	 *
	 * @throws InvalidValue If the appointment's customer or appointment is not found.
	 */
	protected function generate_appointment_data_layer( WC_Appointment $appointment ): Data_Layer {
		// First try to retrieve customer from order.
		$order = $appointment->get_order();
		// appointments can be made without an order, so there's no need to log if the customer isn't found through the order.
		$log_error = false;
		$customer  = Customer_Factory::get_by_order( $order, true, $log_error );
		if ( ! $customer ) {
			// If that fails, retrieve customer from appointment.
			$customer = Customer_Factory::get_by_user_id( $appointment->get_customer_id() );
		}

		if ( ! $customer ) {
			throw InvalidValue::item_not_found( esc_html( DataTypes::CUSTOMER ) );
		}

		$product = $appointment->get_product();
		if ( ! $product ) {
			throw InvalidValue::item_not_found( esc_html( DataTypes::PRODUCT ) );
		}

		return new Data_Layer(
			[
				'appointment'       => $appointment,
				DataTypes::CUSTOMER => $customer,
				DataTypes::PRODUCT  => $product,
				DataTypes::ORDER    => $order ?: new WC_Order(),
			]
		);
	}
}
