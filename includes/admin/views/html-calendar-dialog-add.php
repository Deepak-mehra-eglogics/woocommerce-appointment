<div id="wca-dialog-container-add-appointment">
	<div id="wca-dialog-backdrop"></div>
	<div id="wca-dialog-wrap" class="wp-core-ui" role="dialog" aria-labelledby="wca-dialog-title">
		<form id="wca-dialog">
			<div id="wca-dialog-header">
				<h1><?php esc_html_e( 'Add New Appointment', 'woocommerce-appointments' ); ?></h1>
				<button type="button" id="wca-dialog-close"><span class="screen-reader-text"><?php esc_html_e( 'Close', 'woocommerce-appointments' ); ?></span></button>
			</div>
			<div id="wca-dialog-content">
				<div id="wca-dialog-inner">
					<dl id="wca-new-appointment-product">
						<dt><?php esc_html_e( 'Product', 'woocommerce-appointments' ); ?></dt>
						<dd>
							<select id="appointable_product_id" name="appointable_product_id" class="wc-product-search" style="width: 300px;" data-allow_clear="true" data-placeholder="<?php esc_html_e( 'Select an appointable product...', 'woocommerce-appointments' ); ?>" data-action="woocommerce_json_search_appointable_products"></select>
						</dd>
					</dl>
					<dl id="wca-new-appointment-staff">
						<dt><?php esc_html_e( 'Staff', 'woocommerce-appointments' ); ?></dt>
						<dd>
							<select id="select_staff" name="select_staff" class="chosen_select" style="width:300px">
								<option value=""><?php esc_html_e( 'Select staff &#8230;', 'woocommerce-appointments' ); ?></option>
								<?php foreach ( WC_Appointments_Admin::get_appointment_staff() as $staff ) : ?>
									<option value="<?php echo $staff->ID; ?>"><?php echo $staff->display_name; ?></option>
								<?php endforeach; ?>
							</select>
						</dd>
					</dl>
					<dl id="wca-new-appointment-start">
						<dt><?php esc_html_e( 'Date', 'woocommerce-appointments' ); ?></dt>
						<dd><input type="date" placeholder="yyyy-mm-dd" /></dd>
					</dl>
					<dl id="wca-new-appointment-time">
						<dt><?php esc_html_e( 'Time', 'woocommerce-appointments' ); ?></dt>
						<dd><input type="time" placeholder="hh:mm" /></dd>
					</dl>
				</div>
			</div>
			<div id="wca-dialog-footer">
				<div id="wca-dialog-cancel">
					<button type="button" class="button">
						<?php esc_html_e( 'Close', 'woocommerce-appointments' ); ?>
					</button>
				</div>
				<div id="wca-dialog-update">
					<button type="submit" id="wca-dialog-new-appointment-submit" name="wca-dialog-new-appointment-submit" class="button button-primary">
						<?php esc_html_e( 'Add New Appointment', 'woocommerce-appointments' ); ?>
					</button>
				</div>
			</div>
		</form>
	</div>
</div>

<script type="text/javascript">
	jQuery(function() {

		// Dialog
		jQuery( document ).on( 'click', '.appointments', function(e) {

		    var ap_item = jQuery(".appointments li");
		    if (!ap_item.is(e.target)
		        && ap_item.has(e.target).length === 0)
		    {

				// Dialog elements
				var appointment = jQuery(this);
				var container = jQuery( '#wca-dialog-container-add-appointment' );
				var wrap = jQuery( '#wca-dialog-wrap' );
				var dialog = jQuery( '#wca-dialog' );
				var backdrop = jQuery( '#wca-dialog-backdrop' );
				var submit = jQuery( '#wca-dialog-submit' );
				var close = jQuery( '#wca-dialog-close' );

				// Cursor Position
				var parentOffset = jQuery(this).offset();
				var relX = e.pageX - parentOffset.left;
				var relY = e.pageY - parentOffset.top;

				// Time: Convert Y-position to time
				var calendar_h = jQuery( '.appointments' ).height();
				var time = Math.round( relY / calendar_h * 24 * 60 );
				var hours = Math.floor( time / 60);
				var minutes = time % 60;
				if ( hours < 10 ){
					hours = '0' + hours;
				}
				// Round time to defined interval
				<?php $interval = apply_filters( 'woocommerce_appointments_calendar_add_appointment_interval', 15 ); ?>
				var interval = <?php echo $interval; ?>;
				if(minutes < interval){
					minutes = 0;
				}
				<?php for ( $i = 1; $i <= 60 / $interval; $i++ ) {
					echo "else if (minutes < interval*(" . $i . "+1)){ minutes = interval*" . $i . "; }";
				}
				?>
				if(minutes < 10){
					minutes = '0' + minutes;
				}
				if(minutes == 60){
					minutes = '00';
					hours = hours + 1;
				}
				jQuery('#wca-new-appointment-time input').val( hours + ':' + minutes);

				// Date
				var current_date = jQuery( '.calendar_day' ).val();
				jQuery('#wca-new-appointment-start input').val(current_date);

				// Left position of new appointment
				var left = 0;
				<?php
				$columns_by_staff = apply_filters( 'woocommerce_appointments_calendar_view_by_staff', false );
				if ( $columns_by_staff ) : ?>
					// Set staff by clicked columns
					var staff_id = null;
					var hoursW = jQuery('.hours').width();
					jQuery('.header_column').each(function(){
						var offsetL = jQuery(this).position().left - hoursW;
						var offsetR = jQuery(this).position().left - hoursW + jQuery(this).width();
						if( relX >= offsetL  && relX < offsetR ) {
							staff_id = jQuery(this).attr('data-staff-id');
							left =  offsetL;
						}
					});
					jQuery("#select_staff").val(staff_id).trigger("change");

					var unassigned_r = jQuery('#unassigned_staff').position().left - hoursW;
					if( left == 0 && relX > unassigned_r ) {
						left = unassigned_r;
					}
				<?php endif; ?>

				// Top position of new appointment
				var top = ((Number(hours) * 60) + Number(minutes)) * (calendar_h / 24 / 60);

				// Add appointment to calendar
				jQuery('.appointments').append('<li id="new_appointment" style="left:' + left + 'px; top:' + top + 'px;"><a><strong class="appointment_datetime">' + hours + ':' + minutes + '</strong><ul><li><?php esc_html_e( 'New Appointment', 'woocommerce-appointments' ); ?></li></ul></a></li>');


				// Open dialog
				container.fadeIn( 100 );

			}

		});

		// Hide Appointment Edit Modal
		jQuery( document ).on( 'click', '#wca-dialog-backdrop, #wca-dialog-close, #wca-dialog-cancel button', function(e) {
			jQuery( '#wca-dialog-container-add-appointment' ).fadeOut( 100 );
			jQuery( '#new_appointment' ).fadeOut(100, function(){
			    jQuery(this).remove();
			});
		});

	});
</script>
