<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce Twilio SMS Notifications integration class.
 *
 * Last compatibility check: WooCommerce Twilio SMS Notifications 1.19.0
 *
 * @since 3.2.0
 */
class WC_Appointments_Integration_Twilio_SMS {

	/**
	 * Appointments integration constructor.
	 *
	 * @since 4.4.0
	 */
	public function __construct() {
		// Requires at least WooCommerce 3.4.
		if ( version_compare( WC_VERSION, '3.4', '<' ) ) {
			return;
		}

		// Requires WC_Twilio_SMS class.
		if ( ! class_exists( 'WC_Twilio_SMS' ) && ! is_callable( array( 'WC_Twilio_SMS', 'VERSION' ) ) ) {
			return;
		}

		#print '<pre>'; print_r( WC_Twilio_SMS::VERSION ); print '</pre>';

		// Legacy.
		if ( version_compare( WC_Twilio_SMS::VERSION, '1.12.0', '<' ) ) {
			// include legacy class.
			include_once __DIR__ . '/class-wc-appointments-integration-twilio-sms-legacy.php';

			// Stop here.
			return;
		}

		// include notification schedule class.
		include_once __DIR__ . '/class-wc-appointments-integration-notification-schedule.php';

		if ( is_admin() ) {

			// add appointments section to the Twilio SMS tab
			add_filter( 'wc_twilio_sms_sections', array( $this, 'add_settings_section' ) );

			// add Appointments settings to the WooCommerce SMS tab
			add_filter( 'wc_twilio_sms_settings', array( $this, 'get_settings' ) );

			// save Appointments settings page
			add_action( 'woocommerce_update_options_' . \WC_Twilio_SMS_Admin::$tab_id, array( $this, 'process_settings' ) );

			// add appointments notification tab
			add_action( 'woocommerce_product_write_panel_tabs', array( $this, 'add_appointment_notification_tab' ), 11 );

			// add appointments notification fields
			add_action( 'woocommerce_product_data_panels', array( $this, 'add_appointment_notification_tab_options' ), 11 );

			// save appointments notification
			add_action( 'woocommerce_process_product_meta', array( $this, 'save_appointment_notification_tab_options' ) );

			// add custom appointment schedule field type
			add_action( 'woocommerce_admin_field_wc_twilio_sms_appointment_schedule', array( $this, 'output_appointment_schedule_field_html' ) );
		}

		// modify checkout optin
		add_filter( 'wc_twilio_sms_checkout_optin_label', array( $this, 'modify_checkout_label' ) );

		/**
		 * Handle scheduled events
		 */

		// schedule appointment reminder notifications for both the customer and the admin
		add_action( 'woocommerce_appointment_paid', array( $this, 'schedule_appointment_reminder_notifications' ) );

		// schedule appointment follow-up notifications for the customer
		add_action( 'wc-appointment-complete', array( $this, 'schedule_appointment_follow_up_notifications' ) );

		// clear scheduled notifications if an appointment is cancelled or deleted
		add_action( 'woocommerce_appointment_cancelled', array( $this, 'clear_scheduled_notifications' ) );
		add_action( 'woocommerce_delete_appointment', array( $this, 'clear_scheduled_notifications' ) );

		// reschedule notifications when needed
		add_action( 'woocommerce_appointment_process_meta', array( $this, 'reschedule_appointment_notifications' ) );

		/**
		 * Send notifications
		 */

		// send appointment confirmation to the customer
		add_action( 'woocommerce_appointment_confirmed_notification', array( $this, 'send_customer_confirmed_appointment_notification' ) );

		// send appointment cancellation notifications to both the customer and the admin
		add_action( 'woocommerce_appointment_cancelled', array( $this, 'send_cancelled_appointment_notifications' ) );

		// send an appointment reminder notification to the admin
		add_action( 'wc_twilio_sms_appointments_admin_reminder_notification', array( $this, 'send_admin_reminder_appointment_notification' ) );

		// send an appointment reminder notification to the customer
		add_action( 'wc_twilio_sms_appointments_customer_reminder_notification', array( $this, 'send_customer_reminder_appointment_notification' ) );

		// send an appointment follow-up notification to the customer
		add_action( 'wc_twilio_sms_appointments_customer_follow_up_notification', array( $this, 'send_customer_follow_up_appointment_notification' ) );
	}


	/** Settings methods ******************************************************/


	/**
	 * Adds the Appointments section to the SMS tab.
	 *
	 * @since 4.4.0
	 *
	 * @param array $sections the existing SMS sections
	 * @return array the SMS sections plus Appointments
	 */
	public function add_settings_section( $sections ) {
		wp_enqueue_script( 'wc_appointments_writepanel_js' );

		$sections['appointments'] = __( 'Appointments', 'woocommerce-appointments' );

		return $sections;
	}


