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
			$order_number = $data['MerchantReference'];
			switch ( $data['PaymentType'] ) {
				case 'Capture':
					Qliro_One_Logger::log( "Processing capture callback for order ${order_number}." );
					$this->complete_capture( $order_number, $data );
					break;
				case 'Reversal':
					Qliro_One_Logger::log( "Processing cancel callback for order ${order_number}." );
					$this->complete_cancel( $order_number, $data );
					break;
				case 'Refund':
					Qliro_One_Logger::log( "Processing refund callback for order ${order_number}." );
					$this->complete_refund( $order_number, $data );
					break;
				default:
					$status = $data['PaymentType'];
					Qliro_One_Logger::log( "Unhandled callback for order ${order_number}. Callback type: ${status}" );
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
	 * @param string $order_number The WooCommerce Order Number.
	 * @param array  $data The data from the callback from Qliro.
	 * @return void
	 */
	public function complete_capture( $order_number, $data ) {
		$order = $this->get_woocommerce_order( $order_number );

		if ( empty( $order ) ) {
			return;
		}

		if ( 'Success' !== $data['Status'] ) {
			$order->update_status( 'on-hold', __( 'The order failed to be captured by Qliro.', 'qliro-one-for-woocommerce' ) );
			$order->save();
			return;
		}

		if ( 'none' === $this->settings['capture_ok_status'] ) {
			$order->add_order_note( __( 'The order has been successfully captured by Qliro.', 'qliro-one-for-woocommerce' ) );
			$order->save();
			return;
		}

		$order->update_status( $this->settings['capture_ok_status'], __( 'The order has been successfully captured by Qliro.', 'qliro-one-for-woocommerce' ) );
		$order->save();
	}

	/**
	 * Process the Cancel callback notification.
	 *
	 * @param string $order_number The WooCommerce Order Number.
	 * @param array  $data The data from the callback from Qliro.
	 * @return void
	 */
	public function complete_cancel( $order_number, $data ) {
		$order = $this->get_woocommerce_order( $order_number );

		if ( empty( $order ) ) {
			return;
		}

		if ( 'Success' !== $data['Status'] ) {
			$order->update_status( 'on-hold', __( 'The order failed to be cancelled by Qliro.', 'qliro-one-for-woocommerce' ) );
			$order->save();
			return;
		}

		if ( 'none' === $this->settings['cancel_ok_status'] ) {
			$order->add_order_note( __( 'The order has been successfully cancelled by Qliro.', 'qliro-one-for-woocommerce' ) );
			$order->save();
			return;
		}

		$order->update_status( $this->settings['cancel_ok_status'], __( 'The order has been successfully cancelled by Qliro.', 'qliro-one-for-woocommerce' ) );
		$order->save();
	}

	/**
	 * Process the Refund callback notification.
	 *
	 * @param string $order_number The WooCommerce Order Number.
	 * @param array  $data The data from the callback from Qliro.
	 * @return void
	 */
	public function complete_refund( $order_number, $data ) {
		$order = $this->get_woocommerce_order( $order_number );

		if ( empty( $order ) ) {
			return;
		}

		if ( 'Success' !== $data['Status'] ) {
			$order->update_status( 'on-hold', __( 'The order failed to be refunded by Qliro.', 'qliro-one-for-woocommerce' ) );
			return;
		}

		$order->add_order_note( __( 'The order has been successfully refunded by Qliro.' ) );
	}

	/**
	 * Gets an order by order number.
	 *
	 * @param string $order_number The WooCommerce Order Number.
	 * @return WC_Order
	 */
	public function get_woocommerce_order( $order_number ) {
		// Try to get order if we can.
		$order = wc_get_order( $order_number );
		if ( ! empty( $order ) ) {
			return $order;
		}

		$query_args = array(
			'fields'      => 'ids',
			'post_type'   => wc_get_order_types(),
			'post_status' => array_keys( wc_get_order_statuses() ),
			'meta_key'    => '_order_number', // phpcs:ignore WordPress.DB.SlowDBQuery -- Slow DB Query is ok here, we need to limit to our meta key.
			'meta_value'  => $order_number, // phpcs:ignore WordPress.DB.SlowDBQuery -- Slow DB Query is ok here, we need to limit to our meta key.
		);

		$order_ids = get_posts( $query_args );

		// If zero matching orders were found, log error.
		if ( empty( $order_ids ) ) {
			// Backup order creation.
			Qliro_One_Logger::log( 'Callback Error. No order found with the order number ' . stripslashes_deep( wp_json_encode( $order_number ) ) );
			return;
		}

		$order_id = $order_ids[0];
		$order    = wc_get_order( $order_id );

		return $order;
	}
} new Qliro_One_Callbacks();
