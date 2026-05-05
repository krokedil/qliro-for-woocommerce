/**
 * @var qliroMetaboxParams
 */
jQuery(function ($) {
  	const qliroMetabox = {
		init: function () {
			$(document).on("click", ".qliro-toggle-order-sync", this.toggleOrderSync);
		},

		/**
		 * Show an admin notice.
		 *
		 * @param {string} message The message to display.
		 * @param {'error'|'success'|'warning'|'info'} type The notice type.
		 */
		showNotice: function (message, type) {
			type = type || 'error';
			$('.qliro-admin-notice').remove();
			const i18n = qliroMetaboxParams.i18n || {};
			const $notice = $(
				'<div class="notice notice-' + type + ' is-dismissible qliro-admin-notice">' +
				'<p>' + $('<span>').text(message).html() + '</p>' +
				'<button type="button" class="notice-dismiss"><span class="screen-reader-text">' + (i18n.dismissNotice || 'Dismiss') + '</span></button>' +
				'</div>'
			);
			$('.wp-header-end').after($notice);
			$notice.on('click', '.notice-dismiss', function () {
				$notice.remove();
			});
			$('html, body').animate({
				scrollTop: $('.qliro-admin-notice').offset().top - 100
			}, 500);
		},

		toggleOrderSync: async function (e) {
			e.preventDefault();
			const $this = $(this);
			const $metabox = $("#qliro-one");
			const enabled = $this.attr("data-qliro-order-sync") === "yes" ? "no" : "yes";
			const i18n = qliroMetaboxParams.i18n || {};
			const errorMessage = i18n.orderSyncFailed || 'Failed to toggle order management. Please try again.';

			// Block the page to prevent changing the order during the request.
			$metabox.block({
				message: null,
				overlayCSS: {
					background: "#fff",
					opacity: 0.6,
				},
			});

			// Make the AJAX request to toggle the order sync for the order.
			let result;

			try {
				result = await qliroMetabox.ajaxSetOrderSync(enabled);
			} catch (error) {
				$metabox.unblock();
				qliroMetabox.showNotice(errorMessage, 'error');
				return;
			}
			if (result.success) {
				qliroMetabox.toggleButton($this, enabled);
				// Reload the page to ensure the metadata has been added to the form.
				location.reload();
			} else {
				$metabox.unblock();
				qliroMetabox.showNotice(errorMessage, 'error');
			}
		},

		toggleButton: function ($button, enabled) {
			$button.attr("data-qliro-order-sync", enabled)
				.toggleClass("woocommerce-input-toggle--enabled")
				.toggleClass("woocommerce-input-toggle--disabled");
		},

		ajaxSetOrderSync: async function (enabled) {
			const orderId = qliroMetaboxParams.orderId;
			const { url, action, nonce } = qliroMetaboxParams.ajax.setOrderSync;

			const data = {
				nonce: nonce,
				action: action,
				order_id: orderId,
				enabled: enabled,
			};

			return $.ajax({
				type: "POST",
				url: url,
				data: data,
			});
		},
  	};

  	qliroMetabox.init();
});
