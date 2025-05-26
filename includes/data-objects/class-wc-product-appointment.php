<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Class for the appointment product type.
 */
class WC_Product_Appointment extends WC_Product {

	/**
	 * Stores product data.
	 *
	 * @var array
	 */
	protected $defaults = [
		'has_price_label'         => false,
		'price_label'             => '',
		'has_pricing'             => false,
		'pricing'                 => [],
		'qty'                     => 1,
		'qty_min'                 => 1,
		'qty_max'                 => 1,
		'duration_unit'           => 'hour',
		'duration'                => 1,
		'interval_unit'           => 'hour',
		'interval'                => 1,
		'padding_duration_unit'   => 'hour',
		'padding_duration'        => 0,
		'min_date_unit'           => 'day',
		'min_date'                => 0,
		'max_date_unit'           => 'month',
		'max_date'                => 12,
		'user_can_cancel'         => false,
		'cancel_limit_unit'       => 'day',
		'cancel_limit'            => 1,
		'user_can_reschedule'     => false,
		'reschedule_limit_unit'   => 'day',
		'reschedule_limit'        => 1,
		'requires_confirmation'   => false,
		'customer_timezones'      => false,
		'cal_color'               => '',
		'availability_span'       => '',
		'availability_autoselect' => false,
		'has_restricted_days'     => false,
		'restricted_days'         => [],
		/*'availability'            => [],*/
		'staff_label'             => '',
		'staff_assignment'        => '',
		'staff_nopref'            => false,
		'staff_id'                => [],
		'staff_ids'               => [],
		'staff_base_costs'        => [],
		'staff_qtys'              => [],
	];

	/**
	 * Stores availability rules once loaded.
	 *
	 * @var array
	 */
	public $availability_rules = [];

	/**
	 * Stores staff ID if auto assigned.
	 *
	 * @var array
	 */
	public $assigned_staff_id = false;

	/**
	 * Merges appointment product data into the parent object.
	 *
	 * @param int|WC_Product|object $product Product to init.
	 */
	public function __construct( $product = 0 ) {
		/**
		 * Override default attributes for Product Appointment.
		 *
		 * @since 4.14.4
		 *
		 * @param array $defaults Default values to init new Product.
		 * @param int|WC_Product|object $product Product to init.
		 *
		 * @see WC_Product_Appointment::defaults
		 */
		$defaults   = apply_filters( 'woocommerce_appointments_product_defaults', $this->defaults, $product );
		$this->data = array_merge( $this->data, $defaults );
		parent::__construct( $product );
	}

	/**
	 * Get the add to cart button text
	 *
	 * @return string
	 */
	public function add_to_cart_text() {
		return apply_filters( 'woocommerce_appointment_add_to_cart_text', __( 'Book', 'woocommerce-appointments' ), $this );
	}

	/**
	 * Get the add to cart button text for the single page
	 *
	 * @return string
	 */
	public function single_add_to_cart_text() {
		return $this->get_requires_confirmation() ? apply_filters( 'woocommerce_appointment_single_check_availability_text', __( 'Check Availability', 'woocommerce-appointments' ), $this ) : apply_filters( 'woocommerce_appointment_single_add_to_cart_text', __( 'Book Now', 'woocommerce-appointments' ), $this );
	}

	/**
	 * Return if appointment has label
	 * @return bool
	 */
	public function has_price_label() {
		$has_price_label = false;

		// Products must exist of course
		if ( $this->get_has_price_label() ) {
			$price_label     = $this->get_price_label();
			$has_price_label = $price_label ? $price_label : __( 'Price Varies', 'woocommerce-appointments' );
		}

		return $has_price_label;
	}

	/**
	 * Get price HTML
	 *
	 * @param string $price
	 * @return string
	 */
	public function get_price_html( $deprecated = '' ) {
		$sale_price    = wc_format_sale_price(
			wc_get_price_to_display(
				$this,
				[
					'qty'   => 1,
					'price' => $this->get_regular_price(),
				]
			),
			wc_get_price_to_display( $this )
		) . $this->get_price_suffix();
		$regular_price = wc_price( floatval( wc_get_price_to_display( $this ) ) ) . $this->get_price_suffix();

		// Price.
		if ( '' === $this->get_price() ) {
			$price = apply_filters( 'woocommerce_empty_price_html', '<span class="amount">' . __( 'Free!', 'woocommerce-appointments' ) . '</span>', $this );
		} elseif ( $this->is_on_sale() ) {
			$price = $sale_price;
		} else {
			$price = $regular_price;
		}

		// Default price display.
		$price_html = $price;

		// Price with additional cost.
		if ( $this->has_additional_costs() ) {
			/* translators: %s: display price */
			$price_html = sprintf( __( 'From: %s', 'woocommerce-appointments' ), $price );
		}

		// Price label.
		if ( $this->has_price_label() ) {
			$price_html = $this->has_price_label();
		}

		// Duration HTML label.
		if ( 'month' === $this->get_duration_unit() ) {
			/* translators: %s: display duration */
			$duration_html = ' <small class="duration">' . sprintf( _n( '%s month', '%s months', $this->get_duration(), 'woocommerce-appointments' ), $this->get_duration() ) . '</small>';
		} elseif ( 'day' === $this->get_duration_unit() ) {
			/* translators: %s: display duration */
			$duration_html = ' <small class="duration">' . sprintf( _n( '%s day', '%s days', $this->get_duration(), 'woocommerce-appointments' ), $this->get_duration() ) . '</small>';
		// Hourly or minutes product duration sets add-on duration in minutes.
		} else {
			$duration_full = wc_appointment_pretty_timestamp( $this->get_duration_in_minutes(), 'minute' );
			$duration_html = ' <small class="duration">' . $duration_full . '</small>';
		}

		return apply_filters( 'woocommerce_get_price_html', apply_filters( 'woocommerce_return_price_html', $price_html, $this ) . apply_filters( 'woocommerce_return_duration_html', $duration_html, $this ), $this );
	}

	/**
	 * Get cost HTML
	 *
	 * @param string $cost
	 * @return string
	 */
	public function get_cost_html( $cost = 0 ) {
		if ( 'incl' === get_option( 'woocommerce_tax_display_shop' ) ) {
			$display_price = wc_get_price_including_tax(
				$this,
				[
					'price' => $cost,
				]
			);
		} else {
			$display_price = wc_get_price_excluding_tax(
				$this,
				[
					'price' => $cost,
				]
			);
		}

		/*
		$display_price_suffix  = wc_price( apply_filters( 'woocommerce_product_get_price', $display_price, $this ) ) . $this->get_price_suffix();
		$original_price_suffix = wc_price( $display_price ) . $this->get_price_suffix();

		if ( $original_price_suffix !== $display_price_suffix ) {
			$cost_html = "<del>{$original_price_suffix}</del><ins>{$display_price_suffix}</ins>";
		} elseif ( $display_price ) {
			$cost_html = wc_price( $display_price ) . $this->get_price_suffix();
		} else {
			$cost_html = __( 'Free!', 'woocommerce-appointments' );
		}
		*/

		// Formatted price display.
		$cost_html = wc_price( $display_price ) . $this->get_price_suffix( $cost, 1 );

		return apply_filters(
			'woocommerce_get_cost_html',
			$cost_html,
			$this
		);
	}

	/**
	 * Get internal type.
	 *
	 * @return string
	 */
	public function get_type() {
		return 'appointment';
	}

	/**
	 * @since 3.0.0
	 * @return bool
	 */
	public function is_wc_appointment_has_staff() {
		return $this->has_staff();
	}

	/*
	|--------------------------------------------------------------------------
	| CRUD Getters and setters.
	|--------------------------------------------------------------------------
	*/

	/**
	 * Get has_additional_costs.
	 *
	 * @param  string $context
	 * @return boolean
	 */
	public function get_has_additional_costs( $context = 'view' ) {
		return $this->get_prop( 'has_additional_costs', $context );
	}

	/**
	 * Set has_additional_costs.
	 *
	 * @param boolean $value
	 */
	public function set_has_additional_costs( $value ) {
		$this->set_prop( 'has_additional_costs', wc_appointments_string_to_bool( $value ) );
	}

	/**
	 * Get has_price_label.
	 *
	 * @param  string $context
	 * @return boolean
	 */
	public function get_has_price_label( $context = 'view' ) {
		return $this->get_prop( 'has_price_label', $context );
	}

	/**
	 * Set has_price_label.
	 *
	 * @param boolean $value
	 */
	public function set_has_price_label( $value ) {
		$this->set_prop( 'has_price_label', $value );
	}

	/**
	 * Get price_label.
	 *
	 * @param  string $context
	 * @return string
	 */
	public function get_price_label( $context = 'view' ) {
		return $this->get_prop( 'price_label', $context );
	}

	/**
	 * Set get_price_label.
	 *
	 * @param string $value
	 */
	public function set_price_label( $value ) {
		$this->set_prop( 'price_label', $value );
	}

	/**
	 * Get has_pricing.
	 *
	 * @param  string $context
	 * @return boolean
	 */
	public function get_has_pricing( $context = 'view' ) {
		return $this->get_prop( 'has_pricing', $context );
	}

	/**
	 * Set has_pricing.
	 *
	 * @param boolean $value
	 */
	public function set_has_pricing( $value ) {
		$this->set_prop( 'has_pricing', $value );
	}

	/**
	 * Get pricing_rules.
	 *
	 * @param  string $context
	 * @return array
	 */
	public function get_pricing( $context = 'view' ) {
		return $this->get_prop( 'pricing', $context );
	}

	/**
	 * Set pricing_rules.
	 *
	 * @param array $value
	 */
	public function set_pricing( $value ) {
		$this->set_prop( 'pricing', (array) $value );
	}

	/**
	 * Get the qty available to schedule per slot.
	 *
	 * @param  string $context
	 * @return integer
	 */
	public function get_qty( $context = 'view' ) {
		return $this->get_prop( 'qty', $context );
	}

	/**
	 * Set qty.
	 *
	 * @param integer $value
	 */
	public function set_qty( $value ) {
		$this->set_prop( 'qty', absint( $value ) );
	}

	/**
	 * Get min qty available to schedule per slot.
	 *
	 * @param  string $context
	 * @return integer
	 */
	public function get_qty_min( $context = 'view' ) {
		return $this->get_prop( 'qty_min', $context );
	}

	/**
	 * Set min qty.
	 *
	 * @param integer $value
	 */
	public function set_qty_min( $value ) {
		$this->set_prop( 'qty_min', absint( $value ) );
	}
	/**
	 * Get max qty available to schedule per slot.
	 *
	 * @param  string $context
	 * @return integer
	 */
	public function get_qty_max( $context = 'view' ) {
		return $this->get_prop( 'qty_max', $context );
	}

	/**
	 * Set max qty.
	 *
	 * @param integer $value
	 */
	public function set_qty_max( $value ) {
		$this->set_prop( 'qty_max', absint( $value ) );
	}

	/**
	 * Get duration_unit.
	 *
	 * @param  string $context
	 * @return string
	 */
	public function get_duration_unit( $context = 'view' ) {
		$value = $this->get_prop( 'duration_unit', $context );

		if ( 'view' === $context ) {
			$value = apply_filters( 'woocommerce_appointments_get_duration_unit', $value, $this );
		}
		return $value;
	}

	/**
	 * Set duration_unit.
	 *
	 * @param string $value
	 */
	public function set_duration_unit( $value ) {
		$this->set_prop( 'duration_unit', (string) $value );
	}

	/**
	 * Get duration.
	 *
	 * @param  string $context
	 * @return integer
	 */
	public function get_duration( $context = 'view' ) {
		$value = $this->get_prop( 'duration', $context );

		if ( 'view' === $context ) {
			$value = apply_filters( 'woocommerce_appointments_get_duration', $value, $this );
		}

		return $value;
	}

	/**
	 * Get duration.
	 *
	 * @param  string $context
	 * @return integer
	 */
	public function get_duration_in_minutes() {
		$duration = 'hour' === $this->get_duration_unit() ? $this->get_duration() * 60 : $this->get_duration();
		$duration = 'day' === $this->get_duration_unit() ? $this->get_duration() * 60 * 24 : $duration;
		$duration = 'month' === $this->get_duration_unit() ? $this->get_duration() : $duration;

		return apply_filters( 'woocommerce_appointments_get_duration_in_minutes', $duration, $this );
	}

	/**
	 * Set duration.
	 *
	 * @param integer $value
	 */
	public function set_duration( $value ) {
		$this->set_prop( 'duration', absint( $value ) );
	}

	/**
	 * Get interval_unit.
	 *
	 * @param  string $context
	 * @return string
	 */
	public function get_interval_unit( $context = 'view' ) {
		$value = $this->get_prop( 'interval_unit', $context );

		if ( 'view' === $context ) {
			$value = apply_filters( 'woocommerce_appointments_get_interval_unit', $value, $this );
		}
		return $value;
	}

	/**
	 * Set interval_unit.
	 *
	 * @param string $value
	 */
	public function set_interval_unit( $value ) {
		$this->set_prop( 'interval_unit', (string) $value );
	}

	/**
	 * Get interval.
	 *
	 * @param  string $context
	 * @return integer
	 */
	public function get_interval( $context = 'view' ) {
		return $this->get_prop( 'interval', $context );
	}

	/**
	 * Set interval.
	 *
	 * @param integer $value
	 */
	public function set_interval( $value ) {
		$this->set_prop( 'interval', absint( $value ) );
	}

	/**
	 * Get padding_duration_unit.
	 *
	 * @param  string $context
	 * @return string
	 */
	public function get_padding_duration_unit( $context = 'view' ) {
		$value = $this->get_prop( 'padding_duration_unit', $context );

		if ( 'view' === $context ) {
			$value = apply_filters( 'woocommerce_appointments_get_padding_duration_unit', $value, $this );
		}

		return $value;
	}

	/**
	 * Set padding_duration_unit.
	 *
	 * @param string $value
	 */
	public function set_padding_duration_unit( $value ) {
		$this->set_prop( 'padding_duration_unit', (string) $value );
	}

	/**
	 * Get padding_duration.
	 *
	 * @param  string $context
	 * @return integer
	 */
	public function get_padding_duration( $context = 'view' ) {
		$value = $this->get_prop( 'padding_duration', $context );

		if ( 'view' === $context ) {
			$value = apply_filters( 'woocommerce_appointments_get_padding_duration', $value, $this );
		}

		return $value;
	}

