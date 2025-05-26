<?php
/**
 * STAFF SELECT appointment form field
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/appointment-form/select-staff.php.
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

wp_dequeue_script( 'selectWoo' );
wp_enqueue_script( 'select2' );
wp_enqueue_script( 'wc-appointments-staff-picker' );

// Fields located inside includes\appointment-form\class-wc-appointment-form.php
$class   = $field['class'];
$label   = $field['label'];
$name    = $field['name'];
$nopref  = $field['nopref'];
$options = $field['options'];
?>
<p class="form-field form-field-wide <?php esc_attr_e( implode( ' ', $class ) ); ?>">
	<label for="<?php esc_html_e( $name ); ?>"><?php esc_html_e( $label ); ?></label>
	<select name="<?php esc_html_e( $name ); ?>" id="<?php esc_html_e( $name ); ?>">
		<?php if ( $nopref ) : ?>
			<option value=""><?php esc_html_e( '&mdash; No Preference &mdash;', 'woocommerce-appointments' ); ?></option>
		<?php endif; ?>
		<?php foreach ( $options as $key => $value ) : ?>
			<?php
			$get_avatar = get_avatar( $key, 48 );
			preg_match( "@src='([^']+)'@", $get_avatar, $match ); # single quote
			$avatar = array_pop( $match );
			preg_match( '@src="([^"]+)"@', $get_avatar, $match ); # double quote
			$avatar2 = array_pop( $match );

			// Avatar image link.
			$avatar_url = $avatar ? $avatar : ( $avatar2 ? $avatar2 : '' );
			?>
			<option value="<?php esc_attr_e( $key ); ?>" data-avatar="<?php echo esc_url( $avatar_url ); ?>">
				<?php echo $value; // WPCS: XSS ok. ?>
			</option>
		<?php endforeach; ?>
	</select>
</p>
