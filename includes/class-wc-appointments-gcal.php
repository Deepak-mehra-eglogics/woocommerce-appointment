<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Google Calendar Synchronization.
 */
class WC_Appointments_GCal {

	const TOKEN_TRANSIENT_TIME = 3500;

	const DAYS_OF_WEEK = array(
		1 => 'monday',
		2 => 'tuesday',
		3 => 'wednesday',
		4 => 'thursday',
		5 => 'friday',
		6 => 'saturday',
		7 => 'sunday',
	);

	/**
	 * Class ID set by default.
	 */
	public $id = 'gcal';

	/**
	 * oAuth URI set by default.
	 */
	public $oauth_uri = 'https://accounts.google.com/o/oauth2/';

	/**
	 * Calendars URI set by default.
	 */
	public $calendars_uri = 'https://www.googleapis.com/calendar/v3/calendars/';

	/**
	 * Calendars list set by default.
	 */
	public $calendars_list = 'https://www.googleapis.com/calendar/v3/users/me/calendarList';

	/**
	 * API scope set by default.
	 */
	public $api_scope = 'https://www.googleapis.com/auth/calendar';

	/**
	 * API Redirect URI.
	 */
	public $redirect_uri;

	/**
	 * Option Client ID.
	 */
	public $client_id;

	/**
	 * Option Client Secret.
	 */
	public $client_secret;

	/**
	 * Option Calendar ID.
	 */
	public $calendar_id;

	/**
	 * Option Debug.
	 *
	 * @var bool
	 */
	private $debug = false;

	/**
	 * Option Twoway.
	 *
	 * @var string
	 */
	private $twoway = 'one_way';

	/**
	 * If the service is currently is a syncing operation with google.
	 *
	 * @var bool
	 */
	protected $syncing = false;

	/**
	 * If the service is currently is a syncing operation with google.
	 *
	 * @var bool
	 */
	protected $staff_syncing = false;

	/**
	 * @var WC_Appointments_GCal The single instance of the class
	 */
	protected static $_instance = null;

	/**
	 * Main WC_Appointments_GCal Instance
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * User ID not set by default.
	 */
	private $user_id = null;

	/**
	 * Init and hook in the integration.
	 */
	public function __construct() {
		// API.
		$this->redirect_uri  = WC()->api_request_url( 'wc_appointments_oauth_redirect' );
		$this->client_id     = get_option( 'wc_appointments_gcal_client_id' );
		$this->client_secret = get_option( 'wc_appointments_gcal_client_secret' );
		$this->calendar_id   = get_option( 'wc_appointments_gcal_calendar_id' );
		$this->debug         = get_option( 'wc_appointments_gcal_debug' );
		$this->twoway        = get_option( 'wc_appointments_gcal_twoway' );

		// Oauth redirect.
		add_action( 'woocommerce_api_wc_appointments_oauth_redirect', array( $this, 'oauth_redirect' ) );
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );

		// Run the schedule and Sync from GCal.
		add_action( 'action_scheduler_after_process_queue', array( $this, 'sync_from_gcal' ) );

		// Appointment update actions.
		// Sync all statuses (except cancelled), but limit inside maybe_sync_to_gcal_from_status() function.
		$statuses = [
			'unpaid',
			'paid',
			'pending-confirmation',
			'confirmed',
			'complete',
			'in-cart',
		];
		foreach ( $statuses as $status ) {
			add_action( 'woocommerce_appointment_' . $status, array( $this, 'sync_unedited_appointment' ) );
		}

		// Remove from Gcal.
		add_action( 'woocommerce_appointment_cancelled', array( $this, 'sync_updated_appointment' ) );

		// Process edited appointment.
		add_action( 'woocommerce_appointment_process_meta', array( $this, 'sync_edited_appointment' ) );

		// Process edited appointment with staff changed.
		add_action( 'woocommerce_appointment_staff_changed', array( $this, 'maybe_sync_to_gcal_from_staff_change' ), 10, 4 );

		// Process rescheduled appointment.
		add_action( 'woocommerce_appointments_rescheduled_appointment', array( $this, 'sync_updated_appointment' ) );

		// Sync trashed/untrashed appointments.
		add_action( 'trashed_post', array( $this, 'sync_updated_appointment' ) );
		add_action( 'untrashed_post', array( $this, 'sync_updated_appointment' ) );

