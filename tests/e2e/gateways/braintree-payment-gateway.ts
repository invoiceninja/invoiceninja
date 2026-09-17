import { expect, type Page } from '@playwright/test';
import { dismissCookieConsent } from '../client-portal-helpers';
import { BasePaymentGateway } from './base-payment-gateway';
import { submitRequiredClientInfoIfPresent } from './payment-flow-helpers';
import { GatewayType } from './types';

export class BraintreePaymentGateway extends BasePaymentGateway {
    readonly slug = 'braintree';
    readonly displayName = 'Braintree';
    readonly gatewayKey = 'f7ec488676d310683fb51802d076d713';
    readonly envVar = 'BRAINTREE_KEYS';
    readonly gatewayTypeId = GatewayType.CREDIT_CARD;
    readonly supportsFullPayment = true;

    async assertCheckoutReady(page: Page): Promise<void> {
        await expect(page.locator('meta[name="client-token"]')).toHaveAttribute(
            'content',
            /.+/,
        );
        await expect(page.locator('#dropin-container')).toBeVisible({
            timeout: 30_000,
        });
        await expect(page.locator('#pay-now')).toBeVisible();
    }

    /**
     * The drop-in UI puts each card field in its own hosted iframe.
     *
     * @see resources/views/portal/ninja2020/gateways/braintree/credit_card/pay_livewire.blade.php
     */
    async completePayment(page: Page): Promise<void> {
        /** The consent banner overlays the pay button and swallows the click. */
        await dismissCookieConsent(page);
        await submitRequiredClientInfoIfPresent(page);

        /** Drop-in names each hosted field iframe braintree-hosted-field-<field>. */
        const field = (name: string) =>
            page
                .frameLocator(`iframe[name="braintree-hosted-field-${name}"]`)
                .locator('input')
                .first();

        const cardOption = page.locator('[data-braintree-id="card"]');

        if (await cardOption.isVisible().catch(() => false)) {
            await cardOption.click({ force: true });
        }

        await field('number').fill('4111111111111111');
        await field('expirationDate').fill('12/29');

        /** Drop-in only renders a CVV field when the merchant account asks for one. */
        const cvv = page.locator('iframe[name="braintree-hosted-field-cvv"]');

        if (await cvv.isVisible().catch(() => false)) {
            await field('cvv').fill('123');
        }

        /**
         * The portal overlays the pay button, so a coordinate click lands on the overlay
         * instead of the listener drop-in attached to it.
         */
        await page
            .locator('#pay-now')
            .evaluate((button) => (button as HTMLButtonElement).click());
    }
}
