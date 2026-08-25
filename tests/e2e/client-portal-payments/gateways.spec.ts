import { test, expect } from '../fixtures';
import { paymentGateways } from './registry';
import { openInvoicePaymentPage } from '../gateways/payment-flow-helpers';
import { type GatewayAvailability } from '../gateways/types';
import { decodePrimaryKey } from '../hash-helpers';

test.describe('Client portal payment gateways', () => {
    for (const gateway of paymentGateways) {
        test.describe(gateway.displayName, () => {
            let availability: GatewayAvailability;

            test.beforeEach(async ({ api }, testInfo) => {
                const isolateForEndToEnd =
                    gateway.requiresGatewayIsolation &&
                    testInfo.title === 'end-to-end payment flow';

                if (isolateForEndToEnd) {
                    const setup =
                        await gateway.setupExclusiveTestEnvironment(
                            api.context,
                        );

                    if (setup.skipReason) {
                        test.skip(true, setup.skipReason);
                    }

                    availability = setup.availability;

                    return;
                }

                availability = await gateway.checkAvailability(api.context);
                gateway.skipUnlessAvailable(availability);
            });

            test.afterEach(async ({}, testInfo) => {
                if (
                    gateway.requiresGatewayIsolation &&
                    testInfo.title === 'end-to-end payment flow'
                ) {
                    await gateway.restoreExclusiveGateway();
                }
            });

            test('end-to-end payment flow', async ({
                api,
                page,
                notificationGuard,
            }) => {
                test.setTimeout(120_000);

                await notificationGuard.suppressPaymentEmails();
                await gateway.runEndToEnd({ api, page, availability });
            });

            test('exposes a checkout option on the payment page', async ({
                api,
                page,
            }) => {
                const companyGateway = availability.companyGateway!;
                const context = await gateway.preparePaymentContext(
                    api,
                    page,
                    availability,
                );

                await openInvoicePaymentPage(page, context.invoice);

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
                        `${gateway.displayName} is not offered in Pay Now — enable fees_and_limits for type ${gateway.gatewayTypeId}`,
                    );
                }

                await expect(gatewayOption.first()).toBeVisible({
                    timeout: 15_000,
                });
            });
        });
    }
});
