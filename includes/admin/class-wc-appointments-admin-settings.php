<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * WC_Appointments_Admin_Settings
 */
class WC_Appointments_Admin_Settings extends WC_Settings_Page {

	// Gcal instance.
	public $gcal = null;

	/**
	 * Setup settings class
	 *
	 * @since  1.0
	 */
	public function __construct() {
		// Appointments Settings ID.
		$this->id    = 'appointments';
		$this->label = __( 'Appointments', 'woocommerce-appointments' );

		add_action( 'woocommerce_admin_field_gcal_authorization', [ $this, 'gcal_authorization_setting' ] );
		add_action( 'woocommerce_admin_field_gcal_calendar_id', [ $this, 'gcal_calendar_id_setting' ] );
		add_action( 'woocommerce_admin_field_manual_sync', [ $this, 'manual_sync_setting' ] );

		parent::__construct();
	}

	/**
	 * Get sections
	 *
	 * @return array
	 */
	public function get_sections() {
		$sections = [
			''     => __( 'Global Availability', 'woocommerce-appointments' ),
			'gcal' => __( 'Google Calendar', 'woocommerce-appointments' ),
		];

		return apply_filters( 'woocommerce_get_sections_' . $this->id, $sections );
	}

	/**
	 * Output the settings
	 *
	 * @since 1.0
	 */
	public function output() {
		global $current_section;

		if ( '' == $current_section ) {
			include 'views/html-settings-global-availability.php';
		} else {
			wp_enqueue_script( 'wc_appointments_writepanel_js' );
			$settings = $this->get_settings( $current_section );
			WC_Admin_Settings::output_fields( $settings );
		}
	}

	/**
	 * Save settings
	 */
	public function save() {
		global $current_section;

		if ( '' == $current_section ) {
			$this->save_global_availability();
		} else {
			$settings = $this->get_settings( $current_section );
			WC_Admin_Settings::save_fields( $settings );
		}

		if ( $current_section ) {
			do_action( 'woocommerce_update_options_' . $this->id . '_' . $current_section );
		}
	}

