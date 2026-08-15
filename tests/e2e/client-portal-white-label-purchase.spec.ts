import { smallTestAccount } from './accounts';
import {
    dismissCookieConsent,
    portalContact,
} from './client-portal-helpers';
import { expect, test as baseTest } from './fixtures';
import { StripePaymentGateway } from './gateways/stripe-payment-gateway';
import type { TestAccount } from './accounts';
import {
    completeSubscriptionCheckoutRequiredFields,
    countClientInvoices,
    createWhiteLabelSubscription,
    latestClientInvoice,
    whiteLabelFulfillmentSkipReason,
    logInAndOpenWhiteLabelPurchase,
    openWhiteLabelPurchasePage,
    prepareWhiteLabelPurchaseClient,
    purchaseLoadingSpinner,
    purchasePaymentMethodButtons,
    requiredClientInfoForm,
    selectFirstPurchasePaymentMethod,
    stripeGatewayConfigured,
    waitForGatewayCheckoutReady,
    waitForPurchasePaymentMethods,
    waitForSubscriptionCheckoutRedirect,
} from './white-label-purchase-helpers';

const test = baseTest.extend<{}, { account: TestAccount }>({
    account: [
        async ({}, use) => {
            await use(smallTestAccount());
        },
        { scope: 'worker' },
    ],
});

