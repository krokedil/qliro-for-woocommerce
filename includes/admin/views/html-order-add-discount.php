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


$section_1 = array(
	'section_title' => array(
		'name' => '',
		'type' => 'title',
	),
	'discount_id' => array(
		'name' => 'Rabatt-ID',
		'desc_tip' => true,
		'desc' => 'Innehåller artikelnummer och rabattnummer. Ex. artikelnummer_rabatt01',
		'id' => 'qliro-discount-id',
		'type' => 'text',
	),
	'section_end' => array(
		'type' => 'sectionend',
	)
);

$section_2 = array(
	'section_title' => array(
		'name' => 'Fyll i SEK eller procent',
		'type' => 'title',
	),
	'discount_amount' => array(
		'name' => 'Totalbelopp (SEK)',
		'id' => 'qliro-discount-amount',
		'type' => 'number',
		'placeholder' => 'SEK',
		'custom_attributes' => array(
			'step' => 'any',
			'min' => '0.00',
			'max' => $total_amount,
		),
	),
	'discount_percentage' => array(
		'name' => 'Procent (%)',
		'id' => 'qliro-discount-percentage',
		'type' => 'number',
		'placeholder' => '%',
		'custom_attributes' => array(
			'step' => 'any',
			'min' => '0.00',
			'max' => '100.00',
		),
	),
	'section_end' => array(
		'type' => 'sectionend',
	)
);

$section_3 = array(
	'section_title' => array(
		'name' => 'Nytt belopp att betala',
		'type' => 'title',
	),
	'total_amount' => array(
		'name' => 'Totalbelopp innan',
		'id' => 'qliro-total-amount',
		'type' => 'text',
		'value' => "$total_amount SEK",
		'custom_attributes' => array(
			'readonly' => 'readonly',
		),
	),
	'new_discount_percentage' => array(
		'name' => 'Rabatt',
		'id' => 'qliro-new-discount-percentage',
		'type' => 'text',
		'value' => '0.00',
		'custom_attributes' => array(
			'readonly' => 'readonly',
		),
	),
	'new_total_amount' => array(
		'name' => 'Nytt belopp att betala',
		'id' => 'qliro-new-total-amount',
		'type' => 'text',
		'value' => "$total_amount SEK",
		'custom_attributes' => array(
			'readonly' => 'readonly',
		),
	),
	'section_end' => array(
		'type' => 'sectionend',
	)
);
?>

<div id="wc-backbone-modal-dialog" class="qliro-discount-form-modal" hidden>
	<div class="wc-backbone-modal wc-order-preview">
		<div class="wc-backbone-modal-content" tabindex="0">
			<section class="wc-backbone-modal-main" role="main">
				<header class="wc-backbone-modal-header">
					<h1 id='qliro-discount-form-heading'>Lägg till rabatt</h1>
					<button class="modal-close modal-close-link dashicons dashicons-no-alt">
						<span class="screen-reader-text">Close modal panel</span>
					</button>
				</header>
				<article id="qliro-discount-form" style="max-height: 851.25px;" data-fees="<?php echo esc_attr($fees); ?>" data-total-amount="<?php echo esc_attr( $total_amount ); ?>">
					<?php woocommerce_admin_fields($section_1); ?>
					<p id="qliro-discount-id-error" class="explanation hidden error">Rabatt-ID måste vara unikt</p>
					<hr>

					<?php woocommerce_admin_fields($section_2); ?>
					<p id="qliro-discount-notice" class="explanation">Procentsatsen är baserad på totalbelopp</p>
					<p id="qliro-discount-error" class="woocommerce-error explanation error hidden">Beloppet får inte vara lika med eller överstiga totalbelopp.</p>
					<hr>

					<?php woocommerce_admin_fields($section_3); ?>

				</article>
				<footer>
					<div class="inner">
						<div class="wc-action-button-group">
							<span class="wc-action-button-group__items">
								<button id="qliro-discount-form-close modal-close" class="button wc-action-button wc-action-button-complete complete" aria-label="Tillbaka" title="Tillbaka">Tillbaka</button>
							</span>
						</div>

						<button type="submit" disabled id="qliro-discount-form-submit" class="button button-primary button-large" aria-label="Bekräfta" formaction="<?php echo $action_url; ?>">Bekräfta</button>
					</div>
				</footer>
			</section>
		</div>
	</div>
	<div class="wc-backbone-modal-backdrop modal-close"></div>
</div>