/**
 * Front end handling for the Qliro card registration form on the add card page.
 *
 * Qliro's card form exposes a single event, onCreditCardCreated, and no failure event. So this
 * only improves the successful path: it tells the customer their card was accepted straight away,
 * rather than leaving them on an idle form while Qliro redirects.
 *
 * The card itself is never saved from anything reported here. Token registration happens server
 * side, from the authenticated push callback, because a browser cannot be trusted to say which
 * card belongs to a subscription.
 */
window.qccfReady = function () {
	// Qliro invokes this during their own setup, so anything thrown here risks breaking their form.
	if (typeof qccf === 'undefined' || typeof qccf.onCreditCardCreated !== 'function') {
		return;
	}

	qccf.onCreditCardCreated(function () {
		var container = document.getElementById('qliro-recurring');

		// Qliro gives no guarantee the event fires once, and redirects right after it.
		if (!container || document.getElementById('qliro-add-card-accepted')) {
			return;
		}

		var notice = document.createElement('div');
		notice.id = 'qliro-add-card-accepted';
		notice.className = 'woocommerce-message';
		notice.setAttribute('role', 'status');
		notice.textContent = qliroAddCardParams.accepted_message;

		container.before(notice);
	});
};