	/**
	 * Builds array of plugin settings in format needed to use WC admin settings API.
	 *
	 * @since 4.4.0
	 *
	 * @see woocommerce_admin_fields()
	 * @see woocommerce_update_options()
	 * @param array $settings the current settings
	 * @return array settings
	 */
	public function get_settings( $settings = [] ) {
		if ( ! isset( $_GET['section'] ) || 'appointments' !== $_GET['section'] ) {
			return $settings;
		}

		$settings = array(

			array(
				'name' => __( 'Appointments Settings', 'woocommerce-appointments' ),
				'type' => 'title',
			),

			array(
				'id'       => 'wc_twilio_sms_appointments_optin_checkbox_label',
				'name'     => __( 'Opt-in Checkbox Label', 'woocommerce-appointments' ),
				'desc_tip' => __( 'This message overrides the default label when an appointment is purchased.', 'woocommerce-appointments' ),
				'css'      => 'min-width: 275px;',
				'default'  => __( 'Please send appointment reminders via text message', 'woocommerce-appointments' ),
				'type'     => 'text',
			),

			array( 'type' => 'sectionend' ),

			array(
				'name' => __( 'Admin Notifications', 'woocommerce-appointments' ),
				'type' => 'title',
			),
		);

		// add admin settings
		$all_notifications = $this->get_appointment_notifications();

		foreach ( $all_notifications as $notification => $label ) {

			if ( $this->is_admin_notification( $notification ) ) {
				$settings = array_merge( $settings, $this->build_setting( $notification, $all_notifications ) );
			}
		}

		$settings[] = array( 'type' => 'sectionend' );

		$settings[] = array(
			'name' => __( 'Customer Notifications', 'woocommerce-appointments' ),
			'type' => 'title',
		);

		foreach ( $all_notifications as $notification => $label ) {

			if ( ! $this->is_admin_notification( $notification ) ) {
				$settings = array_merge( $settings, $this->build_setting( $notification, $all_notifications ) );
			}
		}

		$settings[] = array( 'type' => 'sectionend' );

		return $settings;
	}


	/**
	 * Build the settings options for a notification.
	 *
	 * @since 4.4.0
	 *
	 * @param string $notification the notification slug
	 * @param array $all_notifications all available notifications
	 * @return array the notification settings
	 */
	protected function build_setting( $notification, $all_notifications ) {
		/* translators: %1$s is <code>, %2$s is </code> */
		$default_description = sprintf( __( 'Use these tags to customize your message: %1$s{billing_name}%2$s, %1$s{appointment_start_time}%2$s, %1$s{appointment_end_time}%2$s, %1$s{appointment_date}%2$s, %1$s{staff}%2$s, %1$s{appointment_pay}%2$s. Remember that SMS messages may be limited to 160 characters or less.', 'woocommerce-appointments' ), '<code>', '</code>' );

		$setting = array(
			array(
				'id'                => "wc_twilio_sms_appointments_send_{$notification}",
				'name'              => $all_notifications[ $notification ],
				/* translators: Placeholder: %s = notification label */
				'desc'              => sprintf( __( 'Send %s SMS notifications', 'woocommerce-appointments' ), strtolower( $all_notifications[ $notification ] ) ),
				'default'           => 'no',
				'type'              => 'checkbox',
				'class'             => 'wc_twilio_sms_enable',
				'custom_attributes' => array(
					'data-notification' => $notification,
				),
			),
		);

		if ( $this->is_admin_notification( $notification ) ) {

			$setting[] = array(
				'id'          => "wc_twilio_sms_appointments_{$notification}_recipients",
				'name'        => __( 'Notification recipient(s)', 'woocommerce-appointments' ),
				'desc_tip'    => __( 'Enter the mobile number (starting with the country code) where the notification should be sent. Send to multiple recipients by separating numbers with commas.', 'woocommerce-appointments' ),
				'placeholder' => '1-555-867-5309',
				'type'        => 'text',
			);
		}

		if ( $this->notification_has_schedule( $notification ) ) {

			$schedule = $suffix = '';

			switch ( $notification ) {

				case 'customer_follow_up':
					$schedule = '3:days';
					$suffix   = __( 'after appointment ends', 'woocommerce-appointments' );
					break;

				case 'customer_reminder':
					$schedule = '24:hours';
					$suffix   = __( 'before appointment starts', 'woocommerce-appointments' );
					break;

				case 'admin_reminder':
					$schedule = '15:minutes';
					$suffix   = __( 'before appointment starts', 'woocommerce-appointments' );
					break;
			}

			$setting[] = array(
				'id'         => "wc_twilio_sms_appointments_{$notification}_schedule",
				'name'       => __( 'Send this message', 'woocommerce-appointments' ),
				'default'    => $schedule,
				'value'      => get_option( "wc_twilio_sms_appointments_{$notification}_schedule", '' ),
				'post_field' => $suffix,
				'type'       => 'wc_twilio_sms_appointment_schedule',
			);
		}

		$setting[] = array(
			'id'      => "wc_twilio_sms_appointments_{$notification}_template",
			/* translators: Placeholder: %s = notification label */
			'name'    => sprintf( __( '%s message', 'woocommerce-appointments' ), $all_notifications[ $notification ] ),
			'desc'    => $default_description,
			'css'     => 'min-width:500px;',
			'default' => $this->get_default_template( $notification ),
			'type'    => 'textarea',
		);

		return $setting;
	}


