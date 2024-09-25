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
		$this->method_title       = __( 'Qliro One', 'qliro-one-for-woocommerce' );
		$this->method_description = __( 'Qliro One replaces the standard WooCommerce checkout page.', 'qliro-one-for-woocommerce' );
		$this->supports           = apply_filters(
			'qliro_one_gateway_supports',
			array(
				'products',
				'refunds',
				'upsell',
			)
		);
		$this->has_fields         = false;
		$this->init_form_fields();
		$this->init_settings();
		$this->title             = $this->get_option( 'title' );
		$this->description       = $this->get_option( 'description' );
		$this->enabled           = $this->get_option( 'enabled' );
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
		add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'show_thank_you_snippet' ) );
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
		return ! ( 'yes' !== $this->enabled );
	}

	/**
	 * Process the payment and return the result.
	 *
	 * @param  int $order_id WooCommerce order ID.
	 *
	 * @return array
	 */
	public function process_payment( $order_id ) {
		// Try to get qliro order id from wc session.
		$qliro_order_id           = WC()->session->get( 'qliro_one_order_id' );
		$qliro_confirmation_id    = WC()->session->get( 'qliro_order_confirmation_id' );
		$qliro_merchant_reference = WC()->session->get( 'qliro_one_merchant_reference' );
		$chosen_shipping_method   = WC()->session->get( 'chosen_shipping_methods', null );

		$order = wc_get_order( $order_id );
		$order->update_meta_data( '_qliro_one_order_id', $qliro_order_id );
		$order->update_meta_data( '_qliro_one_order_confirmation_id', $qliro_confirmation_id );
		$order->update_meta_data( '_qliro_one_merchant_reference', $qliro_merchant_reference );
		// We need to save the shipping reference to the order as well, since table rate shipping can add a 3rd param to the instance id which is not saved to the order.
		$order->update_meta_data( '_qliro_one_shipping_reference', $chosen_shipping_method[0] );
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
		return QOC_WC()->order_management->refund( $order_id, $amount );
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
		$qliro_order    = qoc_get_thankyou_page_qliro_order( $qliro_order_id );
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
		$qliro_order = qoc_get_thankyou_page_qliro_order( $qliro_order_id );

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
		$upsell_order = QOC_WC()->api->upsell_qliro_one_order( $order_id, $upsell_uuid );

		if ( is_wp_error( $upsell_order ) ) {
			return $upsell_order;
		}

		$order = wc_get_order( $order_id );
		$order->update_meta_data( '_qliro_payment_transaction_id', $upsell_order['PaymentTransactionId'] );
		$order->add_order_note( __( 'Qliro order was upsold with transaction id', 'qliro-for-woocommerce' ) . ": {$upsell_order['PaymentTransactionId']}" );
		$order->save();

		return true;
	}

	/**
	 * Get the limits for the upsell order.
	 *
	 * @param int $order_id The WooCommerce order id.
	 * @return array
	 */
	public function get_upsell_limitations( $order_id ) {
		$limits              = array(
			'amount' => 0,
		);
		$order               = wc_get_order( $order_id );
		$payment_method_type = $order->get_meta( 'qliro_one_payment_method_name' );
		$is_qliro_method     = str_contains( $payment_method_type, 'QLIRO' );
		$is_card_method      = str_contains( $payment_method_type, 'CARD' );

		if ( $is_qliro_method && ! $is_card_method ) {
			$limits['amount'] = $order->get_total() * ( $this->upsell_percentage / 100 );
		}

		if ( ! $is_qliro_method && $is_card_method ) {
			$amount = 300;
			switch ( $order->get_currency() ) {
				case 'EUR':
				case 'USD':
				case 'GBP':
					$amount = 30;
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
			$args = wp_remote_get( 'https://kroconnect.blob.core.windows.net/krokedil/plugin-settings/qliro-checkout.json' );

			if ( is_wp_error( $args ) ) {
				ACO_Logger::log( 'Failed to fetch Qliro Checkout settings page config from remote source.', WC_Log_Levels::ERROR );
				return null;
			}

			$args = wp_remote_retrieve_body( $args );
			set_transient( 'qliro_checkout_settings_page_config', $args, 60 * 60 * 24 ); // 24 hours lifetime.
		}

		return json_decode( $args, true );
	}
}
