<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;
?>
<div class="woocommerce_appointment_staff wc-metabox closed">
	<h3>
		<button type="button" class="remove_appointment_staff button" rel="<?php esc_attr_e( absint( $staff->get_id() ) ); ?>">x</button>

		<?php if ( current_user_can( 'edit_user', absint( $staff->get_id() ) ) ) { ?>
			<a href="<?php echo esc_url( get_edit_user_link( absint( $staff->get_id() ) ) ); ?>" target="_blank" class="edit_staff"><?php esc_html_e( 'Edit', 'woocommerce-appointments' ); ?></a>
		<?php } ?>

		<div class="handlediv" title="<?php esc_html_e( 'Click to toggle', 'woocommerce-appointments' ); ?>"></div>

		<?php echo get_avatar( $staff->get_id(), 22, '', $staff->get_display_name() ); ?>

		<strong><span class="staff_name"><?php esc_attr_e( $staff->get_display_name() ); ?></span></strong>

		<input type="hidden" name="staff_id[<?php esc_html_e( $loop ); ?>]" value="<?php esc_attr_e( $staff->get_id() ); ?>" />
		<input type="hidden" class="staff_menu_order" name="staff_menu_order[<?php esc_html_e( $loop ); ?>]" value="<?php esc_html_e( $loop ); ?>" />
	</h3>
	<table cellpadding="0" cellspacing="0" class="wc-metabox-content">
		<tbody>
			<tr>
				<td>
					<label><?php esc_html_e( 'Additional Cost', 'woocommerce-appointments' ); ?>:</label>
					<input
						type="number"
						class=""
						name="staff_cost[<?php esc_html_e( $loop ); ?>]"
						<?php
						if ( ! empty( $staff_base_cost ) ) {
							echo 'value="' . esc_attr( $staff_base_cost ) . '"';
						}
						?>
						placeholder="0.00"
						step="0.01"
						style="width: 4em;"
					/>
                    <?php do_action( 'woocommerce_appointments_after_staff_cost', $staff->get_id(), $post->ID ); ?>
				</td>
				<td>
					<label><?php esc_html_e( 'Quantity', 'woocommerce-appointments' ); ?>:</label>
					<input
						type="number"
						class=""
						name="staff_qty[<?php esc_html_e( $loop ); ?>]"
						<?php
						if ( ! empty( $staff_qty ) ) {
							echo 'value="' . esc_attr( $staff_qty ) . '"';
						}
						?>
						placeholder="<?php esc_html_e( 'N/A', 'woocommerce-appointments' ); ?>"
						step="1"
						style="width: 4em;"
					/>
                    <?php do_action( 'woocommerce_appointments_after_staff_qty', $staff->get_id(), $post->ID ); ?>
				</td>
			</tr>
		</tbody>
	</table>
</div>