	/**
	 * Updates appointments notifications settings.
	 *
	 * @since 4.4.0
	 *
	 * @see woocommerce_update_options()
	 * @uses Appointments::get_settings() to get settings array
	 */
	public function process_settings() {
		// save the appointment notification schedules
		$appointment_schedule_fields = array(
			'wc_twilio_sms_appointments_admin_reminder_schedule',
			'wc_twilio_sms_appointments_customer_reminder_schedule',
			'wc_twilio_sms_appointments_customer_follow_up_schedule',
		);

		foreach ( $appointment_schedule_fields as $field_name ) {

			if ( isset( $_POST[ $field_name . '_number' ], $_POST[ $field_name . '_modifier' ] ) ) {

				$number   = absint( $_POST[ $field_name . '_number' ] );
				$modifier = wc_clean( $_POST[ $field_name . '_modifier' ] );

				// validate and sanitize a the appointment schedule
				$appointment_schedule = new WC_Appointments_Integration_Notification_Schedule();

				// restrict reminder to 48 hours prior to event
				$restricted_number = ( strstr( $field_name, 'reminder' ) ) ? $appointment_schedule->get_restricted_reminder( $number, $modifier ) : $number;

				$appointment_schedule->set_value( $restricted_number, $modifier );

				update_option( $field_name, $appointment_schedule->get_value() );

				// inform the user if they have set a reminder schedule greater than 48 hours before the event
				if ( $restricted_number !== $number ) {

					$this->add_restricted_schedule_message();
				}
			}
		}

		// save all other options
		woocommerce_update_options( $this->get_settings() );
	}


	/**
	 * Adds an 'SMS Notifications' tab to the product options.
	 *
	 * @since 4.4.0
	 */
	public function add_appointment_notification_tab() {
		?>
		<li class="wc-twilio-sms-tab show_if_appointment">
			<a href="#wc-twilio-sms-appointments-data"><span><?php esc_html_e( 'SMS Notification', 'woocommerce-appointments' ); ?></span></a>
		</li>
		<?php
	}


	/**
	 * Adds appointments notification options to the products.
	 *
	 * @since 4.4.0
	 */
	public function add_appointment_notification_tab_options() {
		$product = wc_get_product( get_the_ID() );

		if ( ! $product ) {
			return;
		}

		$appointments_options = $product->get_meta( '_wc_twilio_sms_appointments_options' );
		$available_options    = array(
			'global'   => __( 'Use global settings', 'woocommerce-appointments' ),
			'override' => __( 'Override global settings', 'woocommerce-appointments' ),
			'disabled' => __( 'Don\'t send', 'woocommerce-appointments' ),
		);

		/* translators: %1$s is <code>, %2$s is </code> */
		$default_description = sprintf( __( 'Use these tags to customize your message: %1$s{billing_name}%2$s, %1$s{appointment_start_time}%2$s, %1$s{appointment_end_time}%2$s, %1$s{appointment_date}%2$s, %1$s{staff}%2$s. Remember that SMS messages may be limited to 160 characters or less.', 'woocommerce-appointments' ), '<code>', '</code>' );

		?>
		<div id="wc-twilio-sms-appointments-data" class="panel woocommerce_options_panel">
			<?php foreach ( $this->get_appointment_notifications() as $notification => $label ) : ?>
			<div class="options_group">
				<?php
				woocommerce_wp_select(
					array(
						'id'                => "wc_twilio_sms_appointments_{$notification}_override",
						'label'             => $label,
						'options'           => $available_options,
						'value'             => ( ! empty( $appointments_options[ $notification ] ) ) ? $appointments_options[ $notification ] : 'global',
						'class'             => 'wc_twilio_sms_notification_toggle',
						'custom_attributes' => array(
							'data-notification' => $notification,
						),
					)
				);

				if ( $this->notification_has_schedule( $notification ) ) :
					$title      = 'customer_follow_up' === $notification ? __( 'Send follow-up', 'woocommerce-appointments' ) : __( 'Send reminder', 'woocommerce-appointments' );
					$post_field = 'customer_follow_up' === $notification ? __( 'after appointment ends', 'woocommerce-appointments' ) : __( 'before appointment starts', 'woocommerce-appointments' );
					?>

					<p class="form-field">
						<?php
						$this->output_appointment_schedule_field_html(
							array(
								'id'         => "wc_twilio_sms_appointments_{$notification}_schedule",
								'title'      => $title,
								'default'    => '24:hours',
								'value'      => ! empty( $appointments_options[ "{$notification}_schedule" ] ) ? $appointments_options[ "{$notification}_schedule" ] : '',
								'post_field' => $post_field,
								'type'       => 'wc_twilio_sms_appointment_schedule',
							)
						);
						?>
					</p>
				<?php endif; ?>

				<?php
				woocommerce_wp_textarea_input(
					array(
						'id'          => "wc_twilio_sms_appointments_{$notification}_template",
						'label'       => __( 'Message', 'woocommerce-appointments' ),
						'description' => $default_description,
						'value'       => ! empty( $appointments_options[ "{$notification}_template" ] ) ? $appointments_options[ "{$notification}_template" ] : $this->get_default_template( $notification ),
					)
				);
				?>
			</div>
			<?php endforeach; ?>
		</div>
		<?php
	}


