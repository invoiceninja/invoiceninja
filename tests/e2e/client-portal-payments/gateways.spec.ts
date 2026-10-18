import { test, expect } from '../fixtures';
import { ensureCompanyGatewayForKey, syncCompanyGatewayConfigFromEnv } from '../api-helpers';
import { paymentGateways } from './registry';
import { openInvoicePaymentPage } from '../gateways/payment-flow-helpers';
import { type GatewayAvailability } from '../gateways/types';
import { decodePrimaryKey } from '../hash-helpers';

test.describe('Client portal payment gateways', () => {
    for (const gateway of paymentGateways) {
        test.describe(gateway.displayName, () => {
            let availability: GatewayAvailability;

            test.beforeEach(async ({ api }) => {
                if (gateway.requiresGatewayIsolation) {
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

                if (gateway.isEnvConfigured()) {
                    const companyGateway = await ensureCompanyGatewayForKey(
                        api.context,
                        gateway.gatewayKey,
                        gateway.envVar,
                    );

                    if (companyGateway) {
                        await syncCompanyGatewayConfigFromEnv(
                            api.context,
                            companyGateway,
                            gateway.envVar,
                        );
                    }
                }

                availability = await gateway.checkAvailability(api.context);
                gateway.skipUnlessAvailable(availability);
            });

            test.afterEach(async () => {
                if (gateway.requiresGatewayIsolation) {
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

                await expect(gatewayOption.first()).toBeVisible({
                    timeout: 15_000,
                });
            });
        });
    }
});
