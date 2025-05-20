<?php
/**
 *
 * @package Qliro_One_For_WooCommerce/Includes/Admin/Views
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
?>

<div class="qliro-one-overlay">
	<div class="qliro-one-overlay" id="qliro-discount">
		<h1>Lägg till rabatt</h1>
		<section class="discount-id">
			<div class="row">
				<div class="toggle-box">
					<input type="text" placeholder="Rabatt-ID" />
					<span class="symbol tooltip">i</span>
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
					<input type="text" name="discount-amount">
					<span class="symbol">SEK</span>
				</div>
				<p>=</p>
				<div class="toggle-box">
					<input type="text" name="discount-percentage">
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
					<input type="text" name="discount-vat" placeholder="25">
					<span class="symbol">%</span>
				</div>
			</div>
		</section>
		<hr>
		<section class="summary">
			<div class="total">
				<div class="row">
					<p>Totalbelopp innan</p>
					<p>2961.25 SEK</p>
				</div>
				<div class="row">
					<p class="violet">Rabatt</p>
					<p class="violet">-25% (0 SEK)</p>
				</div>

			</div>
			<hr>
			<div class="new-total center">
				<div class="row new-total-heading">
					<p class="bold">Nytt belopp att betala:</p>
				</div>
				<div class="row new-total-amount">
					<p class="bold">2961.25 SEK</p>
				</div>
			</div>
		</section>
		<footer>
			<div class="row">
				<button>Tillbaka</button>
				<button class="confirm">Bekräfta</button>
			</div>
		</footer>
	</div>

</div>