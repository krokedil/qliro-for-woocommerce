<?php
/**
 * HTML template for the Qliro One discount form modal in the order admin.
 *
 * @package Qliro_One_For_WooCommerce/Includes/Admin/Views
 */

defined( 'ABSPATH' ) || exit;

/**
 * Ensure we have the necessary data.
 *
 * @var array{ action_url: string, items_total_amount: float, available_amount: float, total_amount: float, currency: string, order: WC_Order|null } $qliro_discount_data
 */
if ( ! isset( $qliro_discount_data['currency'], $qliro_discount_data['total_amount'], $qliro_discount_data['available_amount'], $qliro_discount_data['fees'], $qliro_discount_data['order'] ) ) {
	return;
}

?>

<div id="wc-backbone-modal-dialog" class="qliro-discount-form-modal" hidden>
	<div class="wc-backbone-modal wc-order-preview">
		<div class="wc-backbone-modal-content" tabindex="0">
			<section class="wc-backbone-modal-main" role="main">
				<header class="wc-backbone-modal-header">
					<h1 id='qliro-discount-form-heading'><?php esc_html_e( 'Add discount', 'qliro-for-woocommerce' ); ?></h1>
					<button class="modal-close modal-close-link dashicons dashicons-no-alt">
						<span class="screen-reader-text"><?php esc_html_e( 'Close modal panel', 'qliro-for-woocommerce' ); ?></span>
					</button>
				</header>
				<article id="qliro-discount-form" style="max-height: 851.25px;" data-fees="<?php echo esc_attr( $qliro_discount_data['fees'] ); ?>" data-total-amount="<?php echo esc_attr( $qliro_discount_data['total_amount'] ); ?>" data-available-amount="<?php echo esc_attr( wc_format_decimal( $qliro_discount_data['available_amount'] ) ); ?>">
					<?php woocommerce_admin_fields( Qliro_Order_Discount::get_discount_id_section_fields() ); ?>
					<p id="qliro-discount-id-error" class="explanation hidden error"><?php esc_html_e( 'Discount ID must be unique', 'qliro-for-woocommerce' ); ?></p>
					<hr>

					<?php woocommerce_admin_fields( Qliro_Order_Discount::get_discount_amount_section_fields( $qliro_discount_data['currency'] ?? '', $qliro_discount_data['total_amount'] ?? '', $qliro_discount_data['order'] ?? null ) ); ?>
					<p id="qliro-discount-notice" class="explanation"><?php esc_html_e( 'The percentage is calculated based on the total amount, excluding shipping and fees.', 'qliro-for-woocommerce' ); ?></p>
					<p id="qliro-discount-error" class="woocommerce-error explanation error hidden"><?php esc_html_e( 'The amount must not be equal to or exceed the total amount.', 'qliro-for-woocommerce' ); ?></p>
					<hr>

					<?php woocommerce_admin_fields( Qliro_Order_Discount::get_summary_section_fields( $qliro_discount_data['currency'] ?? '', $qliro_discount_data['total_amount'] ?? '' ) ); ?>

				</article>
				<footer>
					<div class="inner">
						<div class="wc-action-button-group">
							<span class="wc-action-button-group__items">
								<button id="qliro-discount-form-close modal-close" class="button wc-action-button wc-action-button-complete complete" aria-label="<?php esc_attr_e( 'Back', 'qliro-for-woocommerce' ); ?>" title="<?php esc_attr_e( 'Back', 'qliro-for-woocommerce' ); ?>"><?php esc_html_e( 'Back', 'qliro-for-woocommerce' ); ?></button>
							</span>
						</div>

						<button type="submit" disabled id="qliro-discount-form-submit" class="button button-primary button-large" aria-label="<?php esc_attr_e( 'Confirm', 'qliro-for-woocommerce' ); ?>" formaction="<?php echo esc_url( $qliro_discount_data['action_url'] ); ?>"><?php esc_html_e( 'Confirm', 'qliro-for-woocommerce' ); ?></button>
					</div>
				</footer>
			</section>
		</div>
	</div>
	<div class="wc-backbone-modal-backdrop modal-close"></div>
</div>
