import { expect, test, type Page } from '@playwright/test';
import { BasePaymentGateway } from './base-payment-gateway';
import { GatewayType } from './types';

export class AuthorizePaymentGateway extends BasePaymentGateway {
    readonly slug = 'authorize';
    readonly displayName = 'Authorize.Net';
    readonly gatewayKey = '3b6621f970ab18887c4f6dca78d3f8bb';
    readonly envVar = 'AUTHORIZE_KEYS';
    readonly gatewayTypeId = GatewayType.CREDIT_CARD;
    readonly supportsFullPayment = false;

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
}
