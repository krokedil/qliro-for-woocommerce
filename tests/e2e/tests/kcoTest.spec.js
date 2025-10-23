import puppeteer from "puppeteer";
import API from "../api/API";
import setup from "../api/setup";
import urls from "../helpers/urls";
import utils from "../helpers/utils";
import iframeHandler from "../helpers/iframeHandler";
import tests from "../config/tests.json"
import data from "../config/data.json";

const options = {
    "headless": true,
    "defaultViewport": null,
    "args": [
        "--disable-infobars",
        "--disable-web-security",
        "--disable-features=IsolateOrigins,site-per-process"
    ]
};

// Main selectors
let page;
let browser;
let context;
let timeOutTime = 2500;
let json = data;

describe("Qliro E2E tests", () => {
    beforeAll(async () => {
        // try {
        //     json = await setup.setupStore(json);
        // } catch (e) {
        //     console.log(e);
        // }
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
        // await API.clearWCSession();
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
                await utils.setIframeShipping(args.shippingInIframe);

                // --------------- ADD PRODUCTS TO CART --------------- //
                await utils.addMultipleProductsToCart(page, args.products, json);
                await page.waitForTimeout(1 * timeOutTime);

                // --------------- GO TO CHECKOUT --------------- //
                await page.goto(urls.CHECKOUT);
                await page.waitForTimeout(timeOutTime);
                await utils.selectKco(page);
                await page.waitForTimeout(4 * timeOutTime);


            } catch(e) {
                console.log("Error placing order", e)
            }

            // --------------- POST PURCHASE CHECKS --------------- //

        }, 190000);
});