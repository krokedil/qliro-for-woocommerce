import { expect, FrameLocator, Locator, Page } from '@playwright/test';

export class QliroIframe {
    readonly page: Page;

    readonly iframe: FrameLocator;

    readonly continueButton: Locator;
    readonly placeOrderButton: Locator;

    readonly emailInput: Locator;
    readonly ssnLast4Input: Locator;
    readonly ssnFullInput: Locator;

    readonly beforeDeliveryRadio: Locator;
    readonly afterDeliveryRadio: Locator;

    readonly paymentOptions: Record<string, Locator>;

    constructor(page: Page) {
        this.page = page;

        this.iframe = page.frameLocator('#qliro-root iframe');

        this.continueButton = this.iframe.locator('button[type="submit"]', { hasText: 'Continue' });
        this.placeOrderButton = this.iframe.locator('button[type="submit"]', { hasText: 'Complete purchase' });

        this.emailInput = this.iframe.locator('input[name="emailPhone"]');
        this.ssnLast4Input = this.iframe.locator('input[name="personalNumberLast4Characters"]');
        this.ssnFullInput = this.iframe.locator('input[name="personalNumber"]');

        this.beforeDeliveryRadio = this.iframe.locator('text=Before delivery');
        this.afterDeliveryRadio = this.iframe.locator('text=After delivery');

        this.paymentOptions = {
            invoice: this.iframe.locator('text=Within 14 days'),
            payLater: this.iframe.locator('text=Buy now, pay in'),
            installment: this.iframe.locator('text=Bit by bit'),
            card: this.iframe.locator('text=Pay by card'),
            bank: this.iframe.locator('text=Directly from your bank'),
        }
    }

    async fillEmail(email: string = 'test@krokedil.se') {
        await this.emailInput.fill(email);
    }

    async fillSsn(ssn: string = '790625-5307') {
        if (this.ssnLast4Input.isVisible()) {
            await this.ssnLast4Input.fill(ssn.split('-')[1]);
        } else {
            await this.ssnFullInput.fill(ssn);
        }
    }

    async selectPaymentType(paymentType: string = 'after') {
        if (paymentType === 'before') {
            await this.beforeDeliveryRadio.click();
        } else {
            await this.afterDeliveryRadio.click();
        }
    }

    async selectPaymentOption(paymentOption: string = 'invoice') {
        await this.paymentOptions[paymentOption].click();
    }

    async continue() {
        await this.continueButton.click();
    }

    async placeOrder() {
        await this.placeOrderButton.click();
    }

    async fillAndSubmit() {
        await this.fillEmail();
        await this.continue();
        await this.fillSsn();
        await this.continue();
        await this.selectPaymentType();
        await this.selectPaymentOption();
        await this.placeOrder();
    }
}
