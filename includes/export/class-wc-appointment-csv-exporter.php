<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Include dependencies.
 */
if ( ! class_exists( 'WC_CSV_Batch_Exporter', false ) ) {
	include_once WC_ABSPATH . 'includes/export/abstract-wc-csv-batch-exporter.php';
}

/**
 * WC_Appointment_CSV_Exporter Class.
 */
class WC_Appointment_CSV_Exporter extends WC_CSV_Batch_Exporter {

	/**
	 * Type of export used in filter names.
	 *
	 * @var string
	 */
	protected $export_type = 'appointment';


	/**
	 * Appointments starting at which date should be exported.
	 *
	 * @var string
	 */
	protected $appointment_start_date_to_export = '';

	/**
	 * Appointments ending at which date should be exported.
	 *
	 * @var string
	 */
	protected $appointment_end_date_to_export = '';

	/**
	 * Appointments belonging to what product should be exported.
	 *
	 * @var string
	 */
	protected $appointment_product_to_export = [];

	/**
	 * Appointments belonging to what staff should be exported.
	 *
	 * @var string
	 */
	protected $appointment_staff_to_export = [];

	/**
	 * Should add-on fields be exported?
	 *
	 * @var boolean
	 */
	protected $enable_addon_export = false;

	/**
	 * Appointment start date to export
	 *
	 * @param string $appointment_start_date_to_export Appointment start date to export, empty string exports all.
	 *
	 * @since  4.9.7
	 * @return void
	 */
	public function set_start_date_to_export( $appointment_start_date_to_export ) {
		$this->appointment_start_date_to_export = wp_unslash( $appointment_start_date_to_export );
	}

	/**
	 * Appointment end date to export
	 *
	 * @param string $appointment_end_date_to_export Appointment end date to export, empty string exports all.
	 *
	 * @since  4.9.7
	 * @return void
	 */
	public function set_end_date_to_export( $appointment_end_date_to_export ) {
		$this->appointment_end_date_to_export = wp_unslash( $appointment_end_date_to_export );
	}

	/**
	 * Appointment product to export
	 *
	 * @param string $appointment_product_to_export Appointment product IDS to export, empty string exports all.
	 *
	 * @since  4.9.7
	 * @return void
	 */
	public function set_appointment_product_to_export( $appointment_product_to_export ) {
		$this->appointment_product_to_export = array_map( 'intval', (array) wp_unslash( $appointment_product_to_export ) );
	}

	/**
	 * Appointment staff to export
	 *
	 * @param string $appointment_staff_to_export Appointment product IDS to export, empty string exports all.
	 *
	 * @since  4.9.7
	 * @return void
	 */
	public function set_appointment_staff_to_export( $appointment_staff_to_export ) {
		$this->appointment_staff_to_export = array_map( 'intval', (array) wp_unslash( $appointment_staff_to_export ) );
	}

	/**
	 * Should add-on fields be exported?
	 *
	 * @param bool $enable_addon_export Should add-on fields be exported.
	 *
	 * @since 4.9.7
	 */
	public function enable_addon_export( $enable_addon_export ) {
		$this->enable_addon_export = (bool) $enable_addon_export;
	}

	/**
	 * Return an array of columns to export.
	 *
	 * @since  4.9.7
	 * @return array
	 */
	public function get_default_column_names() {
		return apply_filters(
			"woocommerce_export_{$this->export_type}_default_columns",
			[
				'id'                       => 'ID',
				'product_id'               => 'Product ID',
				'product_name'             => 'Product Name',
				'staff_ids'                => 'Staff ID',
				'staff_names'              => 'Staff Name',
				'parent_id'                => 'Parent Appointment ID',
				'start_date'               => 'Start Date',
				'start'                    => 'Start timestamp',
				'end_date'                 => 'End Date',
				'end'                      => 'End timestamp',
				'date_created'             => 'Created Date timestamp',
				'date_modified'            => 'Modified Date timestamp',
				'timezone'                 => 'Timezone',
				'all_day'                  => 'Is all day?',
				'qty'                      => 'Quantity',
				'status'                   => 'Status',
				'cost'                     => 'Cost',
				'google_calendar_event_id' => 'Synced event ID',
				'order_id'                 => 'Order ID',
				'customer_id'              => 'Customer ID',
				'customer_name'            => 'Customer Name',
				'customer_email'           => 'Customer Email',
				'customer_phone'           => 'Customer Phone',
				'customer_address'         => 'Customer Address',
				'customer_status'          => 'Customer Status',
			]
		);
	}

