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

    /**
     * @param $name The name of the fee
     * @return string
     */
    public static function get_fee_reference( $name )
    {
        //We have no real identifier from WooCommerce that can be reliably used. The name
        //might contain forbidden characters and might exceed the max-length from Qliro.
        //By using MD5 we make sure that we always create strings that pass the regex.
        return md5( sanitize_title_with_dashes( $name ) );
    }

}
