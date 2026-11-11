import { test } from '../../fixtures';
import {
    completeRequiredClientInfoAndUnblockCheckout,
} from '../../gateways/rff-payment-flow-helpers';
import {
    navigateToPortalGatewayCheckoutWithoutRequiredClientInfo,
    prepareIncompleteClientPaymentContext,
    type PortalPaymentFlow,
} from '../../gateways/payment-flow-helpers';
import { type GatewayAvailability } from '../../gateways/types';
import {
    assertPayPalMethodCheckoutReadyWhenUnblocked,
    configurePayPalUsFundingSandboxContext,
    requiresPayPalUsFundingSandboxContext,
} from './flow-helpers';
import { PayPalPaymentGateway } from './payment-gateway';
import {
    isOptionalPayPalSandboxMethod,
    isPayPalSandboxPaymentMethod,
    payPalE2ePaymentCompletionSkipReason,
    supportsPayPalE2ePaymentCompletion,
    type PayPalRestPaymentMethod,
} from './payment-methods';

export interface PayPalRffPaymentMethodSuiteState {
    availability: GatewayAvailability;
    enabledMethods: PayPalRestPaymentMethod[];
    setupSkipReason?: string;
}

export function definePayPalRffPaymentMethodSuite(options: {
    paypal: PayPalPaymentGateway;
    methods: PayPalRestPaymentMethod[];
    paymentFlow: PortalPaymentFlow;
    setupSuite: () => PayPalRffPaymentMethodSuiteState;
}): void {
    const { paypal, methods, paymentFlow, setupSuite } = options;

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

    async function navigateToPayPalMethodWithEmptyClient(
        page: import('@playwright/test').Page,
        context: Awaited<
            ReturnType<typeof prepareIncompleteClientPaymentContext>
        >,
        method: PayPalRestPaymentMethod,
    ): Promise<void> {
        await navigateToPortalGatewayCheckoutWithoutRequiredClientInfo(
            page,
            context.companyGateway,
            method.gatewayTypeId,
            paymentFlow,
            context.invoice,
            method.label,
        );

        if (
            await page
                .getByText(/Unable to gain access token from PayPal/i)
                .isVisible()
                .catch(() => false)
        ) {
            throw new Error(
                'PayPal REST checkout failed to initialize — verify PAYPAL_REST_KEYS and company gateway config on the test app',
            );
        }
    }

    async function assertCheckoutReadyAfterRff(
        page: import('@playwright/test').Page,
        method: PayPalRestPaymentMethod,
    ): Promise<void> {
        try {
            await assertPayPalMethodCheckoutReadyWhenUnblocked(page, method);
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

            if (isPayPalSandboxPaymentMethod(method)) {
                test('completes sandbox payment after required client info', async ({
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
                            method.checkoutKind === 'advanced-cards' ||
                                method.fundingSource === 'card'
                                ? 'PayPal checkout unavailable for this method'
                                : 'Add buyerEmail and buyerPassword to PAYPAL_REST_KEYS',
                        );
                    }

                    test.setTimeout(300_000);

                    const { availability } = setupSuite();
                    const context = await prepareIncompleteClientPaymentContext(
                        api,
                        page,
                        availability.companyGateway!,
                        { paymentFlow },
                    );

                    try {
                        await notificationGuard.suppressPaymentEmails();

                        await navigateToPayPalMethodWithEmptyClient(
                            page,
                            context,
                            method,
                        );
                        await completeRequiredClientInfoAndUnblockCheckout(page);
                        await assertCheckoutReadyAfterRff(page, method);
                        await paypal.completeMethodPayment(page, method);
                        await paypal.assertPaymentSucceeded(page);
                        await paypal.assertInvoicePaid(
                            api.context,
                            context.invoice,
                        );
                    } finally {
                        await context.restoreGatewayRequirements();
                    }
                });
            }
        });
    }
}
