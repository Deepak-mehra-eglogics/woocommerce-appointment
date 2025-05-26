<?php

namespace AutomateWoo\DataTypes;

use AutomateWoo\Proxies\Appointments;
use AutomateWoo\Exceptions\Exception as ExceptionInterface;
use WC_Appointment;

defined( 'ABSPATH' ) || exit;

/**
 * Appointment data type class.
 *
 * @since 4.18.1
 */
class Appointment extends AbstractDataType {

	/**
	 * @var Appointments
	 */
	protected $appointments_proxy;

	/**
	 * Appointment constructor.
	 *
	 * @param Appointments $appointments_proxy Appointments service class.
	 */
	public function __construct() {
		$this->appointments_proxy = new Appointments();
	}

	/**
	 * Check that an item is a valid object.
	 *
	 * @param mixed $item
	 *
	 * @return bool
	 */
	public function validate( $item ): bool {
		return $item instanceof WC_Appointment;
	}

	/**
	 * Compress a item to a storable format (typically an ID).
	 *
	 * @param mixed $item
	 *
	 * @return int|null Returns int if successful or null on failure.
	 */
	public function compress( $item ) {
		if ( $item instanceof WC_Appointment ) {
			return $item->get_id();
		}

		return null;
	}

	/**
	 * Get the full item from its stored format.
	 *
	 * @param int|string|null $compressed_item
	 * @param array           $compressed_data_layer
	 *
	 * @return WC_Appointment|null Returns a appointment object or null on failure.
	 */
	public function decompress( $compressed_item, $compressed_data_layer ) {
		try {
			return $this->appointments_proxy->get_appointment( intval( $compressed_item ) );
		} catch ( ExceptionInterface $e ) {
			return null;
		}
	}
}
