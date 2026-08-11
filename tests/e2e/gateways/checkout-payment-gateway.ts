import { expect, type Page } from '@playwright/test';
import { BasePaymentGateway } from './base-payment-gateway';
import { GatewayType } from './types';

export class CheckoutPaymentGateway extends BasePaymentGateway {
    readonly slug = 'checkout';
    readonly displayName = 'Checkout.com';
    readonly gatewayKey = '3758e7f7c6f4cecf0f4f348b9a00f456';
    readonly envVar = 'CHECKOUT_KEYS';
    readonly gatewayTypeId = GatewayType.CREDIT_CARD;
    readonly supportsFullPayment = false;

    async assertCheckoutReady(page: Page): Promise<void> {
        await expect(page.locator('meta[name="public-key"]')).toHaveAttribute(
            'content',
            /.+/,
        );
        await expect(page.locator('#payment-form')).toBeVisible();
        await expect(page.locator('#pay-now')).toBeVisible();
    }
}
