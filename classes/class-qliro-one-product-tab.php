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
	 * Adds custom product tab for Qliro.
	 *
	 * @param array $tabs of product panel.
	 * @return array
	 */
	public function register_product_tab( $tabs ) {
		$tabs['qliro-product-settings'] = array(
			'label'  => __( 'Qliro', 'qliro-one-for-woocommerce' ),
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
		$product                 = wc_get_product( qoc_get_the_ID() );
		$minimum_age             = $product->get_meta( 'qoc_min_age' );
		$require_id_verification = $product->get_meta( 'qoc_require_id_verification' );
		$has_risk                = $product->get_meta( 'qoc_has_risk' );

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
			woocommerce_wp_checkbox(
				array(
					'id'    => 'qoc_has_risk',
					'label' => __( 'Has risk', 'qliro-one-for-woocommerce' ),
					'value' => ( ! empty( $has_risk ) ) ? $has_risk : '',
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
		$require_id_verification = filter_input( INPUT_POST, 'qoc_require_id_verification', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$has_risk                = filter_input( INPUT_POST, 'qoc_has_risk', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		$product = wc_get_product( $post_id );
		if ( ! empty( $minimum_age ) ) {
			$product->update_meta_data( 'qoc_min_age', $minimum_age );
		}

		// If the checkbox is unchecked, NULL will be returned, not "no" (in contrast to "yes"). Therefore, NULL is a valid value.
		$product->update_meta_data( 'qoc_require_id_verification', $require_id_verification ?? 'no' );
		$product->update_meta_data( 'qoc_has_risk', $has_risk ?? 'no' );
		$product->save();
	}
} new Qliro_One_Product_Tab();