	/**
	 * Save global availability
	 */
	public function save_global_availability() {
		// Save the field values
		if ( ! empty( $_POST['appointments_availability_submitted'] ) ) {

			// Delete.
			if ( ! empty( $_POST['wc_appointment_availability_deleted'] ) ) {
				$deleted_ids = array_filter( explode( ',', wc_clean( wp_unslash( $_POST['wc_appointment_availability_deleted'] ) ) ) );

				foreach ( $deleted_ids as $delete_id ) {
					$availability_object = get_wc_appointments_availability( $delete_id );
					if ( $availability_object ) {
						$availability_object->delete();
					}
				}
			}

			// Save.
			$types    = isset( $_POST['wc_appointment_availability_type'] ) ? wc_clean( wp_unslash( $_POST['wc_appointment_availability_type'] ) ) : [];
			$row_size = count( $types );

			for ( $i = 0; $i < $row_size; $i ++ ) {
				if ( isset( $_POST['wc_appointment_availability_id'][ $i ] ) ) {
					$current_id = intval( $_POST['wc_appointment_availability_id'][ $i ] );
				} else {
					$current_id = 0;
				}

				$availability = get_wc_appointments_availability( $current_id );
				$availability->set_ordering( $i );
				$availability->set_range_type( $types[ $i ] );
				$availability->set_kind( 'availability#global' );

				if ( isset( $_POST['wc_appointment_availability_appointable'][ $i ] ) ) {
					$availability->set_appointable( wc_clean( wp_unslash( $_POST['wc_appointment_availability_appointable'][ $i ] ) ) );
				}

				if ( isset( $_POST['wc_appointment_availability_title'][ $i ] ) ) {
					$availability->set_title( sanitize_text_field( wp_unslash( $_POST['wc_appointment_availability_title'][ $i ] ) ) );
				}

				if ( isset( $_POST['wc_appointment_availability_qty'][ $i ] ) ) {
					$availability->set_qty( intval( $_POST['wc_appointment_availability_qty'][ $i ] ) );
				}

				if ( isset( $_POST['wc_appointment_availability_priority'][ $i ] ) ) {
					$availability->set_priority( intval( $_POST['wc_appointment_availability_priority'][ $i ] ) );
				}

				switch ( $availability->get_range_type() ) {
					case 'custom':
						if ( isset( $_POST['wc_appointment_availability_from_date'][ $i ] ) && isset( $_POST['wc_appointment_availability_to_date'][ $i ] ) ) {
							$availability->set_from_range( wc_clean( wp_unslash( $_POST['wc_appointment_availability_from_date'][ $i ] ) ) );
							$availability->set_to_range( wc_clean( wp_unslash( $_POST['wc_appointment_availability_to_date'][ $i ] ) ) );
						}
						break;
					case 'months':
						if ( isset( $_POST['wc_appointment_availability_from_month'][ $i ] ) && isset( $_POST['wc_appointment_availability_to_month'][ $i ] ) ) {
							$availability->set_from_range( wc_clean( wp_unslash( $_POST['wc_appointment_availability_from_month'][ $i ] ) ) );
							$availability->set_to_range( wc_clean( wp_unslash( $_POST['wc_appointment_availability_to_month'][ $i ] ) ) );
						}
						break;
					case 'weeks':
						if ( isset( $_POST['wc_appointment_availability_from_week'][ $i ] ) && isset( $_POST['wc_appointment_availability_to_week'][ $i ] ) ) {
							$availability->set_from_range( wc_clean( wp_unslash( $_POST['wc_appointment_availability_from_week'][ $i ] ) ) );
							$availability->set_to_range( wc_clean( wp_unslash( $_POST['wc_appointment_availability_to_week'][ $i ] ) ) );
						}
						break;
					case 'days':
						if ( isset( $_POST['wc_appointment_availability_from_day_of_week'][ $i ] ) && isset( $_POST['wc_appointment_availability_to_day_of_week'][ $i ] ) ) {
							$availability->set_from_range( wc_clean( wp_unslash( $_POST['wc_appointment_availability_from_day_of_week'][ $i ] ) ) );
							$availability->set_to_range( wc_clean( wp_unslash( $_POST['wc_appointment_availability_to_day_of_week'][ $i ] ) ) );
						}
						break;
					case 'rrule':
						// Do nothing rrules are read only for now.
						break;
					case 'time':
					case 'time:1':
					case 'time:2':
					case 'time:3':
					case 'time:4':
					case 'time:5':
					case 'time:6':
					case 'time:7':
						if ( isset( $_POST['wc_appointment_availability_from_time'][ $i ] ) && isset( $_POST['wc_appointment_availability_to_time'][ $i ] ) ) {
							$availability->set_from_range( wc_appointment_sanitize_time( wp_unslash( $_POST['wc_appointment_availability_from_time'][ $i ] ) ) );
							$availability->set_to_range( wc_appointment_sanitize_time( wp_unslash( $_POST['wc_appointment_availability_to_time'][ $i ] ) ) );
						}
						break;
					case 'time:range':
					case 'custom:daterange':
						if ( isset( $_POST['wc_appointment_availability_from_time'][ $i ] ) && isset( $_POST['wc_appointment_availability_to_time'][ $i ] ) ) {
							$availability->set_from_range( wc_appointment_sanitize_time( wp_unslash( $_POST['wc_appointment_availability_from_time'][ $i ] ) ) );
							$availability->set_to_range( wc_appointment_sanitize_time( wp_unslash( $_POST['wc_appointment_availability_to_time'][ $i ] ) ) );
						}
						if ( isset( $_POST['wc_appointment_availability_from_date'][ $i ] ) && isset( $_POST['wc_appointment_availability_to_date'][ $i ] ) ) {
							$availability->set_from_date( wc_clean( wp_unslash( $_POST['wc_appointment_availability_from_date'][ $i ] ) ) );
							$availability->set_to_date( wc_clean( wp_unslash( $_POST['wc_appointment_availability_to_date'][ $i ] ) ) );
						}
						break;
				}

				#update_option( 'xxx_' . $current_id, $_POST );

				$availability->save();
			}
			do_action( 'wc_appointments_global_availability_on_save' );
		}
	}