	/**
	 * Prepare data for export.
	 *
	 * @since 4.9.7
	 */
	public function prepare_data_to_export() {
		$args = [
			'start'    => '',
			'end'      => '',
			'product'  => [],
			'staff'    => [],
			'status'   => array_unique( array_merge( get_wc_appointment_statuses( 'all' ), get_wc_appointment_statuses( 'user' ), get_wc_appointment_statuses( 'cancel' ) ) ),
			'limit'    => -1,
			'order_by' => 'date_created',
			'order'    => 'DESC',
			'return'   => 'objects',
		];

		if ( ! empty( $this->appointment_start_date_to_export ) ) {
			$args['start'] = strtotime( urldecode( $this->appointment_start_date_to_export ) );
		}

		if ( ! empty( $this->appointment_end_date_to_export ) ) {
			$args['end'] = strtotime( urldecode( $this->appointment_end_date_to_export ) );
		}

		// Product IDs.
		if ( ! empty( $this->appointment_product_to_export ) ) {
			$args['product'] = $this->appointment_product_to_export;
		}

		// Staff IDs.
		if ( ! empty( $this->appointment_staff_to_export ) ) {
			$args['staff'] = $this->appointment_staff_to_export;
		}

		// Allow 3rd parties to process the arguments, e.g. to change the orderby or similar.
		$args = apply_filters( 'woocommerce_appointment_export_data_args', $args, $this );

		#error_log( var_export( $args, true ) );

		$appointments = WC_Appointment_Data_Store::get_appointments_in_date_range(
			$args['start'],
			$args['end'],
			$args['product'],
			$args['staff'],
			false,
			$args
		);

		#$this->total_rows = $appointments->total;
		$this->row_data = [];

		foreach ( $appointments as $appointment ) {
			$this->row_data[] = $this->generate_row_data( $appointment );
		}
	}

	/**
	 * Take a appointment and generate row data from it for export.
	 *
	 * @param WC_Appointment $appointment WC_Appointment object.
	 *
	 * @return array
	 */
	protected function generate_row_data( $appointment ) {
		$columns = $this->get_column_names();
		$row     = [];
		foreach ( $columns as $column_id => $column_name ) {
			$column_id = strstr( $column_id, ':' ) ? current( explode( ':', $column_id ) ) : $column_id;
			$value     = '';

			// Skip some columns if dynamically handled later or if we're being selective.
			if ( in_array( $column_id, [ 'addons' ], true ) || ! $this->is_column_exporting( $column_id ) ) {
				continue;
			}

			if ( has_filter( "woocommerce_export_{$this->export_type}_column_{$column_id}" ) ) {
				// Filter for 3rd parties.
				$value = apply_filters( "woocommerce_export_{$this->export_type}_column_{$column_id}", '', $appointment, $column_id );

			} elseif ( is_callable( [ $this, "get_column_value_{$column_id}" ] ) ) {
				// Handle special columns which don't map 1:1 to appointment data.
				$value = $this->{"get_column_value_{$column_id}"}( $appointment );

			} elseif ( is_callable( [ $appointment, "get_{$column_id}" ] ) ) {
				// Default and custom handling.
				$value = $appointment->{"get_{$column_id}"}( 'edit' );
			}

			$row[ $column_id ] = $value;
		}

		$this->prepare_addon_for_export( $appointment, $row );

		return apply_filters( 'woocommerce_appointment_export_row_data', $row, $appointment );
	}

	/**
	 * Get product ID value.
	 *
	 * @param WC_Appointment $appointment Appointment being exported.
	 *
	 * @since  4.9.7
	 * @return int
	 */
	protected function get_column_value_product_id( $appointment ) {
		return $appointment->get_product_id( 'view' );
	}

