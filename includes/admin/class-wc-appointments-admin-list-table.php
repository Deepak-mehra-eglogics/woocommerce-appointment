<?php
/**
 * Admin functions for the appointments post type
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

if ( class_exists( 'WC_Appointments_Admin_List_Table', false ) ) {
	return;
}

if ( ! class_exists( 'WC_Admin_List_Table', false ) ) {
	include_once WC_ABSPATH . 'includes/admin/list-tables/abstract-class-wc-admin-list-table.php';
}

/**
 * WC_Appointments_Admin_List_Table Class.
 */
class WC_Appointments_Admin_List_Table extends WC_Admin_List_Table {

	/**
	 * Post type.
	 *
	 * @var string
	 */
	protected $list_table_type = 'wc_appointment';

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct();
		add_filter( 'disable_months_dropdown', '__return_true' );
		add_filter( 'parse_query', [ $this, 'appointment_filters_query' ] );
		add_filter( 'parse_query', [ $this, 'search_custom_fields' ] );
		add_filter( 'get_search_query', [ $this, 'search_label' ] );
		add_filter( 'views_edit-wc_appointment', [ $this, 'appointment_views' ] );
		add_action( 'admin_footer', [ $this, 'bulk_admin_footer' ], 10 );
		add_action( 'admin_notices', [ $this, 'bulk_admin_notices' ] );
	}

	/**
	 * Render blank state.
	 */
	protected function render_blank_state() {
		echo '<div class="woocommerce-BlankState">';

		printf(
			'<h2 class="woocommerce-BlankState-message">%1$s</h2>',
			esc_html__( 'Ready to start accepting appointments?', 'woocommerce-appointments' )
		);

		echo '<div class="woocommerce-BlankState-buttons">';

		printf(
			'<a class="woocommerce-BlankState-cta button-primary button" href="%1$s">%2$s</a>',
			esc_url( admin_url( 'edit.php?post_type=wc_appointment&page=add_appointment' ) ),
			esc_html__( 'Add New Appointment', 'woocommerce-appointments' )
		);
		printf(
			'<a class="woocommerce-BlankState-cta button" href="%1$s">%2$s</a>',
			esc_url( admin_url( 'post-new.php?post_type=product&appointable_product=1' ) ),
			esc_html__( 'Add Appointable Product', 'woocommerce-appointments' )
		);

		echo '</div>';

		echo '</div>';
	}

	/**
	 * Define primary column.
	 *
	 * @return string
	 */
	protected function get_primary_column() {
		return 'appointment_id';
	}

	/**
	 * Get row actions to show in the list table.
	 *
	 * @param array   $actions Array of actions.
	 * @param WP_Post $post Current post object.
	 * @return array
	 */
	protected function get_row_actions( $actions, $post ) {
		// Remove quick edit.
		unset( $actions['inline hide-if-no-js'] );
		return array_merge(
			[
				/* translators: %d: product ID. */
				'id' => sprintf( __( 'ID: %d', 'woocommerce-appointments' ), $post->ID ),
			],
			$actions
		);
	}

	/**
	 * Define hidden columns.
	 *
	 * @return array
	 */
	protected function define_hidden_columns() {
		return [
			'addons',
		];
	}

	/**
	 * Define which columns are sortable.
	 *
	 * @param array $columns Existing columns.
	 * @return array
	 */
	public function define_sortable_columns( $columns ) {
		$custom = [
			'appointment_id'   => 'appointment_id',
			'appointment_when' => 'appointment_when',
		];
		return wp_parse_args( $custom, $columns );
	}

	/**
	 * Define which columns to show on this screen.
	 *
	 * @param array $columns Existing columns.
	 * @return array
	 */
	public function define_columns( $columns ) {
		if ( empty( $columns ) && ! is_array( $columns ) ) {
			$columns = [];
		}

		unset( $columns['title'], $columns['comments'], $columns['date'] );

		$show_columns                        = [];
		$show_columns['cb']                  = '<input type="checkbox" />';
		$show_columns['appointment_id']      = __( 'Appointment', 'woocommerce-appointments' );
		$show_columns['appointment_when']    = __( 'When', 'woocommerce-appointments' );
		$show_columns['scheduled_product']   = __( 'Product', 'woocommerce-appointments' );
		$show_columns['addons']              = __( 'Add-ons', 'woocommerce-appointments' );
		$show_columns['scheduled_staff']     = __( 'Staff', 'woocommerce-appointments' );
		$show_columns['appointment_actions'] = __( 'Actions', 'woocommerce-appointments' );

		return array_merge( $show_columns, $columns );
	}

	/**
	 * Pre-fetch any data for the row each column has access to it. the_product global is there for bw compat.
	 *
	 * @param int $post_id Post ID being shown.
	 */
	protected function prepare_row_data( $post_id ) {
		global $the_appointment;

		if ( empty( $this->object ) || $this->object->get_id() !== $post_id ) {
			$the_appointment = get_wc_appointment( $post_id );
			$this->object    = $the_appointment;
		}
	}

	/**
	 * Render columm: appointment_id.
	 */
	protected function render_appointment_id_column() {
		$customer = $this->object->get_order() ? $this->object->get_customer( $this->object->get_order() ) : $this->object->get_customer();
		$buyer    = $customer->full_name;

		/**
		 * Filter buyer name in list table appointments.
		 *
		 * @since 4.7.6
		 * @param string         $buyer       Buyer name.
		 * @param WC_Appointment $appointment Appointment data.
		 */
		$buyer = apply_filters( 'woocommerce_appointments_admin_buyer_name', $buyer, $this->object );

		printf(
			'<a href="%s"><strong>%s</strong></a> - <mark class="appointment-status status-%s"><span>%s</span></mark>',
			esc_url( admin_url( 'post.php?post=' . $this->object->get_id() . '&action=edit' ) ),
			esc_attr( $buyer ),
			esc_attr( $this->object->get_status() ),
			esc_attr( wc_appointments_get_status_label( $this->object->get_status() ) )
		);
		// Order.
		if ( $this->object->get_order() ) {
			printf(
				/* translators: %1$s: Order link, %2$d: Order ID, %3$s: Order status */
				'<br /><a href="%1$s">' . esc_html__( 'Order #%2$s', 'woocommerce-appointments' ) . '</a> - %3$s',
				esc_url( $this->object->get_order()->get_edit_order_url() ),
				esc_attr( $this->object->get_order()->get_order_number() ),
				esc_attr( wc_get_order_status_name( $this->object->get_order()->get_status() ) )
			);
		}
	}

	/**
	 * Render columm: appointment_when.
	 */
	protected function render_appointment_when_column() {
		// Start date/time.
		esc_attr_e( $this->object->get_start_date() );
		// Duration.
		printf(
			'<span class="description">%1$s</span>',
			esc_attr( $this->object->get_duration() )
		);
	}

	/**
	 * Render columm: scheduled_product.
	 */
	protected function render_scheduled_product_column() {
		if ( $this->object->get_product() ) {
			$saved_qty   = $this->object->get_qty();
			$product_qty = $saved_qty ? esc_attr( $saved_qty ) : 1;
			printf(
				'<a href="%1$s">%2$s</a> <div class="view"><small class="times">×</small> %3$d</div>',
				esc_url( admin_url( 'post.php?post=' . esc_attr( $this->object->get_product()->get_id() ) . '&action=edit' ) ),
				esc_attr( $this->object->get_product()->get_title() ),
				esc_attr( $product_qty )
			);
		} else {
			echo '–';
		}
	}

	/**
	 * Render columm: addons.
	 */
	protected function render_addons_column() {
		$addons = $this->object->get_addons();
		if ( $addons ) {
			echo $addons; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} else {
			echo '–';
		}
	}

	/**
	 * Render columm: scheduled_staff.
	 */
	protected function render_scheduled_staff_column() {
		$staff = $this->object->get_staff_members( true, true );
		if ( $staff ) {
			echo $staff; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} else {
			echo '–';
		}
	}

	/**
	 * Render columm: appointment_actions.
	 */
	protected function render_appointment_actions_column() {
		echo '<p>';
		$actions = [
			'view' => [
				'url'    => admin_url( 'post.php?post=' . $this->object->get_id() . '&action=edit' ),
				'name'   => __( 'View', 'woocommerce-appointments' ),
				'action' => 'view',
			],
		];

		if ( $this->object->has_status( [ 'pending-confirmation' ] ) ) {
			$actions['confirm'] = [
				'url'    => wp_nonce_url(
					admin_url( 'admin-ajax.php?action=wc-appointment-confirm&appointment_id=' . $this->object->get_id() ),
					'wc-appointment-confirm'
				),
				'name'   => __( 'Confirm', 'woocommerce-appointments' ),
				'action' => 'confirm',
			];
		}

		$actions = apply_filters( 'woocommerce_admin_appointment_actions', $actions, $this->object );

		foreach ( $actions as $action ) {
			printf(
				'<a class="button tips %s" href="%s" data-tip="%s">%s</a>',
				esc_attr( $action['action'] ),
				esc_url( $action['url'] ),
				wc_sanitize_tooltip( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					__( $action['name'] )
				),
				esc_attr( $action['name'] )
			);
		}
		echo '</p>';
	}

	/**
	 * Handle any custom filters.
	 *
	 * @param array $query_vars Query vars.
	 * @return array
	 */
	protected function query_filters( $query_vars ) {
		// Custom order by arguments.
		if ( isset( $query_vars['orderby'] ) ) {
			$orderby = strtolower( $query_vars['orderby'] );
			$order   = isset( $query_vars['order'] ) ? strtoupper( $query_vars['order'] ) : 'DESC';

			if ( 'ID' === $orderby ) {
				$query_vars = array_merge(
					$query_vars,
					[
						'orderby' => 'ID',
					]
				);
			}

			if ( 'appointment_when' === $orderby ) {
				// @codingStandardsIgnoreStart
				$query_vars = array_merge(
					$query_vars,
					[
						'meta_key' => '_appointment_start',
						'orderby'  => 'meta_value_num',
					]
				);
				// @codingStandardsIgnoreEnd
			}
		}

		return $query_vars;
	}

	/**
	 * Render any custom filters and search inputs for the list table.
	 */
	protected function render_filters() {
		$filters = apply_filters(
			'woocommerce_appointments_admin_list_table_filters',
			[
				'appointment_dates'   => [ $this, 'render_appointment_dates_filter' ],
				'appointment_product' => [ $this, 'render_appointment_product_filter' ],
				'appointment_staff'   => [ $this, 'render_appointment_staff_filter' ],
			]
		);

		ob_start();
		foreach ( $filters as $filter_callback ) {
			call_user_func( $filter_callback );
		}
		$output = ob_get_clean();

		echo apply_filters( 'woocommerce_appointment_filters', $output ); // WPCS: XSS ok.
	}

	/**
	 * Render the appointment from/end dates filter for the list table.
	 *
	 * @since 4.7.0
	 */
	protected function render_appointment_dates_filter() {
		$date_from           = isset( $_REQUEST['date_from'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['date_from'] ) ) : ''; // phpcs:ignore  WordPress.Security.NonceVerification.NoNonceVerification
		$date_from_formatted = $date_from ? date( 'Y-m-d', strtotime( $date_from ) ) : '';
		$date_to             = isset( $_REQUEST['date_to'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['date_to'] ) ) : ''; // phpcs:ignore  WordPress.Security.NonceVerification.NoNonceVerification
		$date_to_formatted   = $date_to ? date( 'Y-m-d', strtotime( $date_to ) ) : '';
		?>
		<div class="alignleft">
			<div class="date_filter">
				<input type="search" name="date_from" class="date_from date-picker" value="<?php esc_attr_e( $date_from_formatted ); ?>" placeholder="<?php esc_html_e( 'Start Date', 'woocommerce-appointments' ); ?>" autocomplete="off" />
			</div>
			<div class="date_filter">
				<input type="search" name="date_to" class="date_to date-picker" value="<?php esc_attr_e( $date_to_formatted ); ?>" placeholder="<?php esc_html_e( 'End Date', 'woocommerce-appointments' ); ?>" autocomplete="off" />
			</div>
		</div>
		<?php
	}

	/**
	 * Render the appointment product filter for the list table.
	 *
	 * @since 4.7.0
	 */
	protected function render_appointment_product_filter() {
		$product_name = '';
		$product_id   = '';

		if ( ! empty( $_REQUEST['filter_product'] ) ) { // phpcs:disable  WordPress.Security.NonceVerification.NoNonceVerification
			$product_id   = absint( $_REQUEST['filter_product'] ); // WPCS: input var ok, sanitization ok.
			$product      = get_wc_product_appointment( $product_id );
			$product_name = $product ? $product->get_title() : '';
		}
		?>
		<div class="alignleft">
			<select class="wc-product-search" name="filter_product" style="width: 200px;" data-allow_clear="true" data-placeholder="<?php esc_html_e( 'Filter by product', 'woocommerce-appointments' ); ?>" data-action="woocommerce_json_search_appointable_products">
				<option value="<?php esc_attr_e( $product_id ); ?>" selected="selected"><?php esc_html_e( $product_name ); ?></option>
			</select>
		</div>
		<?php
	}

	/**
	 * Render the appointment staff filter for the list table.
	 *
	 * @since 4.7.0
	 */
	protected function render_appointment_staff_filter() {
		if ( ! current_user_can( 'manage_others_appointments' ) ) {
			return;
		}
		$staff_array = [];

		foreach ( WC_Appointments_Admin::get_appointment_staff() as $staff_member ) {
			$staff_array[ $staff_member->ID ] = $staff_member->display_name;
		}

		if ( $staff_array ) {
			$requested_staff = isset( $_REQUEST['filter_staff'] ) ? wc_clean( wp_unslash( $_REQUEST['filter_staff'] ) ) : ''; // phpcs:ignore  WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			?>
			<select name="filter_staff">
				<option value=""><?php esc_html_e( 'All staff', 'woocommerce-appointments' ); ?></option>
				<option<?php selected( '–', $requested_staff ); ?> value="–">–</option>
				<?php foreach ( $staff_array as $filter_id => $filter ) { ?>
					<option<?php selected( $filter_id, $requested_staff ); ?> value="<?php esc_attr_e( absint( $filter_id ) ); ?>"><?php esc_html_e( $filter ); ?></option>
				<?php } ?>
			</select>
			<?php
		}
	}

	/**
	 * Filter the products in admin based on options
	 *
	 * @param mixed $query
	 */
	public function appointment_filters_query( $query ) {
		global $typenow, $wp_query;

		// Get current screen.
		$screen    = function_exists( 'get_current_screen' ) ? get_current_screen() : '';
		$screen_id = $screen ? $screen->id : '';

		if ( $this->list_table_type === $typenow ) {
			$meta_query = [];

			// Only show appointments appliable to current staff member.
			if ( ! current_user_can( 'manage_others_appointments' ) && 'edit-wc_appointment' === $screen_id ) {
				$meta_query[] = [
					'key'     => '_appointment_staff_id',
					'value'   => get_current_user_id(),
					'compare' => 'IN',
				];
			}

			// Date filters.
			if ( ! empty( $_REQUEST['date_from'] ) && empty( $query->query_vars['suppress_filters'] ) ) {
				$meta_query[] = [
					'key'     => '_appointment_start',
					'value'   => esc_sql( date( 'YmdHis', strtotime( wc_clean( wp_unslash( $_REQUEST['date_from'] ) ) ) ) ), // phpcs:ignore  WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
					'compare' => '>=',
				];
			}
			if ( ! empty( $_REQUEST['date_to'] ) && empty( $query->query_vars['suppress_filters'] ) ) {
				$meta_query[] = [
					'key'     => '_appointment_end',
					'value'   => esc_sql( date( 'YmdHis', strtotime( wc_clean( wp_unslash( $_REQUEST['date_to'] ) ) ) ) ), // phpcs:ignore  WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
					'compare' => '<',
				];
			}

			// Product and Staff filters.
			if ( ! empty( $_REQUEST['filter_product'] ) && empty( $query->query_vars['suppress_filters'] ) ) {
				$meta_query[] = [
					'key'   => '_appointment_product_id',
					'value' => absint( $_REQUEST['filter_product'] ),
				];
			}
			if ( ! empty( $_REQUEST['filter_staff'] ) && empty( $query->query_vars['suppress_filters'] ) ) {
				if ( '–' === $_REQUEST['filter_staff'] ) {
					$meta_query[] = [
						'key'     => '_appointment_staff_id',
						'compare' => 'NOT EXISTS',
					];
				} else {
					$meta_query[] = [
						'key'   => '_appointment_staff_id',
						'value' => absint( $_REQUEST['filter_staff'] ),
					];
				}
			}

			if ( $meta_query && ! empty( $meta_query ) ) {
				$query->query_vars['meta_query'] = [
					'relation' => 'AND',
					$meta_query,
				];
			}

			#echo '<pre>' . var_export( $meta_query, true ) . '</pre>';
		}
	}

	/**
	 * Search custom fields
	 *
	 * @param mixed $wp
     *
     * @return void
	 */
	public function search_custom_fields( $wp ) {
		global $pagenow, $wpdb;

		if ( 'edit.php' !== $pagenow || empty( $wp->query_vars['s'] ) || $this->list_table_type !== $wp->query_vars['post_type'] ) {
			return $wp;
		}

		$appointment_ids = [];
		$term            = isset( $_GET['s'] ) ? wc_clean( wp_unslash( $_GET['s'] ) ) : []; // WPCS: input var ok, sanitization ok.

		if ( is_numeric( $term ) ) {
			$appointment_ids[] = $term;
		}

		$order_ids       = wc_order_search( $term );
		$appointment_ids = array_merge(
			$appointment_ids,
			$order_ids ? WC_Appointment_Data_Store::get_appointment_ids_from_order_id( $order_ids ) : [ 0 ],
			wc_appointment_search( $term )
		);

		$wp->query_vars['s']                  = false;
		$wp->query_vars['post__in']           = $appointment_ids;
		$wp->query_vars['appointment_search'] = true;
	}

	/**
	 * Change views on the edit appointment screen.
	 *
	 * @param  array $views Array of views.
	 * @return array
	 */
	public function appointment_views( $views ) {
		// Appointments do not have authors.
		unset( $views['mine'] );

		// Appointment status sorting links for staff members
		// who cannot manage other appointments.
		if ( ! current_user_can( 'manage_others_appointments' ) ) {
			$views     = []; // empty default views
			$edit_link = '<a href="edit.php?post_type=wc_appointment&%s" %s>%s</a>';
			$class     = '';
			$staff_id  = get_current_user_id();
			if ( ! isset( $_REQUEST['post_status'] ) ) {
				$class = 'class="current"';
			}
			$views['all'] = sprintf( $edit_link, '', $class, __( 'All' ) );
			// get all appointments statuses there are
			$appointments_statuses = get_wc_appointment_statuses( 'user', true );
			foreach ( $appointments_statuses as $appointment_status => $appointment_status_name ) {
				if ( isset( $_REQUEST['post_status'] ) && $appointment_status === $_REQUEST['post_status'] ) {
					$class = 'class="current"';
				} else {
					$class = '';
				}
				$views[ $appointment_status ] = sprintf( $edit_link, 'post_status=' . $appointment_status, $class, $appointment_status_name );
			}
		}

		return $views;
	}

	/**
	 * Change the label when searching appointments.
	 *
	 * @param string $query Search Query.
	 * @return string
	 */
	public function search_label( $query ) {
		global $pagenow, $typenow;

		if ( 'edit.php' !== $pagenow || 'wc_appointment' !== $typenow || ! get_query_var( 'appointment_search' ) || ! isset( $_GET['s'] ) ) { // WPCS: input var ok.
			return $query;
		}

		return wc_clean( wp_unslash( $_GET['s'] ) ); // WPCS: input var ok, sanitization ok.
	}

	/**
	 * Remove edit from the bulk actions.
	 *
	 * @param mixed $actions
	 * @return array
	 */
	public function define_bulk_actions( $actions ) {
		if ( isset( $actions['edit'] ) ) {
			unset( $actions['edit'] );
		}

		return $actions;
	}

	/**
	 * Handle bulk actions.
	 *
	 * @param  string $redirect_to URL to redirect to.
	 * @param  string $action      Action name.
	 * @param  array  $ids         List of ids.
     *
	 * @return string|void
	 */
	public function handle_bulk_actions( $redirect_to, $action, $ids ) {
		switch ( $action ) {
			case 'confirm_appointments':
				$new_status    = 'confirmed';
				$report_action = 'appointments_confirmed';
				break;
			case 'unconfirm_appointments':
				$new_status    = 'pending-confirmation';
				$report_action = 'appointments_unconfirmed';
				break;
			case 'mark_paid_appointments':
				$new_status    = 'paid';
				$report_action = 'appointments_marked_paid';
				break;
			case 'mark_unpaid_appointments':
				$new_status    = 'unpaid';
				$report_action = 'appointments_marked_unpaid';
				break;
			case 'cancel_appointments':
				$new_status    = 'cancelled';
				$report_action = 'appointments_cancelled';
				break;
			default:
				return;
		}

		$changed = 0;

		foreach ( $ids as $id ) {
			$appointment = get_wc_appointment( $id );
			if ( $new_status !== $appointment->get_status() ) {
				$appointment->update_status( $new_status );
			}
			$changed++;
		}

		$sendback = add_query_arg(
			[
				'post_type'    => $this->list_table_type,
				$report_action => true,
				'changed'      => $changed,
				'ids'          => join( ',', $ids ),
			],
			''
		);

		return esc_url_raw( $sendback );
	}

	/**
	 * Add extra bulk action options to mark orders as complete or processing
	 *
	 * Using Javascript until WordPress core fixes: http://core.trac.wordpress.org/ticket/16031
	 */
	public function bulk_admin_footer() {
		global $post_type, $pagenow;

		// Bail out if not on wc_appointment list page.
		if ( 'edit.php' !== $pagenow || 'wc_appointment' !== $post_type ) { // WPCS: input var ok, CSRF ok.
			return;
		}

		wp_enqueue_script( 'wc_appointments_writepanel_js' );
		?>
		<script type="text/javascript">
			jQuery( document ).ready( function ( $ ) {
				$( '<option value="confirm_appointments"><?php esc_html_e( 'Confirm appointments', 'woocommerce-appointments' ); ?></option>' ).appendTo( 'select[name="action"], select[name="action2"]' );
				$( '<option value="unconfirm_appointments"><?php esc_html_e( 'Unconfirm appointments', 'woocommerce-appointments' ); ?></option>' ).appendTo( 'select[name="action"], select[name="action2"]' );
				$( '<option value="cancel_appointments"><?php esc_html_e( 'Cancel appointments', 'woocommerce-appointments' ); ?></option>' ).appendTo( 'select[name="action"], select[name="action2"]' );
				$( '<option value="mark_paid_appointments"><?php esc_html_e( 'Mark appointments as paid', 'woocommerce-appointments' ); ?></option>' ).appendTo( 'select[name="action"], select[name="action2"]' );
				$( '<option value="mark_unpaid_appointments"><?php esc_html_e( 'Mark appointments as unpaid', 'woocommerce-appointments' ); ?></option>' ).appendTo( 'select[name="action"], select[name="action2"]' );
			});
		</script>
		<?php
	}

	/**
	 * Show confirmation message that order status changed for number of orders
	 */
	public function bulk_admin_notices() {
		global $post_type, $pagenow;

		// Bail out if not on wc_appointment list page.
		if ( 'edit.php' !== $pagenow || 'wc_appointment' !== $post_type ) { // WPCS: input var ok, CSRF ok.
			return;
		}

		if ( isset( $_REQUEST['appointments_confirmed'] )
			|| isset( $_REQUEST['appointments_marked_paid'] )
			|| isset( $_REQUEST['appointments_marked_unpaid'] )
			|| isset( $_REQUEST['appointments_unconfirmed'] )
			|| isset( $_REQUEST['appointments_cancelled'] )
		) {
			$number = isset( $_REQUEST['changed'] ) ? absint( $_REQUEST['changed'] ) : 0;

			$message = sprintf(
				/* translators: %s: number of appointment statuses change */
				_n(
					'Appointment status changed.',
					'%s appointment statuses changed.',
					$number,
					'woocommerce-appointments'
				),
				number_format_i18n( $number )
			);
			echo '<div class="updated"><p>' . $message . '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}
}