	/**
	 * Get settings array.
	 *
	 * @param string $current_section Current section name.
	 * @return array
	 */
	public function get_settings( $current_section = '' ) {
		$settings = [];
		if ( 'gcal' === $current_section ) {
			$settings = apply_filters(
				'wc_appointments_gcal_settings',
				[
					[
						'title' => __( 'Google Calendar Sync', 'woocommerce-appointments' ),
						'type'  => 'title',
						'desc'  => sprintf(
							/* translators: %s: link to google calendar sync tutorial */
							__( 'To use this integration go through %s instructions.', 'woocommerce-appointments' ),
							'<a href="https://bookingwp.com/help/setup/wc-appointments/google-calendar-integration/" target="_blank">' . __( 'Google Calendar Integration', 'woocommerce-appointments' ) . '</a>'
						),
						'id'    => 'wc_appointments_gcal_options',
					],
					[
						'title'             => __( 'Client ID', 'woocommerce-appointments' ),
						'desc'              => __( 'Your Google Client ID.', 'woocommerce-appointments' ),
						'id'                => 'wc_appointments_gcal_client_id',
						'type'              => 'password',
						'class'             => 'password-input',
						'custom_attributes' => [
							'autocomplete' => 'off',
						],
						'default'           => '',
						'autoload'          => false,
						'desc_tip'          => true,
					],
					[
						'title'             => __( 'Client Secret', 'woocommerce-appointments' ),
						'desc'              => __( 'Your Google Client Secret.', 'woocommerce-appointments' ),
						'id'                => 'wc_appointments_gcal_client_secret',
						'type'              => 'password',
						'class'             => 'password-input',
						'custom_attributes' => [
							'autocomplete' => 'off',
						],
						'default'           => '',
						'autoload'          => false,
						'desc_tip'          => true,
					],
					[
						'title' => __( 'Authorization', 'woocommerce-appointments' ),
						'type'  => 'gcal_authorization',
					],
					[
						'title' => __( 'Calendar ID', 'woocommerce-appointments' ),
						'type'  => 'gcal_calendar_id',
						'id'    => 'wc_appointments_gcal_calendar_id',
					],
					'gcal_twoway' => [
						'title'    => __( 'Sync Preference', 'woocommerce-appointments' ),
						'desc'     => __( 'Choose the sync preference.', 'woocommerce-appointments' ),
						'options'  => [
							'one_way' => __( 'One way - from Store to Google', 'woocommerce-appointments' ),
							'two_way' => __( 'Two way - between Store and Google', 'woocommerce-appointments' ),
						],
						'id'       => 'wc_appointments_gcal_twoway',
						'default'  => 'one_way',
						'type'     => 'select',
						'class'    => 'wc-enhanced-select',
						'desc_tip' => true,
					],
					'manual_sync' => [
						'title' => __( 'Last Sync', 'woocommerce-appointments' ),
						'type'  => 'manual_sync',
					],
					[
						'type' => 'sectionend',
						'id'   => 'gcal_twoway_options',
					],
					[
						'title' => __( 'Testing', 'woocommerce-appointments' ),
						'type'  => 'title',
						/* translators: %s: log file name string */
						'desc'  => sprintf(
							__( 'Log Google Calendar events, such as API requests, inside %s', 'woocommerce-appointments' ),
							'<code>woocommerce/logs/' . $this->id . '-' . sanitize_file_name( wp_hash( $this->id ) ) . '.txt</code>'
						),
						'id'    => 'wc_appointments_gcal_testing',
					],
					[
						'title'   => __( 'Debug Log', 'woocommerce-appointments' ),
						'desc'    => __( 'Enable logging', 'woocommerce-appointments' ),
						'id'      => 'wc_appointments_gcal_debug',
						'default' => 'no',
						'type'    => 'checkbox',
					],
					[
						'type' => 'sectionend',
						'id'   => 'gcal_debug_options',
					],
				]
			);

			// Run Gcal oauth redirect.
			$gcal_integration_class = wc_appointments_gcal();

			// Get access token.
			$access_token = $gcal_integration_class->get_access_token();

			// Get calendar ID.
			$calendar_id = get_option( 'wc_appointments_gcal_calendar_id' );

			// Stop here if access token no active.
			if ( ! $access_token || ! $calendar_id ) {
				unset( $settings['manual_sync'] );
			}

			// Stop here if access token no active.
			if ( ! $access_token ) {
				unset( $settings['gcal_twoway'] );
			}
		}

		return apply_filters( 'woocommerce_get_settings_' . $this->id, $settings, $current_section );
	}

