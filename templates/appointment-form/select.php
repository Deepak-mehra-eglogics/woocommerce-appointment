<?php
/**
 * SELECT appointment form field
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/appointment-form/select.php.
 *
 * HOWEVER, on occasion we will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see         https://docs.woocommerce.com/document/template-structure/
 * @version     4.14.0
 * @since       1.2.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

$class   = $field['class'];
$label   = $field['label'];
$name    = $field['name'];
$options = $field['options'];
?>
<p class="form-field form-field-wide <?php esc_attr_e( implode( ' ', $class ) ); ?>">
	<label for="<?php esc_html_e( $name ); ?>"><?php esc_html_e( $label ); ?>:</label>
	<select name="<?php esc_html_e( $name ); ?>" id="<?php esc_html_e( $name ); ?>">
		<?php foreach ( $options as $key => $value ) : ?>
			<option value="<?php esc_html_e( $key ); ?>"><?php esc_html_e( $value ); ?></option>
		<?php endforeach; ?>
	</select>
</p>
