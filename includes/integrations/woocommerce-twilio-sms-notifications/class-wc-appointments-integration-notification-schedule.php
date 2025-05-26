<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Appointments notification schedule field helper object.
 *
 * This helper class renders the HTML to display a notification schedule field.
 *
 * This field's value is a string in the format d:s+ (example: 5:days) which
 * determines the value and modifier (example: 5 days) of the appointment
 * notification schedule.
 *
 * @since 4.4.0
 */
class WC_Appointments_Integration_Notification_Schedule {

	/** @var string the character used to separate value and modifier in a string (example: 5:days) */
	private static $delimiter = ':';


	/** @var null|string schedule value as a string in the format d:s+ (example: 5:days) */
	protected $value;

	/** @var int the schedule number representing the amount of days, hours or minutes */
	protected $number;

	/** @var null|string the schedule modifier representing minutes, hours, or days */
	protected $modifier;


	/**
	 * Price adjustment constructor.
	 *
	 * @since 4.4.0
	 *
	 * @param null|string $value string in the format d:s+ (example: 5:days)
	 */
	public function __construct( $value = null ) {

		if ( $value ) {
			$this->value = $value;

			$parsed_values = $this->parse_value( $this->value );

			if ( $this->is_valid_modifier_option( $parsed_values[1] ) ) {

				$this->number   = (int) $parsed_values[0];
				$this->modifier = $parsed_values[1];
			}
		}
	}


	/**
	 * Parse a string value into a number and modifier.
	 *
	 * @since 4.4.0
	 *
	 * @param string $value string in the format d:s+ (example: 5:days)
	 * @return null|array array with indexes:
	 *   0 - number
	 *   1 - modifier
	 */
	private function parse_value( $value ) {

		$values = null;

		if ( strstr( $value, self::$delimiter ) ) {

			$values = explode( self::$delimiter, $value );
		}

		return $values;
	}


	/**
	 * Sets the appointment schedule value.
	 *
	 * Accepts a number and modifier and builds a value string.
	 *
	 * @since 4.4.0
	 *
	 * @param int $number the schedule number representing the amount of days, hours or minutes
	 * @param string $modifier appointment schedule modifier as defined in get_modifier_options()
	 */
	public function set_value( $number, $modifier ) {

		if ( $this->is_valid_modifier_option( $modifier ) ) {

			$number = (int) $number;

			$this->value = $number . self::$delimiter . $modifier;
		}
	}


	/**
	 * Returns the appointment schedule raw value.
	 *
	 * @since 4.4.0
	 *
	 * @return string
	 */
	public function get_value() {

		return $this->value;
	}


	/**
	 * Subtracts the time represented by this appointment notification schedule from an existing timestamp.
	 *
	 * @since 4.4.0
	 *
	 * @param int $timestamp the timestamp to subtract this schedule from
	 */
	public function get_time_before( $timestamp ) {

		$seconds = $this->get_value_in_seconds();

		return $timestamp - $seconds;
	}


	/**
	 * Adds the time represented by this appointment notification schedule to an existing timestamp.
	 *
	 * @since 4.4.0
	 *
	 * @param int $timestamp the timestamp to add this schedule to
	 */
	public function get_time_after( $timestamp ) {

		$seconds = $this->get_value_in_seconds();

		return $timestamp + $seconds;
	}


	/**
	 * Returns a reminder number limited to 48 hours based on the modifier.
	 *
	 * @since 4.4.0
	 *
	 * @param int $number the schedule number representing the amount of days, hours or minutes
	 * @param string $modifier appointment schedule modifier as defined in get_modifier_options()
	 * @return int the schedule number limited to 48 hours
	 */
	public function get_restricted_reminder( $number, $modifier ) {

		$restricted_number = $number;

		if ( 'minutes' === $modifier && $number > ( 60 * 48 ) ) {

			$restricted_number = 60 * 48;
		} elseif ( 'hours' === $modifier && $number > 48 ) {

			$restricted_number = 48;
		} elseif ( 'days' === $modifier && $number > 2 ) {

			$restricted_number = 2;
		}

		return $restricted_number;
	}


