<p class="form-field show-if-appointment-status">
    <label for="meta_appointments_last_status"><?php esc_html_e( 'Last Status', 'woocommerce-appointments' ); ?></label>
    <select name="meta[appointments_last_status]" id="meta_appointments_last_status">
        <option value="" <?php selected( $appointments_last_status, '' ); ?>><?php esc_html_e( 'Any status', 'woocommerce-appointments' ); ?></option>
        <?php foreach ( get_wc_appointment_statuses( 'all' ) as $status ): ?>
        <option value="<?php echo $status; ?>" <?php selected( $appointments_last_status, $status ); ?>><?php echo ucfirst( $status ); ?></option>
        <?php endforeach; ?>
    </select>
    <br/>
    <span class="description"><?php esc_html_e( 'Only send this email if the appointment\'s last status matches the selected value', 'woocommerce-appointments' ); ?></span>
</p>
