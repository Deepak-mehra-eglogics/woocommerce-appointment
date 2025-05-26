<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * WC_Appointments_Admin_Exporters Class.
 */
class WC_Appointments_Admin_Exporters {

	/**
	 * Array of exporter IDs.
	 *
	 * @var string[]
	 */
	protected $exporters = [];

	/**
	 * Constructor.
	 */
	public function __construct() {
		if ( ! $this->export_allowed() ) {
			return;
		}

		// Requires at least WooCommerce 3.1.
		if ( version_compare( WC_VERSION, '3.1', '<' ) ) {
			return;
		}

		$this->download_export_file();

		add_action( 'admin_menu', [ $this, 'add_to_menus' ] );
		add_action( 'admin_head', [ $this, 'hide_from_menus' ] );
		add_action( 'wp_ajax_woocommerce_do_ajax_appointment_export', [ $this, 'do_ajax_appointment_export' ] );

		// Register WooCommerce exporters.
		$this->exporters['appointment_exporter'] = [
			'menu'       => 'edit.php?post_type=wc_appointment',
			'name'       => __( 'Appointment Export', 'woocommerce-appointments' ),
			'capability' => 'export',
			'callback'   => [ $this, 'appointment_exporter' ],
		];
	}

	/**
	 * Return true if WooCommerce export is allowed for current user, false otherwise.
	 *
	 * @return bool Whether current user can perform export.
	 */
	protected function export_allowed() {
		return current_user_can( 'edit_appointments' ) && current_user_can( 'export' );
	}

	/**
	 * Add menu items for our custom exporters.
	 */
	public function add_to_menus() {
		foreach ( $this->exporters as $id => $exporter ) {
			add_submenu_page( $exporter['menu'], $exporter['name'], $exporter['name'], $exporter['capability'], $id, $exporter['callback'] );
		}
	}

	/**
	 * Hide menu items from view so the pages exist, but the menu items do not.
	 */
	public function hide_from_menus() {
		global $submenu;

		foreach ( $this->exporters as $id => $exporter ) {
			if ( isset( $submenu[ $exporter['menu'] ] ) ) {
				foreach ( $submenu[ $exporter['menu'] ] as $key => $menu ) {
					if ( $id === $menu[2] ) {
						unset( $submenu[ $exporter['menu'] ][ $key ] );
					}
				}
			}
		}
	}

	/**
	 * Export page UI.
	 */
	public function appointment_exporter() {
		include_once WC_APPOINTMENTS_ABSPATH . 'includes/export/class-wc-appointment-csv-exporter.php';
		include_once __DIR__ . '/views/html-appointment-export.php';
	}

	/**
	 * Serve the generated file.
	 */
	public function download_export_file() {
		if ( is_admin()
		    && isset( $_GET['action'], $_GET['nonce'] )
			&& wp_verify_nonce( wp_unslash( $_GET['nonce'] ), 'appointment-csv' )
			&& 'download_appointment_csv' === wp_unslash( $_GET['action'] )
		) { // WPCS: input var ok, sanitization ok.
			include_once WC_APPOINTMENTS_ABSPATH . 'includes/export/class-wc-appointment-csv-exporter.php';
			$exporter = new WC_Appointment_CSV_Exporter();

			if ( ! empty( $_GET['filename'] ) ) { // WPCS: input var ok.
				$exporter->set_filename( wp_unslash( $_GET['filename'] ) ); // WPCS: input var ok, sanitization ok.
			}

			$exporter->export();
		}
	}

	/**
	 * AJAX callback for doing the actual export to the CSV file.
	 */
	public function do_ajax_appointment_export() {
		check_ajax_referer( 'wc-appointment-export', 'security' );

		if ( ! $this->export_allowed() ) {
			wp_send_json_error(
				[
					'message' => __( 'Insufficient privileges to export appointments.', 'woocommerce-appointments' ),
				]
			);
		}

		include_once WC_APPOINTMENTS_ABSPATH . 'includes/export/class-wc-appointment-csv-exporter.php';

		$step     = isset( $_POST['step'] ) ? absint( $_POST['step'] ) : 1; // WPCS: input var ok, sanitization ok.
		$exporter = new WC_Appointment_CSV_Exporter();

		if ( ! empty( $_POST['columns'] ) ) { // WPCS: input var ok.
			$exporter->set_column_names( wp_unslash( $_POST['columns'] ) ); // WPCS: input var ok, sanitization ok.
		}

		if ( ! empty( $_POST['selected_columns'] ) ) { // WPCS: input var ok.
			$exporter->set_columns_to_export( wp_unslash( $_POST['selected_columns'] ) ); // WPCS: input var ok, sanitization ok.
		}

		if ( ! empty( $_POST['export_start'] ) ) { // WPCS: input var ok.
			$exporter->set_start_date_to_export( wp_unslash( $_POST['export_start'] ) ); // WPCS: input var ok, sanitization ok.
		}

		if ( ! empty( $_POST['export_end'] ) ) { // WPCS: input var ok.
			$exporter->set_end_date_to_export( wp_unslash( $_POST['export_end'] ) ); // WPCS: input var ok, sanitization ok.
		}

		if ( ! empty( $_POST['export_product'] ) && is_array( $_POST['export_product'] ) ) {// WPCS: input var ok.
			$exporter->set_appointment_product_to_export( wp_unslash( array_values( $_POST['export_product'] ) ) ); // WPCS: input var ok, sanitization ok.
		}

		if ( ! empty( $_POST['export_staff'] ) && is_array( $_POST['export_staff'] ) ) {// WPCS: input var ok.
			$exporter->set_appointment_staff_to_export( wp_unslash( array_values( $_POST['export_staff'] ) ) ); // WPCS: input var ok, sanitization ok.
		}

		if ( ! empty( $_POST['export_addon'] ) ) { // WPCS: input var ok.
			$exporter->enable_addon_export( true );
		}

		if ( ! empty( $_POST['filename'] ) ) { // WPCS: input var ok.
			$exporter->set_filename( wp_unslash( $_POST['filename'] ) ); // WPCS: input var ok, sanitization ok.
		}

		$exporter->set_page( $step );
		$exporter->generate_file();

		$query_args = apply_filters(
			'woocommerce_export_get_ajax_query_args',
			[
				'nonce'    => wp_create_nonce( 'appointment-csv' ),
				'action'   => 'download_appointment_csv',
				'filename' => $exporter->get_filename(),
			]
		);

		if ( 100 === $exporter->get_percent_complete() ) {
			wp_send_json_success(
				[
					'step'       => 'done',
					'percentage' => 100,
					'url'        => add_query_arg( $query_args, admin_url( 'edit.php?post_type=appointment&page=appointment_exporter' ) ),
				]
			);
		} else {
			wp_send_json_success(
				[
					'step'       => ++$step,
					'percentage' => $exporter->get_percent_complete(),
					'columns'    => $exporter->get_column_names(),
				]
			);
		}
	}
}

new WC_Appointments_Admin_Exporters();
