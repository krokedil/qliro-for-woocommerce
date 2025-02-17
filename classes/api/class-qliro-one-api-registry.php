<?php
/**
 * Register the Qliro One API controllers.
 *
 * @package Qliro_One_For_WooCommerce/Classes/API
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Qliro_One_API_Registry
 */
class Qliro_One_API_Registry {
	/**
	 * The list of controllers.
	 *
	 * @var Qliro_One_API_Controller_Base[]
	 */
	protected $controllers = array();

	/**
	 * Class constructor.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->init();
		add_action( 'rest_api_init', array( $this, 'register_controller_routes' ) );
	}

	/**
	 * Initialize the API controllers and models.
	 *
	 * @return void
	 */
	public function init() {
		// Include the controllers.
		include_once __DIR__ . '/controllers/class-qliro-one-api-controller-base.php';
		include_once __DIR__ . '/controllers/class-qliro-one-api-controller-save-card.php';

		// Register the controllers.
		$this->register_controller( new Qliro_One_API_Controller_Save_Card() );
	}

	/**
	 * Register the controllers.
	 *
	 * @return void
	 */
	public function register_controller_routes() {
		foreach ( $this->controllers as $controller ) {
			$controller->register_routes();
		}
	}

	/**
	 * Register a controller.
	 *
	 * @param Qliro_One_API_Controller_Base $controller The controller to register.
	 *
	 * @return void
	 */
	public function register_controller( $controller ) {
		$this->controllers[ get_class( $controller ) ] = $controller;
	}

	/**
	 * Get the request path for a specific controller.
	 *
	 * @param string $controller The controller class name to get the path for.
	 * @param string $endpoint The endpoint to get the path for.
	 *
	 * @return string
	 */
	public function get_request_path( $controller, $endpoint = '' ) {
		if ( isset( $this->controllers[ $controller ] ) ) {
			$path = $this->controllers[ $controller ]->get_request_path();
			return get_rest_url( null, "qliro/$path/$endpoint" );
		}

		return '';
	}
}
