<?php
/**
 * HTML Code for the Partial Capture selection.
 *
 * @package Qliro_One_For_WooCommerce/Includes/Admin/Views
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

?>

<div class="wc-order-data-row wc-order-partial-capture wc-order-data-row-toggle" style="display: none;">
	<div class="clear"></div>
	<div class="capture-actions">
		<button type="button" class="button button-primary do-capture tips" data-tip="<?php esc_attr_e( 'This will create a partial capture for this order.', 'qliro-one-for-woocommerce' ); ?>"><?php printf( esc_html__( 'Create partial capture', 'qliro-one-for-woocommerce' ) ); ?></button>
		<button type="button" class="button cancel-action"><?php esc_html_e( 'Cancel', 'woocommerce' ); ?></button>
		<div class="clear"></div>
	</div>
</div>