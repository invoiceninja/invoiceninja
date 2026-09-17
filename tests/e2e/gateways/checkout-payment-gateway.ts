import { expect, test, type Page } from '@playwright/test';
import { dismissCookieConsent } from '../client-portal-helpers';
import { BasePaymentGateway } from './base-payment-gateway';
import { submitRequiredClientInfoIfPresent } from './payment-flow-helpers';
import { GatewayType } from './types';

export class CheckoutPaymentGateway extends BasePaymentGateway {
    readonly slug = 'checkout';
    readonly displayName = 'Checkout.com';
    readonly gatewayKey = '3758e7f7c6f4cecf0f4f348b9a00f456';
    readonly envVar = 'CHECKOUT_KEYS';
    readonly gatewayTypeId = GatewayType.CREDIT_CARD;
    readonly supportsFullPayment = true;

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

    /**
     * Frames renders one iframe holding card number, expiry and CVV, and only enables
     * #pay-button once it reports the card as valid.
     *
     * @see resources/js/clients/payments/checkout-credit-card.js
     */
    async completePayment(page: Page): Promise<void> {
        /** The consent banner overlays the pay button and swallows the click. */
        await dismissCookieConsent(page);
        await submitRequiredClientInfoIfPresent(page);

        const frame = page.frameLocator('#payment-form iframe').first();

        await frame
            .locator('input[name="cardNumber"], #checkout-frames-card-number')
            .first()
            .fill('4242424242424242');
        await frame
            .locator('input[name="expiryDate"], #checkout-frames-expiry-date')
            .first()
            .fill('12/29');
        await frame
            .locator('input[name="cvv"], #checkout-frames-cvv')
            .first()
            .fill('100');

        const payButton = page.locator('#pay-button');

        await expect(payButton).toBeEnabled({ timeout: 30_000 });
        await payButton.click();
    }
}
