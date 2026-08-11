import { expect, type Page } from '@playwright/test';
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
        await expect(
            page.locator('meta[name="authorize-public-key"]'),
        ).toHaveAttribute('content', /.+/);
        await expect(
            page.locator('#authorize--credit-card-container'),
        ).toBeVisible();
        await expect(page.locator('#pay-now')).toBeVisible();
    }
}
