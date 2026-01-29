<?php
/**
 * Class file for Qliro_One_Gateway class.
 *
 * @package Qliro_One_For_WooCommerce/Classes
 */

use KrokedilQliroDeps\Krokedil\SettingsPage\SettingsPage;
use KrokedilQliroDeps\Krokedil\SettingsPage\Gateway;

/**
 * Class Qliro_One_Gateway
 */
class Qliro_One_Gateway extends WC_Payment_Gateway {

	/**
	 * If test mode is enabled.
	 *
	 * @var bool
	 */
	public $testmode;

	/**
	 * If logging is enabled.
	 *
	 * @var bool
	 */
	public $logging;

	/**
	 * The upsell percentage.
	 *
	 * @var int
	 */
	public $upsell_percentage;

	/**
	 * Class constructor.
	 */
	public function __construct() {
		$this->id                 = 'qliro_one';
		$this->method_title       = __( 'Qliro for WooCommerce', 'qliro-for-woocommerce' );
		$this->method_description = __( 'Safe and simple payment. An embedded checkout with high conversion rates and the most popular payment methods on the market — giving your customers a first-class shopping experience.', 'qliro-for-woocommerce' );
		$this->supports           = apply_filters(
			'qliro_one_gateway_supports',
			array(
				'products',
				'refunds',
				'upsell',
				'subscriptions',
				'subscription_cancellation',
				'subscription_suspension',
				'subscription_reactivation',
				'subscription_amount_changes',
				'subscription_date_changes',
				// 'subscription_payment_method_change', Qliro does not support 0 value orders, which this would create.
				// 'subscription_payment_method_change_customer', Qliro does not support 0 value orders, which this would create.
				// 'subscription_payment_method_change_admin', Qliro does not support 0 value orders, which this would create.
				'multiple_subscriptions',
				'tokenization', // Only for card payments when buying subscriptions.
			)
		);
		$this->has_fields         = false;
		$this->init_form_fields();
		$this->init_settings();
		$this->title             = $this->get_option( 'title' );
		$this->description       = $this->get_option( 'description' );
		$this->enabled           = qliro_is_enabled_with_demo_check() ? 'yes' : 'no';
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
		add_action( 'woocommerce_update_options_payment_gateways_qliro_one', array( $this, 'update_conditional_settings' ) );
		add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'show_thank_you_snippet' ) );
		add_filter( 'woocommerce_order_needs_payment', array( $this, 'maybe_change_needs_payment' ), 999, 3 );
		add_filter( 'wc_order_is_editable', array( $this, 'can_edit_order' ), 10, 2 );
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
		// If the payment method is not enabled, just return false right away.
		if ( ! wc_string_to_bool( $this->enabled ?? 'no' ) ) {
			return false;
		}

		// If the cart contains a subscription, we only support swedish customers for now.
		if ( Qliro_One_Subscriptions::cart_has_subscription() ) {
			$billing_country = WC()->customer->get_billing_country();
			if ( 'SE' !== $billing_country ) { // Qliro only supports Swedish customers for subscriptions.
				return false;
			}
		}

		return true;
	}

	/**
	 * Process the payment and return the result.
	 *
	 * @param  int $order_id WooCommerce order ID.
	 *
	 * @return array
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		// If we are on the pay for order page, or the page is a change subscription payment page, we need to process the redirect flow instead.
		$change_payment_method = filter_input( INPUT_GET, 'change_payment_method', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( ! empty( $change_payment_method ) || is_wc_endpoint_url( 'order-pay' ) ) {
			$qliro_order_id = $order->get_meta( '_qliro_one_order_id' );

			if ( empty( $qliro_order_id ) ) {
				// Create a new order and return the redirect url.
				$result = QLIRO_WC()->api->create_qliro_one_order( $order_id );

				if ( is_wp_error( $result ) ) {
					return array(
						'result'   => 'failure',
						'messages' => $result->get_error_message(),
					);
				}
				$payment_link = $result['PaymentLink'] ?? '';
				$order->update_meta_data( '_qliro_one_order_id', $result['OrderId'] );
				$order->update_meta_data( '_qliro_one_merchant_reference', $order->get_order_number() );
				$order->update_meta_data( '_qliro_one_hpp_url', $payment_link );
				$order->save();

				$redirect_url = $payment_link;
			} else {
				$redirect_url = $order->get_meta( '_qliro_one_hpp_url' );
			}

			if ( empty( $redirect_url ) ) {
				return array(
					'result'   => 'failure',
					'messages' => __( 'Could not retrieve the Qliro payment link. Please contact the store administrator.', 'qliro-for-woocommerce' ),
				);
			}

			return array(
				'result'   => 'success',
				'redirect' => $redirect_url,
			);
		}

		// Try to get qliro order id from wc session.
		$qliro_order_id           = WC()->session->get( 'qliro_one_order_id' );
		$qliro_confirmation_id    = WC()->session->get( 'qliro_order_confirmation_id' );
		$qliro_merchant_reference = WC()->session->get( 'qliro_one_merchant_reference' );
		$chosen_shipping_method   = WC()->session->get( 'chosen_shipping_methods', null );

		// If the order id, confirmation id or merchant reference is not set, we can not proceed.
		if ( empty( $qliro_order_id ) || empty( $qliro_confirmation_id ) || empty( $qliro_merchant_reference ) ) {
			Qliro_One_Logger::log( "Could not process payment due to missing session data. qliro_one_order_id: $qliro_order_id, qliro_order_confirmation_id: $qliro_confirmation_id, qliro_one_merchant_reference: $qliro_merchant_reference" );
			return array(
				'result'   => 'failure',
				'messages' => __( 'The order could not be processed. Please reload the page and try again.', 'qliro-for-woocommerce' ),
			);
		}

		$order->update_meta_data( '_qliro_one_order_id', $qliro_order_id );
		$order->update_meta_data( '_qliro_one_order_confirmation_id', $qliro_confirmation_id );
		$order->update_meta_data( '_qliro_one_merchant_reference', $qliro_merchant_reference );
		// We need to save the shipping reference to the order as well, since table rate shipping can add a 3rd param to the instance id which is not saved to the order.
		if ( ! empty( $chosen_shipping_method ) ) {
			$order->update_meta_data( '_qliro_one_shipping_reference', $chosen_shipping_method[0] );
		}
		$order->save();

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
		$return_fee = Qliro_One_Order_Management::get_return_fee_from_post();

		return QLIRO_WC()->order_management->refund( $order_id, $amount, array( $return_fee ) );
	}

	/**
	 * Print the iframe on the thankyou page.
	 *
	 * @param int $order_id The WooCommerce order id.
	 * @return void
	 */
	public function show_thank_you_snippet( $order_id = null ) {
		$order          = wc_get_order( $order_id );
		$qliro_order_id = $order->get_meta( '_qliro_one_order_id' );
		$qliro_order    = qliro_get_thankyou_page_qliro_order( $qliro_order_id );
		$order          = wc_get_order( $order_id );
		// Check if the order has been confirmed already.
		if ( ! empty( $order->get_date_paid() ) ) {
			$result = qliro_confirm_order( $order );

			if ( $result ) {
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

		$qliro_order_id = $order->get_meta( '_qliro_one_order_id' );
		// Unused variable. What for?
		$qliro_order = qliro_get_thankyou_page_qliro_order( $qliro_order_id );

		// Check if we have a urgency time.
		$urgency_deadline = $order->get_meta( '_ppu_upsell_urgency_deadline' );

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
	 * @return bool|WP_Error
	 */
	public function upsell( $order_id, $upsell_uuid ) {
		$upsell_order = QLIRO_WC()->api->upsell_qliro_one_order( $order_id, $upsell_uuid );

		if ( is_wp_error( $upsell_order ) ) {
			return $upsell_order;
		}

		$result = true;
		$order  = wc_get_order( $order_id );

		// If we got a link to redirect to for the upsell, we need to handle that.
		if ( isset( $upsell_order['UpsellLink'] ) ) {
			$result = $upsell_order['UpsellLink'];
			$order->add_order_note( __( 'Qliro order was upsold, but the customer was redirected to a new payment page.', 'qliro-for-woocommerce' ) );
		}

		if ( isset( $upsell_order['PaymentTransactionId'] ) ) {
			$order->update_meta_data( '_qliro_payment_transaction_id', $upsell_order['PaymentTransactionId'] );
			$order->add_order_note( __( 'Qliro order was upsold with transaction id', 'qliro-for-woocommerce' ) . ": {$upsell_order['PaymentTransactionId']}" );
		}

		$order->save();

		return $result;
	}

	/**
	 * Get the limits for the upsell order.
	 *
	 * @param int $order_id The WooCommerce order id.
	 * @return array
	 */
	public function get_upsell_limitations( $order_id ) {
		$limits              = array(
			'amount' => PHP_INT_MAX, // No limit by default since Qliro should be able to support a higher amount to some payment providers.
		);
		$order               = wc_get_order( $order_id );
		$payment_method_type = $order->get_meta( 'qliro_one_payment_method_name' );
		$is_qliro_method     = str_contains( $payment_method_type, 'QLIRO' );
		$is_card_method      = str_contains( $payment_method_type, 'CARD' );

		// If the payment method is a qliro method and not a card method, we need to set the upsell limit based on the upsell percentage.
		if ( $is_qliro_method && ! $is_card_method ) {
			$limits['amount'] = $order->get_total() * ( $this->upsell_percentage / 100 );
		}

		// If the payment method is a card method, we need to set a fixed limit.
		if ( ! $is_qliro_method && $is_card_method ) {
			$amount = 300; // 300 for SEK/NOK/DKK etc for card payments.
			switch ( $order->get_currency() ) {
				case 'EUR':
				case 'USD':
				case 'GBP':
					$amount = 30; // 30 for Euro/Pounds/Dollars for card payments.
					break;
			}
			$limits['amount'] = $amount;
		}

		return $limits;
	}

	/**
	 * Check if the order can be refunded with Qliro or not.
	 *
	 * @param WC_Order $order The WooCommerce order.
	 *
	 * @return bool
	 */
	public function can_refund_order( $order ) {
		// Check that the order has order sync enabled.
		if ( ! Qliro_One_Order_Management::is_order_sync_enabled( $order ) ) {
			return false;
		}

		if ( ! qliro_is_fully_captured( $order ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Maybe change the needs payment for a WooCommerce order.
	 *
	 * @param bool     $wc_result The result WooCommerce had.
	 * @param WC_Order $order The WooCommerce order.
	 * @param array    $valid_order_statuses The valid order statuses.
	 * @return bool
	 */
	public function maybe_change_needs_payment( $wc_result, $order, $valid_order_statuses ) {

		// Only change for Qliro orders.
		if ( 'qliro_one' !== $order->get_payment_method() ) {
			return $wc_result;
		}

		// Only if our filter is active and is set to false.
		if ( apply_filters( 'qliro_check_if_needs_payment', true ) ) {
			return $wc_result;
		}

		return true;
	}

	/**
	 * Add settings page extension for Qliro Checkout.
	 *
	 * @return void
	 */
	public function admin_options() {
		$args = $this->get_settings_page_args();

		if ( empty( $args ) ) {
			parent::admin_options();
			return;
		}

		$args['icon'] = QLIRO_WC_PLUGIN_URL . '/assets/images/qliro-icon.png';

		$gateway_page = new Gateway( $this, $args );

		$args['general_content'] = array( $gateway_page, 'output' );
		$settings_page           = ( SettingsPage::get_instance() )
		->set_plugin_name( 'Qliro Checkout' )
		->register_page( $this->id, $args, $this )
		->output( $this->id );
	}

	/**
	 * Read the settings page arguments from remote or local storage.
	 * If the args are stored locally, they are fetched from the transient cache.
	 * If they are not available locally, they are fetched from the remote source and stored in the transient cache.
	 * If the remote source is not available, the function returns null, and default settings page will be used instead.
	 *
	 * @return array|null
	 */
	private function get_settings_page_args() {
		$args = get_transient( 'qliro_checkout_settings_page_config' );
		if ( ! $args ) {
			$args = wp_remote_get( 'https://krokedil-settings-page-configs.s3.eu-north-1.amazonaws.com/main/configs/qliro-one-for-woocommerce.json' );

			if ( is_wp_error( $args ) ) {
				Qliro_One_Logger::log( 'Failed to fetch Qliro Checkout settings page config from remote source.' );
				return null;
			}

			$args = wp_remote_retrieve_body( $args );
			set_transient( 'qliro_checkout_settings_page_config', $args, 60 * 60 * 24 ); // 24 hours lifetime.
		}

		return json_decode( $args, true );
	}

	public function update_conditional_settings() {
		$settings = get_option( 'woocommerce_qliro_one_settings', array() );

		// If all locations are set to none, disable the banner widget.
		$banner_cart_location = sanitize_text_field( $settings['banner_widget_cart_placement_location'] ?? 'woocommerce_cart_collaterals' );
		$banner_location      = sanitize_text_field( $settings['banner_widget_placement_location'] ?? 'none' );
		$banner_enabled       = ( $banner_cart_location === 'none' && $banner_location === 'none' ) ? 'no' : 'yes';
		update_option( 'woocommerce_qliro_one_banner_widget_enabled', $banner_enabled );

		// If the payment widget location is set to none, disable the payment widget.
		$payment_location = sanitize_text_field( $settings['payment_widget_placement_location'] ?? '15' );
		$payment_enabled  = ( $payment_location === 'none' ) ? 'no' : 'yes';
		update_option( 'woocommerce_qliro_one_payment_widget_enabled', $payment_enabled );

		$om_advanced_settings = sanitize_text_field( $settings['om_advanced_settings'] ?? 'no' );

		// If the advanced order management setting is disabled, reset the custom order statuses to default.
		if ( 'no' === $om_advanced_settings ) {
			$this->reset_om_statuses( $settings );
		}

		update_option( 'woocommerce_qliro_one_settings', $settings );
	}

	/**
	 * Reset the order management statuses.
	 *
	 * @param array $settings The Qliro One settings array.
	 * @return void
	 */
	private function reset_om_statuses( &$settings = array() ) {
		$order_status_settings = array(
			'capture_pending_status',
			'capture_ok_status',
			'cancel_pending_status',
			'cancel_ok_status',
		);

		foreach ( $order_status_settings as $order_status_setting ) {
			$settings[ $order_status_setting ] = 'none';
		}
	}
	/**
	 * Check if the order should be editable in WooCommerce admin.
	 *
	 * @param bool     $is_editable If the order is editable.
	 * @param WC_Order $order The WooCommerce order.
	 * @return bool
	 */
	public function can_edit_order( $is_editable, $order ) {
		if ( 'qliro_one' !== $order->get_payment_method() ) {
			return $is_editable;
		}

		// If is pay for order and not paid yet, do not allow editing.
		$qliro_hpp_url = $order->get_meta( '_qliro_one_hpp_url' );
		if ( ! empty( $qliro_hpp_url ) && empty( $order->get_date_paid() ) ) {
			return false;
		}

		return $is_editable;
	}
}
