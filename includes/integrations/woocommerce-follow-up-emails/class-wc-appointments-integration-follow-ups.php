<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Files.FileName

/**
 * WooCommerce Follow Up Emails integration class.
 *
 * Last compatibility check: WooCommerce Follow Up Emails 4.9.13
 */
class WC_Appointments_Integration_WCFUE {

	public function __construct() {
        add_filter( 'fue_email_types', array( $this, 'register_email_type' ) );

		// manual emails
        add_action( 'fue_manual_types', array( $this, 'manual_types' ) );
        add_action( 'fue_manual_type_actions', array( $this, 'manual_type_actions' ) );
        add_action( 'fue_manual_js', array( $this, 'manual_js' ) );
        add_filter( 'fue_manual_email_recipients', array( $this, 'manual_email_recipients' ), 10, 2 );

        // trigger fields
        add_filter( 'fue_email_form_trigger_fields', array( $this, 'add_product_selector' ) );

        #add_action( 'fue_email_form_scripts', array( $this, 'email_form_script' ) );
        add_action( 'fue_manual_js', array( $this, 'manual_form_script' ) );

        add_filter( 'fue_trigger_str', array( $this, 'date_trigger_string' ), 10, 2 );

        add_action( 'fue_email_variables_list', array( $this, 'email_variables_list' ) );
        add_action( 'fue_email_manual_variables_list', array( $this, 'email_variables_list' ) );

        add_action( 'fue_before_variable_replacements', array( $this, 'register_variable_replacements' ), 11, 4 );

        // Appointment created trigger.
		add_action( 'woocommerce_new_appointment', array( $this, 'appointment_created' ) );
		add_action( 'woocommerce_appointment_in-cart_to_unpaid', array( $this, 'appointment_created_from_cart' ) );
		add_action( 'woocommerce_appointment_in-cart_to_pending-confirmation', array( $this, 'appointment_created_from_cart' ) );

		foreach ( get_wc_appointment_statuses( 'all' ) as $status ) {
			add_action( 'woocommerce_appointment_' . $status, array( $this, 'appointment_status_updated' ) );
		}

        // manually trigger status changes because WC Appointments doesn't trigger these when saving from the admin screen
        add_action( 'save_post', array( $this, 'maybe_trigger_status_update' ), 11, 1 );

        add_filter( 'fue_unqueue_emails_filter_on_order_status_change', array( $this, 'maybe_unqueue_emails_on_status_change' ), 10, 4 );

        // Register trigger fields.
        add_action( 'fue_email_form_trigger_fields', array( $this, 'register_trigger_fields' ), 9 );

        // saving email.
		add_filter( 'fue_email_created', array( $this, 'fix_save_type_meta' ), 11, 2 );
        add_filter( 'fue_email_updated', array( $this, 'fix_save_type_meta' ), 11, 2 );

        // Order Importer.
        add_filter( 'fue_import_orders_supported_types', array( $this, 'declare_import_support' ) );
        add_filter( 'fue_wc_get_orders_for_email', array( $this, 'get_orders_for_email' ), 10, 2 );
        add_filter( 'fue_wc_filter_orders_for_email', array( $this, 'filter_orders_for_email' ), 10, 2 );
        add_filter( 'fue_wc_import_insert', array( $this, 'add_appointment_id_to_meta' ), 1, 2 );
        add_filter( 'fue_wc_import_insert', array( $this, 'modify_insert_send_date' ), 10, 2 );

    }

    /**
     * Register custom email type
     *
     * @param array $types
     * @return array
     */
    public function register_email_type( $types ) {
        $triggers = array(
            'before_appointment_event' => __( 'Before Scheduled Date', 'woocommerce-appointments' ),
            'after_appointment_event'  => __( 'After Scheduled Date', 'woocommerce-appointments' ),
            'appointment_created'      => __( 'After Appointment is Created', 'woocommerce-appointments' ),
        );

        // add appointment statuses
        foreach ( get_wc_appointment_statuses( 'all' ) as $status ) {
            /* translators: %s: appointment statuses */
            $triggers[ 'appointment_status_' . $status ] = sprintf( __( 'After Appointment Status: %s', 'woocommerce-appointments' ), $status );
        }

        $duration_units = is_callable( array( 'Follow_Up_Emails', 'get_schedule_units' ) ) ? Follow_Up_Emails::get_schedule_units() : Follow_Up_Emails::$durations;

        $props = array(
            'label'             => __( 'WooCommerce Appointments', 'woocommerce-appointments' ),
            'singular_label'    => __( 'WooCommerce Appointment', 'woocommerce-appointments' ),
            'triggers'          => $triggers,
            'durations'         => $duration_units,
            'long_description'  => __( 'Send follow-up emails to customers that schedule appointments, services or rentals.<br />Increase revenue with a custom lifecycle marketing program from Outbound Commerce. It is email marketing for busy eCommerce businesses built by experienced eCommerce and marketing professionals.', 'woocommerce-appointments' ),
            'short_description' => __( 'Not sure where to start? Let Outbound Commerce help. Get email marketing for busy eCommerce businesses built by experienced eCommerce and marketing professionals.', 'woocommerce-appointments' ),
        );

        $types[] = new FUE_Email_Type( 'wc_appointments', $props );

        return $types;
    }

	/**
     * Appointment option for manual emails
     */
    public function manual_types() {
        ?>
        <option value="scheduled_event"><?php esc_html_e( 'Customers who scheduled this event', 'woocommerce-appointments' ); ?></option>
        <?php
    }

