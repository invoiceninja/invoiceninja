import {
    createAndLogInClient,
    logInPortalClient,
    type PortalClient,
} from '../../client-portal-helpers';
import { test, expect, uniqueName } from '../../fixtures';
import {
    filterClientGatewayTokens,
    listClientGatewayTokens,
    updateClient,
} from '../../api-helpers';
import { type GatewayAvailability } from '../../gateways/types';
import {
    defaultClientAddress,
    navigateToPortalGatewayCheckout,
    paymentTestSettings,
    type PortalPaymentFlow,
} from '../../gateways/payment-flow-helpers';
import { createSentInvoice } from '../../portal-entity-helpers';
import {
    assertPayPalMethodCheckoutReady,
    PAYPAL_SANDBOX_TEST_CARD,
} from './flow-helpers';
import { PayPalPaymentGateway } from './payment-gateway';
import { payPalRestPaymentMethodByTypeId } from './payment-methods';
import {
    assertPayPalVaultTokenCheckoutReady,
    completePayPalAdvancedCardVaultPayment,
    completePayPalVaultTokenPayment,
    enablePayPalAdvancedCardVaultOption,
} from './vault-helpers';

const advancedCards = payPalRestPaymentMethodByTypeId(29)!;
const vaultedCardLast4 = PAYPAL_SANDBOX_TEST_CARD.number.slice(-4);

export function definePayPalAdvancedCardVaultSuite(
    paypal: PayPalPaymentGateway,
    paymentFlow: PortalPaymentFlow,
): void {
    let availability: GatewayAvailability;
    let setupSkipReason: string | undefined;
    let vaultClient: PortalClient | undefined;

    test.beforeAll(async ({ workerApi }) => {
        const setup = await paypal.setupExclusiveTestEnvironment(workerApi);

        if (setup.skipReason) {
            setupSkipReason = setup.skipReason;
        }

        availability = setup.availability;

        if (
            !setupSkipReason &&
            !paypal
                .enabledPaymentMethods(availability)
                .some((method) => method.gatewayTypeId === 29)
        ) {
            setupSkipReason =
                'PayPal Advanced Cards (type 29) is not enabled on this gateway';
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

    test(`shows save payment method option on advanced card checkout (${paymentFlow} flow)`, async ({
        api,
        page,
    }) => {
        test.setTimeout(120_000);

        vaultClient = await createAndLogInClient(api, page, {
            name: uniqueName('paypal-vault'),
            settings: {
                ...paymentTestSettings,
                payment_flow: paymentFlow,
            },
        });
        vaultClient = await updateClient(api.context, vaultClient, {
            ...defaultClientAddress,
            phone: '5555555555',
        });

        const invoice = await createSentInvoice(api, vaultClient, {
            label: `paypal-vault-ui-${paymentFlow}`,
            cost: 42,
        });

        await navigateToPortalGatewayCheckout(
            page,
            availability.companyGateway!,
            advancedCards.gatewayTypeId,
            paymentFlow,
            invoice,
            advancedCards.label,
        );
        await assertPayPalMethodCheckoutReady(page, advancedCards);
        await enablePayPalAdvancedCardVaultOption(page);
    });

    test(`vaults card on successful advanced card payment (${paymentFlow} flow)`, async ({
        api,
        page,
        notificationGuard,
    }) => {
        test.setTimeout(300_000);

        if (!vaultClient) {
            test.skip(true, 'Vault client was not created in the prior test');
        }

        await notificationGuard.suppressPaymentEmails();
        await logInPortalClient(page, vaultClient!);

        const invoice = await createSentInvoice(api, vaultClient!, {
            label: `paypal-vault-store-${paymentFlow}`,
            cost: 42,
        });

        await navigateToPortalGatewayCheckout(
            page,
            availability.companyGateway!,
            advancedCards.gatewayTypeId,
            paymentFlow,
            invoice,
            advancedCards.label,
        );
        await assertPayPalMethodCheckoutReady(page, advancedCards);
        await completePayPalAdvancedCardVaultPayment(page);
        await paypal.assertPaymentSucceeded(page);

        const tokens = filterClientGatewayTokens(
            await listClientGatewayTokens(api.context),
            {
                clientId: vaultClient!.id,
                companyGatewayId: availability.companyGateway!.id,
                gatewayTypeId: advancedCards.gatewayTypeId,
            },
        );

        expect(tokens.length).toBeGreaterThanOrEqual(1);
        expect(tokens[0]?.meta?.last4).toBe(vaultedCardLast4);
        expect(String(tokens[0]?.token ?? '').length).toBeGreaterThan(2);
    });

    test(`pays with vaulted token on a second invoice (${paymentFlow} flow)`, async ({
        api,
        page,
        notificationGuard,
    }) => {
        test.setTimeout(300_000);

        if (!vaultClient) {
            test.skip(true, 'Vault client was not created in the prior test');
        }

        const existingTokens = filterClientGatewayTokens(
            await listClientGatewayTokens(api.context),
            {
                clientId: vaultClient!.id,
                companyGatewayId: availability.companyGateway!.id,
                gatewayTypeId: advancedCards.gatewayTypeId,
            },
        );

        if (existingTokens.length === 0) {
            test.skip(
                true,
                'No vaulted PayPal advanced card token exists for this client',
            );
        }

        await notificationGuard.suppressPaymentEmails();
        await logInPortalClient(page, vaultClient!);

        const invoice = await createSentInvoice(api, vaultClient!, {
            label: `paypal-vault-reuse-${paymentFlow}`,
            cost: 42,
        });

        await navigateToPortalGatewayCheckout(
            page,
            availability.companyGateway!,
            advancedCards.gatewayTypeId,
            paymentFlow,
            invoice,
            advancedCards.label,
        );
        await assertPayPalVaultTokenCheckoutReady(page, vaultedCardLast4);
        await completePayPalVaultTokenPayment(page);
        await paypal.assertPaymentSucceeded(page);
        await paypal.assertInvoicePaid(api.context, invoice);
    });
}
