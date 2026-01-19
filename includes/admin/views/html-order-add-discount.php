<?php
/**
 * HTML for the Add Discount modal in the Order Edit screen.
 *
 * @package Qliro_One_For_WooCommerce/Includes/Admin/Views
 */

defined( 'ABSPATH' ) || exit;
?>

<div id="qliro-discount-modal" class="qliro-discount-modal">
	<div class="qliro-discount-modal-content">
		<div class="qliro-discount-modal-header">
			<h2 class="qliro-discount-modal-title"><?php esc_html_e( 'Add Discount', 'qliro-for-woocommerce' ); ?></h2>
		</div>
		<div class="qliro-discount-modal-info">
			<span><?php esc_html_e( 'Discounts can only be applied to the products in the order.', 'qliro-for-woocommerce' ); ?></span>
			<br />
			<span><?php esc_html_e( 'The totals shown exclude the price for shipping and fees, and including VAT.', 'qliro-for-woocommerce' ); ?></span>
		</div>
		<div class="qliro-discount-modal-form-wrapper">
			<form id="qliro-discount-form" method="POST">
				<div class="qliro-discount-input-wrapper-full-width qliro-discount-input-wrapper-label-top">
					<div class="qliro-discount-tip">
						<?php echo wp_kses_post( wc_help_tip( __( 'Contains article number and discount number. E.g. articleno_discount01', 'qliro-for-woocommerce' ) ) ); ?>
					</div>
					<input type="text" name="qliro-discount-id" id="qliro-discount-id" placeholder="" required />
					<label for="qliro-discount-id"><?php esc_html_e( 'Discount ID', 'qliro-for-woocommerce' ); ?></label>
				</div>
				<div class="qliro-discount-modal-separator"></div>
				<span class="qliro-discount-label"><?php esc_html_e( 'Enter amount or percent', 'qliro-for-woocommerce' ); ?></span>
				<div class="qliro-discount-amount-wrapper">
					<div class="qliro-discount-input-wrapper">
						<input type="number" step="0.01" min="0" max="9999" name="qliro-discount-amount" id="qliro-discount-amount" placeholder="" required />
						<label for="qliro-discount-amount"><?php echo esc_html( get_woocommerce_currency_symbol() ); ?></label>
					</div>
					<span>=</span>
					<div class="qliro-discount-input-wrapper">
						<input type="number" step="0.01" min="0" max="100" name="qliro-discount-percent" id="qliro-discount-percent" placeholder="" required />
						<label for="qliro-discount-percent"><?php esc_html_e( '%', 'qliro-for-woocommerce' ); ?></label>
					</div>
				</div>
				<?php if ( ! empty( $qliro_discount_data['vat_rates'] ?? array() ) ) : ?>
					<div class="qliro-discount-modal-separator"></div>
					<label for="qliro-discount-vat-rate" class="qliro-discount-label"><?php esc_html_e( 'VAT Rate', 'qliro-for-woocommerce' ); ?></label>
					<select name="qliro-discount-vat-rate" id="qliro-discount-vat-rate" required>
						<?php foreach ( $qliro_discount_data['vat_rates'] as $qliro_vat_rate ) : ?>
							<option value="<?php echo esc_attr( $qliro_vat_rate['id'] ); ?>"><?php echo esc_html( $qliro_vat_rate['percentage'] ); ?>%</option>
						<?php endforeach; ?>
					</select>
				<?php endif; ?>
			</form>
		</div>
		<div class="qliro-discount-modal-summary">
			<span class="qliro-discount-label"><?php esc_html_e( 'Summary', 'qliro-for-woocommerce' ); ?></span>
			<div class="qliro-summary-line">
				<p><?php esc_html_e( 'Total before discount', 'qliro-for-woocommerce' ); ?></p>
				<p id="qliro-discount-total-summary"></p>
			</div>
			<div class="qliro-summary-line">
				<p><?php esc_html_e( 'Discount', 'qliro-for-woocommerce' ); ?></p>
				<p>
					<span id="qliro-discount-percent-summary"></span>
					<span id="qliro-discount-amount-summary">()</span>
				</p>
			</div>
			<div class="qliro-summary-line">
				<p><b><?php esc_html_e( 'New total after discount', 'qliro-for-woocommerce' ); ?></b></p>
				<p id="qliro-discount-total-after-summary"></p>
			</div>
		</div>
		<div class="qliro-discount-modal-footer">
			<button type="button" id="qliro-discount-cancel-button" class="button"><?php esc_html_e( 'Cancel', 'qliro-for-woocommerce' ); ?></button>
			<button type="button" id="qliro-discount-add-button" type="submit" form="qliro-discount-form" class="button button-primary" disabled><?php esc_html_e( 'Add Discount', 'qliro-for-woocommerce' ); ?></button>
		</div>
	</div>
</div>