	/**
	 * Get product name value.
	 *
	 * @param WC_Appointment $appointment Appointment being exported.
	 *
	 * @since  4.9.7
	 * @return int
	 */
	protected function get_column_value_product_name( $appointment ) {
		return $appointment->get_product_name();
	}

	/**
	 * Get staff IDs value.
	 *
	 * @param WC_Appointment $appointment Appointment being exported.
	 *
	 * @since  4.9.7
	 * @return string
	 */
	protected function get_column_value_staff_ids( $appointment ) {
		return $this->implode_values( $appointment->get_staff_ids( 'view' ) );
	}

	/**
	 * Get staff names value.
	 *
	 * @param WC_Appointment $appointment Appointment being exported.
	 *
	 * @since  4.9.7
	 * @return string
	 */
	protected function get_column_value_staff_names( $appointment ) {
		$staff_names = $appointment->get_staff_members( true );
		return $staff_names ? $staff_names : '';
	}

	/**
	 * Get parent appointment ID value.
	 *
	 * @param WC_Appointment $appointment Appointment being exported.
	 *
	 * @since  4.9.7
	 * @return int
	 */
	protected function get_column_value_parent_id( $appointment ) {
		if ( $appointment->get_parent_id( 'edit' ) ) {
			$parent = get_wc_appointment( $appointment->get_parent_id( 'edit' ) );
			if ( ! $parent ) {
				return '';
			}

			return $parent->get_id() ? $parent->get_id() : '';
		}
		return '';
	}

	/**
	 * Get formatted start date.
	 *
	 * @param WC_Appointment $appointment Appointment being exported.
	 *
	 * @return string
	 */
	protected function get_column_value_start_date( $appointment ) {
		return $appointment->get_start_date();
	}

	/**
	 * Get start timestamp
	 *
	 * @param WC_Appointment $appointment Appointment being exported.
	 *
	 * @return int
	 */
	protected function get_column_value_start( $appointment ) {
		return $appointment->get_start( 'view' );
	}

	/**
	 * Get formatted end date.
	 *
	 * @param WC_Appointment $appointment Appointment being exported.
	 *
	 * @return string
	 */
	protected function get_column_value_end_date( $appointment ) {
		return $appointment->get_end_date();
	}

	/**
	 * Get end timestamp
	 *
	 * @param WC_Appointment $appointment Appointment being exported.
	 *
	 * @return int
	 */
	protected function get_column_value_end( $appointment ) {
		return $appointment->get_end( 'view' );
	}

	/**
	 * Get created date timestamp
	 *
	 * @param WC_Appointment $appointment Appointment being exported.
	 *
	 * @return int
	 */
	protected function get_column_value_date_created( $appointment ) {
		return $appointment->get_date_created( 'view' );
	}

	/**
	 * Get modified date timestamp
	 *
	 * @param WC_Appointment $appointment Appointment being exported.
	 *
	 * @return int
	 */
	protected function get_column_value_date_modified( $appointment ) {
		return $appointment->get_date_modified( 'view' );
	}

	/**
	 * Get timezone
	 *
	 * @param WC_Appointment $appointment Appointment being exported.
	 *
	 * @return string
	 */
	protected function get_column_value_timezone( $appointment ) {
		return $appointment->get_timezone( 'view' );
	}

	/**
	 * Get all day value.
	 *
	 * @param WC_Appointment $appointment Appointment being exported.
	 *
	 * @since  4.9.7
	 * @return bool
	 */
	protected function get_column_value_all_day( $appointment ) {
		return $appointment->is_all_day();
	}

	/**
	 * Get quantity
	 *
	 * @param WC_Appointment $appointment Appointment being exported.
	 *
	 * @return int
	 */
	protected function get_column_value_qty( $appointment ) {
		return $appointment->get_qty( 'view' );
	}

	/**
	 * Get status
	 *
	 * @param WC_Appointment $appointment Appointment being exported.
	 *
	 * @return string
	 */
	protected function get_column_value_status( $appointment ) {
		return $appointment->get_status( 'view' );
	}

