<?php
/**
 * Class for the product tab.
 *
 * @package Qliro_One/Classes
 */

 /**
  * Class for the product tab.
  */
class Qliro_One_Product_Tab {
	/**
	 * Class constructor.
	 */
	public function __construct() {
		add_filter( 'woocommerce_product_data_tabs', array( $this, 'register_product_tab' ) );
		add_action( 'woocommerce_product_data_panels', array( $this, 'product_options' ) );
		add_action( 'woocommerce_process_product_meta_simple', array( $this, 'save_product_options' ) );
		add_action( 'woocommerce_process_product_meta_variable', array( $this, 'save_product_options' ) );
	}

	/**
	 * Adds custom product tab for Qliro One.
	 *
	 * @param array $tabs of product panel.
	 * @return array
	 */
	public function register_product_tab( $tabs ) {
		$tabs['qliro-product-settings'] = array(
			'label'  => __( 'Qliro One', 'qliro-one-for-woocommerce' ),
			'target' => 'qliro-product-settings',
			'class'  => array( 'show_if_simple', 'show_if_variable', 'show_if_external', 'qliro-tab' ),
		);

		return $tabs;
	}

	/**
	 * Adds the form to the product options.
	 *
	 * @return void
	 */
	public function product_options() {
		global $post;

		$minimum_age             = get_post_meta( $post->ID, 'qoc_min_age', true );
		$require_id_verification = get_post_meta( $post->ID, 'qoc_require_id_verification', true );

		?>
		<div id="qliro-product-settings" class="panel woocommerce_options_panel">
			<?php
			woocommerce_wp_text_input(
				array(
					'id'    => 'qoc_min_age',
					'label' => __( 'Minimum customer age', 'qliro-one-for-woocommerce' ),
					'value' => ( ! empty( $minimum_age ) ) ? $minimum_age : '',
					'type'  => 'number',
				)
			);
			woocommerce_wp_checkbox(
				array(
					'id'    => 'qoc_require_id_verification',
					'label' => __( 'Require ID verification', 'qliro-one-for-woocommerce' ),
					'value' => ( ! empty( $require_id_verification ) ) ? $require_id_verification : '',
				)
			);
			?>
		</div>
		<?php
	}

	/**
	 * Save the custom data to the products meta fields.
	 *
	 * @param int $post_id The WordPress post id.
	 * @return void
	 */
	public function save_product_options( $post_id ) {
		$minimum_age             = filter_input( INPUT_POST, 'qoc_min_age', FILTER_SANITIZE_NUMBER_INT );
		$require_id_verification = filter_input( INPUT_POST, 'qoc_require_id_verification', FILTER_SANITIZE_STRING );

		if ( ! empty( $minimum_age ) ) {
			update_post_meta( $post_id, 'qoc_min_age', $minimum_age );
		}

		if ( ! empty( $require_id_verification ) ) {
			update_post_meta( $post_id, 'qoc_require_id_verification', $require_id_verification );
		}
	}
} new Qliro_One_Product_Tab();
