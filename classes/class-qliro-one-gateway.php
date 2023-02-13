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
				'upsell',
			)
		);
		$this->has_fields         = false;
		$this->init_form_fields();
		$this->init_settings();
		$this->title             = $this->get_option( 'title' );
		$this->description       = $this->get_option( 'description' );
		$this->enabled           = $this->get_option( 'enabled' );
		$this->testmode          = 'yes' === $this->get_option( 'testmode' );
		$this->logging           = 'yes' === $this->get_option( 'logging' );
		$this->upsell_percentage = $this->get_option( 'upsell_percentage', 10 );
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
		// Try to get qliro order id from wc session.
		$qliro_order_id           = WC()->session->get( 'qliro_one_order_id' );
		$qliro_confirmation_id    = WC()->session->get( 'qliro_order_confirmation_id' );
		$qliro_merchant_reference = WC()->session->get( 'qliro_one_merchant_reference' );
		$chosen_shipping_method   = WC()->session->get( 'chosen_shipping_methods', null );
		update_post_meta( $order_id, '_qliro_one_order_id', $qliro_order_id );
		update_post_meta( $order_id, '_qliro_one_order_confirmation_id', $qliro_confirmation_id );
		update_post_meta( $order_id, '_qliro_one_merchant_reference', $qliro_merchant_reference );
		// We need to save the shipping reference to the order as well, since table rate shipping can add a 3rd param to the instance id which is not saved to the order.
		update_post_meta( $order_id, '_qliro_one_shipping_reference', $chosen_shipping_method[0] );


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
		$qliro_order    = qoc_get_thankyou_page_qliro_order( $qliro_order_id );
		$order          = wc_get_order( $order_id );
		// Check if the order has been confirmed already.
		if ( ! empty( $order->get_date_paid() ) ) {
			$result = qliro_confirm_order( $order );

			if( $result ) {
				Qliro_One_Logger::log( "Order $order_id confirmed on the thankyou page. Qliro Order ID: $qliro_order_id." );
			}
		}

		if ( $qliro_order && ! is_wp_error( $qliro_order ) ) {
			echo $qliro_order['OrderHtmlSnippet']; // phpcs:ignore WordPress.Security.EscapeOutput -- Cant escape since this is the iframe snippet.
		}
	}

	/**
	 * Check the qliro order if upsell should be available.
	 *
	 * @param int $order_id The WooCommerce order id.
	 * @return bool
	 */
	public function upsell_available( $order_id ) {
		$order = wc_get_order( $order_id );

		// If the order has not been paid for, and is in for example on-hold. We can not do a upsell.
		if ( empty( $order->get_date_paid() ) ) {
			return false;
		}

		$qliro_order_id = get_post_meta( $order_id, '_qliro_one_order_id', true );
		$qliro_order    = qoc_get_thankyou_page_qliro_order( $qliro_order_id );

		// Check if we have a urgency time.
		$urgency_deadline = get_post_meta( $order_id, '_ppu_upsell_urgency_deadline', true );

		if ( empty( $urgency_deadline ) ) {
			return false;
		}

		// Check if the urgency time has passed.
		if ( $urgency_deadline < strtotime( 'now' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Handles a upsell request.
	 *
	 * @param int    $order_id The WooCommerce order id.
	 * @param string $upsell_uuid The UUID for the Upsell request.
	 * @return bool
	 */
	public function upsell( $order_id, $upsell_uuid ) {
		$upsell_order = QOC_WC()->api->upsell_qliro_one_order( $order_id, $upsell_uuid );

		if ( is_wp_error( $upsell_order ) ) {
			return $upsell_order;
		}

		$order = wc_get_order( $order_id );
		update_post_meta( $order_id, '_qliro_payment_transaction_id', $upsell_order['PaymentTransactionId'] );
		$order->add_order_note( __( 'Qliro order was upsold with transaction id', 'qliro-for-woocommerce' ) . ": {$upsell_order['PaymentTransactionId']}" );

		return true;
	}

	/**
	 * Get the limits for the upsell order.
	 *
	 * @param int $order_id The WooCommerce order id.
	 * @return array
	 */
	public function get_upsell_limitations( $order_id ) {
		$limits              = array(
			'amount' => 0,
		);
		$order               = wc_get_order( $order_id );
		$payment_method_type = get_post_meta( $order_id, 'qliro_one_payment_method_name', true );
		$is_qliro_method     = str_contains( $payment_method_type, 'QLIRO' );
		$is_card_method      = str_contains( $payment_method_type, 'CARD' );

		if ( $is_qliro_method && ! $is_card_method ) {
			$limits['amount'] = $order->get_total() * ( $this->upsell_percentage / 100 );
		}

		if ( ! $is_qliro_method && $is_card_method ) {
			$amount = 300;
			switch ( $order->get_currency() ) {
				case 'EUR':
				case 'USD':
				case 'GBP':
					$amount = 30;
					break;
			}
			$limits['amount'] = $amount;
		}

		return $limits;
	}
}
