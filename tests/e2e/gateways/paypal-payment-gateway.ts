import { expect, type Page } from '@playwright/test';
import { BasePaymentGateway } from './base-payment-gateway';
import { GatewayType } from './types';

export class PayPalPaymentGateway extends BasePaymentGateway {
    readonly slug = 'paypal';
    readonly displayName = 'PayPal Express';
    readonly gatewayKey = '38f2c48af60c7dd69e04248cbb24c36e';
    readonly envVar = 'PAYPAL_KEYS';
    readonly gatewayTypeId = GatewayType.PAYPAL;
    readonly supportsFullPayment = false;

    async assertCheckoutReady(page: Page): Promise<void> {
        await expect(page.locator('#paypal-payment')).toBeVisible({
            timeout: 30_000,
        });
    }
}
