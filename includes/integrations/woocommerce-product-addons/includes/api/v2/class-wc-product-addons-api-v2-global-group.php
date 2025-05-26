<?php
/**
 * Rest API v2 Global Group helper.
 *
 * @package Automattic\WooCommerce\ProductAddons
 * @since 6.9.0
 */

/**
 * WC_Product_Addons_Api_V2_Global_Group class.
 */
class WC_Product_Addons_Api_V2_Global_Group extends WC_Product_Addons_Api_V2_Abstract_Group {

	/**
	 * Group restrictions.
	 *
	 * @var array
	 */
	protected $restrict_to_category_ids = array();

	/**
	 * Constructor.
	 *
	 * @throws \Exception Exception if the provided ID is invalid.
	 * @param integer $id Group ID. 0 if the group does not yet exist.
	 */
	public function __construct( int $id = 0 ) {
		$this->id = $id;

		if ( $this->id ) {
			$post = get_post( $this->id );

			if ( ! is_a( $post, '\WP_Post' ) || 'global_product_addon' !== $post->post_type ) {
				throw new \Exception( 'WC_Product_Addons_Api_V2_Global_Group::Invalid group ID' );
			}

			$this->set_name( $post->post_title );
			$this->set_priority( intval( get_post_meta( $post->ID, '_priority', true ) ) );
			$this->set_restrict_to_category_ids( (array) wp_get_post_terms( $post->ID, array( 'product_cat' ), array( 'fields' => 'ids' ) ) );
			$this->set_fields_from_db( get_post_meta( $post->ID, '_product_addons', true ) );
		}
	}

	/**
	 * Persist data.
	 */
	public function save() {
		wp_update_post(
			array(
				'ID'         => $this->get_id(),
				'post_title' => $this->get_name(),
			)
		);
		update_post_meta( $this->get_id(), '_priority', $this->get_priority() );
		update_post_meta( $this->get_id(), '_product_addons', $this->get_fields_for_db() );
		wp_set_post_terms( $this->get_id(), $this->get_restrict_to_category_ids(), 'product_cat', false );
		update_post_meta( $this->get_id(), '_all_products', empty( $this->get_restrict_to_category_ids() ) ? 1 : 0 );
	}

	/**
	 * Get restrict_to_category_ids as id=>name pairs.
	 *
	 * @return array
	 */
	public function get_restrict_to_categories() {
		$return = array();
		foreach ( $this->get_restrict_to_category_ids() as $category_id ) {
			$term = get_term_by( 'id', $category_id, 'product_cat' );
			if ( $term ) {
				$return[] = array(
					'id'   => $category_id,
					'name' => $term->name,
				);
			}
		}
		return $return;
	}

	/**
	 * Get restrict_to_category_ids.
	 *
	 * @return int[]
	 */
	public function get_restrict_to_category_ids() {
		return $this->restrict_to_category_ids;
	}

	/**
	 * Set restrict_to_category_ids.
	 *
	 * @param array $value Value to set.
	 */
	public function set_restrict_to_category_ids( array $value ) {
		$this->restrict_to_category_ids = wp_parse_id_list( $value );
	}

	/**
	 * Create a new global Add-On group.
	 *
	 * @param \WP_REST_Request $request Args to update.
	 *
	 * @return WC_Product_Addons_Api_V2_Global_Group|WC_Product_Addons_Api_V2_Product_Group|\WP_Error Returns the updated group on success.
	 */
	public static function create_group( \WP_REST_Request $request ) {
		$new_post_id = wp_insert_post(
			array(
				'post_title'  => 'Untitled',
				'post_status' => 'publish',
				'post_type'   => 'global_product_addon',
			)
		);
		$group       = new self( $new_post_id );
		return $group->update_group( $request );
	}

	/**
	 * Given a global group ID, update add-ons from the args provided
	 *
	 * @param \WP_REST_Request $request Args to update.
	 *
	 * @return WC_Product_Addons_Api_V2_Global_Group Returns the updated group on success.
	 */
	public function update_group( \WP_REST_Request $request ) {

		if ( isset( $request['name'] ) ) {
			$this->set_name( $request['name'] );
		}

		if ( isset( $request['priority'] ) ) {
			$this->set_priority( $request['priority'] );
		}

		if ( isset( $request['restrict_to_categories'] ) ) {
			$restrict_to_categories     = array();
			$restrict_to_categories_raw = array_filter( (array) $request['restrict_to_categories'] );
			foreach ( $restrict_to_categories_raw as $raw_value ) {
				if ( is_array( $raw_value ) && isset( $raw_value['id'] ) ) {
					$restrict_to_categories[] = absint( $raw_value['id'] );
				}
				if ( is_numeric( $raw_value ) ) {
					$restrict_to_categories[] = absint( $raw_value );
				}
			}
			$this->set_restrict_to_category_ids( $restrict_to_categories );
		}

		if ( isset( $request['fields'] ) ) {
			$this->update_fields( $request['fields'] );
		}

		$this->save();

		return $this;
	}

	/**
	 * Given a global group ID, deletes it
	 *
	 * @param int $id Group ID.
	 * @return bool|\WP_Error
	 */
	public function delete_group( int $id ) {
		if ( ! self::is_a_global_group_id( $id ) ) {
			return new \WP_Error(
				'invalid_id',
				esc_html__( 'Unable to delete group. Invalid global add-on group ID.', 'woocommerce-appointments' )
			);
		}
		$success = wp_delete_post( $id, true );
		return ( null !== $success ) && ( false !== $success );
	}

	/**
	 * Tests if the passed ID corresponds to a global group
	 *
	 * @param integer $id Group ID.
	 * @return bool
	 */
	public static function is_a_global_group_id( int $id ) {
		$post = \WP_Post::get_instance( $id );

		if ( ! is_a( $post, 'WP_Post' ) ) {
			return false;
		}

		return ( 'global_product_addon' === $post->post_type );
	}

	/**
	 * Returns all the global groups (if any) and their add-ons
	 *
	 * @return array(WC_Product_Addons_Api_V2_Global_Group)
	 * @throws \Exception Exception if the provided ID is invalid.
	 */
	public static function get_all() {
		$global_groups = array();
		$args          = array(
			'posts_per_page'   => -1,
			'orderby'          => 'title',
			'order'            => 'ASC',
			'post_type'        => 'global_product_addon',
			'post_status'      => 'any',
			'fields'           => 'ids',
			'suppress_filters' => true,
		);

		$global_group_posts = get_posts( $args );

		foreach ( (array) $global_group_posts as $id ) {
			$global_groups[] = new WC_Product_Addons_Api_V2_Global_Group( $id );
		}

		return $global_groups;
	}
}