	/**
	 * Get duration.
	 *
	 * @param  string $context
	 * @return integer
	 */
	public function get_padding_duration_in_minutes() {
		$duration = 'hour' === $this->get_padding_duration_unit() ? $this->get_padding_duration() * 60 : $this->get_padding_duration();
		$duration = 'day' === $this->get_padding_duration_unit() ? $this->get_padding_duration() * 60 * 24 : $duration;
		$duration = 'month' === $this->get_padding_duration_unit() ? $this->get_padding_duration() : $duration;

		return apply_filters( 'woocommerce_appointments_get_padding_duration_in_minutes', $duration, $this );
	}

	/**
	 * Set padding_duration.
	 *
	 * @param integer $value
	 */
	public function set_padding_duration( $value ) {
		$this->set_prop( 'padding_duration', absint( $value ) );
	}

	/**
	 * Get min_date_unit.
	 *
	 * @param  string $context
	 * @return string
	 */
	public function get_min_date_unit( $context = 'view' ) {
		return $this->get_prop( 'min_date_unit', $context );
	}

	/**
	 * Set min_date_unit.
	 *
	 * @param string $value
	 */
	public function set_min_date_unit( $value ) {
		$this->set_prop( 'min_date_unit', (string) $value );
	}

	/**
	 * Get min_date.
	 *
	 * @param  string $context
	 * @return integer
	 */
	public function get_min_date( $context = 'view' ) {
		return $this->get_prop( 'min_date', $context );
	}

	/**
	 * Set min_date.
	 *
	 * @param integer $value
	 */
	public function set_min_date( $value ) {
		$this->set_prop( 'min_date', absint( $value ) );
	}

	/**
	 * Get max_date_unit.
	 *
	 * @param  string $context
	 * @return string
	 */
	public function get_max_date_unit( $context = 'view' ) {
		return $this->get_prop( 'max_date_unit', $context );
	}

	/**
	 * Set max_date_unit.
	 *
	 * @param string $value
	 */
	public function set_max_date_unit( $value ) {
		$this->set_prop( 'max_date_unit', (string) $value );
	}

	/**
	 * Get max_date.
	 *
	 * @param  string $context
	 * @return integer
	 */
	public function get_max_date( $context = 'view' ) {
		return $this->get_prop( 'max_date', $context );
	}

	/**
	 * Set max_date.
	 *
	 * @param integer $value
	 */
	public function set_max_date( $value ) {
		$this->set_prop( 'max_date', absint( $value ) );
	}

	/**
	 * Get user_can_cancel.
	 *
	 * @param  string $context
	 * @return boolean
	 */
	public function get_user_can_cancel( $context = 'view' ) {
		return $this->get_prop( 'user_can_cancel', $context );
	}

	/**
	 * Set user_can_cancel.
	 *
	 * @param boolean $value
	 */
	public function set_user_can_cancel( $value ) {
		$this->set_prop( 'user_can_cancel', wc_appointments_string_to_bool( $value ) );
	}

	/**
	 * Get cancel_limit_unit.
	 *
	 * @param  string $context
	 * @return string
	 */
	public function get_cancel_limit_unit( $context = 'view' ) {
		$value = $this->get_prop( 'cancel_limit_unit', $context );

		if ( 'view' === $context ) {
			$value = apply_filters( 'woocommerce_appointments_get_cancel_limit_unit', $value, $this );
		}

		return $value;
	}

	/**
	 * Set cancel_limit_unit.
	 *
	 * @param string $value
	 */
	public function set_cancel_limit_unit( $value ) {
		$value = in_array( $value, [ 'month', 'day', 'hour', 'minute' ] ) ? $value : 'day';
		$this->set_prop( 'cancel_limit_unit', $value );
	}

	/**
	 * Get cancel_limit.
	 *
	 * @param  string $context
	 * @return integer
	 */
	public function get_cancel_limit( $context = 'view' ) {
		$value = $this->get_prop( 'cancel_limit', $context );

		if ( 'view' === $context ) {
			$value = apply_filters( 'woocommerce_appointments_get_cancel_limit', $value, $this );
		}

		return $value;
	}

	/**
	 * Set cancel_limit.
	 *
	 * @param integer $value
	 */
	public function set_cancel_limit( $value ) {
		$this->set_prop( 'cancel_limit', max( 1, absint( $value ) ) );
	}

	/**
	 * Get user_can_reschedule.
	 *
	 * @param  string $context
	 * @return boolean
	 */
	public function get_user_can_reschedule( $context = 'view' ) {
		return $this->get_prop( 'user_can_reschedule', $context );
	}

	/**
	 * Set user_can_reschedule.
	 *
	 * @param boolean $value
	 */
	public function set_user_can_reschedule( $value ) {
		$this->set_prop( 'user_can_reschedule', wc_appointments_string_to_bool( $value ) );
	}

	/**
	 * Get reschedule_limit_unit.
	 *
	 * @param  string $context
	 * @return string
	 */
	public function get_reschedule_limit_unit( $context = 'view' ) {
		return $this->get_prop( 'reschedule_limit_unit', $context );
	}

	/**
	 * Set reschedule_limit_unit.
	 *
	 * @param string $value
	 */
	public function set_reschedule_limit_unit( $value ) {
		$value = in_array( $value, [ 'month', 'day', 'hour', 'minute' ] ) ? $value : 'day';
		$this->set_prop( 'reschedule_limit_unit', $value );
	}

	/**
	 * Get reschedule_limit.
	 *
	 * @param  string $context
	 * @return integer
	 */
	public function get_reschedule_limit( $context = 'view' ) {
		return $this->get_prop( 'reschedule_limit', $context );
	}

	/**
	 * Set reschedule_limit.
	 *
	 * @param integer $value
	 */
	public function set_reschedule_limit( $value ) {
		$this->set_prop( 'reschedule_limit', max( 1, absint( $value ) ) );
	}

	/**
	 * Get requires_confirmation.
	 *
	 * @param  string $context
	 * @return boolean
	 */
	public function get_requires_confirmation( $context = 'view' ) {
		return $this->get_prop( 'requires_confirmation', $context );
	}

	/**
	 * Set requires_confirmation.
	 *
	 * @param boolean $value
	 */
	public function set_requires_confirmation( $value ) {
		$this->set_prop( 'requires_confirmation', wc_appointments_string_to_bool( $value ) );
	}

	/**
	 * Get customer_timezones.
	 *
	 * @param  string $context
	 * @return boolean
	 */
	public function get_customer_timezones( $context = 'view' ) {
		return $this->get_prop( 'customer_timezones', $context );
	}

	/**
	 * Set customer_timezones.
	 *
	 * @param boolean $value
	 */
	public function set_customer_timezones( $value ) {
		$this->set_prop( 'customer_timezones', wc_appointments_string_to_bool( $value ) );
	}

	/**
	 * Get cal_color.
	 *
	 * @param  string $context
	 * @return string
	 */
	public function get_cal_color( $context = 'view' ) {
		return $this->get_prop( 'cal_color', $context );
	}

	/**
	 * Set get_cal_color.
	 *
	 * @param string $value
	 */
	public function set_cal_color( $value ) {
		$this->set_prop( 'cal_color', $value );
	}

	/**
	 * Get availability_span.
	 *
	 * @param  string $context
	 * @return string
	 */
	public function get_availability_span( $context = 'view' ) {
		$value = $this->get_prop( 'availability_span', $context );

		if ( 'view' === $context ) {
			$value = apply_filters( 'woocommerce_appointments_get_availability_span', $value, $this );
		}
		return $value;
	}

	/**
	 * Set availability_span.
	 *
	 * @param string $value
	 */
	public function set_availability_span( $value ) {
		$this->set_prop( 'availability_span', (string) $value );
	}

	/**
	 * Get availability_autoselect.
	 *
	 * @param  string $context
	 * @return boolean
	 */
	public function get_availability_autoselect( $context = 'view' ) {
		return $this->get_prop( 'availability_autoselect', $context );
	}

	/**
	 * Set availability_autoselect.
	 *
	 * @param boolean $value
	 */
	public function set_availability_autoselect( $value ) {
		$this->set_prop( 'availability_autoselect', wc_appointments_string_to_bool( $value ) );
	}

	/**
	 * Get availability.
	 *
	 * @param  string $context
	 * @return array
	 */
	public function get_availability( $context = 'view' ) {
		$product_rules = WC_Data_Store::load( 'appointments-availability' )->get_all_as_array(
			[
				[
					'key'     => 'kind',
					'compare' => '=',
					'value'   => 'availability#product',
				],
				[
					'key'     => 'kind_id',
					'compare' => '=',
					'value'   => $this->get_id(),
				],
			]
		);

		return apply_filters( 'wc_appointments_product_availability', $product_rules, $this );
	}

	/**
	 * Get has_restricted_days.
	 *
	 * @param  string $context
	 * @return string
	 */
	public function get_has_restricted_days( $context = 'view' ) {
		return $this->get_prop( 'has_restricted_days', $context );
	}

	/**
	 * Set has_restricted_days.
	 *
	 * @param string $value
	 */
	public function set_has_restricted_days( $value ) {
		$this->set_prop( 'has_restricted_days', $value );
	}

	/**
	 * Get restricted_days.
	 *
	 * @param  string $context
	 * @return string
	 */
	public function get_restricted_days( $context = 'view' ) {
		return $this->get_prop( 'restricted_days', $context );
	}

	/**
	 * Set restricted_days.
	 *
	 * @param string $value
	 */
	public function set_restricted_days( $value ) {
		$this->set_prop( 'restricted_days', $value );
	}

	/**
	 * Get staff_label.
	 *
	 * @param  string $context
	 * @return string
	 */
	public function get_staff_label( $context = 'view' ) {
		return $this->get_prop( 'staff_label', $context );
	}

	/**
	 * Set staff_label.
	 *
	 * @param string $value
	 */
	public function set_staff_label( $value ) {
		$this->set_prop( 'staff_label', $value );
	}

	/**
	 * Get staff_assignment.
	 *
	 * @param  string $context
	 * @return string
	 */
	public function get_staff_assignment( $context = 'view' ) {
		return $this->get_prop( 'staff_assignment', $context );
	}

	/**
	 * Set staff_assignment.
	 *
	 * @param string $value
	 */
	public function set_staff_assignment( $value ) {
		$this->set_prop( 'staff_assignment', (string) $value );
	}

	/**
	 * Get staff_nopref.
	 *
	 * @param  string $context
	 * @return string
	 */
	public function get_staff_nopref( $context = 'view' ) {
		return $this->get_prop( 'staff_nopref', $context );
	}

	/**
	 * Set staff_nopref.
	 *
	 * @param string $value
	 */
	public function set_staff_nopref( $value ) {
		$this->set_prop( 'staff_nopref', wc_appointments_string_to_bool( $value ) );
	}

	/**
	 * Get staff_ids.
	 *
	 * @param  string $context
	 * @return array
	 */
	public function get_staff_ids( $context = 'view' ) {
		return $this->get_prop( 'staff_ids', $context );
	}

	/**
	 * Set staff_ids.
	 *
	 * @param array $value
	 */
	public function set_staff_ids( $value ) {
		$this->set_prop( 'staff_ids', wp_parse_id_list( (array) $value ) );
	}

	/**
	 * Get staff_base_costs.
	 *
	 * @param  string $context
	 * @return array
	 */
	public function get_staff_base_costs( $context = 'view' ) {
		return $this->get_prop( 'staff_base_costs', $context );
	}

	/**
	 * Set staff_base_costs.
	 *
	 * @param array $value
	 */
	public function set_staff_base_costs( $value ) {
		$this->set_prop( 'staff_base_costs', (array) $value );
	}

	/**
	 * Get staff_qtys.
	 *
	 * @param  string $context
	 * @return array
	 */
	public function get_staff_qtys( $context = 'view' ) {
		return $this->get_prop( 'staff_qtys', $context );
	}

	/**
	 * Set staff_qtys.
	 *
	 * @param array $value
	 */
	public function set_staff_qtys( $value ) {
		$this->set_prop( 'staff_qtys', (array) $value );
	}

	/*
	|--------------------------------------------------------------------------
	| Conditionals
	|--------------------------------------------------------------------------
	|
	| Conditionals functions which return true or false.
	*/

	/**
	 * If this product class is a skeleton/place holder class (used for appointment addons).
	 *
	 * @return boolean
	 */
	public function is_skeleton() {
		return false;
	}

	/**
	 * If this product class is an addon for appointments.
	 *
	 * @return boolean
	 */
	public function is_appointments_addon() {
		return false;
	}

	/**
	 * Extension/plugin/add-on name for the appointment addon this product refers to.
	 *
	 * @return string
	 */
	public function appointments_addon_title() {
		return '';
	}

	/**
	 * Returns whether or not the product is in stock.
	 *
	 * @todo Develop further to embrace WC stock statuses and backorders.
	 *
	 * @return bool
	 */
	public function is_in_stock() {
		return apply_filters( 'woocommerce_product_is_in_stock', true, $this );
		// return apply_filters( 'woocommerce_product_is_in_stock', 'instock' === $this->get_stock_status(), $this );
	}

	/**
	 * Appointments can always be purchased regardless of price.
	 *
	 * @return boolean
	 */
	public function is_purchasable() {
		return apply_filters( 'woocommerce_is_purchasable', $this->exists() && ( 'publish' === $this->get_status() || current_user_can( 'edit_post', $this->get_id() ) ), $this );
	}

	/**
	 * The base cost will either be the 'base' cost or the base cost + cheapest staff
	 * @return string
	 */
	public function get_base_cost() {
		$base = $this->get_price();

		if ( $this->has_staff() ) {
			$staff    = $this->get_staff();
			$cheapest = null;

			foreach ( $staff as $staff_member ) {
				if ( is_null( $cheapest ) || $staff_member->get_base_cost() < $cheapest ) {
					$cheapest = $staff_member->get_base_cost();
				}
			}
			$base += $cheapest;
		}

		return $base;
	}

