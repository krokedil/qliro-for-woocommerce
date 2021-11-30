<?php // phpcs:ignore
/**
 * Plugin Name: Qliro One for WooCommerce
 * Plugin URI: https://krokedil.com/qliro/
 * Description: Qliro One Checkout payment gateway for WooCommerce.
 * Author: Krokedil
 * Author URI: https://krokedil.com/
 * Version: 2.6.3
 * Text Domain: qliro-one-for-woocommerce.php
 * Domain Path: /languages
 *
 * WC requires at least: 4.0.0
 * WC tested up to: 5.9.0
 *
 * Copyright (c) 2017-2021 Krokedil
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
define( 'QLIRO_WC_VERSION', '2.6.3' );
define( 'QLIRO_WC_MIN_PHP_VER', '5.6.0' );
define( 'QLIRO_WC_MIN_WC_VER', '3.9.0' );
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
			wc_doing_it_wrong( __FUNCTION__, __( 'Nope' ), '1.0' );
		}

		/**
		 * Private unserialize method to prevent unserializing of the *Singleton*
		 * instance.
		 *
		 * @return void
		 */
		public function __wakeup() {
			wc_doing_it_wrong( __FUNCTION__, __( 'Nope' ), '1.0' );
		}

		/**
		 * Protected constructor to prevent creating a new instance of the
		 * *Singleton* via the `new` operator from outside of this class.
		 */
		protected function __construct() {
			add_action( 'plugins_loaded', array( $this, 'init' ) );
			add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_action_links' ) );
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
			$section_slug = 'qoc';

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
			// todo include files.
			load_plugin_textdomain( 'qliro-one-for-woocommerce', false, plugin_basename( __DIR__ ) . '/languages' );
			add_filter( 'woocommerce_payment_gateways', array( $this, 'add_gateways' ) );
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
			$methods[] = 'QOC_Gateway';

			return $methods;
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
