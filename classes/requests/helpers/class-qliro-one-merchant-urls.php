<?php
/**
 * Class that formats merchant URLs for Qliro One API.
 *
 * @package Qliro_One_For_WooCommerce/Classes/Requests/Helpers
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Qliro_One_Merchant_URLs class.
 *
 * Class that formats gets merchant URLs Qliro One API.
 */
class Qliro_One_Merchant_URLS {

	/**
	 * Gets formatted merchant URLs array.
	 *
	 * @param WC_Order|int $order The WooCommerce order or order id.
	 * @return array
	 */
	public function get_urls( $order = null ) {
		// If the order is not null, but not an instance of WC_Order, try to get the order object.
		if ( $order !== null && ! $order instanceof WC_Order ) {
			$order = wc_get_order( $order );
		}

		// Generate a random string to use as confirmation id, following the UUID format.
		$rand_string = strtolower(
			sprintf(
				'%04X%04X-%04X-%04X-%04X-%04X%04X%04X',
				random_int( 0, 65535 ),
				random_int( 0, 65535 ),
				random_int( 0, 65535 ),
				random_int( 16384, 20479 ),
				random_int( 32768, 49151 ),
				random_int( 0, 65535 ),
				random_int( 0, 65535 ),
				random_int( 0, 65535 )
			)
		);

		// If the order is null, set the confirmation id to the session, else store it in the order meta.
		if ( null === $order ) {
			WC()->session->set( 'qliro_order_confirmation_id', $rand_string );
		} else {
			$order->update_meta_data( '_qliro_one_order_confirmation_id', $rand_string );
			$order->save();
		}

		$merchant_urls = array(
			'terms'        => $this->get_terms_url(),
			'confirmation' => $this->get_confirmation_url( $rand_string, $order ),
			'push'         => $this->get_push_url( $rand_string ),
			'om_push'      => $this->get_om_push_url( $rand_string ),
		);

		// If the cart contains a subscription, add the save card callback url.
		if ( Qliro_One_Subscriptions::is_subscription( $order ) ) {
			$merchant_urls['save_card'] = QOC_WC()->api_registry()->get_request_path( Qliro_One_API_Controller_Save_Card::class, 'save-card' );
		}

		return apply_filters( 'qliro_one_wc_merchant_urls', $merchant_urls );
	}

	/**
	 * Terms URL.
	 *
	 * Required. URL of merchant terms and conditions. Should be different than checkout, confirmation and push URLs.
	 *
	 * @return string
	 */
	private function get_terms_url() {
		$terms_url = get_permalink( wc_get_page_id( 'terms' ) );

		return apply_filters( 'qliro_one_wc_terms_url', $terms_url );
	}

	/**
	 * Confirmation URL.
	 *
	 * Required. URL of merchant confirmation page. Should be different than checkout and confirmation URLs.
	 *
	 * @param string   $rand_string A random string generated on creation that will follow the entire order process.
	 * @param WC_Order $order The WooCommerce order if available.
	 * @return string
	 */
	private function get_confirmation_url( $rand_string, $order = null ) {
		$url = ( null !== $order ) ? $order->get_checkout_order_received_url() : wc_get_checkout_url();

		$confirmation_url = add_query_arg(
			array(
				'qliro_one_confirm_page' => $rand_string,
			),
			$url
		);

		return apply_filters( 'qliro_one_wc_confirmation_url', $confirmation_url );
	}

	/**
	 * Push URL.
	 *
	 * URL of the push callback page for Checkout status changes.
	 *
	 * @param string $rand_string A random string generated on creation that will follow the entire order process.
	 * @return string
	 */
	private function get_push_url( $rand_string ) {
		$checkout_push_url = add_query_arg(
			array(
				'qliro_one_confirm_id' => $rand_string,
			),
			home_url( '/wc-api/QOC_Checkout_Status/' )
		);
		return apply_filters( 'qliro_one_wc_push_url', $checkout_push_url );
	}

	/**
	 * Push URL.
	 *
	 * URL of the push callback page for Order Management status changes.
	 *
	 * @param string $rand_string A random string generated on creation that will follow the entire order process.
	 * @return string
	 */
	public function get_om_push_url( $rand_string ) {
		$om_push_url = add_query_arg(
			array(
				'qliro_one_confirm_id' => $rand_string,
			),
			home_url( '/wc-api/QOC_OM_Status/' )
		);

		return apply_filters( 'qliro_one_wc_om_push_url', $om_push_url );
	}
}
