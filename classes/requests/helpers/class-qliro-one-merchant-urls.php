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
			'terms'        => $this->get_terms_url(),                   // Required.
//			'checkout'     => $this->get_checkout_url(),                // Required.
			'confirmation' => $this->get_confirmation_url( $order_id ), // Required.
//			'push'         => $this->get_push_url(),                    // Required.
//			'notification' => $this->get_notification_url(),
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
	 * @return mixed|void
	 */
	private function get_privacy_page() {
		// todo double check.
		$terms_url = get_permalink( wc_get_page_id( 'privacy' ) );

		return apply_filters( 'qliro_one_wc_terms_url', $terms_url );
	}

	/**
	 * Checkout URL.
	 *
	 * Required. URL of merchant checkout page. Should be different than terms, confirmation and push URLs.
	 *
	 * @return string
	 */
	private function get_checkout_url() {
		$checkout_url = wc_get_checkout_url();
		return apply_filters( 'qliro_one_wc_checkout_url', $checkout_url );
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
		$confirmation_url = add_query_arg(
			array(
				'qliro_one_confirm' => 'yes',
			),
			wc_get_checkout_url()
		);
		return apply_filters( 'qliro_one_wc_confirmation_url', $confirmation_url );
	}

	/**
	 * Push URL.
	 *
	 * Required. URL of merchant confirmation page. Should be different than checkout and confirmation URLs.
	 *
	 * @return string
	 */
	private function get_push_url() {
		return '';
	}



	/**
	 * Notification URL.
	 *
	 * URL for notifications on pending orders.
	 *
	 * @return string
	 */
	private function get_notification_url() {
		$notification_url = '';
		return apply_filters( 'qliro_one_wc_notification_url', $notification_url );
	}

}
