<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Avaiablity Filter Widget and related functions.
 *
 * Generates from/to date picker to filter products by date.
 *
 * @version 3.4.0
 * @since 3.4.0
 */
class WC_Appointments_Widget_Availability extends WC_Widget {

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wp_locale;

		$this->widget_cssclass    = 'woocommerce widget_availability_filter';
		$this->widget_description = __( 'Filter products in your store by availability.', 'woocommerce-appointments' );
		$this->widget_id          = 'woocommerce_availability_filter';
		$this->widget_name        = __( 'Filter Products by Availability', 'woocommerce-appointments' );
		$this->settings           = [
			'title' => [
				'type'  => 'text',
				'std'   => __( 'Filter by availability', 'woocommerce-appointments' ),
				'label' => __( 'Title', 'woocommerce-appointments' ),
			],
		];

		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		wp_register_script(
			'wc-appointments-availability-filter',
			WC_APPOINTMENTS_PLUGIN_URL . '/assets/js/availability-filter' . $suffix . '.js',
			[ 'jquery-ui-datepicker', 'underscore', 'wc-appointments-moment' ],
			WC_APPOINTMENTS_VERSION,
			true
		);
		wp_localize_script(
			'wc-appointments-availability-filter',
			'wc_appointments_availability_filter_params',
			[
				'closeText'       => esc_js( __( 'Close', 'woocommerce-appointments' ) ),
				'currentText'     => esc_js( __( 'Today', 'woocommerce-appointments' ) ),
				'prevText'        => esc_js( __( 'Previous', 'woocommerce-appointments' ) ),
				'nextText'        => esc_js( __( 'Next', 'woocommerce-appointments' ) ),
				'monthNames'      => array_values( $wp_locale->month ),
				'monthNamesShort' => array_values( $wp_locale->month_abbrev ),
				'dayNames'        => array_values( $wp_locale->weekday ),
				'dayNamesShort'   => array_values( $wp_locale->weekday_abbrev ),
				'dayNamesMin'     => array_values( $wp_locale->weekday_initial ),
				'firstDay'        => get_option( 'start_of_week' ),
				'isRTL'           => is_rtl(),
				'dateFormat'      => wc_appointments_convert_to_moment_format( wc_appointments_date_format() ),
			]
		);

		if ( is_customize_preview() ) {
			wp_enqueue_script( 'wc-appointments-availability-filter' );
		}

		add_filter( 'loop_shop_post_in', [ $this, 'filter_products_by_availability' ] );

