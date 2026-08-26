import { test } from '../../fixtures';
import {
    getCompanyGateway,
    isGatewayMethodEnabled,
} from '../../api-helpers';
import {
    completeRequiredClientInfoAndUnblockCheckout,
} from '../../gateways/rff-payment-flow-helpers';
import {
    assertRequiredClientInfoBlocksCheckout,
    navigateToPortalGatewayCheckoutWithoutRequiredClientInfo,
    prepareIncompleteClientPaymentContext,
    type PortalPaymentFlow,
} from '../../gateways/payment-flow-helpers';
import { type GatewayAvailability } from '../../gateways/types';
import {
    assertPayPalAdvancedCardCheckoutReadyWhenUnblocked,
    assertPayPalMethodCheckoutReadyWhenUnblocked,
} from './flow-helpers';
import {
    payPalRestPaymentMethodByTypeId,
    type PayPalRestPaymentMethod,
} from './payment-methods';

export function definePayPalRequiredClientInfoSuite(options: {
    paymentFlow: PortalPaymentFlow;
    setupSuite: () => {
        availability: GatewayAvailability;
        setupSkipReason?: string;
    };
}): void {
    const { paymentFlow, setupSuite } = options;
    const payPalWallet = payPalRestPaymentMethodByTypeId(3)!;
    const payPalAdvancedCards = payPalRestPaymentMethodByTypeId(29)!;

    function skipUnlessAdvancedCardsEnabled(): void {
        const { setupSkipReason, availability } = setupSuite();

        if (setupSkipReason) {
            test.skip(true, setupSkipReason);
        }

        const gateway = availability.companyGateway;

        if (
            !gateway ||
            !isGatewayMethodEnabled(gateway, payPalAdvancedCards.gatewayTypeId)
        ) {
            test.skip(
                true,
                'PayPal Advanced Cards (type 29) is not enabled on the REST gateway — PPCP fees_and_limits must include type 29',
            );
        }
    }

    async function preparePayPalRffContext(
        api: import('../../fixtures').ApiFixture,
        page: import('@playwright/test').Page,
    ) {
        const { availability } = setupSuite();

        return {
            context: await prepareIncompleteClientPaymentContext(
                api,
                page,
                availability.companyGateway!,
                { paymentFlow },
            ),
        };
    }

    async function navigateToPayPalMethod(
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

    test(`blocks PayPal wallet checkout until billing address is provided (${paymentFlow} flow)`, async ({
        api,
        page,
    }) => {
        const { setupSkipReason } = setupSuite();

        if (setupSkipReason) {
            test.skip(true, setupSkipReason);
        }

        const { context } = await preparePayPalRffContext(api, page);

        try {
            await navigateToPayPalMethod(page, context, payPalWallet);
            await assertRequiredClientInfoBlocksCheckout(page);

            if (paymentFlow === 'default') {
                await page
                    .locator('#paypal-button-container')
                    .waitFor({ state: 'attached', timeout: 30_000 });
            }
        } finally {
            await context.restoreGatewayRequirements();
        }
    });

    test(`unblocks PayPal wallet checkout after Continue succeeds (${paymentFlow} flow)`, async ({
        api,
        page,
    }) => {
        const { setupSkipReason } = setupSuite();

        if (setupSkipReason) {
            test.skip(true, setupSkipReason);
        }

        const { context } = await preparePayPalRffContext(api, page);

        try {
            await navigateToPayPalMethod(page, context, payPalWallet);
            await completeRequiredClientInfoAndUnblockCheckout(page);
            await assertPayPalMethodCheckoutReadyWhenUnblocked(
                page,
                payPalWallet,
            );
        } finally {
            await context.restoreGatewayRequirements();
        }
    });

    test(`blocks PayPal Advanced Cards checkout until billing address is provided (${paymentFlow} flow)`, async ({
        api,
        page,
    }) => {
        skipUnlessAdvancedCardsEnabled();

        const { context } = await preparePayPalRffContext(api, page);

        try {
            await navigateToPayPalMethod(page, context, payPalAdvancedCards);
            await assertRequiredClientInfoBlocksCheckout(page);

            if (paymentFlow === 'default') {
                await page.locator('#checkout-form').waitFor({
                    state: 'attached',
                    timeout: 30_000,
                });
                await page
                    .locator('#card-number-field-container')
                    .waitFor({ state: 'attached', timeout: 30_000 });
            }
        } finally {
            await context.restoreGatewayRequirements();
        }
    });

    test(`unblocks PayPal Advanced Cards checkout after Continue succeeds (${paymentFlow} flow)`, async ({
        api,
        page,
    }) => {
        skipUnlessAdvancedCardsEnabled();

        const { context } = await preparePayPalRffContext(api, page);

        try {
            await navigateToPayPalMethod(page, context, payPalAdvancedCards);
            await completeRequiredClientInfoAndUnblockCheckout(page);
            await assertPayPalAdvancedCardCheckoutReadyWhenUnblocked(page);
        } finally {
            await context.restoreGatewayRequirements();
        }
    });
}
