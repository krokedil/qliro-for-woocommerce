/**
 * @var qliroOneParams
 */
jQuery(function ($) {
	if (typeof qliroOneParams === 'undefined' || qliroOneParams.isEnabled !== 'yes') {
		return false;
	}
	var qliroOneForWooCommerce = {
		bodyEl: $('body'),
		checkoutFormSelector: 'form.checkout',
		preventPaymentMethodChange: false,
		selectAnotherSelector: '#qliro-one-select-other',
		paymentMethodEl: $('input[name="payment_method"]'),

		init: function () {
			$(document).ready(qliroOneForWooCommerce.documentReady);
			qliroOneForWooCommerce.bodyEl.on('change', 'input[name="payment_method"]', qliroOneForWooCommerce.maybeChangeToQliroOne);
			qliroOneForWooCommerce.bodyEl.on('click', qliroOneForWooCommerce.selectAnotherSelector, qliroOneForWooCommerce.changeFromQliroOne);
			qliroOneForWooCommerce.bodyEl.on('updated_checkout', qliroOneForWooCommerce.maybeDisplayShippingPrice);
			qliroOneForWooCommerce.renderIframe();
		},
		/**
		 * Triggers on document ready.
		 */
		documentReady: function () {
			if (0 < $('input[name="payment_method"]').length) {
				qliroOneForWooCommerce.paymentMethod = $('input[name="payment_method"]').filter(':checked').val();
			} else {
				qliroOneForWooCommerce.paymentMethod = 'qliro_one';
			}

			if (!qliroOneParams.payForOrder && qliroOneForWooCommerce.paymentMethod === 'qliro_one') {
				qliroOneForWooCommerce.moveExtraCheckoutFields();
			}
			qliroOneForWooCommerce.bodyEl.on('update_checkout', qliroOneForWooCommerce.updateCheckout);
			qliroOneForWooCommerce.bodyEl.on('updated_checkout', qliroOneForWooCommerce.updatedCheckout);

			$('#billing_country').on('change', () => { 
				const country = $('#billing_country').val();

				// TODO: Remove console.log.
				console.log('update checkout')
				$.ajax({
					type: 'POST',
					data: {
						nonce: qliroOneParams.changeCountryNonce,
						country: country,
					},
					success: () => { 
						location.reload()
					},
					dataType: 'json',
					url: qliroOneParams.changeCountryUrl,
				})
			});
		},
		renderIframe: function () {
			window.q1Ready = function (q1) {
				q1.onCustomerInfoChanged(qliroOneForWooCommerce.updateAddress);
				q1.onValidateOrder(qliroOneForWooCommerce.placeWooOrder);
				q1.onShippingMethodChanged(qliroOneForWooCommerce.shippingMethodChanged);
			}
			$('#qliro-one-iframe').append(qliroOneParams.iframeSnippet);
		},
		updateCheckout: function () {
			if (window.q1 !== undefined) {
				window.q1.lock();
			}
		},
		updatedCheckout: function () {
			if (window.q1 !== undefined) {
				window.q1.onOrderUpdated(function (order) {
					window.q1.unlock();
				});
			}
		},
		shippingMethodChanged: function (shipping) {
			debugger
			$('#qoc_shipping_data').val(JSON.stringify(shipping));
			$('body').trigger('qoc_shipping_option_changed', [shipping]);
			$('body').trigger('update_checkout');
		},
		/**
		 * When the customer changes from Qliro One to other payment methods.
		 * @param {Event} e
		 */
		changeFromQliroOne: function (e) {
			e.preventDefault();
			$(qliroOneForWooCommerce.checkoutFormSelector).block({
				message: null,
				overlayCSS: {
					background: '#fff',
					opacity: 0.6
				}
			});

			$.ajax({
				type: 'POST',
				dataType: 'json',
				data: {
					qliro_one: false,
					nonce: qliroOneParams.change_payment_method_nonce
				},
				url: qliroOneParams.change_payment_method_url,
				success: function (data) { },
				error: function (data) { },
				complete: function (data) {
					window.location.href = data.responseJSON.data.redirect;
				}
			});
		},
		/**
		 * When the customer changes to Qliro One from other payment methods.
		 */
		maybeChangeToQliroOne: function () {
			if (!qliroOneForWooCommerce.preventPaymentMethodChange) {
				if ('qliro_one' === $(this).val()) {
					$('.woocommerce-info').remove();
					$(qliroOneForWooCommerce.checkoutFormSelector).block({
						message: null,
						overlayCSS: {
							background: '#fff',
							opacity: 0.6
						}
					});

					$.ajax({
						type: 'POST',
						data: {
							qliro_one: true,
							nonce: qliroOneParams.change_payment_method_nonce
						},
						dataType: 'json',
						url: qliroOneParams.change_payment_method_url,
						success: function (data) { },
						error: function (data) { },
						complete: function (data) {
							window.location.href = data.responseJSON.data.redirect;
						}
					});
				}
			}
		},
		/**
		 * Display Shipping Price in order review if Display shipping methods in iframe settings is active.
		 */
		maybeDisplayShippingPrice: function () {
			// Check if we already have set the price. If we have, return.
			if ($('.qoc-shipping').length) {
				return;
			}
			if ('qliro_one' === qliroOneForWooCommerce.paymentMethod && 'no' !== qliroOneParams.shipping_in_iframe) {
				if ($('#shipping_method input[type=\'radio\']').length) {
					// Multiple shipping options available.
					$('#shipping_method input[type=\'radio\']:checked').each(function () {
						var idVal = $(this).attr('id');
						var shippingPrice = $('label[for=\'' + idVal + '\']').text();
						$('.woocommerce-shipping-totals td').html(shippingPrice);
						$('.woocommerce-shipping-totals td').addClass('qoc-shipping');
					});
				} else {
					// Only one shipping option available.
					var idVal = $('#shipping_method input[name=\'shipping_method[0]\']').attr('id');
					var shippingPrice = $('label[for=\'' + idVal + '\']').text();
					$('.woocommerce-shipping-totals td').html(shippingPrice);
					$('.woocommerce-shipping-totals td').addClass('qoc-shipping');
				}
			}
		},
		/*
		 * Check if Qliro One is the selected gateway.
		 */
		checkIfQliroOneSelected: function () {
			if (qliroOneForWooCommerce.paymentMethodEl.length > 0) {
				qliroOneForWooCommerce.paymentMethod = qliroOneForWooCommerce.paymentMethodEl.filter(':checked').val();
				if ('qliro_one' === qliroOneForWooCommerce.paymentMethod) {
					return true;
				}
			}
			return false;
		},
		/**
		 * Moves all non standard fields to the extra checkout fields.
		 */
		moveExtraCheckoutFields: function () {
			// Move order comments.
			$('.woocommerce-additional-fields').appendTo('#qliro-one-extra-checkout-fields');

			let form = $('form[name="checkout"] input, form[name="checkout"] select, textarea');
			for (var i = 0; i < form.length; i++) {
				let name = form[i].name;
				// Check if field is inside the order review.
				if ($('table.woocommerce-checkout-review-order-table').find(form[i]).length) {
					continue;
				}

				// Check if this is a standard field.
				if (-1 === $.inArray(name, qliroOneParams.standardWooCheckoutFields)) {
					// This is not a standard Woo field, move to our div.
					if (0 < $('p#' + name + '_field').length) {
						$('p#' + name + '_field').appendTo('#qliro-one-extra-checkout-fields');
					} else {
						$('input[name="' + name + '"]').closest('p').appendTo('#qliro-one-extra-checkout-fields');
					}
				}
			}
		},
		updateAddress: async (customerInfo) => {
			// Since the postal code is not always included in the frontend, we need to fetch the address from the backend.
			let billingAddress, shippingAddress, customer
			try {
				const response = await $.ajax({
					type: 'POST',
					data: {
						nonce: qliroOneParams.get_order_nonce,
					},
					dataType: 'json',
					url: qliroOneParams.get_order_url,
				});

				if (!response.success) {
					throw 'Failed to GET address';
				}

				const { data } = response;
				billingAddress = data.billingAddress;
				shippingAddress = data.shippingAddress;
				customer = data.customer;

			} catch (error) {
				console.warning(error);
				window.location.reload();
			}

			const firstName = billingAddress?.FirstName ?? customerInfo?.address?.firstName;
			const lastName = billingAddress?.LastName ?? customerInfo?.address?.lastName;
			const street = billingAddress?.Street ?? customerInfo?.address?.street;
			const postalCode = billingAddress?.PostalCode ?? customerInfo?.address?.postalCode;
			const city = billingAddress?.City ?? customerInfo?.address?.city;
			const area = billingAddress?.Area ?? customerInfo?.address?.area;
			const phone = customer?.MobileNumber ?? customerInfo?.mobileNumber;
			const email = customer?.Email ?? customerInfo?.email;

			qliroOneForWooCommerce.setCustomerType(customerInfo);

			// Check if shipping fields or billing fields are to be used.
			if (!$('#ship-to-different-address-checkbox').is(":checked")) {
				(email == null) ? null : $('#billing_email').val(email);
				(phone == null) ? null : $('#billing_phone').val(phone);
				(firstName == null) ? null : $('#billing_first_name').val(firstName);
				(lastName == null) ? null : $('#billing_last_name').val(lastName);
				(street == null) ? null : $('#billing_address_1').val(street);
				(postalCode == null) ? null : $('#billing_postcode').val(postalCode);
				(city == null) ? null : $('#billing_city').val(city);
				(area == null) ? null : qliroOneForWooCommerce.setStateField('billing', area);

				$("form.checkout").trigger('update_checkout');
				$('#billing_email').change();
				$('#billing_email').blur();
			} else {
				const shippingFirstName = shippingAddress?.FirstName ?? customerInfo?.address?.firstName;
				const shippingLastName = shippingAddress?.LastName ?? customerInfo?.address?.lastName;
				const shippingStreet = shippingAddress?.Street ?? customerInfo?.address?.street;
				const shippingPostalCode = shippingAddress?.PostalCode ?? customerInfo?.address?.postalCode;
				const shippingCity = shippingAddress?.City ?? customerInfo?.address?.city;
				const shippingArea = shippingAddress?.Area ?? customerInfo?.address?.area;

				(email == null) ? null : $('#shipping_email').val(email);
				(phone == null) ? null : $('#shipping_phone').val(phone);
				(shippingFirstName == null) ? null : $('#shipping_first_name').val(shippingFirstName);
				(shippingLastName == null) ? null : $('#shipping_last_name').val(shippingLastName);
				(shippingStreet == null) ? null : $('#shipping_address_1').val(shippingStreet);
				(shippingPostalCode == null) ? null : $('#shipping_postcode').val(shippingPostalCode);
				(shippingCity == null) ? null : $('#shipping_city').val(shippingCity);
				(shippingArea == null) ? null : qliroOneForWooCommerce.setStateField("shipping", shippingArea);

				$('body').trigger('update_checkout');
				$('#shipping_email').change();
				$('#shipping_email').blur();
			}
		},

		/*
		 * Sets the customer type in the cookie.
		 */
		setCustomerType: function (customerInfo) {
			if (customerInfo.organizationNumber) {
				// Business customer.
				Cookies.set(qliroOneParams.customerTypeCookieName, 'business');
			} else {
				// Consumer customer.
				Cookies.set(qliroOneParams.customerTypeCookieName, 'consumer');
			}
		},

		getQliroOneOrder: function (data, callback) {
			qliroOneForWooCommerce.logToFile('onValidateOrder from Qliro triggered');
			$.ajax({
				type: 'POST',
				data: {
					nonce: qliroOneParams.get_order_nonce,
				},
				dataType: 'json',
				url: qliroOneParams.get_order_url,
				success: function (data) {
				},
				error: function (data) {
				},
				complete: function (data) {
					// If the response was not successful, we should fail the order.
					if (data.responseJSON.success !== true) {
						qliroOneForWooCommerce.failOrder('getQliroOneOrder', data.responseJSON.data, callback);
						return;
					}

					qliroOneForWooCommerce.setAddressData(data.responseJSON.data, callback);
					console.log('getQliroOneOrder completed');
				}
			});
		},
		/*
		 * Sets the WooCommerce form field data.
		 */
		setAddressData: function (addressData, callback) {
			if (0 < $('form.checkout #terms').length) {
				$('form.checkout #terms').prop('checked', true);
			}
			console.log(addressData);

			// Billing fields.
			$('#billing_first_name').val(addressData.billingAddress.FirstName);
			$('#billing_last_name').val(addressData.billingAddress.LastName);
			$('#billing_company').val(addressData.billingAddress.CompanyName);
			$('#billing_address_1').val(addressData.billingAddress.Street);
			$('#billing_address_2').val(addressData.billingAddress.Street2);
			$('#billing_city').val(addressData.billingAddress.City);
			$('#billing_postcode').val(addressData.billingAddress.PostalCode);
			qliroOneForWooCommerce.setStateField('billing', addressData.billingAddress.Area);
			$('#billing_phone').val(addressData.customer.MobileNumber);
			$('#billing_email').val(addressData.customer.Email);

			// Shipping fields.
			$('#ship-to-different-address-checkbox').prop('checked', true);
			$('#shipping_first_name').val(addressData.shippingAddress.FirstName);
			$('#shipping_last_name').val(addressData.shippingAddress.LastName);
			$('#shipping_company').val(addressData.shippingAddress.CompanyName);
			$('#shipping_address_1').val(addressData.shippingAddress.Street);
			$('#shipping_address_2').val(addressData.shippingAddress.Street2);
			$('#shipping_city').val(addressData.shippingAddress.City);
			$('#shipping_postcode').val(addressData.shippingAddress.PostalCode);
			qliroOneForWooCommerce.setStateField('shipping', addressData.shippingAddress.Area);
			// todo country

			// Only set country fields if we have data in them.
			if (addressData.billingAddress) {
				$('#billing_country').val(addressData.billingAddress.CountryCode);
			}
			if (addressData.shippingAddress) {
				$('#shipping_country').val(addressData.shippingAddress.CountryCode);
			}

			qliroOneForWooCommerce.submitOrder(callback);
		},

		/**
		 *
		 * @param {"billing" | "shipping"} type The type of field to set.
		 * @param {string | null | undefined} area The value to set the field to.
		 *
		 * @returns
		 */
		setStateField: function (type, area) {
			// Ignore if area is null or undefined.
			if (area === null || area === undefined) {
				return;
			}

			// Get the field.
			const $field = $(`#${type}_state`);

			// If the field does not exist, return.
			if ($field.length === 0) {
				return;
			}

			// If its a select field, we need to select the correct option where the option label is the same as the area.
			if ($field.is("select")) {
				$field.find("option").each(function () {
					if ($(this).text() === area) {
						$field.val($(this).val());
					}
				});
				return;
			}

			// If its an input field, we just need to set the value.
			$field.val(area);
		},

		/**
		 * Submit the order using the WooCommerce AJAX function.
		 */
		submitOrder: function (callback) {
			$('.woocommerce-checkout-review-order-table').block({
				message: null,
				overlayCSS: {
					background: '#fff',
					opacity: 0.6
				}
			});
			$.ajax({
				type: 'POST',
				url: qliroOneParams.submitOrder,
				data: $('form.checkout').serialize(),
				dataType: 'json',
				success: function (data) {
					console.log(data);
					try {
						if ('success' === data.result) {
							console.log('submit order success', data);
							qliroOneForWooCommerce.logToFile('Successfully placed order. Sending "shouldProceed: true" to Qliro.');
							callback({ shouldProceed: true, errorMessage: "" });

							// Remove the timeout.
							clearTimeout(qliroOneForWooCommerce.timeout);

						} else {
							console.log('submit order - missing success', data);
							throw 'Result failed';
						}
					} catch (err) {
						console.log('catch error');
						console.error(err);
						if (data.messages) {
							// Strip HTML code from messages.
							let messages = data.messages.replace(/<\/?[^>]+(>|$)/g, "");
							console.log('error ', messages);
							qliroOneForWooCommerce.logToFile('Checkout error | ' + messages);
							qliroOneForWooCommerce.failOrder('submission', messages, callback);
						} else {
							qliroOneForWooCommerce.logToFile('Checkout error | No message');
							qliroOneForWooCommerce.failOrder('submission', 'Checkout error', callback);
						}
					}
				},
				error: function (data) {
					try {
						qliroOneForWooCommerce.logToFile('AJAX error | ' + JSON.stringify(data));
					} catch (e) {
						qliroOneForWooCommerce.logToFile('AJAX error | Failed to parse error message.');
					}
					qliroOneForWooCommerce.failOrder('ajax-error', 'Internal Server Error', callback)
				}
			});
		},
		failOrder: function (event, error_message, callback) {

			// Remove the timeout.
			clearTimeout(qliroOneForWooCommerce.timeout);

			callback({ shouldProceed: false, errorMessage: error_message });

			// Re-enable the form.
			$('body').trigger('updated_checkout');
			var className = 'form.checkout';
			$(qliroOneForWooCommerce.checkoutFormSelector).removeClass('processing');
			$(qliroOneForWooCommerce.checkoutFormSelector).unblock();
			$('.woocommerce-checkout-review-order-table').unblock();
		},

		placeWooOrder: function (data, callback) {
			qliroOneForWooCommerce.timeout = setTimeout(() => {
				qliroOneForWooCommerce.logToFile('Timeout error | Timeout when placing the WooCommerce order');
				qliroOneForWooCommerce.failOrder('timeout-error', 'Timeout error', callback);
			}, 29000); // 29 seconds.

			qliroOneForWooCommerce.getQliroOneOrder(data, callback);

		},
		/**
		 * Logs the message to the Qliro checkout log in WooCommerce.
		 * @param {string} message
		 */
		logToFile: function (message) {
			$.ajax(
				{
					url: qliroOneParams.log_to_file_url,
					type: 'POST',
					dataType: 'json',
					data: {
						message: message,
						nonce: qliroOneParams.log_to_file_nonce
					}
				}
			);
		},
	};
	qliroOneForWooCommerce.init();
});