		parent::__construct();
	}

	/**
	 * Output widget.
	 *
	 * @see WP_Widget
	 *
	 * @param array $args     Arguments.
	 * @param array $instance Widget instance.
	 */
	public function widget( $args, $instance ) {
		global $wp;

		if ( ! is_shop() && ! is_product_taxonomy() ) {
			return;
		}

		if ( isset( wc()->query->get_main_query()->post_count ) && ! wc()->query->get_main_query()->post_count ) {
			return;
		}

		// Add date picker JS.
		wp_enqueue_script( 'wc-appointments-availability-filter' );

		$min_date = isset( $_GET['min_date'] ) ? wc_clean( wp_unslash( $_GET['min_date'] ) ) : ''; // WPCS: input var ok, CSRF ok.
		$max_date = isset( $_GET['max_date'] ) ? wc_clean( wp_unslash( $_GET['max_date'] ) ) : ''; // WPCS: input var ok, CSRF ok.

		$min_date_local = $min_date ? date_i18n( wc_appointments_date_format(), strtotime( $min_date ) ) : '';
		$max_date_local = $max_date ? date_i18n( wc_appointments_date_format(), strtotime( $max_date ) ) : '';

		#var_dump( $min_date_local );

		$this->widget_start( $args, $instance );

		if ( '' === get_option( 'permalink_structure' ) ) {
			$form_action = remove_query_arg(
				[
					'page',
					'paged',
					'product-page',
					'min_date_label',
					'max_date_label',
				],
				add_query_arg(
					$wp->query_string,
					'',
					home_url( $wp->request )
				)
			);
		} else {
			$form_action = preg_replace( '%/page/[0-9]+%', '', home_url( trailingslashit( $wp->request ) ) );
		}

		// Fields id="min_date_label" and id="max_date_label"
		// have no name defined are not submitted.
		echo '<form method="get" action="' . esc_url( $form_action ) . '">
			<div class="date_picker_wrapper">
				<div class="date_picker_inner date_picker_start">
					<label for="min_date_label">' . esc_html__( 'Start Date:', 'woocommerce-appointments' ) . '</label>
					<input type="text" id="min_date_label" class="date-picker" value="' . esc_attr( $min_date_local ) . '" autocomplete="off" readonly="readonly" />
					<input type="hidden" id="min_date" class="date-picker-field" name="min_date" value="' . esc_attr( $min_date ) . '" autocomplete="off" readonly="readonly" />
				</div>
				<div class="date_picker_inner date_picker_end">
					<label for="max_date_label">' . esc_html__( 'End Date:', 'woocommerce-appointments' ) . '</label>
					<input type="text" id="max_date_label" class="date-picker" value="' . esc_attr( $max_date_local ) . '" autocomplete="off" readonly="readonly" />
					<input type="hidden" id="max_date" class="date-picker-field" name="max_date" value="' . esc_attr( $max_date ) . '" autocomplete="off" readonly="readonly" />
				</div>
				<button type="submit" class="button">' . esc_html__( 'Filter', 'woocommerce-appointments' ) . '</button>
				<div class="clear"></div>
			</div>
		</form>'; // WPCS: XSS ok.

		$this->widget_end( $args );
	}

	/**
	 * Get filtered min date for current products.
	 *
	 * @return void|bool|array
	 */
	public function filter_products_by_availability() {
		$min_date = isset( $_GET['min_date'] ) ? wc_clean( wp_unslash( $_GET['min_date'] ) ) : ''; // WPCS: input var ok, CSRF ok.
		$max_date = isset( $_GET['max_date'] ) ? wc_clean( wp_unslash( $_GET['max_date'] ) ) : ''; // WPCS: input var ok, CSRF ok.

		// Get available appointments.
        if ( $this->is_valid_date( $min_date ) && $this->is_valid_date( $max_date ) ) {
            $maches = $this->get_available_products( $min_date, $max_date );

			if ( $maches ) {
				return $maches;
			} else {
				return false;
			}
        }
	}

	/**
     * Get all available appointment products
     *
     * @param string $start_date_raw YYYY-MM-DD format
     * @param string $end_date_raw YYYY-MM-DD format
     *
     * @return array|void Available post IDs
     */
    protected function get_available_products( $start_date_raw, $end_date_raw ) {
        // Separate dates from times.
        $start_date = explode( ' ', $start_date_raw );
        $end_date   = explode( ' ', $end_date_raw );

        // If time wasn't passed, define defaults.
        if ( ! isset( $start_date[1] ) ) {
            $start_date[1] = '12:00';
        }

		$appointable_product_ids = WC_Data_Store::load( 'product-appointment' )->get_appointable_product_ids( true );

		// If no products yet, return zero.
		if ( ! $appointable_product_ids ) {
			return;
		}

		// Gather maches of available product IDs.
		$maches2 = [];

        // Loop through all posts
        foreach ( $appointable_product_ids as $appointable_product_id ) {
			// Get product object.
			$appointable_product = get_wc_product_appointment( $appointable_product_id );

            // Get product duration unit.
            $duration_unit = $appointable_product->get_duration_unit();

            // All slots are available (exact match).
			// TODO Add time filtering when available as filter.
			$appointment_form = new WC_Appointment_Form( $appointable_product );
			$check_date       = strtotime( $start_date_raw );
			$end_date         = strtotime( '+ 1 day', strtotime( $end_date_raw ) );
			$min_date         = strtotime( '- 1 day', strtotime( $start_date_raw ) );
			$max_date         = $start_date_raw === $end_date_raw ? strtotime( '+ 2 days', strtotime( $end_date_raw ) ) : strtotime( '+ 1 day', strtotime( $end_date_raw ) );

			#print '<pre>'; print_r( $appointable_product_id ); print '</pre>';
			#print '<pre>'; print_r( date( 'Y-m-d', $check_date ) . ' < ' . date( 'Y-m-d', $end_date ) ); print '</pre>';

			// Check against availability rules.
			$maches    = [];
			$timestamp = $check_date;
			while ( $timestamp < $end_date ) {
				$appointable_day = WC_Product_Appointment_Rule_Manager::check_availability_rules_against_date( $appointable_product, $timestamp );
				if ( $appointable_day ) {
					#print '<pre>'; print_r( date( 'Y-m-d', $timestamp ) ); print '</pre>';
					$maches[] = $timestamp;
				}
				$timestamp = strtotime( '+1 day', $timestamp );
			}

			// Skip the product when no availability.
			if ( ! $maches ) {
				continue;
			}

			// Find scheduled slots for the range.
			$scheduled_day_slots = WC_Appointments_Controller::find_scheduled_day_slots( $appointable_product, $min_date, $max_date, 'Y-m-d' );

			#print '<pre>'; print_r( $appointable_product_id ); print '</pre>';
			#print '<pre>'; print_r( $scheduled_day_slots ); print '</pre>';

			// Check for each each day in range.
			$timestamp2 = $check_date;
			while ( $timestamp2 < $end_date ) {
				// Check against scheduled appointments.
				$date = date( 'Y-m-d', $timestamp2 );
				if ( ! isset( $scheduled_day_slots['fully_scheduled_days'][ $date ] ) && ! isset( $scheduled_day_slots['unavailable_days'][ $date ] ) ) {
					$maches2[] = $appointable_product->get_id();
					#print '<pre>'; print_r( $date . ' - ' . $appointable_product->get_id() . ' - ' . isset( $scheduled_day_slots['fully_scheduled_days'][ $date ] ) ); print '</pre>';
				}

				// move to next day
				$timestamp2 = strtotime( '+1 day', $timestamp2 );
			}
        }

		#print '<pre>'; print_r( array_unique( $maches2 ) ); print '</pre>';

		return array_unique( $maches2 );
    }

	/**
     * Validate date input
     *
     * @requires PHP 5.3+
     */
    protected function is_valid_date( $date ) {
        if ( empty( $date ) ) {
            return false;
        } elseif ( 10 === strlen( $date ) ) {
            $d = DateTime::createFromFormat( 'Y-m-d', $date );
            return $d && $d->format( 'Y-m-d' ) === $date;
        } elseif ( 16 === strlen( $date ) ) {
            $d = DateTime::createFromFormat( 'Y-m-d H:i', $date );
            return $d && $d->format( 'Y-m-d H:i' ) === $date;
        }

        return false;
    }

}

/**
 * Register Widgets.
 *
 * @since 3.4.0
 */
function wc_appointments_register_widgets() {
	register_widget( 'WC_Appointments_Widget_Availability' );
}

add_action( 'widgets_init', 'wc_appointments_register_widgets' );
