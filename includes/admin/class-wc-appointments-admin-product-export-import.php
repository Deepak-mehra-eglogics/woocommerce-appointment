<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Class for extending WooCommerce Product Exports and Imports.
 */
class WC_Appointments_Admin_Product_Export_Import {
	/**
	 * The data properties we'd like to include in the export.
	 *
	 * @var array
	 */
	protected $properties = [];

	/**
	 * Merges appointment product data into the parent object.
	 *
	 * @param int|WC_Product|object $product Product to init.
	 */
	public function __construct( $product = 0 ) {
		$this->set_default_properties();

		// Prepare for exporting.
		add_filter( 'woocommerce_product_export_product_default_columns', [ $this, 'columns_for_export' ], 30, 2 );
		add_filter( 'woocommerce_product_export_row_data', [ $this, 'prepare_data_for_export' ], 30, 3 );

		// Prepare for importing.
		add_filter( 'woocommerce_csv_product_import_mapping_options', [ $this, 'add_mapped_fields' ], 30, 2 );
		add_filter( 'woocommerce_csv_product_import_mapping_default_columns', [ $this, 'flip_mapped_fields' ], 30, 2 );
		add_filter( 'woocommerce_product_import_pre_insert_product_object', [ $this, 'prepare_data_for_import' ], 30, 2 );
	}
	/**
 	 * Setup the appointable properties that should also be exported.
	 *
	 * @TODO Add translations to labels.
 	 *
 	 * @since 3.6.0
 	 */
	public function set_default_properties(){
		$this->properties = [
			'has_price_label'         => 'Has price label option',
			'price_label'             => 'Price label',
			'has_pricing'             => 'Has pricing option',
			'pricing'                 => 'Pricing',
			'qty'                     => 'Qty',
			'qty_min'                 => 'Qty min',
			'qty_max'                 => 'Qty max',
			'duration_unit'           => 'Duration unit',
			'duration'                => 'Duration',
			'interval_unit'           => 'Interval unit',
			'interval'                => 'Interval',
			'padding_duration_unit'   => 'Padding duration unit',
			'padding_duration'        => 'Padding duration',
			'min_date_unit'           => 'Min date unit',
			'min_date'                => 'Min date',
			'max_date_unit'           => 'Max date unit',
			'max_date'                => 'Max date',
			'user_can_cancel'         => 'User can cancel option',
			'cancel_limit_unit'       => 'Cancel limit unit',
			'cancel_limit'            => 'Cancel limit',
			'user_can_reschedule'     => 'User can reschedule option',
			'reschedule_limit_unit'   => 'Reschedule limit unit',
			'reschedule_limit'        => 'Reschedule limit',
			'cal_color'               => 'Calendar color',
			'requires_confirmation'   => 'Requires confirmation option',
			'availability_span'       => 'Availability span selection',
			'availability_autoselect' => 'Availability autoselect option',
			'has_restricted_days'     => 'Has restricted days option',
			'restricted_days'         => 'Restricted days options',
			'availability'            => 'Availability rules',
			'staff_label'             => 'Staff label',
			'staff_assignment'        => 'Staff assignment selection',
			'staff_nopref'            => 'Staff no preference option',
			'staff_id'                => 'Staff ID',
			'staff_ids'               => 'Staff IDs',
			'staff_base_costs'        => 'Staff base costs',
			'staff_qtys'              => 'Staff quantities',
		];
	}

	/**
	 * When exporting WC core uses columns to decided which properties to export.
	 * Add the appointments properties  to this list to ensure that we export those values.
	 *
	 * @since 3.6.0
	 *
	 * @param array $default_columns
	 *
	 * @return array
	 */
	public function columns_for_export( $default_columns ) {
		return $default_columns + $this->properties;
	}

