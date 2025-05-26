<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

class WC_Appointment_Meta_Box_Data {

	/**
	 * Meta box ID.
	 *
	 * @var string
	 */
	public $id;

	/**
	 * Meta box title.
	 *
	 * @var string
	 */
	public $title;

	/**
	 * Meta box context.
	 *
	 * @var string
	 */
	public $context;

	/**
	 * Meta box priority.
	 *
	 * @var string
	 */
	public $priority;

	/**
	 * Meta box post types.
	 * @var array
	 */
	public $post_types;

	/**
	 * Are meta boxes saved?
	 *
	 * @var boolean
	 */
	private static $saved_meta_box = false;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id         = 'woocommerce-appointment-data';
		$this->title      = __( 'Appointment Details', 'woocommerce-appointments' );
		$this->context    = 'normal';
		$this->priority   = 'high';
		$this->post_types = [ 'wc_appointment' ];

		add_action( 'save_post', [ $this, 'meta_box_save' ], 10, 2 );
	}

	/**
	 * Check data and output warnings.
	 */
	private function sanity_check_notices( $appointment, $product ) {
		if ( $appointment->get_start() && $appointment->get_start() > strtotime( '+ 2 year', current_time( 'timestamp' ) ) ) {
			echo '<div class="notice notice-warning"><p>' . esc_attr__( 'This appointment is scheduled over 2 years into the future. Please ensure this is correct.', 'woocommerce-appointments' ) . '</p></div>';
		}

		if ( $product && is_callable( [ $product, 'get_max_date_a' ] ) ) {
			$max      = $product->get_max_date_a();
			$max_date = strtotime( "+{$max['value']} {$max['unit']}", current_time( 'timestamp' ) );
			if ( $appointment->get_start() > $max_date || $appointment->get_end() > $max_date ) {
				/* translators: %s: maximum appointable date */
				echo '<div class="notice notice-warning"><p>' . sprintf( esc_attr__( 'This appointment is scheduled over the products allowed max appointment date (%s). Please ensure this is correct.', 'woocommerce-appointments' ), esc_attr( date_i18n( wc_appointments_date_format(), $max_date ) ) ) . '</p></div>';
			}
		}

		if ( $appointment->get_start() && $appointment->get_end() && $appointment->get_start() > $appointment->get_end() ) {
			echo '<div class="error"><p>' . esc_attr__( 'This appointment has an end date set before the start date.', 'woocommerce-appointments' ) . '</p></div>';
		}

		if ( ! $product ) {
			echo '<div class="error"><p>' . esc_attr__( 'It appears the appointment product associated with this appointment has been removed.', 'woocommerce-appointments' ) . '</p></div>';
			return;
		}

		if ( $product && is_callable( [ $product, 'is_skeleton' ] ) && $product->is_skeleton() ) {
			/* translators: %s: product type */
			echo '<div class="error"><p>' . sprintf( esc_attr__( 'This appointment is missing a required add-on (product type: %s). Some information is shown below but might be incomplete. Please install the missing add-on through the plugins screen.', 'woocommerce-appointments' ), esc_attr( $product->get_type() ) ) . '</p></div>';
		}
	}

	public function meta_box_inner( $post ) {
		global $appointment;

		// Nonce.
		wp_nonce_field( 'wc_appointments_details_meta_box', 'wc_appointments_details_meta_box_nonce' );

		// Scripts.
		wp_enqueue_script( 'wc-enhanced-select' );
		wp_enqueue_script( 'jquery-ui-datepicker' );

		if ( ! is_a( $appointment, 'WC_Appointment' ) || $appointment->get_id() !== $post->ID ) {
			$appointment = get_wc_appointment( $post->ID );
		}

		// Sanity check saved dates
		$order           = $appointment->get_order();
		$product_id      = $appointment->get_product_id( 'edit' );
		$staff_ids       = $appointment->get_staff_ids( 'edit' );
		$staff_ids       = ! is_array( $staff_ids ) ? [ $staff_ids ] : $staff_ids;
		$product         = wc_get_product( $product_id );
		$product_name    = $product ? $product->get_title() . ' (#' . $product->get_id() . ')' : '';
		$statuses        = array_unique(
			array_merge(
				get_wc_appointment_statuses( null, true ),
				get_wc_appointment_statuses( 'user', true ),
				get_wc_appointment_statuses( 'cancel', true )
			)
		);
		$customer        = $appointment->get_customer();
		$customer_string = sprintf(
			/* translators: %1$s: full name, %2$s: user ID, %3$s: email */
			esc_html__( '%1$s (#%2$s &ndash; %3$s)', 'woocommerce-appointments' ),
			$customer->full_name,
			$customer->user_id,
			$customer->email
		);
		$this->sanity_check_notices( $appointment, $product );
		?>
		<div class="panel-wrap woocommerce">
			<div id="appointment_data" class="panel">

				<div class="appointment_data_container appointment_data_general">
					<div class="appointment_data_column data_column_wide">
						<h2>
						<?php
						printf(
							/* translators: %s: appointment ID */
							esc_attr__( 'Appointment #%d', 'woocommerce-appointments' ),
							esc_attr( $post->ID )
						);
						// Calendar view.
						printf(
							'<a href="%1$s" class="view-on-calendar" title="%2$s"><span>☶</span></a>',
							esc_url(
								admin_url(
									'edit.php?post_type=wc_appointment&page=appointment_calendar&view=day&calendar_day=' . $appointment->get_start_date( 'Y-m-d', '' )
								)
							),
							esc_attr__( 'View on calendar', 'woocommerce-appointments' )
						);
						?>
						</h2>
						<p class="appointment-order-number">
						<?php
						if ( $order ) {
							printf(
								/* translators: %s: href to order ID */
								' ' . esc_attr__( 'Linked to Order %s', 'woocommerce-appointments' ),
								'<a href="' . esc_url( $order->get_edit_order_url() ) . '">#' . esc_html( $order->get_order_number() ) . '</a>'
							);
						}

						if ( $product && is_callable( [ $product, 'is_appointments_addon' ] ) && $product->is_appointments_addon() ) {
							printf(
								/* translators: %s: appointments addon title */
								' ' . esc_attr__( 'Appointment type: %s', 'woocommerce-appointments' ),
								esc_attr( $product->appointments_addon_title() )
							);
						}
						?>
						</p>
						<?php
						#print '<pre>'; print_r( $appointment ); print '</pre>';
						?>
					</div>
					<div class="clear"></div>
					<div class="appointment_data_column">
						<h4><?php esc_html_e( 'General Details', 'woocommerce-appointments' ); ?></h4>

						<p class="form-field form-field-wide customer-form-field">
							<label for="_appointment_customer_id"><?php esc_html_e( 'Customer:', 'woocommerce-appointments' ); ?></label>
							<select name="_appointment_customer_id" id="_appointment_customer_id" class="wc-customer-search" data-placeholder="<?php esc_attr_e( $customer->full_name ); ?>" data-allow_clear="true">
								<?php if ( $customer->user_id ) : ?>
									<option selected="selected" value="<?php esc_attr_e( $customer->user_id ); ?>"><?php esc_attr_e( $customer_string ); ?></option>
								<?php endif; ?>
							</select>
						</p>

						<?php
							$customer_statuses = array_unique( get_wc_appointment_statuses( 'customer', true ) );
							// Remove Expected status, when appointment is past current time.
							if ( $appointment->get_start() < current_time( 'timestamp' ) && $appointment->get_end() < current_time( 'timestamp' ) ) {
								unset( $customer_statuses['expected'] );
							}
						?>

						<p class="form-field form-field-wide customer-status-field">
							<label for="_appointment_customer_status"><?php esc_html_e( 'Customer Status:', 'woocommerce-appointments' ); ?></label>
							<select id="_appointment_customer_status" name="_appointment_customer_status" class="wc-enhanced-select">
								<?php
								foreach ( $customer_statuses as $key => $value ) {
									echo '<option value="' . esc_attr( $key ) . '" ' . selected( $key, $appointment->get_customer_status(), false ) . '>' . esc_html__( $value, 'woocommerce-appointments' ) . '</option>';
								}
								?>
							</select>
						</p>

						<?php
							$statuses = array_unique( array_merge( get_wc_appointment_statuses( null, true ), get_wc_appointment_statuses( 'user', true ), get_wc_appointment_statuses( 'cancel', true ) ) );
						?>

						<p class="form-field form-field-wide appointment-status-field">
							<label for="_appointment_status"><?php esc_html_e( 'Appointment Status:', 'woocommerce-appointments' ); ?></label>
							<select id="_appointment_status" name="_appointment_status" class="wc-enhanced-select">
								<?php
								foreach ( $statuses as $key => $value ) {
									echo '<option value="' . esc_attr( $key ) . '" ' . selected( $key, $appointment->get_status(), false ) . '>' . esc_html__( $value, 'woocommerce-appointments' ) . '</option>';
								}
								?>
							</select>
							<input type="hidden" name="post_status" value="<?php esc_attr_e( $appointment->get_status() ); ?>">
						</p>

						<?php do_action( 'woocommerce_admin_appointment_data_after_appointment_details', $post->ID ); ?>
					</div>
					<div class="appointment_data_column">
						<h4><?php esc_html_e( 'Specification', 'woocommerce-appointments' ); ?></h4>
						<?php
						// Product select.
						?>
						<p class="form-field form-field-wide">
							<label for="product_id"><?php esc_html_e( 'Product:', 'woocommerce-appointments' ); ?></label>
							<select class="wc-product-search" id="product_id" name="product_id" data-placeholder="<?php esc_html_e( 'N/A', 'woocommerce-appointments' ); ?>" data-action="woocommerce_json_search_appointable_products">
								<option value="<?php esc_attr_e( $product_id ); ?>" selected="selected"><?php esc_html_e( $product_name ); ?></option>';
							</select>
						</p>

						<?php
						// Staff select.
						if ( current_user_can( 'manage_others_appointments' ) ) {
							$staff = WC_Appointments_Admin::get_appointment_staff(); #all staff
						} elseif ( $product_id ) { #product staff
							$staff = [];
							foreach ( $product->get_staff_ids() as $staff_id ) {
								$staff[] = new WC_Product_Appointment_Staff( $staff_id );
							}
						} else {
							$staff = WC_Appointments_Admin::get_appointment_staff(); #all staff
						}

						$appointable_staff = [];
						foreach ( $staff as $staff_member ) {
							$appointable_staff[ $staff_member->ID ] = $staff_member->display_name;
						}
						?>
						<p class="form-field form-field-wide">
							<label for="staff_ids"><?php esc_html_e( 'Staff:', 'woocommerce-appointments' ); ?></label>
							<select multiple="multiple" id="staff_ids" name="staff_ids[]" class="multiselect wc-enhanced-select" data-placeholder="<?php esc_html_e( 'N/A', 'woocommerce-appointments' ); ?>" data-allow_clear="true">
								<?php
								foreach ( $appointable_staff as $key => $value ) {
									echo '<option value="' . esc_attr( $key ) . '" ' . selected( in_array( $key, $staff_ids ), true ) . '>' . esc_html__( $value, 'woocommerce-appointments' ) . '</option>';
								}
								?>
							</select>
						</p>
						<?php

						// Parent product ID.
						woocommerce_wp_text_input(
							[
								'type'        => 'number',
								'id'          => '_appointment_parent_id',
								'label'       => __( 'Parent Appointment ID', 'woocommerce-appointments' ),
								'placeholder' => 'N/A',
							]
						);

						// Number of customers.
						if ( $appointment->get_qty( 'edit' ) ) {
							woocommerce_wp_text_input(
								[
									'type'          => 'number',
									'id'            => '_appointment_qty',
									'label'         => __( 'Qty', 'woocommerce-appointments' ),
									'placeholder'   => '0',
									'value'         => $appointment->get_qty( 'edit' ),
									'custom_attributes' => array(
										'min'  => '1',
										'step' => '1',
									),
									'wrapper_class' => 'appointment-qty',
								]
							);
						}

						?>
					</div>
					<div class="appointment_data_column last_column">
						<h4><?php esc_html_e( 'Date/Time', 'woocommerce-appointments' ); ?></h4>

						<?php
						woocommerce_wp_checkbox(
							[
								'id'          => '_appointment_all_day',
								'label'       => __( 'All Day:', 'woocommerce-appointments' ),
								'description' => __( 'Check this box if the appointment lasts all day.', 'woocommerce-appointments' ),
								'value'       => $appointment->get_all_day( 'edit' ) ? 'yes' : 'no',
							]
						);

						woocommerce_wp_text_input(
							[
								'id'          => 'appointment_start_date',
								'type'        => 'text',
								'label'       => __( 'Start Date:', 'woocommerce-appointments' ),
								'placeholder' => 'yyyy-mm-dd',
								'value'       => date( 'Y-m-d', $appointment->get_start( 'edit' ) ),
								'class'       => 'date-picker',
							]
						);

						woocommerce_wp_text_input(
							[
								'id'          => 'appointment_start_time',
								'type'        => 'time',
								'label'       => __( 'Start Time:', 'woocommerce-appointments' ),
								'placeholder' => 'hh:mm',
								'value'       => date( 'H:i', $appointment->get_start( 'edit' ) ),
								'class'       => 'time-picker',
							]
						);

						woocommerce_wp_text_input(
							[
								'id'          => 'appointment_end_date',
								'type'        => 'text',
								'label'       => __( 'End Date:', 'woocommerce-appointments' ),
								'placeholder' => 'yyyy-mm-dd',
								'value'       => date( 'Y-m-d', $appointment->get_end( 'edit' ) ),
								'class'       => 'date-picker',
							]
						);

						woocommerce_wp_text_input(
							[
								'id'          => 'appointment_end_time',
								'type'        => 'time',
								'label'       => __( 'End Time:', 'woocommerce-appointments' ),
								'placeholder' => 'hh:mm',
								'value'       => date( 'H:i', $appointment->get_end( 'edit' ) ),
								'class'       => 'time-picker',
							]
						);

						// Customer timezone.
						$tzstring_site = get_option( 'timezone_string' );
						$tzstring_user = get_user_meta( get_current_user_id(), 'timezone_string', true );
						$tzstring_site = $tzstring_user && $tzstring_site !== $tzstring_user ? $tzstring_site : '';
						$tzstring      = $appointment->get_local_timezone( 'edit' );
						$tzstring      = $tzstring ? $tzstring : $appointment->get_timezone( 'edit' ); #fallback
						$tzstring      = $tzstring ? $tzstring : $tzstring_site;
						?>
						<p class="form-field form-field-wide">
							<label for="_local_timezone"><?php esc_html_e( 'Customer Timezone:', 'woocommerce-appointments' ); ?></label>
							<select id="_local_timezone" name="_local_timezone" class="wc-enhanced-select" aria-label="<?php esc_attr_e( 'Timezone', 'woocommerce-appointments' ); ?>">
								<?php echo wp_timezone_choice( $tzstring, get_user_locale() ); // WPCS: XSS ok. ?>
							</select>
						</p>
					</div>
					<div class="clear"></div>
				</div>
				<?php
				$appointment_addons = $appointment->get_addons();
				if ( $appointment_addons ) {
				?>
					<div class="appointment_data_container appointment_data_addons">
						<div class="clear"></div>
						<div class="appointment_data_column data_column_wide">
							<h3><?php esc_html_e( 'Add-ons', 'woocommerce-appointments' ); ?></h3>
							<?php echo $appointment_addons; // WPCS: XSS ok. ?>
						</div>
						<div class="clear"></div>
					</div>
				<?php } ?>
			</div>
		</div>
		<?php
		// Select2 handling.
		wc_enqueue_js(
			"
			$( '#_appointment_all_day' ).on( 'change', function () {
				if ( $( this ).is( ':checked' ) ) {
					$( '#appointment_start_time, #appointment_end_time, #_local_timezone' ).closest( 'p' ).hide();
				} else {
					$( '#appointment_start_time, #appointment_end_time, #_local_timezone' ).closest( 'p' ).show();
				}
			}).trigger( 'change' );

			$( '.date-picker' ).datepicker({
				dateFormat: 'yy-mm-dd',
				numberOfMonths: 1,
				showOtherMonths: true,
				changeMonth: true,
				showButtonPanel: true,
				firstDay: '" . absint( get_option( 'start_of_week', 1 ) ) . "'
			});

			// Check if start- and end date are correct
			var start = $( '#appointment_start_date' );
			var end = $( '#appointment_end_date' );
			var start_date = start.val().replace(/-/g,'');
			var end_date = end.val().replace(/-/g,'');
			$( '#appointment_start_date, #appointment_end_date' ).on( 'click', function() {
				update_dates();
			});
			start.on( 'change', function() {
				if ( start_date > end_date ) {
					end.val( $( this ).val() );
					input_animate(end);
				} else if ( start_date === end_date ) {
						end.val( $( this ).val() );
					input_animate(end);
					}
			});
			end.on( 'change', function() {
				update_dates();
				if ( end_date < start_date ) {
					start.val( $( this ).val() );
					input_animate(start);
				}
			});

			function update_dates(){
				start_date = start.val().replace(/-/g,'');
				end_date = end.val().replace(/-/g,'');
			}

			function input_animate(e){
				e.stop().css({backgroundColor:'#ddd'}).animate({backgroundColor:'none'}, 500);
			}

		"
		);
	}

	/**
	 * Returns an array of labels (statuses wrapped in gettext)
	 *
	 * @param  array  $statuses
	 * @deprecated since 2.3.0. $this->get_wc_appointment_statuses now also comes with globalised strings.
	 * @return array
	 */
	public function get_labels_for_statuses( $statuses = [] ) {
		$labels = [];

		foreach ( $statuses as $status ) {
			$labels[ $status ] = $status;
		}

		return $labels;
	}

	/**
	 * Save handler.
	 *
	 * @param  int     $post_id
	 * @param  WP_Post $post
     *
     * @return int|void
	 */
	public function meta_box_save( $post_id, $post ) {
		if ( ! isset( $_POST['wc_appointments_details_meta_box_nonce'] ) || ! wp_verify_nonce( $_POST['wc_appointments_details_meta_box_nonce'], 'wc_appointments_details_meta_box' ) ) {
			return $post_id;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return $post_id;
		}

		// Check the post being saved == the $post_id to prevent triggering this call for other save_post events
		if ( empty( $_POST['post_ID'] ) || intval( $_POST['post_ID'] ) !== $post_id ) {
			return $post_id;
		}

		if ( ! in_array( $post->post_type, $this->post_types ) ) {
			return $post_id;
		}

		if ( self::$saved_meta_box ) {
			return $post_id;
		}

		// We need this save event to run once to avoid potential endless loops. This would have been perfect:
		// remove_action( current_filter(), __METHOD__ );
		// But cannot be used due to https://github.com/woocommerce/woocommerce/issues/6485
		// When that is patched in core we can use the above. For now:
		self::$saved_meta_box = true;

		// Get appointment object.
		$appointment = get_wc_appointment( $post_id );
		$product_id  = wc_clean( $_POST['product_id'] ) ? wc_clean( $_POST['product_id'] ) : $appointment->get_product_id();
		$start_date  = explode( '-', wc_clean( $_POST['appointment_start_date'] ) );
		$end_date    = explode( '-', wc_clean( $_POST['appointment_end_date'] ) );
		$start_time  = explode( ':', wc_clean( $_POST['appointment_start_time'] ) );
		$end_time    = explode( ':', wc_clean( $_POST['appointment_end_time'] ) );
		$start       = mktime( $start_time[0], $start_time[1], 0, $start_date[1], $start_date[2], $start_date[0] );
		$end         = mktime( $end_time[0], $end_time[1], 0, $end_date[1], $end_date[2], $end_date[0] );
		$product     = wc_get_product( $product_id );

		// New appointment meta.
		$props = [
			'all_day'         => isset( $_POST['_appointment_all_day'] ),
			'customer_id'     => isset( $_POST['_appointment_customer_id'] ) ? absint( $_POST['_appointment_customer_id'] ) : '',
			'date_created'    => empty( $_POST['appointment_date'] ) ? current_time( 'timestamp' ) : strtotime( $_POST['appointment_date'] . ' ' . (int) $_POST['appointment_date_hour'] . ':' . (int) $_POST['appointment_date_minute'] . ':00' ),
			'start'           => $start,
			'end'             => $end,
			'parent_id'       => isset( $_POST['_appointment_parent_id'] ) ? absint( $_POST['_appointment_parent_id'] ) : '',
			'product_id'      => absint( $product_id ),
			'staff_ids'       => isset( $_POST['staff_ids'] ) ? wc_clean( $_POST['staff_ids'] ) : '',
			'status'          => wc_clean( $_POST['_appointment_status'] ),
			'customer_status' => wc_clean( $_POST['_appointment_customer_status'] ),
			'qty'             => isset( $_POST['_appointment_qty'] ) ? absint( $_POST['_appointment_qty'] ) : 1,
			'local_timezone'  => wc_clean( $_POST['_local_timezone'] ),
		];

		do_action( 'woocommerce_admin_process_appointment_props', $props, $appointment );

		// Save appointment meta.
		$appointment->set_props( $props );

		do_action( 'woocommerce_admin_process_appointment_object', $appointment );

		$appointment->save();

		do_action( 'woocommerce_appointment_process_meta', $post_id );
	}
}

return new WC_Appointment_Meta_Box_Data();
