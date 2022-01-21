import puppeteer from "puppeteer";
import setup from "../api/setup";
import urls from "../helpers/urls";
import utils from "../helpers/utils";
import tests from "../config/tests.json"
import data from "../config/data.json";
import API from "../api/API";


const options = {
    "headless": false,
    "defaultViewport": null,
    "args": [
        '--proxy-bypass-list=*',
        '--ignore-certificate-errors-spki-list',
        '--ignore-certificate-errors',
        "--disable-infobars",
        "--disable-web-security",
        "--disable-features=IsolateOrigins,site-per-process",
    ]
};

// Main selectors
let page;
let browser;
let context;
let timeOutTime = 2500;
let json = data;

describe("Qliro One E2E tests", () => {
    beforeAll(async () => {
        try {
            json = await setup.setupStore(json);
            await utils.setOptions();
        } catch (e) {
            console.log(e);
        }
    }, 250000);

    beforeEach(async () => {
        browser = await puppeteer.launch(options);
        context = await browser.createIncognitoBrowserContext();
        page = await context.newPage();
    });

    afterEach(async () => {
        if (!page.isClosed()) {
            browser.close();
        }
        await API.clearWCSession();
    });

    test.each(tests)(
        "$name",
        async (args) => {
            try {
                // --------------- GUEST/LOGGED IN --------------- //
                if(args.loggedIn) {
                    await page.goto(urls.MY_ACCOUNT);
                    await utils.login(page, "admin", "password");
                }

                // --------------- SETTINGS --------------- //
                await utils.setPricesIncludesTax({value: args.inclusiveTax});
                // await utils.setIframeShipping(args.shippingInIframe);

                // --------------- ADD PRODUCTS TO CART --------------- //
                await utils.addMultipleProductsToCart(page, args.products, json);
                await page.waitForTimeout(1 * timeOutTime);

                // --------------- GO TO CHECKOUT --------------- //
                await page.goto(urls.CHECKOUT);
                await page.waitForTimeout(timeOutTime);
                await utils.selectQliroOne(page);
                await page.waitForTimeout(4 * timeOutTime);

                const elementHandle = await page.$('div#qliro-one-iframe-wrapper iframe');
                const frame = await elementHandle.contentFrame();
                // process frame.


                await frame.type("[name='emailPhone']", "test@krokedil.com");
                await page.waitForTimeout(4 * timeOutTime);

                await frame.click('div.authenticationForm_buttonContainer__2xlUu button');
                await page.waitForTimeout(4 * timeOutTime);

                await frame.type("[name='personalNumberLast4Digits']", '5307');

                await page.waitForTimeout(4 * timeOutTime);
                await frame.click('div.authenticationForm_buttonContainer__2xlUu button');
                await page.waitForTimeout(4 * timeOutTime);



                //customerForm_link__hHzoU bg-background
                let editLinkExists = false;
                if (await frame.$('a.customerForm_link__hHzoU')) {
                    editLinkExists = true;
                }
                // fill the form.
                if (editLinkExists === false) {

                    await frame.type("[name='shippingFirstName']", 'RUBEN');
                    await frame.type("[name='shippingLastName']", 'HENRIKSSON');
                    await frame.type("[name='shippingStreet']", 'Getstigen 4');

                    const postalCodeInput = await frame.$('[name="shippingPostalCode"]');
                    await postalCodeInput.click({clickCount:3});
                    await frame.type("[name='shippingPostalCode']", '80266');

                    await frame.type("[name='shippingCity']", 'Gävle');
                    await page.waitForTimeout(4 * timeOutTime);
                    await frame.click('button.buttonQliroOne_button__1VIMx');
                }

                await page.waitForTimeout(4 * timeOutTime);


                const tabsSelector = '.radio_indicator__2iMrA';
                const tabs = await frame.$$(tabsSelector);

                // TODO refactor to functions.

                // --------------- BEFORE DELIVERY--------------- //

                /*

                await tabs[0].click();
                await page.waitForTimeout(4 * timeOutTime);

                const elementHandleCardNUmber = await page.$('div#qliro-one-iframe-wrapper iframe');
                await page.waitForTimeout(2 * timeOutTime);
                const frameCreditCard = await elementHandleCardNUmber.contentFrame();

                await frameCreditCard.click('.paymentOption_title__2J-QI.paymentOption_noDescription__31XBF');

                console.log(frameCreditCard);

                await page.waitForTimeout(2 * timeOutTime);
                await frameCreditCard.type('[name="card.number"]', '4111 1111 1111 1111');
                await frameCreditCard.type('.wpwl-control-expiry', '0125');
                await frameCreditCard.type('[name="card.cvv"]', '123');

                await page.waitForTimeout(timeOutTime);

                await frameCreditCard.click('button.buttonQliroOne_button__1VIMx');

                await page.waitForTimeout(4 * timeOutTime);

                const elementHandlePayOn = await frameCreditCard.$('[name="payonTarget"]');
                await page.waitForTimeout(timeOutTime);
                const framePayOn = await elementHandlePayOn.contentFrame();
                await page.waitForTimeout(timeOutTime);
                const value = await framePayOn.$eval("#simulatorDiv", (e) => e.textContent);
                expect(value).toBe("SIMULATION");

                await framePayOn.$eval("input[type='submit']", (e) => {
                    e.click();
                });
                 */

                // --------------- END BEFORE DELIVERY --------------- //


                // --------------- AFTER DELIVERY --------------- //

                await tabs[1].click();
                await page.waitForTimeout(4 * timeOutTime);
                await frame.click('.paymentOption_title__2J-QI.paymentOption_noDescription__31XBF');
                await page.waitForTimeout(timeOutTime);
                await frame.click('button.buttonQliroOne_button__1VIMx');

                // --------------- END AFTER DELIVERY --------------- //



                // --------------- THANK YOU SNIPPET --------------- //
                await page.waitForTimeout(5 * timeOutTime);
                const value = await page.$eval(".entry-title", (e) => e.textContent);
                expect(value).toBe("Order received");

                await page.waitForTimeout(5 * timeOutTime);
                const elementHandleThankYou = await page.$('iframe');
                const frameThankYou = await elementHandleThankYou.contentFrame();

                await page.waitForTimeout(2 * timeOutTime);
                const qliroTotal = await frameThankYou.$eval('.totalPrice_totalPrice__UZMVa', (e) => e.textContent);
                const qliroTotalAsNumber = parseFloat(qliroTotal.replace(' SEK', '').replace(',', ''));
                expect(qliroTotalAsNumber).toBe(args.expectedTotal);

            } catch(e) {
                console.log("Error placing order", e)
            }

            // --------------- POST PURCHASE CHECKS --------------- //

        }, 190000);
});