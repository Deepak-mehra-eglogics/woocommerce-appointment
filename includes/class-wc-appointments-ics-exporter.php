<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * .ics Exporter
 */
class WC_Appointments_ICS_Exporter {

	/**
	 * Appointments list to export
	 *
	 * @var array
	 */
	protected $appointments = [];

	/**
	 * File path
	 *
	 * @var string
	 */
	protected $file_path = '';

	/**
	 * UID prefix.
	 *
	 * @var string
	 */
	protected $uid_prefix = 'wc_appointments_';

	/**
	 * End of line.
	 *
	 * @var string
	 */
	protected $eol = "\r\n";

	/**
	 * Get appointment .ics
	 *
	 * @param  WC_Appointment $appointment Appointment data
	 *
	 * @return string .ics path
	 */
	public function get_appointment_ics( $appointment ) {
		$product              = $appointment->get_product();
		$this->file_path      = $this->get_file_path( $appointment->get_id() . '-' . $product->get_title() );
		$this->appointments[] = $appointment;

		// Create the .ics
		$this->create();

		return $this->file_path;
	}

	/**
	 * Get .ics for appointments.
	 *
	 * @param  array  $appointments Array with WC_Appointment objects
	 * @param  string $filename .ics filename
	 *
	 * @return string .ics path
	 */
	public function get_ics( $appointments, $filename = '' ) {
		// Create a generic filename.
		if ( '' == $filename ) {
			$filename = 'appointments-' . date_i18n( 'Ymd-His', current_time( 'timestamp' ) );
		}

		$this->file_path    = $this->get_file_path( $filename );
		$this->appointments = $appointments;

		// Create the .ics
		$this->create();

		return $this->file_path;
	}

	/**
	 * Get file path
	 *
	 * @param  string $filename Filename
	 *
	 * @return string
	 */
	protected function get_file_path( $filename ) {
		$upload_data = wp_upload_dir();

		return $upload_data['path'] . '/' . sanitize_title( $filename ) . '.ics';
	}

	/**
	 * Create the .ics file
	 *
	 * @return void
	 */
	protected function create() {
		// @codingStandardIgnoreStart
		$handle = @fopen( $this->file_path, 'w' );
		$ics    = $this->generate();
		@fwrite( $handle, $ics );
		@fclose( $handle );
		// @codingStandardIgnoreEnd
	}

	/**
	 * Format the date
	 *
	 * @version 3.0.0
	 *
	 * @param int        $timestamp Timestamp to format.
	 * @param WC_Appointment $appointment   Appointment object.
	 *
	 * @return string Formatted date for ICS.
	 */
	protected function format_date( $timestamp, $appointment = null ) {
		#$pattern = 'Ymd\THis\Z';
		$pattern = 'Ymd\THis';
		$old_ts  = $timestamp;

		if ( $appointment ) {
			$pattern = ( $appointment->is_all_day() ) ? 'Ymd' : $pattern;

			// If we're working on the end timestamp
			if ( $appointment->get_end() === $timestamp ) {
				// If appointments are more than 1 day, ics format for the end date should be the day after the appointment ends
				if ( strtotime( 'midnight', $appointment->get_start() ) !== strtotime( 'midnight', $appointment->get_end() ) ) {
					$timestamp += 86400;
				}
			}
		}

		return apply_filters( 'woocommerce_appointments_ics_format_date', date( $pattern, $timestamp ), $timestamp, $old_ts, $appointment );
	}

	/**
	 * Sanitize strings for .ics
	 *
	 * @param  string $string
	 *
	 * @return string
	 */
	protected function sanitize_string( $string ) {
		$string = preg_replace( '/([,;])/', '\\\$1', $string );
		$string = str_replace( "\n", '\n', $string );
		$string = sanitize_text_field( $string );

		return $string;
	}

