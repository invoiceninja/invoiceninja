import { expect, type Page } from '@playwright/test';
import { BasePaymentGateway } from './base-payment-gateway';
import { fillStripeTestCard } from './payment-flow-helpers';
import { GatewayType } from './types';

export class StripePaymentGateway extends BasePaymentGateway {
    readonly slug = 'stripe';
    readonly displayName = 'Stripe';
    readonly gatewayKey = 'd14dd26a37cecc30fdd65700bfb55b23';
    readonly envVar = 'STRIPE_KEYS';
    readonly gatewayTypeId = GatewayType.CREDIT_CARD;
    readonly supportsFullPayment = true;

    async assertCheckoutReady(page: Page): Promise<void> {
        await expect(
            page.locator('meta[name="stripe-publishable-key"]'),
        ).toHaveAttribute('content', /.+/);
        await expect(page.locator('#card-element')).toBeVisible();
        await expect(page.locator('#pay-now')).toBeVisible();
    }

    async completePayment(page: Page): Promise<void> {
        const consent = page.getByRole('button', { name: 'Got it!' });
        if (await consent.isVisible().catch(() => false)) {
            await consent.click();
        }

        await fillStripeTestCard(page);
        await page.locator('#pay-now').click({ force: true });
    }
}
