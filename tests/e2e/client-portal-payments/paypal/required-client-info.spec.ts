import { test, expect } from '../../fixtures';
import {
    getCompanyGateway,
    isGatewayMethodEnabled,
} from '../../api-helpers';
import {
    assertRequiredClientInfoBlocksCheckout,
    assertRequiredClientInfoUnblocksCheckout,
    completeRequiredClientInfoForm,
    navigateToGatewayCheckoutWithoutRequiredClientInfo,
    prepareIncompleteClientPaymentContext,
} from '../../gateways/payment-flow-helpers';
import { type GatewayAvailability } from '../../gateways/types';
import {
    assertPayPalAdvancedCardCheckoutReadyWhenUnblocked,
    assertPayPalMethodCheckoutReadyWhenUnblocked,
} from './flow-helpers';
import { PayPalPaymentGateway } from './payment-gateway';
import {
    payPalRestPaymentMethodByTypeId,
    type PayPalRestPaymentMethod,
} from './payment-methods';

const paypal = new PayPalPaymentGateway();

test.use({
    headless:
        process.env.PLAYWRIGHT_HEADLESS === '1' || process.env.CI === 'true',
});

test.describe.configure({ timeout: 120_000 });

test.describe('PayPal REST required client info', () => {
    const payPalWallet = payPalRestPaymentMethodByTypeId(3)!;
    const payPalAdvancedCards = payPalRestPaymentMethodByTypeId(29)!;

    let availability: GatewayAvailability;
    let setupSkipReason: string | undefined;

    test.beforeAll(async ({ workerApi }) => {
        const setup = await paypal.setupExclusiveTestEnvironment(workerApi);

        if (setup.skipReason) {
            setupSkipReason = setup.skipReason;
        }

        availability = setup.availability;

        if (availability.companyGateway) {
            availability = {
                ...availability,
                companyGateway: await getCompanyGateway(
                    workerApi,
                    availability.companyGateway.id,
                ),
            };
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

    function skipUnlessAdvancedCardsEnabled(): void {
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
        const context = await prepareIncompleteClientPaymentContext(
            api,
            page,
            availability.companyGateway!,
        );

        return { context };
    }

    async function navigateToPayPalMethod(
        page: import('@playwright/test').Page,
        context: Awaited<
            ReturnType<typeof prepareIncompleteClientPaymentContext>
        >,
        method: PayPalRestPaymentMethod,
    ): Promise<void> {
        await navigateToGatewayCheckoutWithoutRequiredClientInfo(
            page,
            context.companyGateway,
            method.gatewayTypeId,
            context.invoice,
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

    test('blocks PayPal wallet checkout until billing address is provided', async ({
        api,
        page,
    }) => {
        const { context } = await preparePayPalRffContext(api, page);

        try {
            await navigateToPayPalMethod(page, context, payPalWallet);
            await assertRequiredClientInfoBlocksCheckout(page);
            await expect(
                page.locator('#paypal-button-container'),
            ).toBeAttached();
        } finally {
            await context.restoreGatewayRequirements();
        }
    });

    test('unblocks PayPal wallet checkout after Continue succeeds', async ({
        api,
        page,
    }) => {
        const { context } = await preparePayPalRffContext(api, page);

        try {
            await navigateToPayPalMethod(page, context, payPalWallet);
            await assertRequiredClientInfoBlocksCheckout(page);
            await completeRequiredClientInfoForm(page);
            await assertRequiredClientInfoUnblocksCheckout(page);
            await assertPayPalMethodCheckoutReadyWhenUnblocked(
                page,
                payPalWallet,
            );
        } finally {
            await context.restoreGatewayRequirements();
        }
    });

    test('blocks PayPal Advanced Cards checkout until billing address is provided', async ({
        api,
        page,
    }) => {
        skipUnlessAdvancedCardsEnabled();

        const { context } = await preparePayPalRffContext(api, page);

        try {
            await navigateToPayPalMethod(page, context, payPalAdvancedCards);
            await assertRequiredClientInfoBlocksCheckout(page);
            await expect(page.locator('#checkout-form')).toBeAttached();
            await expect(
                page.locator('#card-number-field-container'),
            ).toBeAttached();
        } finally {
            await context.restoreGatewayRequirements();
        }
    });

    test('unblocks PayPal Advanced Cards checkout after Continue succeeds', async ({
        api,
        page,
    }) => {
        skipUnlessAdvancedCardsEnabled();

        const { context } = await preparePayPalRffContext(api, page);

        try {
            await navigateToPayPalMethod(page, context, payPalAdvancedCards);
            await assertRequiredClientInfoBlocksCheckout(page);
            await completeRequiredClientInfoForm(page);
            await assertRequiredClientInfoUnblocksCheckout(page);
            await assertPayPalAdvancedCardCheckoutReadyWhenUnblocked(page);
        } finally {
            await context.restoreGatewayRequirements();
        }
    });
});
