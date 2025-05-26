<?php
/**
 * Rest API v2 Product Group helper.
 *
 * @package Automattic\WooCommerce\ProductAddons
 * @since 6.9.0
 */

/**
 * WC_Product_Addons_Api_V2_Product_Group class.
 */
class WC_Product_Addons_Api_V2_Product_Group extends WC_Product_Addons_Api_V2_Abstract_Group {

	/**
	 * Exclude global add-ons.
	 *
	 * @var bool
	 */
	protected $exclude_global_add_ons = false;

	/**
	 * Get exclude_global_add_ons.
	 *
	 * @return bool
	 */
	public function get_exclude_global_add_ons() {
		return $this->exclude_global_add_ons;
	}

	/**
	 * Set exclude_global_add_ons.
	 *
	 * @param bool $value Value to set.
	 */
	public function set_exclude_global_add_ons( bool $value ) {
		$this->exclude_global_add_ons = $value;
	}

	/**
	 * Constructor.
	 *
	 * @throws \Exception Exception if the provided ID is invalid.
	 * @param integer $id Product ID. 0 if the product does not yet exist.
	 */
	public function __construct( int $id = 0 ) {
		$this->id = $id;

		if ( $this->id ) {
			$product = wc_get_product( $this->id );

			if ( ! $product || ! $product->get_id() ) {
				throw new \Exception( 'WC_Product_Addons_Api_V2_Product_Group::Invalid product ID' );
			}

			$this->set_name( $product->get_name() );
			$this->set_exclude_global_add_ons( ! empty( $product->get_meta( '_product_addons_exclude_global' ) ) );
			$this->set_fields_from_db( $product->get_meta( '_product_addons' ) );
		}
	}

	/**
	 * Check if product add-ons have already been set for the product.
	 *
	 * @param integer $product_id Product ID.
	 * @return bool
	 * @throws \Exception Exception if the provided ID is invalid.
	 */
	public static function addons_already_set( int $product_id ) {
		if ( ! $product_id ) {
			throw new \Exception( 'WC_Product_Addons_Api_V2_Product_Group::Invalid product ID' );
		}

		$product = wc_get_product( $product_id );

		if ( ! $product || ! $product->get_id() ) {
			throw new \Exception( 'WC_Product_Addons_Api_V2_Product_Group::Invalid product ID' );
		}

		if ( $product->get_meta( '_product_addons' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Persist data.
	 *
	 * @throws \Exception Exception if the provided ID is invalid.
	 */
	public function save() {
		$product = wc_get_product( $this->get_id() );

		if ( ! $product || ! $product->get_id() ) {
			throw new \Exception( 'WC_Product_Addons_Api_V2_Product_Group::Invalid product ID' );
		}

		$product->update_meta_data( '_product_addons_exclude_global', $this->get_exclude_global_add_ons() ? 1 : 0 );
		$product->update_meta_data( '_product_addons', $this->get_fields_for_db() );
		$product->save_meta_data();
	}

	/**
	 * Get priority.
	 *
	 * Product specific Add-On's priority can't be changed and isn't exposed to the user.
	 *
	 * @return string
	 */
	public function get_priority() {
		return 10;
	}
}
