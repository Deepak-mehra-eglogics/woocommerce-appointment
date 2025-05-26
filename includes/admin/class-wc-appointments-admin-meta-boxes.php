<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * WC_Appointments_Admin_Meta_Boxes.
 */
class WC_Appointments_Admin_Meta_Boxes {

	/**
	 * Stores an array of meta boxes we include.
	 *
	 * @var array
	 */
	private $meta_boxes = [];

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->meta_boxes[] = include 'meta-boxes/class-wc-appointment-meta-box-data.php';
		$this->meta_boxes[] = include 'meta-boxes/class-wc-appointment-meta-box-customer.php';
		$this->meta_boxes[] = include 'meta-boxes/class-wc-appointment-meta-box-save.php';

		add_action( 'add_meta_boxes', [ $this, 'add_meta_boxes' ], 10, 1 );
		add_action( 'add_meta_boxes', [ $this, 'remove_meta_boxes' ] );
	}

	/**
	 * Add meta boxes to edit product page
	 */
	public function add_meta_boxes() {
		foreach ( $this->meta_boxes as $meta_box ) {
			foreach ( $meta_box->post_types as $post_type ) {
				add_meta_box(
		            $meta_box->id,
		            $meta_box->title,
		            [
						$meta_box,
						'meta_box_inner',
					],
		            $post_type,
		            $meta_box->context,
		            $meta_box->priority
		        );
			}
		}
	}

	/**
	 * Removes built-in meta boxes.
	 *
	 * The post_status field from submitdiv meta box causing unexpected transition
	 * appointment status events.
	 *
	 * @since 2.7.0
	 * @version 4.10.5
	 */
	public function remove_meta_boxes() {
		remove_meta_box( 'submitdiv', 'wc_appointment', 'side' );
		remove_meta_box( 'slugdiv', 'wc_appointment', 'normal' );
	}
}

return new WC_Appointments_Admin_Meta_Boxes();
