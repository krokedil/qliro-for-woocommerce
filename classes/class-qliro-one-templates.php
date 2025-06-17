<?php
/**
 * Templates class for Qliro checkout.
 *
 * @package  Qliro_One_For_WooCommerce/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 *  Qliro_One_Templates class.
 */
class Qliro_One_Templates {

	/**
	 * The reference the *Singleton* instance of this class.
	 *
	 * @var $instance
	 */
	protected static $instance;

	/**
	 * Checkout layout setting.
	 *
	 * @var string
	 */
	protected $checkout_layout;

	/**
	 * Returns the *Singleton* instance of this class.
	 *
	 * @return self::$instance The *Singleton* instance.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Plugin actions.
	 */
	public function __construct() {
		$qliro_settings        = get_option( 'woocommerce_qliro_one_settings' );
		$this->checkout_layout = isset( $qliro_settings['checkout_layout'] ) ? $qliro_settings['checkout_layout'] : 'one_column_checkout';

		// Override template if Qliro Checkout page.
		add_filter( 'wc_get_template', array( $this, 'override_template' ), 999, 2 );

		// Template hooks.
		add_action( 'qliro_one_wc_after_order_review', 'qliro_one_wc_show_another_gateway_button', 20 );
		add_action( 'qliro_one_wc_after_order_review', array( $this, 'add_extra_checkout_fields' ), 10 );
		add_action( 'qliro_one_wc_before_snippet', array( $this, 'add_wc_form' ), 10 );

		// Body class modifications. For checkout layout setting.
		add_filter( 'body_class', array( $this, 'add_body_class' ) );

		// Country selector.
		if ( 'shortcode' !== ( $qliro_settings['country_selector_placement'] ?? 'shortcode' ) ) {
			// Defaults to 'qliro_one_wc_before_snippet' hook.
			$placement = $qliro_settings['country_selector_placement'] ?? 'qliro_one_wc_before_snippet';
			add_action( $placement, array( $this, 'add_country_selector' ) );
		}

		// Shortcode: country selector. Should always be available.
		add_shortcode( 'qliro_country_selector', array( $this, 'country_selector_shortcode' ) );

		// Unhook the country field if it has the country selector shortcode, and Qliro is the chosen gateway. We'll inject the country field instead.
		add_filter( 'woocommerce_checkout_fields', array( $this, 'unhook_country_field' ) );
	}

	/**
	 * Shortcode: `qliro_country_selector`.
	 *
	 * @param array       $atts Shortcode attributes.
	 * @param string|null $content Shortcode content.
	 * @param string      $shortcode_tag Shortcode tag.
	 */
	public function country_selector_shortcode( $atts, $content, $shortcode_tag ) {
		$this->add_country_selector();
	}

	/**
	 * Add country selector to the checkout page.
	 */
	public function add_country_selector() {
		// The order received page is a checkout page, but we don't want to show the country selector there.
		if ( ! is_checkout() || is_order_received_page() ) {
			return;
		}

		if ( ! isset( WC()->session ) || 'qliro_one' !== WC()->session->get( 'chosen_payment_method' ) ) {
			return;
		}

		if ( function_exists( 'WC' ) && ! WC()->checkout ) {
			return;
		}

		$checkout = WC()->checkout();
		$fields   = $checkout->get_checkout_fields( 'billing' );
		$args     = array_merge(
			$fields['qliro_billing_country'] ?? $fields['billing_country'],
			array(
				'type'     => 'country',
				'required' => true,
			)
		);
		$value    = $checkout->get_value( 'billing_country' );

		do_action( 'before_qliro_country_selector' );
		woocommerce_form_field( 'billing_country', $args, $value );
		do_action( 'after_qliro_country_selector' );
	}

	/**
	 * Maybe unhook the billing country field.
	 *
	 * @param array $fields Checkout fields.
	 *
	 * @return array
	 */
	public function unhook_country_field( $fields ) {
		if ( ! isset( WC()->session ) || 'qliro_one' !== WC()->session->get( 'chosen_payment_method' ) ) {
			return $fields;
		}

		global $post;
		if ( ! isset( $post ) || ! has_shortcode( $post->post_content, 'qliro_country_selector' ) ) {
			return $fields;
		}

		// If we unhook the billing field, it won't be available when we call get_checkout_fields in the `add_country_selector` method which we need to re-use WC defaults.
		$fields['billing']['qliro_billing_country']             = $fields['billing']['billing_country'];
		$fields['billing']['qliro_billing_country']['type']     = 'hidden';
		$fields['billing']['qliro_billing_country']['required'] = false;

		// We do not want it to appear on the checkout field since we'll replace it with our own country field.
		unset( $fields['billing']['billing_country'] );
		return $fields;
	}

