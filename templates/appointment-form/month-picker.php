<?php
/**
 * Month picker
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/appointment-form/month-picker.php.
 *
 * HOWEVER, on occasion we will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see         https://docs.woocommerce.com/document/template-structure/
 * @version     4.14.0
 * @since       3.4.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

wp_enqueue_script( 'wc-appointments-month-picker' );
wp_enqueue_script( 'wc-appointments-appointment-form' );

// Fields located inside includes\appointment-form\class-wc-appointment-form-month-picker.php.
$class                  = $field['class'];
$label                  = $field['label'];
$name                   = $field['name'];
$fully_scheduled_months = $field['fully_scheduled_months'];
$product                = $field['product'];
$product_id             = $field['product_id'];
$duration_unit          = $field['duration_unit'];
$appointment_duration   = $field['appointment_duration'];
$availability_span      = $field['availability_span'];
$is_autoselect          = $field['is_autoselect'];
$slots                  = $field['slots'];
?>
<div class="form-field form-field-wide <?php esc_attr_e( implode( ' ', $class ) ); ?>">
	<label for="<?php esc_html_e( $name ); ?>"><?php esc_html_e( $label ); ?>:</label>
	<ul class="picker slot-picker"
		data-fully-scheduled-months="<?php esc_html_e( wp_json_encode( $fully_scheduled_months ) ); ?>"
		data-product_id="<?php esc_html_e( $product_id ); ?>"
		data-duration_unit="<?php esc_html_e( $duration_unit ); ?>"
		data-appointment_duration="<?php esc_html_e( $appointment_duration ); ?>"
		data-availability_span="<?php esc_html_e( $availability_span ); ?>"
		data-is_autoselect="<?php esc_html_e( $is_autoselect ); ?>"
	>
	<?php
	foreach ( $slots as $slot ) {
		// Sett fully scheduled CSS class.
		$fully_scheduled_class = '';
		if ( in_array( date( 'Y-n', $slot ), array_keys( $fully_scheduled_months ) ) ) {
			if ( $product->has_staff() ) {
				// Only disable, when all staff is unavailable.
				$all_staff_unavailable = false;
				foreach ( $product->get_staff_ids() as $staff_member_id ) {
					if ( isset( $fully_scheduled_months[ date( 'Y-n', $slot ) ][ $staff_member_id ] ) ) {
						$all_staff_unavailable = true;
					} else {
						$all_staff_unavailable = false;
						break;
					}
				}
				if ( $all_staff_unavailable ) {
					$fully_scheduled_class = ' fully_scheduled';
				} else {
					$fully_scheduled_class = ' partial_scheduled';
				}
			} else {
				$fully_scheduled_class = ' fully_scheduled';
			}
		}

		echo '<li class="slot' . esc_attr( $fully_scheduled_class ) . '" data-slot="' . esc_attr( date( 'Ym', $slot ) ) . '"><a href="#" data-value="' . esc_attr( date( 'Y-m', $slot ) ) . '">' . esc_attr( date_i18n( 'M Y', $slot ) ) . '</a></li>';
	}
	?>
	</ul>
	<input type="hidden" name="<?php esc_html_e( $name ); ?>_yearmonth" id="<?php esc_html_e( $name ); ?>" />
</div>