	/**
	 * Return if appointment has extra costs.
	 *
	 * @return bool
	 */
	public function has_additional_costs() {
		if ( $this->get_has_additional_costs() ) {
			return true;
		}

		if ( $this->has_staff() ) {
			foreach ( (array) $this->get_staff() as $staff_member ) {
				if ( $staff_member->get_base_cost() ) {
					return true;
				}
			}
		}

		$costs = $this->get_costs();

		if ( ! empty( $costs ) && $this->get_has_pricing() ) {
			return true;
		}

		return false;
	}

	/**
	 * How staff are assigned.
	 *
	 * @param string $type
	 * @return boolean customer or automatic
	 */
	public function is_staff_assignment_type( $type ) {
		return $this->get_staff_assignment() === $type;
	}

	/**
	 * Checks if a product requires confirmation.
	 *
	 * @return bool
	 */
	public function requires_confirmation() {
		return apply_filters( 'woocommerce_appointment_requires_confirmation', $this->get_requires_confirmation(), $this );
	}

	/**
	 * See if the appointment can be cancelled.
	 *
	 * @return boolean
	 */
	public function can_be_cancelled() {
		return apply_filters( 'woocommerce_appointment_user_can_cancel', $this->get_user_can_cancel(), $this );
	}

	/**
	 * See if the appointment can be rescheduled.
	 *
	 * @return boolean
	 */
	public function can_be_rescheduled() {
		return apply_filters( 'woocommerce_appointment_user_can_reschedule', $this->get_user_can_reschedule(), $this );
	}

	/**
	 * See if the appointment has timezones.
	 *
	 * @return boolean
	 */
	public function has_timezones() {
		return apply_filters( 'woocommerce_appointment_customer_timezones', $this->get_customer_timezones(), $this );
	}

	/**
	 * See if dates are by default appointable.
	 *
	 * @return bool
	 */
	public function get_default_availability() {
		return apply_filters( 'woocommerce_appointment_default_availability', false, $this );
	}

	/**
	 * See if this appointment product has restricted days.
	 *
	 * @return boolean
	 */
	public function has_restricted_days() {
		return $this->get_has_restricted_days();
	}

	/*
	|--------------------------------------------------------------------------
	| Non-CRUD getters
	|--------------------------------------------------------------------------
	*/
	/**
	 * Gets all formatted cost rules.
	 *
	 * @return array
	 */
	public function get_costs() {
		if ( ! $this->get_has_pricing() ) {
			return [];
		}
		return WC_Product_Appointment_Rule_Manager::process_pricing_rules( $this->get_pricing() );
	}

	/**
	 * Get Min date.
	 *
	 * @return array|bool
	 */
	public function get_min_date_a() {
		$min_date['value'] = apply_filters( 'woocommerce_appointments_min_date', $this->get_min_date(), $this->get_id() );
		$min_date['unit']  = $this->get_min_date_unit() ? apply_filters( 'woocommerce_appointments_min_date_unit', $this->get_min_date_unit(), $this->get_id() ) : 'month';
		return $min_date;
	}

	/**
	 * Get max date.
	 *
	 * @return array
	 */
	public function get_max_date_a() {
		$max_date['value'] = $this->get_max_date() ? apply_filters( 'woocommerce_appointments_max_date', $this->get_max_date(), $this->get_id() ) : 1;
		$max_date['unit']  = $this->get_max_date_unit() ? apply_filters( 'woocommerce_appointments_max_date_unit', $this->get_max_date_unit(), $this->get_id() ) : 'month';
		return $max_date;
	}

	/**
	 * Get default intervals.
	 *
	 * @since 3.2.0 introduced.
	 * @param  int $id
	 * @return Array
	 */
	public function get_intervals() {
		$default_interval = 'hour' === $this->get_duration_unit() ? $this->get_duration() * 60 : $this->get_duration();
		$custom_interval  = 'hour' === $this->get_duration_unit() ? $this->get_duration() * 60 : $this->get_duration();
		if ( $this->get_interval_unit() && $this->get_interval() ) {
			$custom_interval = 'hour' === $this->get_interval_unit() ? $this->get_interval() * 60 : $this->get_interval();
			$custom_interval = 'month' === $this->get_duration_unit() ? $this->get_duration() : $custom_interval;
		}

		// Filters for the intervals.
		$default_interval = apply_filters( 'woocommerce_appointments_interval', $default_interval, $this );
		$custom_interval  = apply_filters( 'woocommerce_appointments_base_interval', $custom_interval, $this );
		$intervals        = [ $default_interval, $custom_interval ];

		return $intervals;
	}

	/**
	 * See if this appointment product has any staff.
	 * @return boolean
	 */
	public function has_staff() {
		$count_staff = count( $this->get_staff_ids() );
		return $count_staff ? $count_staff : false;
	}

	/**
	 * Get staff by ID.
	 *
	 * @param  int $id
	 * @return WC_Product_Appointment_Staff object
	 */
	public function get_staff() {
		$product_staff = [];

		foreach ( $this->get_staff_ids() as $staff_id ) {
			$product_staff[] = new WC_Product_Appointment_Staff( $staff_id, $this );
		}

		return $product_staff;
	}

	/**
	 * Get staff member by ID
	 *
	 * @param  int $id
	 * @return WC_Product_Appointment_Staff object
	 */
	public function get_staff_member( $staff_id ) {
		if ( $this->has_staff() && ! empty( $staff_id ) ) {
			$staff_member = new WC_Product_Appointment_Staff( $staff_id, $this );

			return $staff_member;
		}

		return false;
	}

	/**
	 * Get staff members by IDs
	 *
	 * @param  int $id
	 * @param  bool $names
	 * @param  bool $with_link
	 * @return WC_Product_Appointment_Staff object
	 */
	public function get_staff_members( $ids = [], $names = false, $with_link = false ) {
		// If no IDs are give, get all product staff IDs.
		if ( ! $ids ) {
			$ids = $this->get_staff_ids();

			return false;
		}

		return wc_appointments_get_staff_from_ids( $ids, $names, $with_link );
	}

	/**
	 * Get available quantity.
	 *
	 * @since 3.2.0 introduced.
	 * @param $staff_id
	 * @return bool|int
	 */
	public function get_available_qty( $staff_id = '', $no_fallback = false, $individual = false ) {
		$default_qty = $no_fallback ? 0 : $this->get_qty();

		if ( $this->has_staff() ) {
			$qtys       = $this->get_staff_qtys();
			$staff_qty  = 0;
			$staff_qtys = [];

			if ( $staff_id && is_array( $staff_id ) ) {
				foreach ( (array) $staff_id as $staff_member_id ) {
					$qty          = isset( $qtys[ $staff_member_id ] ) && '' !== $qtys[ $staff_member_id ] && 0 !== $qtys[ $staff_member_id ] ? $qtys[ $staff_member_id ] : $default_qty;
					$staff_qtys[] = $qty;
				}
				// Only count when $qtys is an array.
				if ( is_array( $staff_qtys ) && ! empty( $staff_qtys ) ) {
					$staff_qty = $this->is_staff_assignment_type( 'all' ) ? max( $staff_qtys ) : array_sum( $staff_qtys );
				}
			} elseif ( $staff_id && is_numeric( $staff_id ) ) {
				$staff_qty = isset( $qtys[ $staff_id ] ) && '' !== $qtys[ $staff_id ] && 0 !== $qtys[ $staff_id ] ? $qtys[ $staff_id ] : $default_qty;
			} elseif ( ! $staff_id ) {
				foreach ( $this->get_staff_ids() as $staff_member_id ) {
					$staff_qtys[] = isset( $qtys[ $staff_member_id ] ) && '' !== $qtys[ $staff_member_id ] && 0 !== $qtys[ $staff_member_id ] ? $qtys[ $staff_member_id ] : $this->get_qty();
				}
				// Only count when $qtys is an array.
				if ( is_array( $staff_qtys ) && ! empty( $staff_qtys ) ) {
					$staff_qty = $individual || $this->is_staff_assignment_type( 'all' ) ? max( $staff_qtys ) : array_sum( $staff_qtys );
				}
			}

			return apply_filters( 'woocommerce_appointments_get_available_quantity', $staff_qty ? absint( $staff_qty ) : absint( $default_qty ), $this, $staff_id );
		}

		return apply_filters( 'woocommerce_appointments_get_available_quantity', $default_qty, $this, $staff_id );
	}

	/**
	 * Get rules in order of `override power`. The higher the index the higher the override power. Element at index 4 will
	 * override element at index 2.
	 *
	 * Within priority the rules will be ordered top to bottom.
	 *
	 * @return array  availability_rules {
	 *    @type $staff_id => array {
	 *
	 *       The $order_index depicts the levels override. `0` Is the lowest. `1` overrides `0` and `2` overrides `1`.
	 *       e.g. If monday is set to available in `1` and not available in `2` the results should be that Monday is
	 *       NOT available because `2` overrides `1`.
	 *       $order_index corresponds to override power. The higher the element index the higher the override power.
	 *       @type $order_index => array {
	 *          @type string $type   The type of range selected in admin.
	 *          @type string $range  Depending on the type this depicts what range and if available or not.
	 *          @type integer $priority
	 *          @type string $level Global, Product or Staff
	 *          @type integer $order The index for the order set in admin.
	 *      }
	 * }
	 */
	public function get_availability_rules( $for_staff = 0 ) {
		// Default to zero, when no staff is set.
		if ( empty( $for_staff ) ) {
			$for_staff = 0;
		}

		// Repeat the function if staff IDs are in array.
		if ( is_array( $for_staff ) ) {
			$for_all_staff = [];
			foreach ( $for_staff as $for_staff_id ) {
				$for_all_staff[] = $this->get_availability_rules( $for_staff_id );
			}
			return array_merge( ...$for_all_staff );
		}

		if ( ! isset( $this->availability_rules[ $for_staff ] ) ) {
			$this->availability_rules[ $for_staff ] = [];

			// Global and Gcal rules.
			$global_rules = WC_Appointments_Availability_Data_Store::get_global_availability();
			#print '<pre>'; print_r( $global_rules ); print '</pre>';

			// Product rules.
			$product_rules = $this->get_availability();
			#print '<pre>'; print_r( $product_rules ); print '</pre>';

			// Staff rules.
			$staff_rules = [];

			// Get availability of each staff - no staff has been chosen yet.
			if ( $this->has_staff() && ! $for_staff ) {
				// If all slotss are available by default, we should not hide days if we don't know which staff is going to be used.
				if ( ! $this->get_default_availability() ) {
					// Staff rules.
					$staff_rules = WC_Appointments_Availability_Data_Store::get_staff_availability( $this->get_staff_ids() );
					#print '<pre>'; print_r( $staff_rules ); print '</pre>';
				}
			} elseif ( $for_staff ) {

				// Staff rules.
				$staff_object = $this->get_staff_member( $for_staff );
				$staff_rules  = $staff_object ? $staff_object->get_availability() : [];
				#print '<pre>'; print_r( $staff_rules ); print '</pre>';
			}

			// The order that these rules are put into the array are important due to the way that
			// the rules as processed for overrides.
			$availability_rules = array_filter(
				array_merge(
					WC_Product_Appointment_Rule_Manager::process_availability_rules( array_reverse( $global_rules ), 'global' ),
					WC_Product_Appointment_Rule_Manager::process_availability_rules( array_reverse( $product_rules ), 'product' ),
					WC_Product_Appointment_Rule_Manager::process_availability_rules( array_reverse( $staff_rules ), 'staff' )
				)
			);
			#print '<pre>'; print_r( $availability_rules ); print '</pre>';

			usort( $availability_rules, [ $this, 'rule_override_power_sort' ] );

			$this->availability_rules[ $for_staff ] = $availability_rules;
		}

		return apply_filters( 'woocommerce_appointment_get_availability_rules', $this->availability_rules[ $for_staff ], $for_staff, $this );
	}

	/*
	|--------------------------------------------------------------------------
	| Slot calculation functions. @todo move to own manager class
	|--------------------------------------------------------------------------
	*/