	/**
	 * Override checkout form template if Qliro Checkout is the selected payment method.
	 *
	 * @param string $template      Template.
	 * @param string $template_name Template name.
	 *
	 * @return string
	 */
	public function override_template( $template, $template_name ) {
		if ( is_checkout() ) {
			$confirm = filter_input( INPUT_GET, 'confirm', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
			// Don't display Qliro template if we have a cart that doesn't needs payment.
			if ( apply_filters( 'qliro_check_if_needs_payment', true ) && ! is_wc_endpoint_url( 'order-pay' ) && null !== WC()->cart ) {
				if ( ! WC()->cart->needs_payment() ) {
					return $template;
				}
			}

			// Qliro Checkout.
			if ( 'checkout/form-checkout.php' === $template_name ) {
				$available_gateways = WC()->payment_gateways()->get_available_payment_gateways();

				if ( locate_template( 'woocommerce/qliro-one-checkout.php' ) ) {
					$qliro_one_checkout_template = locate_template( 'woocommerce/qliro-one-checkout.php' );
				} else {
					$qliro_one_checkout_template = QLIRO_WC_PLUGIN_PATH . '/templates/qliro-one-checkout.php';
				}

				// Qliro checkout page.
				if ( array_key_exists( 'qliro_one', $available_gateways ) ) {
					// If chosen payment method exists.
					if ( 'qliro_one' === WC()->session->get( 'chosen_payment_method' ) ) {
						if ( empty( $confirm ) ) {
							$template = $qliro_one_checkout_template;
						}
					}

					// If chosen payment method does not exist and Qliro is the first gateway.
					if ( null === WC()->session->get( 'chosen_payment_method' ) || '' === WC()->session->get( 'chosen_payment_method' ) ) {
						reset( $available_gateways );

						if ( 'qliro_one' === key( $available_gateways ) ) {
							if ( empty( $confirm ) ) {
								$template = $qliro_one_checkout_template;
							}
						}
					}

					// If another gateway is saved in session, but has since become unavailable.
					if ( WC()->session->get( 'chosen_payment_method' ) ) {
						if ( ! array_key_exists( WC()->session->get( 'chosen_payment_method' ), $available_gateways ) ) {
							reset( $available_gateways );

							if ( 'qliro_one' === key( $available_gateways ) ) {
								if ( empty( $confirm ) ) {
									$template = $qliro_one_checkout_template;
								}
							}
						}
					}
				}
			}
		}

		return $template;
	}

	/**
	 * Adds the extra checkout field div to the checkout page.
	 */
	public function add_extra_checkout_fields() {
		do_action( 'qliro_wc_before_extra_fields' );
		?>
		<div id="qliro-one-extra-checkout-fields">
		</div>
		<?php
		do_action( 'qliro_wc_after_extra_fields' );
	}


	/**
	 * Adds the WC form and other fields to the checkout page.
	 *
	 * @return void
	 */
	public function add_wc_form() {
		?>
		<div aria-hidden="true" id="qliro-one-wc-form" style="position:absolute; top:-99999px; left:-99999px;">
			<?php do_action( 'woocommerce_checkout_billing' ); ?>
			<?php do_action( 'woocommerce_checkout_shipping' ); ?>
			<div id="qliro-one-nonce-wrapper">
				<?php
				if ( version_compare( WOOCOMMERCE_VERSION, '3.4', '<' ) ) {
					wp_nonce_field( 'woocommerce-process_checkout' );
				} else {
					wp_nonce_field( 'woocommerce-process_checkout', 'woocommerce-process-checkout-nonce' );
				}
				wc_get_template( 'checkout/terms.php' );
				?>
			</div>
			<input id="payment_method_qliro_one" type="radio" class="input-radio" name="payment_method" value="qliro_one" checked="checked" />
		</div>
		<?php
	}

	/**
	 * Add checkout page body class, depending on checkout page layout settings.
	 *
	 * @param array $class CSS classes used in body tag.
	 *
	 * @return array
	 */
	public function add_body_class( $class ) {
		if ( is_checkout() && ! is_wc_endpoint_url( 'order-received' ) ) {

			// Don't display Collector body classes if we have a cart that doesn't needs payment.
			if ( method_exists( WC()->cart, 'needs_payment' ) && ! WC()->cart->needs_payment() ) {
				return $class;
			}

			$settings = get_option( 'woocommerce_qliro_one_settings' );

			$first_gateway = '';
			if ( WC()->session->get( 'chosen_payment_method' ) ) {
				$first_gateway = WC()->session->get( 'chosen_payment_method' );
			} else {
				$available_payment_gateways = WC()->payment_gateways->get_available_payment_gateways();
				reset( $available_payment_gateways );
				$first_gateway = key( $available_payment_gateways );
			}

			if ( 'qliro_one' === $first_gateway && 'two_column_left' === $this->checkout_layout ) {
				$class[] = 'qliro-one-selected';
				$class[] = 'qliro-two-column-left';
			}
			if ( 'qliro_one' === $first_gateway && 'two_column_left_sf' === $this->checkout_layout ) {
				$class[] = 'qliro-one-selected';
				$class[] = 'qliro-two-column-left-sf';
			}

			if ( 'qliro_one' === $first_gateway && 'two_column_right' === $this->checkout_layout ) {
				$class[] = 'qliro-one-selected';
				$class[] = 'qliro-two-column-right';
			}

			if ( 'qliro_one' === $first_gateway && 'one_column_checkout' === $this->checkout_layout ) {
				$class[] = 'qliro-one-selected';
			}

			// If the setting for shipping in iframe is yes, then add the class.
			if ( 'qliro_one' === $first_gateway && QOC_WC()->checkout()->is_shipping_in_iframe_enabled() ) {
				$class[] = 'qliro-shipping-display';
			}
		}
		return $class;
	}
}

Qliro_One_Templates::get_instance();
