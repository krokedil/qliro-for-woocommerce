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
	 * @param int|null $order_id The WooCommerce order id to create the Qliro One order for or null to create from the cart.
	 * @return array|WP_Error
	 */
	public function create_qliro_one_order( $order_id = null ) {
		$request  = new Qliro_One_Request_Create_Order( array( 'order_id' => $order_id ) );
		$response = $request->request();

		return $this->check_for_api_error( $response );
	}

	/**
	 * Gets a Qliro One Checkout order
	 *
	 * @param string $qliro_one_order_id The Qliro One Checkout order id.
	 * @return array|WP_Error
	 */
	public function get_qliro_one_order( $qliro_one_order_id ) {
		$request  = new Qliro_One_Request_Get_Order( array( 'qliro_order_id' => $qliro_one_order_id ) );
		$response = $request->request();
		return $this->check_for_api_error( $response );
	}

	/**
	 * Gets a Qliro One Admin order
	 *
	 * @param string $qliro_one_order_id The Qliro One Checkout order id.
	 * @return mixed
	 */
	public function get_qliro_one_admin_order( $qliro_one_order_id ) {
		$request  = new Qliro_One_Request_Admin_Get_Order( array( 'qliro_order_id' => $qliro_one_order_id ) );
		$response = $request->request();
		return $response;
	}


	/**
	 * Updates a Qliro One Checkout order.
	 *
	 * @param string $qliro_one_order_id The Qliro One Checkout order id.
	 * @param int    $order_id The WooCommerce order id.
	 * @param bool   $force If true always update the order, even if not needed.
	 * @return array|WP_Error
	 */
	public function update_qliro_one_order( $qliro_one_order_id, $order_id = null, $force = false ) {
		$request  = new Qliro_One_Request_Update_Order( array( 'qliro_order_id' => $qliro_one_order_id ) );
		$response = $request->request();
		return $this->check_for_api_error( $response );
	}

	/**
	 * Updates a Qliro One order during order management.
	 *
	 * @param string $qliro_one_order_id The Qliro One Checkout order id.
	 * @param int    $order_id The WooCommerce order id.
	 *
	 * @return array|WP_Error
	 */
	public function om_update_qliro_one_order( $qliro_one_order_id, $order_id ) {
		$request_id = ( new Qliro_One_Helper_Order() )->generate_request_id();
		$request    = new Qliro_One_Request_OM_Update_Order(
			array(
				'order_id'       => $order_id,
				'qliro_order_id' => $qliro_one_order_id,
				'request_id'     => $request_id,
			)
		);
		$response   = $request->request();
		return $this->check_for_api_error( $response );
	}

	/**
	 * Cancels a Qliro One order.
	 *
	 * @param int $order_id Order ID.
	 * @return array|WP_Error
	 */
	public function cancel_qliro_one_order( $order_id ) {
		$request_id = ( new Qliro_One_Helper_Order() )->generate_request_id();
		$request    = new Qliro_One_Cancel_Order(
			array(
				'order_id'   => $order_id,
				'request_id' => $request_id,
			)
		);
		$response   = $request->request();
		return $this->check_for_api_error( $response );
	}

	/**
	 * Capture a Qliro one order.
	 *
	 * @param int   $order_id Order ID.
	 * @param array $items Items to capture.
	 * @return array|WP_Error
	 */
	public function capture_qliro_one_order( $order_id, $items = array() ) {
		$request  = new Qliro_One_Capture_Order(
			array(
				'order_id' => $order_id,
				'items'    => $items,
			)
		);
		$response = $request->request();
		return $this->check_for_api_error( $response );
	}

	/**
	 * Refund a Qliro one order.
	 *
	 * @param int $order_id Order ID.
	 * @return array|WP_Error
	 */
	public function refund_qliro_one_order( $order_id, $refund_order_id, $capture_id = '', $items = array() ) {
		$request  = new Qliro_One_Request_Return_Items(
			array(
				'order_id'        => $order_id,
				'refund_order_id' => $refund_order_id,
				'capture_id'      => $capture_id,
				'items'           => $items,
			)
		);
		$response = $request->request();
		return $this->check_for_api_error( $response );
	}

	/**
	 * Refund a Qliro one order.
	 *
	 * @param int   $order_id Order ID.
	 * @param array $items Items to add.
	 * @return array|WP_Error
	 */
	public function add_items_qliro_order( $order_id, $items ) {
		$request  = new Qliro_One_Request_Add_Items(
			array(
				'order_id' => $order_id,
				'items'    => $items,
			)
		);
		$response = $request->request();
		return $this->check_for_api_error( $response );
	}

	/**
	 * Update the merchant references for a Qliro one order.
	 *
	 * @param int $order_id The WooCommerce Order ID.
	 * @return array|WP_Error
	 */
	public function update_qliro_one_merchant_reference( $order_id ) {
		$request  = new Qliro_One_Update_Merchant_Reference( array( 'order_id' => $order_id ) );
		$response = $request->request();
		return $this->check_for_api_error( $response );
	}

	/**
	 * Create a merchant payment for a Qliro recurring order.
	 *
	 * @param int         $order_id The WooCommerce order id.
	 * @param string|null $token The stored card token if available.
	 */
	public function create_merchant_payment( $order_id, $token = null ) {
		$request  = new Qliro_One_Request_Create_Merchant_Payment(
			array(
				'order_id' => $order_id,
				'token'    => $token,
			)
		);
		$response = $request->request();
		return $this->check_for_api_error( $response );
	}

	/**
	 * Upsell a Qliro one order.
	 *
	 * @param int    $order_id Order ID.
	 * @param string $upsell_request_id Id that upsell order lines is tagged with.
	 * @return array|WP_Error
	 */
	public function upsell_qliro_one_order( $order_id, $upsell_request_id ) {
		$request  = new Qliro_One_Request_Upsell_Order(
			array(
				'order_id'          => $order_id,
				'upsell_request_id' => $upsell_request_id,
			)
		);
		$response = $request->request();
		return $this->check_for_api_error( $response );
	}

	/**
	 * Checks for WP Errors and returns either the response as array or a false.
	 *
	 * @param object|WP_Error $response The response from the request.
	 * @return mixed
	 */
	private function check_for_api_error( $response ) {
		if ( is_wp_error( $response ) ) {
			if ( ! is_admin() && ! wp_is_serving_rest_request() ) {
				qliro_one_print_error_message( $response );
			}
		}
		return $response;
	}
}
