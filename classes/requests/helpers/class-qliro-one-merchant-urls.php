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
	 * @param string $order_id The WooCommerce order id.
	 * @return array
	 */
	public function get_urls( $order_id = null ) {
		$merchant_urls = array(
			'terms'        => $this->get_terms_url(),
			'confirmation' => $this->get_confirmation_url( $order_id ),
			'push'         => $this->get_push_url(),
			'om_push'      => $this->get_om_push_url(),
		);

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
	 * @param string $order_id The WooCommerce order id.
	 * @return string
	 */
	private function get_confirmation_url( $order_id ) {
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

		WC()->session->set( 'qliro_order_confirmation_id', $rand_string );

		$confirmation_url = add_query_arg(
			array(
				'qliro_one_confirm' => $rand_string,
			),
			wc_get_checkout_url()
		);
		return apply_filters( 'qliro_one_wc_confirmation_url', $confirmation_url );
	}

	/**
	 * Push URL.
	 *
	 * URL of the push callback page for Checkout status changes.
	 *
	 * @return string
	 */
	private function get_push_url() {
		$om_push_url = home_url( '/wc-api/QOC_Checkout_Status/' );
		return apply_filters( 'qliro_one_wc_push_url', $om_push_url );
	}

	/**
	 * Push URL.
	 *
	 * URL of the push callback page for Order Management status changes.
	 *
	 * @return string
	 */
	private function get_om_push_url() {
		$om_push_url = home_url( '/wc-api/QOC_OM_Status/' );
		return apply_filters( 'qliro_one_wc_om_push_url', $om_push_url );
	}
}
