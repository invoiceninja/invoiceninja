import { expect, type Page } from '@playwright/test';
import {
    completePayPalAdvancedCardSandboxPayment,
    isPayPalPaymentConfirmationUrl,
    merchantPaymentResponseUrlMatcher,
    PAYPAL_SANDBOX_TEST_CARD,
    waitForPayPalMerchantPaymentComplete,
} from './flow-helpers';

export async function enablePayPalAdvancedCardVaultOption(
    page: Page,
): Promise<void> {
    const saveCardContainer = page.locator('#save-card--container');

    await expect(saveCardContainer).toBeVisible({ timeout: 30_000 });

    const saveYes = page.locator(
        'input[name="token-billing-checkbox"][value="true"]',
    );

    await saveYes.check({ force: true });
    await expect(saveYes).toBeChecked();
}

export async function completePayPalAdvancedCardVaultPayment(
    page: Page,
): Promise<void> {
    await enablePayPalAdvancedCardVaultOption(page);
    await completePayPalAdvancedCardSandboxPayment(page);
}

export async function assertPayPalVaultTokenCheckoutReady(
    page: Page,
    last4: string = PAYPAL_SANDBOX_TEST_CARD.number.slice(-4),
): Promise<void> {
    const savedToken = page
        .locator('.payment-method-item')
        .filter({ hasText: last4 })
        .locator('input.toggle-payment-with-token')
        .first();

    await expect(savedToken).toBeVisible({ timeout: 30_000 });
    await savedToken.check({ force: true });

    await expect(page.locator('#pay-now-token--container')).not.toHaveClass(
        /hidden/,
    );
    await expect(page.locator('#pay-now-token')).toBeVisible({ timeout: 15_000 });
    await expect(page.locator('#checkout-form')).toHaveClass(/hidden/);
}

export async function completePayPalVaultTokenPayment(
    page: Page,
): Promise<void> {
    const payNowToken = page.locator('#pay-now-token');

    await expect(payNowToken).toBeVisible({ timeout: 15_000 });

    const paymentResponsePromise = page.waitForResponse(
        (response) =>
            response.request().method() === 'POST' &&
            merchantPaymentResponseUrlMatcher(response.url()),
        { timeout: 180_000 },
    );

    await payNowToken.click({ timeout: 15_000, force: true });

    await paymentResponsePromise.catch(() => null);
    await page.waitForURL(
        (url) => isPayPalPaymentConfirmationUrl(url),
        { timeout: 180_000 },
    );
    await waitForPayPalMerchantPaymentComplete(page);
}
