import { expect, test, type Page } from '@playwright/test';
import { dismissCookieConsent } from '../client-portal-helpers';
import { BasePaymentGateway } from './base-payment-gateway';
import { submitRequiredClientInfoIfPresent } from './payment-flow-helpers';
import { GatewayType } from './types';

export class AuthorizePaymentGateway extends BasePaymentGateway {
    readonly slug = 'authorize';
    readonly displayName = 'Authorize.Net';
    readonly gatewayKey = '3b6621f970ab18887c4f6dca78d3f8bb';
    readonly envVar = 'AUTHORIZE_KEYS';
    readonly gatewayTypeId = GatewayType.CREDIT_CARD;
    readonly supportsFullPayment = true;

    async assertCheckoutReady(page: Page): Promise<void> {
        const publicKey = page.locator('meta[name="authorize-public-key"]');
        const content = await publicKey
            .getAttribute('content', { timeout: 10_000 })
            .catch(() => null);

        // Accept.js public client key is fetched live from Authorize.Net; stale
        // sandbox credentials leave the meta empty and the form unusable.
        if (!content) {
            test.skip(
                true,
                'Authorize.Net public client key unavailable (credentials cannot fetch Accept.js key)',
            );
        }

        await expect(publicKey).toHaveAttribute('content', /.+/);
        await expect(
            page.locator('#authorize--credit-card-container'),
        ).toBeVisible();
        await expect(page.locator('#pay-now')).toBeVisible();
    }

    /**
     * Authorize renders plain inputs (card-js) and tokenises them with Accept.js on
     * submit, so there is no iframe to reach into.
     *
     * @see resources/views/portal/ninja2020/gateways/authorize/includes/credit_card.blade.php
     */
    async completePayment(page: Page): Promise<void> {
        /** The consent banner overlays the pay button and swallows the click. */
        await dismissCookieConsent(page);
        await submitRequiredClientInfoIfPresent(page);

        await page.locator('#cardholder_name').fill('Playwright Test');

        /** simple-card drops characters when the number is typed key by key. */
        await page.locator('#number').fill('4111111111111111');
        await page.locator('#date').fill('12/29');
        await page.locator('#cvv').fill('123');

        /**
         * The portal overlays the pay button, and a coordinate click lands on the overlay
         * rather than the button - dispatch it on the element itself.
         */
        await page.locator('#pay-now').evaluate((button) => (button as HTMLButtonElement).click());
    }
}
