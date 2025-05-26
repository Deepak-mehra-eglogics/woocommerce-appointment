<?php

namespace AutomateWoo\Proxies;

use AutomateWoo\Exceptions\InvalidValue;
use AutomateWoo\Exceptions\InvalidIntegration;
use AutomateWoo\Traits\IntegrationValidator;
use WC_Appointment;
use WC_Appointment_Data_Store;

defined( 'ABSPATH' ) || exit;

/**
 * Proxy for the WooCommerce appointments integration.
 *
 * @since 4.18.1
 */
class Appointments implements AppointmentsInterface {

	use IntegrationValidator;

	/**
	 * Get a appointment by ID.
	 *
	 * @param int $id
	 *
	 * @return WC_Appointment
	 *
	 * @throws InvalidValue If appointment not found.
	 * @throws InvalidIntegration If appointments plugin not active.
	 */
	public function get_appointment( int $id ): WC_Appointment {
		$appointment = get_wc_appointment( $id );
		if ( ! $appointment instanceof WC_Appointment ) {
			throw InvalidValue::item_not_found();
		}

		return $appointment;
	}

	/**
	 * Get appointment ids by filters.
	 *
	 * The 'status' filter defaults to use all appointment statuses excluding 'trash'.
	 *
	 * @see WC_Appointment_Data_Store::get_appointment_ids_by (wrapped method)
	 *
	 * @param array $filters Filters for the query.
	 * @param int   $limit  The query limit.
	 * @param int   $offset The query offset.
	 *
	 * @return int[]
	 *
	 * @throws InvalidIntegration If appointments plugin not active.
	 */
	public function get_appointment_ids_by( array $filters = [], int $limit = -1, int $offset = 0 ): array {
		$filters['offset'] = $offset;
		$filters['limit']  = $limit;
		$filters           = array_merge(
			[
				// Set query statuses so trashed appointment aren't included
				'status' => array_keys( $this->get_appointment_statuses() ),
			],
			$filters
		);

		return WC_Appointment_Data_Store::get_appointment_ids_by( $filters );
	}

	/**
	 * Get the most recent appointment.
	 *
	 * @return WC_Appointment
	 *
	 * @throws InvalidIntegration If appointments plugin not active.
	 * @throws InvalidValue If appointment not found.
	 */
	public function get_most_recent_appointment(): WC_Appointment {
		$ids = $this->get_appointment_ids_by( [], 1 );
		if ( empty( $ids ) ) {
			throw InvalidValue::item_not_found();
		}
		return $this->get_appointment( $ids[0] );
	}

	/**
	 * Return a list of supported appointment status values & labels.
	 *
	 * @return array Array of valid status values, in slug => label form.
	 */
	public function get_appointment_statuses(): array {
		// Hard-coding these for now.
		// We could call `get_wc_appointment_statuses( $context )`, but we would need to hard-code
		// various values for $context, and then remove duplicates.
		// Simpler to just hard-code the status values directly.
		return [
			'unpaid'               => __( 'Unpaid', 'woocommerce-appointments' ),
			'pending-confirmation' => __( 'Pending confirmation', 'woocommerce-appointments' ),
			'confirmed'            => __( 'Confirmed', 'woocommerce-appointments' ),
			'paid'                 => __( 'Paid', 'woocommerce-appointments' ),
			'complete'             => __( 'Complete', 'woocommerce-appointments' ),
			'in-cart'              => __( 'In cart', 'woocommerce-appointments' ),
			'cancelled'            => __( 'Cancelled', 'woocommerce-appointments' ),
		];
	}

	/**
	 * Get a list of draft appointment statuses.
	 *
	 * @since 5.4.0
	 *
	 * @return string[]
	 */
	public function get_draft_appointment_statuses(): array {
		return [ 'in-cart', 'was-in-cart' ];
	}
}
