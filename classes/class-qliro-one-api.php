<?php
/**
 * API Class file.
 *
 * @package Qliro_One_For_WooCommerce/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Qliro_One_API class.
 *
 * Class that has functions for the Qliro One communication.
 */
class Qliro_One_API {
	/**
	 * Creates a Qliro One Checkout order.
	 *
	 * @param int $order_id The WooCommerce order id.
	 * @return mixed
	 */
	public function create_qliro_one_order( $order_id = false ) {
		$request  = new Qliro_One_Create_Order( array() );
		$response = $request->request();

		return $this->check_for_api_error( $response );
	}

	/**
	 * Gets a Qliro One Checkout order
	 *
	 * @param string $qliro_one_order_id The Qliro One Checkout order id.
	 * @return mixed
	 */
	public function get_qliro_one_order( $qliro_one_order_id ) {
		$request  = new Qliro_One_Request_Get_Order( array( 'order_id' => $qliro_one_order_id ) );
		$response = $request->request();
		return $this->check_for_api_error( $response );
	}


	/**
	 * Updates a Qliro One Checkout order.
	 *
	 * @param string $qliro_one_order_id The Qliro One Checkout order id.
	 * @param int    $order_id The WooCommerce order id.
	 * @param bool   $force If true always update the order, even if not needed.
	 * @return mixed
	 */
	public function update_qliro_one_order( $qliro_one_order_id, $order_id = null, $force = false ) {
		// todo add update request.
		$request  = new Qliro_One_Update_Order( array( 'order_id' => $qliro_one_order_id ) );
		$response = $request->request();
		return $this->check_for_api_error( $response );
	}

	/**
	 * Cancels a Qliro One order.
	 *
	 * @param int $order_id Order ID.
	 */
	public function cancel_qliro_one_order( $order_id ) {
		// todo.
		$request  = new Qliro_One_Cancel_Order( array( 'order_id' => $order_id ) );
		$response = $request->request();
		return $this->check_for_api_error( $response );
	}

	/**
	 * Capture a Qliro one order.
	 *
	 * @param int $order_id Order ID.
	 * @return array
	 */
	public function capture_qliro_one_order( $order_id ) {
		// todo.
		$request  = new Qliro_One_Capture_Order( array( 'order_id' => $order_id ) );
		$response = $request->request();
		return $this->check_for_api_error( $response );
	}

	/**
	 * Refund a Qliro one order.
	 *
	 * @param int $order_id Order ID.
	 * @return array
	 */
	public function refund_qliro_one_order( $order_id ) {
		// todo.
		$request  = new Qliro_One_Request_Return_Items( array( 'order_id' => $order_id ) );
		$response = $request->request();
		return $this->check_for_api_error( $response );
	}

	/**
	 * Update the merchant references for a Qliro one order.
	 *
	 * @param int $order_id Order ID.
	 * @return array
	 */
	public function update_qliro_one_merchant_reference( $order_id ) {
		$request  = new Qliro_One_Update_Merchant_Reference( array( 'order_id' => $order_id ) );
		$response = $request->request();
		return $this->check_for_api_error( $response );
	}

	/**
	 * Checks for WP Errors and returns either the response as array or a false.
	 *
	 * @param array $response The response from the request.
	 * @return mixed
	 */
	private function check_for_api_error( $response ) {
		if ( is_wp_error( $response ) ) {
			qliro_one_print_error_message( $response );
			return false;
		}
		return $response;
	}


}