    /**
     * Action for manual emails when appointment is selected
     */
    public function manual_type_actions() {
        $products = [];

        $args = array(
            'post_type'   => 'product',
            'post_status' => 'publish',
            'nopaging'    => true,
        );

        $posts_query = new WP_Query();
        $posts       = $posts_query->query( $args );

        foreach ( $posts as $post ) {
            $product = WC_FUE_Compatibility::wc_get_product( $post->ID );

            if ( $product->is_type( array( 'appointment' ) ) ) {
                $products[] = $product;
			}
        }

        ?>
        <div class="send-type-appointments send-type-div">
            <select id="appointment_event_id" name="appointment_event_id" class="select2" style="width: 400px;">
                <?php foreach ( $products as $product ) : ?>
                    <option value="<?php esc_attr_e( $product->get_id() ); ?>"><?php esc_html_e( $product->get_title() ); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    <?php
    }

    /**
     * JS for manual emails
     */
    public function manual_js() {
        ?>
        jQuery( '#send_type' ).on( 'change', function() {
            switch ( jQuery(this).val() ) {
                case 'scheduled_event':
                    jQuery( '.send-type-appointments' ).show();
                    break;
            }
        } ).trigger( 'change' );
    <?php
    }

    /**
     * Get users who scheduled the selected event
     *
     * @param array $recipients
     * @param array $post
     *
     * @return array
     */
    public function manual_email_recipients( $recipients, $post ) {
        global $wpdb;

        if ( 'scheduled_event' == $post['send_type'] ) {

            $search_args = array(
                'post_type'   => 'wc_appointment',
                'post_status' => array( 'complete', 'paid' ),
                'meta_query'  => array(
                    array(
                        'key'     => '_appointment_product_id',
                        'value'   => $post['appointment_event_id'],
                        'compare' => '=',
                    ),
                ),
            );

            $posts_query  = new WP_Query();
            $appointments = $posts_query->query( $search_args );

            foreach ( $appointments as $appointment ) {

                $order_item_id = get_post_meta( $appointment->ID, '_appointment_order_item_id', true );
                $user_id       = get_post_meta( $appointment->ID, '_appointment_customer_id', true );
                $order_id      = $wpdb->get_var( $wpdb->prepare( "SELECT order_id FROM {$wpdb->prefix}woocommerce_order_items WHERE order_item_id = %d", $order_item_id ) );
				$order         = WC_FUE_Compatibility::wc_get_order( $order_id );

                $key                = $user_id . '|' . WC_FUE_Compatibility::get_order_prop( $order, 'billing_email' ) . '|' . WC_FUE_Compatibility::get_order_prop( $order, 'billing_first_name' ) . ' ' . WC_FUE_Compatibility::get_order_prop( $order, 'billing_last_name' );
				$recipients[ $key ] = array( $user_id, WC_FUE_Compatibility::get_order_prop( $order, 'billing_email' ), WC_FUE_Compatibility::get_order_prop( $order, 'billing_first_name' ) . ' ' . WC_FUE_Compatibility::get_order_prop( $order, 'billing_last_name' ) );

            }
        }

        return $recipients;
    }

    public function add_product_selector( $email ) {
        if ( in_array( $email->type, apply_filters( 'fue_appointment_form_products_selector_email_types', array( 'wc_appointments' ) ) ) ) {
            // load the categories
            $categories     = get_terms(
                'product_cat',
                array(
                    'order_by' => 'name',
                    'order'    => 'ASC',
                )
            );
            $has_variations = ( ! empty( $email->product_id ) && FUE_Addon_Woocommerce::product_has_children( $email->product_id ) ) ? true : false;
            $storewide_type = ( ! empty( $email->meta['storewide_type'] ) ) ? $email->meta['storewide_type'] : 'all';

            include 'templates/email-form/appointments/email-form.php';
        }
    }

    /**
     * Javascript for the email form
     */
    public function email_form_script() {
        wp_enqueue_script( 'fue-form-appointments', FUE_TEMPLATES_URL . '/js/email-form-appointments.js' );
    }

    /**
     * Javascript for manual emails
     */
    public function manual_form_script() {
        ?>
        jQuery( '#send_type' ).on( 'change', function() {
            if ( 'scheduled_event' == jQuery( this).val() ) {
                jQuery( '.var_wc_appointments' ).show();
            } else {
                jQuery( '.var_wc_appointments' ).hide();
            }
        } ).trigger( 'change' );
        <?php
    }

    /**
     * Return the correct trigger string for date-based emails
     *
     * @param string $trigger
     * @param $email $email
     * @return string
     */
    public function date_trigger_string( $trigger, $email ) {
        if ( 'wc_appointments' != $email->type ) {
            return $trigger;
        }

        if ( 'date' == $email->duration ) {
            $trigger = sprintf(
				/* translators: %s: Send datetime */
				__( 'Send on %s' ),
				fue_format_send_datetime( $email )
			);
        }
        return $trigger;
    }

