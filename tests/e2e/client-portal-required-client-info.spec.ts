import { dismissCookieConsent } from './client-portal-helpers';
import { test, expect } from './fixtures';
import {
    assertRequiredClientInfoBlocksCheckout,
    assertRequiredClientInfoUnblocksCheckout,
    completeRequiredClientInfoForm,
    isRequiredClientInfoBlockingCheckout,
    navigateToGatewayCheckoutWithoutRequiredClientInfo,
    prepareIncompleteClientPaymentContext,
    requiredClientInfoForm,
} from './gateways/payment-flow-helpers';
import { StripePaymentGateway } from './gateways/stripe-payment-gateway';
import { GatewayType } from './gateways/types';

const stripe = new StripePaymentGateway();

test.describe('Required client info checkout gating', () => {
    test.describe.configure({ timeout: 120_000 });

    test.describe('Stripe credit card', () => {
        test('blocks checkout until billing address is provided', async ({
            api,
            page,
        }) => {
            const availability = await stripe.checkAvailability(api.context);
            stripe.skipUnlessAvailable(availability);

            const context = await prepareIncompleteClientPaymentContext(
                api,
                page,
                availability.companyGateway!,
            );

            try {
                await navigateToGatewayCheckoutWithoutRequiredClientInfo(
                    page,
                    context.companyGateway,
                    GatewayType.CREDIT_CARD,
                    context.invoice,
                );
                await assertRequiredClientInfoBlocksCheckout(page);
                await expect(page.locator('#card-element')).toBeAttached();
            } finally {
                await context.restoreGatewayRequirements();
            }
        });

        test('keeps checkout blocked when validation fails', async ({
            api,
            page,
        }) => {
            const availability = await stripe.checkAvailability(api.context);
            stripe.skipUnlessAvailable(availability);

            const context = await prepareIncompleteClientPaymentContext(
                api,
                page,
                availability.companyGateway!,
            );

            try {
                await navigateToGatewayCheckoutWithoutRequiredClientInfo(
                    page,
                    context.companyGateway,
                    GatewayType.CREDIT_CARD,
                    context.invoice,
                );
                await assertRequiredClientInfoBlocksCheckout(page);

                const form = requiredClientInfoForm(page);
                await form.locator('[name="contact_email"]').fill('not-an-email');
                await form.locator('button.button-primary').click();

                await expect
                    .poll(() => isRequiredClientInfoBlockingCheckout(page), {
                        timeout: 15_000,
                    })
                    .toBe(true);
                await expect(form.locator('p.border-red-300').first()).toBeVisible({
                    timeout: 15_000,
                });
            } finally {
                await context.restoreGatewayRequirements();
            }
        });

        test('unblocks checkout after Continue succeeds', async ({
            api,
            page,
        }) => {
            const availability = await stripe.checkAvailability(api.context);
            stripe.skipUnlessAvailable(availability);

            const context = await prepareIncompleteClientPaymentContext(
                api,
                page,
                availability.companyGateway!,
            );

            try {
                await navigateToGatewayCheckoutWithoutRequiredClientInfo(
                    page,
                    context.companyGateway,
                    GatewayType.CREDIT_CARD,
                    context.invoice,
                );
                await assertRequiredClientInfoBlocksCheckout(page);
                await completeRequiredClientInfoForm(page);
                await assertRequiredClientInfoUnblocksCheckout(page);
                await stripe.assertCheckoutReady(page);
            } finally {
                await context.restoreGatewayRequirements();
            }
        });

        test('requires Continue even when client already has billing address if always_show_required_fields is enabled', async ({
            api,
            page,
        }) => {
            const availability = await stripe.checkAvailability(api.context);
            stripe.skipUnlessAvailable(availability);

            const context = await prepareIncompleteClientPaymentContext(
                api,
                page,
                availability.companyGateway!,
                { alwaysShowRequiredFields: true },
            );

            try {
                await navigateToGatewayCheckoutWithoutRequiredClientInfo(
                    page,
                    context.companyGateway,
                    GatewayType.CREDIT_CARD,
                    context.invoice,
                );
                await assertRequiredClientInfoBlocksCheckout(page);
                await completeRequiredClientInfoForm(page);
                await assertRequiredClientInfoUnblocksCheckout(page);
                await stripe.assertCheckoutReady(page);
            } finally {
                await context.restoreGatewayRequirements();
            }
        });
    });
});
