import API from "../api/API";
import urls from "./urls";

const timeOutTime = 2500;
const qliroOneSettings = {
	woocommerce_qliro_one_settings: {
		enabled :"yes",
		title: "Qliro One",
		description: "Payment method description.",
		testmode: "yes",
		logging: "yes",
		credentials: "",
		api_key: "",
		api_secret: "",
		test_api_key: "",
		test_api_secret: "",
		other_payment_method_button_text: "",
		qliro_one_button_ask_for_newsletter_signup: "no",
		qliro_one_button_ask_for_newsletter_signup_checked: "no",
		qliro_one_enforced_juridical_type: "Physical",
		capture_status: "wc-completed",
		capture_pending_status: "none",
		capture_ok_status: "none",
		cancel_status: "wc-cancelled",
		cancel_pending_status: "none",
		cancel_ok_status: "none",
		qliro_one_bg_color: "",
		qliro_one_primary_color: "",
		qliro_one_call_action_color: "",
		qliro_one_call_action_hover_color: "",
		qliro_one_corner_radius: "",
		qliro_one_button_corner_radius: "",
		checkout_layout: "one_column_checkout"
	},
};

const login = async (page, username, password) => {
	await page.type("#username", username);
	await page.type("#password", password);
	await page.waitForSelector("button[name=login]");
	await page.click("button[name=login]");
};

const applyCoupons = async (page, appliedCoupons) => {
	if (appliedCoupons.length > 0) {
		await appliedCoupons.forEach(async (singleCoupon) => {
			await page.click('[class="showcoupon"]');
			await page.waitForTimeout(500);
			await page.type('[name="coupon_code"]', singleCoupon);
			await page.click('[name="apply_coupon"]');
		});
	}
	await page.waitForTimeout(3 * timeOutTime);
};

const addSingleProductToCart = async (page, productId) => {
	const productSelector = productId;

	try {
		await page.goto(`${urls.ADD_TO_CART}${productSelector}`);
		await page.goto(urls.SHOP);
	} catch {
		// Proceed
	}
};

const addMultipleProductsToCart = async (page, products, data) => {
	const timer = products.length;

	await page.waitForTimeout(timer * 800);
	let ids = [];

	products.forEach((name) => {
		data.products.simple.forEach((product) => {
			if (name === product.name) {
				ids.push(product.id);
			}
		});

		data.products.variable.forEach((product) => {
			product.attribute.options.forEach((variation) => {
				if (name === variation.name) {
					ids.push(variation.id);
				}
			});
		});
	});

	await (async function addEachProduct() {
		for (let i = 0; i < ids.length + 1; i += 1) {
			await addSingleProductToCart(page, ids[i]);
		}
	})();

	await page.waitForTimeout(timer * 800);
};

const setPricesIncludesTax = async (value) => {
	await API.pricesIncludeTax(value);
};

const setOptions = async () => {
	await API.updateOptions(qliroOneSettings);
};

const selectQliroOne = async (page) => {
	if (await page.$('input[id="payment_method_qliro_one"]')) {
		await page.evaluate(
			(paymentMethod) => paymentMethod.click(),
			await page.$('input[id="payment_method_qliro_one"]')
		);
	}
};

export default {
	login,
	applyCoupons,
	addSingleProductToCart,
	addMultipleProductsToCart,
	setPricesIncludesTax,
	setOptions,
	selectQliroOne,
};
