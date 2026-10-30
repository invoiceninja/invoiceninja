import { expect, type Page } from '@playwright/test';
import { dismissCookieConsent } from '../client-portal-helpers';
import { BasePaymentGateway } from './base-payment-gateway';
import { submitRequiredClientInfoIfPresent } from './payment-flow-helpers';
import { GatewayType } from './types';

export class BraintreePaymentGateway extends BasePaymentGateway {
    readonly slug = 'braintree';
    readonly displayName = 'Braintree';
    readonly gatewayKey = 'f7ec488676d310683fb51802d076d713';
    readonly envVar: string;
    readonly gatewayTypeId = GatewayType.CREDIT_CARD;
    readonly supportsFullPayment = true;

    constructor(env_var = 'BRAINTREE_KEYS') {
        super();
        this.envVar = env_var;
    }

    async assertCheckoutReady(page: Page): Promise<void> {
        await expect(page.locator('#braintree-credit-card-payment meta[name="client-token"]')).toHaveAttribute(
            'content',
            /.+/,
        );
        await expect(page.locator('#braintree-hosted-fields')).toBeVisible({
            timeout: 30_000,
        });
        await expect(page.locator('#pay-now')).toBeVisible();
    }

    /**
     * Hosted Fields puts each card field in its own hosted iframe.
     *
     * @see resources/views/portal/ninja2020/gateways/braintree/credit_card/pay_livewire.blade.php
     */
    async completePayment(page: Page): Promise<void> {
        /** The consent banner overlays the pay button and swallows the click. */
        await dismissCookieConsent(page);
        await submitRequiredClientInfoIfPresent(page);

        /** Braintree names each hosted field iframe braintree-hosted-field-<field>. */
        const field = (name: string) =>
            page
                .frameLocator(`iframe[name="braintree-hosted-field-${name}"]`)
                .locator('input')
                .first();

        await field('number').fill('4111111111111111');
        await field('expirationDate').fill('12/29');

        /** Hosted Fields only renders a CVV field when the merchant account asks for one. */
        const cvv = page.locator('iframe[name="braintree-hosted-field-cvv"]');

        if (await cvv.isVisible().catch(() => false)) {
            await field('cvv').fill('123');
        }

        /**
         * The portal overlays the pay button, so a coordinate click lands on the overlay
         * instead of the payment listener attached to it.
         */
        await page
            .locator('#pay-now')
            .evaluate((button) => (button as HTMLButtonElement).click());
    }
}
