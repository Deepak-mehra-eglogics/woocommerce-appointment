<div id="wca-dialog-container-edit-appointment">
	<div id="wca-dialog-backdrop"></div>
	<div id="wca-dialog-wrap" class="wp-core-ui" role="dialog" aria-labelledby="wca-dialog-title">
		<form id="wca-dialog">
			<div id="wca-dialog-header">
				<div id="wca-dialog-inner-header"></div>
				<button type="button" id="wca-dialog-close"><span class="screen-reader-text"><?php esc_html_e( 'Close', 'woocommerce-appointments' ); ?></span></button>
			</div>
			<div id="wca-dialog-content">
				<div id="wca-dialog-inner">
					<?php do_action( 'woocommerce_appointments_before_admin_dialog_content' ); ?>
					<div id="wca-dialog-inner-content"></div>
					<?php do_action( 'woocommerce_appointments_after_admin_dialog_content' ); ?>
				</div>
			</div>
			<div id="wca-dialog-footer">
				<div id="wca-dialog-cancel">
					<button type="button" class="button">
						<?php esc_html_e( 'Close', 'woocommerce-appointments' ); ?>
					</button>
				</div>
				<div id="wca-dialog-update" class="button-group">
					<?php do_action( 'woocommerce_appointments_before_admin_dialog_button' ); ?>
					<div id="wca-dialog-submit"></div>
					<?php do_action( 'woocommerce_appointments_after_admin_dialog_button' ); ?>
				</div>
			</div>
		</form>
	</div>
</div>

