<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Class for an appointment product's staff type
 */
class WC_Product_Appointment_Staff {

	private $staff;
	private $product;

	/**
	 * Constructor
	 */
	public function __construct( $user, $product = false ) {
		if ( $user && is_numeric( $user ) ) {
			$this->staff = get_user_by( 'id', $user );
		} elseif ( $user instanceof WP_User ) {
			$this->staff = $user;
		} else {
			$this->staff = new WP_User( $user );
		}

		$this->product = is_wc_appointment_product( $product ) ? $product : false;
	}

	/**
	 * __isset function.
	 *
	 * @access public
	 * @param string $key
	 * @return bool
	 */
	public function __isset( $key ) {
		return isset( $this->staff->$key );
	}

	/**
	 * __get function.
	 *
	 * @access public
	 * @param string $key
	 * @return string
	 */
	public function __get( $key ) {
		return $this->staff->$key;
	}

	/**
	 * Return the ID
	 * @return int
	 */
	public function get_id() {
		return isset( $this->staff->ID ) ? $this->staff->ID : 0;
	}

	/**
	 * Get the title of the staff
	 * @return string
	 */
	public function get_display_name() {
		return $this->get_id() ? $this->staff->display_name : '';
	}

	/**
	 * Get the full name of the staff
	 * @return string
	 */
	public function get_full_name() {
		return $this->get_id() ? trim( $this->staff->user_firstname . ' ' . $this->staff->user_lastname ) : '';
	}

	/**
	 * Get the email of the staff
	 * @return string
	 */
	public function get_email() {
		return $this->get_id() ? $this->staff->user_email : '';
	}

	/**
	 * Return the base cost
	 * @return int|float
	 */
	public function get_base_cost() {
		$cost = 0;

		if ( $this->get_id() && $this->product ) {
			$costs = $this->product->get_staff_base_costs();
			$cost  = isset( $costs[ $this->get_id() ] ) ? $costs[ $this->get_id() ] : 0;
		}

		return (float) $cost;
	}

	/**
	 * Return the capacity of the staff
	 * @return float
	 */
	public function get_qty() {
		$qty = 0;

		if ( $this->get_id() && $this->product ) {
			$qtys = $this->product->get_staff_qtys();
			$qty  = isset( $qtys[ $this->get_id() ] ) ? $qtys[ $this->get_id() ] : 0;
		}

		// Default to product qty, when staff capacity not set on product level.
		if ( ! $qty && $this->product ) {
			$qty = $this->product->get_qty();
		}

		return (float) $qty;
	}

	/**
	 * Return the availability rules
	 * @return array
	 */
	public function get_availability( $skip_filters = false ) {
		if ( ! $this->get_id() ) {
			return [];
		}

		$user_availability = WC_Data_Store::load( 'appointments-availability' )->get_all_as_array(
			[
				[
					'key'     => 'kind',
					'compare' => '=',
					'value'   => 'availability#staff',
				],
				[
					'key'     => 'kind_id',
					'compare' => '=',
					'value'   => $this->get_id(),
				],
			]
		);

		// Skip filters.
		if ( $skip_filters ) {
			return $user_availability;
		}

		return apply_filters( 'wc_appointments_staff_availability', $user_availability, $this, $skip_filters );
	}

	/**
	 * Return the products assigned to staff.
	 * @return array
	 */
	public function get_product_ids( $skip_filters = false ) {
		if ( ! $this->get_id() ) {
			return [];
		}

		$user_product_ids = WC_Data_Store::load( 'product-appointment' )->get_appointable_product_ids_for_staff( $this->get_id() );

		if ( ! $user_product_ids ) {
			return [];
		}

		// Skip filters.
		if ( $skip_filters ) {
			return $user_product_ids;
		}

		return apply_filters( 'wc_appointments_staff_products', $user_product_ids, $this, $skip_filters );
	}

}