	/**
	 * Returns the number of seconds this appointment notification schedule represents.
	 *
	 * @since 4.4.0
	 *
	 * @return int the appointment notification schedule converted to seconds
	 */
	private function get_value_in_seconds() {

		$seconds = 0;

		if ( 'minutes' === $this->modifier ) {

			$seconds = $this->number * 60;
		} elseif ( 'hours' === $this->modifier ) {

			$seconds = $this->number * 60 * 60;
		} elseif ( 'days' === $this->modifier ) {

			$seconds = $this->number * 60 * 60 * 24;
		}

		return $seconds;
	}


	/**
	 * Returns an array of modifiers for use with the appointment schedule field.
	 *
	 * @since 4.4.0
	 *
	 * @return array of possible schedule modifiers
	 */
	public function get_modifier_options() {

		return array(
			'minutes' => __( 'Minute(s)', 'woocommerce-appointments' ),
			'hours'   => __( 'Hour(s)', 'woocommerce-appointments' ),
			'days'    => __( 'Day(s)', 'woocommerce-appointments' ),
		);
	}


	/**
	 * Determines if an appointment schedule modifier is a valid type.
	 *
	 * @since 4.4.0
	 *
	 * @param string $modifier the modifier to test
	 * @return bool
	 */
	public function is_valid_modifier_option( $modifier ) {

		return in_array( $modifier, array_keys( $this->get_modifier_options() ), true );
	}


	/**
	 * Returns the appointment schedule field HTML.
	 *
	 * @since 4.4.0
	 *
	 * @param array $args array of field arguments
	 * @param bool $as_table true if HTML should be output as a table row
	 * @return string HTML
	 */
	public function get_field_html( $args, $as_table = false ) {

		$field_id       = $args['id'];
		$field_number   = $field_id . '_number';
		$field_modifier = $field_id . '_modifier';

		$modifier_options = $this->get_modifier_options();

		ob_start();

		if ( $as_table ) :
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="<?php esc_attr_e( $field_number ); ?>"><?php esc_html_e( $args['title'], 'woocommerce-appointments' ); ?></label>
			</th>
			<td>
		<?php else : ?>
			<label for="<?php esc_attr_e( $field_number ); ?>"><?php esc_html_e( $args['title'], 'woocommerce-appointments' ); ?></label>
		<?php endif; ?>
			<input
				type="number"
				id="<?php esc_attr_e( $field_number ); ?>"
				name="<?php esc_attr_e( $field_number ); ?>"
				value="<?php esc_attr_e( $this->number ); ?>"
				style="max-width: 50px;"
				placeholder="0"
				step="1"
				min="0"
			/>
			<select
				id="<?php esc_attr_e( $field_modifier ); ?>"
				name="<?php esc_attr_e( $field_modifier ); ?>"
				class="select wc-twilio-sms-dropdown"
				style="max-width: 90px;"
			>
			<?php foreach ( $modifier_options as $modifier_id => $modifier_name ) { ?>
				<option
					value="<?php esc_attr_e( $modifier_id ); ?>"
					title="<?php esc_attr_e( $modifier_id ); ?>"
					<?php selected( $modifier_id, $this->modifier, true ); ?>><?php esc_attr_e( $modifier_name ); ?></option>
			<?php } ?>
			</select>
			<span class="wc-twilio-sms-post-field" style="display: inline-block; margin-left: 1em; vertical-align: bottom;"><i><?php esc_html_e( $args['post_field'] ); ?></i></span>
		<?php
		if ( $as_table ) {
		?>
			</td>
		</tr>
		<?php
		}

		return ob_get_clean();
	}

}

new WC_Appointments_Integration_Notification_Schedule();