	/**
	 * Saves appointment notification options at the product level.
	 *
	 * @since 4.4.0
	 *
	 * @param int $product_id the ID of the product being saved
	 */
	public function save_appointment_notification_tab_options( $product_id ) {
		$options = [];
		$product = wc_get_product( $product_id );

		if ( $product ) {

			foreach ( array_keys( $this->get_appointment_notifications() ) as $notification ) {

				if ( isset( $_POST[ "wc_twilio_sms_appointments_{$notification}_override" ] ) && $this->is_valid_product_override_option( $_POST[ "wc_twilio_sms_appointments_{$notification}_override" ] ) ) {

					$options[ $notification ]              = wc_clean( $_POST[ "wc_twilio_sms_appointments_{$notification}_override" ] );
					$options[ "{$notification}_template" ] = wc_clean( $_POST[ "wc_twilio_sms_appointments_{$notification}_template" ] );

					if ( $this->notification_has_schedule( $notification ) ) {

						$schedule_number   = (int) $_POST[ "wc_twilio_sms_appointments_{$notification}_schedule_number" ];
						$schedule_modifier = wc_clean( $_POST[ "wc_twilio_sms_appointments_{$notification}_schedule_modifier" ] );

						$notification_schedule = new WC_Appointments_Integration_Notification_Schedule();

						// restrict reminder to 48 hours prior to event
						$restricted_schedule_number = in_array( $notification, array( 'admin_reminder', 'customer_reminder' ), true ) ? $notification_schedule->get_restricted_reminder( $schedule_number, $schedule_modifier ) : $schedule_number;

						// use defaults if schedule is not set
						if ( $restricted_schedule_number > 0 ) {
							$notification_schedule->set_value( $restricted_schedule_number, $schedule_modifier );

							$options[ "{$notification}_schedule" ] = $notification_schedule->get_value();

							// inform the user if they have set a reminder schedule greater than 48 hours before the event
							if ( $restricted_schedule_number !== $schedule_number ) {

								$this->add_restricted_schedule_message();
							}
						} else {
							$options[ "{$notification}_schedule" ] = get_option( "wc_twilio_sms_appointments_{$notification}_schedule" );
						}
					}
				}
			}

			$product->update_meta_data( '_wc_twilio_sms_appointments_options', $options );
			$product->save();
		}
	}


	/**
	 * Outputs HTML markup for the appointment schedule field.
	 *
	 * @since 4.4.0
	 *
	 * @param array $args
	 */
	public function output_appointment_schedule_field_html( $args ) {
		// get the current appointment schedule from the field ID or default value
		$value = isset( $args['value'] ) && ! empty( $args['value'] ) ? $args['value'] : $args['default'];

		$appointment_schedule = new WC_Appointments_Integration_Notification_Schedule( $value );

		echo $appointment_schedule->get_field_html( $args, true );
	}


	/** Notification schedules & sending ********************************************/


	/**
	 * Modify the optin checkbox label when the cart contains an appointment.
	 *
	 * @since 4.4.0
	 *
	 * @param string $label the initial checkbox label
	 * @return string updated label
	 */
	public function modify_checkout_label( $label ) {
		if ( WC()->cart && is_array( WC()->cart->get_cart() ) ) {
			foreach ( WC()->cart->get_cart() as $cart_item ) {

				if ( $cart_item['data'] instanceof \WC_Product && is_wc_appointment_product( $cart_item['data'] ) ) {
					$appointmentsLabel = get_option( 'wc_twilio_sms_appointments_optin_checkbox_label', __( 'Please send appointment reminders via text message', 'woocommerce-appointments' ) );

					if ( ! empty( $appointmentsLabel ) ) {
						$label = $appointmentsLabel;
					}
					break;
				}
			}
		}

		return $label;
	}


	/**
	 * Clears all scheduled events related to an appointment.
	 *
	 * @param int $appointment_id the ID of the related appointment
	 */
	public function clear_scheduled_notifications( $appointment_id ) {
		$hook_args = array(
			'appointment_id' => $appointment_id,
		);

		as_unschedule_action( 'wc_twilio_sms_appointments_admin_reminder_notification', $hook_args );
		as_unschedule_action( 'wc_twilio_sms_appointments_customer_reminder_notification', $hook_args );
		as_unschedule_action( 'wc_twilio_sms_appointments_customer_follow_up_notification', $hook_args );
	}


	/**
	 * Reschedules appointment notifications when an appointment is saved in case of changes.
	 *
	 * @since 4.4.0
	 *
	 * @param int $appointment_id the appointment ID
	 */
	public function reschedule_appointment_notifications( $appointment_id ) {
		$appointment = get_wc_appointment( $appointment_id );

		if ( $appointment ) {

			// we could probably check if the time has changed, but for now, just clear + reschedule
			$this->clear_scheduled_notifications( $appointment_id );

			if ( $appointment->has_status( 'complete' ) ) {

				$this->schedule_appointment_follow_up_notifications( $appointment_id );

			} elseif ( $appointment->has_status( array( 'paid', 'confirmed' ) ) ) {

				$this->schedule_appointment_reminder_notifications( $appointment_id );
			}
		}
	}


