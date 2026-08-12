import { expect, type Page } from '@playwright/test';
import { BasePaymentGateway } from './base-payment-gateway';
import { GatewayType } from './types';

export class BraintreePaymentGateway extends BasePaymentGateway {
    readonly slug = 'braintree';
    readonly displayName = 'Braintree';
    readonly gatewayKey = 'f7ec488676d310683fb51802d076d713';
    readonly envVar = 'BRAINTREE_KEYS';
    readonly gatewayTypeId = GatewayType.CREDIT_CARD;
    readonly supportsFullPayment = false;

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
}
