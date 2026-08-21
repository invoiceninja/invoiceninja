import { dismissCookieConsent } from './client-portal-helpers';
import { test, expect } from './fixtures';
import { PayPalPaymentGateway } from './gateways/paypal-payment-gateway';
import {
    isOptionalPayPalSandboxMethod,
    isPayPalSandboxPaymentMethod,
    PAYPAL_REST_PAYMENT_METHODS,
    type PayPalRestPaymentMethod,
} from './gateways/paypal-payment-methods';
import { type GatewayAvailability } from './gateways/types';
import { decodePrimaryKey } from './hash-helpers';

const paypal = new PayPalPaymentGateway();

// PayPal SDK blocks headless wallet checkout. Force headed for this file
// (VS Code extension does not always inherit playwright.config use.headless).
test.use({
    headless:
        process.env.PLAYWRIGHT_HEADLESS === '1' || process.env.CI === 'true',
});

test.describe('PayPal REST payment methods', () => {
    test.describe.configure({ timeout: 300_000 });

    let availability: GatewayAvailability;
    let enabledMethods: PayPalRestPaymentMethod[];
    let setupSkipReason: string | undefined;

    test.beforeAll(async ({ workerApi }) => {
        const setup = await paypal.setupExclusiveTestEnvironment(workerApi);

        if (setup.skipReason) {
            setupSkipReason = setup.skipReason;
        }

        availability = setup.availability;
        enabledMethods = paypal.enabledPaymentMethods(availability);

        if (!setupSkipReason && enabledMethods.length === 0) {
            setupSkipReason =
                'PayPal REST gateway has no enabled payment methods in fees_and_limits';
        }
    }, 120_000);

    test.afterAll(async () => {
        await paypal.restoreExclusiveGateway();
    });

    test.beforeEach(() => {
        if (setupSkipReason) {
            test.skip(true, setupSkipReason);
        }
    });

    function skipUnlessMethodEnabled(method: PayPalRestPaymentMethod): void {
        if (
            !enabledMethods.some(
                (entry) => entry.gatewayTypeId === method.gatewayTypeId,
            )
        ) {
            test.skip(true, `${method.label} is not enabled on this gateway`);
        }
    }

    async function assertCheckoutReadyForSandboxPayment(
        page: import('@playwright/test').Page,
        method: PayPalRestPaymentMethod,
    ): Promise<void> {
        try {
            await paypal.assertMethodCheckoutReady(page, method);
        } catch (error) {
            const message =
                error instanceof Error ? error.message : String(error);

            if (isOptionalPayPalSandboxMethod(method)) {
                test.skip(
                    true,
                    `${method.label} is unavailable in this PayPal sandbox — ${message}`,
                );
            }

            throw error;
        }
    }

    for (const method of PAYPAL_REST_PAYMENT_METHODS) {
        test.describe(method.label, () => {
            test('is offered in Pay Now', async ({ api, page }) => {
                skipUnlessMethodEnabled(method);

                const context = await paypal.preparePaymentContext(
                    api,
                    page,
                    availability,
                );
                const companyGateway = availability.companyGateway!;
                const rawId = decodePrimaryKey(companyGateway.id);

                await page.goto(`/client/invoices/${context.invoice.id}`);
                await dismissCookieConsent(page);

                const dropdown = page.locator('[dusk="pay-now-dropdown"]');

                if ((await dropdown.count()) === 0) {
                    test.skip(true, 'Pay Now dropdown is unavailable');
                }

                await dropdown.click();

                const gatewayOption = page
                    .locator(
                        `[dusk="payment-methods-dropdown"] [data-gateway-key="${paypal.gatewayKey}"][data-gateway-type-id="${method.gatewayTypeId}"]`,
                    )
                    .or(
                        page.locator(
                            `[dusk="payment-methods-dropdown"] [data-company-gateway-id="${rawId}"][data-gateway-type-id="${method.gatewayTypeId}"]`,
                        ),
                    );

                if ((await gatewayOption.count()) === 0) {
                    test.skip(
                        true,
                        `${method.label} is not offered for this company gateway`,
                    );
                }

                await expect(gatewayOption.first()).toBeVisible({
                    timeout: 15_000,
                });
            });

            test('renders checkout UI', async ({ api, page }) => {
                skipUnlessMethodEnabled(method);
                test.setTimeout(120_000);

                const context = await paypal.preparePaymentContext(
                    api,
                    page,
                    availability,
                );

                await paypal.navigateToMethodCheckout(page, context, method);

                try {
                    await paypal.assertMethodCheckoutReady(page, method);
                } catch (error) {
                    test.skip(
                        true,
                        error instanceof Error
                            ? error.message
                            : `${method.label} checkout UI unavailable`,
                    );
                }
            });

            if (isPayPalSandboxPaymentMethod(method)) {
                test('completes sandbox payment', async ({
                    api,
                    page,
                    notificationGuard,
                }) => {
                    skipUnlessMethodEnabled(method);

                    if (!paypal.methodSupportsSandboxPayment(method)) {
                        test.skip(
                            true,
                            method.checkoutKind === 'advanced-cards'
                                ? 'PayPal advanced card checkout unavailable'
                                : 'Add buyerEmail and buyerPassword to PAYPAL_REST_KEYS',
                        );
                    }

                    test.setTimeout(300_000);

                    await notificationGuard.suppressPaymentEmails();

                    const context = await paypal.preparePaymentContext(
                        api,
                        page,
                        availability,
                    );

                    await paypal.navigateToMethodCheckout(
                        page,
                        context,
                        method,
                    );
                    await assertCheckoutReadyForSandboxPayment(page, method);
                    await paypal.completeMethodPayment(page, method);
                    await paypal.assertPaymentSucceeded(page);
                    await paypal.assertInvoicePaid(
                        api.context,
                        context.invoice,
                    );
                });
            }
        });
    }
});
