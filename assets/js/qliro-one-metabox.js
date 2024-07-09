/**
 * @var qliroMetaboxParams
 */
jQuery(function ($) {
  	const qliroMetabox = {
		init: function () {
			$(document).on("click", ".qliro-toggle-order-sync", this.toggleOrderSync);
		},

		toggleOrderSync: async function (e) {
			e.preventDefault();
			const $this = $(this);
			const $metabox = $("#qliro-one");
			const enabled = $this.attr("data-qliro-order-sync") === "yes" ? "no" : "yes";

			// Block the page to prevent changing the order during the request.
			$metabox.block({
				message: null,
				overlayCSS: {
					background: "#fff",
					opacity: 0.6,
				},
			});

			// Make the AJAX request to toggle the order sync for the order.
			const result = await qliroMetabox.ajaxSetOrderSync(enabled);
			if (result.success) {
				qliroMetabox.toggleButton($this, enabled);
			} else {
				alert("Failed to toggle order sync. Please try again.");
			}

			// Reload the page to ensure the metadata has been added to the form.
			location.reload();
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
