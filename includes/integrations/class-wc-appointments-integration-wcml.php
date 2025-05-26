<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

use WCML\Compatibility\WcProductAddons\MulticurrencyHooks;
use function WCML\functions\getWooCommerceWpml;
use WCML\PointerUi\Factory;

/**
 * WooCommerce Multilingual (WPML) integration class.
 *
 * Last compatibility check: WPML Multilingual CMS 4.6.15
 * Last compatibility check: WooCommerce Multilingual 5.4.0
 */
class WC_Appointments_Integration_WCML {

    /**
	 * @var WPML_Element_Translation_Package
	 */
	private $tp;

	/**
	 * @var SitePress
	 */
	private $sitepress;

	/**
	 * @var WooCommerce
	 */
	private $woocommerce;

	/**
	 * @var woocommerce_wpml
	 */
	private $woocommerce_wpml;

	/**
	 * @var WPML_Post_Translation
	 */
	private $wpml_post_translations;

	/**
	 * @var WCML_Product_Addons
	 */
	private $product_addons;

	/**
	 * @var MulticurrencyHooks
	 */
	private $product_addons_multicurrency;

	/**
	 * @var wpdb
	 */
	private $wpdb;

	/**
	 * WCML_Appointments constructor.
	 * @param SitePress                        $sitepress
	 * @param WooCommerce                      $woocommerce
	 * @param woocommerce_wpml                 $woocommerce_wpml
	 * @param wpdb                             $wpdb
	 * @param WPML_Element_Translation_Package $tp
	 * @param WPML_Post_Translation            $wpml_post_translations
	 */
	public function __construct( SitePress $sitepress, WooCommerce $woocommerce, woocommerce_wpml $woocommerce_wpml, wpdb $wpdb, WPML_Element_Translation_Package $tp, WPML_Post_Translation $wpml_post_translations ) {
        $this->sitepress              = $sitepress;
		$this->woocommerce            = $woocommerce;
		$this->woocommerce_wpml       = $woocommerce_wpml;
		$this->wpdb                   = $wpdb;
		$this->tp                     = $tp;
		$this->wpml_post_translations = $wpml_post_translations;

		// Product Add-ons.
		if ( ! class_exists( 'Product_Addon_Display' ) && class_exists( 'WCML_Product_Addons' ) ) {
			if ( version_compare( WCML_VERSION, '5.4.0', '>=' ) ) {
				$pointerFactory = new WCML\PointerUi\Factory();
				$this->product_addons = new WCML_Product_Addons( $this->sitepress, $pointerFactory );
			} else {
				$this->product_addons = new WCML_Product_Addons( $this->sitepress );
			}

			if ( is_callable( [ $this->product_addons, 'add_hooks' ] ) ) {
				$this->product_addons->add_hooks();
			}

			// Do not run when multicurrency is not enabled.
			if ( wcml_is_multi_currency_on() ) {
				// Uses class from "WCML\Compatibility\WcProductAddons\MulticurrencyHooks".
				$this->product_addons_multicurrency = new MulticurrencyHooks( getWooCommerceWpml() );

				if ( is_callable( [ $this->product_addons_multicurrency, 'add_hooks' ] ) ) {
					$this->product_addons_multicurrency->add_hooks();
				}
			}
		}

        $this->add_hooks();
	}

	public function add_hooks() {
		add_action( 'woocommerce_appointments_after_appointment_pricing_base_cost', [ $this, 'wcml_price_field_after_appointment_pricing_base_cost' ], 10, 2 );
		add_action( 'woocommerce_appointments_after_appointment_pricing_cost', [ $this, 'wcml_price_field_after_appointment_pricing_cost' ], 10, 2 );
		add_action( 'woocommerce_appointments_after_staff_cost', [ $this, 'wcml_price_field_after_staff_cost' ], 10, 2 );
		add_action( 'woocommerce_appointments_after_staff_qty', [ $this, 'wcml_price_field_after_staff_qty' ], 10, 2 );
		add_action( 'woocommerce_appointments_after_appointments_pricing', [ $this, 'after_appointments_pricing' ] );

		add_action( 'save_post', [ $this, 'save_appointment_action_handler' ], 110 );
		add_action( 'wcml_before_sync_product', [ $this, 'sync_product_data' ], 10, 2 );
		add_action( 'wpml_translation_job_saved', [ $this, 'save_appointment_data_to_translation' ], 10, 3 );

		add_filter( 'woocommerce_appointments_process_pricing_rules_cost', [ $this, 'wc_appointments_process_pricing_rules_cost' ], 10, 3 );
		add_filter( 'woocommerce_appointments_process_pricing_rules_base_cost', [ $this, 'wc_appointments_process_pricing_rules_base_cost' ], 10, 3 );
		add_filter( 'woocommerce_appointments_process_pricing_rules_override_slot', [ $this, 'wc_appointments_process_pricing_rules_override_slot_cost' ], 10, 3 );

		// Multi-currency.
		add_filter( 'wcml_multi_currency_ajax_actions', [ $this, 'wcml_multi_currency_is_ajax' ] );
		add_filter( 'wcml_load_multi_currency_in_ajax', [ $this, 'wcml_load_multi_currency_in_admin' ] );
		add_action( 'woocommerce_appointments_before_add_appointment_page', [ $this, 'appointment_currency_dropdown' ] );
		add_action( 'init', [ $this, 'set_appointment_currency' ] );
		// add_filter( 'wcml_cart_contents_not_changed', [ $this, 'filter_bundled_product_in_cart_contents' ], 10, 3 );
		add_action( 'wp_ajax_wcml_appointment_set_currency', [ $this, 'set_appointment_currency_ajax' ] );
		add_action( 'woocommerce_appointments_add_appointment_page_add_order_item', [ $this, 'set_order_currency_on_add_appointment_page' ] );
		add_filter( 'woocommerce_currency_symbol', [ $this, 'filter_appointment_currency_symbol' ] );
		add_filter( 'get_appointment_products_args', [ $this, 'filter_get_appointment_products_args' ] );
		add_filter( 'wcml_filter_currency_position', [ $this, 'add_appointment_page_client_currency' ] );
		add_filter( 'wcml_client_currency', [ $this, 'add_appointment_page_client_currency' ] );

		add_filter( 'wcml_check_is_single', [ $this, 'show_custom_slots_for_staff' ], 10, 3 );
		add_filter( 'wcml_do_not_display_custom_fields_for_product', [ $this, 'replace_tm_editor_custom_fields_with_own_sections' ] );
		add_filter( 'wcml_not_display_single_fields_to_translate', [ $this, 'remove_single_custom_fields_to_translate' ] );
		add_filter( 'wcml_product_content_label', [ $this, 'product_content_staff_label' ], 10, 2 );
		add_action( 'wcml_update_extra_fields', [ $this, 'wcml_products_tab_sync_staff_and_availabilities' ], 10, 4 );

		foreach ( get_wc_appointment_statuses( 'all' ) as $status ) {
			add_action( 'woocommerce_appointment_' . $status, [ $this, 'translate_transactional_appointment_email_texts' ] ); #translate emails
		}

		// Email translation.
		add_filter( 'wcml_emails_options_to_translate', [ $this, 'emails_options_to_translate' ] );
		add_filter( 'wcml_emails_text_keys_to_translate', [ $this, 'emails_text_keys_to_translate' ] );
		add_filter( 'woocommerce_email_get_option', [ $this, 'translate_emails_text_strings' ], 10, 4 );

		add_action( 'woocommerce_appointment_pending-confirmation_to_cancelled_notification', [ $this, 'translate_appointment_cancelled_email_texts' ], 9 );
		add_action( 'woocommerce_appointment_confirmed_to_cancelled_notification', [ $this, 'translate_appointment_cancelled_email_texts' ], 9 );
		add_action( 'woocommerce_appointment_paid_to_cancelled_notification', [ $this, 'translate_appointment_cancelled_email_texts' ], 9 );
		add_action( 'woocommerce_appointment_pending-confirmation_to_cancelled_notification', [ $this, 'translate_appointment_cancelled_admin_email_texts' ], 9 );
		add_action( 'woocommerce_appointment_confirmed_to_cancelled_notification', [ $this, 'translate_appointment_cancelled_admin_email_texts' ], 9 );
		add_action( 'woocommerce_appointment_paid_to_cancelled_notification', [ $this, 'translate_appointment_cancelled_admin_email_texts' ], 9 );

		add_action( 'woocommerce_admin_new_appointment_notification', [ $this, 'translate_new_appointment_email_texts' ], 9 );
		add_action( 'wc-appointment-confirmed', [ $this, 'translate_appointment_confirmed_email_texts' ], 9 );
		add_action( 'wc-appointment-reminder', [ $this, 'translate_appointment_reminder_email_texts' ], 9 );
		add_action( 'wc-appointment-follow-up', [ $this, 'translate_appointment_follow_up_email_texts' ], 9 );

		add_filter( 'wcml_email_language', [ $this, 'appointment_email_language' ] );

		add_filter( 'woocommerce_appointments_in_date_range_query_args', [ $this, 'appointments_date_range_query_args' ] );
		add_filter( 'woocommerce_appointments_for_objects_query_args', [ $this, 'appointments_date_range_query_args' ] );

		if ( is_admin() ) {
            add_filter( 'wpml_tm_translation_job_data', [ $this, 'append_staff_to_translation_package' ], 10, 2 );

            // lock fields on translations pages
            add_filter( 'wcml_js_lock_fields_ids', [ $this, 'wcml_js_lock_fields_ids' ] );

			// Allow filtering staff by language
			add_filter( 'get_appointment_staff_args', [ $this, 'filter_get_appointment_staff_args' ] );

			add_filter( 'get_translatable_documents_all', [ $this, 'filter_translatable_documents' ] );

			add_filter( 'pre_wpml_is_translated_post_type', [ $this, 'filter_is_translated_post_type' ] );
		}

		#if ( ! is_admin() || isset( $_POST['action'] ) && 'wc_appointments_calculate_costs' === $_POST['action'] ) {
			add_filter( 'get_post_metadata', [ $this, 'filter_wc_appointment_cost' ], 10, 4 );
		#}

		add_filter( 'wpml_language_filter_extra_conditions_snippet', [ $this, 'extra_conditions_to_filter_appointments' ] );

		add_filter( 'wpml_tm_dashboard_translatable_types', [ $this, 'hide_appointments_type_on_tm_dashboard' ] );

		add_filter( 'wc_apointments_check_appointment_product', [ $this, 'filter_check_appointment_product' ], 1, 3 );

	}