	/**
	 * Check the staff availability against all the slots.
	 *
	 * @param  string $start_date
	 * @param  string $end_date
	 * @param  int    $qty
	 * @param  WC_Product_Appointment_Staff|null $product_staff
	 * @return string|WP_Error
	 */
	public function get_slots_availability( $start_date, $end_date, $qty, $staff_id, $intervals = [], $appointments = [] ) {
		$slots              = $this->get_slots_in_range( $start_date, $end_date, $intervals, $staff_id, [], false, false );
		$interval           = $this->get_duration_in_minutes();
		$padding_in_seconds = 0;

		if ( empty( $slots ) || ! in_array( $start_date, $slots ) ) {
			return false;
		}

		#print '<pre>'; print_r( date( 'Ymd H:i', $start_date ) . ' ======= ' . date( 'Ymd H:i', $end_date ) ); print '</pre>';
		#print '<pre>'; print_r( $slots ); print '</pre>';
		#print '<pre>'; print_r( $staff_id ); print '</pre>';

		$available_qtys         = [];
		$original_available_qty = $this->get_available_qty( $staff_id );

		#print '<pre>'; print_r( $slots ); print '</pre>';

		// Check all slots availability.
		foreach ( $slots as $slot ) {
			$qty_scheduled_in_slot = 0;

			// Check capacity based on duration unit.
			if ( in_array( $this->get_duration_unit(), [ 'hour', 'minute' ] ) ) {
				$slot_qty = WC_Product_Appointment_Rule_Manager::check_availability_rules_against_time( $this, $slot, strtotime( "+{$interval} minutes", $slot ), $staff_id, true );
			} else {
				$slot_qty = WC_Product_Appointment_Rule_Manager::check_availability_rules_against_date( $this, $slot, $staff_id, true );
			}

			#print '<pre>'; print_r( date( 'G:i', $slot ) . '___' .'_qty:'. $slot_qty .'__qty_orig:'. $original_available_qty ); print '</pre>';
			#print '<pre>'; print_r( $appointments ); print '</pre>';

			if ( ! empty( $appointments ) ) {
				foreach ( $appointments as $appointment ) {
					// Appointment and Slot start/end timestamps.
					$slot_start        = $slot;
					$slot_end          = strtotime( "+{$interval} minutes", $slot );
					$appointment_start = $appointment['get_start'];
					$appointment_end   = $appointment['get_end'];

					// Product padding?
					$padding_duration_in_minutes = $this->get_padding_duration_in_minutes();
					if ( $padding_duration_in_minutes && in_array( $this->get_duration_unit(), [ 'hour', 'minute', 'day' ] ) ) {
						$appointment_start = strtotime( "-{$padding_duration_in_minutes} minutes", $appointment_start );
						$appointment_end   = strtotime( "+{$padding_duration_in_minutes} minutes", $appointment_end );
					}

					// Is within slot?
					if ( ! $appointment_start || ! $appointment_end || $appointment_start >= $slot_end || $appointment_end <= $slot_start ) {
						continue;
					}

					// When existing appointment is scheduled with another product,
					// remove all available capacity, so staff becomes unavailable for this product.
					if ( $appointment['get_product_id'] !== $this->get_id() && apply_filters( 'wc_apointments_check_appointment_product', true, $appointment['get_id'], $this->get_id() ) ) {
						$qty_to_add = $original_available_qty;
					// Only remove capacity scheduled for existing product.
					} else {
						$qty_to_add = $appointment['get_qty'] ? $appointment['get_qty'] : 1;
					}
					$qty_to_add = apply_filters( 'wc_apointments_check_appointment_qty', $qty_to_add, $appointment, $this );

					$qty_scheduled_in_slot += $qty_to_add;

					// Staff doesn't match, so don't check.
					if ( $staff_id
						&& ! is_array( $staff_id )
					    && $appointment['get_staff_ids']
						&& is_array( $appointment['get_staff_ids'] )
						&& ! in_array( $staff_id, $appointment['get_staff_ids'] ) ) {

						$qty_scheduled_in_slot -= $qty_to_add;

					} elseif ( $staff_id
						&& is_array( $staff_id )
					    && $appointment['get_staff_ids']
						&& is_array( $appointment['get_staff_ids'] )
					 	&& ! array_intersect( $staff_id, $appointment['get_staff_ids'] ) ) {

						$qty_scheduled_in_slot -= $qty_to_add;

					}
				}
			}

			// Calculate available capacity.
			$available_qty = max( $slot_qty - $qty_scheduled_in_slot, 0 );

			#print '<pre>'; print_r( date( 'ymd H:i', $slot ) . '____' . $available_qty .' = '. $slot_qty .' - '. $qty_scheduled_in_slot . ' < ' . $qty . ' staff=' . $staff_id ); print '</pre>';

			// Remaining places are less than requested qty, return an error.
			if ( $available_qty < $qty ) {
				if ( in_array( $this->get_duration_unit(), [ 'hour', 'minute' ] ) ) {
					return new WP_Error(
						'Error',
						sprintf(
							/* translators: %1$d: available quantity %2$s: appointment slot date %3$s: appointment slot time */
							_n( 'There is a maximum of %1$d place remaining on %2$s at %3$s.', 'There are a maximum of %1$d places remaining on %2$s at %3$s.', $available_qty, 'woocommerce-appointments' ),
							max( $available_qty, 0 ),
							date_i18n( wc_appointments_date_format(), $slot ),
							date_i18n( wc_appointments_time_format(), $slot )
						)
					);
				} elseif ( ! $available_qtys ) {
					return new WP_Error(
						'Error',
						sprintf(
							/* translators: %1$d: available quantity %2$s: appointment slot date */
							_n( 'There is a maximum of %1$d place remaining on %2$s', 'There are a maximum of %1$d places remaining on %2$s', $available_qty, 'woocommerce-appointments' ),
							$available_qty,
							date_i18n( wc_appointments_date_format(), $slot )
						)
					);
				} else {
					return new WP_Error(
						'Error',
						sprintf(
							/* translators: %1$d: available quantity %2$s: appointment slot date */
							_n( 'There is a maximum of %1$d place remaining on %2$s', 'There are a maximum of %1$d places remaining on %2$s', $available_qty, 'woocommerce-appointments' ),
							max( $available_qtys ),
							date_i18n( wc_appointments_date_format(), $slot )
						)
					);
				}
			}

			$available_qtys[] = $available_qty;
		}

		return apply_filters(
			'woocommerce_appointments_slots_availability',
			min( $available_qtys ),
			$qty,
			$start_date,
			$end_date,
			$staff_id,
			$this
		);
	}

	/**
	 * Get an array of slots within in a specified date range - might be days, might be slots within days, depending on settings.
	 *
	 * @param       $start_date
	 * @param       $end_date
	 * @param array $intervals
	 * @param int   $staff_id
	 * @param array $scheduled
	 * @param bool  $get_past_times
	 *
	 * @return array
	 */
	public function get_slots_in_range( $start_date, $end_date, $intervals = [], $staff_id = 0, $scheduled = [], $get_past_times = false, $timezone_span = true ) {
		$intervals = empty( $intervals ) ? $this->get_intervals() : $intervals;

		// Span 1 day before and after to account for all timezones.
		if ( $timezone_span ) {
			$start_date = strtotime( '-1 day', $start_date );
			$end_date   = strtotime( '+1 day', $end_date );
		}

		#print '<pre>'; print_r( debug_backtrace() ); print '</pre>';
		#print '<pre>'; print_r( date( 'Y-n-j H:i', $start_date ) .'___'. date( 'Y-n-j H:i', $end_date ) ); print '</pre>';
		#print '<pre>'; print_r( $staff_id ); print '</pre>';

		if ( $this->has_staff() && 0 === $staff_id ) {
			$staff_ids      = $this->get_staff_ids();
			$slots_in_range = [];

			foreach ( $staff_ids as $staff_id ) {
				if ( 'day' === $this->get_duration_unit() ) {
					$slots_in_range_a = $this->get_slots_in_range_for_day( $start_date, $end_date, $staff_id, $scheduled );
				} elseif ( 'month' === $this->get_duration_unit() ) {
					$slots_in_range_a = $this->get_slots_in_range_for_month( $start_date, $end_date, $staff_id );
				} else {
					$slots_in_range_a = $this->get_slots_in_range_for_hour_or_minutes( $start_date, $end_date, $intervals, $staff_id, $scheduled, $get_past_times );
				}

				#print '<pre>'; print_r( $staff_id ); print '</pre>';
				#print '<pre>'; print_r( $slots_in_range ); print '</pre>';

				$slots_in_range = array_merge( $slots_in_range_a, $slots_in_range );
			}
		} else {
			if ( 'day' === $this->get_duration_unit() ) {
				$slots_in_range = $this->get_slots_in_range_for_day( $start_date, $end_date, $staff_id, $scheduled );
			} elseif ( 'month' === $this->get_duration_unit() ) {
				$slots_in_range = $this->get_slots_in_range_for_month( $start_date, $end_date, $staff_id );
			} else {
				$slots_in_range = $this->get_slots_in_range_for_hour_or_minutes( $start_date, $end_date, $intervals, $staff_id, $scheduled, $get_past_times );
			}
		}

		#print '<pre>'; print_r( $slots_in_range ); print '</pre>';

		asort( $slots_in_range ); #sort ascending by value so latest time goes at the end

		#print '<pre>'; print_r( $slots_in_range ); print '</pre>';

		return array_unique( $slots_in_range );
	}

	/**
	 * Get slots/day slots in range for day duration unit.
	 *
	 * @param $start_date
	 * @param $end_date
	 * @param $staff_id
	 * @param $scheduled
	 *
	 * @return array
	 */
	public function get_slots_in_range_for_day( $start_date, $end_date, $staff_id, $scheduled ) {
		$slots = [];

		// get scheduled days with a counter to specify how many appointments on that date
		$scheduled_days_with_count = [];
		foreach ( $scheduled as $appointment ) {
			$appointment_start       = $appointment[0];
			$appointment_end         = $appointment[1];
			$current_appointment_day = $appointment_start;

			// < because appointment end depicts an end of a day and not a start for a new day.
			while ( $current_appointment_day < $appointment_end ) {
				$date = date( 'Y-m-d', $current_appointment_day );

				if ( isset( $scheduled_days_with_count[ $date  ] ) ) {
					$scheduled_days_with_count[ $date ]++;
				} else {
					$scheduled_days_with_count[ $date ] = 1;
				}

				$current_appointment_day = strtotime( '+1 day', $current_appointment_day );
			}
		}

		// If exists always treat scheduling_period in minutes.
		$check_date = $start_date;

		$end_date = $this->get_max_allowed_date_into_the_future( $end_date );

		while ( $check_date <= $end_date ) {
			if ( WC_Product_Appointment_Rule_Manager::check_availability_rules_against_date( $this, $check_date, $staff_id ) ) {
				$available_qty = WC_Product_Appointment_Rule_Manager::check_availability_rules_against_date( $this, $check_date, $staff_id, true );
				$date          = date( 'Y-m-d', $check_date );
				if ( ! isset( $scheduled_days_with_count[ $date ] ) || $scheduled_days_with_count[ $date ] < $available_qty ) {
					$slots[] = $check_date;
				}
			}

			// move to next day
			$check_date = strtotime( '+1 day', $check_date );
		}

		return $slots;
	}

	/**
	 * For months, loop each month in the range to find slots.
	 *
	 * @param $start_date
	 * @param $end_date
	 * @param integer $staff_id
	 *
	 * @return array
	 */
	public function get_slots_in_range_for_month( $start_date, $end_date, $staff_id ) {
		$slots = [];

		if ( 'month' !== $this->get_duration_unit() ) {
			return $slots;
		}

		$end_date = $this->get_max_allowed_date_into_the_future( $end_date );

		// Generate a range of slots for months
		$from       = strtotime( date( 'Y-m-01', $start_date ) );
		$to         = strtotime( date( 'Y-m-t', $end_date ) );
		$month_diff = 0;
		$month_from = strtotime( '+1 MONTH', $from );

		while ( $month_from <= $to ) {
			$month_from = strtotime( '+1 MONTH', $month_from );
			$month_diff ++;
		}

		for ( $i = 0; $i <= $month_diff; $i ++ ) {
			$year  = date( 'Y', ( $i ? strtotime( "+ {$i} month", $from ) : $from ) );
			$month = date( 'n', ( $i ? strtotime( "+ {$i} month", $from ) : $from ) );

			if ( ! WC_Product_Appointment_Rule_Manager::check_availability_rules_against_date( $this, strtotime( "{$year}-{$month}-01" ), $staff_id, true ) ) {
				continue;
			}

			$slots[] = strtotime( "+ {$i} month", $from );
		}

		return $slots;
	}

	/**
	 * Get slots in range for hour or minute duration unit.
	 * For minutes and hours find valid slots within THIS DAY ($check_date)
	 *
	 * @param $start_date
	 * @param $end_date
	 * @param $intervals
	 * @param $staff_id
	 * @param $scheduled
	 * @param $get_past_times
	 *
	 * @return array
	 */
	public function get_slots_in_range_for_hour_or_minutes( $start_date, $end_date, $intervals, $staff_id, $scheduled, $get_past_times ) {
		// Setup.
		$slot_start_times_in_range   = [];
		$minutes_not_available       = [];
		$interval                    = $intervals[0]; #duration
		$check_date                  = $start_date;
		$first_slot_time_minute      = 0;
		$default_appointable_minutes = $this->get_default_availability() ? range( $first_slot_time_minute, ( 1440 + $interval ) ) : [];
		$rules                       = $this->get_availability_rules( $staff_id ); // Work out what minutes are actually appointable on this dayž
		$end_date                    = $this->get_max_allowed_date_into_the_future( $end_date );

		#print '<pre>'; print_r( date( 'Y-n-j H:i', $check_date ) .'___'. date( 'Y-n-j H:i', $end_date ) ); print '</pre>';
		#print '<pre>'; print_r( $rules ); print '</pre>';

		// Get available slot start times.
		#$minutes_not_available = $this->get_unavailable_minutes( $scheduled ); // Get unavailable slot start times.
		#print '<pre>'; print_r( $minutes_not_available ); print '</pre>';

		// Looping day by day look for available slots.
		while ( $check_date <= $end_date ) {
			#print '<pre>'; print_r( $check_date ); print '</pre>';
			$appointable_minutes_for_date = array_merge( $default_appointable_minutes, WC_Product_Appointment_Rule_Manager::get_minutes_from_rules( $rules, $check_date ) );
			#print '<pre>'; print_r( $appointable_minutes_for_date ); print '</pre>';
			// Run through rules only when minutes are not empty.
			if ( $appointable_minutes_for_date ) {
				if ( ! $this->get_default_availability() ) {
					// From an array of minutes for a day, remove all minutes before first slot time.
					$appointable_minutes_for_date = array_filter(
						$appointable_minutes_for_date,
						function( $minute ) use ( $first_slot_time_minute ) {
							return $first_slot_time_minute <= $minute;
						}
					);
				}
				#print '<pre>'; print_r( $appointable_minutes_for_date ); print '</pre>';
				$appointable_start_and_end = $this->get_appointable_minute_start_and_end( $appointable_minutes_for_date );
				#print '<pre>'; print_r( $appointable_start_and_end ); print '</pre>';
				$slots = $this->get_appointable_minute_slots_for_date( $check_date, $start_date, $end_date, $appointable_start_and_end, $intervals, $staff_id, $minutes_not_available, $get_past_times );
				#print '<pre>'; print_r( $slots ); print '</pre>';
				$slot_start_times_in_range = array_merge( $slots, $slot_start_times_in_range );
				#print '<pre>'; print_r( $slot_start_times_in_range ); print '</pre>';
			}

			$check_date = strtotime( '+1 day', $check_date ); // Move to the next day
		}

		return $slot_start_times_in_range;
	}

	/**
	 * From an array of minutes for a day remove all minutes before first slot time.
	 * @since 3.3.0
	 *
	 * @param array $appointable_minutes
	 * @param int $first_slot_minutes
	 *
	 * @return array $minutes
	 */
	public function apply_first_slot_time( $appointable_minutes, $first_slot_minutes ) {
		_deprecated_function( __METHOD__, '4.2.0' );

		$minutes = [];
		foreach ( $appointable_minutes as $minute ) {
			if ( $first_slot_minutes <= $minute ) {
				$minutes[] = $minute;
			}
		}
		return $minutes;
	}

