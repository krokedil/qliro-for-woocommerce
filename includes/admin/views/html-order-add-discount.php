<?php
/**
 *
 * @package Qliro_One_For_WooCommerce/Includes/Admin/Views
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// WC isn't consistent in their tax naming. E.g., "VAT 12" is returned as "vat-12".
$tax_classes = $order->get_items_tax_classes();
$tax_rates   = array();
foreach ( $tax_classes as $tax_class ) {
	$tax_rate = WC_Tax::get_base_tax_rates( $tax_class );
	if ( empty( $tax_rate ) ) {
		continue;
	}

	$tax_rate                = reset( $tax_rate );
	$tax_rates[ $tax_class ] = $tax_rate['rate'];
}

// We must exclude shipping and any fees from the available discount amount.
$items_total_amount = array_reduce( $order->get_items( 'line_item' ), fn( $total_amount, $item ) => $total_amount + ( $item->get_total() + $item->get_total_tax() ) ) ?? 0;
$fees_total_amount  = array_reduce( $order->get_fees(), fn( $total_amount, $item ) => $total_amount + ( $item->get_total() + $item->get_total_tax() ) ) ?? 0;
$available_amount   = max( 0, $items_total_amount - abs( $fees_total_amount ) );

$total_amount = wc_format_decimal( $order->get_total() );

$fees = array();
foreach ( $order->get_fees() as $fee ) {
	$id = $fee->get_meta( 'qliro_discount_id' );
	if ( ! empty( $id ) ) {
		$fees[] = $id;
	}
}
$fees     = wp_json_encode( $fees );
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
	'discount_tax_class'  => array(
		'name'    => __( 'VAT Percentage (%)', 'qliro-one-for-woocommerce' ),
		'id'      => 'qliro-discount-tax-class',
		'type'    => 'select',
		'default' => (string) array_key_first( $tax_rates ),
		'options' => array_combine(
			array_keys( $tax_rates ),
			array_map( fn( $rate ) => "{$rate}%", $tax_rates )
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
		'value'             => wp_strip_all_tags( wc_price( $available_amount, array( 'currency' => $currency ) ) ),
		'custom_attributes' => array(
			'readonly' => 'readonly',
		),
	),
	'new_discount_percentage' => array(
		'name'              => __( 'Discount', 'qliro-one-for-woocommerce' ),
		'id'                => 'qliro-new-discount-percentage',
		'type'              => 'text',
		'value'             => '0%',
		'custom_attributes' => array(
			'readonly' => 'readonly',
		),
	),
	'new_total_amount'        => array(
		'name'              => __( 'New total amount to pay', 'qliro-one-for-woocommerce' ),
		'id'                => 'qliro-new-total-amount',
		'type'              => 'text',
		'value'             => wp_strip_all_tags( wc_price( $available_amount, array( 'currency' => $currency ) ) ),
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
				<article id="qliro-discount-form" style="max-height: 851.25px;" data-fees="<?php esc_attr_e( $fees ); ?>" data-total-amount="<?php esc_attr_e( $total_amount ); ?>" data-available-amount="<?php esc_attr_e( wc_format_decimal( $available_amount ) ); ?>">
					<?php woocommerce_admin_fields( $section_1 ); ?>
					<p id="qliro-discount-id-error" class="explanation hidden error"><?php esc_html_e( 'Discount ID must be unique', 'qliro-one-for-woocommerce' ); ?></p>
					<hr>

					<?php woocommerce_admin_fields( $section_2 ); ?>
					<p id="qliro-discount-notice" class="explanation"><?php esc_html_e( 'The percentage is calculated based on the total amount, excluding shipping and fees.', 'qliro-one-for-woocommerce' ); ?></p>
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
