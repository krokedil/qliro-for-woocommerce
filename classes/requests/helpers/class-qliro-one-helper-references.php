<?php
/**
 *Helps formatting Merchant References to ensure consistency between Cart and Order usage.
 *
 * @package Qliro_One_For_WooCommerce/Classes/Requests/Helpers
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Qliro_One_Helper_References {

    /**
     * @param object $product The WooCommerce Product.
     * @return string
     */
    public static function get_product_reference( $product )
    {
        if ( $product->get_sku() ) {
            $item_reference = $product->get_sku();
        } else {
            $item_reference = $product->get_id();
        }

        return $item_reference;
    }

}