	/**
	 * @param array $appointable_minutes
	 *
	 * @return array
	 */
	public function get_appointable_minute_start_and_end( $appointable_minutes ) {
		// Break appointable minutes into sequences - appointments cannot have breaks
		$appointable_minute_slots     = [];
		$appointable_minute_slot_from = current( $appointable_minutes );

		foreach ( $appointable_minutes as $key => $minute ) {
			if ( isset( $appointable_minutes[ $key + 1 ] ) ) {
				if ( $appointable_minutes[ $key + 1 ] - 1 === $minute ) {
					continue;
				} else {
					// There was a break in the sequence
					$appointable_minute_slots[]   = [ $appointable_minute_slot_from, $minute + 1 ];
					$appointable_minute_slot_from = $appointable_minutes[ $key + 1 ];
				}
			} else {
				// We're at the end of the appointable minutes
				$appointable_minute_slots[] = [ $appointable_minute_slot_from, $minute + 1 ];
			}
		}

		/*
		// Find slots that don't span any amount of time (same start + end)
		foreach ( $appointable_minute_slots as $key => $appointable_minute_slot ) {
			if ( $appointable_minute_slot[0] === $appointable_minute_slot[1] ) {
				$keys_to_remove[] = $key; // track which slots need removed
			}
		}
		// Remove all of our slots
		if ( ! empty( $keys_to_remove ) ) {
			foreach ( $keys_to_remove as $key ) {
				unset( $appointable_minute_slots[ $key ] );
			}
		}
		*/

		return $appointable_minute_slots;
	}

	/**
	 * Return an array of that is not available for appointment.
	 *
	 * @since 2.3.0 introduced.
	 * @since 4.10.5 disabled as setting unavailable minutes should only apply with no staff.
	 *
	 * @param array $scheduled. Pairs of scheduled slot start and end times.
	 * @return array $scheduled_minutes
	 */
	public function get_unavailable_minutes( $scheduled ) {
		$minutes_not_available = [];
		$padding               = ( $this->get_padding_duration_in_minutes() ?: 0 ) * 60;
		#print '<pre>'; print_r( $padding ); print '</pre>';
		foreach ( $scheduled as $scheduled_slot ) {
			$start = $scheduled_slot[0] - $padding;
			$end   = $scheduled_slot[1] + $padding;
			for ( $i = $start; $i < $end; $i += 60 ) {
				$minutes_not_available[] = $i; #previously set as: array_push( $minutes_not_available, $i );
			}
		}

		$minutes_not_available = array_count_values( $minutes_not_available );

		return $minutes_not_available;
	}

	/**
	 * Returns slots/time slots from a given start and end minute slots.
	 *
	 * This function take varied inputs but always retruns a slot array of available slots.
	 * Sometimes it gets the minutes and see if all is available some times it needs to make up the
	 * minutes based on what is scheduled.
	 *
	 * It uses start and end date to figure things out.
	 *
	 * @since 2.3.0 introduced.
	 *
	 * @param $check_date
	 * @param $start_date
	 * @param $end_date
	 * @param $appointable_start_and_end
	 * @param $intervals
	 * @param $staff_id
	 * @param $minutes_not_available
	 * @param $get_past_times
	 *
	 * @return array
	 */
	public function get_appointable_minute_slots_for_date( $check_date, $start_date, $end_date, $appointable_start_and_end, $intervals, $staff_id, $minutes_not_available, $get_past_times ) {
		// slots as in an array of slots. $slot_start_times
		$slots = [];

		// boring interval stuff
		$interval      = $intervals[0]; #duration
		$base_interval = $intervals[1]; #interval

		// get a time stamp to check from and get a time stamp to check to
		$product_min_date = $this->get_min_date_a();
		$product_max_date = $this->get_max_date_a();

		#print '<pre>'; print_r( $product_min_date ); print '</pre>';
		#print '<pre>'; print_r( $product_max_date ); print '</pre>';

		$min_check_from = strtotime( "+{$product_min_date['value']} {$product_min_date['unit']}", current_time( 'timestamp' ) );
		$max_check_to   = strtotime( "+{$product_max_date['value']} {$product_max_date['unit']}", current_time( 'timestamp' ) );
		$min_date       = wc_appointments_get_min_timestamp_for_day( $start_date, $product_min_date['value'], $product_min_date['unit'] );

		#print '<pre>'; print_r( date( 'Y-m-d H:i', $min_check_from ) ); print '</pre>';
		#print '<pre>'; print_r( date( 'Y-m-d H:i', $max_check_to ) ); print '</pre>';
		#print '<pre>'; print_r( date( 'Y-m-d H:i', $min_date ) ); print '</pre>';

		$current_time_stamp = current_time( 'timestamp' );

		// if we have a padding, we will shift all times accordingly by changing the from_interval
		// e.g. 60 min paddingpadding shifts [ 480, 600, 720 ] into [ 480, 660, 840 ]
		#$padding = $this->get_padding_duration_in_minutes() ?: 0;

		#print '<pre>'; print_r( $check_date ); print '</pre>';
		#print '<pre>'; print_r( $appointable_start_and_end ); print '</pre>';
		#print '<pre>'; print_r( $minutes_not_available ); print '</pre>';

		// Loop the slots of appointable minutes and add a slot if there is enough room to book
		foreach ( $appointable_start_and_end as $time_slot ) {
			$range_start = $time_slot[0];
			$range_end   = $time_slot[1];

			/*
			// Adding 1 minute to round up to a full hour.
			if ( 'hour' === $this->get_duration_unit() ) {
				$range_end  += 1;
			}
			*/

			/*
			$time_slot_start        = strtotime( "midnight +{$range_start} minutes", $check_date );
			$minutes_in_slot        = $range_end - $range_start;
			$base_intervals_in_slot = floor( $minutes_in_slot / $base_interval );
			$time_slot_end_time 	= strtotime( "midnight +{$range_end} minutes", $check_date );
			*/

			$range_start_time       = strtotime( "midnight +{$range_start} minutes", $check_date );
			$range_end_time         = strtotime( "midnight +{$range_end} minutes", $check_date );
			$minutes_for_range      = $range_end - $range_start;
			$base_intervals_in_slot = floor( $minutes_for_range / $base_interval );

			// Only need to check first hour.
			if ( 'start' === $this->get_availability_span() ) {
				$base_interval          = 1; #test
				$base_intervals_in_slot = 1; #test
			}

			for ( $i = 0; $i < $base_intervals_in_slot; $i++ ) {
				#$from_interval = $i * ( $base_interval + $padding );
				$from_interval = $i * $base_interval;
				$to_interval   = $from_interval + $interval;
				$start_time    = strtotime( "+{$from_interval} minutes", $range_start_time );
				$end_time      = strtotime( "+{$to_interval} minutes", $range_start_time );

				#print '<pre>'; print_r( '$stime: ' . date('Y-n-j H:i', $start_time) ); print '</pre>';
				#print '<pre>'; print_r( '$etime: ' . date('Y-n-j H:i', $end_time) ); print '</pre>';

				// Remove 00:00 or 24:00 for same day slot.
				#if ( strtotime( 'midnight +1 day', $start_date ) === $start_time ) {
					#continue;
				#}

				// Available quantity.
				$available_qty = WC_Product_Appointment_Rule_Manager::check_availability_rules_against_time( $this, $start_time, $end_time, $staff_id, 1 );
				#$available_qty = $this->get_available_qty( $staff_id ); // exact quantity is checked in get_available_slots_html() function
				#print '<pre>'; print_r( date('Y-n-j H:i', $check_date) . '......' . date( 'Y-n-j H:i', $start_time ) . '.' . date( 'Y-n-j H:i', $end_time ) . '___' . $available_qty . '___' . $staff_id ); print '</pre>';

				// Staff must be available or skip if no staff and no availability.
				if ( ( ! $available_qty && $staff_id ) || ( ! $this->has_staff() && ! $available_qty ) ) {
					continue;
				}

				// Break if start time is after the end date being calculated.
				if ( $start_time > $end_date && ( 'start' !== $this->get_availability_span() ) ) {
					break 2;
				}

				#print '<pre>'; print_r( date( 'Y-m-d H:i', $start_time ) .' < '. date( 'Y-m-d H:i', $min_check_from ) ); print '</pre>';

				// Must be in the future.
				if ( ( $start_time < $min_date || $start_time <= $current_time_stamp ) && ! $get_past_times ) {
					continue;
				}

				/*
				// Disabled with 4.10.0.
				// Skip if start minutes not available.
				if ( isset( $minutes_not_available[ $start_time ] )
				    && ! $this->is_staff_assignment_type( 'all' )
				    && $minutes_not_available[ $start_time ] >= $available_qty ) {
					continue;
				}

				// Skip if any minute is not available.
				// Not when checking availability against starting slot only.
				if ( 'start' !== $this->get_availability_span()
			        && ! $this->is_staff_assignment_type( 'all' ) ) {
					$interval_not_appointable = false;
					// Check if any minute of slot is not within not available minutes.
					for ( $t = $start_time; $t < $end_time; $t += 60 ) {
						if ( isset( $minutes_not_available[ $t ] ) && $minutes_not_available[ $t ] >= $available_qty ) {
							$interval_not_appointable = true;
							break;
						}
					}

					if ( $interval_not_appointable ) {
						continue;
					}
				}
				*/

				// Make sure minute & hour slots are not past minimum & max appointment settings.
				if ( ( $start_time < $min_check_from || $end_time < $min_check_from || $start_time > $max_check_to ) && ! $get_past_times ) {
					continue;
				}

				// Make sure slot doesn't start after the end date.
				if ( $start_time > $end_date ) {
					continue;
				}

				/*
				// Skip if end time bigger than slot end time.
				// thrown out as it prevented 24/7 businesses last slot buildup
				if ( $end_time > $time_slot_end_time && ( 'start' !== $this->get_availability_span() ) ) {
					continue;
				}
				*/

				if ( ! in_array( $start_time, $slots ) ) {
					$slots[] = $start_time;
				}
			}
		}

		#var_dump( $slots );

		return $slots;
	}

