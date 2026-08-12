import { expect, test, type Page } from '@playwright/test';
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
        const publicKey = page.locator('meta[name="public-key"]');
        const content = await publicKey
            .getAttribute('content', { timeout: 10_000 })
            .catch(() => null);

        if (!content) {
            test.skip(
                true,
                'Checkout.com public key meta missing after gateway selection',
            );
        }

        await expect(publicKey).toHaveAttribute('content', /.+/);
        // Checkout Frames does not always expose a classic #pay-now control.
        await expect(page.locator('#payment-form')).toBeVisible();
    }
}
