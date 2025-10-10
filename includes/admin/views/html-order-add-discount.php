<?php
/**
 *
 * @package Qliro_One_For_WooCommerce/Includes/Admin/Views
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$total_amount = wc_format_decimal( $order->get_total() );

$fees = array();
foreach ( $order->get_fees() as $fee ) {
	$id = $fee->get_meta( 'qliro_discount_id' );
	if ( ! empty( $id ) ) {
		$fees[] = $id;
	}
}
$fees = wp_json_encode( $fees );

$currency = $order->get_currency();

$section_1 = array(
	'section_title' => array(
		'name' => '',
		'type' => 'title',
	),
	'discount_id'   => array(
		'name'     => __( 'Discount ID', 'qliro-one-for-woocommerce' ),
		'desc_tip' => true,
		'desc'     => __( 'Contains article number and discount number. E.g. articleno_discount01', 'qliro-one-for-woocommerce' ),
		'id'       => 'qliro-discount-id',
		'type'     => 'text',
	),
	'section_end'   => array(
		'type' => 'sectionend',
	),
);

$section_2 = array(
	'section_title'       => array(
		'name' => __( 'Enter amount or percentage', 'qliro-one-for-woocommerce' ),
		'type' => 'title',
	),
	'discount_amount'     => array(
		// translators: %s: Currency code, e.g. SEK.
		'name'              => sprintf( __( 'Total amount (%s)', 'qliro-one-for-woocommerce' ), $currency ),
		'id'                => 'qliro-discount-amount',
		'type'              => 'number',
		'placeholder'       => $currency,
		'custom_attributes' => array(
			'step' => 'any',
			'min'  => '0.00',
			'max'  => $total_amount,
		),
	),
	'discount_percentage' => array(
		'name'              => __( 'Percentage (%)', 'qliro-one-for-woocommerce' ),
		'id'                => 'qliro-discount-percentage',
		'type'              => 'number',
		'placeholder'       => '%',
		'custom_attributes' => array(
			'step' => 'any',
			'min'  => '0.00',
			'max'  => '100.00',
		),
	),
	'section_end'         => array(
		'type' => 'sectionend',
	),
);

$section_3 = array(
	'section_title'           => array(
		'name' => __( 'New amount to pay', 'qliro-one-for-woocommerce' ),
		'type' => 'title',
	),
	'total_amount'            => array(
		'name'              => __( 'Total amount before discount', 'qliro-one-for-woocommerce' ),
		'id'                => 'qliro-total-amount',
		'type'              => 'text',
		'value'             => $total_amount . ' ' . $currency,
		'custom_attributes' => array(
			'readonly' => 'readonly',
		),
	),
	'new_discount_percentage' => array(
		'name'              => __( 'Discount', 'qliro-one-for-woocommerce' ),
		'id'                => 'qliro-new-discount-percentage',
		'type'              => 'text',
		'value'             => '0.00',
		'custom_attributes' => array(
			'readonly' => 'readonly',
		),
	),
	'new_total_amount'        => array(
		'name'              => __( 'New total amount to pay', 'qliro-one-for-woocommerce' ),
		'id'                => 'qliro-new-total-amount',
		'type'              => 'text',
		'value'             => $total_amount . ' ' . $currency,
		'custom_attributes' => array(
			'readonly' => 'readonly',
		),
	),
	'section_end'             => array(
		'type' => 'sectionend',
	),
);
?>

<div id="wc-backbone-modal-dialog" class="qliro-discount-form-modal" hidden>
	<div class="wc-backbone-modal wc-order-preview">
		<div class="wc-backbone-modal-content" tabindex="0">
			<section class="wc-backbone-modal-main" role="main">
				<header class="wc-backbone-modal-header">
					<h1 id='qliro-discount-form-heading'><?php esc_html_e( 'Add discount', 'qliro-one-for-woocommerce' ); ?></h1>
					<button class="modal-close modal-close-link dashicons dashicons-no-alt">
						<span class="screen-reader-text"><?php esc_html_e( 'Close modal panel', 'qliro-one-for-woocommerce' ); ?></span>
					</button>
				</header>
				<article id="qliro-discount-form" style="max-height: 851.25px;" data-fees="<?php echo esc_attr( $fees ); ?>" data-total-amount="<?php echo esc_attr( $total_amount ); ?>">
					<?php woocommerce_admin_fields( $section_1 ); ?>
					<p id="qliro-discount-id-error" class="explanation hidden error"><?php esc_html_e( 'Discount ID must be unique', 'qliro-one-for-woocommerce' ); ?></p>
					<hr>

					<?php woocommerce_admin_fields( $section_2 ); ?>
					<p id="qliro-discount-notice" class="explanation"><?php esc_html_e( 'The percentage is based on the total amount', 'qliro-one-for-woocommerce' ); ?></p>
					<p id="qliro-discount-error" class="woocommerce-error explanation error hidden"><?php esc_html_e( 'The amount must not be equal to or exceed the total amount.', 'qliro-one-for-woocommerce' ); ?></p>
					<hr>

					<?php woocommerce_admin_fields( $section_3 ); ?>

				</article>
				<footer>
					<div class="inner">
						<div class="wc-action-button-group">
							<span class="wc-action-button-group__items">
								<button id="qliro-discount-form-close modal-close" class="button wc-action-button wc-action-button-complete complete" aria-label="<?php esc_attr_e( 'Back', 'qliro-one-for-woocommerce' ); ?>" title="<?php esc_attr_e( 'Back', 'qliro-one-for-woocommerce' ); ?>"><?php esc_html_e( 'Back', 'qliro-one-for-woocommerce' ); ?></button>
							</span>
						</div>

						<button type="submit" disabled id="qliro-discount-form-submit" class="button button-primary button-large" aria-label="<?php esc_attr_e( 'Confirm', 'qliro-one-for-woocommerce' ); ?>" formaction="<?php echo esc_url( $action_url ); ?>"><?php esc_html_e( 'Confirm', 'qliro-one-for-woocommerce' ); ?></button>
					</div>
				</footer>
			</section>
		</div>
	</div>
	<div class="wc-backbone-modal-backdrop modal-close"></div>
</div>
