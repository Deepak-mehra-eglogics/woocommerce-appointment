<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$has_addons = ( ! empty( $product_addons ) && 0 < count( $product_addons ) ) ? 'wc-pao-has-addons' : '';

?>
<div id="product_addons_data" class="panel woocommerce_options_panel wc-metaboxes-wrapper <?php echo ! $has_addons ? 'onboarding' : ''; ?>">
	<?php do_action( 'woocommerce_product_addons_panel_start' ); ?>
	<?php if ( $exists ) : ?>
		<div class="options_group global_addon_options">
			<?php

			// Default Status.
			woocommerce_wp_checkbox(
				array(
					'id'          => '_product_addons_exclude_global',
					'name'        => '_product_addons_exclude_global',
					'value'       => ! $exclude_global ? 'yes' : 'no',
					'label'       => __( 'Use Global Add-Ons?', 'woocommerce-appointments' ),
					'description' => sprintf(
						/* translators: %s link to global add-ons tab */
						__( 'Use this option to control if <a href="%s">global add-ons</a> are assigned to this product.', 'woocommerce-appointments' ),
						esc_url( admin_url() . 'edit.php?post_type=product&page=addons' )
					),
				)
			);

			?>
		</div>
	<?php endif; ?>
	<div class="hr-section hr-section-addons"><?php echo esc_html__( 'Product Add-Ons', 'woocommerce-appointments' ); ?></div>
	<div class="wc-pao-field-header">
		<p class="toolbar wc-pao-toolbar <?php echo esc_attr( $has_addons ); ?>">
			<a href="#" class="wc-pao-import-addons"><?php esc_html_e( 'Import', 'woocommerce-appointments' ); ?></a>
			<a href="#" class="wc-pao-export-addons"><?php esc_html_e( 'Export', 'woocommerce-appointments' ); ?></a>
			<a href="#" class="wc-pao-close-all"><?php esc_html_e( 'Close all', 'woocommerce-appointments' ); ?></a>
			<a href="#" class="wc-pao-expand-all"><?php esc_html_e( 'Expand all', 'woocommerce-appointments' ); ?></a>&nbsp;
			<input type="hidden" name="product_addons_export_string" class="product_addons_export_string" value="<?php echo wc_esc_json( json_encode( $product_addons ) ); ?>" />
		</p>
	</div>

	<div class="wc-pao-addons <?php echo esc_attr( $has_addons ); ?>">

		<?php
		$loop = 0;

		foreach ( $product_addons as $addon ) {
			include __DIR__ . '/html-addon.php';

			++$loop;
		}
		?>

	</div>

	<div class="pao_boarding__addons addon_fields_container widefat">
		<div class="pao_boarding__addons__message">
			<p>
			<?php
			if ( isset( $_GET['page'] ) && 'addons' === $_GET['page'] ) {
				esc_html_e( 'Choose an add-on field to add to this group,', 'woocommerce-appointments' );
			?>
			<br>
			<?php
				echo wp_kses_post( __( 'or <a href="#" class="wc-pao-import-addons">click here</a> to import data.', 'woocommerce-appointments' ) );
			} else {
				esc_html_e( 'Use add-ons to add free or paid options to this product.', 'woocommerce-appointments' );
			?>
			<br>
			<?php
				echo wp_kses_post( __( 'Choose a field type to add below, or <a href="#" class="wc-pao-import-addons">click here</a> to import data.', 'woocommerce-appointments' ) );
			}
			?>
			</p>
		</div>
		<div class="addon_fields_add addon_fields_row">
			<div class="addon_fields_select">
				<p class="sw-enhanced-select">
					<select class="addon_field_type">
						<option value="add" selected="selected"><?php esc_html_e( 'Add field&hellip;', 'woocommerce-appointments' ); ?></option>
						<option value="multiple_choice"><?php esc_html_e( 'Multiple Choice', 'woocommerce-appointments' ); ?></option>
						<option value="checkbox"><?php esc_html_e( 'Checkboxes', 'woocommerce-appointments' ); ?></option>
						<option value="custom_text"><?php esc_html_e( 'Short Text', 'woocommerce-appointments' ); ?></option>
						<option value="custom_textarea"><?php esc_html_e( 'Long Text', 'woocommerce-appointments' ); ?></option>
						<option value="file_upload"><?php esc_html_e( 'File Upload', 'woocommerce-appointments' ); ?></option>
						<option value="custom_price"><?php esc_html_e( 'Customer Defined Price', 'woocommerce-appointments' ); ?></option>
						<option value="input_multiplier"><?php esc_html_e( 'Quantity', 'woocommerce-appointments' ); ?></option>
						<option value="heading"><?php esc_html_e( 'Heading', 'woocommerce-appointments' ); ?></option>
						<option value="datepicker"><?php esc_html_e( 'Date Picker', 'woocommerce-appointments' ); ?></option>
					</select>
				</p>
			</div>
		</div>
	</div>
	<?php do_action( 'woocommerce_product_addons_panel_end' ); ?>
</div>
