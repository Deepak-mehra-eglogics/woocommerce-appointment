<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$page_title   = __( 'Create add-ons group', 'woocommerce-appointments' );
$button_title = __( 'Publish', 'woocommerce-appointments' );

if ( isset( $_POST ) && ! empty( $_POST['save_addon'] ) || ! empty( $_GET['edit'] ) ) {
	$page_title   = __( 'Edit Add-on', 'woocommerce-appointments' );
	$button_title = __( 'Update', 'woocommerce-appointments' );
}
?>
<div class="wrap woocommerce">
	<h1 class="wp-heading-inline"><?php echo esc_html( $page_title ); ?></h1>

	<div>
		<p><?php echo esc_html_e( 'Create a group of global add-ons to add free or paid options to your products in bulk. You may optionally limit these add-ons to specific product categories.', 'woocommerce-appointments' ); ?></p>
		<p><?php echo esc_html_e( 'To create add-ons for individual products, navigate to the <strong>Product Data > Add-ons</strong> tab in the product editor.', 'woocommerce-appointments' ); ?></p>
	</div>

	<form method="POST" action="">

		<?php wp_nonce_field( 'wc_pao_global_addons_edit' ); ?>

		<table class="form-table global-addons-form meta-box-sortables">
			<tr>
				<th>
					<label for="addon-reference"><?php esc_html_e( 'Name', 'woocommerce-appointments' ); ?></label>
				</th>
				<td>
					<input type="text" name="addon-reference" id="addon-reference" style="width:50%;" value="<?php echo esc_attr( $reference ); ?>" />
					<p class="description"><?php esc_html_e( 'Type a unique name to identify this global add-ons group. This will not be visible to customers.', 'woocommerce-appointments' ); ?></p>
				</td>
			</tr>
			<tr>
				<th>
					<label for="addon-objects"><?php esc_html_e( 'Product Categories', 'woocommerce-appointments' ); ?></label>
				</th>
				<td>
					<select id="addon-objects" name="addon-objects[]" multiple="multiple" style="width:50%;" data-placeholder="<?php esc_attr_e( 'Choose categories&hellip;', 'woocommerce-appointments' ); ?>" class="wc-enhanced-select wc-pao-enhanced-select">
						<option value="all" <?php selected( in_array( 0, $objects ), true ); ?>><?php esc_html_e( 'All Products', 'woocommerce-appointments' ); ?></option>
						<optgroup label="<?php esc_attr_e( 'Product categories', 'woocommerce-appointments' ); ?>">
							<?php
							$terms = get_terms( 'product_cat', array( 'hide_empty' => 0 ) );

							foreach ( $terms as $term ) {
								echo '<option value="' . esc_attr( $term->term_id ) . '" ' . selected( in_array( $term->term_id, $objects ), true, false ) . '>' . esc_html( $term->name ) . '</option>';
							}
							?>
						</optgroup>
						<?php do_action( 'woocommerce_product_addons_global_edit_objects', $objects ); ?>
					</select>
					<p class="description"><?php esc_html_e( 'Use this option to assign this global add-ons group to specific product categories.', 'woocommerce-appointments' ); ?></p>
				</td>
			</tr>
			<tr>
				<th>
					<label for="addon-priority"><?php esc_html_e( 'Display Order', 'woocommerce-appointments' ); ?></label>
				</th>
				<td>
					<input type="text" name="addon-priority" id="addon-priority" style="width:50%;" value="<?php echo esc_attr( $priority ); ?>" />
					<p class="description"><?php echo esc_html_e( 'This number determines the position of this add-ons group relative to other groups in product pages. Groups with a lower <strong>Display Order</strong> are displayed higher in the product page. Add-ons created for individual products are displayed at order 10.', 'woocommerce-appointments' ); ?></p>
				</td>
			</tr>
			<tr>
				<td colspan="2">
				</td>
			</tr>
			<tr>
				<td id="poststuff" class="postbox" colspan="2">
					<?php
					$exists = false;
					require __DIR__ . '/html-addon-panel.php';
					?>
				</td>
			</tr>
		</table>
		<p class="submit">
			<input type="hidden" name="edit_id" value="<?php echo ( ! empty( $edit_id ) ? esc_attr( $edit_id ) : '' ); ?>" />
			<input type="hidden" name="save_addon" value="true" />
			<input type="submit" name="submit" id="submit" class="button button-primary" value="<?php esc_attr_e( $button_title ); ?>">
		</p>
	</form>
</div>

<script type="text/javascript">
	jQuery( function( $ ) {
		$( '.wc-enhanced-select' ).on( 'select2:select', function( e ) {
			var selectedID = e.params.data.id,
				values     = $( '.wc-enhanced-select' ).val(),
				all        = 'all',
				allIndex   = values.indexOf( all );

			if ( all === selectedID ) {
				values = [ all ];
			} else if ( 0 === allIndex ) {
				values.splice( allIndex, 1 );
			}

			$( '.wc-enhanced-select' ).val( values ).trigger( 'change.select2' );
		} );
	} );
</script>