	/**
	 * Returns available slots from a range of slots by looking at existing appointments.
	 *
	 * @param  array $args
	 *     @option  array   $slots      The slots we'll be checking availability for.
	 *     @option  array   $intervals   Array containing 2 items; the interval of the slot (maybe user set), and the base interval for the slot/product.
	 *     @option  integer $staff_id Resource we're getting slots for. Falls backs to product as a whole if 0.
	 *     @option  integer $from        The starting date for the set of slots
	 *     @option  integer $to          Ending date for the set of slots
	 *
	 * @return array The available slots array
	 */
	public function get_available_slots( $args ) {
		$args = wp_parse_args(
			$args,
			[
				'slots'        => [],
				'intervals'    => [],
				'staff_id'     => 0,
				'from_range'   => '',
				'to_range'     => '',
				'from'         => '',
				'to'           => '',
				'appointments' => 0,
			]
		);

		$slots        = $args['slots'];
		$intervals    = $args['intervals'];
		$staff_id     = $args['staff_id'];
		$from         = $args['from'];
		$to           = $args['to'];
		$appointments = $args['appointments'];
		// Appointments not defined. Run a query.
		if ( 0 === $appointments ) {
			$appointments          = [];
			$existing_appointments = WC_Appointment_Data_Store::get_all_existing_appointments( $this, $from, $to, $staff_id );
			if ( ! empty( $existing_appointments ) ) {
				foreach ( $existing_appointments as $existing_appointment ) {
					#print '<pre>'; print_r( $existing_appointment->get_id() ); print '</pre>';
					$appointments[] = [
						'get_staff_ids'  => $existing_appointment->get_staff_ids(),
						'get_start'      => $existing_appointment->get_start(),
						'get_end'        => $existing_appointment->get_end(),
						'get_qty'        => $existing_appointment->get_qty(),
						'get_id'         => $existing_appointment->get_id(),
						'get_product_id' => $existing_appointment->get_product_id(),
						'is_all_day'     => $existing_appointment->is_all_day(),
					];
				}
			}
		}

		$intervals = empty( $intervals ) ? $this->get_intervals() : $intervals;

 		list( $interval, $base_interval ) = $intervals;

 		$available_times = [];

		$start_date = $from;
		if ( empty( $start_date ) ) {
			$start_date = reset( $slots );
		}

		$end_date = $to;
		if ( empty( $end_date ) ) {
			$end_date = absint( end( $slots ) );
		}

		$product_staff = $this->has_staff() && ! $staff_id ? $this->get_staff_ids() : $staff_id;

		$original_available_qty = $this->get_available_qty( $staff_id );
		#print '<pre>'; print_r( $original_available_qty ); print '</pre>';

 		if ( ! empty( $slots ) ) {

 			// Staff scheduled array. Staff can be a "staff" but also just an appointment if it has no staff.
 			$staff_scheduled   = [
				0 => [],
			];
 			$product_scheduled = [
				0 => [],
			];

			if ( ! empty( $appointments ) ) {
				foreach ( $appointments as $appointment ) {
					$appointment_staff_ids  = $appointment['get_staff_ids'];
	 				$appointment_product_id = $appointment['get_product_id'];

					// Staff doesn't match, so don't check.
					if ( $product_staff
						&& ! is_array( $product_staff )
					    && $appointment_staff_ids
					 	&& is_array( $appointment_staff_ids )
					 	&& ! in_array( $product_staff, $appointment_staff_ids ) ) {

						continue;

					} elseif ( $product_staff
						&& is_array( $product_staff )
					    && $appointment_staff_ids
						&& is_array( $appointment_staff_ids )
						&& ! array_intersect( $product_staff, $appointment_staff_ids ) ) {

						continue;

					}

	 				// Prepare staff and product array.
	 				foreach ( (array) $appointment_staff_ids as $appointment_staff_id ) {
	 					$staff_scheduled[ $appointment_staff_id ] = $staff_scheduled[ $appointment_staff_id ] ?? [];
	 				}
	 				$product_scheduled[ $appointment_product_id ] = $product_scheduled[ $appointment_product_id ] ?? [];

	 				// Slot start/end time.
					$start_time = $appointment['get_start'];
					$end_time   = $appointment['get_end'];

	 				// Existing appointment lasts all day, force end day time.
	 				if ( $appointment['is_all_day'] && in_array( $this->get_duration_unit(), [ 'minute', 'hour' ] ) ) {
	 					$end_time = strtotime( 'midnight +1 day', $end_time );
	 				}

	 				// Product duration set to day, force daily check
	 				if ( 'day' === $this->get_duration_unit() ) {
	 					$start_time = strtotime( 'midnight', $start_time );
	 					$end_time   = strtotime( 'midnight +1 day', $end_time );
	 				}

	 				// When existing appointment is scheduled with another product,
	 				// remove all available capacity, so staff becomes unavailable for this product.
	 				if ( $appointment_product_id !== $this->get_id() && apply_filters( 'wc_apointments_check_appointment_product', true, $appointment['get_id'], $this->get_id() ) ) {
						$repeat = max( 1, $original_available_qty );
	 				// Only remove capacity scheduled for existing product.
	 				} else {
	 					$repeat = max( 1, $appointment['get_qty'] );
	 				}
					$repeat = apply_filters( 'wc_apointments_check_appointment_qty', $repeat, $appointment, $this );

	 				// Repeat to add capacity for each scheduled qty.
	 				foreach ( (array) $appointment_staff_ids as $appointment_staff_id ) {
	 					for ( $i = 0; $i < $repeat; $i++ ) {
	 						array_push( $staff_scheduled[ $appointment_staff_id ], [ $start_time, $end_time ] );
	 					}
	 				}
	 				for ( $i = 0; $i < $repeat; $i++ ) {
	 					array_push( $product_scheduled[ $appointment_product_id ], [ $start_time, $end_time ] );
	 				}
	 			}
			}

 			// Available times for product: Generate arrays that contain information about what slots to unset.
			#print '<pre>'; print_r( 'TEST' ); print '</pre>';
			$available_times = array_merge( $available_times, $this->get_slots_in_range( $start_date, $end_date, [ $interval, $base_interval ], $product_staff ) );
			#$available_times = $this->get_slots_in_range( $start_date, $end_date, [ $interval, $base_interval ], $product_staff );
			#print '<pre>'; print_r( $product_scheduled ); print '</pre>';
			#print '<pre>'; print_r( $staff_scheduled ); print '</pre>';

			/*
 			// Test
 			$test = [];
 			foreach ( $available_times as $available_time ) {
 				$test[] = date( 'Y-m-d H:i', $available_time );
 			}
 			print '<pre>'; print_r( $test ); print '</pre>';
			*/

 			if ( $this->has_staff() ) {

				// Build staff times array.
				$staff_times = [];

				// Loop through all staff in array.
				if ( ! empty( $staff_scheduled ) && is_array( $product_staff ) ) {
					foreach ( $product_staff as $product_staff_id ) {
						$staff_appointments = $staff_scheduled[ $product_staff_id ] ?? [];
                        $staff_times_a      = $this->get_slots_in_range( $start_date, $end_date, [ $interval, $base_interval ], $product_staff_id, $staff_appointments );
						#$staff_times_a      = $this->get_slots_in_range( $start_date, $end_date, [ $interval, $base_interval ], $product_staff_id );
						#print '<pre>'; print_r( $staff_times_a ); print '</pre>';
						$staff_times = array_merge( $staff_times_a, $staff_times );
					}
					#print '<pre>'; print_r( $staff_times ); print '</pre>';

				// Single staff.
				} elseif ( isset( $staff_scheduled[ $staff_id ] ) && ! empty( $staff_scheduled[ $staff_id ] ) ) {
					$staff_appointments = $staff_scheduled[ $staff_id ] ?? [];
                    $staff_times        = $this->get_slots_in_range( $start_date, $end_date, [ $interval, $base_interval ], $staff_id, $staff_appointments );
					#print '<pre>'; print_r( $staff_times ); print '</pre>';

				// When scheduling outside of staff scope.
				} elseif ( isset( $staff_scheduled[0] ) ) {
					$staff_appointments = $staff_scheduled[0] ?? [];
					$staff_times        = $this->get_slots_in_range( $start_date, $end_date, [ $interval, $base_interval ], $staff_id, $staff_appointments );
					#print '<pre>'; print_r( $staff_times ); print '</pre>';
				// Everything else.
				} else {
					$staff_times = $this->get_slots_in_range( $start_date, $end_date, [ $interval, $base_interval ], $staff_id, [] );
					#print '<pre>'; print_r( $staff_times ); print '</pre>';
				}

				#print '<pre>'; print_r( $staff_times ); print '</pre>';

				// No preference selected.
				if ( ! $staff_id ) {
					$available_times = array_merge( $available_times, $staff_times ); #add times from staff to times from product
				// Staff selected.
				} else {
					$available_times = array_intersect( $available_times, $staff_times ); #merge times from staff that are also available in product
				}
				#print '<pre>'; print_r( $available_times ); print '</pre>';
 			}

 			/*
 			// Test
 			$test2 = [];
 			foreach ( $available_times as $available_slot ) {
 				$test2[] = date( 'y-m-d H:i', $available_slot );
 			}
			print '<pre>'; print_r( $test2 ); print '</pre>';
 			*/
 		}

		$available_times = array_unique( $available_times );
		sort( $available_times );

		/*
		// Test
		$test = [];
		foreach ( $available_times as $available_slot ) {
			$test[] = date( 'y-m-d H:i', $available_slot );
		}
		print '<pre>'; print_r( $test ); print '</pre>';
		*/

		/**
		 * Filter the available slots for a product within a given range
		 *
		 * @since 1.9.8 introduced
		 *
		 * @param array $available_times
		 * @param WC_Product $appointments_product
		 * @param array $raw_range passed into this function.
		 * @param array $intervals
		 * @param integer $staff_id
		 */
		return apply_filters( 'wc_appointments_product_get_available_slots', $available_times, $this, $slots, $intervals, $staff_id );
 	}

	/**
	 * Get the availability of all staff
	 *
	 * @param string $start_date
	 * @param string $end_date
	 * @param integer $qty
	 * @return array| WP_Error
	 */
	public function get_all_staff_availability( $start_date, $end_date, $qty ) {
		$staff_ids       = $this->get_staff_ids();
		$available_staff = [];

		foreach ( $staff_ids as $staff_id ) {
			$availability = wc_appointments_get_total_available_appointments_for_range( $this, $start_date, $end_date, $staff_id, $qty );

			if ( $availability && ! is_wp_error( $availability ) ) {
				$available_staff[ $staff_id ] = $availability;
			}
		}

		if ( empty( $available_staff ) ) {
			return new WP_Error( 'Error', __( 'This slot cannot be scheduled.', 'woocommerce-appointments' ) );
		}

		return $available_staff;
	}


	/*
	|--------------------------------------------------------------------------
	| Deprecated Methods
	|--------------------------------------------------------------------------
	*/

	/**
	 * Get the minutes that should be available based on the rules and the date to check.
	 *
	 * The minutes are returned in a range from the start incrementing minutes right up to the last available minute.
	 *
	 * @deprecated since 2.6.5
	 * @param array $rules
	 * @param int $check_date
	 * @return array $appointable_minutes
	 */
	public function get_minutes_from_rules( $rules, $check_date ) {
		return WC_Product_Appointment_Rule_Manager::get_minutes_from_rules( $rules, $check_date );
	}

	/**
	 * Find the minimum slot's timestamp based on settings.
	 *
	 * @deprecated Replaced with wc_appointments_get_min_timestamp_for_day
	 * @return int
	 */
	public function get_min_timestamp_for_date( $start_date, $product_min_date_value, $product_min_date_unit ) {
		return wc_appointments_get_min_timestamp_for_day( $start_date, $product_min_date_value, $product_min_date_unit );
	}

	/**
	 * Sort rules.
	 *
	 * @deprecated Replaced with WC_Product_Appointment_Rule_Manager::sort_rules_callback
	 */
	public function rule_override_power_sort( $rule1, $rule2 ) {
		return WC_Product_Appointment_Rule_Manager::sort_rules_callback( $rule1, $rule2 );
	}

	/**
	 * Return an array of staff which can be scheduled for a defined start/end date
	 *
	 * @deprecated Replaced with wc_appointments_get_slot_availability_for_range
	 * @param  string $start_date
	 * @param  string $end_date
	 * @param  string $staff_id
	 * @param  integer $qty being scheduled
	 * @return bool|WP_ERROR if no slots available, or int count of appointments that can be made, or array of available staff
	 */
	public function get_available_appointments( $start_date, $end_date, $staff_id = '', $qty = 1 ) {
		return wc_appointments_get_total_available_appointments_for_range( $this, $start_date, $end_date, $staff_id, $qty );
	}

	/**
	 * Get existing appointments in a given date range
	 *
	 * @param string $start_date
	 * @param string $end_date
	 * @param int    $staff_id
	 * @return array
	 */
	public function get_appointments_in_date_range( $start_date, $end_date, $staff_id = null ) {
		if ( $this->has_staff() && $staff_id ) {
			if ( ! is_array( $staff_id ) ) {
				$staff_id = [ $staff_id ];
			}
		} elseif ( $this->has_staff() && ! $staff_id ) {
			$staff_id = $this->get_staff_ids();
		}

		return WC_Appointment_Data_Store::get_all_existing_appointments( $this->get_id(), $start_date, $end_date, $staff_id );
		#return WC_Appointment_Data_Store::get_appointments_in_date_range( $start_date, $end_date, $this->get_id(), $staff_id );
	}

	/**
	 * Check a date against the availability rules
	 *
	 * @param  string $check_date date to check
	 * @return bool available or not
	 */
	public function check_availability_rules_against_date( $check_date, $staff_id, $get_capacity = false ) {
		return WC_Product_Appointment_Rule_Manager::check_availability_rules_against_date( $this, $check_date, $staff_id, $get_capacity );
	}

	/**
	 * Check a time against the time specific availability rules
	 *
	 * @param  string  $slot_start_time timestamp to check
	 * @param  string  $slot_end_time   timestamp to check
	 * @return bool    available or not
	 */
	public function check_availability_rules_against_time( $slot_start_time, $slot_end_time, $staff_id, $get_capacity = false ) {
		return WC_Product_Appointment_Rule_Manager::check_availability_rules_against_time( $this, $slot_start_time, $slot_end_time, $staff_id, $get_capacity );
	}

	/**
	 * Checks appointment data is correctly set, and that the chosen slots are indeed available.
	 *
	 * @since 4.7.0
	 *
	 * @param  array $data
	 *
	 * @return bool|WP_Error on failure, true on success
	 */
	public function is_appointable( $data ) {
		// Validate staff are set
		if ( $this->has_staff() && $this->is_staff_assignment_type( 'customer' ) ) {
			if ( empty( $data['_staff_id'] ) ) {
				// return new WP_Error( 'Error', sprintf( __( 'Please choose the %s.', 'woocommerce-appointments' ), $this->get_staff_label() ? $this->get_staff_label() : __( 'Providers', 'woocommerce-appointments' ) ) );
				$data['_staff_id'] = 0;
			}
		} elseif ( $this->has_staff() && $this->is_staff_assignment_type( 'automatic' ) ) {
			$data['_staff_id'] = 0;
		} elseif ( $this->has_staff() && $this->is_staff_assignment_type( 'all' ) ) {
			$data['_staff_id'] = 0;
		} else {
			$data['_staff_id'] = '';
		}

		// Validate date and time
		if ( empty( $data['date'] ) ) {
			return new WP_Error( 'Error', __( 'Date is required - please choose one above', 'woocommerce-appointments' ) );
		}
		if ( in_array( $this->get_duration_unit(), [ 'minute', 'hour' ] ) && empty( $data['time'] ) ) {
			return new WP_Error( 'Error', __( 'Time is required - please choose one above', 'woocommerce-appointments' ) );
		}
		if ( $data['_date'] && date( 'Ymd', strtotime( $data['_date'] ) ) < date( 'Ymd', current_time( 'timestamp' ) ) ) {
			return new WP_Error( 'Error', __( 'You must choose a future date and time.', 'woocommerce-appointments' ) );
		}
		if ( $data['_date'] && ! empty( $data['_time'] ) && date( 'YmdHi', strtotime( $data['_date'] . ' ' . $data['_time'] ) ) < date( 'YmdHi', current_time( 'timestamp' ) ) ) {
			return new WP_Error( 'Error', __( 'You must choose a future date and time.', 'woocommerce-appointments' ) );
		}

		// Validate min date and max date
		if ( in_array( $this->get_duration_unit(), [ 'minute', 'hour' ] ) ) {
			$now = current_time( 'timestamp' );
		} elseif ( 'month' === $this->get_duration_unit() ) {
			$now = strtotime( 'midnight first day of this month', current_time( 'timestamp' ) );
		} else {
			$now = strtotime( 'midnight', current_time( 'timestamp' ) );
		}

		$min = $this->get_min_date_a();
		if ( $min ) {
			$min_date = wc_appointments_get_min_timestamp_for_day( strtotime( $data['_date'] ), $min['value'], $min['unit'] );

			if ( strtotime( $data['_date'] . ' ' . $data['_time'] ) < $min_date ) {
				/* translators: %s: minimum date */
				return new WP_Error( 'Error', sprintf( __( 'The earliest appointment possible is currently %s.', 'woocommerce-appointments' ), date_i18n( wc_appointments_date_format() . ' ' . wc_appointments_time_format(), $min_date ) ) );
			}
		}

		$max = $this->get_max_date_a();
		if ( $max ) {
			$max_date = strtotime( "+{$max['value']} {$max['unit']}", $now );
			if ( strtotime( $data['_date'] . ' ' . $data['_time'] ) > $max_date ) {
				/* translators: %s: maximum date */
				return new WP_Error( 'Error', sprintf( __( 'The latest appointment possible is currently %s.', 'woocommerce-appointments' ), date_i18n( wc_appointments_date_format() . ' ' . wc_appointments_time_format(), $max_date ) ) );
			}
		}

		// Check that the day of the week is not restricted.
		if ( $this->has_restricted_days() ) {
			$restricted_days = (array) $this->get_restricted_days();

			if ( ! in_array( date( 'w', $data['_start_date'] ), $restricted_days ) ) {
				return new WP_Error( 'Error', __( 'Sorry, appointments cannot start on this day.', 'woocommerce-appointments' ) );
			}
		}

		// Get availability for the dates
		$available_appointments = wc_appointments_get_total_available_appointments_for_range( $this, $data['_start_date'], $data['_end_date'], $data['_staff_id'], $data['_qty'] );

		#print '<pre>'; print_r( $available_appointments ); print '</pre>';

		if ( is_array( $available_appointments ) ) {
			$this->assigned_staff_id = current( array_keys( $available_appointments ) );
		}

		if ( is_wp_error( $available_appointments ) ) {
			return $available_appointments;
		} elseif ( ! $available_appointments ) {
			return new WP_Error( 'Error', __( 'Sorry, the selected slot is not available.', 'woocommerce-appointments' ) );
		}

		return true;
	}

