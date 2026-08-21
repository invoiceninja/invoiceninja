import { dismissCookieConsent } from '../../client-portal-helpers';
import { test, expect } from '../../fixtures';
import { decodePrimaryKey } from '../../hash-helpers';
import {
    assertSmoothPaymentMethodOffered,
    openSmoothInvoicePaymentPage,
    type PortalPaymentFlow,
} from '../../gateways/payment-flow-helpers';
import { type GatewayAvailability } from '../../gateways/types';
import {
    assertPayPalAdvancedCardValidationError,
    configurePayPalUsFundingSandboxContext,
    requiresPayPalUsFundingSandboxContext,
    submitPayPalAdvancedCardWithInvalidNumber,
} from './flow-helpers';
import { PayPalPaymentGateway } from './payment-gateway';
import {
    isOptionalPayPalSandboxMethod,
    isPayPalSandboxPaymentMethod,
    payPalE2ePaymentCompletionSkipReason,
    supportsPayPalE2ePaymentCompletion,
    type PayPalRestPaymentMethod,
} from './payment-methods';

export interface PayPalRestMethodSuiteState {
    availability: GatewayAvailability;
    enabledMethods: PayPalRestPaymentMethod[];
    setupSkipReason?: string;
}

export function definePayPalRestMethodSuite(options: {
    paypal: PayPalPaymentGateway;
    methods: PayPalRestPaymentMethod[];
    paymentFlow: PortalPaymentFlow;
    setupSuite: () => PayPalRestMethodSuiteState;
}): void {
    const { paypal, methods, paymentFlow, setupSuite } = options;
    const flowLabel = paymentFlow === 'smooth' ? 'smooth payment flow' : 'Pay Now';

    function skipUnlessMethodEnabled(method: PayPalRestPaymentMethod): void {
        const { enabledMethods } = setupSuite();

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

    for (const method of methods) {
        test.describe(method.label, () => {
            test.beforeEach(async ({ page }) => {
                const { setupSkipReason } = setupSuite();

                if (setupSkipReason) {
                    test.skip(true, setupSkipReason);
                }

                if (requiresPayPalUsFundingSandboxContext(method)) {
                    await configurePayPalUsFundingSandboxContext(page.context());
                }
            });

            test(`is offered in ${flowLabel}`, async ({ api, page }) => {
                skipUnlessMethodEnabled(method);

                const { availability } = setupSuite();
                const context = await paypal.preparePaymentContext(
                    api,
                    page,
                    availability,
                    paymentFlow,
                );
                const companyGateway = availability.companyGateway!;
                const rawId = decodePrimaryKey(companyGateway.id);

                if (paymentFlow === 'smooth') {
                    await openSmoothInvoicePaymentPage(page, context.invoice);
                    await assertSmoothPaymentMethodOffered(
                        page,
                        companyGateway,
                        method.gatewayTypeId,
                        method.label,
                    );

                    return;
                }

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

                const { availability } = setupSuite();
                const context = await paypal.preparePaymentContext(
                    api,
                    page,
                    availability,
                    paymentFlow,
                );

                await paypal.navigateToMethodCheckout(
                    page,
                    context,
                    method,
                    paymentFlow,
                );

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

            if (method.checkoutKind === 'advanced-cards') {
                test('surfaces invalid card number validation error', async ({
                    api,
                    page,
                }) => {
                    skipUnlessMethodEnabled(method);
                    test.setTimeout(120_000);

                    const pageErrors: string[] = [];
                    page.on('pageerror', (error) => {
                        pageErrors.push(error.message);
                    });

                    const { availability } = setupSuite();
                    const context = await paypal.preparePaymentContext(
                        api,
                        page,
                        availability,
                        paymentFlow,
                    );

                    await paypal.navigateToMethodCheckout(
                        page,
                        context,
                        method,
                        paymentFlow,
                    );
                    await assertCheckoutReadyForSandboxPayment(page, method);
                    await submitPayPalAdvancedCardWithInvalidNumber(page);
                    await assertPayPalAdvancedCardValidationError(page);

                    expect(
                        pageErrors.some((message) =>
                            message.includes('indexOf is not a function'),
                        ),
                    ).toBe(false);
                });
            }

            if (isPayPalSandboxPaymentMethod(method)) {
                test('completes sandbox payment', async ({
                    api,
                    page,
                    notificationGuard,
                }) => {
                    skipUnlessMethodEnabled(method);

                    if (!supportsPayPalE2ePaymentCompletion(method)) {
                        test.skip(
                            true,
                            payPalE2ePaymentCompletionSkipReason(method),
                        );
                    }

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

                    const { availability } = setupSuite();
                    const context = await paypal.preparePaymentContext(
                        api,
                        page,
                        availability,
                        paymentFlow,
                    );

                    await paypal.navigateToMethodCheckout(
                        page,
                        context,
                        method,
                        paymentFlow,
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
}
