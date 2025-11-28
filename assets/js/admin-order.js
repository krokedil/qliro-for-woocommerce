jQuery(function ($) {
	const qoc = {
		/**
		 * Create a capture.
		 */
		create_capture: function () {
			// Block the UI.
			qoc.block();

			// Show confirmation dialog.
			if (!window.confirm(qoc_admin_params.make_capture_confirm)) {
				qoc.unblock();
				return; // Bail if user does not confirm.
			}

			// Get all items to deliver.
			let line_items = qoc.get_line_items_to_capture();
			let shipping_items = qoc.get_shipping_items_to_capture();
			let fee_items = qoc.get_fee_items_to_capture();

			// Combine all items to deliver.
			let items_to_deliver = $.extend({}, line_items, shipping_items, fee_items);

			// If the items to deliver is empty, unblock the UI and return, but display an alert box.
			if ($.isEmptyObject(items_to_deliver)) {
				qoc.unblock();
				window.alert(qoc_admin_params.make_capture_no_items);
				return;
			}

			let data = {
				items: items_to_deliver,
				order_id: qoc_admin_params.order_id,
				nonce: qoc_admin_params.make_capture_nonce,
			};

			$.ajax({
				url: qoc_admin_params.make_capture_url,
				data: data,
				type: 'POST',
				dataType: 'json',
				complete: function (response) {
					console.log(response);
					if (true === response.responseJSON.success) {
						// Redirect to same page for show the refunded status
						window.location.reload();
					} else {
						window.alert(response.responseJSON.data);
					}
				}
			});
		},

		/**
		 * Get the line items to deliver.
		 *
		 * @returns {Object} line_items
		 */
		get_line_items_to_capture: function () {
			let line_items = {};
			$('input.qoc-quantity').each(function (index) {
				if ($(this).val() > 0) {
					// Get the line item id from the order_item_id data tag for the tr element.
					let line_item_id = $(this).closest('tr').data('order_item_id');
					line_items[line_item_id] = $(this).val();
				}
			});
			return line_items;
		},

		/**
		 * Get the shipping items to deliver.
		 *
		 * @returns {Object} shipping_items
		 */
		get_shipping_items_to_capture: function () {
			let shipping_items = {};
			$('input.qoc-shipping').each(function (index) {
				if ($(this).is(':checked')) {
					// Get the line item id from the order_item_id data tag for the tr element.
					let line_item_id = $(this).closest('tr').data('order_item_id');
					shipping_items[line_item_id] = 1;
				}
			});
			return shipping_items;
		},

		/**
		 * Get the fee items to deliver.
		 * @returns {Object} fee_items
		 */
		get_fee_items_to_capture: function () {
			let fee_items = {};
			$('input.qoc-fee').each(function (index) {
				if ($(this).is(':checked')) {
					// Get the line item id from the order_item_id data tag for the tr element.
					let line_item_id = $(this).closest('tr').data('order_item_id');
					fee_items[line_item_id] = 1;
				}
			});
			return fee_items;
		},

		/**
		 * Moves the HTML element into the correct position.
		 */
		move_element: function () {
			$('div.wc-order-partial-capture').insertAfter('div.wc-order-refund-items');
		},

		/**
		 * Adds checkboxes for shipping lines and fee lines.
		 */
		add_checkboxes: function () {
			console.log('qoc_admin_params.captured_items', qoc_admin_params.captured_items);
			capturedItems = $.parseJSON(qoc_admin_params.captured_items);
			$("tr.shipping").each(function (index) {
				var id = $(this).data('order_item_id');
				if (null === capturedItems || !capturedItems[id]) {
					$('<input value="' + id + '" type="checkbox" class="qoc-shipping" id="qoc_shipping_' + id + '" style="display:none;">').appendTo($(this).find('td.quantity'));
					$('<span class="woocommerce-help-tip krokedil-help-tip" data-tip="' + qoc_admin_params.shipping_checkbox_text + '"></span>').appendTo($(this).find('td.quantity'));
				}
			});
			$("tr.fee").each(function (index) {
				var id = $(this).data('order_item_id');
				if (null === capturedItems || !capturedItems[id]) {
					$('<input value="' + id + '" type="checkbox" class="qoc-fee" id="qoc_fee_' + id + '" style="display:none;">').appendTo($(this).find('td.quantity'));
					$('<span class="woocommerce-help-tip krokedil-help-tip" data-tip="' + qoc_admin_params.fee_checkbox_text + '"></span>').appendTo($(this).find('td.quantity'));
				}
			});
			$('.woocommerce-help-tip.krokedil-help-tip')
				.tipTip({
					'attribute': 'data-tip',
					'fadeIn': 50,
					'fadeOut': 50,
					'delay': 200
				}).hide();
		},

		/**
		 * Adds input fields for line items.
		 */
		add_input_fields: function () {
			console.log('add_input_fields');
			capturedItems = $.parseJSON(qoc_admin_params.captured_items);
			$("tr.item").each(function (index) {
				var id = $(this).data('order_item_id');
				let quantity = $(this).find('td.quantity div.view').text();
				// Remove anything not an int.
				quantity = quantity.replace(/[^\d.]/g, '');
				let max = quantity;
				if (null !== capturedItems && capturedItems[id]) {
					max -= capturedItems[id];
				}

				if (0 === max) {
					return;
				}

				$('<input step="1" min="0" max="' + max + '" type="number" class="qoc-quantity" name="qoc_order_item_quantity[' + id + ']" style="display:none;">').insertAfter($(this).find('td.quantity div.refund'));
			});
		},

		/**
		 * Function to trigger the HTML changes for Partial capture.
		 */
		capture: function () {
			$('div.wc-order-partial-capture').slideDown();
			$('div.wc-order-data-row-toggle').not('div.wc-order-partial-capture').slideUp();
			$('div.wc-order-totals-items').slideUp();
			$('#woocommerce-order-items').find('.qoc-quantity').show();
			$('input.qoc-shipping').show();
			$('input.qoc-fee').show();
			$('.woocommerce-help-tip.krokedil-help-tip').show();
			$('#woocommerce-order-items').find('td.line_cost div.refund').hide();
			$('#woocommerce-order-items').find('td.line_tax div.refund').hide();
			$('.wc-order-edit-line-item .wc-order-edit-line-item-actions').hide();
		},

		/**
		 * Function to revert HTML changes for Partial capture.
		 */
		cancel_capture: function () {
			$('div.wc-order-data-row-toggle').not('div.wc-order-bulk-actions').slideUp();
			$('div.wc-order-bulk-actions').slideDown();
			$('div.wc-order-totals-items').slideDown();
			$('#woocommerce-order-items').find('.qoc-quantity').hide();
			$('input.qoc-shipping').hide();
			$('input.qoc-fee').hide();
			$('.woocommerce-help-tip.krokedil-help-tip').hide();
		},

		/**
		 * Blocks the meta box.
		 */
		block: function () {
			$('#woocommerce-order-items').block({
				message: null,
				overlayCSS: {
					background: '#fff',
					opacity: 0.6
				}
			});
		},

		/**
		 * Unblocks meta box.
		 */
		unblock: function () {
			$('#woocommerce-order-items').unblock();
		},

		/**
		 * Function to trigger the display of the list of products in the capture order.
		 */
		showListOfDeliveries: function () {
			console.log('showListOfDeliveries');
			const currentHash = location.hash;
			const splittedHash = currentHash.split("=");
			if (splittedHash[0] == "#capture-id") {
				// Get the data from the enqueued params.
				console.log(qoc_admin_params.capture_products);
				const data = qoc_admin_params.capture_products[splittedHash[1]]
				console.log(data);

				let name_list = '';
				for (i = 0; i < data.length; ++i) {
					const name = data[i];
					console.log(name);
					if (name !== undefined) {
						name_list = name_list + name + '<br>';
					}
				}

				jQuery('#capture_items_' + splittedHash[1]).html(name_list);
			}
		},

		refund_items: function() {
			$qliroReturnFee = $('#qliro_return_fee');
			$qliroReturnFee.show();
		},

		cancel_refund: function() {
			$qliroReturnFee = $('#qliro_return_fee');

			if ($qliroReturnFee.attr('data-qliro-hide') === 'no' ) {
				return;
			}

			$qliroReturnFee.hide();
		},

		modify_refund_button_text: function() {
			const $qliroRefundButton = $("button.do-api-refund");
			$qliroRefundButton.append( '<span id="qliro_return_fee_total"></span>' );
		},

		update_qliro_refund_amount: function() {
			const $qliroReturnFeeAmountField = $('#qliro_return_fee input.refund_line_total.wc_input_price');
			const $qliroReturnFeeTaxAmountField = $('#qliro_return_fee input.refund_line_tax.wc_input_price');
			const $qliroReturnFeeTotalSpan = $("span#qliro_return_fee_total");

			const refundFeeAmount = qoc.unformat_number($qliroReturnFeeAmountField.val()) + qoc.unformat_number($qliroReturnFeeTaxAmountField.val());

			if (refundFeeAmount === 0) {
				$qliroReturnFeeTotalSpan.text('');
				return;
			}

			// Update the button text with the return fee amount by replacing inner text of the span#qliro_return_fee_total with the refund fee amount.
			//$qliroReturnFeeTotalSpan.text( qoc_admin_params.return_fee_text + ' ' + accounting.formatMoney(refundFeeAmount, {
			$qliroReturnFeeTotalSpan.text(' (' + qoc_admin_params.with_return_fee_text + ' ' + qoc.format_number(refundFeeAmount) + ')' );
		},

		format_number: function (number) {
			return accounting.formatMoney(
				number,
				{
					symbol: woocommerce_admin_meta_boxes.currency_format_symbol,
					decimal: woocommerce_admin_meta_boxes.currency_format_decimal_sep,
					thousand: woocommerce_admin_meta_boxes.currency_format_thousand_sep,
					precision: woocommerce_admin_meta_boxes.currency_format_num_decimals,
					format: woocommerce_admin_meta_boxes.currency_format
				}
			);
		},

		unformat_number: function (number) {
			return accounting.unformat(
				number,
				woocommerce_admin.mon_decimal_point
			);
		},

		on_refund_submit: function (e) {
			// Get the refund amount from the input field.
			const $refundAmount = $('#refund_amount');
			const $qliroReturnFeeAmountField = $('#qliro_return_fee input.refund_line_total.wc_input_price');
			const $qliroReturnFeeTaxAmountField = $('#qliro_return_fee input.refund_line_tax.wc_input_price');

			const diff = qoc.unformat_number($refundAmount.val()) - (qoc.unformat_number($qliroReturnFeeAmountField.val()) + qoc.unformat_number($qliroReturnFeeTaxAmountField.val()));

			if (diff < 0) {
				// Show an alert box with the message "Refund amount is less than the return fee amount."
				window.alert(qoc_admin_params.refund_amount_less_than_return_fee_text);

				// Pause the default action of the button.
				e.preventDefault();
				e.stopPropagation();
				return;
			}
		},

		/**
		 * Function that initiates the events for this file.
		 */
		init: function () {
			$('#woocommerce-order-items')
				.on('click', 'button.partial-capture', this.capture)
				.on('click', '.capture-actions .cancel-action', this.cancel_capture)
				.on('click', '.do-capture', this.create_capture)
				.on('click', '.button.refund-items', this.refund_items)
				.on('click', '.refund-actions .cancel-action', this.cancel_refund);

			$('button.do-api-refund').on('click', this.on_refund_submit);

			$(document)
				.ready(this.move_element)
				.ready(this.add_checkboxes)
				.ready(this.add_input_fields)
				.ready(this.modify_refund_button_text)
				.on('change', '#refund_amount', this.update_qliro_refund_amount)
				.on('change', '#qliro_return_fee input.refund_line_total.wc_input_price', this.update_qliro_refund_amount)

			window.addEventListener("hashchange", qoc.showListOfDeliveries);

			const discount = $('#qliro-discount-form');
			if (discount.length > 0) {
				const fees = JSON.parse($('#qliro-discount-form').attr('data-fees'));
				const totalAmount = parseFloat(discount.attr('data-total-amount'));
				const availableAmount = parseFloat(discount.attr('data-available-amount'));
				const discountIdEl = $('#qliro-discount-id');
				const discountAmountEl = $('#qliro-discount-amount');
				const discountPercentageEl = $('#qliro-discount-percentage');
				const discountTaxClassEl = $('#qliro-discount-tax-class');
				const newDiscountPercentageEl = $('#qliro-new-discount-percentage');
				const newTotalAmountEl = $('#qliro-new-total-amount');
				const modal = $('.qliro-discount-form-modal');
				const submitButton = $('#qliro-discount-form-submit');
				const closeButtons = $('.qliro-discount-form-modal .modal-close')

				const updateURL = () => {
					const actionURL = submitButton.attr('formaction')
					const url = new URL(actionURL, location.origin);

					const discountAmount = parseFloat(discountAmountEl.val());
					if (!isNaN(discountAmount)) {
						url.searchParams.set('discount_amount', discountAmount.toFixed(2));
					}

					url.searchParams.set('discount_tax_class', discountTaxClassEl.val());
					url.searchParams.set('discount_id', discountIdEl.val());

					submitButton.attr('formaction', url.toString());
				}

				const updateView = (amount, percentage) => {
					const discountedTotalAmount = Math.max(0, availableAmount - amount);
					newTotalAmountEl.val(qoc.format_number(discountedTotalAmount));
					newDiscountPercentageEl.val(`${-1 * percentage.toFixed(2)}%`);

					const isFullyDiscounted = amount > availableAmount;
					const hasDiscountAmount = amount > 0
					const hasDiscountId = discountIdEl.val().length > 0;
					const isDuplicateId = fees.includes(discountIdEl.val());
					submitButton.attr('disabled', !hasDiscountId || !hasDiscountAmount || isDuplicateId || isFullyDiscounted);

					$('#qliro-discount-error').toggleClass('hidden', !isFullyDiscounted);
					$('#qliro-discount-notice').toggleClass('hidden', isFullyDiscounted);
					
					updateURL()
				}

				discountIdEl.on('input', function () {
					const alreadyExists = fees.includes($(this).val());
					$('#qliro-discount-id-error').toggleClass('hidden', !alreadyExists);

					const discountAmount = parseFloat(discountAmountEl.val());
					const discountPercentage = parseFloat(discountPercentageEl.val());
					if (!isNaN(discountAmount) && !isNaN(discountPercentage)) {
						updateView(discountAmount, discountPercentage);
					}

					updateURL()
				})

				discountAmountEl.on('input', function () {
					let discountAmount = parseFloat($(this).val());
					discountAmount = isNaN(discountAmount) ? 0 : discountAmount;

					// Do not allow exceeding the total amount.
					if (discountAmount > availableAmount) {
						discountAmount = availableAmount;
						$(this).val(discountAmount.toFixed(2));

					// Do not allow negative values.
					} else if (discountAmount < 0) {
						discountAmount = 0;
						$(this).val(discountAmount.toFixed(2));
					}

					const percentage = availableAmount > 0 ? ((discountAmount / availableAmount) * 100) : 0;
					discountPercentageEl.val(percentage.toFixed(2));

					updateView(discountAmount, percentage);
				})

				discountPercentageEl.on('input', function () {
					let discountPercentage = parseFloat($(this).val());
					discountPercentage = isNaN(discountPercentage) ? 0 : discountPercentage;

					// Do not allow exceeding 100%.
					if (discountPercentage > 100) {
						discountPercentage = 100;
					// Do not allow negative values.
					} else if (discountPercentage <= 0) {
						discountPercentage = 0;
					} 

					let discountAmount = ((availableAmount * discountPercentage) / 100);
					discountAmountEl.val(discountAmount.toFixed(2));

					if (discountAmount > availableAmount) { 
						discountAmount = availableAmount;
						discountPercentage = 100;
					} else if (0 === discountAmount) {
						discountPercentage = 0;
					}

					$(this).val(discountPercentage);

					updateView(discountAmount, discountPercentage);
				})

				discountTaxClassEl.on('change', updateURL)

				$('#qliro_add_order_discount').on('click', function (e) {
					e.preventDefault();
					modal.show();
				})

				const toggleModal = (e) => {
					e.preventDefault();
					modal.hide();
				}

				closeButtons.on('click', toggleModal)

			}
		}
	}
	qoc.init();
});
