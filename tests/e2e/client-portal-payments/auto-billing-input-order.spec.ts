import { test as base, expect } from '../fixtures';
import {
    getEntity,
    getUsers,
    updateUser,
    parseCompanyGatewayConfig,
    updateCompanyGatewayRequirements,
    type CompanyGatewayEntity,
} from '../api-helpers';
import {
    createRecurringInvoice,
    type PortalEntity,
} from '../portal-entity-helpers';
import { dismissCookieConsent } from '../client-portal-helpers';
import { runArtisan } from '../artisan-helpers';
import { decodePrimaryKey } from '../hash-helpers';
import { StripePaymentGateway } from '../gateways/stripe-payment-gateway';
import {
    navigateToPortalGatewayCheckout,
    preparePortalPaymentContext,
} from '../gateways/payment-flow-helpers';

// Preference regression: makes real Stripe sandbox payments, never live payments.
const test = base.extend<{ checkoutGateway: CompanyGatewayEntity }>({
    checkoutGateway: async ({ api }, use) => {
        const stripe = new StripePaymentGateway();
        const { availability } = await stripe.setupExclusiveTestEnvironment(
            api.context
        );
        try {
            stripe.skipUnlessAvailable(availability);
            const gateway = availability.companyGateway!;
            try {
                await use(gateway);
            } finally {
                await updateCompanyGatewayRequirements(api.context, gateway, {
                    token_billing: gateway.token_billing,
                });
            }
        } finally {
            await stripe.restoreExclusiveGateway();
        }
    },
});

function attachInvoices(
    recurring: PortalEntity,
    invoices: PortalEntity[]
): void {
    const ids = invoices.map((invoice) => Number(decodePrimaryKey(invoice.id)));
    runArtisan(
        `$recurring = \\App\\Models\\RecurringInvoice::findOrFail(${Number(decodePrimaryKey(recurring.id))});` +
            `\\App\\Models\\Invoice::whereIn('id', ${JSON.stringify(ids)})` +
            "->where('company_id', $recurring->company_id)->where('client_id', $recurring->client_id)" +
            "->update(['recurring_id' => $recurring->id, 'auto_bill_enabled' => $recurring->auto_bill_enabled]);"
    );
}

test.skip(
    process.env.PLAYWRIGHT_AUTOBILL_RACE !== '1',
    'Explicit Stripe sandbox regression; set PLAYWRIGHT_AUTOBILL_RACE=1'
);

