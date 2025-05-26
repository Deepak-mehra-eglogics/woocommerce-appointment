<?php
/**
 * Appointment form class
 */
class WC_Appointment_Form {

	/**
	 * Appointment product data.
	 * @var WC_Product_Appointment
	 */
	public $product;

	/**
	 * Appointment fields.
	 * @var array
	 */
	private $fields;

	/**
	 * Constructor
	 * @param $product WC_Product_Appointment
	 */
	public function __construct( $product ) {
		$this->product = $product;
	}

	/**
	 * Prepare fields for the appointment form
	 */
	public function prepare_fields() {
		// Destroy existing fields
		$this->reset_fields();

		// Add fields in order
		$this->timezone_field();
		$this->staff_field();
		$this->date_field();
		$this->addons_data_field();

		$this->fields = apply_filters( 'appointment_form_fields', $this->fields );
	}

	/**
	 * Reset fields array
	 */
	public function reset_fields() {
		$this->fields = [];
	}

	/**
	 * Add timezones field
	 */
	private function timezone_field() {
		// Timezones field
		if ( ! $this->product->has_timezones() ) {
			return;
		}

		// Get site's timezone.
		$tzstring = wc_timezone_string();

		// Add timezone from cookie.
		$tzstring = isset( $_COOKIE['appointments_time_zone'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['appointments_time_zone'] ) ) : $tzstring;

		$this->add_field(
			[
				'type'     => 'select-timezone',
				'name'     => 'timezone',
				'label'    => __( 'Timezone:', 'woocommerce-appointments' ) . ' <a class="selected-timezone">' . esc_html( str_replace( '_', ' ', $tzstring ) ) . '</a>',
				'selected' => $tzstring,
			]
		);
	}

	/**
	 * Add staff field
	 */
	private function staff_field() {
		// Staff field
		if ( ! $this->product->has_staff() || 'customer' !== $this->product->get_staff_assignment() ) {
			return;
		}

		$staff         = $this->product->get_staff();
		$staff_options = [];
		$data          = [];

		foreach ( $staff as $staff_member ) {
			$additional_cost = [];

			if ( $staff_member->get_base_cost() ) {
				$additional_cost[] = ' + ' . wp_strip_all_tags( wc_price( (float) $staff_member->get_base_cost() ) );
			}

			if ( $additional_cost ) {
				$additional_cost_string = implode( ', ', $additional_cost );
			} else {
				$additional_cost_string = '';
			}

			$staff_options[ $staff_member->get_id() ] = $staff_member->get_display_name() . apply_filters( 'woocommerce_appointments_staff_additional_cost_string', $additional_cost_string, $staff_member );
		}

		$this->add_field(
			[
				'type'    => 'select-staff',
				'name'    => 'staff',
				'label'   => $this->product->get_staff_label() ? $this->product->get_staff_label() : __( 'Providers', 'woocommerce-appointments' ),
				'nopref'  => $this->product->get_staff_nopref(),
				'class'   => [ 'wc_appointment_field_' . sanitize_title( $this->product->get_staff_label() ) ],
				'options' => $staff_options,
			]
		);
	}

	/**
	 * Add the date field to the appointment form
	 */
	private function date_field() {
		$picker = null;

		// Get date picker specific to the duration unit for this product
		switch ( $this->product->get_duration_unit() ) {
			case 'month':
				include_once 'class-wc-appointment-form-month-picker.php';
				$picker = new WC_Appointment_Form_Month_Picker( $this );
				break;
			case 'day':
			case 'night':
				include_once 'class-wc-appointment-form-date-picker.php';
				$picker = new WC_Appointment_Form_Date_Picker( $this );
				break;
			case 'minute':
			case 'hour':
				include_once 'class-wc-appointment-form-datetime-picker.php';
				$picker = new WC_Appointment_Form_Datetime_Picker( $this );
				break;
			default:
				break;
		}

		if ( ! is_null( $picker ) ) {
			$this->add_field( $picker->get_args() );
		}
	}

	/**
	 * Add Addons field
	 */
	private function addons_data_field() {
		// Addons fields
		$this->add_field(
			[
				'type'  => 'hidden',
				'name'  => 'addons_duration',
				'value' => 0,
			]
		);
		$this->add_field(
			[
				'type'  => 'hidden',
				'name'  => 'addons_cost',
				'value' => 0,
			]
		);
	}

	/**
	 * Add Field
	 * @param  array $field
	 * @return void
	 */
	public function add_field( $field ) {
		$default = [
			'name'  => '',
			'class' => [],
			'label' => '',
			'type'  => 'text',
		];

		$field = wp_parse_args( $field, $default );

		if ( ! $field['name'] || ! $field['type'] ) {
			return;
		}

		$nicename = 'wc_appointments_field_' . sanitize_title( $field['name'] );

		$field['name']    = $nicename;
		$field['class'][] = $nicename;

		$this->fields[ sanitize_title( $field['name'] ) ] = $field;
	}

	/**
	 * Output the form - called from the add to cart templates
	 */
	public function output() {
		$this->prepare_fields();

		foreach ( $this->fields as $key => $field ) {
			wc_get_template(
				'appointment-form/' . $field['type'] . '.php',
				[
					'field'   => $field,
					'product' => $this->product,
				],
				'',
				WC_APPOINTMENTS_TEMPLATE_PATH
			);
		}
	}

	/**
	 * Get posted form data into a neat array
	 * @param  array  $posted
	 * @param  string $get_data
	 * @return array|string|void
	 */
	public function get_posted_data( $posted = [], $get_data = false ) {
		wc_deprecated_function( __METHOD__, '4.7.0', 'wc_appointments_get_posted_data()' );
		return wc_appointments_get_posted_data( $posted, $this->product, $get_data );
	}

	/**
	 * Checks appointment data is correctly set, and that the chosen slots are indeed available.
	 *
	 * @param  array $data
	 * @return WP_Error on failure, true on success
	 */
	public function is_appointable( $data ) {
		wc_deprecated_function( __METHOD__, '4.7.0', 'WC_Product_Appointment::is_appointable()' );
		return $this->product->is_appointable( $data );
	}

	/**
	 * Get an array of formatted time values
	 * @param  string $timestamp
	 * @return array
	 */
	public function get_formatted_times( $timestamp ) {
		wc_deprecated_function( __METHOD__, '4.7.0', 'wc_appointments_get_formatted_times()' );
		return wc_appointments_get_formatted_times( $timestamp );
	}

	/**
	 * Calculate costs from posted values
	 * @param  array $posted
	 * @return string cost
	 */
	public function calculate_appointment_cost( $posted ) {
		wc_deprecated_function( __METHOD__, '1.15.0', 'WC_Bookings_Cost_Calculation::calculate_booking_cost()' );
		$data = wc_appointments_get_posted_data( $posted, $this->product );
		return apply_filters( 'appointment_form_calculated_appointment_cost', WC_Appointments_Cost_Calculation::calculate_appointment_cost( $data, $this->product ) );
	}

}
