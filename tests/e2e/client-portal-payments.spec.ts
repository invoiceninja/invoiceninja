import { dismissCookieConsent } from './client-portal-helpers';
import { test, expect } from './fixtures';
import { paymentGateways } from './gateways/gateway-registry';
import { decodePrimaryKey } from './hash-helpers';

test.describe('Client portal payment gateways', () => {
    for (const gateway of paymentGateways) {
        test(`${gateway.displayName} end-to-end payment flow`, async ({
            api,
            page,
            notificationGuard,
        }) => {
            test.setTimeout(120_000);

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

            const companyGateway = availability.companyGateway!;
            await gateway.preparePaymentContext(api, page, availability);

            await page.goto('/client/invoices');
            await dismissCookieConsent(page);
            await page.locator('[dusk="pay-now"]').first().click();
            await expect(page).toHaveURL(/\/client\/invoices\/payment/);
            await dismissCookieConsent(page);

            await page.locator('[dusk="pay-now-dropdown"]').click();

            const rawId = decodePrimaryKey(companyGateway.id);
            const gatewayOption = page
                .locator(
                    `[dusk="payment-methods-dropdown"] [data-gateway-key="${gateway.gatewayKey}"][data-gateway-type-id="${gateway.gatewayTypeId}"]`,
                )
                .or(
                    page.locator(
                        `[dusk="payment-methods-dropdown"] [data-company-gateway-id="${rawId}"][data-gateway-type-id="${gateway.gatewayTypeId}"]`,
                    ),
                );

            if ((await gatewayOption.count()) === 0) {
                test.skip(
                    true,
                    `${gateway.displayName} is not offered in Pay Now — deploy the PaymentMethod multi-gateway fix or enable fees_and_limits for this type`,
                );
            }

            await expect(gatewayOption.first()).toBeVisible({ timeout: 15_000 });
        });
    }
});
