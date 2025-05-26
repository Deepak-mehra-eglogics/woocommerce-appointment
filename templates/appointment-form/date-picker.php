<?php
/**
 * Date picker
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/appointment-form/date-picker.php.
 *
 * HOWEVER, on occasion we will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see         https://docs.woocommerce.com/document/template-structure/
 * @version     4.14.0
 * @since       1.0.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

wp_enqueue_script( 'wc-appointments-date-picker' );
wp_enqueue_script( 'wc-appointments-appointment-form' );

// Fields located inside includes\appointment-form\class-wc-appointment-form-date-picker.php.
$class                = $field['class'];
$label                = $field['label'];
$name                 = $field['name'];
$default_availability = $field['default_availability'];
$min_date_js          = $field['min_date_js'];
$max_date_js          = $field['max_date_js'];
$default_date         = $field['default_date'];
$product_type         = $field['product_type'];
$product_id           = $field['product_id'];
$duration_unit        = $field['duration_unit'];
$appointment_duration = $field['appointment_duration'];
$availability_span    = $field['availability_span'];
$is_autoselect        = $field['is_autoselect'];

$month_before_day = strpos( __( 'F j, Y', 'woocommerce-appointments' ), 'F' ) < strpos( __( 'F j, Y', 'woocommerce-appointments' ), 'j' );

?>
<fieldset class="wc-appointments-date-picker wc-appointments-date-picker-<?php esc_attr_e( $product_type ); ?> <?php esc_attr_e( implode( ' ', $class ) ); ?>">
	<legend>
		<span class="label"><?php esc_html_e( $label ); ?></span>: <small class="wc-appointments-date-picker-choose-date"><?php esc_html_e( 'Choose...', 'woocommerce-appointments' ); ?></small>
	</legend>

	<div class="picker"
		data-default-availability="<?php echo $default_availability ? 'true' : 'false'; ?>"
		data-min_date="<?php echo ! empty( $min_date_js ) ? esc_attr( $min_date_js ) : 0; ?>"
		data-max_date="<?php esc_attr_e( $max_date_js ); ?>"
		data-default_date="<?php esc_attr_e( $default_date ); ?>"
		data-product_id="<?php esc_html_e( $product_id ); ?>"
		data-duration_unit="<?php esc_html_e( $duration_unit ); ?>"
		data-appointment_duration="<?php esc_html_e( $appointment_duration ); ?>"
		data-availability_span="<?php esc_html_e( $availability_span ); ?>"
		data-is_autoselect="<?php esc_html_e( $is_autoselect ); ?>"
	></div>

	<div class="wc-appointments-date-picker-date-fields">
		<?php
        // woocommerce_appointments_mdy_format filter to choose between month/day/year and day/month/year format
		if ( $month_before_day && apply_filters( 'woocommerce_appointments_mdy_format', true ) ) :
        ?>
		<label>
			<input type="text" autocomplete="off" name="<?php esc_html_e( $name ); ?>_month" placeholder="<?php esc_html_e( 'mm', 'woocommerce-appointments' ); ?>" size="2" class="appointment_date_month notranslate" />
			<span><?php esc_html_e( 'Month', 'woocommerce-appointments' ); ?></span>
		</label> / <label>
			<input type="text" autocomplete="off" name="<?php esc_html_e( $name ); ?>_day" placeholder="<?php esc_html_e( 'dd', 'woocommerce-appointments' ); ?>" size="2" class="appointment_date_day notranslate" />
			<span><?php esc_html_e( 'Day', 'woocommerce-appointments' ); ?></span>
		</label>
		<?php else : ?>
		<label>
			<input type="text" autocomplete="off" name="<?php esc_html_e( $name ); ?>_day" placeholder="<?php esc_html_e( 'dd', 'woocommerce-appointments' ); ?>" size="2" class="appointment_date_day notranslate" />
			<span><?php esc_html_e( 'Day', 'woocommerce-appointments' ); ?></span>
		</label> / <label>
			<input type="text" autocomplete="off" name="<?php esc_html_e( $name ); ?>_month" placeholder="<?php esc_html_e( 'mm', 'woocommerce-appointments' ); ?>" size="2" class="appointment_date_month notranslate" />
			<span><?php esc_html_e( 'Month', 'woocommerce-appointments' ); ?></span>
		</label>
		<?php endif; ?> / <label>
			<input type="text" autocomplete="off" name="<?php esc_html_e( $name ); ?>_year" value="<?php esc_html_e( date( 'Y' ) ); ?>" placeholder="<?php esc_html_e( 'YYYY', 'woocommerce-appointments' ); ?>" size="4" class="appointment_date_year notranslate" />
			<span><?php esc_html_e( 'Year', 'woocommerce-appointments' ); ?></span>
		</label>
	</div>
</fieldset>