	/**
	 * Get formatted cost.
	 *
	 * @param WC_Appointment $appointment Appointment being exported.
	 *
	 * @return string
	 */
	protected function get_column_value_cost( $appointment ) {
		return wc_format_localized_price( $appointment->get_cost( 'view' ) );
	}

	/**
	 * Get synced Google calendar's event ID
	 *
	 * @param WC_Appointment $appointment Appointment being exported.
	 *
	 * @return string
	 */
	protected function get_column_value_google_calendar_event_id( $appointment ) {
		return $appointment->get_google_calendar_event_id( 'view' );
	}

	/**
	 * Get order ID
	 *
	 * @param WC_Appointment $appointment Appointment being exported.
	 *
	 * @return int
	 */
	protected function get_column_value_order_id( $appointment ) {
		return $appointment->get_order_id( 'view' );
	}

	/**
	 * Get customer ID
	 *
	 * @param WC_Appointment $appointment Appointment being exported.
	 *
	 * @return int
	 */
	protected function get_column_value_customer_id( $appointment ) {
		return $appointment->get_customer_id( 'view' );
	}

	/**
	 * Get customer name
	 *
	 * @param WC_Appointment $appointment Appointment being exported.
	 *
	 * @return string
	 */
	protected function get_column_value_customer_name( $appointment ) {
		$appointment_customer = $appointment->get_customer();
		return $appointment_customer->full_name;
	}

	/**
	 * Get customer email
	 *
	 * @param WC_Appointment $appointment Appointment being exported.
	 *
	 * @return string
	 */
	protected function get_column_value_customer_email( $appointment ) {
		$appointment_customer = $appointment->get_customer();
		return $appointment_customer->email;
	}

	/**
	 * Get customer phone
	 *
	 * @param WC_Appointment $appointment Appointment being exported.
	 *
	 * @return int
	 */
	protected function get_column_value_customer_phone( $appointment ) {
		$appointment_customer = $appointment->get_customer();
		return $appointment_customer->phone ? $appointment_customer->phone : '';
	}

	/**
	 * Get customer address
	 *
	 * @param WC_Appointment $appointment Appointment being exported.
	 *
	 * @return int
	 */
	protected function get_column_value_customer_address( $appointment ) {
		$appointment_customer = $appointment->get_customer();
		return $appointment_customer->billing_address ? $this->filter_description_field( $appointment_customer->billing_address ) : '';
	}

	/**
	 * Get customer status
	 *
	 * @param WC_Appointment $appointment Appointment being exported.
	 *
	 * @return int
	 */
	protected function get_column_value_customer_status( $appointment ) {
		return $appointment->get_customer_status( 'view' );
	}

	/**
	 * Filter description field for export.
	 * Convert newlines to '\n'.
	 *
	 * @param string $description Appointment description text to filter.
	 *
	 * @since  3.5.4
	 * @return string
	 */
	protected function filter_description_field( $description ) {
		$description = str_replace( '\n', "\\\\n", $description );
		$description = str_replace( "\n", '\n', $description );
		return $description;
	}

	/**
	 * Export add-on fields data.
	 *
	 * @param WC_Appointment $appointment Appointment being exported.
	 * @param array          $row         Row data.
	 *
	 * @since 4.9.7
	 */
	protected function prepare_addon_for_export( $appointment, &$row ) {
		if ( $this->enable_addon_export ) {
			$args       = [
				'before'       => '',
				'after'        => '',
				'separator'    => ',',
				'echo'         => false,
				'autop'        => false,
				'label_before' => '',
				'label_after'  => ':',
			];
			$addon_data = wp_strip_all_tags( $appointment->get_addons( $args ) );
			$column_key = 'meta'; // must be "meta", otherwise it will not export.

			// Allow 3rd parties to process the meta, e.g. to transform non-scalar values to scalar.
			$addon_data = apply_filters( 'woocommerce_appointment_export_addon_data', $addon_data, $appointment, $row );

			if ( is_scalar( $addon_data ) ) {
				$this->column_names[ $column_key ] = __( 'Add-ons', 'woocommerce-appointments' );
				$row[ $column_key ]                = $addon_data;
			}
		}
	}
}