	/**
	 * Schedules admin and customer SMS message reminders for a confirmed appointment.
	 *
	 * @since 4.4.0
	 *
	 * @param int $appointment_id the ID of the related appointment
	 */
	public function schedule_appointment_reminder_notifications( $appointment_id ) {
		$this->schedule_appointment_notification( $appointment_id, 'admin_reminder' );
		$this->schedule_appointment_notification( $appointment_id, 'customer_reminder' );
	}


	/**
	 * Schedules customer SMS post-appointment messages.
	 *
	 * @since 4.4.0
	 *
	 * @param int $appointment_id the ID of the related appointment
	 */
	public function schedule_appointment_follow_up_notifications( $appointment_id ) {
		// clear any reminders just to be sure
		$this->clear_scheduled_notifications( $appointment_id );

		$this->schedule_appointment_notification( $appointment_id, 'customer_follow_up', true );
	}


	/**
	 * Schedules an appointment notification to send later.
	 *
	 * @since 4.4.0
	 *
	 * @param int $appointment_id the appointment ID
	 * @param string $notification the notification slug
	 * @param bool $after_event true if the notification should be sent after the event is complete
	 */
	public function schedule_appointment_notification( $appointment_id, $notification, $after_event = false ) {
		$appointment = get_wc_appointment( $appointment_id );

		if ( $appointment ) {

			$hook_args = array(
				'appointment_id' => $appointment_id,
			);

			$schedule = $this->get_notification_schedule( $notification, $appointment->get_product() );

			if ( ! empty( $schedule ) ) {

				try {

					$timezone = new \DateTimeZone( wc_timezone_string() );

					$start_date = ( new \DateTime( date( 'Y-m-d H:i:s', $appointment->get_start() ), $timezone ) )->getTimestamp();
					$end_date   = ( new \DateTime( date( 'Y-m-d H:i:s', $appointment->get_end() ), $timezone ) )->getTimestamp();

				} catch ( \Exception $e ) {

					$start_date = $appointment->get_start() - wc_timezone_offset();
					$end_date   = $appointment->get_end() - wc_timezone_offset();
				}

				// determine when to send this notification and schedule action
				$notification_schedule  = new WC_Appointments_Integration_Notification_Schedule( $schedule );
				$notification_timestamp = $after_event ? $notification_schedule->get_time_after( $end_date ) : $notification_schedule->get_time_before( $start_date );

				if ( ! as_next_scheduled_action( "wc_twilio_sms_appointments_{$notification}_notification", $hook_args ) ) {
					as_schedule_single_action( $notification_timestamp, "wc_twilio_sms_appointments_{$notification}_notification", $hook_args, 'woocommerce-appointments' );
				}
			}
		}
	}


	/**
	 * Sends an admin reminder SMS message for an upcoming appointment.
	 *
	 * @since 4.4.0
	 *
	 * @param int $appointment_id the ID of the related appointment
	 */
	public function send_admin_reminder_appointment_notification( $appointment_id ) {
		if ( $this->is_notification_enabled( $appointment_id, 'admin_reminder' ) ) {
			$this->send_appointment_notification( $appointment_id, 'admin_reminder' );
		}
	}


	/**
	 * Sends an appointment reminder SMS message to a customer.
	 *
	 * @since 4.4.0
	 *
	 * @param int $appointment_id the ID of the related appointment
	 */
	public function send_customer_reminder_appointment_notification( $appointment_id ) {
		if ( $this->is_notification_enabled( $appointment_id, 'customer_reminder' ) ) {
			$this->send_appointment_notification( $appointment_id, 'customer_reminder' );
		}
	}


	/**
	 * Sends an appointment follow-up SMS message to a customer.
	 *
	 * @since 4.4.0
	 *
	 * @param int $appointment_id the ID of the related appointment
	 */
	public function send_customer_follow_up_appointment_notification( $appointment_id ) {
		if ( $this->is_notification_enabled( $appointment_id, 'customer_follow_up' ) ) {
			$this->send_appointment_notification( $appointment_id, 'customer_follow_up' );
		}
	}


	/**
	 * Sends a customer SMS message when an appointment is confirmed.
	 *
	 * @since 4.4.0
	 *
	 * @param int $appointment_id the ID of the related appointment
	 */
	public function send_customer_confirmed_appointment_notification( $appointment_id ) {
		if ( $this->is_notification_enabled( $appointment_id, 'customer_confirmation' ) ) {
			$this->send_appointment_notification( $appointment_id, 'customer_confirmation' );
		}

		// now schedule reminders if they're not set already
		$this->schedule_appointment_reminder_notifications( $appointment_id );
	}