for (const flow of ['default', 'smooth'] as const) {
    for (const autoBill of ['optin', 'optout'] as const) {
        test(`${flow}, ${autoBill}: synchronous save-method update survives a delayed Livewire response`, async ({
            api,
            page,
            checkoutGateway,
        }, testInfo) => {
            test.setTimeout(180_000);
            const selection = autoBill === 'optin';
            const config = parseCompanyGatewayConfig(checkoutGateway);
            if (
                !String(config.apiKey ?? '').startsWith('sk_test_') ||
                !String(config.publishableKey ?? '').startsWith('pk_test_') ||
                config.account_id
            ) {
                throw new Error(
                    'This regression requires direct Stripe test-mode keys; Connect/live credentials are not supported.'
                );
            }
            const users = await getUsers(api.context);
            let release = () => {};
            let releaseRequest = () => {};
            try {
                for (const user of users) {
                    await updateUser(api.context, {
                        ...user,
                        company_user: {
                            ...user.company_user,
                            notifications: {
                                ...user.company_user?.notifications,
                                email: [],
                            },
                        },
                    });
                }
                const gateway = await updateCompanyGatewayRequirements(
                    api.context,
                    checkoutGateway,
                    { token_billing: 'optin' }
                );
                const { client, invoice } = await preparePortalPaymentContext(
                    api,
                    page,
                    gateway,
                    flow,
                    {
                        settings: {
                            payment_flow: flow,
                            client_online_payment_notification: false,
                            client_manual_payment_notification: false,
                            require_invoice_signature: false,
                            show_accept_invoice_terms: false,
                        },
                    }
                );
                const recurring = await createRecurringInvoice(api, client, {
                    autoBill,
                });
                attachInvoices(recurring, [invoice]);
                await navigateToPortalGatewayCheckout(
                    page,
                    gateway,
                    1,
                    flow,
                    invoice
                );
                await dismissCookieConsent(page);
                const publicKey = await page
                    .locator('meta[name="stripe-publishable-key"]')
                    .getAttribute('content');
                expect(publicKey?.startsWith('pk_test_')).toBe(true);
                await page
                    .locator('#cardholder-name')
                    .fill('Playwright Sandbox');
                const cardFrame = page
                    .frameLocator('#card-element iframe')
                    .first();
                await cardFrame
                    .locator('input[name="cardnumber"]')
                    .fill('4242424242424242');
                await cardFrame.locator('input[name="exp-date"]').fill('1229');
                await cardFrame.locator('input[name="cvc"]').fill('123');
                const postal = cardFrame.locator('input[name="postal"]');
                if (await postal.isVisible()) await postal.fill('90210');

                let signalRequested!: () => void;
                const requested = new Promise<void>((resolve) => {
                    signalRequested = resolve;
                });
                const requestGate = new Promise<void>((resolve) => {
                    releaseRequest = resolve;
                });
                let signalSaved!: () => void;
                const saved = new Promise<void>((resolve) => {
                    signalSaved = resolve;
                });
                const gate = new Promise<void>((resolve) => {
                    release = resolve;
                });
                await page.route('**/livewire/**', async (route) => {
                    const body = route.request().postDataJSON();
                    if (
                        !body.components?.some(
                            (component: { calls?: { method: string }[] }) =>
                                component.calls?.some(
                                    (call) => call.method === 'setAutoBilling'
                                )
                        )
                    ) {
                        await route.continue();
                        return;
                    }
                    signalRequested();
                    await requestGate;
                    const response = await route.fetch();
                    expect(response.ok()).toBe(true);
                    signalSaved();
                    // Simulate a slow response after the server has persisted consent.
                    await gate;
                    await route.fulfill({ response }).catch(() => {});
                });
                await page
                    .locator(
                        `input[name^="auto_bill_enabled_"][value="${selection ? 1 : 0}"]`
                    )
                    .check();
                await requested;
                await expect(
                    page.locator('input[name="token-billing-checkbox"]:checked')
                ).toHaveValue(String(selection));
                await expect(
                    page.locator('#save-payment-method--container')
                ).toBeVisible({ visible: !selection });
                await expect(page.locator('#pay-now')).toBeEnabled();
                releaseRequest();
                await saved;
                const savedAt = Date.now();
                const enabled = await getEntity<PortalEntity>(
                    api.context,
                    'recurring_invoices',
                    recurring.id
                );
                expect(enabled.auto_bill_enabled).toBe(selection);
                // Keep the Livewire response withheld until the real payment has completed.
                await expect(page.locator('#pay-now')).toBeEnabled();
                await expect(
                    page.locator('input[name="token-billing-checkbox"]:checked')
                ).toHaveValue(String(selection));

                const posted = page.waitForRequest(
                    (request) =>
                        request.url().includes('/payments/process/response') &&
                        request.method() === 'POST',
                    { timeout: 30_000 }
                );
                await page.locator('#pay-now').click();
                const payload = new URLSearchParams((await posted).postData()!);
                const paymentCompletedAfterMs = Date.now() - savedAt;
                const stripeResult = JSON.parse(
                    payload.get('gateway_response')!
                );
                expect(stripeResult.livemode).toBe(false);
                expect(stripeResult.status).toBe('succeeded');
                await expect
                    .poll(
                        async () =>
                            (
                                await getEntity<PortalEntity>(
                                    api.context,
                                    'invoices',
                                    invoice.id
                                )
                            ).balance,
                        { timeout: 30_000 }
                    )
                    .toBe(0);
                const storedTokens = Number(
                    runArtisan(
                        `echo \\App\\Models\\ClientGatewayToken::where('client_id', ${Number(decodePrimaryKey(client.id))})->where('company_gateway_id', ${Number(decodePrimaryKey(gateway.id))})->count();`
                    )
                );
                const evidence = {
                    flow,
                    autoBill,
                    paymentCompletedAfterMs,
                    stripePaymentIntent: stripeResult.id,
                    stripeStatus: stripeResult.status,
                    liveMode: stripeResult.livemode,
                    autoBillEnabled: enabled.auto_bill_enabled,
                    browserStoreCard: payload.get('store_card'),
                    submittedStoreCard: payload.get('store_card'),
                    invoiceBalance: 0,
                    storedTokens,
                };
                console.log(JSON.stringify(evidence));
                await testInfo.attach('sandbox-race-evidence', {
                    body: JSON.stringify(evidence, null, 2),
                    contentType: 'application/json',
                });
                expect(
                    payload.get('store_card'),
                    'Successful payment must respect the selected save-method preference'
                ).toBe(String(selection));
                expect(storedTokens).toBe(selection ? 1 : 0);
            } finally {
                releaseRequest();
                release();
                for (const user of users) {
                    await updateUser(api.context, user);
                }
            }
        });
    }
}