	public function save_appointment_action_handler( $appointment_id ) {

		$this->maybe_set_appointment_language( $appointment_id );

		$this->save_custom_costs( $appointment_id );
	}

	public function wcml_price_field_after_appointment_pricing_base_cost( $pricing, $post_id ) {

		$this->echo_wcml_price_field( $post_id, 'wcml_wc_appointment_pricing_base_cost', $pricing );

	}

	public function wcml_price_field_after_appointment_pricing_cost( $pricing, $post_id ) {

		$this->echo_wcml_price_field( $post_id, 'wcml_wc_appointment_pricing_cost', $pricing );

	}

	public function wcml_price_field_after_staff_cost( $staff_id, $post_id ) {

		$this->echo_wcml_price_field( $post_id, 'wcml_wc_appointment_staff_cost', false, true, $staff_id );

	}

	public function wcml_price_field_after_staff_qty( $staff_id, $post_id ) {

		$this->echo_wcml_price_field( $post_id, 'wcml_wc_appointment_staff_qty', false, true, $staff_id );

	}

	public function echo_wcml_price_field( $post_id, $field, $pricing = false, $check = true, $staff_id = false ) {

		if ( ( ! $check || $this->woocommerce_wpml->products->is_original_product( $post_id ) )
		    && WCML_MULTI_CURRENCIES_INDEPENDENT === $this->woocommerce_wpml->settings['enable_multi_currency']
		) {

			$currencies = $this->woocommerce_wpml->multi_currency->get_currencies();

			$wc_currencies = get_woocommerce_currencies();

			if ( ! function_exists( 'woocommerce_wp_text_input' ) ) {
				include_once dirname( WC_PLUGIN_FILE ) . '/includes/admin/wc-meta-box-functions.php';
			}

			echo '<div class="wcml_custom_cost_field" >';

			foreach ( $currencies as $currency_code => $currency ) {

				switch ( $field ) {

					case 'wcml_wc_appointment_pricing_base_cost':
						if ( isset( $pricing[ 'base_cost_' . $currency_code ] ) ) {
							$value = $pricing[ 'base_cost_' . $currency_code ];
						} else {
							$value = '';
						}

						echo '<div class="wcml_appointments_range_slot" >';
						echo '<label>' . wp_kses_post( get_woocommerce_currency_symbol( $currency_code ) ) . '</label>';
						echo '<input type="number" step="0.01" name="wcml_wc_appointment_pricing_base_cost[' . esc_html( $currency_code ) . '][]" class="wcml_appointments_custom_price" value="' . esc_html( $value ) . '" placeholder="0" />';
						echo '</div>';
						break;

					case 'wcml_wc_appointment_pricing_cost':
						if ( isset( $pricing[ 'cost_' . $currency_code ] ) ) {
							$value = $pricing[ 'cost_' . $currency_code ];
						} else {
							$value = '';
						}

						echo '<div class="wcml_appointments_range_slot" >';
						echo '<label>' . wp_kses_post( get_woocommerce_currency_symbol( $currency_code ) ) . '</label>';
						echo '<input type="number" step="0.01" name="wcml_wc_appointment_pricing_cost[' . esc_html( $currency_code ) . '][]" class="wcml_appointments_custom_price" value="' . esc_html( $value ) . '" placeholder="0" />';
						echo '</div>';
						break;

					case 'wcml_wc_appointment_staff_cost':
						$staff_base_costs = maybe_unserialize( get_post_meta( $post_id, '_staff_base_costs', true ) );

						if ( isset( $staff_base_costs['custom_costs'][ $currency_code ][ $staff_id ] ) ) {
							$value = $staff_base_costs['custom_costs'][ $currency_code ][ $staff_id ];
						} else {
							$value = '';
						}

						echo '<div class="wcml_appointments_staff_slot" >';
						echo '<label>' . wp_kses_post( get_woocommerce_currency_symbol( $currency_code ) ) . '</label>';
						echo '<input type="number" step="0.01" name="wcml_wc_appointment_staff_cost[' . intval( $staff_id ) . '][' . esc_html( $currency_code ) . ']" class="wcml_appointments_custom_price" value="' . esc_html( $value ) . '" placeholder="0" />';
						echo '</div>';
						break;

					case 'wcml_wc_appointment_staff_qty':
						$staff_qtys = maybe_unserialize( get_post_meta( $post_id, '_staff_qtys', true ) );

						if ( isset( $staff_qtys['custom_qtys'][ $currency_code ][ $staff_id ] ) ) {
							$value = $staff_qtys['custom_qtys'][ $currency_code ][ $staff_id ];
						} else {
							$value = '';
						}

						echo '<div class="wcml_appointments_staff_slot" >';
						echo '<label>' . wp_kses_post( get_woocommerce_currency_symbol( $currency_code ) ) . '</label>';
						echo '<input type="number" step="1" name="wcml_wc_appointment_staff_qty[' . intval( $staff_id ) . '][' . esc_html( $currency_code ) . ']" class="wcml_appointments_custom_qty" value="' . esc_html( $value ) . '" placeholder="0" />';
						echo '</div>';
						break;

					default:
						break;

				}

			}

			echo '</div>';

		}
	}

