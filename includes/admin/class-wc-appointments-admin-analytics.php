<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Plugin Name: WooCommerce Admin SQL modification Example
 *
 * @package WooCommerce\Admin
 */

/**
 * Make the currency settings available to the javascript client using
 * AssetDataRegistry, available in WooCommerce 3.9.
 *
 * The add_currency_settings function is a most basic example, but below is
 * a more elaborate example of how one might use AssetDataRegistry in classes.
 *
	```php
	<?php

	class MyClassWithAssetData {
		private $asset_data_registry;
		public function __construct( Automattic\WooCommerce\Blocks\AssetDataRegistry $asset_data_registry ) {
			$this->asset_data_registry = $asset_data_registry;
		}

		protected function some_method_adding_assets() {
			$this->asset_data_registry->add( 'myData', [ 'foo' => 'bar' ] );
		}
	}

	// somewhere in the extensions bootstrap
	class Bootstrap {
		protected $container;
		public function __construct( Automattic\WooCommerce\Blocks\Container $container ) {
			$this->container = $container;
			$this->container->register( MyClassWithAssetData::class, function( $blocks_container ) => {
				return new MyClassWithAssetData( $blocks_container->get( Automattic\WooCommerce\Blocks\AssetDataRegistry::class ) );
			} );
		}
	}

	// now anywhere MyClassWithAssetData is instantiated it will automatically be
	// constructed with the AssetDataRegistry
	```
 */

 /**
  * Logic for WooCommerce dashboard display.
  */
class WC_Appointments_Admin_Analytics {

	/**
	 * Hook in additional reporting to WooCommerce dashboard widget
	 */
	public function __construct() {
		// Add the dashboard widget text
		add_filter( 'admin_init', [ $this, 'add_staff_settings' ] );

		// Filter the Analytics data.
		add_filter( 'woocommerce_analytics_revenue_query_args', [ $this, 'analytics_staff_query_args' ] );
		add_filter( 'woocommerce_analytics_orders_query_args', [ $this, 'analytics_staff_query_args' ] );
		add_filter( 'woocommerce_analytics_orders_stats_query_args', [ $this, 'analytics_staff_query_args' ] );
		add_filter( 'woocommerce_analytics_products_query_args', [ $this, 'analytics_staff_query_args' ] );
		add_filter( 'woocommerce_analytics_products_stats_query_args', [ $this, 'analytics_staff_query_args' ] );
		add_filter( 'woocommerce_analytics_categories_query_args', [ $this, 'analytics_staff_query_args' ] );
		add_filter( 'woocommerce_analytics_coupons_query_args', [ $this, 'analytics_staff_query_args' ] );
		add_filter( 'woocommerce_analytics_coupons_stats_query_args', [ $this, 'analytics_staff_query_args' ] );
		add_filter( 'woocommerce_analytics_taxes_query_args', [ $this, 'analytics_staff_query_args' ] );
		add_filter( 'woocommerce_analytics_taxes_stats_query_args', [ $this, 'analytics_staff_query_args' ] );

		add_filter( 'woocommerce_analytics_products_query_args', [ $this, 'analytics_products_query_args' ] );
		add_filter( 'woocommerce_analytics_products_stats_query_args', [ $this, 'analytics_products_query_args' ] );

		#add_filter( 'woocommerce_analytics_clauses_select', [ $this, 'analytics_clauses_select_staff' ], 10, 2 );

		add_filter( 'woocommerce_analytics_clauses_join', [ $this, 'analytics_clauses_join' ], 10, 2 );
		#add_filter( 'woocommerce_analytics_clauses_join', [ $this, 'analytics_clauses_join_staff' ], 10, 2 );

		add_filter( 'woocommerce_analytics_clauses_where', [ $this, 'analytics_clauses_where' ], 10, 2 );
		#add_filter( 'woocommerce_analytics_clauses_where', [ $this, 'analytics_clauses_where_staff' ], 10, 2 );
	}

