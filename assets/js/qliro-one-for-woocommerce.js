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
		},
		/**
		 * Triggers on document ready.
		 */
		documentReady: function() {
			// todo
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
	};

	qliroOneForWooCommerce.init();

});