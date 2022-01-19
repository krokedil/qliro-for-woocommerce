<?php
/**
 * Handles the callbacks for the Qliro One integration.
 *
 * @package Qliro_One_For_WooCommerce/Classes
 */

/**
 * Class for handling the callbacks for the Qliro One integration
 */
class Qliro_One_Callbacks {
	/**
	 * Class constructor.
	 */
	public function __construct() {
		add_action( 'woocommerce_api_qoc_om_status', array( $this, 'om_push_cb' ) );
		$this->settings = get_option( 'woocommerce_qliro_one_settings' );
	}

	/**
	 * Handles the Order Management push callback.
	 *
	 * @return void
	 */
	public function om_push_cb() {
		$body = file_get_contents( 'php://input' );
		$data = json_decode( $body, true );

		Qliro_One_Logger::log( "Callback recieved: ${body}." );

		if ( isset( $data['PaymentType'] ) ) {
			$order_id = $data['MerchantReference'];
			switch ( $data['PaymentType'] ) {
				case 'Capture':
					Qliro_One_Logger::log( "Processing capture callback for order ${order_id}." );
					$this->complete_capture( $order_id, $data );
					break;
				case 'Reversal':
					Qliro_One_Logger::log( "Processing cancel callback for order ${order_id}." );
					$this->complete_cancel( $order_id, $data );
					break;
				case 'Refund':
					Qliro_One_Logger::log( "Processing refund callback for order ${order_id}." );
					$this->complete_refund( $order_id, $data );
					break;
				default:
					$status = $data['PaymentType'];
					Qliro_One_Logger::log( "Unhandled callback for order ${order_id}. Callback type: ${status}" );
					break;
			}
		}
		header( 'HTTP/1.1 200 OK' );
		echo '{ "CallbackResponse": "received" }';
		die();
	}

	/**
	 * Process the Capture callback notification.
	 *
	 * @param int   $order_id The WooCommerce Order ID.
	 * @param array $data The data from the callback from Qliro.
	 * @return void
	 */
	public function complete_capture( $order_id, $data ) {
		$order = wc_get_order( $order_id );
		if ( 'Success' !== $data['Status'] ) {
			$order->update_status( 'on-hold', __( 'The order failed to be captured by Qliro.', 'qliro-one-for-woocommerce' ) );
			return;
		}

		if ( 'none' === $this->settings['capture_ok_status'] ) {
			$order->add_order_note( __( 'The order has been successfully captured by Qliro.', 'qliro-one-for-woocommerce' ) );
			return;
		}

		$order->update_status( $this->settings['capture_ok_status'], __( 'The order has been successfully captured by Qliro.', 'qliro-one-for-woocommerce' ) );
	}

	/**
	 * Process the Cancel callback notification.
	 *
	 * @param int   $order_id The WooCommerce Order ID.
	 * @param array $data The data from the callback from Qliro.
	 * @return void
	 */
	public function complete_cancel( $order_id, $data ) {
		$order = wc_get_order( $order_id );
		if ( 'Success' !== $data['Status'] ) {
			$order->update_status( 'on-hold', __( 'The order failed to be cancelled by Qliro.', 'qliro-one-for-woocommerce' ) );
			return;
		}

		if ( 'none' === $this->settings['cancel_ok_status'] ) {
			$order->add_order_note( __( 'The order has been successfully cancelled by Qliro.', 'qliro-one-for-woocommerce' ) );
			return;
		}

		$order->update_status( $this->settings['cancel_ok_status'], __( 'The order has been successfully cancelled by Qliro.', 'qliro-one-for-woocommerce' ) );
	}

	/**
	 * Process the REfund callback notification.
	 *
	 * @param int   $order_id The WooCommerce Order ID.
	 * @param array $data The data from the callback from Qliro.
	 * @return void
	 */
	public function complete_refund( $order_id, $data ) {
		$order = wc_get_order( $order_id );
		if ( 'Success' !== $data['Status'] ) {
			$order->update_status( 'on-hold', __( 'The order failed to be refunded by Qliro.', 'qliro-one-for-woocommerce' ) );
			return;
		}

		$order->add_order_note( __( 'The order has been successfully refunded by Qliro.' ) );
	}
} new Qliro_One_Callbacks();
