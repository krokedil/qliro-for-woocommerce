<?php
/**
 *
 * @package Qliro_One_For_WooCommerce/Includes/Admin/Views
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$total_amount = wc_format_decimal( $order->get_total() );
?>

<div class="qliro-one-overlay-backdrop">
	<dialog class="qliro-one-overlay" id="qliro-discount-modal" role="dialog">
		<span class="close-button close">&#x2715;</span>
		<div id="qliro-discount" data-total-amount="<?php echo $total_amount; ?>">
			<h1>Lägg till rabatt</h1>
			<section class="discount-id">
				<div class="row">
					<div class="toggle-box">
						<input type="text" placeholder="Rabatt-ID" />
						<div class="tooltip-container">
							<span class="symbol tooltip">i</span>
							<p class="tooltip-hover-text">Innehåller artikelnummer och rabattnummer. Ex. artikelnummer_rabatt01</p>
						</div>
					</div>
				</div>
			</section>
			<hr>
			<section>
				<div class="row">
					<h2 class="bold">Fyll i SEK eller procent</h2>
				</div>
				<div class="row">
					<div class="toggle-box">
						<input type="number" name="qliro-discount-amount" step="any" min="0.00" max="<?php echo $total_amount; ?>">
						<span class="symbol">SEK</span>
					</div>
					<p>=</p>
					<div class="toggle-box">
						<input type="number" name="qliro-discount-percentage" step="any" min="0.00" max="100.00">
						<span class="symbol">%</span>
					</div>
				</div>
				<div class="row">
					<p class="explanation">Procentsatsen är baserad på totalbelopp</p>
				</div>
			</section>
			<hr>
			<section>
				<div class="row">
					<p class="bold">Moms</p>
					<div class="toggle-box">
						<input type="number" name="qliro-discount-vat" placeholder="25" step="any" min="0.00" max="100.00">
						<span class="symbol">%</span>
					</div>
				</div>
			</section>
			<hr>
			<section class="summary">
				<div class="total">
					<div class="row">
						<p>Totalbelopp innan</p>
						<p class="price"><?php echo $total_amount; ?> SEK</p>
					</div>
					<div class="row">
						<p class="violet">Rabatt</p>
						<p class="violet price"><span class="discount-percentage">0</span>% (<span class="discount-amount">0.00</span> SEK)</p>
					</div>

				</div>
				<hr>
				<div class="new-total center">
					<div class="row new-total-heading">
						<p class="bold">Nytt belopp att betala:</p>
					</div>
					<div class="row new-total-amount">
						<p class="bold"><span id="qliro-new-total-amount"><?php echo $total_amount; ?></span> SEK</p>
					</div>
				</div>
			</section>
			<footer>
				<div class="row">
					<button class="close">Tillbaka</button>
					<button class="confirm" disabled type="submit" formaction="<?php echo $action_url; ?>">Bekräfta</button>
				</div>
			</footer>
		</div>
</dialog>
</div>