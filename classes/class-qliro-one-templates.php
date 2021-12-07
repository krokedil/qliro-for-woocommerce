<?php
/**
 * Templates class for Qliro One checkout.
 *
 * @package  Qliro_One_For_WooCommerce/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 *  Qliro_One_Templates class.
 */
class Qliro_One_Templates {

	/**
	 * The reference the *Singleton* instance of this class.
	 *
	 * @var $instance
	 */
	protected static $instance;

	/**
	 * Returns the *Singleton* instance of this class.
	 *
	 * @return self::$instance The *Singleton* instance.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Plugin actions.
	 */
	public function __construct() {
		// Override template if Qliro One Checkout page.
		add_filter( 'wc_get_template', array( $this, 'override_template' ), 999, 2 );
	}

	/**
	 * Override checkout form template if Qliro One Checkout is the selected payment method.
	 *
	 * @param string $template      Template.
	 * @param string $template_name Template name.
	 *
	 * @return string
	 */
	public function override_template( $template, $template_name ) {
		if ( is_checkout() ) {
			$confirm = filter_input( INPUT_GET, 'confirm', FILTER_SANITIZE_STRING );
			// Don't display Qliro One template if we have a cart that doesn't needs payment.
			if ( apply_filters( 'qliro_one_check_if_needs_payment', true ) && ! is_wc_endpoint_url( 'order-pay' ) ) {
				if ( ! WC()->cart->needs_payment() ) {
					return $template;
				}
			}

			// Klarna Checkout.
			if ( 'checkout/form-checkout.php' === $template_name ) {
				$available_gateways = WC()->payment_gateways()->get_available_payment_gateways();

				if ( locate_template( 'woocommerce/qliro-one-checkout.php' ) ) {
					$qliro_one_checkout_template = locate_template( 'woocommerce/qliro-one-checkout.php' );
				} else {
					$qliro_one_checkout_template = QLIRO_WC_PLUGIN_PATH . '/templates/qliro-one-checkout.php';
				}

				// Klarna checkout page.
				if ( array_key_exists( 'qliro_one', $available_gateways ) ) {
					// If chosen payment method exists.
					if ( 'qliro_one' === WC()->session->get( 'chosen_payment_method' ) ) {
						if ( empty( $confirm ) ) {
							$template = $qliro_one_checkout_template;
						}
					}

					// If chosen payment method does not exist and Qliro One is the first gateway.
					if ( null === WC()->session->get( 'chosen_payment_method' ) || '' === WC()->session->get( 'chosen_payment_method' ) ) {
						reset( $available_gateways );

						if ( 'qliro_one' === key( $available_gateways ) ) {
							if ( empty( $confirm ) ) {
								$template = $qliro_one_checkout_template;
							}
						}
					}

					// If another gateway is saved in session, but has since become unavailable.
					if ( WC()->session->get( 'chosen_payment_method' ) ) {
						if ( ! array_key_exists( WC()->session->get( 'chosen_payment_method' ), $available_gateways ) ) {
							reset( $available_gateways );

							if ( 'qliro_one' === key( $available_gateways ) ) {
								if ( empty( $confirm ) ) {
									$template = $qliro_one_checkout_template;
								}
							}
						}
					}
				}
			}

			// Klarna Checkout Pay for order.
			if ( 'checkout/form-pay.php' === $template_name ) {
				global $wp;
				$order_id           = $wp->query_vars['order-pay'];
				$order              = wc_get_order( $order_id );
				$available_gateways = WC()->payment_gateways()->get_available_payment_gateways();
				if ( array_key_exists( 'qliro_one', $available_gateways ) ) {
					if ( locate_template( 'woocommerce/klarna-checkout-pay.php' ) ) {
						$qliro_one_checkout_template = locate_template( 'woocommerce/qliro-one-checkout-pay.php' );
					} else {
						$qliro_one_checkout_template = QLIRO_WC_PLUGIN_PATH . '/templates/qliro-one-checkout-pay.php';
					}

					if ( 'qliro_one' === $order->get_payment_method() ) {
						$confirm = filter_input( INPUT_GET, 'confirm', FILTER_SANITIZE_STRING );
						if ( empty( $confirm ) ) {
							$template = $qliro_one_checkout_template;
						}
					}

					// If chosen payment method does not exist and Qliro One is the first gateway.
					if ( empty( $order->get_payment_method() ) ) {
						reset( $available_gateways );
						if ( 'qliro_one' === key( $available_gateways ) ) {
							if ( empty( $confirm ) ) {
								$template = $qliro_one_checkout_template;
							}
						}
					}
				}
			}
		}

		return $template;
	}

}

Qliro_One_Templates::get_instance();
