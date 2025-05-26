<div id="appointments_staff" class="woocommerce_options_panel panel wc-metaboxes-wrapper">

	<div class="options_group" id="staff_options">

		<?php
		woocommerce_wp_text_input(
			[
				'id'          => '_wc_appointment_staff_label',
				'placeholder' => __( 'Providers', 'woocommerce-appointments' ),
				'label'       => __( 'Label', 'woocommerce-appointments' ),
				'value'       => $appointable_product->get_staff_label( 'edit' ),
				'desc_tip'    => true,
				'description' => __( 'The label shown on the frontend if the staff is customer defined.', 'woocommerce-appointments' ),
			]
		);

		woocommerce_wp_select(
			[
				'id'          => '_wc_appointment_staff_assignment',
				'label'       => __( 'Selection', 'woocommerce-appointments' ),
				'value'       => $appointable_product->get_staff_assignment( 'edit' ),
				'options'     => [
					'customer'  => __( 'Customer selected', 'woocommerce-appointments' ),
					'automatic' => __( 'Automatically assigned', 'woocommerce-appointments' ),
					'all'       => __( 'Automatically assigned (all staff together)', 'woocommerce-appointments' ),
				],
				'desc_tip'    => true,
				'description' => __( 'Customer selected staff allow customers to choose one from the appointment form.', 'woocommerce-appointments' ),
			]
		);

		woocommerce_wp_checkbox(
			[
				'id'          => '_wc_appointment_staff_nopref',
				'label'       => __( 'No Preference?', 'woocommerce-appointments' ),
				'value'       => $appointable_product->get_staff_nopref( 'edit' ) ? 'yes' : 'no',
				'description' => __( 'Check this box if you want to show No preference option.', 'woocommerce-appointments' ),
			]
		);
		?>

	</div>

	<div class="options_group">
		<div class="toolbar">
			<h3><?php esc_html_e( 'Staff', 'woocommerce-appointments' ); ?></h3>
		</div>
		<div class="woocommerce_appointable_staff wc-metaboxes">
			<?php
			global $post, $wpdb;

			$all_staff        = self::get_appointment_staff();
			$product_staff    = $appointable_product->get_staff_ids( 'edit' );
			$staff_base_costs = $appointable_product->get_staff_base_costs( 'edit' );
			$staff_qtys       = $appointable_product->get_staff_qtys( 'edit' );
			$loop             = 0;

			if ( $product_staff ) {
				foreach ( $product_staff as $staff_id ) {
					$staff           = new WC_Product_Appointment_Staff( $staff_id );
					$staff_base_cost = $staff_base_costs[ $staff_id ] ?? '';
					$staff_qty       = $staff_qtys[ $staff_id ] ?? '';

					include 'html-appointment-staff-member.php';
					++$loop;
				}
			}
			?>
		</div>
		<div class="toolbar">
			<?php if ( $all_staff ) { ?>
				<button type="button" class="button add_staff"><?php esc_html_e( 'Assign Staff', 'woocommerce-appointments' ); ?></button>
				<select id="add_staff_id" name="add_staff_id" class="add_select_id wc-enhanced-select" style="min-width:160px;">
					<?php
					foreach ( $all_staff as $staff ) {
						echo '<option value="' . esc_attr( $staff->ID ) . '">' . esc_html( $staff->display_name ) . '</option>';
					}
					?>
				</select>
			<?php } ?>
			<div class="description" style="line-height:2.2;">
				<a href="<?php echo esc_url( admin_url( 'users.php?role=shop_staff' ) ); ?>" target="_blank"><?php esc_html_e( 'Manage Staff', 'woocommerce-appointments' ); ?></a>
				<?php if ( current_user_can( 'create_users' ) ) { ?>
					&middot; <a href="<?php echo esc_url( admin_url( 'user-new.php' ) ); ?>" target="_blank"><?php esc_html_e( 'Add Staff', 'woocommerce-appointments' ); ?></a>
				<?php } ?>
			</div>
		</div>
	</div>
</div>