test.describe('White label license purchase', () => {
    test('loads the v1 purchase page for a white label subscription', async ({
        api,
        page,
    }) => {
        const { subscription } = await createWhiteLabelSubscription(api);

        await openWhiteLabelPurchasePage(page, subscription.id);

        await expect(page.locator('#billing-page-company-logo')).toContainText(
            subscription.name ?? '',
        );
        await expect(page).toHaveURL(
            new RegExp(`/client/subscriptions/${subscription.id}/purchase`),
        );
    });

    test('shows checkout spinner and hides payment buttons after selecting a method', async ({
        api,
        page,
    }) => {
        const { subscription } = await createWhiteLabelSubscription(api);
        await logInAndOpenWhiteLabelPurchase(api, page, subscription.id);

        const paymentButton = purchasePaymentMethodButtons(page).first();
        await paymentButton.click();

        await expect(purchasePaymentMethodButtons(page)).toHaveCount(0, {
            timeout: 10_000,
        });
        await expect(purchaseLoadingSpinner(page)).toBeVisible();
    });

    test('creates a proforma invoice before redirecting to gateway checkout', async ({
        api,
        page,
    }) => {
        const { subscription } = await createWhiteLabelSubscription(api);
        const client = await logInAndOpenWhiteLabelPurchase(
            api,
            page,
            subscription.id,
        );

        const invoicesBefore = await countClientInvoices(api.context, client.id);

        await selectFirstPurchasePaymentMethod(page);
        await waitForSubscriptionCheckoutRedirect(page);

        const invoicesAfter = await countClientInvoices(api.context, client.id);
        expect(invoicesAfter).toBe(invoicesBefore + 1);

        const invoice = await latestClientInvoice(api.context, client.id);
        expect(String(invoice.status_id)).not.toBe('4'); // not paid yet
    });

    test.fail(
        'does not create duplicate invoices when payment method is double-clicked',
        async ({ api, page }) => {
        const { subscription } = await createWhiteLabelSubscription(api);
        const client = await logInAndOpenWhiteLabelPurchase(
            api,
            page,
            subscription.id,
        );

        const invoicesBefore = await countClientInvoices(api.context, client.id);
        const paymentButton = purchasePaymentMethodButtons(page).first();

        await paymentButton.dblclick();
        await page.waitForTimeout(4_000);

        const invoicesAfter = await countClientInvoices(api.context, client.id);

        expect(invoicesAfter - invoicesBefore).toBe(1);
        },
    );

    test('keeps payment buttons disabled while checkout is in progress', async ({
        api,
        page,
    }) => {
        const { subscription } = await createWhiteLabelSubscription(api);
        await logInAndOpenWhiteLabelPurchase(api, page, subscription.id);

        const paymentButton = purchasePaymentMethodButtons(page).first();
        await paymentButton.click();

        await expect(purchasePaymentMethodButtons(page)).toHaveCount(0);

        // Regression guard: users should not be able to start checkout twice
        // from the billing portal while the spinner is showing.
        await expect(paymentButton).toBeHidden();
        await expect(purchaseLoadingSpinner(page)).toBeVisible();
    });

    test('requires required client info before enabling stripe checkout', async ({
        api,
        page,
    }) => {
        test.setTimeout(120_000);

        const stripe = new StripePaymentGateway();
        const availability = await stripe.checkAvailability(api.context);
        stripe.skipUnlessAvailable(availability);

        const { subscription } = await createWhiteLabelSubscription(api);
        await logInAndOpenWhiteLabelPurchase(api, page, subscription.id);

        await selectFirstPurchasePaymentMethod(page);
        await waitForSubscriptionCheckoutRedirect(page);
        await dismissCookieConsent(page);

        await expect(requiredClientInfoForm(page)).toBeVisible();
        await expect(
            page.getByRole('heading', { name: /Required payment details/i }),
        ).toBeVisible();

        await completeSubscriptionCheckoutRequiredFields(page);
        await waitForGatewayCheckoutReady(page);
        await stripe.assertCheckoutReady(page);
    });

    test('redirects to stripe checkout after selecting a payment method', async ({
        api,
        page,
    }) => {
        test.setTimeout(120_000);

        const stripe = new StripePaymentGateway();
        const availability = await stripe.checkAvailability(api.context);
        stripe.skipUnlessAvailable(availability);

        const { subscription } = await createWhiteLabelSubscription(api);
        const client = await logInAndOpenWhiteLabelPurchase(
            api,
            page,
            subscription.id,
        );

        await selectFirstPurchasePaymentMethod(page);
        await waitForSubscriptionCheckoutRedirect(page);
        await dismissCookieConsent(page);
        await completeSubscriptionCheckoutRequiredFields(
            page,
            portalContact(client).email,
        );
        await waitForGatewayCheckoutReady(page);
        await stripe.assertCheckoutReady(page);
    });

    test('issues a license key after successful white label payment', async ({
        api,
        page,
        notificationGuard,
    }) => {
        test.setTimeout(180_000);

        const fulfillmentSkipReason = await whiteLabelFulfillmentSkipReason(
            api.context,
        );
        if (fulfillmentSkipReason) {
            test.skip(true, fulfillmentSkipReason);
        }

        if (!stripeGatewayConfigured()) {
            test.skip(true, 'Set STRIPE_KEYS to run white label fulfillment');
        }

        await notificationGuard.suppressPaymentEmails();

        const stripe = new StripePaymentGateway();
        const availability = await stripe.checkAvailability(api.context);
        stripe.skipUnlessAvailable(availability);

        const { subscription } = await createWhiteLabelSubscription(api, {
            cleanup: false,
        });
        const client = await logInAndOpenWhiteLabelPurchase(
            api,
            page,
            subscription.id,
        );

        await selectFirstPurchasePaymentMethod(page);
        await waitForSubscriptionCheckoutRedirect(page);
        await dismissCookieConsent(page);
        await completeSubscriptionCheckoutRequiredFields(
            page,
            portalContact(client).email,
        );
        await waitForGatewayCheckoutReady(page);
        await stripe.assertCheckoutReady(page);
        await stripe.completePayment(page);
        await stripe.assertPaymentSucceeded(page);

        const paidInvoice = await latestClientInvoice(api.context, client.id);
        expect(paidInvoice.footer ?? '').toMatch(/v5_[0-9a-f-]{36}/i);
    });

    test('v2 purchase page loads for a white label subscription', async ({
        api,
        page,
    }) => {
        const { subscription } = await createWhiteLabelSubscription(api);
        await prepareWhiteLabelPurchaseClient(api, page);

        const response = await page.goto(
            `/client/subscriptions/${subscription.id}/purchase/v2`,
        );
        expect(response?.ok()).toBe(true);
        await dismissCookieConsent(page);
        await expect(page.locator('#billing-page-company-logo')).toContainText(
            subscription.name ?? '',
        );
        await expect(page).toHaveURL(
            new RegExp(
                `/client/subscriptions/${subscription.id}/purchase/v2`,
            ),
        );
    });
});
