<?php
/**
 * Class file for Qliro_One_Gateway class.
 *
 * @package Qliro_One_For_WooCommerce/Classes
 */

/**
 * Class Qliro_One_Gateway
 */
class Qliro_One_Gateway extends WC_Payment_Gateway {

	/**
	 * Class constructor.
	 */
	public function __construct() {
		$this->id                 = 'qliro_one';
		$this->method_title       = __( 'Qliro One', 'qliro-one-for-woocommerce' );
		$this->method_description = __( 'Qliro One replaces the standard WooCommerce checkout page.', 'qliro-one-for-woocommerce' );
		$this->supports           = apply_filters(
			'qliro_one_gateway_supports',
			array(
				'products',
				'refunds',
			)
		);
		$this->has_fields         = false;
		$this->init_form_fields();
		$this->init_settings();
		$this->title       = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );
		$this->enabled     = $this->get_option( 'enabled' );
		$this->testmode    = 'yes' === $this->get_option( 'testmode' );
		$this->logging     = 'yes' === $this->get_option( 'logging' );
		add_action(
			'woocommerce_update_options_payment_gateways_qliro_one',
			array(
				$this,
				'process_admin_options',
			)
		);
		add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'show_thank_you_snippet' ) );
	}


	/**
	 * Initialise settings fields.
	 */
	public function init_form_fields() {
		$this->form_fields = Qliro_One_Fields::fields();
	}

	/**
	 * Checks if method should be available.
	 *
	 * @return boolean
	 */
	public function is_available() {
		return ! ( 'yes' !== $this->enabled );
	}

	/**
	 * Process the payment and return the result.
	 *
	 * @param  int $order_id WooCommerce order ID.
	 *
	 * @return array
	 */
	public function process_payment( $order_id ) {
		// try to get qliro order id from wc session.
		$qliro_order_id        = WC()->session->get( 'qliro_one_order_id' );
		$qliro_confirmation_id = WC()->session->get( 'qliro_order_confirmation_id' );
		update_post_meta( $order_id, '_qliro_one_order_id', $qliro_order_id );
		update_post_meta( $order_id, '_qliro_one_order_confirmation_id', $qliro_confirmation_id );
		return array(
			'result' => 'success',
		);

	}

	/** Process refund request.
	 *
	 * @param int    $order_id The WooCommerce order ID.
	 * @param float  $amount The amount to be refunded.
	 * @param string $reason The reason given for the refund.
	 *
	 * @return bool|void
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		return QOC_WC()->order_management->refund( $order_id, $amount );
	}

	/**
	 * Print the iframe on the thankyou page.
	 *
	 * @param int $order_id The WooCommerce order id.
	 * @return void
	 */
	public function show_thank_you_snippet( $order_id = null ) {
		$qliro_order_id = get_post_meta( $order_id, '_qliro_one_order_id', true );
		$qliro_order    = QOC_WC()->api->get_qliro_one_order( $qliro_order_id );
		$order          = wc_get_order( $order_id );
		qliro_confirm_order( $order );

		if ( $qliro_order ) {
			echo $qliro_order['OrderHtmlSnippet']; // phpcs:ignore WordPress.Security.EscapeOutput -- Cant escape since this is the iframe snippet.
		}
	}
}