	/**
	 * Compares the provided date with the appointment max allowed date and returns
	 * the earliest.
	 *
	 * @since 4.8.14
	 * @param  int $end_date Date to compare.
	 * @return int
	 */
	private function get_max_allowed_date_into_the_future( $end_date ) {
		$product_max_date = $this->get_max_date();

		if ( $product_max_date ) {
			return $end_date;
		}

		$max_end_date = strtotime( "+{$product_max_date['value']} {$product_max_date['unit']}", current_time( 'timestamp' ) );

		return $end_date > $max_end_date ? $max_end_date : $end_date;
	}

	/**
	 * Find available and scheduled slots for specific staff (if any) and return them as array.
	 *
	 * @param  array $args
	 *     @option  array   $slots          The slots we'll be checking availability for.
	 *     @option  array   $intervals      Array containing 2 items; the interval of the slot (maybe user set), and the base interval for the slot/product.
	 *     @option  integer $staff_id       Resource we're getting slots for. Falls backs to product as a whole if 0.
	 *     @option  integer $time_to_check  Specific time checking.
	 *     @option  integer $from           The starting date for the set of slots.
	 *     @option  integer $to             Ending date for the set of slots.
	 *     @option  string  $timezone       Timezone string.
	 *     @option  array   $appointments   Scheduled appointments.
	 *
	 * @return array
	 *
	 * @version  1.10.5
	 */
	public function get_time_slots( $args ) {
		$args = apply_filters(
			'woocommerce_appointments_time_slots_args',
			wp_parse_args(
				$args,
				[
					'slots'            => [],
					'intervals'        => [],
					'staff_id'         => 0,
					'time_to_check'    => 0,
					'from'             => 0,
					'to'               => 0,
					'timezone'         => 'UTC',
					'appointments'     => [],
					'include_sold_out' => false,
				]
			),
			$this
		);

		$slots            = $args['slots'];
		$intervals        = $args['intervals'];
		$staff_id         = $args['staff_id'];
		$time_to_check    = $args['time_to_check'];
		$from             = $args['from'];
		$to               = $args['to'];
		$timezone         = $args['timezone'];
		$appointments     = $args['appointments'];
		$include_sold_out = $args['include_sold_out'];

		// Caching.
		$transient_name                   = 'schedule_ts_' . md5( http_build_query( [ $this->get_id(), $staff_id, $from, $to ] ) );
		$appointment_slots_transient_keys = array_filter( (array) WC_Appointments_Cache::get( 'appointment_slots_transient_keys' ) );
		$available_slots                  = WC_Appointments_Cache::get( $transient_name );

		// Should we serve the old availability until the action scheduler runs or not.
		$old_availability_served = false;

		// Action Scheduler Event exists or not. Set to false by default.
		$clear_cache_event      = false;
		$cache_update_scheduled = get_post_meta( $this->get_id(), 'cache_update_scheduled-' . $transient_name );

		// If event is created show the old availability (i.e. from transient) to the user.
		// The $arg_list[7] is $timezone_offset, this is required to check event's existence.
		// The $arg_list[8] is $source, this is to know if the request is coming from the 'user'
		// or this is a call from the event itself (i.e. 'action-scheduler').
		if ( $cache_update_scheduled && isset( $args['timezone_offset'] ) && isset( $args['source'] ) && 'user' === $args['source'] ) {
			// If the action scheduler event is not set,
			// or it is not running right now (i.e. 'cache_update_started-..' meta does not exist),
			// force clear the transient now. Otherwise, keep serving the old availability.
			$clear_cache_event    = as_has_scheduled_action( 'wc-appointment-scheduled-update-availability', array( $this->get_id(), $from, $to, $args['timezone_offset'] ) );
			$cache_update_started = get_post_meta( $this->get_id(), 'cache_update_started-' . $transient_name, true );
			if ( ! $clear_cache_event && ! $cache_update_started ) {
				update_post_meta( $this->get_id(), 'cache_update_started', true );
				WC_Appointments_Cache::delete_appointment_slots_transient();
			} else {
				// Old availability should be served until the action is fired.
				$old_availability_served = true;
			}
		}

		// If we have to serve the old availability, but we don't have availability slots in transient,
		// Let's see if we have 'prev-availability' transient available (that is created by the event scheduler).
		if ( $old_availability_served && ! $available_slots ) {
			// If not available, set it to `false` to prevent `Illegal string offset` issue.
			$available_slots = WC_Appointments_Cache::get( 'prev-availability-' . $transient_name ) ?: false;
		}

		if ( ! isset( $appointment_slots_transient_keys[ $this->get_id() ] ) ) {
			$appointment_slots_transient_keys[ $this->get_id() ] = array();
		}

		if ( ! in_array( $transient_name, $appointment_slots_transient_keys[ $this->get_id() ] ) ) {
			$appointment_slots_transient_keys[ $this->get_id() ][] = $transient_name;
			// Give array of keys a long ttl because if it expires we won't be able to flush the keys when needed.
			// We can't use 0 to never expire because then WordPress will autoload the option on every page.
			WC_Appointments_Cache::set( 'appointment_slots_transient_keys', $appointment_slots_transient_keys, YEAR_IN_SECONDS );
		}

		if ( false === $available_slots ) {

			$intervals = empty( $intervals ) ? $this->get_intervals() : $intervals;

			list( $interval, $base_interval ) = $intervals;
			$interval                         = 'start' === $this->get_availability_span() ? $base_interval : $interval;

			$product_staff = $staff_id ? $staff_id : 0;
			$product_staff = $this->has_staff() && ! $product_staff ? $this->get_staff_ids() : $product_staff;

			sort( $slots );

			#print '<pre>'; print_r( $slots ); print '</pre>';

			// List only available slots.
			if ( ! $include_sold_out ) {
				$slots = $this->get_available_slots(
					[
						'slots'        => $slots,
						'intervals'    => $intervals,
						'staff_id'     => $staff_id,
						'from'         => $from,
						'to'           => $to,
						'appointments' => $appointments,
					]
				);
			}

			#print '<pre>'; print_r( $slots ); print '</pre>';

			if ( empty( $slots ) ) {
				return [];
			}

			$available_slots                 = [];
			$product_available_qty           = $this->get_available_qty();
			$product_has_staff               = $this->has_staff();
			$product_duration_unit           = $this->get_duration_unit();
			$padding_duration_in_minutes     = $this->get_padding_duration_in_minutes();
			$product_is_staff_assignment_all = $this->is_staff_assignment_type( 'all' );
			$product_id                      = $this->get_id();

			foreach ( $slots as $slot ) {
				$staff         = [];
				$available_qty = 0;

				// Make sure default staff qty is set.
				// Used for google calendar events in most cases.
				$staff[0] = $product_available_qty;

				#print '<pre>'; print_r( date( 'Y-m-d G:i', $slot ) ); print '</pre>';

				// Figure out how much qty have, either based on combined staff quantity,
				// single staff, or just product.
				if ( $product_has_staff && $product_staff && is_array( $product_staff ) ) {
					$staff_qtys = [];
					foreach ( $product_staff as $product_staff_id ) {
						// Only include if it is available for this selection.
						if ( ! WC_Product_Appointment_Rule_Manager::check_availability_rules_against_date( $this, $slot, $product_staff_id ) ) {
							$staff[ $product_staff_id ] = 0;
							continue;
						}

						// Get qty based on duration unit.
						if ( in_array( $product_duration_unit, [ 'minute', 'hour' ] ) ) {
							$check_rules_against_time = WC_Product_Appointment_Rule_Manager::check_availability_rules_against_time( $this, $slot, strtotime( "+{$interval} minutes", $slot ), $product_staff_id, true );

							if ( ! $check_rules_against_time || 0 === $check_rules_against_time ) {
								$staff[ $product_staff_id ] = 0;
								continue;
							}

							$get_available_qty = $check_rules_against_time;
						} else {
							$get_available_qty = WC_Product_Appointment_Rule_Manager::check_availability_rules_against_date( $this, $slot, $product_staff_id, true );
						}

						$staff_qtys[] = $get_available_qty;

						$staff[ $product_staff_id ] = $get_available_qty;

						$available_qty += $get_available_qty;
					}

					$max_available_qty = is_array( $staff_qtys ) && ! empty( $staff_qtys ) ? max( $staff_qtys ) : 0;

					#print '<pre>'; print_r( $staff ); print '</pre>';

				} elseif ( $product_has_staff && $product_staff && is_int( $product_staff ) ) {
					// Get qty based on duration unit.
					$check_rules_against_time = WC_Product_Appointment_Rule_Manager::check_availability_rules_against_time( $this, $slot, strtotime( "+{$interval} minutes", $slot ), $product_staff, true );
					if ( ! $check_rules_against_time || 0 === $check_rules_against_time ) {
						$staff[ $product_staff ] = 0;
						continue;
					}

					// Get qty based on duration unit.
					if ( in_array( $product_duration_unit, [ 'minute', 'hour' ] ) ) {
						$get_available_qty = $check_rules_against_time;
					} else {
						$get_available_qty = WC_Product_Appointment_Rule_Manager::check_availability_rules_against_date( $this, $slot, $product_staff, true );
					}

					$staff[ $product_staff ] = $get_available_qty;

					$available_qty += $get_available_qty;

					$max_available_qty = $get_available_qty;
				} else {
					// Get qty based on duration unit.
					if ( in_array( $product_duration_unit, [ 'minute', 'hour' ] ) ) {
						$get_available_qty = WC_Product_Appointment_Rule_Manager::check_availability_rules_against_time( $this, $slot, strtotime( "+{$interval} minutes", $slot ), $product_staff, true );
					} else {
						$get_available_qty = WC_Product_Appointment_Rule_Manager::check_availability_rules_against_date( $this, $slot, $product_staff, true );
					}

					$staff[0] = $get_available_qty;

					$available_qty += $get_available_qty;

					$max_available_qty = $get_available_qty;
				}

				$qty_scheduled_in_slot   = 0;
				$qty_scheduled_for_staff = [];

				#error_log( var_export( date( 'Y-m-d G:i', $slot ), true ) );
				#error_log( var_export( $staff, true ) );
				#print '<pre>'; print_r( date( 'Y-m-d G:i', $slot ) ); print '</pre>';
				#print '<pre>'; print_r( $max_available_qty ); print '</pre>';
				#print '<pre>'; print_r( $get_available_qty ); print '</pre>';
				#print '<pre>'; print_r( $staff ); print '</pre>';
				#print '<pre>'; print_r( $product_staff ); print '</pre>';
				#print '<pre>'; print_r( $available_qty ); print '</pre>';

				// All staff assigned at once.
				if ( $product_is_staff_assignment_all && $product_staff && is_array( $product_staff ) ) {
					$count_all_staff       = count( $product_staff );
					$count_available_staff = count( $staff ) - 1;

					// Set $available_qty to zero, when any of the staff is not available.
					if ( $count_all_staff !== $count_available_staff ) {
						$available_qty = 0;
					}
				}

				$qty_to_add = 1;

				if ( $appointments ) {
					foreach ( $appointments as $appointment ) {
						// Appointment and Slot start/end timestamps.
						$slot_start        = $slot;
						$slot_end          = strtotime( "+{$interval} minutes", $slot );
						$appointment_start = $appointment['get_start'];
						$appointment_end   = $appointment['get_end'];

						// Account for padding?
						if ( $padding_duration_in_minutes && in_array( $product_duration_unit, [ 'hour', 'minute', 'day' ] ) ) {
							if ( ! empty( $appointment_start ) ) {
								$appointment_start = strtotime( "-{$padding_duration_in_minutes} minutes", $appointment_start );
							}
							if ( ! empty( $appointment_end ) ) {
								$appointment_end = strtotime( "+{$padding_duration_in_minutes} minutes", $appointment_end );
							}
						}

						// Is within slot?
						if ( ! $appointment_start || ! $appointment_end || $appointment_start >= $slot_end || $appointment_end <= $slot_start ) {
							continue;
						}

						#error_log( var_export( $appointment['get_id'], true ) );
						#error_log( var_export( date( 'G:i', $appointment_start ) . ' == ' . date( 'G:i', $appointment_end ), true ) );
						#error_log( var_export( date( 'G:i', $slot_start ) . ' == ' . date( 'G:i', $slot_end ), true ) );

						// Appointment quantity.
						$qty_to_add = $appointment['get_qty'] ? $appointment['get_qty'] : 1;

						// Reset quantity for staff on each loop.
						$qty_scheduled_for_staff = [];

						// Appointment has staff?
						if ( $product_has_staff ) {
							// Get staff IDs. If no staff, make it zero (applies to all).
							$appointment_staff_ids = $appointment['get_staff_ids'];
							$appointment_staff_ids = ! is_array( $appointment_staff_ids ) ? [ $appointment_staff_ids ] : $appointment_staff_ids;
							$appointment_staff_ids = empty( $appointment_staff_ids ) ? [ 0 ] : $appointment_staff_ids;

							if (
								$product_staff
								&& ! is_array( $product_staff )
								&& $appointment_staff_ids
								&& is_array( $appointment_staff_ids )
								&& in_array( $product_staff, $appointment_staff_ids )
							) {
								foreach ( $appointment_staff_ids as $appointment_staff_id ) {
									if ( $appointment['get_product_id'] !== $product_id && apply_filters( 'wc_apointments_check_appointment_product', true, $appointment['get_id'], $product_id ) ) {
										$qty_to_add = $this->get_available_qty( $appointment_staff_id );
									}
									$qty_to_add                      = apply_filters( 'wc_apointments_check_appointment_qty', $qty_to_add, $appointment, $this );
									$qty_scheduled_for_staff[]       = $qty_to_add;
									$appointment_staff_id_qty_scheduled = ( $staff[ $appointment_staff_id ] ?? 0 ) - $qty_to_add;
									$appointment_staff_id_qty_available = max( $appointment_staff_id_qty_scheduled, 0 ); #when negative, turn to zero.
									$staff[ $appointment_staff_id ]     = $appointment_staff_id_qty_available;
								}
								$qty_scheduled_in_slot += max( $qty_scheduled_for_staff );
								#print '<pre>'; print_r( date( 'Y-m-d G:i', $slot ) ); print '</pre>';
								#print '<pre>'; print_r( $qty_scheduled_for_staff ); print '</pre>';
								#print '<pre>'; print_r( $qty_scheduled_in_slot ); print '</pre>';

							} elseif (
								$product_staff
								&& is_array( $product_staff )
								&& $appointment_staff_ids
								&& is_array( $appointment_staff_ids )
								&& array_intersect( $product_staff, $appointment_staff_ids )
							) {
								foreach ( $appointment_staff_ids as $appointment_staff_id ) {
									if ( $appointment['get_product_id'] !== $product_id && apply_filters( 'wc_apointments_check_appointment_product', true, $appointment['get_id'], $product_id ) ) {
										$qty_to_add = $this->get_available_qty( $appointment_staff_id );
									}
									$qty_to_add                      = apply_filters( 'wc_apointments_check_appointment_qty', $qty_to_add, $appointment, $this );
									$qty_scheduled_for_staff[]       = $qty_to_add;
									$appointment_staff_id_qty_scheduled = ( $staff[ $appointment_staff_id ] ?? 0 ) - $qty_to_add;
									$appointment_staff_id_qty_available = max( $appointment_staff_id_qty_scheduled, 0 ); #when negative, turn to zero.
									$staff[ $appointment_staff_id ]     = $appointment_staff_id_qty_available;

									// All staff assigned at once.
									if ( $product_is_staff_assignment_all && 0 === $appointment_staff_id_qty_available ) {
										// Set $available_qty to zero, staff is not available.
										$available_qty = 0;
									}
								}
								$qty_scheduled_in_slot += max( $qty_scheduled_for_staff );

							} else {
								$staff[0] = ( $staff[0] ?? 0 ) - $qty_to_add;
							}
						} else {
							$qty_scheduled_in_slot += $qty_to_add;
							$staff[0]               = ( $staff[0] ?? 0 ) - $qty_to_add;
						}

						$qty_scheduled_in_slot = apply_filters(
							'wc_apointments_qty_scheduled_in_slot',
							$qty_scheduled_in_slot,
							$appointment,
							$this
						);
					}
				}

				if ( ! $available_qty && ! $include_sold_out ) {
					continue;
				}

				// Available qty in slot.
				$qty_available_in_slot = $available_qty - $qty_scheduled_in_slot;

				if ( 0 > $qty_available_in_slot && ! $include_sold_out ) {
					continue;
				}

				$available_slots[ $slot ] = apply_filters(
					'woocommerce_appointments_time_slot',
					[
						'scheduled' => $qty_scheduled_in_slot,
						'available' => $qty_available_in_slot,
						'staff'     => $staff,
					],
					$slot,
					$qty_scheduled_in_slot,
					$available_qty,
					$staff,
					$this,
					$staff_id,
					$appointments
				);

				#error_log( var_export( date( 'G:i', $slot ), true ) );
				#error_log( var_export( $qty_scheduled_in_slot, true ) );
				#error_log( var_export( $available_slots, true ) );

				#print '<pre>'; print_r( date( 'G:i', $slot ) ); print '</pre>';
				#print '<pre>'; print_r( $qty_scheduled_in_slot ); print '</pre>';
				#print '<pre>'; print_r( $staff ); print '</pre>';
				#print '<pre>'; print_r( $staff_id ); print '</pre>';
				#print '<pre>'; print_r( $available_qty ); print '</pre>';
				#print '<pre>'; print_r( $available_slots ); print '</pre>';
				#print '<pre>'; print_r( $appointments ); print '</pre>';
			}

			WC_Appointments_Cache::set( $transient_name, $available_slots );

			// Delete cache schedule tracking metas.
			delete_post_meta( $product_id, 'cache_update_scheduled-' . $transient_name );
			delete_post_meta( $product_id, 'cache_update_started-' . $transient_name );

			// Delete the backup of previous availability.
			WC_Appointments_Cache::delete( 'prev-availability-' . $transient_name );

			// Reset Flag.
			$old_availability_served = false;

			// Delete if the action is still exists. We don't need it anymore as the availability is generated!
			// This is possible when the cache is cleared from somewhere else or by another action.
			// Note: The $arg_list[7] is already set if `$clear_cache_event` is set. No need to check with `isset()`.
			if ( $clear_cache_event ) {
				$timezone_offset = isset( $args['timezone_offset'] ) ? $args['timezone_offset'] : 0;
				as_unschedule_action( 'wc-appointment-scheduled-update-availability', array( $product_id, $from, $to, $timezone_offset ) );
			}

		}

		// Return `old_availability` flag to the browser, so we can display a message
		// to the users that the old availability is being served to them or the latest one is being served.
		if ( $old_availability_served ) {
			$available_slots['old_availability'] = $old_availability_served;
		}

		return apply_filters(
			'woocommerce_appointments_time_slots',
			$available_slots,
			$slots,
			$intervals,
			$time_to_check,
			$staff_id,
			$from,
			$to,
			$timezone,
			$this,
			$appointments
		);

	}

