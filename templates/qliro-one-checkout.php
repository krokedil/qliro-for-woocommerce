<?php
/**
 * Qliro One Checkout page
 *
 * Overrides /checkout/form-checkout.php.
 *
 * @package Qliro_One_For_WooCommerce/Templates
 */


do_action( 'woocommerce_before_checkout_form', WC()->checkout() );

// If checkout registration is disabled and not logged in, the user cannot checkout.
if ( ! $checkout->is_registration_enabled() && $checkout->is_registration_required() && ! is_user_logged_in() ) {
	echo esc_html( apply_filters( 'woocommerce_checkout_must_be_logged_in_message', __( 'You must be logged in to checkout.', 'woocommerce' ) ) );
	return;
}

?>

<form name="checkout" class="checkout woocommerce-checkout">
	<?php do_action( 'qliro_one_wc_before_wrapper' ); ?>
	<div id="qliro-one-wrapper">
		<div id="qliro-one-order-review">
			<?php do_action( 'qliro_one_wc_before_order_review' ); ?>
			<?php woocommerce_order_review(); ?>
			<?php do_action( 'qliro_one_wc_after_order_review' ); ?>
		</div>
		<div id="qliro-one-iframe-wrapper">
			<?php do_action( 'qliro_one_wc_before_snippet' ); ?>
			<?php qliro_one_wc_show_snippet(); ?>
			<?php do_action( 'qliro_one_wc_after_snippet' ); ?>
		</div>
	</div>
	<?php do_action( 'qliro_one_wc_after_wrapper' ); ?>
</form>
<?php do_action( 'qliro_one_wc_after_checkout_form' ); ?>