	public function after_appointments_pricing( $post_id ) {

		if ( in_array( 'appointment', wp_get_post_terms( $post_id, 'product_type', [ "fields" => "names" ] ) )
		    && $this->woocommerce_wpml->products->is_original_product( $post_id )
			&& WCML_MULTI_CURRENCIES_INDEPENDENT == $this->woocommerce_wpml->settings['enable_multi_currency'] ) {

			$custom_costs_status = get_post_meta( $post_id, '_wcml_custom_costs_status', true );

			$checked = ! $custom_costs_status ? 'checked="checked"' : ' ';

			echo '<div class="wcml_custom_costs">';

			echo '<input type="radio" name="_wcml_custom_costs" id="wcml_custom_costs_auto" value="0" class="wcml_custom_costs_input" ' . $checked . ' />';
			echo '<label for="wcml_custom_costs_auto">' . esc_html__( 'Calculate costs in other currencies automatically', 'woocommerce-multilingual' ) . '</label>';

			$checked = 1 == $custom_costs_status ? 'checked="checked"' : ' ';

			echo '<input type="radio" name="_wcml_custom_costs" value="1" id="wcml_custom_costs_manually" class="wcml_custom_costs_input" ' . $checked . ' />';
			echo '<label for="wcml_custom_costs_manually">' . esc_html__( 'Set costs in other currencies manually', 'woocommerce-multilingual' ) . '</label>';

			wp_nonce_field( 'wcml_save_custom_costs', '_wcml_custom_pricing_nonce' );

			echo '</div>';
		}

	}

	public function save_custom_costs( $post_id ) {
		$nonce = filter_var( $_POST['_wcml_custom_pricing_nonce'] ?? '', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		if ( isset( $_POST['_wcml_custom_costs'] ) && isset( $nonce ) && wp_verify_nonce( $nonce, 'wcml_save_custom_costs' ) ) {

			update_post_meta( $post_id, '_wcml_custom_costs_status', $_POST['_wcml_custom_costs'] );

			if ( 1 === (int) $_POST['_wcml_custom_costs'] ) {

				$currencies = $this->woocommerce_wpml->multi_currency->get_currencies();
				if ( empty( $currencies ) || 0 === $post_id ) {
					return false;
				}

				$this->update_appointment_pricing( $currencies, $post_id );

				if ( isset( $_POST['wcml_wc_appointment_staff_cost'] ) && is_array( $_POST['wcml_wc_appointment_staff_cost'] ) ) {
					$this->update_appointment_staff_cost( $currencies, $post_id, $_POST['wcml_wc_appointment_staff_cost'] );
				}

				if ( isset( $_POST['wcml_wc_appointment_staff_qty'] ) && is_array( $_POST['wcml_wc_appointment_staff_qty'] ) ) {
					$this->update_appointment_staff_qty( $currencies, $post_id, $_POST['wcml_wc_appointment_staff_qty'] );
				}
			} else {
				return false;
			}
		}

	}

	public function sync_product_data( $original_product_id, $current_product_id ) {
		if ( has_term( 'appointment', 'product_type', $original_product_id ) ) {
			// sync staff and availabilities.
			$this->save_staff_and_availabilities_data_to_translation( $original_product_id );
		}

	}

	public function save_appointment_data_to_translation( $post_id, $data, $job ) {
		if ( has_term( 'appointment', 'product_type', $job->original_doc_id ) ) {
			// sync staff and availabilities.
			$this->save_staff_and_availabilities_data_to_translation( $job->original_doc_id );
		}
	}

	public function save_staff_and_availabilities_data_to_translation( $original_product_id ) {
		$translations = $this->wpml_post_translations->get_element_translations( $original_product_id, false, true );
		foreach ( $translations as $translation ) {
			$language = $this->wpml_post_translations->get_element_lang_code( $translation );

			// sync_resources.
			$this->sync_staff( $original_product_id, $translation, $language );

			// sync_persons.
			$this->sync_availabilities( $original_product_id, $translation, $language );
		}
	}

    public function sync_staff( $original_product_id, $translated_product_id, $lang_code, $duplicate = true ) {
		$original_staff = $this->wpdb->get_results(
			$this->wpdb->prepare(
            	"SELECT staff_id, sort_order
					FROM {$this->wpdb->prefix}wc_appointment_relationships
					WHERE product_id = %d",
				$original_product_id
			)
		);

		$current_staff_ids = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT *
					FROM {$this->wpdb->prefix}wc_appointment_relationships
					WHERE `product_id` = %d
					ORDER BY sort_order ASC",
				$translated_product_id
			),
			ARRAY_A
		);

		$current_temp = [];
		foreach ( $current_staff_ids as $staff ) {
			$current_temp[ $staff['staff_id'] ] = $staff;
		}
		$current_staff_ids = $current_temp;

		foreach ( $original_staff as $staff_member ) {

			$replace = [
				'sort_order' => $staff_member->sort_order,
				'product_id' => $translated_product_id,
				'staff_id'   => $staff_member->staff_id,
			];

			if ( isset( $current_staff_ids[ $staff_member->staff_id ] ) ) {
				$replace['ID'] = $current_staff_ids[ $staff_member->staff_id ]['ID'];
				unset( $current_staff_ids[ $staff_member->staff_id ] );
			}

			$this->wpdb->replace(
				$this->wpdb->prefix . 'wc_appointment_relationships',
				$replace
			);
		}

		if ( ! empty( $current_staff_ids ) ) {
			foreach ( $current_staff_ids as $staff ) {
				$this->wpdb->delete(
					$this->wpdb->prefix . 'wc_appointment_relationships',
					[
						'ID' => $staff['ID'],
					]
				);
			}
		}