    /**
     * List of available variables
     * @param FUE_Email $email
     */
    public function email_variables_list( $email ) {
        global $woocommerce;

        if ( 'wc_appointments' != $email->type ) {
            return;
        }
        ?>
        <li class="var hideable var_wc_appointments"><strong>{item_name}</strong> <img class="help_tip" title="<?php esc_html_e( 'The name of the purchased item.', 'woocommerce-appointments' ); ?>" src="<?php echo FUE_TEMPLATES_URL; ?>/images/help.png" width="16" height="16" /></li>
        <li class="var hideable var_wc_appointments"><strong>{item_category}</strong> <img class="help_tip" title="<?php esc_html_e( 'The list of categories where the purchased item is under.', 'woocommerce-appointments' ); ?>" src="<?php echo FUE_TEMPLATES_URL; ?>/images/help.png" width="16" height="16" /></li>
        <li class="var hideable var_wc_appointments var_wc_appointments_item_quantity"><strong>{item_quantity}</strong> <img class="help_tip" title="<?php esc_html_e( 'The quantity of the purchased item.', 'woocommerce-appointments' ); ?>" src="<?php echo FUE_TEMPLATES_URL; ?>/images/help.png" width="16" height="16" /></li>
        <li class="var hideable var_wc_appointments"><strong>{appointment_start}</strong> <img class="help_tip" title="<?php esc_html_e( 'The start date of the scheduled product or service', 'woocommerce-appointments' ); ?>" src="<?php echo $woocommerce->plugin_url(); ?>/assets/images/help.png" width="16" height="16" /></li>
        <li class="var hideable var_wc_appointments"><strong>{appointment_end}</strong> <img class="help_tip" title="<?php esc_html_e( 'The end date of the scheduled product or service', 'woocommerce-appointments' ); ?>" src="<?php echo $woocommerce->plugin_url(); ?>/assets/images/help.png" width="16" height="16" /></li>
        <li class="var hideable var_wc_appointments"><strong>{appointment_duration}</strong> <img class="help_tip" title="<?php esc_html_e( 'The duration of the scheduled product or service', 'woocommerce-appointments' ); ?>" src="<?php echo $woocommerce->plugin_url(); ?>/assets/images/help.png" width="16" height="16" /></li>
        <li class="var hideable var_wc_appointments"><strong>{appointment_date}</strong> <img class="help_tip" title="<?php esc_html_e( 'The date of the scheduled product or service', 'woocommerce-appointments' ); ?>" src="<?php echo $woocommerce->plugin_url(); ?>/assets/images/help.png" width="16" height="16" /></li>
        <li class="var hideable var_wc_appointments"><strong>{appointment_time}</strong> <img class="help_tip" title="<?php esc_html_e( 'The time of the scheduled product or service', 'woocommerce-appointments' ); ?>" src="<?php echo $woocommerce->plugin_url(); ?>/assets/images/help.png" width="16" height="16" /></li>
        <li class="var hideable var_wc_appointments"><strong>{appointment_amount}</strong> <img class="help_tip" title="<?php esc_html_e( 'The amount or cost of the scheduled product or service', 'woocommerce-appointments' ); ?>" src="<?php echo $woocommerce->plugin_url(); ?>/assets/images/help.png" width="16" height="16" /></li>
        <li class="var hideable var_wc_appointments"><strong>{appointment_staff}</strong> <img class="help_tip" title="<?php esc_html_e( 'The staff scheduled', 'woocommerce-appointments' ); ?>" src="<?php echo $woocommerce->plugin_url(); ?>/assets/images/help.png" width="16" height="16" /></li>
        <li class="var hideable var_wc_appointments"><strong>{appointment_id}</strong> <img class="help_tip" title="<?php esc_html_e( 'The ID of scheduled product or service', 'woocommerce-appointments' ); ?>" src="<?php echo $woocommerce->plugin_url(); ?>/assets/images/help.png" width="16" height="16" /></li>
        <li class="var hideable var_wc_appointments"><strong>{appointment_cancellation_link}</strong> <img class="help_tip" title="<?php esc_html_e( 'The cancellation link for appointment', 'woocommerce-appointments' ); ?>" src="<?php echo $woocommerce->plugin_url(); ?>/assets/images/help.png" width="16" height="16" /></li>
    <?php
    }

    /**
     * Register subscription variables to be replaced
     *
     * @param FUE_Sending_Email_Variables   $var
     * @param array                 $email_data
     * @param FUE_Email             $email
     * @param object                $queue_item
     */
    public function register_variable_replacements( $var, $email_data, $email, $queue_item ) {
        if ( 'wc_appointments' != $email->type ) {
            return;
        }

        $variables = array(
            'item_category'                 => '',
            'item_name'                     => '',
            'item_quantity'                 => '',
            'appointment_start'             => '',
            'appointment_end'               => '',
            'appointment_duration'          => '',
            'appointment_date'              => '',
            'appointment_time'              => '',
            'appointment_amount'            => '',
            'appointment_staff'             => '',
            'appointment_id'                => '',
            'appointment_cancellation_link' => '',
        );

        // use test data if the test flag is set
        if ( isset( $email_data['test'] ) && $email_data['test'] ) {
            $variables = $this->add_test_variable_replacements( $variables, $email_data, $email );
        } else {
            $variables = $this->add_variable_replacements( $variables, $email_data, $queue_item, $email );
        }

        $var->register( $variables );
    }

