import { test, expect } from './fixtures';
import { paymentGateways } from './gateways/gateway-registry';

test.describe('Client portal payment gateways', () => {
    for (const gateway of paymentGateways) {
        test(`${gateway.displayName} end-to-end payment flow`, async ({
            api,
            page,
            notificationGuard,
        }) => {
            const availability = await gateway.checkAvailability(api.context);
            gateway.skipUnlessAvailable(availability);

            await notificationGuard.suppressPaymentEmails();
            await gateway.runEndToEnd({ api, page, availability });
        });

        test(`${gateway.displayName} exposes a checkout option on the payment page`, async ({
            api,
            page,
        }) => {
            const availability = await gateway.checkAvailability(api.context);
            gateway.skipUnlessAvailable(availability);

            const context = await gateway.preparePaymentContext(
                api,
                page,
                availability,
            );

            await page.goto('/client/invoices');
            await page.locator('[dusk="pay-now"]').first().click();
            await expect(page).toHaveURL(/\/client\/invoices\/payment/);

            await page.locator('[dusk="pay-now-dropdown"]').click();

            const gatewayOption = page.locator(
                `[dusk="payment-methods-dropdown"] [data-gateway-type-id="${gateway.gatewayTypeId}"]`,
            );

            await expect(gatewayOption.first()).toBeVisible();
        });
    }
});