		// Sync availability to Gcal.
		add_action( 'woocommerce_before_appointments_availability_object_save', array( $this, 'sync_availability' ) ); #'woocommerce_before_' . $object_type . '_object_save'
		add_action( 'woocommerce_appointments_before_delete_appointment_availability', array( $this, 'delete_availability' ) );
	}

	/**
	 * Set redirect_uri option.
	 */
	public function set_redirect_uri( $option ) {
        $this->redirect_uri = $option;
    }

	/**
	 * Get redirect_uri option.
	 */
    public function get_redirect_uri() {
        return $this->redirect_uri;
    }

	/**
	 * Set client_id option.
	 */
	public function set_client_id( $option ) {
        $this->client_id = $option;
    }

	/**
	 * Get client_id option.
	 */
    public function get_client_id() {
        return $this->client_id;
    }

	/**
	 * Set client_secret option.
	 */
	public function set_client_secret( $option ) {
        $this->client_secret = $option;
    }

	/**
	 * Get client_secret option.
	 */
    public function get_client_secret() {
        return $this->client_secret;
    }

	/**
	 * Set calendar_id option.
	 */
	public function set_calendar_id( $option ) {
        $this->calendar_id = $option;
    }

	/**
	 * Get calendar_id option.
	 */
    public function get_calendar_id() {
        return $this->calendar_id;
    }

	/**
	 * Set user_id option.
	 */
	public function set_user_id( $option ) {
		$site_calendar_id  = get_option( 'wc_appointments_gcal_calendar_id' );
		$staff_calendar_id = get_user_meta( $option, 'wc_appointments_gcal_calendar_id', true );

		// When synced to same calendar, sync to main calendar only.
		if ( $staff_calendar_id === $site_calendar_id ) {
			$option = 0;
		}

		$this->user_id = $option;
		$calendar_id   = get_user_meta( $option, 'wc_appointments_gcal_calendar_id', true );
		$calendar_id   = $calendar_id ? $calendar_id : get_option( 'wc_appointments_gcal_calendar_id' );
		$two_way       = get_user_meta( $option, 'wc_appointments_gcal_twoway', true );
		$two_way       = $two_way ? $two_way : get_option( 'wc_appointments_gcal_twoway' );

		$this->set_calendar_id( $calendar_id );
		$this->set_twoway( $two_way );
    }

	/**
	 * Get user_id option.
	 */
    public function get_user_id() {
        return $this->user_id;
    }

	/**
	 * Set debug option.
	 */
	public function set_debug( $option ) {
        $this->debug = $option;
    }

	/**
	 * Get debug option.
	 */
    public function get_debug() {
        return $this->debug;
    }

	/**
	 * Set twoway option.
	 */
	public function set_twoway( $option ) {
        $this->twoway = $option;
    }

	/**
	 * Get twoway option.
	 */
    public function get_twoway() {
        return $this->twoway;
    }

	/**
	 * Get twoway option.
	 */
    public function is_twoway_enabled() {
		$twoway_enabled = ( 'two_way' !== $this->get_twoway() ) ? false : true;

		return apply_filters( 'woocommerce_appointments_gcal_sync_twoway_enabled', $twoway_enabled, $this );
    }

	/**
	 * Display admin screen notices.
	 *
	 * @return string
	 */
	public function admin_notices() {
		// Get current screen.
		$screen    = function_exists( 'get_current_screen' ) ? get_current_screen() : '';
		$screen_id = $screen ? $screen->id : '';

		$allowed_screens = array( 'user-edit', 'woocommerce_page_wc-settings' );

		if ( in_array( $screen_id, $allowed_screens ) && isset( $_GET['wc_gcal_oauth'] ) ) {
			if ( 'success' == $_GET['wc_gcal_oauth'] ) {
				echo '<div class="updated fade"><p><strong>' . __( 'Google Calendar', 'woocommerce-appointments' ) . '</strong> ' . __( 'Account connected successfully!', 'woocommerce-appointments' ) . '</p></div>';
			} else {
				echo '<div class="error fade"><p><strong>' . __( 'Google Calendar', 'woocommerce-appointments' ) . '</strong> ' . __( 'Failed to connect to your account, please try again, if the problem persists, turn on Debug Log option and see what is happening.', 'woocommerce-appointments' ) . '</p></div>';
			}
		}

		if ( in_array( $screen_id, $allowed_screens ) && isset( $_GET['wc_gcal_logout'] ) ) {
			if ( 'success' == $_GET['wc_gcal_logout'] ) {
				echo '<div class="updated fade"><p><strong>' . __( 'Google Calendar', 'woocommerce-appointments' ) . '</strong> ' . __( 'Account disconnected successfully!', 'woocommerce-appointments' ) . '</p></div>';
			} else {
				echo '<div class="error fade"><p><strong>' . __( 'Google Calendar', 'woocommerce-appointments' ) . '</strong> ' . __( 'Failed to disconnect to your account, please try again, if the problem persists, turn on Debug Log option and see what is happening.', 'woocommerce-appointments' ) . '</p></div>';
			}
		}
	}

	/**
	 * Get Access Token.
	 *
	 * @param  string $code Authorization code.
	 *
	 * @return string       Access token.
	 */
	public function get_access_token( $code = '', $user_id = '' ) {
		$user_id = $user_id ? $user_id : '';
		$user_id = $this->get_user_id() && 0 !== $this->get_user_id() ? $this->get_user_id() : $user_id;

		// Check roles if user is shop staff.
		if ( $user_id ) {
			$user_meta = get_userdata( $user_id );
			if ( isset( $user_meta->roles ) && ! in_array( 'shop_staff', (array) $user_meta->roles ) ) {
				return;
			}
		}

		// Get access token.
		if ( $user_id ) {
			$access_token = get_transient( 'wc_appointments_gcal_access_token_' . $user_id );
		} else {
			$access_token = get_transient( 'wc_appointments_gcal_access_token' );
		}

		// Get refresh token.
		if ( $user_id ) {
			$refresh_token = get_user_meta( $user_id, 'wc_appointments_gcal_refresh_token', true );
		} else {
			$refresh_token = get_option( 'wc_appointments_gcal_refresh_token' );
		}

		if ( ! $code && $refresh_token ) {
			$data = array(
				'client_id'     => $this->get_client_id(),
				'client_secret' => $this->get_client_secret(),
				'refresh_token' => $refresh_token,
				'grant_type'    => 'refresh_token',
				'access_type'   => 'offline',
			);

			$params = array(
				'body'      => http_build_query( $data ),
				'sslverify' => false,
				'timeout'   => 60,
				'headers'   => array(
					'Content-Type' => 'application/x-www-form-urlencoded',
				),
			);

			$response = wp_safe_remote_post( $this->oauth_uri . 'token', $params );

			if ( ! is_wp_error( $response ) && 200 == $response['response']['code'] && 'OK' == $response['response']['message'] ) {
				$response_data = json_decode( $response['body'] );
				$access_token  = sanitize_text_field( $response_data->access_token );

				// Set the transient.
				if ( $user_id ) {
					set_transient( 'wc_appointments_gcal_access_token_' . $user_id, $access_token, self::TOKEN_TRANSIENT_TIME );

					// Debug.
					if ( 'yes' === $this->get_debug() ) {
						#wc_add_appointment_log( $this->id, 'Google API Access Token for staff #' . $user_id . ' generated successfully: ' . $access_token ); #debug
					}
				} else {
					set_transient( 'wc_appointments_gcal_access_token', $access_token, self::TOKEN_TRANSIENT_TIME );

					// Debug.
					if ( 'yes' === $this->get_debug() ) {
						#wc_add_appointment_log( $this->id, 'Google API Access Token generated successfully: ' . $access_token ); #debug
					}
				}

				return $access_token;
			} else {

				// Debug.
				if ( 'yes' === $this->get_debug() ) {
					#wc_add_appointment_log( $this->id, 'Error while generating the Access Token: ' . var_export( $response['response'], true ) ); #debug
				}
			}
		} elseif ( '' !== $code ) {

			// Debug.
			if ( 'yes' === $this->get_debug() ) {
				#wc_add_appointment_log( $this->id, 'Renewing the Access Token...' ); #debug
			}

			$data = array(
				'code'          => $code,
				'client_id'     => $this->get_client_id(),
				'client_secret' => $this->get_client_secret(),
				'redirect_uri'  => $this->get_redirect_uri(),
				'grant_type'    => 'authorization_code',
				'access_type'   => 'offline',
			);

			$params = array(
				'body'      => http_build_query( $data ),
				'sslverify' => false,
				'timeout'   => 60,
				'headers'   => array(
					'Content-Type' => 'application/x-www-form-urlencoded',
				),
			);

			$response = wp_safe_remote_post( $this->oauth_uri . 'token', $params );

			if ( ! is_wp_error( $response ) && 200 == $response['response']['code'] && 'OK' == $response['response']['message'] ) {
				$response_data = json_decode( $response['body'] );
				$access_token  = sanitize_text_field( $response_data->access_token );

				// Add refresh token.
				if ( $user_id ) {
					update_user_meta( $user_id, 'wc_appointments_gcal_refresh_token', $response_data->refresh_token );
				} else {
					update_option( 'wc_appointments_gcal_refresh_token', $response_data->refresh_token );
				}

				// Set the transient.
				if ( $user_id ) {
					set_transient( 'wc_appointments_gcal_access_token_' . $user_id, $access_token, self::TOKEN_TRANSIENT_TIME );

					// Debug.
					if ( 'yes' === $this->get_debug() ) {
						#wc_add_appointment_log( $this->id, 'Google API Access Token for staff #' . $user_id . ' renewed successfully: ' . $access_token ); #debug
					}
				} else {
					set_transient( 'wc_appointments_gcal_access_token', $access_token, self::TOKEN_TRANSIENT_TIME );

					// Debug.
					if ( 'yes' === $this->get_debug() ) {
						#wc_add_appointment_log( $this->id, 'Google API Access Token renewed successfully: ' . $access_token ); #debug
					}
				}

				return $access_token;
			} else {

				// Debug.
				if ( 'yes' === $this->get_debug() ) {
					#wc_add_appointment_log( $this->id, 'Error while renewing the Access Token: ' . var_export( $response['response'], true ) ); #debug
				}
			}
		}

		// Debug.
		if ( 'yes' === $this->get_debug() ) {
			#wc_add_appointment_log( $this->id, 'Failed to retrieve and generate the Access Token. Code: ' . $code . ', User: ' . $user_id . ', Refresh token: ' . $refresh_token ); #debug
		}
	}

	/**
	 * OAuth Logout.
	 *
	 * @return bool
	 */
	protected function oauth_logout( $user_id = '' ) {
		$user_id = $user_id ? $user_id : '';
		$user_id = $this->get_user_id() ? $this->get_user_id() : $user_id;

		// Debug.
		if ( 'yes' === $this->get_debug() ) {
			wc_add_appointment_log( $this->id, 'Disconnecting from the Google Calendar app...' ); #debug
		}

		// Get the refresh token.
		$refresh_token = $user_id ? get_user_meta( $user_id, 'wc_appointments_gcal_refresh_token', true ) : get_option( 'wc_appointments_gcal_refresh_token' );

		if ( $refresh_token ) {
			$params = array(
				'sslverify' => false,
				'timeout'   => 60,
				'headers'   => array(
					'Content-Type' => 'application/x-www-form-urlencoded',
				),
			);

			$response = wp_remote_get( $this->oauth_uri . 'revoke?token=' . $refresh_token, $params );

			if ( ! is_wp_error( $response ) && 200 == $response['response']['code'] && 'OK' == $response['response']['message'] ) {
				// Delete tokens.
				if ( $user_id ) {
					delete_user_meta( $user_id, 'wc_appointments_gcal_refresh_token' );
					delete_transient( 'wc_appointments_gcal_access_token_' . $user_id );
				} else {
					delete_option( 'wc_appointments_gcal_refresh_token' );
					delete_transient( 'wc_appointments_gcal_access_token' );
				}

				// Debug.
				if ( 'yes' === $this->get_debug() ) {
					wc_add_appointment_log( $this->id, 'Successfully disconnected from the Google Calendar app' ); #debug
				}

				return true;
			} else {

				// Debug.
				if ( 'yes' === $this->get_debug() ) {
					wc_add_appointment_log(
						$this->id,
						'Error while disconnecting from the Google Calendar app: ' . var_export( $response['response'], true )
					); #debug
				}
			}
		}

		// Debug.
		if ( 'yes' === $this->get_debug() ) {
			wc_add_appointment_log( $this->id, 'Failed to disconnect from the Google Calendar app' ); #debug
		}
	}

	/**
	 * Process the oauth redirect.
	 *
	 * @return void
	 */
	public function oauth_redirect() {
		if ( ! current_user_can( 'manage_appointments' ) ) {
			wp_die( __( 'Permission denied!', 'woocommerce-appointments' ) );
		}

		// User ID passed.
		if ( isset( $_GET['state'] ) ) {

			$user_id       = absint( $_GET['state'] );
			$admin_url     = admin_url( 'user-edit.php' );
			$redirect_args = array(
				'user_id' => $_GET['state'],
			);

		} else {

			$user_id       = '';
			$admin_url     = admin_url( 'admin.php' );
			$redirect_args = array(
				'page'    => 'wc-settings',
				'tab'     => 'appointments',
				'section' => $this->id,
			);

		}

		// OAuth.
		if ( isset( $_GET['code'] ) ) {
			$code         = sanitize_text_field( $_GET['code'] );
			$access_token = $this->get_access_token( $code, $user_id );

			if ( ! $access_token ) {
				$redirect_args['wc_gcal_oauth'] = 'fail';

				wp_safe_redirect( add_query_arg( $redirect_args, $admin_url ), 301 );
				exit;
			} else {
				$redirect_args['wc_gcal_oauth'] = 'success';

				wp_safe_redirect( add_query_arg( $redirect_args, $admin_url ), 301 );
				exit;
			}
		}

		// Error.
		if ( isset( $_GET['error'] ) ) {
			$redirect_args['wc_gcal_oauth'] = 'fail';

			wp_safe_redirect( add_query_arg( $redirect_args, $admin_url ), 301 );
			exit;
		}

		// Logout.
		if ( isset( $_GET['logout'] ) ) {
			$logout                          = $this->oauth_logout( $user_id );
			$redirect_args['wc_gcal_logout'] = ( isset( $logout ) && $logout ) ? 'success' : 'fail';

			wp_safe_redirect( add_query_arg( $redirect_args, $admin_url ), 301 );
			exit;
		}

		wp_die( __( 'Invalid request!', 'woocommerce-appointments' ) );
	}

	/**
	 * Get user calendars.
	 *
	 * @return array Calendar list
	 */
	public function get_calendars() {
		// Get all Google Calendars.
		$google_calendars = [];

		// Check if Authorized.
		$access_token = $this->get_access_token();
		if ( ! $access_token ) {
			return;
		}

		// Connection params.
		$params = array(
			'method'    => 'GET',
			'sslverify' => false,
			'timeout'   => 60,
			'headers'   => array(
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $access_token,
			),
		);

		$response = wp_safe_remote_post( $this->calendars_list, $params );

		if ( ! is_wp_error( $response ) && 200 == $response['response']['code'] && 'OK' == $response['response']['message'] ) {
			// Get response data.
			$response_data = json_decode( $response['body'], true );

			// List calendars.
			if ( is_array( $response_data['items'] ) && ! empty( $response_data['items'] ) ) {
				foreach ( $response_data['items'] as $data ) {
					$google_calendars[ $data['id'] ] = $data['summary'];
				}
			}
		}

		return $google_calendars;
	}

	/**
	 * Check if Google Calendar settings are supplied.
	 *
	 * @return bool True is calendar is set, false otherwise.
	 */
	public function is_calendar_set() {
		$client_id     = $this->get_client_id();
		$client_secret = $this->get_client_secret();
		$calendar_id   = $this->get_calendar_id();

		return ! empty( $client_id ) && ! empty( $client_secret ) && ! empty( $calendar_id );
	}

	/**
	 * Makes an http request to the Google Calendar API.
	 *
	 * @param  string $api_url API Url to make the request against
	 * @param  array  $params  Array of parameters that will be used when making the request
	 * @version       3.5.6
	 * @since         3.5.6
	 * @return object Response object from the request
	 */
	protected function make_gcal_request( $api_url, $params = [], $staff_id = '' ) {
		if ( ! isset( $api_url ) ) {
			return;
		}

		// Check if Authorized.
		$access_token = $this->get_access_token( '', $staff_id );
		if ( ! $access_token ) {
			return;
		}

		// Connection params.
		$params['method']    = isset( $params['method'] ) ? strtoupper( $params['method'] ) : 'GET';
		$params['sslverify'] = false;
		$params['timeout']   = 60;
		$params['headers']   = array(
			'Content-Type'  => 'application/json',
			'Authorization' => 'Bearer ' . $access_token,
		);

		// No real use for now.
		if ( isset( $params['querystring'] ) && is_array( $params['querystring'] ) ) {
			$api_url .= '?' . wp_json_encode( http_build_query( $params['querystring'] ), JSON_UNESCAPED_SLASHES );
		}

		if ( in_array( $params['method'], array( 'GET', 'DELETE' ) ) ) {
			unset( $params['body'] );
		}

		// Filter the gCal request.
		$params = apply_filters( 'woocommerce_appointments_gcal_sync_parameters', $params, $api_url, $staff_id );

		$response = wp_safe_remote_request( $api_url, $params );

		// 200 = ok
		// 204 = deleted
		if ( ! is_wp_error( $response ) && 'OK' == $response['response']['message']
			&& in_array( $response['response']['code'], array( 200, 204 ) )
		) {
			// Debug.
			if ( 'yes' === $this->get_debug() ) {
				#wc_add_appointment_log( $this->id, 'Successful Google Calendar request for ' . $api_url . ': ' . var_export( $response, true ) ); #debug
			}
		} elseif ( 410 === $response['response']['code'] ) {
			// Debug.
			if ( 'yes' === $this->get_debug() ) {
				wc_add_appointment_log( $this->id, 'Attempting to delete event that does not exist any more' ); #debug
			}

		// Debug.
		} elseif ( 'yes' === $this->get_debug() ) {
			wc_add_appointment_log( $this->id, 'Error while making Google Calendar request for ' . $api_url . ': ' . var_export( $response, true ) ); #debug
		}

		return $response;
	}

	/**
	 * Is edited from post.php's meta box.
	 *
	 * @return bool
	 */
	public function is_edited_from_meta_box() {
		return (
			! empty( $_POST['wc_appointments_details_meta_box_nonce'] )
			&&
			wp_verify_nonce( $_POST['wc_appointments_details_meta_box_nonce'], 'wc_appointments_details_meta_box' )
		);
	}

	/**
	 * Sync Appointment with GCal when appointment is edited/updated/saved manually.
	 *
	 * @param  int $appointment_id Appointment ID
	 * @return void
	 */
	public function sync_edited_appointment( $appointment_id ) {
		if ( ! $this->is_edited_from_meta_box() ) {
			return;
		}

		$this->maybe_sync_to_gcal_from_status( $appointment_id );
	}

	/**
	 * Sync Appointment with GCal when appointment is NOT edited/updated/saved manually.
	 * New appoiintments should also sync this way.
	 *
	 * @param  int $appointment_id Appointment ID
	 *
	 * @return void
	 */
	public function sync_unedited_appointment( $appointment_id ) {
		if ( $this->is_edited_from_meta_box() ) {
			return;
		}

		$this->maybe_sync_to_gcal_from_status( $appointment_id );
	}

	/**
	 * Sync Appointment with GCal when appointment changes status or anything else.
	 *
	 * @param  int $appointment_id Appointment ID
	 *
	 * @return void
	 */
	public function sync_updated_appointment( $appointment_id ) {
		$this->maybe_sync_to_gcal_from_status( $appointment_id );
	}

	/**
	 * Maybe remove / sync appointment based on appointment status.
	 *
	 * @param int $appointment_id Appointment ID
	 * @return void
	 */
	public function maybe_sync_to_gcal_from_status( $appointment_id ) {
		global $wpdb;

		// Check if Authorized.
		$access_token = $this->get_access_token();
		if ( ! $access_token ) {
			return;
		}

		$status = $wpdb->get_var( $wpdb->prepare( "SELECT post_status FROM $wpdb->posts WHERE post_type = 'wc_appointment' AND ID = %d", $appointment_id ) );

		if ( in_array( $status, [ 'cancelled', 'trash' ] ) ) {
			$this->remove_from_gcal( $appointment_id, false, false, true ); #true - sync other staff as well.
		} elseif ( in_array( $status, apply_filters( 'woocommerce_appointments_gcal_sync_statuses', [ 'confirmed', 'paid', 'complete' ] ) ) ) {
			$this->sync_to_gcal( $appointment_id, false, false, true ); #true - sync other staff as well.
		} elseif ( 'unpaid' === $status ) { #Sync Cash on Delivery appointments.
			$order_id = WC_Appointment_Data_Store::get_appointment_order_id( $appointment_id );
			$order    = wc_get_order( $order_id );
			if ( is_a( $order, 'WC_Order' ) ) {
				if ( 'cod' === $order->get_payment_method() ) {
					$this->sync_to_gcal( $appointment_id, false, false, true ); #true - sync other staff as well.
				}
			}
		}
	}

	/**
	 * Maybe remove / sync appointment based on appointment staff change.
	 *
	 * @param int $appointment_id Appointment ID
	 * @return void
	 */
	public function maybe_sync_to_gcal_from_staff_change( $from, $to, $appointment_id, $appointment ) {
		// Only work with staff changes, when appointments are edited manually
		// and are already existing to avoid infinite loops with calls to gcal.
		if ( ! $this->is_edited_from_meta_box() ) {
			return;
		}

		// Check if Authorized.
		$access_token = $this->get_access_token();

		// Debug.
		if ( 'yes' === $this->get_debug() ) {
			#wc_add_appointment_log( $this->id, 'Manage token checks: ' . var_export( $access_token, true ) );
		}
		if ( ! $access_token ) {
			return;
		}

		// Check if appointment exists.
		$appointment = get_wc_appointment( $appointment_id );
		if ( ! $appointment ) {
			return;
		}

		$this->staff_syncing = true;

		// Debug.
		if ( 'yes' === $this->get_debug() ) {
			#wc_add_appointment_log( $this->id, 'Manage staff for appointment #' . $appointment_id );
		}

		// Get staff that was removed.
		#$to = [4, 5, 6, 7, 8];
		#$from = [1, 2, 3, 4, 5];
		$removed_staff_ids = array_diff( $from, $to );

		// Debug.
		if ( 'yes' === $this->get_debug() ) {
			#wc_add_appointment_log( $this->id, 'Removed staff IDs :' . var_export( $removed_staff_ids, true ) );
		}
		if ( $removed_staff_ids ) {
			foreach ( $removed_staff_ids as $removed_staff_id ) {
				$calendar_id       = get_user_meta( $removed_staff_id, 'wc_appointments_gcal_calendar_id', true );
				$removed_staff_calendar_id = $calendar_id ? $calendar_id : '';

				// Staff must have calendar ID set.
				if ( $removed_staff_calendar_id ) {
					// Switch calendar to staff for new sync.
					$this->set_calendar_id( $removed_staff_calendar_id );
					$this->set_user_id( $removed_staff_id ); #reset to staff calendar sync.

					// Debug.
					if ( 'yes' === $this->get_debug() ) {
						#wc_add_appointment_log( $this->id, 'Removing appointment #' . $appointment_id . ' for staff #' . $removed_staff_id . ' from Google Calendar: ' . $removed_staff_calendar_id );
					}

					// Remove event from removed staff.
					$this->remove_from_gcal( $appointment_id, $removed_staff_id, $removed_staff_calendar_id, false );
				}
			}

			// Reset to global calendar sync.
			$site_calendar_id  = get_option( 'wc_appointments_gcal_calendar_id' );
			$this->set_calendar_id( $site_calendar_id );
			$this->set_user_id( 0 );

			// Debug.
			if ( 'yes' === $this->get_debug() ) {
				#wc_add_appointment_log( $this->id, 'Sync reset to global' );
			}
		}

		// Get staff that was added.
		#$from = [1, 2, 3, 4, 5];
		#$to = [4, 5, 6, 7, 8];
		$added_staff_ids = array_diff( $to, $from );

		// Debug.
		if ( 'yes' === $this->get_debug() ) {
			#wc_add_appointment_log( $this->id, 'Added staff IDs :' . var_export( $added_staff_ids, true ) );
		}
		if ( $added_staff_ids ) {
			foreach ( $added_staff_ids as $added_staff_id ) {
				$calendar_id             = get_user_meta( $added_staff_id, 'wc_appointments_gcal_calendar_id', true );
				$added_staff_calendar_id = $calendar_id ? $calendar_id : '';

				// Staff must have calendar ID set.
				if ( $added_staff_calendar_id ) {
					// Switch calendar to staff for new sync.
					$this->set_calendar_id( $added_staff_calendar_id );
					$this->set_user_id( $added_staff_id ); #reset to staff calendar sync.

					// Debug.
					if ( 'yes' === $this->get_debug() ) {
						#wc_add_appointment_log( $this->id, 'Adding appointment #' . $appointment_id . ' for staff #' . $added_staff_id . 'to Google Calendar: ' . $added_staff_calendar_id );
					}

					// Sync event to added staff.
					$this->sync_to_gcal( $appointment_id, $added_staff_id, $added_staff_calendar_id, false ); #false - do not sync other staff.
				}
			}

			// Reset to global calendar sync.
			$site_calendar_id  = get_option( 'wc_appointments_gcal_calendar_id' );
			$this->set_calendar_id( $site_calendar_id );
			$this->set_user_id( 0 );

			// Debug.
			if ( 'yes' === $this->get_debug() ) {
				#wc_add_appointment_log( $this->id, 'Sync reset to global' );
			}
		}

		$this->staff_syncing = false;
	}

	/**
	 * Sync an event resource with Google Calendar.
	 * https://developers.google.com/google-apps/calendar/v3/reference/events
	 *
	 * @param   int            $appointment_id Appointment ID
	 * @param   array          $params Set of parameters to be passed to the http request
	 * @param   array          $data Optional set of data for writeable syncs
	 * @since                  3.5.6
	 * @version                3.5.6
	 * @return  object|boolean Parsed JSON data from the http request or false if error
	 */
	public function sync_event_resource( $appointment_id = -1, $params = [], $resource_params = [], $data = [] ) {
		if ( $appointment_id < 0 ) {
			return;
		}

		$appointment = get_wc_appointment( $appointment_id );
		$event_id    = $resource_params['event_id'];
		$staff_id    = $resource_params['staff_id'];
		$calendar_id = $resource_params['calendar_id'];
		$api_url     = $this->calendars_uri . $calendar_id . '/events' . ( ( $event_id ) ? '/' . $event_id : '' );
		$json_data   = false;

		if ( isset( $params['method'] ) && 'GET' !== $params['method'] ) {
			$params['body'] = wp_json_encode( apply_filters( 'woocommerce_appointments_gcal_sync', $data, $appointment ) );
		}

		try {

			$response  = $this->make_gcal_request( $api_url, $params, $staff_id );
			$json_data = json_decode( $response['body'], true );

			// Debug.
			if ( 'yes' === $this->get_debug() ) {
				wc_add_appointment_log( $this->id, 'Synced appointment #' . $appointment->get_id() . ' with Google Calendar: ' . $calendar_id ); #debug
			}

		} catch ( Exception $e ) {
			$json_data = false;

			// Debug.
			if ( 'yes' === $this->get_debug() ) {
				wc_add_appointment_log( $this->id, 'Error while getting data for ' . $api_url . ': ' . print_r( $response, true ) ); #debug
			}
		}

		return $json_data;

	}

	/**
	 * Sync Appointment to GCal
	 *
	 * @param  int $appointment_id Appointment ID
	 * @return void
	 */
	public function sync_to_gcal( $appointment_id, $appointment_staff_id = false, $staff_calendar_id = false, $staff_sync = false ) {
		if ( $this->syncing ) {
			// Event is coming from google don't send it back.
			return;
		}

		if ( 'wc_appointment' !== get_post_type( $appointment_id ) ) {
			return;
		}

		/**
		 * woocommerce_appointments_sync_to_gcal_start hook
		 */
		do_action( 'woocommerce_appointments_sync_to_gcal_start', $appointment_id, $appointment_staff_id );

		// Get appointment object.
		$appointment = get_wc_appointment( $appointment_id );

		// Staff calendar should be different from main calendar.
		if ( $appointment_staff_id ) {
			$staff_event_ids = $appointment->get_google_calendar_staff_event_ids();
			$event_id        = isset( $staff_event_ids[ $appointment_staff_id ] ) ? $staff_event_ids[ $appointment_staff_id ] : '';

			// Testing.
			#wc_add_appointment_log( $this->id, 'Staff #' . $appointment_staff_id . ' Event IDs: ' . var_export( $staff_event_ids, true ) );
		} else {
			$event_id = $appointment->get_google_calendar_event_id();

			// Testing.
			#wc_add_appointment_log( $this->id, 'Site Event ID #' . $event_id );
		}

		// Testing.
		#wc_add_appointment_log( $this->id, 'Event ID #' . $event_id );

		$product    = $appointment->get_product();
		$product_id = $appointment->get_product_id();
		$order      = $appointment->get_order();
		$customer   = $appointment->get_customer();
		$timezone   = wc_timezone_string();
		/* translators: %s: appointment ID */
		$summary                     = sprintf( __( 'Appointment #%d', 'woocommerce-appointments' ), $appointment_id ) . ( $product ? ' - ' . html_entity_decode( $product->get_title() ) : '' );
		$description                 = '';
		$description_does_exist      = false;
		$description_has_been_edited = false;

		// Add customer name.
		if ( $customer ) {
			$description .= sprintf( '%s: %s', __( 'Customer', 'woocommerce-appointments' ), $customer->full_name ) . PHP_EOL;
		}

		// Product name.
		if ( is_object( $product ) ) {
			$description .= sprintf( '%s: %s', __( 'Product', 'woocommerce-appointments' ), $product->get_title() ) . PHP_EOL;
		}

		// Appointment data.
		$appointment_data = array(
			__( 'Appointment ID', 'woocommerce-appointments' ) => $appointment_id,
			__( 'When', 'woocommerce-appointments' )      => $appointment->get_start_date(),
			__( 'Duration', 'woocommerce-appointments' )  => $appointment->get_duration(),
			__( 'Providers', 'woocommerce-appointments' ) => $appointment->get_staff_members( true ),
		);

		foreach ( $appointment_data as $key => $value ) {
			if ( empty( $value ) ) {
				continue;
			}

			$description .= sprintf( '%1$s: %2$s', rawurldecode( html_entity_decode( $key ) ), rawurldecode( html_entity_decode( $value ) ) ) . PHP_EOL;
		}

		// Addons and other order items.
		if ( is_a( $order, 'WC_Order' ) ) {
			foreach ( $order->get_items() as $order_item_id => $order_item ) {
				if ( WC_Appointment_Data_Store::get_appointment_order_item_id( $appointment_id ) !== $order_item_id ) {
					continue;
				}

				// Add meta data to events from order.
				foreach ( $order_item->get_all_formatted_meta_data() as $meta_id => $meta ) {
					// Test.
					#wc_add_appointment_log( $this->id, 'item meta data: ' . var_export(  $display_key . ' - ' . $display_value, true ) );

					$description .= apply_filters( 'woocommerce_appointments_gcal_sync_order_meta', sprintf( '%s: %s', rawurldecode( html_entity_decode( $meta->key ) ), rawurldecode( html_entity_decode( $meta->value ) ) ), $the_meta_data, $order ) . PHP_EOL;
				}
			}
		}

		// Resource params.
		$resource_params = array(
			'event_id'    => $event_id,
			'staff_id'    => $appointment_staff_id,
			'calendar_id' => ( $appointment_staff_id && $staff_calendar_id ? $staff_calendar_id : $this->get_calendar_id() ),
		);

		// Testing.
		#wc_add_appointment_log( $this->id, 'Staff ID present?: ' . var_export( $appointment_staff_id, true ) );
		#wc_add_appointment_log( $this->id, 'Staff calendar ID present?: ' . var_export( $staff_calendar_id, true ) );
		#wc_add_appointment_log( $this->id, 'Global get_calendar_id(): ' . var_export( $this->get_calendar_id(), true ) );
		#wc_add_appointment_log( $this->id, 'Resource params: ' . var_export( $resource_params, true ) );

		// Update event.
		if ( $event_id ) {
			$response_data = $this->sync_event_resource(
				$appointment_id,
				array(
					'method'      => 'GET',
					'querystring' => array(
						'fields' => 'summary, description',
					),
				),
				$resource_params
			);

			/*
			// Filter description to add option to preserve the event's original data if set so.

			// Bellow are parameters that can be used in the filter:
			$description_does_exist      = isset( $response_data['description'] ) && ( '' !== trim( $response_data['description'] ) );
			$description_has_been_edited = isset( $response_data['description'] ) && $response_data['description'] !== $description;

			// If the user edited the description on the Google Calendar side we want to keep that data intact.
			if ( $description_does_exist && $description_has_been_edited ) {
				$description = $response_data['description'];
			}
			*/
			$description = apply_filters(
				'woocommerce_appointments_gcal_sync_updated_description',
				$description,
				$response_data,
				$appointment
			);

			$summary_does_exist      = isset( $response_data['summary'] ) && ( '' !== trim( $response_data['summary'] ) );
			$summary_has_been_edited = isset( $response_data['summary'] ) && $response_data['summary'] !== $summary;

			// If the user edited the summary (event title) on the Google Calendar side we want to keep that data intact.
			if ( $summary_does_exist && $summary_has_been_edited ) {
				$summary = $response_data['summary'];
			}
		}

		// Set the event data.
		$data = array(
			'summary'     => wp_kses_post( $summary ),
			'description' => wp_kses_post( $description ),
		);

		// Pass appointment ID.
		$data['extendedProperties'] = array(
			'shared' => array(
				'appointment_id' => $appointment_id,
			),
		);

		// Set the event start and end dates.
		if ( $appointment->is_all_day() ) {
			$data['end'] = array(
				'date' => date( 'Y-m-d', ( $appointment->get_end() + 1440 ) ),
			);

			$data['start'] = array(
				'date' => date( 'Y-m-d', $appointment->get_start() ),
			);
		} else {
			$data['end'] = array(
				'dateTime' => date( 'Y-m-d\TH:i:s', $appointment->get_end() ),
				'timeZone' => $timezone,
			);

			$data['start'] = array(
				'dateTime' => date( 'Y-m-d\TH:i:s', $appointment->get_start() ),
				'timeZone' => $timezone,
			);
		}

		$response_data = $this->sync_event_resource(
			$appointment_id,
			array(
				'method' => $event_id ? 'PUT' : 'POST',
			),
			$resource_params,
			$data
		);

		// Testing.
		#wc_add_appointment_log( $this->id, 'New Event ID #' . $response_data['id'] );

		// Save event ID only when available.
		if ( isset( $response_data['id'] ) ) {
			if ( $appointment_staff_id ) {

				// Testing.
				#wc_add_appointment_log( $this->id, 'New Event set for staff #' . $appointment_staff_id );
				$appointment->set_google_calendar_staff_event_ids( array( $appointment_staff_id => $response_data['id'] ) );
			} else {
				$appointment->set_google_calendar_event_id( wc_clean( $response_data['id'] ) );
			}
		}

		// Save appointment also calls $appointment->status_transition() in which
		// infinite loop could happens.
		$appointment->skip_status_transition_events();
		$appointment->save();

		// Get staff IDs.
		$staff_ids = $appointment->get_staff_ids();

		// Testing.
		#wc_add_appointment_log( $this->id, 'Staff ID present?: ' . var_export( $appointment_staff_id, true ) );
		#wc_add_appointment_log( $this->id, 'Staff sync enabled?: ' . var_export( $staff_sync, true ) );

		// Sync for each staff.
		// Only when $appointment_staff_id is false,
		// so it does not go into inifinite loop.
		if ( $staff_ids && ! $appointment_staff_id && $staff_sync ) {
			foreach ( $staff_ids as $staff_id ) {
				$calendar_id       = get_user_meta( $staff_id, 'wc_appointments_gcal_calendar_id', true );
				$staff_calendar_id = $calendar_id ? $calendar_id : '';
				// Staff must have calendar ID set.
				if ( $staff_calendar_id ) {
					$this->sync_to_gcal( $appointment_id, $staff_id, $staff_calendar_id, false ); #false - do not sync other staff.
				}
			}

			/*
			// Don't delete event ID's from removed staff
			// in case you add it back in future.
			if ( ! $count_staff ) {
				$appointment->set_google_calendar_staff_event_ids('');
				$appointment->save();
			}
			*/
		}
	}

	/**
	 * Remove/cancel the appointment in GCal
	 *
	 * @param  int $appointment_id Appointment ID
	 * @param  int $appointment_staff_id Staff ID
	 * @param  string $staff_calendar_id Staff synced calendar ID
	 * @param  void $staff_sync Should we sync all staff?
	 *
	 * @return void
	 */
	public function remove_from_gcal( $appointment_id, $appointment_staff_id = false, $staff_calendar_id = false, $staff_sync = false ) {
		if ( $this->syncing ) {
			// Event is coming from google don't send it back.
			return;
		}

		// Stop the removal with a filter.
		$stop_the_removal = apply_filters(
			'woocommerce_appointments_gcal_remove_from_gcal',
			false,
			$appointment_id,
			$appointment_staff_id,
			$staff_calendar_id,
			$this
		);
		if ( $stop_the_removal ) {
			return;
		}

		// Check if appointment exists.
		$appointment = get_wc_appointment( $appointment_id );
		if ( ! $appointment ) {
			return;
		}

		// Staff calendar should be different from main calendar.
		if ( $appointment_staff_id ) {
			$staff_event_ids = $appointment->get_google_calendar_staff_event_ids();
			$event_id        = isset( $staff_event_ids[ $appointment_staff_id ] ) ? $staff_event_ids[ $appointment_staff_id ] : '';
		} else {
			$event_id = $appointment->get_google_calendar_event_id();
		}

		// Stop removal, when no event is synced.
		if ( ! $event_id ) {
			return;
		}

		// Check if Authorized.
		$access_token = $this->get_access_token( '', $appointment_staff_id );
		if ( ! $access_token ) {
			return;
		}

		// Calendar ID.
		$calendar_id = $staff_calendar_id ? $staff_calendar_id : $this->get_calendar_id();

		// Stop here if calendar is not set.
		if ( ! $calendar_id ) {
			return;
		}

		// Remove event.
		if ( $event_id ) {
			$api_url = $this->calendars_uri . $calendar_id . '/events/' . $event_id;

			// Connection params.
			$params = array(
				'method'    => 'DELETE',
				'sslverify' => false,
				'timeout'   => 60,
				'headers'   => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $access_token,
				),
			);

			// Debug.
			if ( 'yes' === $this->get_debug() ) {
				#wc_add_appointment_log( $this->id, 'Removing event #' . $event_id . ' from Google Calendar: ' . $calendar_id );
			}

			$response = wp_safe_remote_post( $api_url, $params );

			if ( ! is_wp_error( $response ) && 204 == $response['response']['code'] ) {

				// Debug.
				if ( 'yes' === $this->get_debug() ) {
					#wc_add_appointment_log( $this->id, 'Event #' . $event_id . ' removed successfully!' );
				}

			// Debug.
			} elseif ( 'yes' === $this->get_debug() ) {
				wc_add_appointment_log( $this->id, 'Error while removing event #' . $event_id . ': from Google Calendar: ' . $calendar_id . ' : ' . var_export( $response['body'], true ) );
			}

			// Get staff IDs.
			$staff_ids = $appointment->get_staff_ids();

			// Sync for each staff.
			// Only when $appointment_staff_id is false,
			// so it does not go into inifinite loop.
			if ( $staff_ids && ! $appointment_staff_id && $staff_sync ) {
				foreach ( $staff_ids as $staff_id ) {
					$calendar_id       = get_user_meta( $staff_id, 'wc_appointments_gcal_calendar_id', true );
					$staff_calendar_id = $calendar_id ? $calendar_id : '';
					// Staff must have calendar ID set.
					if ( $staff_calendar_id ) {
						$this->remove_from_gcal( $appointment_id, $staff_id, $staff_calendar_id, true );
					}
				}
			}
		}
	}

	public function get_synced_staff_ids() {
		// Get all users set as staff.
		$all_staff = get_users(
			array(
				'role'    => 'shop_staff',
				'orderby' => 'nicename',
				'order'   => 'asc',
				'fields'  => array( 'ID' ),
			)
		);

		if ( $all_staff ) {
			$synced_ids = [];
			foreach ( $all_staff as $provider ) {
				if ( ! isset( $provider->ID ) ) {
					continue;
				}
				$two_way     = get_user_meta( $provider->ID, 'wc_appointments_gcal_twoway', true );
				$calendar_id = get_user_meta( $provider->ID, 'wc_appointments_gcal_calendar_id', true );

				if ( 'two_way' === $two_way && $calendar_id ) {
					$synced_ids[] = absint( $provider->ID );
				}
			}

			// Array of staff with sync enabled.
			if ( ! empty( $synced_ids ) ) {
				return $synced_ids;
			}
		}

		return;
	}

	public function get_sync_token() {
		if ( $this->get_user_id() ) {
			$sync_token = rawurlencode( get_transient( 'wc_appointments_gcal_sync_token' . $this->get_user_id() ) ); #get
		} else {
			$sync_token = rawurlencode( get_transient( 'wc_appointments_gcal_sync_token' ) ); #get
		}

		return $sync_token;
	}

	public function set_sync_token( $sync_token = 0 ) {
		if ( $this->get_user_id() ) {
			if ( $sync_token ) {
				set_transient( 'wc_appointments_gcal_sync_token' . $this->get_user_id(), $sync_token, self::TOKEN_TRANSIENT_TIME ); #update
			} else {
				delete_transient( 'wc_appointments_gcal_sync_token' . $this->get_user_id() ); #delete
			}
		} else {
			if ( $sync_token ) {
				set_transient( 'wc_appointments_gcal_sync_token', $sync_token, self::TOKEN_TRANSIENT_TIME ); #update
			} else {
				delete_transient( 'wc_appointments_gcal_sync_token' ); #delete
			}
		}
	}

	/**
	 * Sync back events from GCal.
	 *
	 * @return void
	 */
	public function sync_from_gcal( $user_id = '' ) {
		// Get all staff with sync enabled.
		$synced_staff = $this->get_synced_staff_ids();
		if ( $synced_staff && ! $user_id ) {
			foreach ( $synced_staff as $synced_staff_id ) {
				$this->sync_from_gcal( $synced_staff_id );
			}
		}

		// Set user id, calendar and 2-way sync.
		if ( $user_id ) {
			$this->set_user_id( $user_id );
		} else {
			$this->set_user_id( 0 ); #reset to global calendar sync.
		}

		#error_log( var_export( $user_id, true ) );

		// Two way sync not enabled.
		if ( ! $this->is_twoway_enabled() ) {
			return;
		}

		// Check if Authorized and if calendar is set.
		$access_token    = $this->get_access_token();
		$is_calendar_set = $this->is_calendar_set();
		if ( ! $access_token || ! $is_calendar_set ) {
			return;
		}

		// Connection params.
		$params = array(
			'method'    => 'GET',
			'sslverify' => false,
			'timeout'   => 60,
			'headers'   => array(
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $access_token,
			),
		);

		// Don't sync events older than now.
		$timeMin = new DateTime();
		$timeMin->setTimezone( new DateTimeZone( wc_timezone_string() ) );
		$timeMin = $timeMin->format( 'Y-m-d\TH:i:sP' ); #\DateTime::RFC3339
		$timeMin = rawurlencode( $timeMin );

		// Don't sync events more than 1 year in future.
		$timeMax = new DateTime();
		$timeMax->setTimezone( new DateTimeZone( wc_timezone_string() ) );
		$timeMax->modify( '+1 year' );
		$timeMax = $timeMax->format( 'Y-m-d\TH:i:sP' ); #\DateTime::RFC3339
		$timeMax = rawurlencode( $timeMax );

		// Time zone.
		$timeZone = wc_timezone_string();

		// Get sync token.
		$sync_token = $this->get_sync_token();
		#$sync_token = false;

		// maxResults 1000, 250 by default.
		if ( $this->get_sync_token() ) { #updated events only
			$response = wp_safe_remote_post( $this->calendars_uri . $this->get_calendar_id() . '/events' . "?singleEvents=false&showDeleted=true&syncToken=$sync_token&timeZone=$timeZone", $params );
		} else { #full sync
			$response = wp_safe_remote_post( $this->calendars_uri . $this->get_calendar_id() . '/events' . "?singleEvents=false&showDeleted=true&maxResults=1000&timeMin=$timeMin&timeMax=$timeMax&timeZone=$timeZone", $params );
		}

		// If the syncToken expires, the server will respond with a 410 GONE response code.
		// Perform a full synchronization without any syncToken.
		if ( ! is_wp_error( $response ) && 410 == $response['response']['code'] ) {
			// Delete sync token.
			$this->set_sync_token( 0 );

			// Perform a full synchronization without any syncToken.
			$response = wp_safe_remote_post( $this->calendars_uri . $this->get_calendar_id() . '/events' . "?singleEvents=false&showDeleted=true&maxResults=1000&timeMin=$timeMin&timeMax=$timeMax&timeZone=$timeZone", $params );
		}

		// Stop here when error spotted.
		if ( is_wp_error( $response ) && isset( $response['body'] ) ) {

			// Debug.
			if ( 'yes' === $this->get_debug() ) {
				wc_add_appointment_log( $this->id, 'Error while performing sync from Google Calendar: ' . $this->get_calendar_id() . ': ' . var_export( $response['body'], true ) );
			}
			return;
		}

		// Fetch the events.
		$this->gcal_fetch_events( $response );
	}

	/**
	 * Fetch the events and generate gcal availability.
	 *
	 * @param  array  $global_availability   Availability rules.
	 * @return void
	 */
	public function gcal_fetch_events( $response ) {
		// Stop here if no $response.
		if ( ! $response ) {
			return;
		}

		$this->syncing = true;

		// Get gcals availability rules.
		$gcal_availability_rules = $this->gcal_availability_rules( $response );

		// Make sure $gcal_availability_rules is array or object so count() works.
		$gcal_availability_rules = is_array( $gcal_availability_rules ) || is_object( $gcal_availability_rules ) ? $gcal_availability_rules : [];

		// Last synced variables.
		// 0: current time in timestamp.
		// 1: number of events synced.
		$last_synced[] = absint( current_time( 'timestamp' ) );
		$last_synced[] = absint( count( $gcal_availability_rules ) );

		// Save gcal availability.
		if ( $this->get_user_id() ) {
			update_user_meta( $this->get_user_id(), 'wc_appointments_gcal_availability_last_synced', $last_synced );
		} else {
			update_option( 'wc_appointments_gcal_availability_last_synced', $last_synced );
		}

		$this->syncing = false;
	}

	/**
	 * Generate availability rules from GCal.
	 *
	 * @param  array $response
	 * @return void
	 */
	public function gcal_availability_rules( $response ) {
		global $wpdb;

		if ( $this->staff_syncing ) {
			// staff is being added/removed, don't touch the appointments.
			return;
		}

		// Response error.
		if ( is_wp_error( $response ) || 200 !== $response['response']['code'] || 'OK' !== strtoupper( $response['response']['message'] ) ) {

			// Debug.
			if ( 'yes' === $this->get_debug() ) {
				wc_add_appointment_log( $this->id, 'Error while performing sync from Google Calendar: ' . $this->get_calendar_id() . ': ' . var_export( $response['body'], true ) );
			}
			return;
		}

		// Hook: woocommerce_appointments_sync_from_gcal_start
		do_action( 'woocommerce_appointments_sync_from_gcal_start', $response );

		// Get site's time zone.
		$wp_appointments_timezone = wc_timezone_string();

		// Get time zone offset.
		$timezone_offset = wc_appointment_get_timezone_offset( $wp_appointments_timezone );

		// Get response data.
		$response_data = json_decode( $response['body'], true );

		// No events.
		if ( empty( $response_data['items'] ) || ! is_array( $response_data['items'] ) ) {
			return;
		}

		// Set next sync token.
		$sync_token = isset( $response_data['nextSyncToken'] ) ? $response_data['nextSyncToken'] : '';
		if ( $sync_token ) {
			$this->set_sync_token( $sync_token );
		}

		// Set event ids for counting later.
		$gcal_count = [];

		// Set event eid if it exists later.
		$appointment_eid = 0;

		/**
		 * Availability Data store instance.
		 *
		 * @var WC_Appointments_Availability_Data_Store $availability_data_store
		 */
		$availability_data_store = WC_Data_Store::load( WC_Appointments_Availability::DATA_STORE );

		#update_option( 'xxx3', $response_data );

		// Debug.
		if ( 'yes' === $this->get_debug() ) {
			if ( $this->get_user_id() ) {
				#wc_add_appointment_log( $this->id, 'List events from Google for staff #' . $this->get_user_id() . ':' . var_export( $response_data, true ) );
			} else {
				#wc_add_appointment_log( $this->id, 'List events from Google: ' . var_export( $response_data, true ) );
			}
		}

		// Assemble events
		foreach ( $response_data['items'] as $event ) {
			// Hook: woocommerce_appointments_sync_from_gcal_start
			do_action( 'woocommerce_appointments_sync_from_gcal_event_start', $event );

			// Testing.
			#wc_add_appointment_log( $this->id, 'Event data: ' . var_export( $event, true ) );

			// Check if all day event.
			// value = DATE for all day, otherwise time included.
			$all_day = isset( $event['start']['date'] ) && isset( $event['end']['date'] ) ? true : false;

			if ( $all_day ) {
				// Get Start and end date information
				$dtstart = new WC_DateTime( $event['start']['date'] );
				$dtend   = new WC_DateTime( $event['end']['date'] );
				$dtend->modify( '-1 second' ); #reduce 1 sec from end date.
			} else {
				// Get Start and end datetime information
				$dtstart = new WC_DateTime( $event['start']['dateTime'] );
				#$dtstart->setTimezone( new DateTimeZone( $wp_appointments_timezone ) );
				$dtend = new WC_DateTime( $event['end']['dateTime'] );
				#$dtend->setTimezone( new DateTimeZone( $wp_appointments_timezone ) );
			}

			// Load all synced availabilities.
			$availabilities = $availability_data_store->get_all(
				array(
					array(
						'key'     => 'event_id',
						'compare' => '=',
						'value'   => $event['id'],
					),
				)
			);

			// No availabilities, check if an appointment matches the event.
			if ( empty( $availabilities ) ) {

				// Debug.
				if ( 'yes' === $this->get_debug() ) {
					if ( $this->get_user_id() ) {
						#wc_add_appointment_log( $this->id, 'Availabilities for event #' . $event['id'] . ' for staff #' . $this->get_user_id() . ':' . var_export( $availabilities, true ) );
					} else {
						#wc_add_appointment_log( $this->id, 'Availabilities for event #' . $event['id'] . ': ' . var_export( $availabilities, true ) );
					}
				}

				// Check if appointment ID is saved in extendedProperties.
				if ( isset( $event['extendedProperties']['shared']['appointment_id'] ) ) {
					$appointment_eid = absint( $event['extendedProperties']['shared']['appointment_id'] );
					$appointment_eid = is_string( get_post_status( $appointment_eid ) ) ? $appointment_eid : 0; #check if post exists.
				} else {
					$appointment_eid = 0;
				}

				// Cancel appointment.
				if ( $appointment_eid && 0 !== $appointment_eid ) {
					// When event is deleted inside GCal set appointment status to cancelled and go to next event.
					if ( isset( $event['status'] ) && 'CANCELLED' === strtoupper( $event['status'] ) ) {
						// Get appointment object.
						$appointment = get_wc_appointment( $appointment_eid );

						// Don't proceed if ID is not of a valid appointment.
						if ( ! is_a( $appointment, 'WC_Appointment' ) ) {
							continue;
						}

						// Don't cancel trashed appointment.
						if ( 'trash' === $appointment->get_status() ) {
							continue;
						}

						// Don't cancel already cancelled appointment.
						if ( 'cancelled' === $appointment->get_status() ) {
							continue;
						}

						// Get staff IDs.
						$staff_ids = $appointment->get_staff_ids();

						// Testing.
						#wc_add_appointment_log( $this->id, 'Staff IDs: ' . var_export( $staff_ids, true ) );

						// Event coming from staff member.
						if ( $this->get_user_id() ) {
							// Remove staff that has cancelled event from appointment staff.
							$set_staff_ids = array_diff( $staff_ids, [ $this->get_user_id() ] );

							// Staff still present.
							if ( $set_staff_ids ) {
								// Save.
								$this->syncing = true;

								$appointment->set_staff_ids( $set_staff_ids );
								$appointment->save();

								$this->syncing = false;

								// Staff removed, but skipped cancellation.
								if ( 'yes' === $this->get_debug() ) {
									wc_add_appointment_log( $this->id, 'Successfully removed staff #' . $this->get_user_id() . ' from appointment #' . $appointment_eid . ' from Google Calendar event #' . $event['id'] );
								}

								// Skip cancellation.
								continue;

							// No staff present any more.
							} else {
								// Save.
								$this->syncing = true;

								$appointment->set_staff_ids( [] );
								$appointment->save();

								$this->syncing = false;

								// Go one with the appointment cancellation.
							}
						}

						$this->syncing = true;

						// Update appointment status to cancelled.
						$appointment->update_status( 'cancelled' );
						$appointment->save();

						$this->syncing = false;

						// Sync to other calendars withc multi-calendar sync.
						// Sync to site calendar.
						if ( $this->get_user_id() ) {
							$site_calendar_id  = get_option( 'wc_appointments_gcal_calendar_id' );
							// Site calendar exists?
							if ( $site_calendar_id ) {
								// Switch calendar back to global for new sync.
								$this->set_calendar_id( $site_calendar_id );
								$this->set_user_id( 0 ); #reset to global calendar sync.

								// Testing.
								#wc_add_appointment_log( $this->id, 'Removal of appointment #' . $appointment_eid . ' from Google Calendar #' . $this->get_calendar_id() );

								$this->remove_from_gcal( $appointment_eid );
							}
						// Sync to other staff members.
						} elseif ( $staff_ids ) {
							foreach ( $staff_ids as $staff_id ) {
								$calendar_id       = get_user_meta( $staff_id, 'wc_appointments_gcal_calendar_id', true );
								$staff_calendar_id = $calendar_id ? $calendar_id : '';
								// Staff must have calendar ID set.
								// Skip sync to the same calendar again.
								if ( $staff_calendar_id && $staff_calendar_id !== $this->get_calendar_id() ) {
									// Switch calendar to staff for new sync.
									$this->set_calendar_id( $staff_calendar_id );
									$this->set_user_id( $staff_id ); #reset to staff calendar sync.

									// Testing.
									#wc_add_appointment_log( $this->id, 'Removal of appointment #' . $appointment_eid . ' from Google Calendar #' . $this->get_calendar_id() . ' for staff #' . $staff_id );

									$this->remove_from_gcal( $appointment_eid, $staff_id, $staff_calendar_id, false );
								}
							}

							// Reset to global calendar sync.
							$site_calendar_id  = get_option( 'wc_appointments_gcal_calendar_id' );
							$this->set_calendar_id( $site_calendar_id );
							$this->set_user_id( 0 );
						}

						// Debug.
						if ( 'yes' === $this->get_debug() ) {
							if ( $this->get_user_id() ) {
								wc_add_appointment_log( $this->id, 'Successfully cancelled appointment #' . $appointment_eid . ' from Google Calendar event #' . $event['id'] . ' for staff #' . $this->get_user_id() );
							} else {
								wc_add_appointment_log( $this->id, 'Successfully cancelled appointment #' . $appointment_eid . ' from Google Calendar event #' . $event['id'] );
							}
						}
					// Update appointment data.
					} else {
						// Get appointment object.
						$appointment = get_wc_appointment( $appointment_eid );

						// Don't proceed if ID is not of a valid appointment.
						if ( ! is_a( $appointment, 'WC_Appointment' ) ) {
							continue;
						}

						// Testing.
						#wc_add_appointment_log( $this->id, 'Appointment 1 data: ' . var_export( $appointment, true ) );
						#wc_add_appointment_log( $this->id, 'Appointment start-end: ' . var_export( absint( date( 'YmdHis', $appointment->get_start() ) ) . '-' . absint( date( 'YmdHis', $appointment->get_end() ) ), true ) );
						#wc_add_appointment_log( $this->id, 'Event start-end: ' . var_export( absint( $dtstart->format( 'YmdHis' ) ) . '-' . absint( $dtend->format( 'YmdHis' ) ), true ) );
						#wc_add_appointment_log( $this->id, 'Appointment local timestamp start-end: ' . var_export( absint( strtotime( $dtstart->format( 'Y-m-d H:i:s' ) ) ) . '-' . absint( strtotime( $dtend->format( 'Y-m-d H:i:s' ) ) ), true ) );

						// Skip to next event if appointment times are the same.
						if (
							absint( date( 'YmdHis', $appointment->get_start() ) ) === absint( $dtstart->format( 'YmdHis' ) ) &&
							absint( date( 'YmdHis', $appointment->get_end() ) ) === absint( $dtend->format( 'YmdHis' ) )
						) {
							continue;
						}

						// Get staff IDs.
						$staff_ids = $appointment->get_staff_ids();

						// Testing.
						#wc_add_appointment_log( $this->id, 'Staff 2 IDs: ' . var_export( $staff_ids, true ) );

						$this->syncing = true;

						// Set appointment start and end date with events time and timezone.
						// Do not use $dtstart->getTimestamp()" as it does not work correctly.
						$appointment->set_start( absint( strtotime( $dtstart->format( 'Y-m-d H:i:s' ) ) ) );
						$appointment->set_end( absint( strtotime( $dtend->format( 'Y-m-d H:i:s' ) ) ) );
						$appointment->set_all_day( intval( $all_day ) );

						// Testing.
						#wc_add_appointment_log( $this->id, 'Appointment get start-end: ' . var_export( absint( date( 'YmdHis', $appointment->get_start() ) ) . '-' . absint( date( 'YmdHis', $appointment->get_end() ) ), true ) );

						// Update appointment event ID if saved
						// in extendedProperties of the event.
						if ( $appointment_eid ) {
							if ( $this->get_user_id() ) {
								$appointment->set_google_calendar_staff_event_ids( array( $this->get_user_id() => $event['id'] ) );
							} else {
								$appointment->set_google_calendar_event_id( wc_clean( $event['id'] ) );
							}
						}

						// Testing.
						#wc_add_appointment_log( $this->id, 'Appointment 2 data: ' . var_export( $appointment, true ) );

						// Save the changes.
						$appointment->save();

						$this->syncing = false;

						// Sync to other calendars withc multi-calendar sync.
						// Sync to site calendar.
						if ( $this->get_user_id() ) {
							$site_calendar_id  = get_option( 'wc_appointments_gcal_calendar_id' );
							// Site calendar exists?
							if ( $site_calendar_id ) {
								// Switch calendar back to global for new sync.
								$this->set_calendar_id( $site_calendar_id );
								$this->set_user_id( 0 ); #reset to global calendar sync.

								// Testing.
								#wc_add_appointment_log( $this->id, 'Sync appointment #' . $appointment_eid . ' to Google Calendar #' . $this->get_calendar_id() );

								$this->sync_to_gcal( $appointment_eid, false, false, true ); #true - sync other staff as well.
							}
							// Sync to other staff members.
						} elseif ( $staff_ids ) {
							foreach ( $staff_ids as $staff_id ) {
								$calendar_id       = get_user_meta( $staff_id, 'wc_appointments_gcal_calendar_id', true );
								$staff_calendar_id = $calendar_id ? $calendar_id : '';
								// Staff must have calendar ID set.
								// Skip sync to the same calendar again.
								if ( $staff_calendar_id && $staff_calendar_id !== $this->get_calendar_id() ) {
									// Switch calendar to staff for new sync.
									$this->set_calendar_id( $staff_calendar_id );
									$this->set_user_id( $staff_id ); #reset to staff calendar sync.

									// Testing.
									#wc_add_appointment_log( $this->id, 'Sync appointment #' . $appointment_eid . ' to Google Calendar #' . $this->get_calendar_id() . ' for staff #' . $staff_id );

									$this->sync_to_gcal( $appointment_eid, $staff_id, $staff_calendar_id, true ); #true - sync other staff as well.
								}
							}

							// Reset to global calendar sync.
							$site_calendar_id  = get_option( 'wc_appointments_gcal_calendar_id' );
							$this->set_calendar_id( $site_calendar_id );
							$this->set_user_id( 0 );
						}

						// Debug.
						if ( 'yes' === $this->get_debug() ) {
							if ( $this->get_user_id() ) {
								wc_add_appointment_log( $this->id, 'Successfully updated appointment #' . $appointment_eid . ' from Google Calendar event #' . $event['id'] . ' for staff #' . $this->get_user_id() );
							} else {
								wc_add_appointment_log( $this->id, 'Successfully updated appointment #' . $appointment_eid . ' from Google Calendar event #' . $event['id'] );
							}
						}
					}

					// Go to next event.
					continue;
				}

				// Check again if event is already synced.
				// @TODO remove duplicates more elegantly.
				$availabilities_recheck = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT ID
							FROM {$wpdb->prefix}wc_appointments_availability
							WHERE `event_id` = %s
							ORDER BY ordering ASC",
						$event['id']
					),
					ARRAY_A
				);

				// If no availability found, just create one.
				if ( ! empty( $availabilities_recheck ) ) {
					continue;
				}

				$availability = get_wc_appointments_availability();
				if ( 'CANCELLED' !== strtoupper( $event['status'] ) ) {
					$this->update_availability_from_event( $availability, $event );
					$availability->save();

					// Debug.
					if ( 'yes' === $this->get_debug() ) {
						if ( $this->get_user_id() ) {
							wc_add_appointment_log( $this->id, 'Successfully created availability rule from Google Calendar event #' . $event['id'] . ' for staff #' . $this->get_user_id() );
						} else {
							wc_add_appointment_log( $this->id, 'Successfully created availability rule from Google Calendar event #' . $event['id'] );
						}
					}
				}

				continue;
			}

			// Don't save as availability rule if event is from appointment.
			if ( $appointment_eid ) {
				continue;
			}

			// Loop through availability rules.
			// Update rules or delete them.
			foreach ( $availabilities as $availability ) {
				$event_date        = new WC_DateTime( $event['updated'] );
				$availability_date = $availability->get_date_modified();

				// Testing.
				#wc_add_appointment_log( $this->id, 'Event #' . $event['id'] . ' date #' . var_export( $event_date, true ) );
				#wc_add_appointment_log( $this->id, 'Availability #' . $event['id'] . ' date #' . var_export( $availability_date, true ) );
				#wc_add_appointment_log( $this->id, 'Event #' . $event['id'] . ' :' . var_export( $event, true ) );

				if ( $event_date > $availability_date ) {
					// Sync Google Event -> Availability.
					if ( 'CANCELLED' !== strtoupper( $event['status'] ) ) {
						$this->update_availability_from_event( $availability, $event );
						$availability->save();

						// Debug.
						if ( 'yes' === $this->get_debug() ) {
							if ( $this->get_user_id() ) {
								wc_add_appointment_log( $this->id, 'Successfully updated availability rule from Google Calendar event #' . $event['id'] . ' for staff #' . $this->get_user_id() );
							} else {
								wc_add_appointment_log( $this->id, 'Successfully updated availability rule from Google Calendar event #' . $event['id'] );
							}
						}
					} else {
						// @TODO cancelled instances of recurring events should be available.
						$availability->delete();

						// Debug.
						if ( 'yes' === $this->get_debug() ) {
							if ( $this->get_user_id() ) {
								wc_add_appointment_log( $this->id, 'Successfully deleted availability rule #' . $availability->get_id() . ' for staff #' . $this->get_user_id() );
							} else {
								wc_add_appointment_log( $this->id, 'Successfully deleted availability rule #' . $availability->get_id() );
							}
						}
					}
				}
			}

			// Add event to counter.
			if ( 'CANCELLED' !== strtoupper( $event['status'] ) ) {
				$gcal_count[] = $event['id'];
			}
		}

		// Debug.
		if ( 'yes' === $this->get_debug() ) {
			if ( $this->get_user_id() ) {
				#wc_add_appointment_log( $this->id, 'Sync from Google Calendar for staff #' . $this->get_user_id() . ' is successful.' ); #debug
			} else {
				#wc_add_appointment_log( $this->id, 'Sync from Google Calendar is successful.' ); #debug
			}
		}

		// Event ids for counting.
		return $gcal_count;
	}

	/**
	 * Sanitize a recurring rule to make sure the date + time formats match up.
	 *
	 * @param string                        $rrule Recurring Rule.
	 * @param Google_Service_Calendar_Event $event Google calendar event object.
	 *
	 * @return string
	 */
	private function maybe_sanitize_rrule( $rrule, $all_day ) {
		// If we have only a start date then make sure the UNTIL also only has a date.
		if ( $all_day ) {
			$rrule = preg_replace( '/(UNTIL=\d{8})T\d{6}[^;]*/', '$1', $rrule );
		}

		return $rrule;
	}

	/**
	 * Update global availability object with data from google event object.
	 *
	 * @param WC_Appointments_Availability $availability WooCommerce Appointments Availability object.
	 * @param array $event Google calendar event.
	 * @param object $dtstart Google calendar event start date/time.
	 * @param object $dtend Google calendar event end date/time.
	 *
	 * @return bool
	 */
	private function update_availability_from_event( WC_Appointments_Availability $availability, $event ) {
		// Check if all day event.
		// value = DATE for all day, otherwise time included.
		$all_day = isset( $event['start']['date'] ) && isset( $event['end']['date'] ) ? true : false;

		// Check if BUSY or FREE.
		// value = OPAQUE for busy, and TRANSPARENT for free
		#$yes_no = isset( $event['transparency'] ) && 'TRANSPARENT' === strtoupper( $event['transparency'] ) ? 'yes' : 'no';
		$yes_no = 'no';

		// Get site TimeZone.
		$wp_appointments_timezone = wc_timezone_string();

		if ( $all_day ) {
			// Get Start and end date information
			$dtstart = new DateTime( $event['start']['date'] );
			$dtend   = new DateTime( $event['end']['date'] );
			$dtend->modify( '-1 second' ); #reduce 1 sec from end date.
		} else {
			// Get Start and end datetime information
			$dtstart = new DateTime( $event['start']['dateTime'] );
			$dtstart->setTimezone( new DateTimeZone( $wp_appointments_timezone ) );
			$dtend = new DateTime( $event['end']['dateTime'] );
			$dtend->setTimezone( new DateTimeZone( $wp_appointments_timezone ) );
		}

		$availability->set_event_id( $event['id'] )
			->set_title( $event['summary'] )
			->set_appointable( $yes_no )
			->set_priority( 5 )
			->set_ordering( 0 );

		if ( $this->get_user_id() ) {
			$availability->set_kind( 'availability#staff' );
			$availability->set_kind_id( $this->get_user_id() );
		} else {
			$availability->set_kind( 'availability#global' );
		}

		// @TODO: check timezones.
		if ( isset( $event['recurrence'] ) ) {

			$availability->set_range_type( 'rrule' );
			$availability->set_rrule( join( "\n", $event['recurrence'] ) );
			$availability->set_rrule( $this->maybe_sanitize_rrule( join( "\n", $event['recurrence'] ), $all_day ) );
			if ( $all_day ) {
				$availability->set_from_range( $dtstart->format( 'Y-m-d' ) );
				$availability->set_to_range( $dtend->format( 'Y-m-d' ) );
			} else {
				$availability->set_from_range( $dtstart->format( 'Y-m-d\TH:i:sP' ) ); #\DateTime::RFC3339
				$availability->set_to_range( $dtend->format( 'Y-m-d\TH:i:sP' ) ); #\DateTime::RFC3339
			}

		} elseif ( $all_day ) {

			$availability->set_range_type( 'custom' )
				->set_from_range( $dtstart->format( 'Y-m-d' ) )
				->set_to_range( $dtend->format( 'Y-m-d' ) );

		} else {

			$availability->set_range_type( 'custom:daterange' )
				->set_from_date( $dtstart->format( 'Y-m-d' ) )
				->set_to_date( $dtend->format( 'Y-m-d' ) )
				->set_from_range( $dtstart->format( 'H:i' ) )
				->set_to_range( $dtend->format( 'H:i' ) );

		}

		return true;
	}

	/**
	 * Maybe delete Global Availability from Google.
	 *
	 * @param WC_Appointments_Availability $availability Availability to delete.
	 */
	public function delete_availability( WC_Appointments_Availability $availability ) {
		if ( $availability->get_event_id() ) {
			// Set staff ID and staff calendar ID
			// if event is from staff availability.
			if ( 'availability#staff' === $availability->get_kind() && $availability->get_kind_id() ) {
				$this->set_user_id( $availability->get_kind_id() );
			}

			// Set parameters for gcal request.
			$calendar_id = $this->get_calendar_id() ? $this->get_calendar_id() : 0;
			$api_url     = $this->calendars_uri . $calendar_id . '/events/' . $availability->get_event_id();
			$user_id     = $this->get_user_id() ? $this->get_user_id() : 0;
			$params      = array(
				'method' => 'DELETE',
			);

			try {

				$response = $this->make_gcal_request( $api_url, $params, $user_id );

				// Nothing to work on.
				if ( is_wp_error( $response ) || ! $response ) {
					return;
				}

				// Event already deleted.
				if ( 410 === $response['response']['code'] ) {
					return;
				}

				// Debug.
				if ( 'yes' === $this->get_debug() ) {
					if ( $this->get_user_id() ) {
						wc_add_appointment_log( $this->id, 'Successfully deleted event #' . $availability->get_event_id() . ' from Google for staff #' . $this->get_user_id() );
					} else {
						wc_add_appointment_log( $this->id, 'Successfully deleted event #' . $availability->get_event_id() . ' from Google' );
					}
				}

			} catch ( Exception $e ) {

				// Debug.
				if ( 'yes' === $this->get_debug() ) {
					if ( $this->get_user_id() ) {
						wc_add_appointment_log( $this->id, 'Error while deleting event #' . $availability->get_event_id() . ' from Google for staff #' . $this->get_user_id() . ':' . $e->getMessage() );
					} else {
						wc_add_appointment_log( $this->id, 'Error while deleting event #' . $availability->get_event_id() . ' from Google: ' . $e->getMessage() );
					}
				}
			}
		}
	}

	/**
	 * Sync Global Availability to Google.
	 *
	 * @param WC_Appointments_Availability $availability Global Availability object.
	 */
	public function sync_availability( WC_Appointments_Availability $availability ) {
		if ( ! $availability->get_changes() ) {
			// nothing changed don't waste time syncing.
			return;
		}

		if ( $this->syncing ) {
			// Event is coming from google don't send it back.
			return;
		}

		if ( $availability->get_event_id() ) {
			// Set staff ID and staff calendar ID
			// if event is from staff availability.
			if ( 'availability#staff' === $availability->get_kind() && $availability->get_kind_id() ) {
				$this->set_user_id( $availability->get_kind_id() );
			}

			// Set parameters for gcal request.
			$calendar_id = $this->get_calendar_id() ? $this->get_calendar_id() : 0;
			$api_url     = $this->calendars_uri . $calendar_id . '/events/' . $availability->get_event_id();
			$user_id     = $this->get_user_id() ? $this->get_user_id() : 0;
			$params      = array(
				'method' => 'GET',
			);
			$json_data   = false;
			$event_data  = false;

			try {

				$response = $this->make_gcal_request( $api_url, $params, $user_id );

				// Nothing to work on.
				if ( is_wp_error( $response ) || ! $response ) {
					return;
				}

				$json_data = json_decode( $response['body'], true );

				// Debug.
				if ( 'yes' === $this->get_debug() ) {
					if ( $this->get_user_id() ) {
						#wc_add_appointment_log( $this->id, 'Successfully got event #' . $availability->get_event_id() . ' from Google for staff #' . $this->get_user_id() );
					} else {
						#wc_add_appointment_log( $this->id, 'Successfully got event #' . $availability->get_event_id() . ' from Google' );
					}
				}

			} catch ( Exception $e ) {

				// Debug.
				if ( 'yes' === $this->get_debug() ) {
					if ( $this->get_user_id() ) {
						wc_add_appointment_log( $this->id, 'Error while getting event #' . $availability->get_event_id() . ' from Google for staff #' . $this->get_user_id() . ':' . $e->getMessage() );
					} else {
						wc_add_appointment_log( $this->id, 'Error while getting event #' . $availability->get_event_id() . ' from Google: ' . $e->getMessage() );
					}
				}
			}

			// Only update events created in Gcal.
			// @TODO maybe add site rules to gcal as new events.
			if ( $json_data ) {
				$event      = $json_data;
				$event_data = $this->update_event_from_availability( $event, $availability );

				// Skip update of 'rrule' type of rules.
				if ( $event_data ) {

					// Set parameters for gcal request.
					$params = array(
						'method' => 'PUT',
						'body'   => wp_json_encode( $event_data ),
					);

					try {

						$response = $this->make_gcal_request( $api_url, $params, $user_id );

						// Debug.
						if ( 'yes' === $this->get_debug() ) {
							if ( $this->get_user_id() ) {
								wc_add_appointment_log( $this->id, 'Successfully updated event #' . $event_data['id'] . ' with Google for staff #' . $this->get_user_id() );
							} else {
								wc_add_appointment_log( $this->id, 'Successfully updated event #' . $event_data['id'] . ' with Google' );
							}
						}

					} catch ( Exception $e ) {

						// Debug.
						if ( 'yes' === $this->get_debug() ) {
							if ( $this->get_user_id() ) {
								wc_add_appointment_log( $this->id, 'Error while updating event #' . $event_data['id'] . ' with Google for staff #' . $this->get_user_id() . ':' . $e->getMessage() );
							} else {
								wc_add_appointment_log( $this->id, 'Error while updating event #' . $event_data['id'] . ' with Google: ' . $e->getMessage() );
							}
						}
					}
				}
			}
		}
	}

	/**
	 * Update google event object with data from global availability object.
	 *
	 * @param array  $event Google calendar event.
	 * @param WC_Appointments_Availability $availability WooCommerce Global Availability object.
	 *
	 * @return array|void
	 */
	private function update_event_from_availability( $event, WC_Appointments_Availability $availability ) {
		$timezone        = wc_timezone_string();
		$start_date_time = new WC_DateTime();
		$end_date_time   = new WC_DateTime();

		$event['summary'] = $availability->get_title();

		switch ( $availability->get_range_type() ) {
			case 'custom:daterange':
				$start_date_time = new WC_DateTime( $availability->get_from_date() . ' ' . $availability->get_from_range() );
				$event['start']  = array(
					'dateTime' => $start_date_time->format( 'Y-m-d\TH:i:s' ),
					'timeZone' => $timezone,
				);

				$end_date_time = new WC_DateTime( $availability->get_to_date() . ' ' . $availability->get_to_range() );
				$event['end']  = array(
					'dateTime' => $end_date_time->format( 'Y-m-d\TH:i:s' ),
					'timeZone' => $timezone,
				);

				break;
			case 'custom':
				$start_date_time = new WC_DateTime( $availability->get_from_range() );
				$event['start']  = array(
					'date' => $start_date_time->format( 'Y-m-d' ),
				);

				$end_date_time = new WC_DateTime( $availability->get_to_range() );
				$end_date_time->add( new DateInterval( 'P1D' ) );
				$event['end'] = array(
					'date' => $end_date_time->format( 'Y-m-d' ),
				);

				break;
			case 'months':
				$start_date_time->setDate(
					date( 'Y' ),
					$availability->get_from_range(),
					1
				);

				$event['start'] = array(
					'date' => $start_date_time->format( 'Y-m-d' ),
				);

				$number_of_months = 1 + intval( $availability->get_to_range() ) - intval( $availability->get_from_range() );

				$end_date_time = $start_date_time->add( new DateInterval( 'P' . $number_of_months . 'M' ) );

				$event['end'] = array(
					'date' => $end_date_time->format( 'Y-m-d' ),
				);

				$event['recurrence'] = array( 'RRULE:FREQ=YEARLY' );

				break;
			case 'weeks':
				$start_date_time->setDate(
					date( 'Y' ),
					1,
					1
				);

				$end_date_time->setDate(
					date( 'Y' ),
					1,
					2
				);

				$all_days     = join( ',', array_keys( \RRule\RRule::WEEKDAYS ) );
				$week_numbers = join( ',', range( $availability->get_from_range(), $availability->get_to_range() ) );
				$rrule        = "RRULE:FREQ=YEARLY;BYWEEKNO=$week_numbers;BYDAY=$all_days";

				$event['start'] = array(
					'date' => $start_date_time->format( 'Y-m-d' ),
				);

				$event['end'] = array(
					'date' => $end_date_time->format( 'Y-m-d' ),
				);

				$event['recurrence'] = array( $rrule );

				break;
			case 'days':
				$start_day = intval( $availability->get_from_range() );
				$end_day   = intval( $availability->get_to_range() );

				$start_date_time->modify( 'this ' . self::DAYS_OF_WEEK[ $start_day ] );
				$event['start'] = array(
					'date' => $start_date_time->format( 'Y-m-d' ),
				);

				$end_date_time = $start_date_time->modify( 'this ' . self::DAYS_OF_WEEK[ $end_day ] );

				$event['end'] = array(
					'date' => $end_date_time->format( 'Y-m-d' ),
				);

				$event['recurrence'] = array( 'RRULE:FREQ=WEEKLY' );

				break;
			case 'time:1':
			case 'time:2':
			case 'time:3':
			case 'time:4':
			case 'time:5':
			case 'time:6':
			case 'time:7':
				list( , $day_of_week ) = explode( ':', $availability->get_range_type() );

				$start_date_time->modify( 'this ' . self::DAYS_OF_WEEK[ $day_of_week ] );
				$end_date_time->modify( 'this ' . self::DAYS_OF_WEEK[ $day_of_week ] );
				$rrule = 'RRULE:FREQ=WEEKLY';

				// fall through please.
			case 'time':
				if ( ! isset( $rrule ) ) {
					$rrule = 'RRULE:FREQ=DAILY';
				}

				list( $start_hour, $start_min ) = explode( ':', $availability->get_from_range() );
				$start_date_time->setTime( $start_hour, $start_min );

				list( $end_hour, $end_min ) = explode( ':', $availability->get_to_range() );
				$end_date_time->setTime( $end_hour, $end_min );

				$event['start'] = array(
					'dateTime' => $start_date_time->format( 'Y-m-d\TH:i:s' ),
					'timeZone' => $timezone,
				);

				$event['end'] = array(
					'dateTime' => $end_date_time->format( 'Y-m-d\TH:i:s' ),
					'timeZone' => $timezone,
				);

				$event['recurrence'] = array( $rrule );

				break;

			default:
				// That should be everything, anything else is not supported.
				return;
		}

		return $event;
	}
}

if ( ! function_exists( 'wc_appointments_gcal' ) ) {
	/**
	 * Returns the main instance of WC_Appointments_GCal to prevent the need to use globals.
	 *
	 * @return WC_Appointments_GCal
	 */
	function wc_appointments_gcal() {
		return WC_Appointments_GCal::instance();
	}
}

wc_appointments_gcal();