    /**
     * Scan through the keys of $variables and apply the replacement if one is found
     * @param array     $variables
     * @param array     $email_data
     * @param object    $queue_item
     * @param FUE_Email $email
     * @return array
     */
    protected function add_variable_replacements( $variables, $email_data, $queue_item, $email ) {
        if ( $queue_item->order_id && $queue_item->product_id ) {
            $item_id = $queue_item->product_id;

            // appointment data
            $meta           = maybe_unserialize( $queue_item->meta );
            $appointment_id = ! empty( $meta['appointment_id'] ) ? $meta['appointment_id'] : 0;

            if ( 0 === $appointment_id ) {
                return $variables;
            }

            /**
             * @var $appointment WC_Appointment
             * @var $appointment_product WC_Product_Appointment
             */
            $appointment = get_wc_appointment( $appointment_id );

            if ( ! $appointment ) {
                return $variables;
            }

            $appointment_product           = $appointment->get_product();
            $appointment_order             = $appointment->get_order();
            $appointment_start             = $appointment->get_start_date( wc_appointments_date_format() . ' ', wc_appointments_time_format() );
            $appointment_end               = $appointment->get_end_date( wc_appointments_date_format() . ' ', wc_appointments_time_format() );
            $appointment_date              = $appointment->get_start_date( wc_appointments_date_format(), '' );
            $appointment_time              = $appointment->get_start_date( '', wc_appointments_time_format() );
            $appointment_duration          = $appointment->get_duration();
            $appointment_amount            = wc_price( $appointment->cost );
            $appointment_staff             = ( $staff = $appointment->get_staff_members( true ) ) ? $staff : '';
            $appointment_id                = $appointment->get_id();
            $appointment_cancellation_link = esc_url( $appointment->get_cancel_url() );

            $used_cats = [];
            $item_cats = '';

            $categories = get_the_terms( $appointment->product_id, 'product_cat' );

            if ( is_array( $categories ) ) {
                $item_cats .= '<ul>';
				foreach ( $categories as $category ) {
					if ( ! in_array( $category->term_id, $used_cats ) ) {
						$item_cats  .= apply_filters(
							'fue_email_cat_list',
							'<li>' . $category->name . '</li>',
							$queue_item->id,
							$categories
						);
						$used_cats[] = $category->term_id;
					}
				}
                $item_cats .= '</ul>';
			}

            $item_url = FUE_Sending_Mailer::create_email_url(
                $queue_item->id,
                $queue_item->email_id,
                $email_data['user_id'],
                $email_data['email_to'],
                get_permalink( $queue_item->product_id )
            );

            $variables['item_name']                     = FUE_Addon_Woocommerce::get_product_name( $appointment_product );
            $variables['item_url']                      = $item_url;
            $variables['item_category']                 = $item_cats;
            $variables['appointment_start']             = $appointment_start;
            $variables['appointment_end']               = $appointment_end;
            $variables['appointment_date']              = $appointment_date;
            $variables['appointment_time']              = $appointment_time;
            $variables['appointment_duration']          = $appointment_duration;
            $variables['appointment_amount']            = $appointment_amount;
            $variables['appointment_staff']             = $appointment_staff;
            $variables['appointment_id']                = $appointment_id;
            $variables['appointment_cancellation_link'] = $appointment_cancellation_link;
            $variables['order_billing_address']         = '';
            $variables['order_shipping_address']        = '';
            $variables['item_quantity']                 = 1;

            if ( $appointment_order ) {
    			$variables['order_billing_phone']    = WC_FUE_Compatibility::get_order_prop( $appointment_order, 'billing_phone' );
    			$variables['order_shipping_phone']   = $appointment_order->get_shipping_phone() ?? '';
    			$variables['order_date']             = date_i18n( wc_appointments_date_format(), strtotime( WC_FUE_Compatibility::get_order_prop( $appointment_order, 'order_date' ) ) );
    			$variables['order_datetime']         = date_i18n( wc_appointments_date_format() . ' ' . wc_appointments_time_format(), strtotime( WC_FUE_Compatibility::get_order_prop( $appointment_order, 'order_date' ) ) );
    			$variables['order_billing_address']  = $appointment_order->get_formatted_billing_address();
    			$variables['order_shipping_address'] = $appointment_order->get_formatted_shipping_address();
    			$variables['order_number']           = $appointment_order->get_order_number();
    			$variables['dollars_spent_order']    = FUE_Addon_Woocommerce::format_price( $appointment_order->get_total() );
    			$variables['order_subtotal']         = FUE_Addon_Woocommerce::format_price( $appointment_order->get_subtotal() );
    			$variables['order_tax']              = FUE_Addon_Woocommerce::format_price( $appointment_order->get_total_tax() );
    			$variables['order_pay_method']       = WC_FUE_Compatibility::get_order_prop( $appointment_order, 'payment_method_title' );
    			$variables['order_pay_url']          = $appointment_order->get_checkout_payment_url();

                foreach ( $appointment_order->get_items() as $item_id => $item ) {
                    $product_id = ! empty( $item['product_id'] ) ? $item['product_id'] : $item['id'];

                    if ( $appointment_product->get_id() == $product_id ) {
                        $variables['item_quantity'] = $item['qty'];
                        break;
                    }
                }
            }
        }

        return apply_filters( 'fue_appointments_variable_replacements', $variables, $appointment, $email );
    }

