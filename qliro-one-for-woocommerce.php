<?php
use Krokedil\Shipping\Interfaces\PickupPointServiceInterface;
use Krokedil\Shipping\PickupPoints; // phpcs:ignore
/**
 * Plugin Name: Qliro One for WooCommerce
 * Plugin URI: https://krokedil.com/qliro/
 * Description: Qliro One Checkout payment gateway for WooCommerce.
 * Author: Krokedil
 * Author URI: https://krokedil.com/
 * Version: 1.2.0
 * Text Domain: qliro-one-for-woocommerce
 * Domain Path: /languages
 *
 * WC requires at least: 5.0.0
 * WC tested up to: 9.0.2
 *
 * Copyright (c) 2021-2024 Krokedil
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Required minimums and constants
 */
define( 'QLIRO_WC_VERSION', '1.2.0' );
define( 'QLIRO_WC_MAIN_FILE', __FILE__ );
define( 'QLIRO_WC_PLUGIN_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );
define( 'QLIRO_WC_PLUGIN_URL', untrailingslashit( plugin_dir_url( __FILE__ ) ) );

if ( ! class_exists( 'Qliro_One_For_WooCommerce' ) ) {
	/**
	 * Class Qliro_One_For_WooCommerce
	 */
	class Qliro_One_For_WooCommerce {

		/**
		 * The reference the *Singleton* instance of this class.
		 *
		 * @var Qliro_One_For_WooCommerce $instance
		 */
		private static $instance;


		/**
		 * Reference to merchant URLs class.
		 *
		 * @var Qliro_One_Merchant_URLs
		 */
		public $merchant_urls;

		/**
		 *  Reference to API class.
		 *
		 * @var Qliro_One_API
		 */
		public $api;

		/**
		 * Reference to order management class.
		 *
		 * @var Qliro_One_Order_Management
		 */
		public $order_management;

		/**
		 * Pickup points service.
		 *
		 * @var PickupPointServiceInterface $pickup_points_service
		 */
		private $pickup_points_service;

		/**
		 * Returns the *Singleton* instance of this class.
		 *
		 * @return Qliro_One_For_WooCommerce The *Singleton* instance.
		 */
		public static function get_instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * Private clone method to prevent cloning of the instance of the
		 * *Singleton* instance.
		 *
		 * @return void
		 */
		private function __clone() {
			wc_doing_it_wrong( __FUNCTION__, __( 'Nope', 'qliro-one-for-woocommerce' ), '1.0' );
		}

		/**
		 * Private unserialize method to prevent unserializing of the *Singleton*
		 * instance.
		 *
		 * @return void
		 */
		public function __wakeup() {
			wc_doing_it_wrong( __FUNCTION__, __( 'Nope', 'qliro-one-for-woocommerce' ), '1.0' );
		}

		/**
		 * Protected constructor to prevent creating a new instance of the
		 * *Singleton* via the `new` operator from outside this class.
		 */
		protected function __construct() {
			add_action( 'plugins_loaded', array( $this, 'init' ) );
			add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_action_links' ) );
			add_action( 'admin_init', array( $this, 'check_version' ) );
		}

		/**
		 * Init the plugin after plugins_loaded so environment variables are set.
		 */
		public function init() {
			// Init the gateway itself.
			$this->init_gateways();
		}

		/**
		 * Adds plugin action links
		 *
		 * @param array $links Plugin action link before filtering.
		 *
		 * @return array Filtered links.
		 */
		public function plugin_action_links( $links ) {
			$setting_link = $this->get_setting_link();
			$plugin_links = array(
				'<a href="' . $setting_link . '">' . __( 'Settings', 'qliro-one-for-woocommerce' ) . '</a>',
				'<a href="https://krokedil.se/">' . __( 'Support', 'qliro-one-for-woocommerce' ) . '</a>',
			);

			return array_merge( $plugin_links, $links );
		}

		/**
		 * Get setting link.
		 *
		 * @since 1.0.0
		 *
		 * @return string Setting link
		 */
		public function get_setting_link() {
			$section_slug = 'qliro_one';

			$params = array(
				'page'    => 'wc-settings',
				'tab'     => 'checkout',
				'section' => $section_slug,
			);

			return add_query_arg( $params, 'admin.php' );
		}

		/**
		 * Initialize the gateway. Called very early - in the context of the plugins_loaded action
		 *
		 * @since 1.0.0
		 */
		public function init_gateways() {
			if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
				return;
			}

			// Include the autoloader from composer. If it fails, we'll just return and not load the plugin. But an admin notice will show to the merchant.
			if ( ! self::init_composer() ) {
				return;
			}

			include_once QLIRO_WC_PLUGIN_PATH . '/classes/class-qliro-one-assets.php';
			include_once QLIRO_WC_PLUGIN_PATH . '/classes/class-qliro-one-fields.php';
			include_once QLIRO_WC_PLUGIN_PATH . '/classes/class-qliro-one-gateway.php';
			include_once QLIRO_WC_PLUGIN_PATH . '/classes/class-qliro-one-ajax.php';
			include_once QLIRO_WC_PLUGIN_PATH . '/classes/class-qliro-one-confirmation.php';
			include_once QLIRO_WC_PLUGIN_PATH . '/classes/class-qliro-one-checkout.php';
			include_once QLIRO_WC_PLUGIN_PATH . '/classes/class-qliro-one-callbacks.php';
			include_once QLIRO_WC_PLUGIN_PATH . '/classes/class-qliro-one-product-tab.php';
			include_once QLIRO_WC_PLUGIN_PATH . '/classes/class-qliro-one-shipping-method-instance.php';

			include_once QLIRO_WC_PLUGIN_PATH . '/classes/class-qliro-one-logger.php';
			include_once QLIRO_WC_PLUGIN_PATH . '/classes/requests/class-qliro-one-request.php';
			include_once QLIRO_WC_PLUGIN_PATH . '/classes/requests/class-qliro-one-request-post.php';
			include_once QLIRO_WC_PLUGIN_PATH . '/classes/requests/class-qliro-one-request-get.php';
			include_once QLIRO_WC_PLUGIN_PATH . '/classes/requests/class-qliro-one-request-put.php';
			include_once QLIRO_WC_PLUGIN_PATH . '/classes/requests/post/class-qliro-one-request-create-order.php';
			include_once QLIRO_WC_PLUGIN_PATH . '/classes/requests/get/class-qliro-one-request-admin-get-order.php';
			include_once QLIRO_WC_PLUGIN_PATH . '/classes/requests/get/class-qliro-one-request-get-order.php';
			include_once QLIRO_WC_PLUGIN_PATH . '/classes/requests/put/class-qliro-one-request-update-order.php';
			include_once QLIRO_WC_PLUGIN_PATH . '/classes/requests/post/class-qliro-one-request-update-merchant-reference.php';
			include_once QLIRO_WC_PLUGIN_PATH . '/classes/requests/post/class-qliro-one-request-cancel-order.php';
			include_once QLIRO_WC_PLUGIN_PATH . '/classes/requests/post/class-qliro-one-request-capture-order.php';
			include_once QLIRO_WC_PLUGIN_PATH . '/classes/requests/post/class-qliro-one-request-return-items.php';
			include_once QLIRO_WC_PLUGIN_PATH . '/classes/requests/post/class-qliro-one-request-upsell-order.php';

			include_once QLIRO_WC_PLUGIN_PATH . '/classes/class-qliro-one-templates.php';
			include_once QLIRO_WC_PLUGIN_PATH . '/classes/class-qliro-one-api.php';
			include_once QLIRO_WC_PLUGIN_PATH . '/classes/requests/helpers/class-qliro-one-merchant-urls.php';
			include_once QLIRO_WC_PLUGIN_PATH . '/classes/requests/helpers/class-qliro-one-helper-cart.php';
			include_once QLIRO_WC_PLUGIN_PATH . '/classes/requests/helpers/class-qliro-one-helper-order.php';
			include_once QLIRO_WC_PLUGIN_PATH . '/classes/requests/helpers/class-qliro-one-helper-shipping-methods.php';
			include_once QLIRO_WC_PLUGIN_PATH . '/classes/requests/helpers/class-qliro-one-helper-order-limitations.php';
			include_once QLIRO_WC_PLUGIN_PATH . '/classes/class-qliro-one-order-management.php';
			include_once QLIRO_WC_PLUGIN_PATH . '/includes/qliro-one-functions.php';

			include_once QLIRO_WC_PLUGIN_PATH . '/classes/widgets/class-qliro-one-banner-widget.php';
			include_once QLIRO_WC_PLUGIN_PATH . '/classes/widgets/class-qliro-one-payment-widget.php';

			$this->api              = new Qliro_One_API();
			$this->merchant_urls    = new Qliro_One_Merchant_URLS();
			$this->order_management = new Qliro_One_Order_Management();

			$this->pickup_points_service = new PickupPoints();

			// todo include files.
			load_plugin_textdomain( 'qliro-one-for-woocommerce', false, plugin_basename( __DIR__ ) . '/languages' );
			add_filter( 'woocommerce_payment_gateways', array( $this, 'add_gateways' ) );

			add_action( 'before_woocommerce_init', array( $this, 'declare_wc_compatibility' ) );
		}

		/**
		 * Declare compatibility with WooCommerce features.
		 *
		 * @return void
		 */
		public function declare_wc_compatibility() {
			// Declare HPOS compatibility.
			if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
				\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			}
		}


		/**
		 * Initialize composers autoloader. If it does not exist, bail and show an error.
		 *
		 * @return mixed
		 */
		private static function init_composer() {
			$autoloader = QLIRO_WC_PLUGIN_PATH . '/vendor/autoload.php';

			if ( ! is_readable( $autoloader ) ) {
				self::missing_autoloader();
				return false;
			}

			$autoloader_result = require $autoloader;
			if ( ! $autoloader_result ) {
				return false;
			}

			return $autoloader_result;
		}

		/**
		 * Print error message for missing autoloader.
		 *
		 * @return void
		 */
		private static function missing_autoloader() {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( // phpcs:ignore
					esc_html__( 'Your installation of Qliro One for WooCommerce is not complete. If you installed this plugin directly from Github please refer to the README.DEV.md file in the plugin.', 'qliro-one-for-woocommerce' )
				);
			}

			add_action(
				'admin_notices',
				function () {
					?>
																													<div class="notice notice-error">
																														<p>
																															<?php echo esc_html__( 'Your installation of Qliro One for WooCommerce is not complete. If you installed this plugin directly from Github please refer to the README.DEV.md file in the plugin.', 'qliro-one-for-woocommerce' ); ?>
																														</p>
																													</div>
																												<?php
				}
			);
		}

		/**
		 * Add the gateways to WooCommerce
		 *
		 * @param  array $methods Payment methods.
		 *
		 * @return array $methods Payment methods.
		 * @since  1.0.0
		 */
		public function add_gateways( $methods ) {
			$methods[] = 'Qliro_One_Gateway';

			return $methods;
		}

		/**
		 * Get the pickup points service.
		 *
		 * @return PickupPointServiceInterface
		 */
		public function pickup_points_service() {
			return $this->pickup_points_service;
		}

		/**
		 * Checks the plugin version.
		 *
		 * @return void
		 */
		public function check_version() {
			$update_checker = Puc_v4_Factory::buildUpdateChecker(
				'https://kernl.us/api/v1/updates/6239a998af2c275613f57d25/',
				__FILE__,
				'qliro-one-for-woocommerce'
			);
		}
	}
	Qliro_One_For_WooCommerce::get_instance();
}

/**
 * Main instance QOC WooCommerce.
 *
 * Returns the main instance of QOC.
 *
 * @return Qliro_One_For_WooCommerce
 */
function QOC_WC() { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName
	return Qliro_One_For_WooCommerce::get_instance();
}
