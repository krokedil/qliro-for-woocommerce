/**
 * @var qliroOneParams
 */
jQuery( function( $ ) {
	if ( typeof qliroOneParams === 'undefined' || qliroOneParams.isEnabled !== 'yes' ) {
		return false;
	}
	var qliroOneForWooCommerce = {
		bodyEl: $('body'),
		checkoutFormSelector: 'form.checkout',
		preventPaymentMethodChange: false,
		selectAnotherSelector: '#qliro-one-select-other',
		paymentMethodEl: $('input[name="payment_method"]'),

		init: function () {
			$( document ).ready( qliroOneForWooCommerce.documentReady );
			qliroOneForWooCommerce.bodyEl.on( 'change', 'input[name="payment_method"]', qliroOneForWooCommerce.maybeChangeToQliroOne );
			qliroOneForWooCommerce.bodyEl.on( 'click', qliroOneForWooCommerce.selectAnotherSelector, qliroOneForWooCommerce.changeFromQliroOne );
			qliroOneForWooCommerce.bodyEl.on( 'updated_checkout', qliroOneForWooCommerce.maybeDisplayShippingPrice );
			qliroOneForWooCommerce.renderIframe();
		},
		/**
		 * Triggers on document ready.
		 */
		documentReady: function() {
			if ( 0 < $('input[name="payment_method"]').length ) {
				qliroOneForWooCommerce.paymentMethod = $('input[name="payment_method"]').filter( ':checked' ).val();
			} else {
				qliroOneForWooCommerce.paymentMethod = 'qliro_one';
			}

			if( ! qliroOneParams.payForOrder && qliroOneForWooCommerce.paymentMethod === 'qliro_one' ) {
				qliroOneForWooCommerce.moveExtraCheckoutFields();
			}
			qliroOneForWooCommerce.bodyEl.on('update_checkout', qliroOneForWooCommerce.updateCheckout);
			qliroOneForWooCommerce.bodyEl.on('updated_checkout', qliroOneForWooCommerce.updatedCheckout);
		},
		renderIframe: function() {
			window.q1Ready = function(q1) {
				q1.onCustomerInfoChanged(qliroOneForWooCommerce.updateAddress);
				q1.onValidateOrder(qliroOneForWooCommerce.getQliroOneOrder);
				q1.onShippingMethodChanged(qliroOneForWooCommerce.shippingMethodChanged);
			}
			$('#qliro-one-iframe').append( qliroOneParams.iframeSnippet );
		},
		updateCheckout: function() {
			if (window.q1 !== undefined) {
				window.q1.lock();
			}
		},
		updatedCheckout: function() {
			if (window.q1 !== undefined) {
				window.q1.onOrderUpdated(function (order) {
					window.q1.unlock();
				});
			}
		},
		shippingMethodChanged: function (shipping) {
			$('#qoc_shipping_data').val(JSON.stringify(shipping));
			$( 'body' ).trigger( 'qoc_shipping_option_changed', [ shipping ]);
			$( 'body' ).trigger( 'update_checkout' );
		},
		/**
		 * When the customer changes from Qliro One to other payment methods.
		 * @param {Event} e
		 */
		changeFromQliroOne: function( e ) {
			e.preventDefault();
			$( qliroOneForWooCommerce.checkoutFormSelector ).block({
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
				success: function( data ) {},
				error: function( data ) {},
				complete: function( data ) {
					window.location.href = data.responseJSON.data.redirect;
				}
			});
		},
		/**
		 * When the customer changes to Qliro One from other payment methods.
		 */
		maybeChangeToQliroOne: function() {
			if ( ! qliroOneForWooCommerce.preventPaymentMethodChange ) {
				if ( 'qliro_one' === $( this ).val() ) {
					$( '.woocommerce-info' ).remove();
					$( qliroOneForWooCommerce.checkoutFormSelector ).block({
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
						success: function( data ) {},
						error: function( data ) {},
						complete: function( data ) {
							window.location.href = data.responseJSON.data.redirect;
						}
					});
				}
			}
		},
		/**
		 * Display Shipping Price in order review if Display shipping methods in iframe settings is active.
		 */
		maybeDisplayShippingPrice: function() {
		// Check if we already have set the price. If we have, return.
		if( $('.qoc-shipping').length ) {
			return;
		}
		if ( 'qliro_one' === qliroOneForWooCommerce.paymentMethod && 'yes' === qliroOneParams.shipping_in_iframe ) {
			if ( $( '#shipping_method input[type=\'radio\']' ).length ) {
				// Multiple shipping options available.
				$( '#shipping_method input[type=\'radio\']:checked' ).each( function() {
					var idVal = $( this ).attr( 'id' );
					var shippingPrice = $( 'label[for=\'' + idVal + '\']' ).text();
					$( '.woocommerce-shipping-totals td' ).html( shippingPrice );
					$( '.woocommerce-shipping-totals td' ).addClass( 'qoc-shipping' );
				});
			} else {
				// Only one shipping option available.
				var idVal = $( '#shipping_method input[name=\'shipping_method[0]\']' ).attr( 'id' );
				var shippingPrice = $( 'label[for=\'' + idVal + '\']' ).text();
				$( '.woocommerce-shipping-totals td' ).html( shippingPrice );
				$( '.woocommerce-shipping-totals td' ).addClass( 'qoc-shipping' );
			}
		}
	},
		/*
		 * Check if Qliro One is the selected gateway.
		 */
		checkIfQliroOneSelected: function() {
			if (qliroOneForWooCommerce.paymentMethodEl.length > 0) {
				qliroOneForWooCommerce.paymentMethod = qliroOneForWooCommerce.paymentMethodEl.filter(':checked').val();
				if( 'qliro_one' === qliroOneForWooCommerce.paymentMethod ) {
					return true;
				}
			}
			return false;
		},
		/**
		 * Moves all non standard fields to the extra checkout fields.
		 */
		moveExtraCheckoutFields: function() {
			// Move order comments.
			$('.woocommerce-additional-fields').appendTo('#qliro-one-extra-checkout-fields');

			let form = $('form[name="checkout"] input, form[name="checkout"] select, textarea');
			for (var i = 0; i < form.length; i++ ) {
				let name = form[i].name;
				// Check if field is inside the order review.
				if( $( 'table.woocommerce-checkout-review-order-table' ).find( form[i] ).length ) {
					continue;
				}

				// Check if this is a standard field.
				if ( -1 === $.inArray( name, qliroOneParams.standardWooCheckoutFields ) ) {
					// This is not a standard Woo field, move to our div.
					if ( 0 < $( 'p#' + name + '_field' ).length ) {
						$( 'p#' + name + '_field' ).appendTo( '#qliro-one-extra-checkout-fields' );
					} else {
						$( 'input[name="' + name + '"]' ).closest( 'p' ).appendTo( '#qliro-one-extra-checkout-fields' );
					}
				}
			}
		},
		updateAddress: function (customerInfo) {
			var email = (('email' in customerInfo) ? customerInfo.email : null);
			var phone = (('mobileNumber' in customerInfo) ? customerInfo.mobileNumber : null);

			var firstName, lastName, street, postalCode, city;
			if (customerInfo.address) {
				firstName = (('firstName' in customerInfo.address) ? customerInfo.address.firstName : null);
				lastName = (('lastName' in customerInfo.address) ? customerInfo.address.lastName : null);
				street = (('street' in customerInfo.address) ? street : null);
				postalCode = (('postalCode' in customerInfo.address) ? customerInfo.address.postalCode : null);
				city = (('city' in customerInfo.address) ? customerInfo.address.city : null);
			}

			// Check if shipping fields or billing fields are to be used.
			if (!$('#ship-to-different-address-checkbox').is(":checked")) {
				(email !== null && email !== undefined) ? $('#billing_email').val(email) : null;
				(phone !== null && phone !== undefined) ? $('#billing_phone').val(phone) : null;
				(firstName !== null && firstName !== undefined) ? $('#billing_first_name').val(firstName) : null;
				(lastName !== null && lastName !== undefined) ? $('#billing_last_name').val(lastName) : null;
				(street !== null && street !== undefined) ? $('#billing_address_1').val(street) : null;
				(postalCode !== null && postalCode !== undefined) ? $('#billing_postcode').val(postalCode) : null;
				(city !== null && city !== undefined) ? $('#billing_city').val(city) : null;
				$("form.checkout").trigger('update_checkout');
				$('#billing_email').change();
				$('#billing_email').blur();
			} else {
				(email !== null && email !== undefined) ? $('#shipping_email').val(email) : null;
				(phone !== null && phone !== undefined) ? $('#shipping_phone').val(phone) : null;
				(firstName !== null && firstName !== undefined) ? $('#shipping_first_name').val(firstName) : null;
				(lastName !== null && lastName !== undefined) ? $('#shipping_last_name').val(lastName) : null;
				(street !== null && street !== undefined) ? $('#shipping_address_1').val(street) : null;
				(postalCode !== null && postalCode !== undefined) ? $('#shipping_postcode').val(postalCode) : null;
				(city !== null && city !== undefined) ? $('#shipping_city').val(city) : null;
				$("form.checkout").trigger('update_checkout');
				$('#shipping_email').change();
				$('#shipping_email').blur();
			}
		},
		getQliroOneOrder: function (data, callback) {
			qliroOneForWooCommerce.logToFile( 'onValidateOrder from Qliro triggered' );
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
			console.log( addressData );

			// Billing fields.
			$('#billing_first_name').val(addressData.billingAddress.FirstName);
			$('#billing_last_name').val(addressData.billingAddress.LastName);
			$('#billing_company').val(addressData.billingAddress.CompanyName);
			$('#billing_address_1').val(addressData.billingAddress.Street);
			$('#billing_address_2').val(addressData.billingAddress.Street2);
			$('#billing_city').val(addressData.billingAddress.City);
			$('#billing_postcode').val(addressData.billingAddress.PostalCode);
			$('#billing_phone').val(addressData.customer.MobileNumber);
			$('#billing_email').val(addressData.customer.Email);

			// Shipping fields.
			$('#ship-to-different-address-checkbox').prop( 'checked', true);
			$('#shipping_first_name').val(addressData.shippingAddress.FirstName);
			$('#shipping_last_name').val(addressData.shippingAddress.LastName);
			$('#shipping_company').val(addressData.shippingAddress.CompanyName);
			$('#shipping_address_1').val(addressData.shippingAddress.Street);
			$('#shipping_address_2').val(addressData.shippingAddress.Street2);
			$('#shipping_city').val(addressData.shippingAddress.City);
			$('#shipping_postcode').val(addressData.shippingAddress.PostalCode);
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
				timeout:  4500,
				data: $('form.checkout').serialize(),
				dataType: 'json',
				success: function (data) {
					console.log(data);
					try {
						if ('success' === data.result) {
							console.log('submit order success', data);
							qliroOneForWooCommerce.logToFile( 'Successfully placed order. Sending "shouldProceed: true" to Qliro.' );
							callback({shouldProceed: true, errorMessage: ""});

							// Clear the interval.
							clearInterval(qliroOneForWooCommerce.interval);
							// Remove the timeout.
							clearTimeout( qliroOneForWooCommerce.timeout );

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
							qliroOneForWooCommerce.logToFile( 'Checkout error | ' + messages );
							qliroOneForWooCommerce.failOrder( 'submission', messages, callback );
						} else {
							qliroOneForWooCommerce.logToFile( 'Checkout error | No message' );
							qliroOneForWooCommerce.failOrder( 'submission', 'Checkout error', callback );
						}
					}
				},
				error: function (data) {
					try {
						qliroOneForWooCommerce.logToFile( 'AJAX error | ' + JSON.stringify(data) );
					} catch( e ) {
						qliroOneForWooCommerce.logToFile( 'AJAX error | Failed to parse error message.' );
					}
					qliroOneForWooCommerce.failOrder( 'ajax-error', 'Internal Server Error', callback )
				}
			});
		},
		failOrder: function( event, error_message, callback ) {

			// Clear the interval.
			clearInterval(qliroOneForWooCommerce.interval);
			// Remove the timeout.
			clearTimeout( qliroOneForWooCommerce.timeout );

			callback({shouldProceed: false, errorMessage: error_message});

			// Re-enable the form.
			$( 'body' ).trigger( 'updated_checkout' );
			var className = 'form.checkout';
			$( qliroOneForWooCommerce.checkoutFormSelector ).removeClass( 'processing' );
			$( qliroOneForWooCommerce.checkoutFormSelector ).unblock();
			$( '.woocommerce-checkout-review-order-table' ).unblock();
		},
		/**
		 * Logs the message to the klarna checkout log in WooCommerce.
		 * @param {string} message
		 */
		logToFile: function( message ) {
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