    /**
     * Add variable replacements for test emails
     *
     * @param array     $variables
     * @param array     $email_data
     * @param FUE_Email $email
     *
     * @return array
     */
    protected function add_test_variable_replacements( $variables, $email_data, $email ) {
        $variables['item_name']                     = 'Appointments product';
        $variables['item_url']                      = '#url';
        $variables['item_category']                 = 'Appointments category';
        $variables['item_quantity']                 = 1;
        $variables['appointment_start']             = date( wc_appointments_date_format() . ' ' . wc_appointments_time_format(), current_time( 'timestamp' ) + 86400 );
        $variables['appointment_end']               = date( wc_appointments_date_format() . ' ' . wc_appointments_time_format(), current_time( 'timestamp' ) + ( 86400 * 2 ) );
        $variables['appointment_duration']          = '3 Hours';
        $variables['appointment_date']              = date( wc_appointments_date_format(), current_time( 'timestamp' ) + 86400 );
        $variables['appointment_time']              = date( wc_appointments_time_format(), current_time( 'timestamp' ) + 86400 );
        $variables['appointment_amount']            = wc_price( 77 );
        $variables['appointment_staff']             = 'Staff Name';
        $variables['appointment_id']                = 1;
        $variables['appointment_cancellation_link'] = home_url();
        $variables['order_billing_address']         = '77 North Beach Dr., Miami, FL 35122';
        $variables['order_shipping_address']        = '77 North Beach Dr., Miami, FL 35122';

        return $variables;
    }

    /**
	 * Queue emails after an appointment has been created.
	 *
	 * Since this excludes `in-cart` status when appointment is created initially,
	 * this gets triggered only when appointment is created manually.
	 *
	 * @since 1.0.0
	 * @version 4.5.2
	 *
	 * @param int $appointment_id Appointment ID.
	 */
	public function appointment_created( $appointment_id ) {
		$appointment = get_wc_appointment( $appointment_id );

		// Stop FUE from scheduling blank emails after adding an appointment product
		// to the cart.
		if ( 'in-cart' === $appointment->status ) {
			return;
		}

		$this->create_email_order( $appointment_id, array( 'appointment_created' ) );
	}

    /**
	 * Queue emails after an appointment has been created.
	 *
	 * Created appointments from checkout flow always start with `in-cart` status
	 * which then transition to either `unpaid` or `pending-confirmation`.
	 *
	 * @since 4.5.2
	 * @version 4.5.2
	 *
	 * @param int $appointment_id Appointment ID.
	 */
	public function appointment_created_from_cart( $appointment_id ) {
		$this->create_email_order( $appointment_id, array( 'appointment_created' ) );
	}