	/**
	 * Find available slots and return HTML for the user to choose a slot. Used in class-wc-appointments-ajax.php.
	 *
	 * @param  array $args
	 *     @option  array   $slots          The slots we'll be checking availability for.
	 *     @option  array   $intervals      Array containing 2 items; the interval of the slot (maybe user set), and the base interval for the slot/product.
	 *     @option  integer $staff_id       Resource we're getting slots for. Falls backs to product as a whole if 0.
	 *     @option  integer $time_to_check  Specific time checking.
	 *     @option  integer $from           The starting date for the set of slots.
	 *     @option  integer $to             Ending date for the set of slots.
	 *     @option  string  $timezone       Timezone string.
	 *     @option  integer $timestamp      Selected timestamp.
	 *     @option  array   $appointments   Scheduled appointments.
	 *
	 * @return string
	 *
	 * @version  3.3.0
	 */
	public function get_time_slots_html( $args ) {
		$args = apply_filters(
			'woocommerce_appointments_time_slots_html_args',
			wp_parse_args(
				$args,
				[
					'slots'            => [],
					'intervals'        => [],
					'staff_id'         => 0,
					'time_to_check'    => 0,
					'from'             => 0,
					'to'               => 0,
					'timestamp'        => 0,
					'timezone'         => 'UTC',
					'appointments'     => [],
					'include_sold_out' => false,
				]
			),
			$this
		);

		$slots            = $args['slots'];
		$intervals        = $args['intervals'];
		$staff_id         = $args['staff_id'];
		$time_to_check    = $args['time_to_check'];
		$from             = $args['from'];
		$to               = $args['to'];
		$timestamp        = $args['timestamp'];
		$timezone         = $args['timezone'];
		$appointments     = $args['appointments'];
		$include_sold_out = $args['include_sold_out'];

		$available_slots = $this->get_time_slots(
			[
				'slots'         => $slots,
				'intervals'     => $intervals,
				'staff_id'      => $staff_id,
				'time_to_check' => $time_to_check,
				'from'          => $from,
				'to'            => $to,
				'timezone'      => $timezone,
				'appointments'  => $appointments,
			]
		);
		$slots_html      = '';

		#print '<pre>'; print_r( $slots ); print '</pre>';
		#print '<pre>'; print_r( $available_slots ); print '</pre>';

		if ( $available_slots ) {

			// Timezones.
			$timezone_datetime = new DateTime();
			$local_time        = $this->has_timezones() ? wc_appointment_timezone_locale( 'site', 'user', $timezone_datetime->getTimestamp(), wc_appointments_time_format(), $timezone ) : '';
			$site_time         = $this->has_timezones() ? wc_appointment_timezone_locale( 'site', 'user', $timezone_datetime->getTimestamp(), wc_appointments_time_format(), wc_timezone_string() ) : '';

			#print '<pre>'; print_r( $timezone ); print '</pre>';
			#print '<pre>'; print_r( $local_time ); print '</pre>';
			#print '<pre>'; print_r( $site_time ); print '</pre>';

			// Split day into three parts
			$times = apply_filters(
				'woocommerce_appointments_times_split',
				[
					'morning'   => [
						'name' => __( 'Morning', 'woocommerce-appointments' ),
						'from' => strtotime( '00:00' ),
						'to'   => strtotime( '12:00' ),
					],
					'afternoon' => [
						'name' => __( 'Afternoon', 'woocommerce-appointments' ),
						'from' => strtotime( '12:00' ),
						'to'   => strtotime( '17:00' ),
					],
					'evening'   => [
						'name' => __( 'Evening', 'woocommerce-appointments' ),
						'from' => strtotime( '17:00' ),
						'to'   => strtotime( '24:00' ),
					],
				]
			);

			$slots_html .= "<div class=\"slot_row\">";
			foreach ( $times as $k => $v ) {
				$slots_html .= "<ul class=\"slot_column $k\">";
				$slots_html .= '<li class="slot_heading">' . $v['name'] . '</li>';
				$count       = 0;

			 	foreach ( $available_slots as $slot => $quantity ) {

					$local_slot   = $this->has_timezones() ? wc_appointment_timezone_locale( 'site', 'user', $slot, 'U', $timezone ) : $slot;
					$display_slot = ( $this->has_timezones() && $local_time !== $site_time ) ? $local_slot : $slot;

					/*
					// Used for testing.
					if ( '23:00' === date_i18n( 'H:i', $local_slot ) ) {
						print '<pre>'; print_r( date_i18n( 'Y.m.d H:i', $slot ) . ' -- ' . date_i18n( 'Y.m.d H:i', $local_slot ) ); print '</pre>';
					}
					*/

					// Skip dates that are from different days (used for timezones).
					if ( date( 'Y.m.d', $timestamp ) !== date_i18n( 'Y.m.d', $local_slot ) ) {
						continue;
					}

					if ( $v['from'] <= strtotime( date( 'G:i', $display_slot ) ) && $v['to'] > strtotime( date( 'G:i', $display_slot ) ) ) {
						$selected = $time_to_check && date( 'G:i', $slot ) === date( 'G:i', $time_to_check ) ? ' selected' : '';

						#print '<pre>'; print_r( date( 'Hi', $slot ) ); print '</pre>';
						#print '<pre>'; print_r( $quantity ); print '</pre>';

						// Available quantity should be max per staff and not max overall.
						if ( is_array( $quantity['staff'] ) && 1 < count( $quantity['staff'] ) ) {
							unset( $quantity['staff'][0] );
							$quantity_available     = absint( max( $quantity['staff'] ) );
							$quantity_all_available = absint( array_sum( $quantity['staff'] ) );
							if ( 0 === $staff_id && $quantity_available !== $quantity_all_available ) {
								$quantity_available = ( $this->get_qty_max() < $quantity_available ) ? $this->get_qty_max() : $quantity_available;
								/* translators: %d: quantity */
								$spaces_left = sprintf( _n( '%d max', '%d max', $quantity_available, 'woocommerce-appointments' ), $quantity_available );
								/* translators: %d: quantity */
								$spaces_left .= ', ' . sprintf( _n( '%d left', '%d left', $quantity_all_available, 'woocommerce-appointments' ), $quantity_all_available );
							} else {
								/* translators: %d: quantity */
								$spaces_left = sprintf( _n( '%d left', '%d left', $quantity_available, 'woocommerce-appointments' ), $quantity_available );
							}
						} else {
							$quantity_available = absint( $quantity['available'] );
							/* translators: %d: quantity */
							$spaces_left = sprintf( _n( '%d left', '%d left', $quantity_available, 'woocommerce-appointments' ), $quantity_available );
						}

						#print '<pre>'; print_r( date( 'Hi', $slot ) ); print '</pre>';
						#print '<pre>'; print_r( $quantity_available ); print '</pre>';
						#print '<pre>'; print_r( $quantity_all_available ); print '</pre>';
						#print '<pre>'; print_r( $staff_id ); print '</pre>';

						if ( $quantity_available > 0 ) {
							if ( $quantity['scheduled'] ) {
								/* translators: %d: quantity */
				 				$slot_html = "<li class=\"slot$selected\" data-slot=\"" . esc_attr( date( 'Hi', $display_slot ) ) . "\" data-remaining=\"" . esc_attr( $quantity['available'] ) . "\"><a href=\"#\" data-value=\"" . date_i18n( 'G:i', $display_slot ) . "\">" . date_i18n( wc_appointments_time_format(), $display_slot ) . " <small class=\"spaces-left\">" . $spaces_left . "</small></a></li>";
				 			} else {
				 				$slot_html = "<li class=\"slot$selected\" data-slot=\"" . esc_attr( date( 'Hi', $display_slot ) ) . "\" data-remaining=\"" . esc_attr( $quantity['available'] ) . "\"><a href=\"#\" data-value=\"" . date_i18n( 'G:i', $display_slot ) . "\">" . date_i18n( wc_appointments_time_format(), $display_slot ) . "</a></li>";
				 			}
							$slots_html .= apply_filters( 'woocommerce_appointments_time_slot_html', $slot_html, $display_slot, $quantity, $time_to_check, $staff_id, $timezone, $this, $spaces_left, $appointments );
						} elseif ( 0 === $quantity_available && $include_sold_out ) {
							/* translators: %d: quantity */
							$slot_html   = "<li class=\"slot$selected\" data-slot=\"" . esc_attr( date( 'Hi', $display_slot ) ) . "\" data-remaining=\"0\"><span data-value=\"" . date_i18n( 'G:i', $display_slot ) . "\">" . date_i18n( wc_appointments_time_format(), $display_slot ) . " <small class=\"spaces-left\">" . $spaces_left . "</small></span></li>";
							$slots_html .= apply_filters( 'woocommerce_appointments_time_slot_html', $slot_html, $display_slot, $quantity, $time_to_check, $staff_id, $timezone, $this, $spaces_left, $appointments );
						} else {
							continue;
						}
					} else {
						continue;
					}

					$count++;
			 	}

				if ( ! $count ) {
					$slots_html .= '<li class="slot slot_empty">' . __( '&#45;', 'woocommerce-appointments' ) . '</li>';
				}

				$slots_html .= "</ul>";
			}

			$slots_html .= "</div>";
		}

	 	return apply_filters( 'woocommerce_appointments_time_slots_html', $slots_html, $slots, $intervals, $time_to_check, $staff_id, $from, $to, $timezone, $this, $appointments );
	}
}