        $this->sync_staff_costs( $original_product_id, $translated_product_id, '_staff_base_costs', $lang_code );
		$this->sync_staff_costs( $original_product_id, $translated_product_id, '_staff_qtys', $lang_code );
    }

	public function duplicate_staff_member( $tr_product_id, $staff_member, $lang_code ) {
        $this->wpdb->insert(
            $this->wpdb->prefix . 'wc_appointment_relationships',
            [
                'product_id' => $tr_product_id,
                'staff_id'   => $staff_member->staff_id,
                'sort_order' => $staff_member->sort_order,
            ]
        );

        return $staff_member->staff_id;
    }

	public function sync_availabilities( $original_product_id, $translated_product_id, $lang_code ) {
		$translated_availabilities = $this->wpdb->get_results(
			$this->wpdb->prepare(
            	"SELECT ID, ordering
					FROM {$this->wpdb->prefix}wc_appointments_availability
					WHERE kind_id = %d",
				$translated_product_id
			),
			OBJECT
		);

		$original_availabilities = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT *
					FROM {$this->wpdb->prefix}wc_appointments_availability
					WHERE `kind_id` = %d
					ORDER BY ordering ASC",
				$original_product_id
			),
			OBJECT
		);

		#error_log( var_export( $translated_product_id, true ) );

		// Delete availabilities from translated product.
		foreach ( $translated_availabilities as $translated_availability ) {

			$this->delete_availability( $translated_availability );

		}

		// Add new availabilities to translated product.
		foreach ( $original_availabilities as $original_availability ) {

			$this->duplicate_availability( $translated_product_id, $original_availability, $lang_code );

		}
    }

	public function delete_availability( $tr_availability ) {
		$this->wpdb->delete(
			$this->wpdb->prefix . 'wc_appointments_availability',
			[
				'ID'      => $tr_availability->ID,
			]
		);
    }

	public function duplicate_availability( $tr_product_id, $availability, $lang_code ) {

        $this->wpdb->insert(
			$this->wpdb->prefix . 'wc_appointments_availability',
			[
				'kind'          => 'availability#product',
				'kind_id'       => $tr_product_id,
				'title'         => '',
				'range_type'    => $availability->range_type,
				'from_range'    => $availability->from_range,
				'to_range'      => $availability->to_range,
				'from_date'     => $availability->from_date,
				'to_date'       => $availability->to_date,
				'appointable'   => $availability->appointable,
				'priority'      => $availability->priority,
				'qty'           => $availability->qty,
				'ordering'      => $availability->ordering,
				'rrule'         => '',
				'date_created'  => current_time( 'mysql' ),
				'date_modified' => current_time( 'mysql' ),
			]
		);

        return $availability->ID;
    }

    public function sync_staff_costs_with_translations( $object_id, $meta_key, $check = false ) {
        $original_product_id = apply_filters( 'translate_object_id', $object_id, 'product', true, $this->woocommerce_wpml->products->get_original_product_language( $object_id ) );

        if ( $object_id == $original_product_id ) {
            $trid         = $this->sitepress->get_element_trid( $object_id, 'post_product' );
            $translations = $this->sitepress->get_element_translations( $trid, 'post_product' );

            foreach ( $translations as $translation ) {
                if ( ! $translation->original ) {
                    $this->sync_staff_costs( $original_product_id, $translation->element_id, $meta_key, $translation->language_code );
                }
            }

            return $check;
        } else {
            $language_code = $this->sitepress->get_language_for_element( $object_id, 'post_product' );
            $this->sync_staff_costs( $original_product_id, $object_id, $meta_key, $language_code );

            return true;
        }

    }

    public function sync_staff_costs( $original_product_id, $object_id, $meta_key, $language_code ) {
        $original_costs             = maybe_unserialize( get_post_meta( $original_product_id, $meta_key, true ) );
        $wc_appointment_staff_costs = [];

        if ( ! empty( $original_costs ) ) {
            foreach ( $original_costs as $staff_id => $costs ) {
                if ( 'custom_costs' == $staff_id && isset( $costs['custom_costs'] ) ) {
                    foreach ( $costs['custom_costs'] as $code => $currencies ) {
                        foreach ( $currencies as $custom_costs_staff_id => $custom_cost ) {
                            $trns_staff_id = apply_filters( 'translate_object_id', $custom_costs_staff_id, 'appointable_staff', true, $language_code );
                            $wc_appointment_staff_costs['custom_costs'][ $code ][ $trns_staff_id ] = $custom_cost;
                        }
                    }
                } else {
                    $trns_staff_id  = apply_filters( 'translate_object_id', $staff_id, 'appointable_staff', true, $language_code );
                    $wc_appointment_staff_costs[ $trns_staff_id ] = $costs;
                }
            }
        }

        update_post_meta( $object_id, $meta_key, $wc_appointment_staff_costs );
    }

	public function filter_wc_appointment_cost( $check, $object_id, $meta_key, $single ) {
		if ( in_array(
			$meta_key,
			[
				'_wc_appointment_pricing',
	            '_price',
				'_regular_price',
				'_sale_price',
				'_staff_base_costs',
				'_staff_qtys',
			]
		) ) {
			if ( WCML_MULTI_CURRENCIES_INDEPENDENT === $this->woocommerce_wpml->settings['enable_multi_currency'] ) {
				$original_id = $this->woocommerce_wpml->products->get_original_product_id( $object_id );
				$cost_status = get_post_meta( $original_id, '_wcml_custom_costs_status', true );
				$currency    = $this->woocommerce_wpml->multi_currency->get_client_currency();

				if ( wcml_get_woocommerce_currency_option() === $currency ) {
					return $check;
				}

				if ( in_array( $meta_key, [ '_price', '_regular_price', '_sale_price' ] ) ) {
                    return $check;
				}

				if ( in_array(
					$meta_key,
					[
						'_wc_appointment_pricing',
						'_staff_base_costs',
						'_staff_qtys',
					]
				) ) {

					remove_filter( 'get_post_metadata', [ $this, 'filter_wc_appointment_cost' ], 10 );

					if ( '_wc_appointment_pricing' == $meta_key ) {
						if ( $original_id != $object_id ) {
							$value = get_post_meta( $original_id, $meta_key );
						} else {
							$value = $check;
						}
					} else {
						$costs = maybe_unserialize( get_post_meta( $object_id, $meta_key, true ) );

						if ( ! $costs ) {
							$value = $check;
						} elseif ( $cost_status && isset( $costs['custom_costs'][ $currency ] ) ) {

							$res_costs = [];
							foreach ( $costs['custom_costs'][ $currency ] as $staff_id => $cost ) {
								$trns_staff_id               = apply_filters( 'translate_object_id', $staff_id, 'appointable_staff', true, $this->sitepress->get_current_language() );
								$res_costs[ $trns_staff_id ] = $cost;
							}
							$value = [ 0 => $res_costs ];
						} elseif ( $cost_status && isset( $costs[0]['custom_costs'][ $currency ] ) ) {
							$value = [ 0 => $costs[0]['custom_costs'][ $currency ] ];
						} else {

							$converted_values = [];

							foreach ( $costs as $staff_id => $cost ) {
								$converted_values[0][ $staff_id ] = $this->woocommerce_wpml->multi_currency->prices->convert_price_amount( $cost, $currency );
							}

							$value = $converted_values;
						}
					}

					add_filter( 'get_post_metadata', [ $this, 'filter_wc_appointment_cost' ], 10, 4 );

					return $value;

				}

				$value = get_post_meta( $original_id, $meta_key . '_' . $currency, true );

				if ( $cost_status && ( ! empty( $value ) || ( empty( $value ) ) ) ) {
					return $value;
				} else {
					remove_filter( 'get_post_metadata', [ $this, 'filter_wc_appointment_cost' ], 10 );

					$value = get_post_meta( $original_id, $meta_key, true );

					$value = $this->woocommerce_wpml->multi_currency->prices->convert_price_amount( $value, $currency );

					add_filter( 'get_post_metadata', [ $this, 'filter_wc_appointment_cost' ], 10, 4 );

					return $value;

				}
			}
		}

		return $check;
	}

	public function wc_appointments_process_pricing_rules_cost( $cost, $fields, $key ) {
		return $this->filter_pricing_cost( $cost, $fields, 'cost_', $key );
	}

	public function wc_appointments_process_pricing_rules_base_cost( $base_cost, $fields, $key ) {
		return $this->filter_pricing_cost( $base_cost, $fields, 'base_cost_', $key );
	}

	public function wc_appointments_process_pricing_rules_override_slot_cost( $override_cost, $fields, $key ) {
		return $this->filter_pricing_cost( $override_cost, $fields, 'override_slot_', $key );
	}

	public function filter_pricing_cost( $cost, $fields, $name, $key ) {
		global $product;

		if ( WCML_MULTI_CURRENCIES_INDEPENDENT == $this->woocommerce_wpml->settings['enable_multi_currency'] ) {
			$currency = $this->woocommerce_wpml->multi_currency->get_client_currency();

			if ( wcml_get_woocommerce_currency_option() === $currency ) {
				return $cost;
			}

			if ( isset( $_POST['form'] ) ) {
				parse_str( $_POST['form'], $posted );

				$product_id = $posted['add-to-cart'];

			} elseif ( isset( $_POST['add-to-cart'] ) ) {

				$product_id = $_POST['add-to-cart'];

			} elseif ( isset( $_POST['appointable-product-id'] ) ) {

				$product_id = $_POST['appointable-product-id'];

			}

			if ( isset( $product_id ) ) {
				$original_id = $this->woocommerce_wpml->products->get_original_product_id( $product_id );

				if ( $product_id != $original_id ) {
					$fields = maybe_unserialize( get_post_meta( $original_id, '_wc_appointment_pricing', true ) );
					$fields = $fields[ $key ];
				}
			}

			$needs_filter_pricing_cost = $this->needs_filter_pricing_cost( $name, $fields );

			if ( $needs_filter_pricing_cost ) {
				if ( isset( $fields[ $name . $currency ] ) ) {
					return $fields[ $name . $currency ];
				} else {
					return $this->woocommerce_wpml->multi_currency->prices->convert_price_amount( $cost, $currency );
				}
			}
		}

		return $cost;
	}

	public function needs_filter_pricing_cost( $name, $fields ) {
		$modifier_skip_values = [ 'divide', 'times' ];

		if (
			'override_slot_' === $name ||
			( 'cost_' === $name && ! in_array( $fields['modifier'], $modifier_skip_values ) ) ||
			( 'base_cost_' === $name && ! in_array( $fields['base_modifier'], $modifier_skip_values ) )
		) {
			return true;
		} else {
			return false;
		}
	}

	public function wcml_multi_currency_is_ajax( $actions ) {
		$actions[] = 'wc_appointments_calculate_costs';

		return $actions;
	}

	public function wcml_load_multi_currency_in_admin( $load ) {
		if ( isset( $_GET['page'] ) && 'add_appointment' === $_GET['page'] ) {
			return true;
		}

		return $load;
	}

	public function appointment_currency_dropdown() {
		if ( WCML_MULTI_CURRENCIES_INDEPENDENT == $this->woocommerce_wpml->settings['enable_multi_currency'] ) {

			$current_appointment_currency = $this->get_cookie_appointment_currency();

			$wc_currencies = get_woocommerce_currencies();
			$currencies    = $this->woocommerce_wpml->multi_currency->get_currencies( true );
			?>
			<tr valign="top">
				<th scope="row"><?php esc_html_e( 'Appointment currency', 'woocommerce-multilingual' ); ?></th>
				<td>
					<select id="dropdown_appointment_currency">

						<?php foreach ( $currencies as $currency => $count ) : ?>

							<option value="<?php echo $currency ?>" <?php echo $current_appointment_currency == $currency ? 'selected="selected"' : ''; ?>><?php echo $wc_currencies[ $currency ]; ?></option>

						<?php endforeach; ?>

					</select>
				</td>
			</tr>

			<?php

			$wcml_appointment_set_currency_nonce = wp_create_nonce( 'appointment_set_currency' );

			wc_enqueue_js(
			"
            jQuery(document).on('change', '#dropdown_appointment_currency', function(){
               jQuery.ajax({
                    url: ajaxurl,
                    type: 'post',
					data: {
                        action: 'wcml_appointment_set_currency',
                        currency: jQuery('#dropdown_appointment_currency').val(),
                        wcml_nonce: '" . $wcml_appointment_set_currency_nonce . "'
                    },
                    success: function( response ){
                        if(typeof response.error !== 'undefined'){
                            alert(response.error);
                        }else{
                           window.location = window.location.href;
                        }
                    }
                })
            });
        	"
			);

		}
	}

	public function set_appointment_currency_ajax() {
		$nonce = filter_input( INPUT_POST, 'wcml_nonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'appointment_set_currency' ) ) {
			echo wp_json_encode( [ 'error' => esc_html__( 'Invalid nonce', 'woocommerce-multilingual' ) ] );
			die();
		}

		$this->set_appointment_currency( filter_input( INPUT_POST, 'currency', FILTER_SANITIZE_FULL_SPECIAL_CHARS ) );

		die();
	}

	public function set_appointment_currency( $currency_code = false ) {
		$cookie_name = '_wcml_appointment_currency';

		if ( ! isset( $_COOKIE [ $cookie_name ] ) && ! headers_sent() ) {

			$currency_code = wcml_get_woocommerce_currency_option();

			if ( WCML_MULTI_CURRENCIES_INDEPENDENT === $this->woocommerce_wpml->settings['enable_multi_currency'] ) {
				$currency_code = $this->woocommerce_wpml->multi_currency->get_currency_code();
			}
		}

		if ( $currency_code ) {
			do_action( 'wpsc_add_cookie', $cookie_name );
			setcookie( $cookie_name, $currency_code, time() + 86400, COOKIEPATH, COOKIE_DOMAIN );
		}
	}

	// Disabled until we can determine cart_item currency.
	public function filter_bundled_product_in_cart_contents( $cart_item, $key, $current_language ) {

		if ( $cart_item['data'] instanceof WC_Product_Appointment && isset( $cart_item['appointment'] ) ) {

			$current_id      = apply_filters( 'translate_object_id', $cart_item['product_id'], 'product', true, $current_language );
			$cart_product_id = $cart_item['product_id'];

			if ( $current_id != $cart_product_id ) {
				$cart_item['data'] = get_wc_product_appointment( $current_id );
			}

			$client_currency      = $this->woocommerce_wpml->multi_currency->get_client_currency();
			$appointment_currency = $this->get_cookie_appointment_currency();
			$currency_switch      = $this->woocommerce_wpml->settings['cart_sync']['currency_switch'];

			if ( WCML_MULTI_CURRENCIES_INDEPENDENT == $this->woocommerce_wpml->settings['enable_multi_currency'] ) {

				if ( isset( $cart_item['appointment']['_cost'] ) ) {
					if ( $appointment_currency != $client_currency ) {
						#$cost = $this->woocommerce_wpml->multi_currency->prices->convert_price_amount( $cart_item['appointment']['_cost'], $client_currency );
						#$cost = $this->woocommerce_wpml->multi_currency->prices->apply_rounding_rules( $cost, $client_currency );
						$cost = $cart_item['data']->get_price();
						if ( ! is_wp_error( $cost ) ) {
							$cart_item['data']->set_price( $cost );
						}
					}
				}
			}
		}

		return $cart_item;
	}

	public function get_cookie_appointment_currency() {
		if ( isset( $_COOKIE ['_wcml_appointment_currency'] ) ) {
			$currency = $_COOKIE['_wcml_appointment_currency'];
		} else {
			$currency = get_woocommerce_currency();
		}

		return $currency;
	}

	public function filter_appointment_currency_symbol( $currency ) {
		global $pagenow;

		remove_filter( 'woocommerce_currency_symbol', [ $this, 'filter_appointment_currency_symbol' ] );
		if ( isset( $_COOKIE ['_wcml_appointment_currency'] ) && $pagenow == 'edit.php' && isset( $_GET['page'] ) && 'add_appointment' === $_GET['page'] ) {
			$currency = get_woocommerce_currency_symbol( $_COOKIE ['_wcml_appointment_currency'] );
		}
		add_filter( 'woocommerce_currency_symbol', [ $this, 'filter_appointment_currency_symbol' ] );

		return $currency;
	}

	public function add_appointment_page_client_currency( $currency ) {
		global $pagenow;

		if ( wpml_is_ajax() && isset( $_POST['form'] ) ) {
			parse_str( $_POST['form'], $posted );
		}

		if ( ( 'edit.php' == $pagenow && isset( $_GET['page'] ) && 'add_appointment' == $_GET['page'] ) || ( isset( $posted['_wp_http_referer'] ) && strpos( $posted['_wp_http_referer'], 'page=add_appointment' ) !== false ) ) {
			$currency = $this->get_cookie_appointment_currency();
		}

		return $currency;
	}

	public function set_order_currency_on_add_appointment_page( $order_id ) {
		update_post_meta( $order_id, '_order_currency', $this->get_cookie_appointment_currency() );
		update_post_meta( $order_id, 'wpml_language', $this->sitepress->get_current_language() );
	}

	public function filter_get_appointment_products_args( $args ) {
		if ( isset( $args['suppress_filters'] ) ) {
			$args['suppress_filters'] = false;
		}

		return $args;
	}

	public function show_custom_slots_for_staff( $check, $product_id, $product_content ) {
		if ( in_array( $product_content, [ 'wc_appointment_staff' ] ) ) {
			return false;
		}

		return $check;
	}

	public function replace_tm_editor_custom_fields_with_own_sections( $fields ) {
		$fields[] = '_staff_base_costs';
		$fields[] = '_staff_qtys';

		return $fields;
	}

    public function remove_single_custom_fields_to_translate( $fields ) {
        $fields[] = '_wc_appointment_staff_label';

        return $fields;
    }

    public function product_content_staff_label( $meta_key, $product_id ) {
        if ( '_wc_appointment_staff_label' == $meta_key ) {
            return esc_html__( 'Staff label', 'woocommerce-appointments' );
        }

        return $meta_key;
    }

	public function get_original_staff( $product_id ) {
        $orig_staff = maybe_unserialize( get_post_meta( $product_id, '_staff_base_costs', true ) );

        return $orig_staff;
    }

    public function wcml_products_tab_sync_staff_and_availabilities( $original_product_id, $tr_product_id, $data, $language ) {
        global $wpml_post_translations;

        remove_action( 'save_post', [ $wpml_post_translations, 'save_post_actions' ], 100 );

        $orig_staff = $this->get_original_staff( $original_product_id );

        if ( $orig_staff ) {

            foreach ( $orig_staff as $orig_staff_id => $cost ) {

                $staff_id = apply_filters( 'translate_object_id', $orig_staff_id, 'appointable_staff', false, $language );

				/** @var stdClass */
                $orig_staff_member = $this->wpdb->get_row( $this->wpdb->prepare( "SELECT staff_id, sort_order FROM {$this->wpdb->prefix}wc_appointment_relationships WHERE staff_id = %d AND product_id = %d", $orig_staff_id, $original_product_id ), OBJECT );

                if ( is_null( $staff_id ) ) {
                    if ( $orig_staff_member ) {
                        $staff_id = $this->duplicate_staff_member( $tr_product_id, $orig_staff_member, $language );
                    } else {
                        continue;
                    }
                } else {
                    // Update_relationship
                    $exist = $this->wpdb->get_var( $this->wpdb->prepare( "SELECT ID FROM {$this->wpdb->prefix}wc_appointment_relationships WHERE staff_id = %d AND product_id = %d", $staff_id, $tr_product_id ) );

                    if ( ! $exist ) {
                        $this->wpdb->insert(
                            $this->wpdb->prefix . 'wc_appointment_relationships',
                            [
                                'product_id' => $tr_product_id,
                                'staff_id'   => $staff_id,
                                'sort_order' => $orig_staff_member->sort_order,
                            ]
                        );
                    }
                }

                update_post_meta( $staff_id, 'wcml_is_translated', true );
            }

            // sync staff data.
            $this->sync_staff( $original_product_id, $tr_product_id, $language, false );

        }

		// Sync availabilities.
		$this->sync_availabilities( $original_product_id, $tr_product_id, $language );

        add_action( 'save_post', [ $wpml_post_translations, 'save_post_actions' ], 100, 2 );
    }

	public function translate_transactional_appointment_email_texts( $appointment_id ) {
        // Translate emails.
		$appointment = get_wc_appointment( $appointment_id );
		if ( $appointment ) {
			$order = $appointment->get_order();
			if ( $order ) {
				$this->woocommerce_wpml->emails->refresh_email_lang( $order->get_id() );
			}
		}
    }

	public function get_translated_appointments( $appointment_id ) {
		$translated_appointments = $this->wpdb->get_results( $this->wpdb->prepare( "SELECT post_id FROM {$this->wpdb->postmeta} WHERE meta_key = '_appointment_duplicate_of' AND meta_value = %d", $appointment_id ) );

		return $translated_appointments;
	}

	// Include all product translations inside appointment query.
	public function appointments_date_range_query_args( $args ) {
		#error_log( var_export( $args, true ) );
		if ( isset( $args['product_id'] ) && ! empty( $args['product_id'] ) ) {
			$product_ids = is_array( $args['product_id'] ) ? $args['product_id'] : [ $args['product_id'] ];

			$product_id_array = [];
			foreach ( $product_ids as $product_id ) {
				$trid         = $this->sitepress->get_element_trid( $product_id, 'post_product' );
				$translations = $this->sitepress->get_element_translations( $trid, 'post_product' );

				foreach ( $translations as $translation ) {
					$product_id_array[] = (int) $translation->element_id;
				}
			}

			$args['product_id'] = $product_id_array;
		}

        return $args;
    }

    public function append_staff_to_translation_package( $package, $post ) {
        if ( 'product' == $post->post_type ) {
            $product = wc_get_product( $post->ID );

            if ( 'appointment' == $product->get_type() && $product->has_staff() ) {
                $staff = $product->get_staff();
                foreach ( $staff as $staff_member ) {
                    $package['contents'][ 'wc_appointments:staff:' . $staff_member->get_id() . ':name' ] = [
                        'translate' => 1,
                        'data'      => $this->tp->encode_field_data( $staff_member->display_name ),
                        'format'    => 'base64',
                    ];
                }
            }
        }

        return $package;
    }

    public function wcml_js_lock_fields_ids( $ids ) {
        $ids = array_merge(
			$ids,
			[
				'_wc_appointment_has_price_label',
				'_wc_appointment_has_pricing',
				'_wc_appointment_qty',
				'_wc_appointment_qty_min',
				'_wc_appointment_qty_max',
				'_wc_appointment_staff_assignment',
				'_wc_appointment_duration',
				'_wc_appointment_duration_unit',
				'_wc_appointment_interval',
				'_wc_appointment_interval_unit',
				'_wc_appointment_min_date',
				'_wc_appointment_min_date_unit',
				'_wc_appointment_max_date',
				'_wc_appointment_max_date_unit',
				'_wc_appointment_padding_duration',
				'_wc_appointment_padding_duration_unit',
				'_wc_appointment_user_can_cancel',
				'_wc_appointment_cancel_limit',
				'_wc_appointment_cancel_limit_unit',
				'_wc_appointment_user_can_reschedule',
				'_wc_appointment_reschedule_limit_unit',
				'_wc_appointment_reschedule_limit',
				'_wc_appointment_customer_timezones',
				'_wc_appointment_cal_color',
				'_wc_appointment_requires_confirmation',
				'_wc_appointment_availability_span',
				'_wc_appointment_availability_autoselect',
				'_wc_appointment_has_restricted_days',
				'_wc_appointment_restricted_days',
				'appointments_staff select',
				'appointments_staff button',
				'appointments_availability select',
				'appointments_availability input',
				'appointments_availability a.button',
			]
		);

        return $ids;
    }

	/**
	 * @param array $args
	 *
	 * @return array
	 */
    public function filter_get_appointment_staff_args( $args ) {
		// Get current screen.
		$screen    = function_exists( 'get_current_screen' ) ? get_current_screen() : '';
		$screen_id = $screen ? $screen->id : '';

        if ( 'product' === $screen_id ) {
            $args['suppress_filters'] = false;
        }

        return $args;
    }

	/**
	 * @param array $currencies
	 * @param int $post_id
	 *
	 * @return bool
	 */
	private function update_appointment_pricing( $currencies = [], $post_id = 0 ) {
		$updated_meta        = [];
		$appointment_pricing = get_post_meta( $post_id, '_wc_appointment_pricing', true );

        if ( empty( $appointment_pricing ) ) {
			return false;
		}

		foreach ( $appointment_pricing as $key => $prices ) {
			$updated_meta[ $key ] = $prices;
			foreach ( $currencies as $code => $currency ) {
				if ( isset( $_POST['wcml_wc_appointment_pricing_base_cost'][ $code ][ $key ] ) ) {
					$updated_meta[ $key ][ 'base_cost_' . $code ] = sanitize_text_field( $_POST['wcml_wc_appointment_pricing_base_cost'][ $code ][ $key ] );
				}
				if ( isset( $_POST['wcml_wc_appointment_pricing_cost'][ $code ][ $key ] ) ) {
					$updated_meta[ $key ][ 'cost_' . $code ] = sanitize_text_field( $_POST['wcml_wc_appointment_pricing_cost'][ $code ][ $key ] );
				}
			}
		}

		update_post_meta( $post_id, '_wc_appointment_pricing', $updated_meta );

		return true;
	}

    /**
	 * @param array $currencies
	 * @param int $post_id
	 * @param array $staff_cost
	 *
	 * @return bool
	 */
	private function update_appointment_staff_cost( $currencies = [], $post_id = 0, $staff_cost = [] ) {
		if ( empty( $staff_cost ) ) {
			return false;
		}

		$updated_meta = get_post_meta( $post_id, '_staff_base_costs', true );
		if ( ! is_array( $updated_meta ) ) {
			$updated_meta = [];
		}

		$wc_appointment_staff_costs = [];

		foreach ( $staff_cost as $staff_id => $costs ) {
			foreach ( $currencies as $code => $currency ) {
				if ( isset( $costs[ $code ] ) ) {
					$wc_appointment_staff_costs[ $code ][ $staff_id ] = sanitize_text_field( $costs[ $code ] );
				}
			}
		}

		$updated_meta['custom_costs'] = $wc_appointment_staff_costs;

		update_post_meta( $post_id, '_staff_base_costs', $updated_meta );

		$this->sync_staff_costs_with_translations( $post_id, '_staff_base_costs' );

		return true;
	}

	/**
	 * @param array $currencies
	 * @param int $post_id
	 * @param array $staff_qty
	 *
	 * @return bool
	 */
	private function update_appointment_staff_qty( $currencies = [], $post_id = 0, $staff_qty = [] ) {
		if ( empty( $staff_qty ) ) {
			return false;
		}

		$updated_meta = get_post_meta( $post_id, '_staff_qtys', true );
		if ( ! is_array( $updated_meta ) ) {
			$updated_meta = [];
		}

		$wc_appointment_staff_qtys = [];

		foreach ( $staff_qty as $staff_id => $qtys ) {
			foreach ( $currencies as $code => $currency ) {
				if ( isset( $qtys[ $code ] ) ) {
					$wc_appointment_staff_qtys[ $code ][ $staff_id ] = sanitize_text_field( $qtys[ $code ] );
				}
			}
		}

		$updated_meta['custom_qtys'] = $wc_appointment_staff_qtys;

		update_post_meta( $post_id, '_staff_qtys', $updated_meta );

		$this->sync_staff_costs_with_translations( $post_id, '_staff_qtys' );

		return true;
	}

	public function extra_conditions_to_filter_appointments( $extra_conditions ) {
		if ( isset( $_GET[ 'post_type' ] ) && 'wc_appointment' == $_GET[ 'post_type' ] && ! isset( $_GET[ 'post_status' ] ) ){
			$extra_conditions = str_replace( "GROUP BY", " AND post_status = 'confirmed' GROUP BY", $extra_conditions );
		}

		return $extra_conditions;
	}

	public function hide_appointments_type_on_tm_dashboard( $types ) {
		unset( $types['wc_appointment'] );
		return $types;
	}

	// unset "appointments" from translatable documents to hide WPML languages section from appointment edit page
	public function filter_translatable_documents( $icl_post_types ) {

		if (
			( isset( $_GET['post'] ) && 'wc_appointment' == get_post_type( $_GET[ 'post' ] ) ) ||
			( isset( $_GET['post_type'] ) && 'wc_appointment' == $_GET['post_type'] )
		) {
			unset( $icl_post_types['wc_appointment'] );
		}

		return $icl_post_types;
	}

	// hide WPML languages links section from appointments list page
	public function filter_is_translated_post_type( $type ) {
		if ( isset( $_GET['post_type'] ) && 'wc_appointment' == $_GET['post_type'] ) {
			return false;
		}

		return $type;
	}

	// This makes sure same appointments from different products
	// skip default checking for different products.
	public function filter_check_appointment_product( $return, $appointment_id, $product_id ) {
		#echo '<pre>' . var_export( $appointment_id, true ) . '</pre>';

		$appointment            = get_wc_appointment( $appointment_id );
		$appointment_product_id = (int) $appointment->get_product_id();
		$is_original_product    = $this->woocommerce_wpml->products->is_original_product( $product_id );

		// Check if product is original.
		if ( $product_id !== $appointment_product_id ) {
			$trid         = $this->sitepress->get_element_trid( $product_id, 'post_product' );
			$translations = $this->sitepress->get_element_translations( $trid, 'post_product' );

			// Get all translated products.
			$translation_ids = [];
			foreach ( $translations as $translation ) {
				$translation_ids[] = (int) $translation->element_id;
			}

			// Current product is a translation of original product ID.
			if ( in_array( $appointment_product_id, $translation_ids ) ) {
				return false;
			}
		}

		return $return;
	}

	public function emails_options_to_translate( $emails_options ) {
		$emails_options[] = 'woocommerce_new_appointment_settings';
		$emails_options[] = 'woocommerce_appointment_reminder_settings';
		$emails_options[] = 'woocommerce_appointment_confirmed_settings';
		$emails_options[] = 'woocommerce_appointment_cancelled_settings';
		$emails_options[] = 'woocommerce_admin_appointment_cancelled_settings';

		return $emails_options;
	}

	public function emails_text_keys_to_translate( $text_keys ) {
		$text_keys[] = 'subject_confirmation';
		$text_keys[] = 'heading_confirmation';

		return $text_keys;
	}

	public function translate_emails_text_strings( $value, $object, $old_value, $key ) {
		$emails_ids = [
			'admin_appointment_cancelled',
			'admin_new_appointment',
			'admin_appointment_rescheduled',
			'appointment_cancelled',
			'appointment_confirmed',
			'appointment_reminder',
		];

		$keys = [
			'subject',
			'subject_confirmation',
			'heading',
			'heading_confirmation',
		];

		if ( in_array( $key, $keys ) && in_array( $object->id, $emails_ids ) ) {
			$translated_value = $object->$key;
		}

		return ! empty( $translated_value ) ? $translated_value : $value;
	}

	public function translate_appointment_cancelled_email_texts( $appointment_id ) {

		#if ( class_exists( 'WC_Email_Appointment_Cancelled' ) && isset( $this->woocommerce->mailer()->emails['WC_Email_Appointment_Cancelled'] ) ) {
			$appointment = get_wc_appointment( $appointment_id );

			if ( $appointment ) {
				$order = $appointment->get_order();
				if ( $order ) {
					$this->woocommerce_wpml->emails->refresh_email_lang( $order->get_id() );
					$this->translate_email_strings( 'WC_Email_Appointment_Cancelled', 'woocommerce_appointment_cancelled_settings', $order->get_id() );
				}
			}
		#}

	}

	public function translate_appointment_cancelled_admin_email_texts( $appointment_id ) {

		#if ( class_exists( 'WC_Email_Admin_Appointment_Cancelled' ) && isset( $this->woocommerce->mailer()->emails['WC_Email_Admin_Appointment_Cancelled'] ) ) {
			$user = get_user_by( 'email', $this->woocommerce->mailer()->emails['WC_Email_Admin_Appointment_Cancelled']->recipient );
			if ( $user ) {
				$user_lang = $this->sitepress->get_user_admin_language( $user->ID, true );
			} else {
				$appointment = get_wc_appointment( $appointment_id );
				$user_lang   = get_post_meta( $appointment->get_order()->get_id(), 'wpml_language', true );
			}
			$this->translate_email_strings( 'WC_Email_Admin_Appointment_Cancelled', 'woocommerce_admin_appointment_cancelled_settings', false, $user_lang );
		#}

	}

	public function translate_new_appointment_email_texts( $appointment_id ) {

		#if ( class_exists( 'WC_Email_Admin_New_Appointment' ) && isset( $this->woocommerce->mailer()->emails['WC_Email_Admin_New_Appointment'] ) ) {
			$user = get_user_by( 'email', $this->woocommerce->mailer()->emails['WC_Email_Admin_New_Appointment']->recipient );
			if ( $user ) {
				$user_lang = $this->sitepress->get_user_admin_language( $user->ID, true );
			} else {
				$appointment = get_wc_appointment( $appointment_id );
				$user_lang   = get_post_meta( $appointment->get_order()->get_id(), 'wpml_language', true );
			}
			$this->translate_email_strings( 'WC_Email_Admin_New_Appointment', 'woocommerce_new_appointment_settings', false, $user_lang );
		#}

	}

	public function translate_appointment_confirmed_email_texts( $appointment_id ) {

		#if ( class_exists( 'WC_Email_Appointment_Confirmed' ) && isset( $this->woocommerce->mailer()->emails['WC_Email_Appointment_Confirmed'] ) ) {
			$appointment = get_wc_appointment( $appointment_id );

			if ( $appointment ) {
				$order = $appointment->get_order();
				if ( $order ) {
					$this->woocommerce_wpml->emails->refresh_email_lang( $order->get_id() );
					$this->translate_email_strings( 'WC_Email_Appointment_Confirmed', 'woocommerce_appointment_confirmed_settings', $order->get_id() );
				}
			}
		#}

	}

	public function translate_appointment_reminder_email_texts( $appointment_id ) {

		#if ( class_exists( 'WC_Email_Appointment_Reminder' ) && isset( $this->woocommerce->mailer()->emails['WC_Email_Appointment_Reminder'] ) ) {
			$appointment = get_wc_appointment( $appointment_id );

			if ( $appointment ) {
				$order = $appointment->get_order();
				if ( $order ) {
					$this->woocommerce_wpml->emails->refresh_email_lang( $order->get_id() );
					$this->translate_email_strings( 'WC_Email_Appointment_Reminder', 'woocommerce_appointment_reminder_settings', $order->get_id() );
				}
			}
		#}

	}

	public function translate_appointment_follow_up_email_texts( $appointment_id ) {

		#if ( class_exists( 'WC_Email_Appointment_Follow_Up' ) && isset( $this->woocommerce->mailer()->emails['WC_Email_Appointment_Follow_Up'] ) ) {
			$appointment = get_wc_appointment( $appointment_id );

			if ( $appointment ) {
				$order = $appointment->get_order();
				if ( $order ) {
					$this->woocommerce_wpml->emails->refresh_email_lang( $order->get_id() );
					$this->translate_email_strings( 'WC_Email_Appointment_Follow_Up', 'woocommerce_appointment_follow_up_settings', $order->get_id() );
				}
			}
		#}

	}

	public function appointment_email_language( $current_language ) {
		if ( isset( $_POST['post_type'] ) && 'wc_appointment' === $_POST['post_type'] ) {
			$order_language = get_post_meta( $_POST['_appointment_order_id'], 'wpml_language', true );
			if ( $order_language ) {
				$current_language = $order_language;
			}
		}

		return $current_language;
	}

	public function maybe_set_appointment_language( $appointment_id ) {

		if ( 'wc_appointment' === get_post_type( $appointment_id ) ) {
			$language_details = $this->sitepress->get_element_language_details( $appointment_id, 'post_wc_appointment' );
			if ( ! $language_details ) {
				$current_language = $this->sitepress->get_current_language();
				$this->sitepress->set_element_language_details( $appointment_id, 'post_wc_appointment', false, $current_language );
			}
		}

	}

	private function translate_email_strings( $email_class, $setting_slug, $order_id = false, $user_lang = null ) {
		$heading_exists = $this->woocommerce_wpml->emails->wcml_get_translated_email_string( 'admin_texts_' . $setting_slug, '[' . $setting_slug . ']heading', $order_id, $user_lang );
		if ( $heading_exists ) {
			$this->woocommerce->mailer()->emails[ $email_class ]->heading = $heading_exists;
		}

		$subject_exists = $this->woocommerce_wpml->emails->wcml_get_translated_email_string( 'admin_texts_' . $setting_slug, '[' . $setting_slug . ']subject', $order_id, $user_lang );
		if ( $subject_exists ) {
			$this->woocommerce->mailer()->emails[ $email_class ]->subject = $subject_exists;
		}
    }
}

// Load integration after WCML loads.
add_action( 'plugins_loaded', 'wc_appointments_wcml_loaded', 10001 );
function wc_appointments_wcml_loaded() {
	global $sitepress, $woocommerce, $woocommerce_wpml, $wpdb, $wpml_post_translations;

	if ( ! did_action( 'wpml_loaded' ) ) {
		$woocommerce_wpml = new woocommerce_wpml();
	}

	if ( ! $woocommerce_wpml ) {
		return;
	}

	new WC_Appointments_Integration_WCML( $sitepress, $woocommerce, $woocommerce_wpml, $wpdb, new WPML_Element_Translation_Package(), $wpml_post_translations );
}