	/**
	 * Generate the GCal Authorization field.
	 *
	 * @param  mixed $key
	 * @param  array $data
	 *
	 * @echo string
	 */
	public function gcal_authorization_setting( $data ) {
		$client_id     = isset( $_POST['wc_appointments_gcal_client_id'] ) ? sanitize_text_field( $_POST['wc_appointments_gcal_client_id'] ) : get_option( 'wc_appointments_gcal_client_id' );
		$client_secret = isset( $_POST['wc_appointments_gcal_client_secret'] ) ? sanitize_text_field( $_POST['wc_appointments_gcal_client_secret'] ) : get_option( 'wc_appointments_gcal_client_secret' );

		// Run Gcal oauth redirect.
		$gcal_integration_class = wc_appointments_gcal();

		// Get access token.
		$access_token = $gcal_integration_class->get_access_token();

		ob_start();
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<?php echo wp_kses_post( $data['title'] ); ?>
			</th>
			<td class="forminp">
				<input type="hidden" name="wc_appointments_google_calendar_redirect" id="wc_appointments_google_calendar_redirect">
				<?php if ( ! $access_token && ( $client_id && $client_secret ) ) : ?>
					<button type="button" class="button oauth_redirect" data-staff="0" data-logout="0"><?php esc_html_e( 'Connect with Google', 'woocommerce-appointments' ); ?></button>
				<?php elseif ( $access_token ) : ?>
					<p style="color:green;"><?php esc_html_e( 'Successfully authenticated.', 'woocommerce-appointments' ); ?></p>
					<p class="submit"><button type="button" class="button oauth_redirect" data-staff="0" data-logout="1"><?php esc_html_e( 'Disconnect', 'woocommerce-appointments' ); ?></button></p>
				<?php else : ?>
					<p style="color:red;"><?php esc_html_e( 'Please fill out all required fields from above.', 'woocommerce-appointments' ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
		<?php
		echo ob_get_clean();
	}

	/**
	 * Generate the GCal Authorization field.
	 *
	 * @param  mixed $key
	 * @param  array $data
	 *
	 * @echo string
	 */
	public function gcal_calendar_id_setting( $data ) {
		$client_id     = get_option( 'wc_appointments_gcal_client_id' );
		$client_secret = get_option( 'wc_appointments_gcal_client_secret' );
		$calendar_id   = get_option( 'wc_appointments_gcal_calendar_id' );

		// Run Gcal oauth redirect.
		$gcal_integration_class = wc_appointments_gcal();

		// Get access token.
		$access_token = $gcal_integration_class->get_access_token();

		// Get calendars array.
		$get_calendars = $gcal_integration_class->get_calendars();

		if ( ! $access_token || ! $client_id || ! $client_secret ) {
			return;
		}

		ob_start();
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="wc_appointments_gcal_calendar_id">
					<?php echo wp_kses_post( $data['title'] ); ?>
					<?php echo wc_help_tip( esc_html__( 'Your Google Calendar ID. Leave empty if you only want to sync on staff level.', 'woocommerce-appointments' ) ); ?>
				</label>
			</th>
			<td class="forminp">
				<?php if ( $get_calendars ) : ?>
					<select id="wc_appointments_gcal_calendar_id" name="wc_appointments_gcal_calendar_id" class="wc-enhanced-select" style="width:25em;">
						<option value=""><?php esc_html_e( 'N/A', 'woocommerce-appointments' ); ?></option>
						<?php
						// Check if authorized.
						if ( $access_token ) {
							foreach ( $get_calendars as $cal_id => $cal_name ) {
							?>
								<option value="<?php esc_attr_e( $cal_id ); ?>" <?php selected( $calendar_id, $cal_id ); ?>><?php esc_attr_e( $cal_name ); ?></option>
							<?php
							}
						}
						?>
					</select>
				<?php else : ?>
					<input type="text" name="wc_appointments_gcal_calendar_id" id="wc_appointments_gcal_calendar_id" value="<?php esc_attr_e( $calendar_id ); ?>">
				<?php endif; ?>
			</td>
		</tr>
		<?php
		echo ob_get_clean();
	}

	/**
	 * Generate the Manual Sync field.
	 *
	 * @param  mixed $key
	 * @param  array $data
	 *
	 * @echo string
	 */
	public function manual_sync_setting( $data ) {
		$client_id = isset( $_POST['wc_appointments_gcal_client_id'] ) ? sanitize_text_field( $_POST['wc_appointments_gcal_client_id'] ) : false;
		$twoway    = isset( $_POST['wc_appointments_gcal_twoway'] ) ? $_POST['wc_appointments_gcal_twoway'] : false;

		$get_twoway = get_option( 'wc_appointments_gcal_twoway' );

		// Two-way sync not configured yet, enable by default.
		$twoway_enabled = ( 'two_way' !== $get_twoway ) ? false : true;
		$twoway_enabled = $twoway ? true : $twoway_enabled;

		if ( ! $twoway_enabled ) {
			return;
		}

		ob_start();
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<?php echo wp_kses_post( $data['title'] ); ?>
			</th>
			<td class="forminp">
				<?php
				$last_synced = get_option( 'wc_appointments_gcal_availability_last_synced' );
				$last_synced = $last_synced ? $last_synced : '';
				if ( $last_synced ) {
					$ls_timestamp = isset( $last_synced[0] ) && $last_synced[0] ? absint( $last_synced[0] ) : absint( current_time( 'timestamp' ) );
					/* translators: %1$s: date format, %2$s: time format */
					$ls_message = sprintf( __( '%1$s, %2$s', 'woocommerce-appointments' ), date_i18n( wc_appointments_date_format(), $ls_timestamp ), date_i18n( wc_appointments_time_format(), $ls_timestamp ) );
				?>
					<p class="last_synced"><?php esc_attr_e( $ls_message ); ?></p>
				<?php } else { ?>
					<p class="last_synced"><?php esc_html_e( 'No synced rules.', 'woocommerce-appointments' ); ?></p>
				<?php } ?>
				<p class="submit">
					<button type="button" class="button manual_sync"><?php esc_html_e( 'Sync Manually', 'woocommerce-appointments' ); ?></button>
				</p>
			</td>
		</tr>
		<?php
		echo ob_get_clean();
	}

}

return new WC_Appointments_Admin_Settings();