    /**
     * Fires after an appointment's status has been updated
     * @param $appointment_id
     */
    public function appointment_status_updated( $appointment_id ) {
        global $wpdb;

        // get the status directly from wp_posts to make sure that we have the latest
        $status = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT post_status FROM {$wpdb->posts} WHERE ID = %d",
                $appointment_id
            )
        );

        $triggers = array( 'appointment_status_' . $status );

        if ( 'paid' == $status || 'confirmed' == $status ) {
            $triggers[] = 'before_appointment_event';
            $triggers[] = 'after_appointment_event';
        }

		// Filter the triggers.
		$triggers = apply_filters(
			'fue_appointments_status_updated_triggers',
			$triggers,
			$status,
			$appointment_id
		);

        $this->create_email_order( $appointment_id, $triggers );

        // update the _last_status meta
        update_post_meta( $appointment_id, '_last_status', $status );

    }

    /**
     * Triggered from the save_post hook, we have to manually trigger
     * status update hooks because WC Appointments does not broadcast
     * status updates when an appointment is updated using the admin screen
     * @param $post_id
     */
    public function maybe_trigger_status_update( $post_id ) {
        if ( ! empty( $_POST['post_type'] ) && 'wc_appointment' != $_POST['post_type'] ) {
            return $post_id;
        }

        if ( empty( $_POST['_appointment_status'] ) ) {
            return $post_id;
        }

        // remove hook to avoid inifinite loop
        remove_action( 'save_post', array( $this, 'maybe_trigger_status_update' ), 11 );

        $appointment_status = fue_clean( wp_unslash( $_POST['_appointment_status'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Handled before action.
        $last_status        = get_post_meta( $post_id, '_last_status', true );

        if ( $last_status != $appointment_status ) {
            $this->appointment_status_updated( $post_id );
        }

        return $post_id;
    }

    /**
	 * Override `fue_get_emails` filter when removing emails from the queue.
	 *
	 * This only happens if **Remove on status change** option is enabled.
	 *
	 * @since 4.5.2
	 * @version 4.5.2
	 *
	 * @param array  $filter     Filter arg for `fue_get_emails`.
	 * @param int    $order_id   Order ID.
	 * @param string $old_status Old order status.
	 * @param string $new_status New order status.
	 */
	public function maybe_unqueue_emails_on_status_change( $filter, $order_id, $old_status, $new_status ) {
		// Order statuses criteria for Appointment follow-up type when ever order status is updated.
		if ( ! in_array( $old_status, array( 'processing', 'completed' ) ) ) {
			return $filter;
		}
		if ( ! in_array( $new_status, array( 'on-hold', 'pending', 'cancelled' ) ) ) {
			return $filter;
		}

		if ( ! class_exists( 'WC_Appointment_Data_Store' ) ) {
			return $filter;
		}
		if ( ! is_callable( array( 'WC_Appointment_Data_Store', 'get_appointment_ids_from_order_id' ) ) ) {
			return $filter;
		}

		// Bail out if no appointments in the order.
		$appointment_ids = WC_Appointment_Data_Store::get_appointment_ids_from_order_id( $order_id );
		if ( empty( $appointment_ids ) ) {
			return $filter;
		}

		// Triggers that fit for unqueue emails when ever order status is updated.
		$triggers = array(
			'appointment_created',
			'appointment_status_confirmed',
			'appointment_status_paid',
			'appointment_status_complete',
			'before_appointment_event',
			'after_appointment_event',
		);

		$current_triggers = ! empty( $filter['meta_query'][0]['value'] )
			? $filter['meta_query'][0]['value']
			: [];
		$current_triggers = ! is_array( $current_triggers )
			? array( $current_triggers )
			: $current_triggers;

		$filter['meta_query'][0]['value']   = array_merge( $current_triggers, $triggers );
		$filter['meta_query'][0]['compare'] = 'IN';

		return $filter;
	}

    /**
	 * Add last status selector to the Triggers tab
	 *
	 * @param FUE_Email $email
	 */
	public function register_trigger_fields( $email ) {
		if ( 'wc_appointments' == $email->type ) {
			// load saved status.
			$appointments_last_status = get_post_meta( $email->id, '_appointments_last_status', true );

			include 'templates/email-form/appointments/triggers.php';
		}
	}

    /**
	 * Saving meta not working by default.
	 *
	 * @since 4.9.5
	 * @param int   $email_id
	 */
	public function fix_save_type_meta( $email_id, $args ) {
		$email = new FUE_Email( $email_id );

        if ( 'wc_appointments' == $email->type ) {
            if ( isset( $_POST['meta']['appointments_last_status'] ) && ! empty( $_POST['meta']['appointments_last_status'] ) ) {
				$appointments_last_status = sanitize_text_field( wp_unslash( $_POST['meta']['appointments_last_status'] ) );
                update_post_meta( $email_id, '_appointments_last_status', $appointments_last_status );
			} elseif ( isset( $_POST['meta']['appointments_last_status'] ) && empty( $_POST['meta']['appointments_last_status'] ) ) {
				delete_post_meta( $email_id, '_appointments_last_status' );
			}
        }
	}

    /**
     * Add appointments to the email types that support order importing
     *
     * @param array $types
     * @return array
     */
    public function declare_import_support( $types ) {
        $types[] = 'wc_appointments';
        return $types;
    }

    /**
     * Get orders that match the $email's criteria
     * @param array     $orders Matching Order IDs
     * @param FUE_Email $email
     * @return array
     */
    public function get_orders_for_email( $orders, $email ) {
        $wpdb = Follow_Up_Emails::instance()->wpdb;

        if ( 'wc_appointments' != $email->type ) {
            return $orders;
        }

        $valid_statuses           = array( 'confirmed', 'paid', 'complete' );
		$valid_statuses           = apply_filters(
			'fue_appointments_orders_for_email_valid_statuses',
			array(
				'confirmed',
				'paid',
				'complete',
			)
		);
        $appointments_last_status = get_post_meta( $email->id, '_appointments_last_status', true );

        // add appointment statuses
        $status_triggers = [];
        foreach ( get_wc_appointment_statuses( 'all' ) as $status ) {
            $status_triggers[ $status ] = 'appointment_status_' . $status;
        }

        $status = array_search( $email->trigger, $status_triggers );

        if ( $status ) {
            $args = array(
                'nopaging'    => true,
                'post_type'   => 'wc_appointment',
                'post_status' => $status,
                'fields'      => 'ids',
            );

            $posts_query       = new WP_Query();
            $appointment_posts = $posts_query->query( $args );

            foreach ( $appointment_posts as $appointment_id ) {
                $last_status = get_post_meta( $appointment_id, '_last_status', true );

                if ( $appointments_last_status && $appointments_last_status != $last_status ) {
                    continue;
                }

                $orders[] = $appointment_id;
            }
        } elseif ( 'before_appointment_event' === $email->trigger ) {
            $now             = date( 'Ymd' ) . '000000';
            $appointment_ids = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT post_id
                    FROM {$wpdb->postmeta}
                    WHERE meta_key = '_appointment_start'
                    AND meta_value > %s",
                    $now
                )
            );

            if ( ! empty( $appointment_ids ) ) {
                foreach ( $appointment_ids as $appointment_id ) {
                    if ( ! in_array( get_post_status( $appointment_id ), $valid_statuses ) ) {
                        continue;
                    }
                    $orders[] = $appointment_id;
                }
            }
        } elseif ( 'after_appointment_event' === $email->trigger ) {
            $now             = date( 'Ymd' ) . '000000';
            $appointment_ids = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT post_id
                    FROM {$wpdb->postmeta}
                    WHERE meta_key = '_appointment_end'
                    AND meta_value < %s",
                    $now
                )
            );

            if ( ! empty( $appointment_ids ) ) {
                foreach ( $appointment_ids as $appointment_id ) {
                    if ( ! in_array( get_post_status( $appointment_id ), $valid_statuses ) ) {
                        continue;
                    }
                    $orders[] = $appointment_id;
                }
            }
        }

        return array( $email->id => $orders );
    }

    /**
     * Run filters on appointments to remove invalid orders
     *
     * @param array $data
     * @param FUE_Email $email
     * @return array
     */
    public function filter_orders_for_email( $data, $email ) {
        if ( 'wc_appointments' == $email->type ) {
            foreach ( $data as $email_id => $orders ) {
                foreach ( $orders as $idx => $appointment_id ) {
                    $appointment = get_wc_appointment( $appointment_id );
                    $order       = $appointment->get_order();

                    if ( 'wc_appointment' != $appointment->post->post_type ) {
                        unset( $data[ $email_id ][ $idx ] );
                        continue;
                    }

                    if ( $this->is_category_excluded( $appointment, $email ) ) {
                        unset( $data[ $email_id ][ $idx ] );
                        continue;
                    }

                    // A appointment can have no order linked to it
                    if ( $order ) {
                        $customer = fue_get_customer_from_order( $order );
                        if ( Follow_Up_Emails::instance()->fue_wc->wc_scheduler->exclude_customer_based_on_purchase_history( $customer, $email ) ) {
                            unset( $data[ $email_id ][ $idx ] );
                            continue;
                        }
                    }

                    // limit to selected product or category
                    if ( 'products' == $email->meta['storewide_type'] && $email->product_id > 0 && $email->product_id != $appointment->product_id ) {
                        unset( $data[ $email_id ][ $idx ] );
                        continue;
                    } elseif ( 'categories' == $email->meta['storewide_type'] && $email->category_id > 0 ) {
                        $categories = wp_get_object_terms( $appointment->product_id, 'product_cat', array( 'fields' => 'ids' ) );

                        if ( is_wp_error( $categories ) ) {
                            unset( $data[ $email_id ][ $idx ] );
                            continue;
                        }

                        if ( empty( $categories ) || ! in_array( $email->category_id, $categories ) ) {
                            unset( $data[ $email_id ][ $idx ] );
                            continue;
                        }
                    }

                    // look for a possible duplicate item in the queue
                    $dupes = Follow_Up_Emails::instance()->scheduler->get_items(
                        array(
                            'email_id'   => $email->id,
                            'is_sent'    => 0,
                            'order_id'   => $appointment->order_id,
                            'product_id' => $appointment->product_id,
                        )
                    );

                    if ( count( $dupes ) > 0 ) {
                        foreach ( $dupes as $dupe_item ) {
                            if ( ! empty( $dupe_item->meta['appointment_id'] ) && $dupe_item->meta['appointment_id'] == $appointment_id ) {
                                // found exact appointment match
                                unset( $data[ $email_id ][ $idx ] );
                                continue 2;
                            }
                        }
                    }
                }
            }
        }

        return $data;
    }

    /**
     * If the post pointing to the order ID is of type 'wc_appointment', use the
     * order_id as the appointment_id and fill in the proper order ID.
     *
     * @param array     $insert
     * @param FUE_Email $email
     * @return array
     */
    public static function add_appointment_id_to_meta( $insert, $email ) {
        if ( empty( $insert['order_id'] ) ) {
            return $insert;
        }

        $post = get_post( $insert['order_id'] );

        if ( 'wc_appointment' == $post->post_type ) {
            $insert['meta']['appointment_id'] = $insert['order_id'];
            $insert['order_id']               = $post->post_parent;
        }

        return $insert;
    }

    /**
     * Change the send date of the email for 'before_appointment_event' and 'after_appointment_event' triggers
     * @param array $insert
     * @param FUE_Email $email
     * @return array
     */
    public function modify_insert_send_date( $insert, $email ) {
        if ( 'wc_appointments' != $email->type ) {
            return $insert;
        }

        $appointment_id = $insert['meta']['appointment_id'];
        $appointment    = get_wc_appointment( $appointment_id );

        if ( 'before_appointment_event' == $email->trigger ) {
            $start = $appointment->get_start();
            $time  = FUE_Sending_Scheduler::get_time_to_add( $email->interval_num, $email->interval_duration );

            $insert['send_on'] = $start - $time;
        } elseif ( 'after_appointment_event' == $email->trigger ) {
            $start = $appointment->get_end();
            $time  = FUE_Sending_Scheduler::get_time_to_add( $email->interval_num, $email->interval_duration );

            $insert['send_on'] = $start + $time;
        }

        return $insert;
    }

    /**
     * Send emails that matches the provided triggers to the queue
     * @param int $appointment_id
     * @param array $triggers
     */
    private function create_email_order( $appointment_id, $triggers = [] ) {
        /**
         * @var $appointment WC_Appointment
         * @var $order WC_Order
         */
        $appointment = get_wc_appointment( $appointment_id );
        $last_status = get_post_meta( $appointment_id, '_last_status', true );
        $order       = WC_FUE_Compatibility::wc_get_order( $appointment->order_id );
        $emails      = fue_get_emails(
            'any',
            '',
            array(
                'meta_query' => array(
                    array(
                        'key'     => '_interval_type',
                        'value'   => $triggers,
                        'compare' => 'IN',
                    ),
                ),
            )
        );

        foreach ( $emails as $email ) {

            if ( 'fue-active' != $email->status ) {
                continue;
            }

            $meta = maybe_unserialize( $email->meta );

            $appointments_last_status = get_post_meta( $email->id, '_appointments_last_status', true );

            #error_log( var_export( $meta, true ) );
            #error_log( var_export( $last_status, true ) );

            if ( $appointments_last_status && $appointments_last_status != $last_status ) {
                continue;
            }

            #error_log( var_export( 'what?', true ) );

            if ( $this->is_category_excluded( $appointment, $email ) ) {
                continue;
            }

            // A appointment can have no order linked to it
            if ( $order ) {
                $customer = fue_get_customer_from_order( $order );
                if ( Follow_Up_Emails::instance()->fue_wc->wc_scheduler->exclude_customer_based_on_purchase_history( $customer, $email ) ) {
                    continue;
                }
            }

            // limit to selected product or category
            if ( 'products' == $meta['storewide_type'] && $email->product_id > 0 && $email->product_id != $appointment->product_id ) {
                continue;
            } elseif ( 'categories' == $meta['storewide_type'] && $email->category_id > 0 ) {
                $categories = wp_get_object_terms(
					$appointment->product_id,
					'product_cat',
					array(
						'fields' => 'ids',
					)
				);

                if ( is_wp_error( $categories ) ) {
                    continue;
                }

                if ( empty( $categories ) || ! in_array( $email->category_id, $categories ) ) {
                    continue;
                }
            }

            // look for a possible duplicate item in the queue
            $dupes = Follow_Up_Emails::instance()->scheduler->get_items(
                array(
                    'email_id'   => $email->id,
                    'is_sent'    => 0,
                    'order_id'   => $appointment->order_id,
                    'product_id' => $appointment->product_id,
                )
            );

            if ( count( $dupes ) > 0 ) {
                foreach ( $dupes as $dupe_item ) {
                    if ( ! empty( $dupe_item->meta['appointment_id'] ) && $dupe_item->meta['appointment_id'] == $appointment_id ) {
                        // found exact appointment match
                        continue 2;
                    }
                }
            }

            if ( 'date' == $email->duration ) {
                $email->interval_type = 'date';
                $send_on              = $email->get_send_timestamp();
            } else {
                if ( 'before_appointment_event' == $email->interval_type ) {
                    $start = strtotime( get_post_meta( $appointment_id, '_appointment_start', true ) );
                    $time  = FUE_Sending_Scheduler::get_time_to_add( $email->interval_num, $email->interval_duration );

                    $send_on = $start - $time;
                } elseif ( 'after_appointment_event' == $email->interval_type ) {
                    $start = strtotime( get_post_meta( $appointment_id, '_appointment_end', true ) );
                    $time  = FUE_Sending_Scheduler::get_time_to_add( $email->interval_num, $email->interval_duration );

                    $send_on = $start + $time;
                } else {
                    $send_on = $email->get_send_timestamp();
                }
            }

            $insert = array(
                'send_on'    => $send_on,
                'email_id'   => $email->id,
                'product_id' => $appointment->product_id,
                'order_id'   => $appointment->order_id,
                'meta'       => array( 'appointment_id' => $appointment_id ),
            );

            // Assume the user id is Booking's customer ID.
			$user_id = $appointment->customer_id;

            if ( $order && empty( $user_id ) ) {
				$user_id = WC_FUE_Compatibility::get_order_user_id( $order );

				// Assume user's email is order's billing email (if the user_id exists it will use the customer's email instead).
				$insert['user_email'] = WC_FUE_Compatibility::get_order_prop( $order, 'billing_email' );
			}

            if ( $user_id ) {
				$user                 = new WP_User( $user_id );
				$insert['user_id']    = $user_id;
				$insert['user_email'] = empty( $insert['user_email'] ) ? $user->user_email : $insert['user_email'];
			}

            // Skip queueing e-mails that have no recipient.
			if ( empty( $insert['user_email'] ) ) {
				continue;
			}

            // Remove the nonce to avoid infinite loop because doing a
            // remove_action on WC_Appointments_Details_Meta_Box doesnt work
            unset( $_POST['wc_appointments_details_meta_box_nonce'] );

            if ( ! is_wp_error( FUE_Sending_Scheduler::queue_email( $insert, $email ) ) ) {
                // Tell FUE that an email order has been created
                // to stop it from sending storewide emails
                if ( ! defined( 'FUE_ORDER_CREATED' ) ) {
					define( 'FUE_ORDER_CREATED', true );
                }

                if ( $order ) {

                    if ( empty( $insert['send_on'] ) ) {
                        $insert['send_on'] = $email->get_send_timestamp();
                    }

                    $email_trigger = apply_filters( 'fue_interval_str', $email->get_trigger_string(), $email );
                    $send_date     = date( wc_appointments_date_format() . ' ' . wc_appointments_time_format(), $insert['send_on'] );

                    $note = sprintf(
                        __( 'Email queued: %s scheduled on %s<br/>Trigger: %s', 'woocommerce-appointments' ),
                        $email->name,
                        $send_date,
                        $email_trigger
                    );

                    $order->add_order_note( $note );
                }
            }
        }
    }

    /**
     * Checks if $appointment is under a category that is excluded in $email
     *
     * @param WC_Appointment    $appointment
     * @param FUE_Email     $email
     * @return bool
     */
    private function is_category_excluded( $appointment, $email ) {
        $excluded = false;

        $categories = wp_get_object_terms( $appointment->product_id, 'product_cat' );

        if ( is_wp_error( $categories ) ) {
            return false;
        }

        $excludes = ( isset( $email->meta['excluded_categories'] ) ) ? $email->meta['excluded_categories'] : [];

        if ( ! is_array( $excludes ) ) {
            $excludes = [];
        }

        if ( count( $excludes ) > 0 ) {
            foreach ( $categories as $category ) {
                if ( in_array( $category->term_id, $excludes ) ) {
                    $excluded = true;
                    break;
                }
            }
        }

        return apply_filters( 'fue_appointments_category_excluded', $excluded, $appointment, $email );
    }

    private function duration_to_string( $duration, $unit ) {
        $unit = rtrim( $unit, 's' );

        return ( 1 == $duration ) ? $duration . ' ' . $unit : $duration . ' ' . $unit . 's';
    }

}

$GLOBALS['wc_appointments_integration_wcfue'] = new WC_Appointments_Integration_WCFUE();