	/**
	 * Sends SMS messages relevant to a cancelled appointment.
	 *
	 * @since 4.4.0
	 *
	 * @param int $appointment_id the ID of the related appointment
	 */
	public function send_cancelled_appointment_notifications( $appointment_id ) {
		if ( $this->is_notification_enabled( $appointment_id, 'admin_cancellation' ) ) {
			$this->send_appointment_notification( $appointment_id, 'admin_cancellation' );
		}

		if ( $this->is_notification_enabled( $appointment_id, 'customer_cancellation' ) ) {
			$this->send_appointment_notification( $appointment_id, 'customer_cancellation' );
		}
	}


	/** Notification helpers ******************************************************/


	/**
	 * Sends an appointment notification.
	 *
	 * @since 4.4.0
	 *
	 * @param int $appointment_id the appointment ID
	 * @param string $notification the notification type
	 */
	public function send_appointment_notification( $appointment_id, $notification ) {

		#error_log( var_export( $appointment_id, true ) );
		#error_log( var_export( $notification, true ) );

		if ( $appointment = get_wc_appointment( $appointment_id ) ) {

			$template = $this->get_template( $notification, $appointment->get_product() );

			if ( ! $template ) {
				return;
			}

			$message = $this->build_sms_message( $template, $appointment );

			#error_log( var_export( $message, true ) );

			if ( $this->is_admin_notification( $notification ) ) {

				$phone_numbers = $this->parse_mobile_numbers( get_option( "wc_twilio_sms_appointments_{$notification}_recipients", [] ) );

				#error_log( var_export( $phone_numbers, true ) );

				foreach ( $phone_numbers as $number ) {
					$this->send_sms_message( $number, $message );
				}
			} else {

				$appointment_order = $appointment->get_order();
				$phone_number      = $appointment_order ? $appointment_order->get_billing_phone() : '';
				$country_code      = $this->get_customer_country_code( $appointment );

				if ( '' !== $phone_number ) {
					$this->send_sms_message( $phone_number, $message, $country_code );
				}
			}
		}
	}


	/**
	 * Takes a CSV string of mobile phone numbers and returns those in an array.
	 *
	 * @since 4.4.0
	 *
	 * @param string $mobile_numbers CSV string of mobile phone numbers
	 * @return array array of mobile phone numbers
	 */
	public function parse_mobile_numbers( $mobile_numbers ) {
		return array_map( 'trim', explode( ',', $mobile_numbers ) );
	}


	/**
	 * Replaces tokens in an SMS message template with data from an appointment.
	 *
	 * @since 4.4.0
	 *
	 * @param string $message
	 * @param \WC_Appointment $appointment
	 * @return string $message
	 */
	public function build_sms_message( $message, \WC_Appointment $appointment ) {
		/** @var \WC_Order $order */
		$order = $appointment->get_order();

		$billing_name    = $order ? $order->get_formatted_billing_full_name() : '';
		$appointment_pay = $order && 'pending' === $order->get_status() && 0 < $order->get_total() ? sprintf(
			/* translators: %s: checkout payment url */
			esc_html__( 'To pay for this appointment please use the following link: %s', 'woocommerce-appointments' ),
			'<a href="' . esc_url( $order->get_checkout_payment_url() ) . '">' . esc_attr__( 'Pay for appointment', 'woocommerce-appointments' ) . '</a>'
		) : '';

		$date_format = wc_appointments_date_format();
		$time_format = wc_appointments_time_format();

		$token_map = array(
			'{shop_name}'              => get_bloginfo( 'name' ),
			'{billing_name}'           => $billing_name,
			'{appointment_start_time}' => date_i18n( $time_format, $appointment->get_start() ),
			'{appointment_end_time}'   => date_i18n( $time_format, $appointment->get_end() ),
			'{appointment_date}'       => date_i18n( $date_format, $appointment->get_start() ),
			'{staff}'                  => $appointment->get_staff_members( true, false ), #1- with names, 2- with links
			'{appointment_pay}'        => $appointment_pay,
		);

		/**
		 * Allow actors to change the SMS message tokens.
		 *
		 * @since 4.4.0
		 *
		 * @param bool $token_map
		 */
		$token_map = (array) apply_filters( 'wc_twilio_sms_appointments_token_map', $token_map, $appointment );

		foreach ( $token_map as $key => $value ) {

			$message = str_replace( $key, $value, $message );
		}

		return $message;
	}


	/**
	 * Sends a message to a mobile phone number via SMS.
	 *
	 * @since 4.4.0
	 *
	 * @param string $mobile_number the phone number to send the message to
	 * @param string $message the message to send
	 * @param string $country_code (optional) country code in ISO_3166-1_alpha-2 format
	 */
	public function send_sms_message( $mobile_number, $message, $country_code = null ) {
		// sanitize input
		$mobile_number = trim( $mobile_number );
		$message       = sanitize_text_field( $message );

		#error_log( var_export( $mobile_number, true ) );
		#error_log( var_export( $message, true ) );
		#error_log( var_export( $country_code, true ) );

		try {

			if ( \WC_Twilio_SMS_URL_Shortener::using_shortened_urls() ) {

				$message = \WC_Twilio_SMS_URL_Shortener::shorten_urls( $message );
			}

			wc_twilio_sms()->get_api()->send( $mobile_number, $message, $country_code );

		} catch ( \Exception $e ) {

			$error_message = sprintf( __( 'Error sending SMS: %s', 'woocommerce-appointments' ), $e->getMessage() );

			wc_twilio_sms()->log( $error_message );
		}
	}


