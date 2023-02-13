import { test, expect, APIRequestContext } from '@playwright/test';
import { QliroIframe } from '../locators/QliroIframe';
import { GetWcApiClient, WcPages } from '@krokedil/wc-test-helper';

const {
    BASE_URL,
    CONSUMER_KEY,
    CONSUMER_SECRET,
} = process.env;

test.describe('Guest Checkout @shortcode', () => {
    test.use({ storageState: process.env.GUESTSTATE });

    let wcApiClient: APIRequestContext;

    const paymentMethodId = 'qliro_one';

    let orderId: string;

    test.beforeAll(async () => {
        wcApiClient = await GetWcApiClient(BASE_URL ?? 'https://krokedil.e2e.eu.ngrok.io', CONSUMER_KEY ?? 'admin', CONSUMER_SECRET ?? 'password');
    });

    test.afterEach(async () => {
        // Delete the order from WooCommerce.
        await wcApiClient.delete(`orders/${orderId}`);
    });

    test('Can buy 6x 99.99 products with 25% tax.', async ({ page }) => {
        const cartPage = new WcPages.Cart(page, wcApiClient);
        const orderRecievedPage = new WcPages.OrderReceived(page, wcApiClient);
        const checkoutPage = new WcPages.Checkout(page);
        const iframe = new QliroIframe(page);

        // Add products to the cart.
        await cartPage.addtoCart(['simple-25', 'simple-25', 'simple-25', 'simple-25', 'simple-25', 'simple-25']);

        // Go to the checkout page.
        await checkoutPage.goto();

        // Process the Qliro iFrame
        await iframe.fillAndSubmit();

        // Verify that the order was placed.
        await expect(page).toHaveURL(/order-received/);

        orderId = await orderRecievedPage.getOrderId();

        // Verify the order details.
        //await VerifyOrderRecieved(orderRecievedPage);
    });

    test('Can buy 6x 99.99 products with 25% tax with free shipping coupon.', async ({ page }) => {
        const cartPage = new WcPages.Cart(page, wcApiClient);
        const orderRecievedPage = new WcPages.OrderReceived(page, wcApiClient);
        const checkoutPage = new WcPages.Checkout(page);
        const iframe = new QliroIframe(page);

        // Add products to the cart.
        await cartPage.addtoCart(['simple-25', 'simple-25', 'simple-25', 'simple-25', 'simple-25', 'simple-25']);

        // Go to the checkout page.
        await checkoutPage.goto();

        // Process the Qliro iFrame
        await iframe.fillAndSubmit();

        // Verify that the order was placed.
        await expect(page).toHaveURL(/order-received/);

        orderId = await orderRecievedPage.getOrderId();

        // Verify the order details.
        //await VerifyOrderRecieved(orderRecievedPage);
    });

    test('Can buy products with different tax rates', async ({ page }) => {
        const cartPage = new WcPages.Cart(page, wcApiClient);
        const orderRecievedPage = new WcPages.OrderReceived(page, wcApiClient);
        const checkoutPage = new WcPages.Checkout(page);
        const iframe = new QliroIframe(page)

        // Add products to the cart.
        await cartPage.addtoCart(['simple-25', 'simple-12', 'simple-06', 'simple-00']);

        // Go to the checkout page.
        await checkoutPage.goto();

        // Process the Qliro iFrame
        await iframe.fillAndSubmit();

        // Verify that the order was placed.
        await expect(page).toHaveURL(/order-received/);

        orderId = await orderRecievedPage.getOrderId();

        // Verify the order details.
        //await VerifyOrderRecieved(orderRecievedPage);
    });

    test('Can buy products that don\'t require shipping', async ({ page }) => {
        const cartPage = new WcPages.Cart(page, wcApiClient);
        const orderRecievedPage = new WcPages.OrderReceived(page, wcApiClient);
        const checkoutPage = new WcPages.Checkout(page);
        const iframe = new QliroIframe(page);

        // Add products to the cart.
        await cartPage.addtoCart(['simple-virtual-downloadable-25', 'simple-virtual-downloadable-12', 'simple-virtual-downloadable-06', 'simple-virtual-downloadable-00']);

        // Go to the checkout page.
        await checkoutPage.goto();

        // Process the Qliro iFrame
        await iframe.fillAndSubmit();

        // Verify that the order was placed.
        await expect(page).toHaveURL(/order-received/);

        orderId = await orderRecievedPage.getOrderId();

        // Verify the order details.
        //await VerifyOrderRecieved(orderRecievedPage);
    });

    test('Can buy variable products', async ({ page }) => {
        const cartPage = new WcPages.Cart(page, wcApiClient);
        const orderRecievedPage = new WcPages.OrderReceived(page, wcApiClient);
        const checkoutPage = new WcPages.Checkout(page);
        const iframe = new QliroIframe(page)

        // Add products to the cart.
        await cartPage.addtoCart(['variable-25-blue', 'variable-12-red', 'variable-12-red', 'variable-25-black', 'variable-12-black']);

        // Go to the checkout page.
        await checkoutPage.goto();

        // Process the Qliro iFrame
        await iframe.fillAndSubmit();

        // Verify that the order was placed.
        await expect(page).toHaveURL(/order-received/);

        orderId = await orderRecievedPage.getOrderId();

        // Verify the order details.
        //await VerifyOrderRecieved(orderRecievedPage);
    });

    test('Can place order with separate shipping address', async ({ page }) => {
        const cartPage = new WcPages.Cart(page, wcApiClient);
        const orderRecievedPage = new WcPages.OrderReceived(page, wcApiClient);
        const checkoutPage = new WcPages.Checkout(page);
        const iframe = new QliroIframe(page)

        // Add products to the cart.
        await cartPage.addtoCart(['simple-25']);

        // Go to the checkout page.
        await checkoutPage.goto();

        // Process the Qliro iFrame
        await iframe.fillAndSubmit();

        // Verify that the order was placed.
        await expect(page).toHaveURL(/order-received/);

        orderId = await orderRecievedPage.getOrderId();

        // Verify the order details.
        //await VerifyOrderRecieved(orderRecievedPage);
    });

    test('Can place order with Company name in both billing and shipping address', async ({ page }) => {
        const cartPage = new WcPages.Cart(page, wcApiClient);
        const orderRecievedPage = new WcPages.OrderReceived(page, wcApiClient);
        const checkoutPage = new WcPages.Checkout(page);
        const iframe = new QliroIframe(page)

        // Add products to the cart.
        await cartPage.addtoCart(['simple-25']);

        // Go to the checkout page.
        await checkoutPage.goto();

        // Process the Qliro iFrame
        await iframe.fillAndSubmit();

        // Verify that the order was placed.
        await expect(page).toHaveURL(/order-received/);

        orderId = await orderRecievedPage.getOrderId();

        // Verify the order details.
        //await VerifyOrderRecieved(orderRecievedPage);
    });

    test('Can change shipping method', async ({ page }) => {
        const cartPage = new WcPages.Cart(page, wcApiClient);
        const orderRecievedPage = new WcPages.OrderReceived(page, wcApiClient);
        const checkoutPage = new WcPages.Checkout(page);
        const iframe = new QliroIframe(page)

        // Add products to the cart.
        await cartPage.addtoCart(['simple-25']);

        // Go to the checkout page.
        await checkoutPage.goto();

        // Change the shipping method.
        await checkoutPage.selectShippingMethod('Flat rate');

        // Process the Qliro iFrame
        await iframe.fillAndSubmit();

        // Verify that the order was placed.
        await expect(page).toHaveURL(/order-received/);

        orderId = await orderRecievedPage.getOrderId();

        // Verify the order details.
        //await VerifyOrderRecieved(orderRecievedPage);
    });
});
