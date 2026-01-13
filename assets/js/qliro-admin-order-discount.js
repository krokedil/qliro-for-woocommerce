/* global jQuery, qliro_discount_data, woocommerce_admin_meta_boxes, accounting */
jQuery(document).ready(function ($) {
	const qliroDiscount = {
		// Modal elements.
		$modal: $('.qliro-discount-modal'),
		$modalContent: $('.qliro-discount-modal-content'),
		$addDiscountButton: $('#qliro_add_order_discount'),
		$submitButton: $('#qliro-discount-add-button'),
		$cancelButton: $('#qliro-discount-cancel-button'),

		// Form inputs.
		$discountIdInput: $('#qliro-discount-id'),
		$amountInput: $('#qliro-discount-amount'),
		$percentInput: $('#qliro-discount-percent'),
		$vatRateSelect: $('#qliro-discount-vat-rate'),

		// Summary fields.
		$summaryTotalBefore: $('#qliro-discount-total-summary'),
		$summaryDiscountPercent: $('#qliro-discount-percent-summary'),
		$summaryDiscountAmount: $('#qliro-discount-amount-summary'),
		$summaryTotalAfter: $('#qliro-discount-total-after-summary'),

		// State variables.
		discountId: '',
		discountAmount: 0,
		vatRate: 0,
		discountPercent: 0,
		orderTotalAfterDiscount: 0,
		orderTotalDiscountPercent: 0,

		altDiscountSummary: false,

		// Props injected from the server.
		props: {
			orderTotalAmount: 0,
			discountableAmount: 0,
			actionUrl: '',
			previousDiscountIds: [],
			i18n: {
				invalidAmount: '',
				invalidPercent: '',
				invalidDiscountId: '',
			},
		},

		/**
		 * Handle changes to the discount id input.
		 *
		 * @return {void}
		 */
		onDiscountIdChange() {
			// Update the discount id state variable.
			qliroDiscount.discountId = qliroDiscount.$discountIdInput
				.val()
				.trim();

			// Ensure the discount id is unique to previous discounts.
			if (
				qliroDiscount.discountId !== '' &&
				qliroDiscount.props.previousDiscountIds.includes(
					qliroDiscount.discountId
				)
			) {
				qliroDiscount.$discountIdInput[0].setCustomValidity(
					qliroDiscount.props.i18n.invalidDiscountId
				);
				qliroDiscount.$discountIdInput[0].reportValidity();
			} else {
				qliroDiscount.$discountIdInput[0].setCustomValidity('');
			}

			qliroDiscount.validateForm();
		},

		/**
		 * Handle changes to the VAT rate select.
		 *
		 * @return {void}
		 */
		onVatRateChange() {
			// Update the vat rate state variable.
			qliroDiscount.vatRate = qliroDiscount.$vatRateSelect.val();
			qliroDiscount.validateForm();
		},

		/**
		 * Handle changes to the amount input.
		 *
		 * @return {void}
		 */
		onAmountChange() {
			// Update the percent input based on the amount input.
			const amount = parseFloat(qliroDiscount.$amountInput.val()) || 0;
			const percent =
				(amount / qliroDiscount.props.discountableAmount) * 100;

			// Ensure the discount is valid, otherwise set discount to 100% of available amount.
			if (!qliroDiscount.ensureValidDiscount(amount)) {
				qliroDiscount.$amountInput[0].setCustomValidity(
					qliroDiscount.props.i18n.invalidAmount
				);
				qliroDiscount.$amountInput[0].reportValidity();
				qliroDiscount.forceFullDiscount();
				qliroDiscount.validateForm();
			} else {
				qliroDiscount.discountAmount = amount;
				qliroDiscount.discountPercent = percent;
				qliroDiscount.$percentInput.val(percent.toFixed(2));
				qliroDiscount.$amountInput[0].setCustomValidity('');
			}

			qliroDiscount.validateForm();
		},

		/**
		 * Handle changes to the percent input.
		 *
		 * @return {void}
		 */
		onPercentChange() {
			// Update the amount input based on the percent input.
			const percent = parseFloat(qliroDiscount.$percentInput.val()) || 0;
			const amount =
				(percent / 100) * qliroDiscount.props.discountableAmount;

			// Ensure the discount is valid, otherwise set discount to 100% of available amount.
			if (!qliroDiscount.ensureValidDiscount(amount)) {
				qliroDiscount.$percentInput[0].setCustomValidity(
					qliroDiscount.props.i18n.invalidPercent
				);
				qliroDiscount.$percentInput[0].reportValidity();
				qliroDiscount.forceFullDiscount();
			} else {
				qliroDiscount.discountAmount = amount;
				qliroDiscount.discountPercent = percent;
				qliroDiscount.$amountInput.val(amount.toFixed(2));
				qliroDiscount.$percentInput[0].setCustomValidity('');
			}

			qliroDiscount.validateForm();
		},

		/**
		 * Handle the form submission.
		 *
		 * @param {Event} e The submit event.
		 */
		onSubmit(e) {
			// Before submitting, set the form action to the correct URL.
			e.preventDefault();

			// Block the UI to prevent multiple submissions.
			qliroDiscount.$modalContent.block({
				message: null,
				overlayCSS: {
					background: '#fff',
					opacity: 0.6,
				},
			});

			// Ensure the vat rate is set.
			qliroDiscount.vatRate = qliroDiscount.$vatRateSelect.val();

			// Parse the action url to get the current parameters.
			const url = new URL(qliroDiscount.props.actionUrl);

			// Replace the discount_amount, discount_id and discount_vat_rate parameters in the action URL.
			url.searchParams.set(
				'discount_amount',
				qliroDiscount.discountAmount.toFixed(2)
			);
			url.searchParams.set('discount_id', qliroDiscount.discountId);
			url.searchParams.set('discount_vat_rate', qliroDiscount.vatRate);

			// Redirect to the action URL to submit the discount.
			window.location.href = url.toString();
		},

		/**
		 * Validate the form inputs and update the summary.
		 *
		 * @return {boolean} True if the form is valid, false otherwise.
		 */
		validateForm() {
			let isValid = true;

			// Ensure the discount id is not empty.
			if (qliroDiscount.discountId === '') {
				isValid = false;
			}

			// Ensure the discount id is unique to previous discounts.
			if (
				qliroDiscount.discountId !== '' &&
				qliroDiscount.props.previousDiscountIds.includes(
					qliroDiscount.discountId
				)
			) {
				isValid = false;
			}

			// Ensure the discount amount is greater than zero.
			if (qliroDiscount.discountAmount <= 0) {
				isValid = false;
			}

			// Ensure the discount is valid.
			if (
				!qliroDiscount.ensureValidDiscount(qliroDiscount.discountAmount)
			) {
				isValid = false;
			}

			// Enable or disable the submit button based on validity.
			if (isValid) {
				qliroDiscount.enableSubmitButton();
			} else {
				qliroDiscount.disableSubmitButton();
			}

			// Always update the summary.
			qliroDiscount.updateSummary();

			return isValid;
		},

		/**
		 * Disable the submit button.
		 *
		 * @return {void}
		 */
		disableSubmitButton() {
			qliroDiscount.$submitButton.prop('disabled', true);
		},

		/**
		 * Enable the submit button.
		 *
		 * @return {void}
		 */
		enableSubmitButton() {
			qliroDiscount.$submitButton.prop('disabled', false);
		},

		/**
		 * Force the discount to be the full available amount.
		 *
		 * @return {void}
		 */
		forceFullDiscount() {
			qliroDiscount.discountAmount =
				qliroDiscount.props.discountableAmount;
			qliroDiscount.discountPercent = 100.0;
			qliroDiscount.$amountInput.val(
				qliroDiscount.discountAmount.toFixed(2)
			);
			qliroDiscount.$percentInput.val(
				qliroDiscount.discountPercent.toFixed(2)
			);
			qliroDiscount.updateSummary();
		},

		/**
		 * Ensure the discount amount is valid.
		 *
		 * @param {number} amount The discount amount.
		 * @return {boolean} True if the discount is valid, false otherwise.
		 */
		ensureValidDiscount(amount) {
			// Ensure the discount does not exceed the available amount.
			if (amount > qliroDiscount.props.discountableAmount) {
				return false;
			}
			return true;
		},

		/**
		 * Update the summary fields with the current discount values.
		 *
		 * @return {void}
		 */
		updateSummary() {
			// Calculate the new totals.
			qliroDiscount.calculateNewTotal();

			// Update the summary fields with the new values.
			if (!qliroDiscount.altDiscountSummary) {
				qliroDiscount.$summaryTotalBefore.text(
					qliroDiscount.formatPrice(
						qliroDiscount.props.discountableAmount
					)
				);
				qliroDiscount.$summaryDiscountPercent.text(
					qliroDiscount.discountPercent.toFixed(2) + '%'
				);
				qliroDiscount.$summaryDiscountAmount.text(
					'(' +
						qliroDiscount.formatPrice(
							qliroDiscount.discountAmount
						) +
						')'
				);
				qliroDiscount.$summaryTotalAfter.text(
					qliroDiscount.formatPrice(
						qliroDiscount.orderTotalAfterDiscount
					)
				);
			} else {
				qliroDiscount.$summaryTotalBefore.text(
					qliroDiscount.formatPrice(
						qliroDiscount.props.orderTotalAmount
					)
				);
				qliroDiscount.$summaryDiscountPercent.text(
					qliroDiscount.orderTotalDiscountPercent.toFixed(2) + '%'
				);
				qliroDiscount.$summaryDiscountAmount.text(
					'(' +
						qliroDiscount.formatPrice(
							qliroDiscount.discountAmount
						) +
						')'
				);
				qliroDiscount.$summaryTotalAfter.text(
					qliroDiscount.formatPrice(
						qliroDiscount.orderTotalAfterDiscount
					)
				);
			}
		},

		/**
		 * Calculate the new total after applying the discount.
		 *
		 * @return {void}
		 */
		calculateNewTotal() {
			if (!qliroDiscount.altDiscountSummary) {
				const newTotal =
					qliroDiscount.props.discountableAmount -
					qliroDiscount.discountAmount;
				qliroDiscount.orderTotalAfterDiscount =
					newTotal >= 0 ? newTotal : 0;
			} else {
				const newTotal =
					qliroDiscount.props.orderTotalAmount -
					qliroDiscount.discountAmount;
				const newTotalPercent =
					(newTotal / qliroDiscount.props.orderTotalAmount) * 100;
				qliroDiscount.orderTotalDiscountPercent = 100 - newTotalPercent;
				qliroDiscount.orderTotalAfterDiscount =
					newTotal >= 0 ? newTotal : 0;
			}
		},

		/**
		 * Format a price using the WooCommerce currency settings.
		 *
		 * @param {number} amount The amount to format.
		 * @return {string} The formatted price.
		 */
		formatPrice(amount) {
			/* eslint-disable camelcase */
			return accounting.formatMoney(amount, {
				symbol: woocommerce_admin_meta_boxes.currency_format_symbol,
				decimal:
					woocommerce_admin_meta_boxes.currency_format_decimal_sep,
				thousand:
					woocommerce_admin_meta_boxes.currency_format_thousand_sep,
				precision:
					woocommerce_admin_meta_boxes.currency_format_num_decimals,
				format: woocommerce_admin_meta_boxes.currency_format,
			});
			/* eslint-enable camelcase */
		},

		/**
		 * Initialize the discount modal functionality.
		 *
		 * @return {void}
		 */
		init() {
			/* eslint-disable camelcase */
			qliroDiscount.props = qliro_discount_data;
			/* eslint-enable camelcase */

			// Ensure qliroDiscount.props.discountableAmount is a float.
			qliroDiscount.props.discountableAmount =
				parseFloat(qliroDiscount.props.discountableAmount) || 0;

			// Set properties from the qliro_discount object.
			qliroDiscount.$addDiscountButton.on('click', function (e) {
				e.preventDefault();
				qliroDiscount.$modal.show();
			});

			qliroDiscount.$cancelButton.on('click', function (e) {
				e.preventDefault();
				qliroDiscount.$modal.hide();
			});

			// On any change to the form fields, set the state variables, validate the form and update the summary.
			qliroDiscount.$discountIdInput.on(
				'input',
				qliroDiscount.onDiscountIdChange
			);
			qliroDiscount.$vatRateSelect.on(
				'change',
				qliroDiscount.onVatRateChange
			);
			qliroDiscount.$amountInput.on(
				'input',
				qliroDiscount.onAmountChange
			);
			qliroDiscount.$percentInput.on(
				'input',
				qliroDiscount.onPercentChange
			);

			qliroDiscount.$submitButton.on('click', qliroDiscount.onSubmit);

			// Ensure the summary shows the correct initial values.
			qliroDiscount.updateSummary();
		},
	};
	qliroDiscount.init();
});