<script type="text/javascript">
	jQuery(function() {

		// Tooltips
		jQuery( 'li.event_status' ).tipTip({
			'attribute' : 'data-tip',
			'fadeIn' : 50,
			'fadeOut' : 50,
			'delay' : 200
		});

		// Dialog
		jQuery( document ).on( 'click', '.event_card', function(e) {
			e.preventDefault();

			// Dialog elements
			var appointment     = jQuery(this);
			var container       = jQuery( '#wca-dialog-container-edit-appointment' );
			var wrap            = jQuery( '#wca-dialog-wrap' );
			var dialog          = jQuery( '#wca-dialog' );
			var dialog_header   = jQuery( '#wca-dialog-inner-header' );
			var dialog_inner    = jQuery( '#wca-dialog-inner-content' );
			var backdrop        = jQuery( '#wca-dialog-backdrop' );
			var submit          = jQuery( '#wca-dialog-submit' );
			var close           = jQuery( '#wca-dialog-close' );

			// Data attributes
			var order_id                 = appointment.attr( 'data-order_id' );
			var edit_link                = appointment.attr( 'data-edit_link' );
			var order_status             = appointment.attr( 'data-order_status' );
			var event_cost               = appointment.attr( 'data-event_cost' );
			var product_title            = appointment.attr( 'data-product_title' );
			var product_id               = appointment.attr( 'data-product_id' );
			var staff_name               = appointment.attr( 'data-staff_name' );
			var when                     = appointment.attr( 'data-when' );
			var duration                 = appointment.attr( 'data-duration' );
			var addons                   = appointment.attr( 'data-addons' );
			var event_qty                = appointment.attr( 'data-event_qty' );
			var event_status             = appointment.attr( 'data-event_status' );
			var event_status_label       = appointment.attr( 'data-event_status_label' );
			var event_name               = appointment.attr( 'data-event_name' );
			var customer_name            = appointment.attr( 'data-customer_name' );
			var customer_phone           = appointment.attr( 'data-customer_phone' );
			var customer_id              = appointment.attr( 'data-customer_id' );
			var customer_url             = appointment.attr( 'data-customer_url' );
			var customer_email           = appointment.attr( 'data-customer_email' );
			var customer_status          = appointment.attr( 'data-customer_status' );
			var customer_avatar          = appointment.attr( 'data-customer_avatar' );

			// Clear.
			dialog_header.empty();
			dialog_inner.empty();
			submit.empty();

			//** DETAILS: start

				// Customer
				customer      = '';
				customer_img  = '';
				product_name  = '';

				if ( customer_name ) {
					customer += '<span class="wca-customer-name">' + customer_name + '</span>';
				} else {
					customer += '<span class="wca-customer-name"><?php esc_html_e( 'Guest', 'woocommerce-appointments' ); ?></span>';
				}

				if ( customer_status ) {
					customer += '<span class="wca-customer-status">' + customer_status + '</span>';
				}

				if ( customer_url && customer ) {
					customer = '<a href="' + customer_url + '" class="wca-customer-url">' + customer + '</a>';
				}

				if ( customer_phone ) {
					customer += '<a href="tel:' + customer_phone + '" class="wca-customer-meta" title="' + customer_phone + '"><span class="dashicons dashicons-phone"></span></a>';
				}

				if ( customer_email ) {
					customer += '<a href="mailto:' + customer_email + '" class="wca-customer-meta" title="' + customer_email + '"><span class="dashicons dashicons-email"></span></a>';
				}

				if ( customer_avatar ) {
					customer_img = '<img src="' + customer_avatar + '" class="wca-customer-avatar" />';
				}

				if ( event_name ) {
					dialog_header.append( '<div id="wca-dialog-name"><span class="wca-availability-name">' + event_name + '</span></div>' );
				} else if ( customer ) {
					dialog_header.append( '<div id="wca-dialog-name">' + customer_img + customer + '</div>' );
				}

				// Product
				if ( product_title ) {
					// Quantity
					if ( event_qty ) {
						product_name = '<span id="wca-product-qty">' + event_qty + '&times;</span> ';
					}
					product_name += '<a href="<?php echo esc_url( admin_url() ); ?>post.php?post=' + product_id + '&action=edit">' + product_title + '</a>';
					dialog_inner.append( '<dl id="wca-detail-product"><dt><?php esc_html_e( 'Product', 'woocommerce-appointments' ); ?>:</dt><dd>' + product_name + '</dd></dl>' );
				}

				// Staff
				if ( staff_name ) {
					dialog_inner.append( '<dl id="wca-detail-staff"><dt><?php esc_html_e( 'Staff', 'woocommerce-appointments' ); ?>:</dt><dd>' + staff_name + '</dd></dl>' );
				}

				// When
				if ( when ) {
					dialog_inner.append( '<dl id="wca-detail-when"><dt><?php esc_html_e( 'When', 'woocommerce-appointments' ); ?>:</dt><dd>' + when + '</dd></dl>' );
				}

				// Duration
				if ( duration ) {
					dialog_inner.append( '<dl id="wca-detail-duration"><dt><?php esc_html_e( 'Duration', 'woocommerce-appointments' ); ?>:</dt><dd>' + duration + '</dd></dl>' );
				}

				// Addons.
				if ( addons ) {
					dialog_inner.append( '<dl id="wca-detail-addons"><dt><?php esc_html_e( 'Add-ons', 'woocommerce-appointments' ); ?>:</dt><dd>' + addons + '</dd></dl>' );
				}

				// Status
				if ( event_status_label ) {
					event_status = event_status ? event_status : 'unpaid';
					dialog_inner.append( '<dl id="wca-detail-status wca-detail-status-' + event_status + '"><dt><?php esc_html_e( 'Status', 'woocommerce-appointments' ); ?>:</dt><dd>' + event_status_label + '</dd></dl>' );
				}

				// Cost
				if ( event_cost ) {
					dialog_inner.append( '<dl id="wca-detail-cost"><dt><?php esc_html_e( 'Cost', 'woocommerce-appointments' ); ?>:</dt><dd>' + event_cost + '</dd></dl>' );
				}

			//** DETAILS: end

			// Edit Button
			if ( edit_link ) {
				submit.append( '<a href="' + edit_link + '" class="button button-primary"><?php esc_html_e( 'Edit', 'woocommerce-appointments' ); ?></a>' );
			}

			<?php do_action( 'woocommerce_appointments_after_admin_dialog_script' ); ?>

			// Open dialog
			container.fadeIn( 100 );

		});

		// Hide Appointment Edit Modal
		jQuery( document ).on( 'click', '#wca-dialog-backdrop, #wca-dialog-close, #wca-dialog-cancel button', function(e) {
			jQuery( '#wca-dialog-container-edit-appointment' ).fadeOut( 100 );
		});

	});
</script>
