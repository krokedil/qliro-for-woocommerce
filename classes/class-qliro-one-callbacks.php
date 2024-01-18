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
	 * The settings for the plugin.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Class constructor.
	 */
	public function __construct() {
		add_action( 'woocommerce_api_qoc_om_status', array( $this, 'om_push_cb' ) );
		add_action( 'woocommerce_api_qoc_checkout_status', array( $this, 'checkout_push_cb' ) );
		add_action( 'qliro_complete_checkout', array( $this, 'complete_checkout' ), 10 );
		add_action( 'qliro_fail_checkout', array( $this, 'fail_checkout' ), 10 );
		add_action( 'qliro_onhold_checkout', array( $this, 'onhold_checkout' ), 10 );
		$this->settings = get_option( 'woocommerce_qliro_one_settings' );
	}

	/**
	 * Handles the Order Management push callback.
	 *
	 * @return void
	 */
	public function om_push_cb() {
		$body            = file_get_contents( 'php://input' );
		$confirmation_id = filter_input( INPUT_GET, 'qliro_one_confirm_id', FILTER_SANITIZE_SPECIAL_CHARS );
		$data            = json_decode( $body, true );

		Qliro_One_Logger::log( "OM Callback recieved: {$body}." );

		if ( isset( $data['PaymentType'] ) ) {
			$order_number = $data['MerchantReference'];
			switch ( $data['PaymentType'] ) {
				case 'Capture':
					Qliro_One_Logger::log( "Processing capture callback for order {$order_number}." );
					$this->complete_capture( $confirmation_id, $data );
					break;
				case 'Reversal':
					Qliro_One_Logger::log( "Processing cancel callback for order {$order_number}." );
					$this->complete_cancel( $confirmation_id, $data );
					break;
				case 'Refund':
					Qliro_One_Logger::log( "Processing refund callback for order {$order_number}." );
					$this->complete_refund( $confirmation_id, $data );
					break;
				default:
					$status = $data['PaymentType'];
					Qliro_One_Logger::log( "Unhandled callback for order {$order_number}. Callback type: {$status}" );
					break;
			}
		}
		header( 'HTTP/1.1 200 OK' );
		echo '{ "CallbackResponse": "received" }';
		die();
	}

	/**
	 * Handles the Checkout push callback.
	 *
	 * @return void
	 */
	public function checkout_push_cb() {
		$body            = file_get_contents( 'php://input' );
		$confirmation_id = filter_input( INPUT_GET, 'qliro_one_confirm_id', FILTER_SANITIZE_SPECIAL_CHARS );
		$data            = json_decode( $body, true );

		Qliro_One_Logger::log( "Checkout Callback recieved: {$body}." );

		if ( isset( $data['Status'] ) ) {
			switch ( $data['Status'] ) {
				case 'Completed':
					Qliro_One_Logger::log( "Scheduling completed checkout callback for order with confirmation_id {$confirmation_id}." );
					as_schedule_single_action( time() + 30, 'qliro_complete_checkout', array( $confirmation_id ) );
					break;
				case 'Refused':
					Qliro_One_Logger::log( "Scheduling refused callback for order with confirmation_id {$confirmation_id}." );
					as_schedule_single_action( time() + 30, 'qliro_fail_checkout', array( $confirmation_id ) );
					break;
				case 'OnHold':
					Qliro_One_Logger::log( "Scheduling onhold callback for order with confirmation_id {$confirmation_id}." );
					as_schedule_single_action( time() + 30, 'qliro_onhold_checkout', array( $confirmation_id ) );
					break;
				default:
					Qliro_One_Logger::log( "Unknown Qliro One checkout callback status: {$data['Status']}" );
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
	 * @param string $confirmation_id The confirmation ID generated in the create call.
	 * @param array  $data The data from the callback from Qliro.
	 * @return void
	 */
	public function complete_capture( $confirmation_id, $data ) {
		$order = $this->get_woocommerce_order( $confirmation_id );

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
	 * @param string $confirmation_id The confirmation ID generated in the create call.
	 * @param array  $data The data from the callback from Qliro.
	 * @return void
	 */
	public function complete_cancel( $confirmation_id, $data ) {
		$order = $this->get_woocommerce_order( $confirmation_id );

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
	 * @param string $confirmation_id The confirmation ID generated in the create call.
	 * @param array  $data The data from the callback from Qliro.
	 * @return void
	 */
	public function complete_refund( $confirmation_id, $data ) {
		$order = $this->get_woocommerce_order( $confirmation_id );

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
	 * Process the successful callback from the checkout push.
	 *
	 * @param string $confirmation_id The confirmation ID generated in the create call.
	 * @return void
	 */
	public function complete_checkout( $confirmation_id ) {

		Qliro_One_Logger::log( "Execute completed checkout callback for order with confirmation_id {$confirmation_id}." );

		$order = $this->get_woocommerce_order( $confirmation_id );

		if ( empty( $order ) ) {
			Qliro_One_Logger::log( "Could not find an order with the confirmation id $confirmation_id when completing the checkout" );
			return;
		}

		qliro_confirm_order( $order );
	}

	/**
	 * Process the failed callback from the checkout push.
	 *
	 * @param string $confirmation_id The confirmation ID generated in the create call.
	 * @return void
	 */
	public function fail_checkout( $confirmation_id ) {

		Qliro_One_Logger::log( "Execute refused callback for order with confirmation_id {$confirmation_id}." );

		$order = $this->get_woocommerce_order( $confirmation_id );

		if ( empty( $order ) ) {
			Qliro_One_Logger::log( "Could not find an order with the confirmation id $confirmation_id when failing the checkout" );
			return;
		}

		$order->update_status( 'failed', __( 'The Qliro one order was rejected by Qliro.', 'qliro-checkout-for-woocommerce' ) );
		$order->save();
	}

	/**
	 * Process the onhold callback from the checkout push.
	 *
	 * @param string $confirmation_id The confirmation ID generated in the create call.
	 * @return void
	 */
	public function onhold_checkout( $confirmation_id ) {

		Qliro_One_Logger::log( "Execute onhold callback for order with confirmation_id {$confirmation_id}." );

		$order = $this->get_woocommerce_order( $confirmation_id );

		if ( empty( $order ) ) {
			Qliro_One_Logger::log( "Could not find an order with the confirmation id $confirmation_id when failing the checkout" );
			return;
		}

		// Do not put the order to on on-hold if it has been processed. This can happen if the on-hold push happens too late, after the order is successfully completed.
		if ( ! empty( $order->get_date_paid() ) ) {
			// translators: %s - WooCommerce order number, %s - Qliro Confirmation ID.
			Qliro_One_Logger::log( sprintf( __( 'Aborting onhold_checkout function. WooCommerce order %1$s with confirmation_id %2$s already confirmed.', 'qliro-one-for-woocommerce' ), $order->get_order_number(), $confirmation_id ) );
			return;
		}

		$order->update_status( 'on-hold', __( 'The Qliro order is on-hold and awaiting a status update from Qliro.', 'qliro-checkout-for-woocommerce' ) );
		$order->save();
	}

	/**
	 * Gets an order by order number.
	 *
	 * @param string $confirmation_id The Confirmation ID set when we create the order.
	 * @return WC_Order
	 */
	public function get_woocommerce_order( $confirmation_id ) {
		$query_args = array(
			'fields'      => 'ids',
			'post_type'   => wc_get_order_types(),
			'post_status' => array_keys( wc_get_order_statuses() ),
			'meta_key'    => '_qliro_one_order_confirmation_id', // phpcs:ignore WordPress.DB.SlowDBQuery -- Slow DB Query is ok here, we need to limit to our meta key.
			'meta_value'  => $confirmation_id, // phpcs:ignore WordPress.DB.SlowDBQuery -- Slow DB Query is ok here, we need to limit to our meta key.
		);

		$order_ids = get_posts( $query_args );

		// If zero matching orders were found, log error.
		if ( empty( $order_ids ) ) {
			Qliro_One_Logger::log( "Callback Error. No order found with the confirmation id $confirmation_id" );
			return;
		}

		$order_id = $order_ids[0];
		$order    = wc_get_order( $order_id );

		return $order;
	}
} new Qliro_One_Callbacks();