	/**
	 * Gets the available appointment notifications.
	 *
	 * @since 4.4.0
	 *
	 * @return array available notifications as slug => label
	 */
	public function get_appointment_notifications() {
		/**
		 * Filter the available appointment notifications. Lets actors add their own notification key.
		 *
		 * @since 4.4.0
		 *
		 * @param array notifications as slug => label
		 */
		return (array) apply_filters(
			'wc_twilio_sms_appointments_notifications',
			array(
				'admin_reminder'        => __( 'Admin reminder', 'woocommerce-appointments' ),
				'admin_cancellation'    => __( 'Admin cancellation', 'woocommerce-appointments' ),
				'customer_reminder'     => __( 'Customer reminder', 'woocommerce-appointments' ),
				'customer_follow_up'    => __( 'Customer follow up', 'woocommerce-appointments' ),
				'customer_cancellation' => __( 'Customer cancellation', 'woocommerce-appointments' ),
				'customer_confirmation' => __( 'Customer confirmation', 'woocommerce-appointments' ),
			)
		);
	}


	/**
	 * Gets the template for a notification.
	 *
	 * @since 4.4.0
	 *
	 * @param string $notification the notification slug
	 * @param \WC_Product|null $product
	 * @return string|null $template
	 */
	protected function get_template( $notification, \WC_Product $product = null ) {
		// default to global template before checking for product-specific template
		$template = get_option( "wc_twilio_sms_appointments_{$notification}_template", '' );

		/**
		 * Get the product-specific appointments notification options.
		 * @see save_appointment_notification_tab_options()
		 */
		if ( $product ) {

			$appointments_options = $product->get_meta( '_wc_twilio_sms_appointments_options' );

			if ( ! empty( $appointments_options ) && isset( $appointments_options[ $notification ] ) && 'override' === $appointments_options[ $notification ] ) {

				$template = $appointments_options[ "{$notification}_template" ];
			}
		}

		/**
		 * Allow actors to change the SMS template.
		 *
		 * @since 4.4.0
		 *
		 * @param string the found message template.
		 */
		return apply_filters( 'wc_twilio_sms_appointments_notification_template', $template, $notification );
	}


	/**
	 * Gets the default notification templates.
	 *
	 * @since 4.4.0
	 *
	 * @param string $notification the notification slug
	 * @return string the default notification template
	 */
	public function get_default_template( $notification ) {
		$default = __( 'The {shop_name} appointment for {billing_name} starts at {appointment_start_time} on {appointment_date}', 'woocommerce-appointments' );

		/**
		 * Filters the default SMS templates for notifications.
		 *
		 * @since 4.4.0
		 *
		 * @param array default templates
		 */
		$templates = (array) apply_filters(
			'wc_twilio_sms_appointments_default_notification_templates',
			array(
				'admin_reminder'        => __( "Heads up: your appointment with {billing_name} starts at {appointment_start_time}", 'woocommerce-appointments' ),
				'admin_cancellation'    => __( 'Your appointment with {billing_name} at {appointment_start_time} on {appointment_date} has been cancelled', 'woocommerce-appointments' ),
				'customer_reminder'     => __( 'Hi {billing_name}! This is a reminder that your {shop_name} appointment starts at {appointment_start_time} on {appointment_date}. See you soon!', 'woocommerce-appointments' ),
				'customer_follow_up'    => __( 'Thanks again for appointment with {shop_name}, {billing_name}! We hope to see you again soon.', 'woocommerce-appointments' ),
				'customer_cancellation' => __( 'Your appointment with {shop_name} at {appointment_start_time} on {appointment_date} has been cancelled', 'woocommerce-appointments' ),
				'customer_confirmation' => __( 'Your appointment with {shop_name} at {appointment_start_time} on {appointment_date} has been confirmed. See you there! {appointment_pay}', 'woocommerce-appointments' ),
			)
		);

		return in_array( $notification, array_keys( $templates ), true ) ? $templates[ $notification ] : $default;
	}


	/**
	 * Checks if a notification has a schedule.
	 *
	 * @since 4.4.0
	 *
	 * @param string $notification notification key
	 * @return bool true if it has a schedule
	 */
	public function notification_has_schedule( $notification ) {
		$has_schedule = in_array( $notification, array( 'admin_reminder', 'customer_reminder', 'customer_follow_up' ), true );

		/**
		 * Filters whether the notification has a schedule.
		 *
		 * @since 4.4.0
		 *
		 * @param bool if the notification has a schedule
		 * @param string notification key
		 */
		return (bool) apply_filters( 'wc_twilio_sms_appointments_notification_has_schedule', $has_schedule, $notification );
	}