	/**
	 * Generate the .ics content
	 *
	 * @return string
	 */
	protected function generate() {
		$sitename = get_option( 'blogname' );

		// Set the ics data.
		$ics  = 'BEGIN:VCALENDAR' . $this->eol;
		$ics .= 'VERSION:2.0' . $this->eol;
		$ics .= 'PRODID:-//BookingWP//WooCommerce Appointments ' . WC_APPOINTMENTS_VERSION . '//EN' . $this->eol;
		$ics .= 'CALSCALE:GREGORIAN' . $this->eol;
		$ics .= 'X-WR-CALNAME:' . $this->sanitize_string( $sitename ) . $this->eol;
		$ics .= 'X-ORIGINAL-URL:' . $this->sanitize_string( get_site_url( get_current_blog_id(), '/' ) ) . $this->eol;
		/* translators: %s: site name */
		$ics .= 'X-WR-CALDESC:' . $this->sanitize_string( sprintf( __( 'Appointments from %s', 'woocommerce-appointments' ), $sitename ) ) . $this->eol;
		$ics .= 'X-WR-TIMEZONE:' . wc_timezone_string() . $this->eol;

		// Set the ics appointment data.
		foreach ( $this->appointments as $appointment ) {
			$ics_appointment = $this->ics_appointment( $appointment );
			// Loop through ics data.
			foreach ( $ics_appointment as $ics_data ) {
				#error_log( var_export( $ics_data, true ) );
				$ics .= $ics_data . $this->eol;
			}
		}

		$ics .= 'END:VCALENDAR';

		return apply_filters( 'wc_appointments_ics_exporter', $ics, $this );
	}

	/**
	 * Generate the .ics content
	 *
	 * @return array
	 */
	protected function ics_appointment( $appointment ) {
		$sitename  = get_option( 'blogname' );
		$siteadmin = get_option( 'admin_email' );

		// Set the ics data.
		$ics_a          = [];
		$appointment_id = $appointment->get_id();
		$product        = $appointment->get_product();
		$product_title  = $product ? ' - ' . $product->get_title() : '';
		$url            = $appointment->get_order() ? $appointment->get_order()->get_view_order_url() : '';
		$summary        = '#' . $appointment->get_id() . $product_title;
		$description    = '';
		$date_prefix    = $appointment->is_all_day() ? ';VALUE=DATE:' : ':';
		$staff_names    = $appointment->get_staff_members( true );
		#$date_prefix    = $appointment->is_all_day() ? ';VALUE=DATE:' : ';TZID=/' . wc_timezone_string() . ':';

		if ( $staff_names ) {
			$description .= __( 'Staff', 'woocommerce-appointments' ) . ': ' . $staff_names . '\n\n';
		}

		$post_excerpt = $product ? get_post( $product->get_id() )->post_excerp : '';

		if ( '' !== $post_excerpt ) {
			$description .= __( 'Appointment description:', 'woocommerce-appointments' ) . '\n';
			$description .= wp_kses( $post_excerpt, [] );
		}

		$ics_a['begin_vevent'] = 'BEGIN:VEVENT';
		$ics_a['dtstart']      = 'DTSTART' . $date_prefix . $this->format_date( $appointment->get_start(), $appointment );
		$ics_a['dtend']        = 'DTEND' . $date_prefix . $this->format_date( $appointment->get_end(), $appointment );
		$ics_a['uid']          = 'UID:' . $this->uid_prefix . $appointment->get_id();
		$ics_a['dtstamp']      = 'DTSTAMP:' . $this->format_date( current_time( 'timestamp' ) );
		$ics_a['location']     = 'LOCATION:';
		$ics_a['description']  = 'DESCRIPTION:' . $this->sanitize_string( $description );
		$ics_a['url']          = 'URL;VALUE=URI:' . $this->sanitize_string( $url );
		$ics_a['summary']      = 'SUMMARY:' . $this->sanitize_string( $summary );
		$ics_a['organizer']    = 'ORGANIZER;CN="' . $this->sanitize_string( $sitename ) . '":' . $this->sanitize_string( $siteadmin );
		$ics_a['end_vevent']   = 'END:VEVENT';

		return apply_filters( 'wc_appointments_ics_appointment', $ics_a, $appointment, $this );
	}
}
