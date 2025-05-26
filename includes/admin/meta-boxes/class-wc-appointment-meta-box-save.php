<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

class WC_Appointment_Meta_Box_Save {

	/**
	 * Meta box ID.
	 *
	 * @var string
	 */
	public $id;

	/**
	 * Meta box title.
	 *
	 * @var string
	 */
	public $title;

	/**
	 * Meta box context.
	 *
	 * @var string
	 */
	public $context;

	/**
	 * Meta box priority.
	 *
	 * @var string
	 */
	public $priority;

	/**
	 * Meta box post types.
	 * @var array
	 */
	public $post_types;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id         = 'woocommerce-appointment-save';
		$this->title      = __( 'Save', 'woocommerce-appointments' );
		$this->context    = 'side';
		$this->priority   = 'high';
		$this->post_types = [ 'wc_appointment' ];

		add_action( 'woocommerce_before_appointment_object_save', [ $this, 'track_appointment_changes' ], 10, 2 );
	}

	/**
	 * Render inner part of meta box.
	 */
	public function meta_box_inner( $post ) {
		wp_nonce_field( 'wc_appointments_save_appointment_meta_box', 'wc_appointments_save_appointment_meta_box_nonce' );

		?>
		<div class="submitbox">
			<div class="minor-save-actions">
				<div class="misc-pub-section curtime misc-pub-curtime">
					<label for="appointment_date"><?php esc_html_e( 'Created on:', 'woocommerce-appointments' ); ?></label>
					<input
						type="text"
						class="date-picker"
						name="appointment_date"
						id="appointment_date"
						maxlength="10"
						value="<?php esc_attr_e( date_i18n( 'Y-m-d', strtotime( $post->post_date ) ) ); ?>"
						pattern="[0-9]{4}-(0[1-9]|1[012])-(0[1-9]|1[0-9]|2[0-9]|3[01])"
					/>
					@
					<input
						type="number"
						class="hour"
						placeholder="<?php esc_html_e( 'h', 'woocommerce-appointments' ); ?>"
						name="appointment_date_hour"
						id="appointment_date_hour"
						maxlength="2"
						size="2"
						value="<?php esc_html_e( date_i18n( 'H', strtotime( $post->post_date ) ) ); ?>"
						pattern="\-?\d+(\.\d{0,})?"
					/>:<input
						type="number"
						class="minute"
						placeholder="<?php esc_html_e( 'm', 'woocommerce-appointments' ); ?>"
						name="appointment_date_minute"
						id="appointment_date_minute"
						maxlength="2"
						size="2"
						value="<?php esc_html_e( date_i18n( 'i', strtotime( $post->post_date ) ) ); ?>"
						pattern="\-?\d+(\.\d{0,})?"
					/>
				</div>
				<div class="misc-pub-section misc-pub-note">
					<label for="appointment_note"><?php esc_html_e( 'Track changes', 'woocommerce-appointments' ); ?>:</label>
					<select name="appointment_note" id="appointment_note">
						<option value="private"><?php esc_html_e( 'Private note', 'woocommerce-appointments' ); ?></option>
						<option value="customer"><?php esc_html_e( 'Note to customer', 'woocommerce-appointments' ); ?></option>
					</select>
					<?php echo wc_help_tip( esc_html__( 'When appointment changes, add a private note or send a note to customer', 'woocommerce-appointments' ) ); // WPCS: XSS ok. ?>
				</div>
				<div class="clear"></div>
			</div>
			<div class="major-save-actions">
				<div id="delete-action">
					<a class="submitdelete deletion" href="<?php echo get_delete_post_link( $post->ID ); ?> "><?php esc_html_e( 'Move to Trash', 'woocommerce-appointments' ); ?></a>
				</div>
				<div id="publishing-action">
					<input
						type="submit"
						class="button save_order button-primary tips"
						name="save"
						value="<?php esc_html_e( 'Update', 'woocommerce-appointments' ); ?>"
						data-tip="<?php echo wc_sanitize_tooltip( __( 'Save/update the appointment', 'woocommerce-appointments' ) ); // WPCS: XSS ok. ?>"
					/>
				</div>
				<div class="clear"></div>
			</div>
		</div>
		<?php
	}

	public function track_appointment_changes( $appointment, $data_store ) {
		// Don't procede if id is not of a valid appointment.
		if ( ! is_a( $appointment, 'WC_Appointment' ) ) {
			return;
		}

		// Get appointment changes.
		$get_changes = $appointment->get_changes();

		#error_log( var_export( $get_changes, true ) );

		// Get order object.
		$order = $appointment->get_order();

		// Only add note when: start date, staff, quantity or product changes.
		if ( $get_changes && $order && $appointment->is_edited_from_meta_box() ) {
			if ( isset( $get_changes['start'] )
			    || isset( $get_changes['staff_ids'] )
				|| isset( $get_changes['qty'] )
				|| isset( $get_changes['product_id'] )
			) {
				// Get note type.
				$note_type = isset( $_POST['appointment_note'] ) && 'customer' === $_POST['appointment_note'] ? 'customer' : 'private';
				// Appointment edited notice.
				$notice = apply_filters(
					'woocommerce_appointment_edited_notice',
					sprintf(
						/* translators: %1$d: appointment id, %2$s: old appointment time, %3$s: new appointment time */
						__( 'Appointment #%1$d has been updated.', 'woocommerce-appointments' ),
						$appointment->get_id()
					),
					$appointment
				);

				// Add a note to order privately..
				if ( 'customer' === $note_type ) {
					$order->add_order_note( $notice, true, true );
				// Send the order note to customer.
				} else {
					$order->add_order_note( $notice, false, true );
				}
			}
		}
	}
}

return new WC_Appointment_Meta_Box_Save();
