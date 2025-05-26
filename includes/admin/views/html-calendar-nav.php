<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;
?>
<div class="tablenav">
	<div class="filters">
		<?php
		// Product filter.
		$product_name = '';
		$product_id   = '';

		if ( ! empty( $_REQUEST['filter_appointable_product'] ) ) { // phpcs:disable  WordPress.Security.NonceVerification.NoNonceVerification
			$product_id   = absint( $_REQUEST['filter_appointable_product'] ); // WPCS: input var ok, sanitization ok.
			$product      = get_wc_product_appointment( $product_id );
			$product_name = $product ? $product->get_title() : '';
		}
		?>
		<select class="wc-product-search" name="filter_appointable_product" style="width: 200px;" data-allow_clear="true" data-placeholder="<?php esc_html_e( 'Filter by product', 'woocommerce-appointments' ); ?>" data-action="woocommerce_json_search_appointable_products">
			<option value="<?php esc_attr_e( $product_id ); ?>" selected="selected"><?php esc_html_e( $product_name ); ?></option>';
		</select>
		<?php if ( current_user_can( 'manage_others_appointments' ) ) { ?>
			<select name="filter_appointable_staff" class="wc-enhanced-select" style="width:200px">
				<option value=""><?php esc_html_e( 'All Staff', 'woocommerce-appointments' ); ?></option>
				<?php
				$staff_filters = $this->staff_filters();
				if ( $staff_filters ) {
					foreach ( $staff_filters as $filter_id => $filter_name ) {
					?>
						<option value="<?php esc_attr_e( $filter_id ); ?>" <?php selected( $staff_filter, $filter_id ); ?>><?php esc_attr_e( $filter_name ); ?></option>
					<?php
					}
				}
				?>
			</select>
		<?php } ?>
	</div>
	<?php if ( 'month' === $view ) : ?>
		<div class="date_selector">
			<a class="prev" href="
			<?php
			echo esc_url(
				add_query_arg(
					[
						'calendar_year'  => $year,
						'calendar_month' => $month - 1,
					]
				)
			);
			?>
			">&larr;</a>
			<div>
				<select name="calendar_month" class="wc-enhanced-select" style="width:160px">
					<?php for ( $i = 1; $i <= 12; $i ++ ) : ?>
						<option value="<?php esc_attr_e( $i ); ?>" <?php selected( $month, $i ); ?>><?php esc_attr_e( ucfirst( date_i18n( 'M', strtotime( '2013-' . $i . '-01' ) ) ) ); ?></option>
					<?php endfor; ?>
				</select>
			</div>
			<div>
				<select name="calendar_year" class="wc-enhanced-select" style="width:160px">
					<?php $current_year = date( 'Y' ); ?>
					<?php for ( $i = ( $current_year - 1 ); $i <= ( $current_year + 5 ); $i ++ ) : ?>
						<option value="<?php esc_attr_e( $i ); ?>" <?php selected( $year, $i ); ?>><?php esc_attr_e( $i ); ?></option>
					<?php endfor; ?>
				</select>
			</div>
			<a class="next" href="
			<?php
			echo esc_url(
				add_query_arg(
					[
						'calendar_year'  => $year,
						'calendar_month' => $month + 1,
					]
				)
			);
			?>
			">&rarr;</a>
		</div>
	<?php endif; ?>
	<?php if ( 'week' === $view ) : ?>
		<div class="week_selector">
			<a class="prev" href="<?php echo esc_url( add_query_arg( 'calendar_day', $prev_week ) ); ?>">&larr;</a>
			<div class="week_picker">
				<input type="hidden" name="calendar_day" class="calendar_day" value="<?php esc_attr_e( $day_formatted ); ?>" />
				<input type="text" name="calendar_week" class="calendar_week date-picker" value="<?php esc_attr_e( $week_formatted ); ?>" placeholder="<?php esc_attr_e( wc_appointments_date_format() ); ?>" autocomplete="off" readonly="readonly" />
			</div>
			<a class="next" href="<?php echo esc_url( add_query_arg( 'calendar_day', $next_week ) ); ?>">&rarr;</a>
		</div>
	<?php endif; ?>
	<?php if ( in_array( $view, [ 'day', 'staff' ] ) ) : ?>
		<div class="date_selector">
			<a class="prev" href="<?php echo esc_url( add_query_arg( 'calendar_day', $prev_day ) ); ?>">&larr;</a>
			<div>
				<input type="text" name="calendar_day" class="calendar_day date-picker" value="<?php esc_attr_e( $day_formatted ); ?>" placeholder="<?php esc_attr_e( wc_appointments_date_format() ); ?>" autocomplete="off" />
			</div>
			<a class="next" href="<?php echo esc_url( add_query_arg( 'calendar_day', $next_day ) ); ?>">&rarr;</a>
		</div>
	<?php endif; ?>
	<div class="views">
		<a class="view-select <?php echo ( 'month' === $view ) ? 'current' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'view', 'month' ) ); ?>">
			<?php esc_html_e( 'Month', 'woocommerce-appointments' ); ?>
		</a>
		<a class="view-select <?php echo ( 'week' === $view ) ? 'current' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'view', 'week' ) ); ?>">
			<?php esc_html_e( 'Week', 'woocommerce-appointments' ); ?>
		</a>
		<a class="view-select <?php echo ( 'day' === $view ) ? 'current' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'view', 'day' ) ); ?>">
			<?php esc_html_e( 'Day', 'woocommerce-appointments' ); ?>
		</a>
		<?php if ( current_user_can( 'manage_others_appointments' ) ) { ?>
			<a class="view-select <?php echo ( 'staff' === $view ) ? 'current' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'view', 'staff' ) ); ?>">
				<?php esc_html_e( 'Staff', 'woocommerce-appointments' ); ?>
			</a>
		<?php } ?>
	</div>
	<script type="text/javascript">
		jQuery(function() {
			jQuery( '.tablenav select' ).on( 'change', function() {
				jQuery( '#mainform' ).submit();
			});
		});
	</script>
</div>