	/**
	 * Returns a notification schedule in the format used by the WC_Appointments_Integration_Notification_Schedule class.
	 *
	 * @since 4.4.0
	 *
	 * @param string $notification the notification slug
	 * @param \WC_Product|null $product
	 * @return string|null schedule in the format d:s+ (example: 5:days)
	 */
	public function get_notification_schedule( $notification, \WC_Product $product = null ) {
		// default to global schedule before checking for product-specific schedule
		$schedule = get_option( "wc_twilio_sms_appointments_{$notification}_schedule", '' );

		if ( $product ) {

			/**
			 * Get the product-specific appointments notification options.
			 * @see save_appointment_notification_tab_options()
			 */
			$appointments_options = $product->get_meta( '_wc_twilio_sms_appointments_options' );

			if ( ! empty( $appointments_options ) && isset( $appointments_options[ $notification ] ) && 'override' === $appointments_options[ $notification ] ) {

				$schedule = $appointments_options[ "{$notification}_schedule" ];
			}
		}

		/**
		 * Allow actors to change the schedule.
		 *
		 * @since 4.4.0
		 *
		 * @param string|null the configured schedule
		 */
		return apply_filters( 'wc_twilio_sms_appointments_notification_schedule', $schedule, $notification );
	}


	/**
	 * Determines if an appointment notification should be sent.
	 *
	 * @since 4.4.0
	 *
	 * @param int $appointment_id the appointment ID
	 * @param string $notification the notification type
	 * @return bool true if an appointment confirmation notification should be sent
	 */
	public function is_notification_enabled( $appointment_id, $notification ) {
		// default to global setting before checking for product-specific setting
		$enabled     = 'yes' === get_option( "wc_twilio_sms_appointments_send_{$notification}" );
		$appointment = get_wc_appointment( $appointment_id );
		$product     = $appointment->get_product();

		if ( $appointment && $product ) {

			/**
			 * Get the product-specific appointments notification options.
			 * @see save_appointment_notification_tab_options()
			 */
			$appointments_options = $product->get_meta( '_wc_twilio_sms_appointments_options' );

			if ( ! empty( $appointments_options ) && isset( $appointments_options[ $notification ] ) ) {

				// check for options other than 'global' and set enabled status accordingly
				if ( 'override' === $appointments_options[ $notification ] ) {

					$enabled = true;

				} elseif ( 'disabled' === $appointments_options[ $notification ] ) {

					$enabled = false;
				}
			}

			// for customer notifications, if we think we should be sending it, confirm the customer has opted in
			if ( ! $this->is_admin_notification( $notification ) && $enabled && $appointment->get_order() ) {
				// Kex changed from 1.19.0 onward.
				if ( version_compare( WC_Twilio_SMS::VERSION, '1.19.0', '>=' ) ) {
					/** @var string full name of the meta key */
					$meta_key = '_wc_other/skyverge/twilio-opt-in';
				} else {
					/** @var string name of the legacy meta key (data has been migrated out of here) */
					$meta_key = '_wc_twilio_sms_optin';
				}

				$enabled = '1' === $appointment->get_order()->get_meta( $meta_key );
			}
		}

		/**
		 * Allow actors to change the status of the notification.
		 *
		 * @since 4.4.0
		 *
		 * @param bool $enabled
		 * @param string $notification the notification type
		 */
		return (bool) apply_filters( 'wc_twilio_sms_appointments_notification_enabled', $enabled, $notification );
	}


	/**
	 * Checks if a notification is an admin-notification (and thus has recipients).
	 *
	 * @since 4.4.0
	 *
	 * @param string $notification notification key
	 * @return bool true if it is for admins
	 */
	public function is_admin_notification( $notification ) {
		$for_admins = in_array( $notification, array( 'admin_reminder', 'admin_cancellation' ), true );

		/**
		 * Filters whether the notification is for admins.
		 *
		 * @since 4.4.0
		 *
		 * @param bool if the notification has a schedule
		 * @param string notification key
		 */
		return (bool) apply_filters( 'wc_twilio_sms_appointments_is_admin_notification', $for_admins, $notification );
	}


	/** General helpers ******************************************************/


	/**
	 * When overriding a global appointment schedule option, determines if a valid selection is made
	 *
	 * @since 4.4.0
	 *
	 * @return bool true if the product override option is valid
	 */
	private function is_valid_product_override_option( $option ) {
		return ( 'global' === $option || 'override' === $option || 'disabled' === $option );
	}


	/**
	 * Returns a customer's mobile number country code based on their billing information.
	 *
	 * @since 4.4.0
	 *
	 * @param \WC_Appointment $appointment
	 * @return string the country code
	 */
	private function get_customer_country_code( \WC_Appointment $appointment ) {
		$order = $appointment->get_order();

		return $order ? $order->get_billing_country() : null;
	}


	/**
	 * Adds an admin message informing the user their notification schedule has been adjusted.
	 *
	 * @since 4.4.0
	 */
	private function add_restricted_schedule_message() {
		wc_twilio_sms()->get_message_handler()->add_info( __( 'Reminder SMS notifications can only be sent up to 48 hours before an event starts. Your schedule has been automatically adjusted.', 'woocommerce-appointments' ) );
	}

}

new WC_Appointments_Integration_Twilio_SMS();
