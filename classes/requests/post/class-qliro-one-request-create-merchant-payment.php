<?php
/**
 * Request class for the create merchant payment request to create new recurring orders in Qliro.
 *
 * @package Qliro_One_For_WooCommerce/Classes/Requests/Post
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Qliro_One_Request_Create_Merchant_Payment
 */
class Qliro_One_Request_Create_Merchant_Payment extends Qliro_One_Request_Post {
	/**
	 * The log title to use.
	 *
	 * @var string
	 */
	protected $log_title = 'Create merchant payment';

	/**
	 * Get the request URL.
	 *
	 * @return string
	 */
	protected function get_request_url() {
		return $this->get_api_url_base() . 'checkout/adminapi/v2/merchantpayment';
	}

	/**
	 * Get the body for the request.
	 *
	 * @return array
	 */
	protected function get_body() {
		$order_data = new Qliro_One_Helper_Order();
		$order      = wc_get_order( $this->arguments['order_id'] );
		$token      = $this->arguments['token'];
		$confirm_id = wp_generate_uuid4();

		$body = array(
			'RequestId'                            => $order_data->generate_request_id(),
			'MerchantApiKey'                       => $this->get_qliro_key(),
			'MerchantReference'                    => $order->get_order_number(),
			'Currency'                             => $order->get_currency(),
			'Country'                              => $order->get_billing_country(),
			'Language'                             => str_replace( '_', '-', strtolower( get_locale() ) ),
			'MerchantOrderManagementStatusPushUrl' => QLIRO_WC()->merchant_urls->get_om_push_url( $confirm_id ),
			'OrderItems'                           => $order_data::get_order_lines( $this->arguments['order_id'] ),
			'BillingAddress'                       => array(
				'FirstName'  => $order->get_billing_first_name(),
				'LastName'   => $order->get_billing_last_name(),
				'Street'     => $order->get_billing_address_1(),
				'PostalCode' => $order->get_billing_postcode(),
				'City'       => $order->get_billing_city(),
			),
			'ShippingAddress'                      => array(
				'FirstName'  => $order->get_shipping_first_name(),
				'LastName'   => $order->get_shipping_last_name(),
				'Street'     => $order->get_shipping_address_1(),
				'PostalCode' => $order->get_shipping_postcode(),
				'City'       => $order->get_shipping_city(),
			),
		);

		// If we have a token, add the payment method for card payments, with the saved credit card id.
		if ( ! empty( $token ) ) {
			$body['PaymentMethod'] = array(
				'Name'                      => 'CREDITCARDS',
				'MerchantSavedCreditCardId' => $token,
			);
		} else { // Else add the payment method and customer required for invoice payments.
			$body['PaymentMethod'] = array(
				'Name'    => 'QLIRO_INVOICE',
				'Subtype' => 'INVOICE',
			);

			$body['Customer'] = array(
				'PersonalNumber' => $order->get_meta( '_qliro_personal_number' ) ?? '',
				'Email'          => $order->get_billing_email(),
				'JuridicalType'  => empty( $order->get_billing_company() ) ? 'Physical' : 'Company',
				'MobileNumber'   => $order->get_billing_phone(),
			);
		}

		// Set the confirm id to the order meta to handle the callback for the order management.
		$order->update_meta_data( '_qliro_one_order_confirmation_id', $confirm_id );
		$order->save();

		return $body;
	}
}