	/**
	 * Export all fields with arrays as json encoded strings.
	 *
	 * @since 4.8.19
	 *
	 * @param WC_Product $product Product being exported.
	 * @param array      $row     Row being exported.
	 *
	 * @return array
	 */
	public function prepare_data_for_export( $row, $product ) {
		if ( ! is_wc_appointment_product( $product ) ) {
			return $row;
		}

		if ( isset( $row['pricing'] ) && ! empty( $row['pricing'] ) ) {
			$row['pricing'] = wp_json_encode( $row['pricing'] );
		}
		if ( isset( $row['restricted_days'] ) && ! empty( $row['restricted_days'] ) ) {
			$row['restricted_days'] = wp_json_encode( $row['restricted_days'] );
		}
		if ( isset( $row['availability'] ) && ! empty( $row['availability'] ) ) {
			$row['availability'] = wp_json_encode( $row['availability'] );
		}
		if ( isset( $row['staff_id'] ) && ! empty( $row['staff_id'] ) ) {
			$row['staff_id'] = wp_json_encode( $row['staff_id'] );
		}
		if ( isset( $row['staff_ids'] ) && ! empty( $row['staff_ids'] ) ) {
			$row['staff_ids'] = wp_json_encode( $row['staff_ids'] );
		}
		if ( isset( $row['staff_base_costs'] ) && ! empty( $row['staff_base_costs'] ) ) {
			$row['staff_base_costs'] = wp_json_encode( $row['staff_base_costs'] );
		}
		if ( isset( $row['staff_qtys'] ) && ! empty( $row['staff_qtys'] ) ) {
			$row['staff_qtys'] = wp_json_encode( $row['staff_qtys'] );
		}

		#error_log( var_export( $row, true ) );

		return $row;
	}

	/**
	 * When importing WC core uses maps to link data to properties.
	 * Add the appointments mappings to this list.
	 *
	 * @since 3.6.0
	 *
	 * @param array $mappings
	 *
	 * @return array
	 */
	public function add_mapped_fields( $mappings ) {
		return $mappings + $this->properties;
	}

	/**
	 * When importing WC core uses maps to link data to properties.
	 * Add the appointments mappings to this list.
	 *
	 * @since 4.8.19
	 *
	 * @param array $mappings
	 *
	 * @return array
	 */
	public function flip_mapped_fields( $mappings ) {
		return $mappings + array_flip( $this->properties );
	}

	/**
	 * Json decode all imported strings.
	 *
	 * @since 4.8.19
	 *
	 * @param WC_Product $product Could be bookable or standard product.
	 * @param array $data Raw import data added tot the WC_Product.
	 *
	 * @return array|WC_Product
	 */
	public function prepare_data_for_import( $product, $data ) {
		if ( ! is_wc_appointment_product( $product ) ) {
			return $product;
		}

		if ( isset( $data['pricing'] ) && ! empty( $data['pricing'] ) ) {
			$product->set_pricing( json_decode( $data['pricing'], true ) );
		}
		if ( isset( $data['restricted_days'] ) && ! empty( $data['restricted_days'] ) ) {
			$product->set_restricted_days( json_decode( $data['restricted_days'], true ) );
		}
		if ( isset( $data['availability'] ) && ! empty( $data['availability'] ) ) {
			$availability = json_decode( $data['availability'], true );

			if ( $availability ) {
				foreach ( $availability as $availability_rule ) {
					$availability = get_wc_appointments_availability();
					$availability->set_kind( $availability_rule['kind'] );
					$availability->set_kind_id( $product->get_id() );
					$availability->set_event_id( $availability_rule['event_id'] );
					$availability->set_title( $availability_rule['title'] );
					$availability->set_range_type( $availability_rule['range_type'] );
					$availability->set_from_date( $availability_rule['from_date'] );
					$availability->set_to_date( $availability_rule['to_date'] );
					$availability->set_from_range( $availability_rule['from_range'] );
					$availability->set_to_range( $availability_rule['to_range'] );
					$availability->set_appointable( $availability_rule['appointable'] );
					$availability->set_priority( $availability_rule['priority'] );
					$availability->set_qty( $availability_rule['qty'] );
					$availability->set_ordering( $availability_rule['ordering'] );
					$availability->set_rrule( $availability_rule['rrule'] );
					$availability->save();
				}
			}
		}
		if ( isset( $data['staff_ids'] ) && ! empty( $data['staff_ids'] ) ) {
			$product->set_staff_ids( json_decode( $data['staff_ids'], true ) );
		}
		if ( isset( $data['staff_base_costs'] ) && ! empty( $data['staff_base_costs'] ) ) {
			$product->set_staff_base_costs( json_decode( $data['staff_base_costs'], true ) );
		}
		if ( isset( $data['staff_qtys'] ) && ! empty( $data['staff_qtys'] ) ) {
			$product->set_staff_qtys( json_decode( $data['staff_qtys'], true ) );
		}

		#error_log( var_export( $row, true ) );

		return $product;
	}
}

new WC_Appointments_Admin_Product_Export_Import();