	/**
	 * Add the query argument `staff` for caching purposes. Otherwise, a
	 * change of the currency will return the previous request's data.
	 *
	 * @param array $args query arguments.
	 * @return array augmented query arguments.
	 */
	public function add_staff_settings() {
		$staff = WC_Appointments_Admin::get_appointment_staff(); #all staff

		// Default.
		$providers[] = [
			'label' => esc_html__( 'All staff', 'woocommerce-appointments' ),
			'value' => 'all',
		];

		foreach ( $staff as $staff_member ) {
			$providers[] = [
				'label' => strval( $staff_member->display_name ), #convert to string
				'value' => strval( $staff_member->ID ), #convert to string
			];
		}

		#error_log( var_export( $providers, true ) );

		$data_registry = Automattic\WooCommerce\Blocks\Package::container()->get(
			Automattic\WooCommerce\Blocks\Assets\AssetDataRegistry::class
		);

		$data_registry->add( 'multiStaff', $providers );
	}

	/**
	 * Add the query argument `staff` for caching purposes. Otherwise, a
	 * change of the staff will return the previous request's data.
	 *
	 * @param array $args query arguments.
	 *
	 * @return array augmented query arguments.
	 */
	public function analytics_staff_query_args( $args ) {
		$staff = 'all';

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['staff'] ) ) {
			$staff = wc_clean( wp_unslash( $_GET['staff'] ) );
		}
		// phpcs:enable

		$args['staff'] = $staff;

		#error_log( var_export( $args, true ) );

		return $args;
	}

	/**
	 * Appointable Products filtered under the Analytics page.
	 *
	 * @param array $args Query arguments.
	 *
	 * @return array Updated query arguments.
	 */
	public function analytics_products_query_args( $args ) {
		if ( 'appointments' === wc_clean( wp_unslash( $_GET['filter'] ?? '' ) ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Recommended
			if ( isset( $args['meta_query'] ) ) {
				$args['meta_query'][] = array(
					'relation' => 'AND',
					array(
						'key'     => '_wc_appointment_availability',
						'compare' => 'EXISTS',
					),
					array(
						'key'     => '_regular_price',
						'compare' => 'NOT EXISTS',
					),
				);
			} else {
				$args['meta_query'] = array(
					'relation' => 'AND',
					array(
						'key'     => '_wc_appointment_availability',
						'compare' => 'EXISTS',
					),
					array(
						'key'     => '_regular_price',
						'compare' => 'NOT EXISTS',
					),
				);
			}
		}

		#error_log( var_export( $args, true ) );

		return $args;
	}

	/**
	 * Appointable Products STAFF filtered via SELECT clause(s).
	 *
	 * @param array $clauses The existing clauses.
	 * @param array $context The context of the clause.
	 *
	 * @return array Updated clauses.
	 */
	public function analytics_clauses_select_staff( $clauses, $context ) {
		global $wpdb;

		if ( isset( $_GET['staff'] ) ) {
			$clauses[] = ", p.ID AS p FROM {$wpdb->posts}";
		}

		#error_log( var_export( $clauses, true ) );

		return $clauses;
	}

	/**
	 * Appointable Products filtered via JOIN clause(s).
	 *
	 * @param array $clauses The existing clauses.
	 * @param array $context The context of the clause.
	 *
	 * @return array Updated clauses.
	 */
	public function analytics_clauses_join( $clauses, $context ) {
		global $wpdb;

		if ( 'appointments' === wc_clean( wp_unslash( $_GET['filter'] ?? '' ) ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Recommended
			$clauses[] = " JOIN {$wpdb->prefix}term_relationships ON {$wpdb->prefix}wc_order_product_lookup.product_id = {$wpdb->prefix}term_relationships.object_id";
			$clauses[] = " JOIN {$wpdb->prefix}term_taxonomy ON {$wpdb->prefix}term_taxonomy.term_taxonomy_id = {$wpdb->prefix}term_relationships.term_taxonomy_id";
			$clauses[] = " JOIN {$wpdb->prefix}terms ON {$wpdb->prefix}term_taxonomy.term_id = {$wpdb->prefix}terms.term_id";
		}

		#error_log( var_export( $clauses, true ) );

		return $clauses;
	}

	/**
	 * Appointable Products STAFF filtered via JOIN clause(s).
	 *
	 * @param array $clauses an array of JOIN query strings.
	 * @param array $context The context of the clause.

	 * @return array Updated clauses.
	 */
	public function analytics_clauses_join_staff( $clauses, $context ) {
		global $wpdb;

		/*
		if ( isset( $request['date_to'] ) ) {
			$meta_query[] = [
				'key'     => '_appointment_end',
				'value'   => esc_sql( date( 'YmdHis', strtotime( $request['date_to'] ) ) ),
				'compare' => '<',
			];
		}

		if ( $meta_query && ! empty( $meta_query ) ) {
			$args['meta_query'] = [
				'relation' => 'AND',
				$meta_query,
			];
		}
		*/

		/*
		if ( ! empty( $filters['staff_id'] ) ) {
			$meta_keys[]   = '_appointment_staff_id';
			$query_where[] = "_appointment_staff_id.meta_value IN ('" . implode( "','", array_map( 'esc_sql', $filters['staff_id'] ) ) . "')";
		}
		$query_select = "SELECT p.ID FROM {$wpdb->posts} p";
		$meta_keys    = array_unique( $meta_keys );
		$query_where  = implode( ' AND ', $query_where );

		foreach ( $meta_keys as $index => $meta_key ) {
			$key           = esc_sql( $meta_key );
			$query_select .= " LEFT JOIN {$wpdb->postmeta} {$key} ON p.ID = {$key}.post_id AND {$key}.meta_key = '{$key}'";
		}

		$data = array_filter(
			wp_parse_id_list(
				$wpdb->get_col(
					"{$query_select} {$query_where} {$query_order} {$query_limit};"
				)
			)
		);
		*/

		/*
		if ( 'appointments' === wc_clean( wp_unslash( $_GET['filter'] ?? '' ) ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Recommended
			$clauses[] = " JOIN {$wpdb->prefix}term_relationships ON {$wpdb->prefix}wc_order_product_lookup.product_id = {$wpdb->prefix}term_relationships.object_id";
			$clauses[] = " JOIN {$wpdb->prefix}term_taxonomy ON {$wpdb->prefix}term_taxonomy.term_taxonomy_id = {$wpdb->prefix}term_relationships.term_taxonomy_id";
			$clauses[] = " JOIN {$wpdb->prefix}terms ON {$wpdb->prefix}term_taxonomy.term_id = {$wpdb->prefix}terms.term_id";
		}
		*/

		if ( isset( $_GET['staff'] ) ) {
			$meta_key  = esc_sql( '_appointment_staff_id' );
			$clauses[] = " JOIN {$wpdb->postmeta} {$meta_key} ON p.ID = {$meta_key}.post_id AND {$meta_key}.meta_key = '{$meta_key}'";
		}

		#error_log( var_export( $clauses, true ) );

		return $clauses;
	}

	/**
	 * Appointable Products filtered via WHERE clause(s).
	 *
	 * @param array $clauses The existing clauses.
	 * @param array $context The context of the clause.
	 *
	 * @return array Updated clauses.
	 */
	public function analytics_clauses_where( $clauses, $context ) {
		global $wpdb;

		if ( 'appointments' === wc_clean( wp_unslash( $_GET['filter'] ?? '' ) ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Recommended
			$clauses[] = " AND {$wpdb->prefix}term_taxonomy.taxonomy = 'product_type'";
			$clauses[] = " AND {$wpdb->prefix}terms.slug = 'appointment'";
		}

		#error_log( var_export( $clauses, true ) );

		return $clauses;
	}

	/**
	 * Appointable Products STAFF filtered via WHERE clause(s).
	 *
	 * @param array $clauses The existing clauses.
	 * @param array $context The context of the clause.
	 *
	 * @return array Updated clauses.
	 */
	public function analytics_clauses_where_staff( $clauses, $context ) {
		global $wpdb;

		if ( isset( $_GET['staff'] ) ) {
			$meta_key   = esc_sql( '_appointment_staff_id' );
			$staff      = wc_clean( wp_unslash( $_GET['staff'] ) );
			$clauses[]  = " AND {$meta_key}.meta_value IN ('" . implode( "','", array_map( 'esc_sql', $staff ) ) . "')";
		}

		#error_log( var_export( $clauses, true ) );

		return $clauses;
	}
}

new WC_Appointments_Admin_Analytics();
