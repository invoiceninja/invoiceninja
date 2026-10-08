import { type Locator, type Page } from '@playwright/test';
import { expect, test as base, uniqueName } from './fixtures';
import { getEntity, getUsers, updateClient, updateUser } from './api-helpers';
import { createAndLogInClient, waitForAlpine } from './client-portal-helpers';
import { createSentInvoice, createSentQuote, type PortalEntity } from './portal-entity-helpers';
import { disableNativeDialogSupport } from './client-portal-mobile-compatibility';
import { StripePaymentGateway } from './gateways/stripe-payment-gateway';
import { defaultClientAddress, expectSmoothPaymentStep, paymentTestSettings, selectSmoothPaymentMethod } from './gateways/payment-flow-helpers';
import { GatewayType } from './gateways/types';

// Real submissions against disposable fixtures and Stripe test mode. No SDK mocks.
const test = base.extend<{ quietNotifications: void }>({
    quietNotifications: [async ({ api }, use) => {
        const users = await getUsers(api.context);
        try {
            for (const user of users) {
                await updateUser(api.context, { ...user, company_user: {
                    ...user.company_user,
                    notifications: { ...user.company_user?.notifications, email: [] },
                } });
            }
            await use();
        } finally {
            for (const user of users) await updateUser(api.context, user);
        }
    }, { auto: true }],
});
test.use({ viewport: { width: 390, height: 844 }, hasTouch: true, actionTimeout: 15_000 });
test.describe.configure({ timeout: 120_000 });
test.beforeEach(async ({ page }) => {
    await page.addInitScript(() => {
        document.addEventListener('DOMContentLoaded', () => {
            const style = document.createElement('style');
            style.textContent = '.phpdebugbar { display: none !important; }';
            document.head.append(style);
        });
    });
    if (process.env.PLAYWRIGHT_LEGACY_DIALOGS) await disableNativeDialogSupport(page);
});

async function tap(control: Locator) {
    await expect(control).toBeVisible();
    await expect(control).toBeEnabled();
    await control.tap();
}

async function sign(page: Page) {
    const canvas = page.locator('#signature-pad');
    await expect(canvas).toBeVisible();
    const box = (await canvas.boundingBox())!;
    await page.touchscreen.tap(box.x + 30, box.y + 40);
    await page.touchscreen.tap(box.x + 80, box.y + 60);
}

for (const decision of ['approve', 'reject']) {
    test(`submits quote ${decision} and verifies the persisted result`, async ({ api, page }, testInfo) => {
        const client = await createAndLogInClient(api, page, { settings: {
            require_quote_signature: true, show_accept_quote_terms: true,
            accept_client_input_quote_approval: true,
        } });
        const quote = await createSentQuote(api, client, { terms: 'Review and approve these test terms.' });
        const posts: string[] = [];
        page.on('request', request => {
            if (request.method() === 'POST' && /\/client\/quotes\/(approve|reject)/.test(request.url())) posts.push(request.url());
        });
        await page.goto(`/client/quotes/${quote.id}`);
        await waitForAlpine(page);
        await tap(page.locator(`#${decision}-button`));
        await expect(page.locator(`#${decision === 'approve' ? 'reject' : 'approve'}-button`)).toBeDisabled();
        if (decision === 'approve') {
            await page.locator('#user_input').fill('MOBILE-SUBMISSION-PO');
            await tap(page.locator('#input-next-step'));
            await sign(page);
            await tap(page.locator('#signature-next-step'));
            await tap(page.locator('#accept-terms-button'));
            await expect(page.getByRole('heading', { name: 'Approved', exact: true })).toBeVisible({ timeout: 30_000 });
        } else {
            await page.locator('#reject_reason').fill('Mobile submission validation');
            await tap(page.locator('#reject-confirm-button'));
            await expect(page).toHaveURL(/\/client\/quotes\/?$/);
        }
        const updated = await getEntity<PortalEntity>(api.context, 'quotes', quote.id);
        expect(Number(updated.status_id)).toBe(decision === 'approve' ? 3 : 5);
        if (decision === 'approve') expect(updated.po_number).toBe('MOBILE-SUBMISSION-PO');
        expect(posts).toHaveLength(1);
        await testInfo.attach('quote-result', { body: JSON.stringify({ quoteId: quote.id, status: updated.status_id, submissions: posts.length }), contentType: 'application/json' });
    });
}

async function authenticate(page: Page, succeed: boolean) {
    const name = succeed ? /^complete(?: authentication)?$/i : /^fail(?: authentication)?$/i;
    let control: Locator | undefined;
    await expect.poll(async () => {
        for (const frame of page.frames()) {
            const candidate = frame.getByRole('button', { name });
            if (await candidate.isVisible().catch(() => false)) {
                control = candidate;
                return true;
            }
        }
        return false;
    }, { timeout: 30_000, message: 'Stripe must display its real test authentication challenge' }).toBe(true);
    await tap(control!);
}

for (const flow of ['default', 'smooth'] as const) {
    for (const authentication of [false, true]) {
        test(`Stripe ${flow}: ${authentication ? 'failed 3DS authentication then successful retry' : 'successful card payment'}`, async ({ api, page }, testInfo) => {
            const keys = JSON.parse(process.env.STRIPE_KEYS || '{}');
            expect(keys.publishableKey?.startsWith('pk_test_'), 'Require Stripe test credentials').toBe(true);
            expect(keys.apiKey?.startsWith('sk_test_'), 'Require Stripe test credentials').toBe(true);
            const stripe = new StripePaymentGateway();
            const providerFailures: { host: string; error: string }[] = [];
            page.on('requestfailed', request => {
                const host = new URL(request.url()).hostname;
                if (host.endsWith('.stripe.com')) providerFailures.push({ host, error: request.failure()?.errorText || 'Request failed' });
            });
            try {
                const { availability } = await stripe.setupExclusiveTestEnvironment(api.context);
                expect(availability.companyGatewayConfigured, availability.skipReason).toBe(true);
                const gateway = availability.companyGateway!;
                let client = await createAndLogInClient(api, page, { settings: {
                    ...paymentTestSettings, payment_flow: flow,
                    require_invoice_signature: true, show_accept_invoice_terms: true,
                } });
                client = await updateClient(api.context, client, { ...defaultClientAddress,
                    contacts: client.contacts.map(contact => ({ ...contact, send_email: false })),
                });
                const invoice = await createSentInvoice(api, client, { label: uniqueName('mobile-payment'), cost: 42, terms: 'Sandbox payment terms.' });
                await page.goto(`/client/invoices/${invoice.id}`);
                await waitForAlpine(page);
                if (flow === 'default') {
                    await tap(page.locator('[dusk="pay-now-dropdown"]'));
                    await tap(page.locator(`[data-gateway-key="${stripe.gatewayKey}"][data-gateway-type-id="1"]`));
                    await sign(page);
                    await tap(page.locator('#signature-next-step'));
                    await tap(page.locator('#accept-terms-button'));
                } else {
                    await tap(page.locator('#accept-terms-button'));
                    await sign(page);
                    await tap(page.locator('#save-button'));
                    await expectSmoothPaymentStep(page);
                    await selectSmoothPaymentMethod(page, gateway, GatewayType.CREDIT_CARD, 'Credit Card');
                }
                if (flow === 'default') await stripe.assertCheckoutReady(page);
                await waitForAlpine(page);
                const requiredDetails = page.locator('#required-client-info-form');
                if (await requiredDetails.isVisible()) {
                    await tap(requiredDetails.locator('button.button-primary'));
                    await expect(page.locator('[data-ref="required-fields-container"]')).toBeHidden();
                    if (flow === 'smooth') await selectSmoothPaymentMethod(page, gateway, GatewayType.CREDIT_CARD, 'Credit Card');
                }
                if (await page.locator('[data-ref="gateway-container"]').count()) {
                    await expect(page.locator('[data-ref="gateway-container"]')).not.toHaveClass(/pointer-events-none/);
                }
                await stripe.assertCheckoutReady(page);
                await expect(page.locator('meta[name="stripe-publishable-key"]')).toHaveAttribute('content', /^pk_test_/);
                await page.locator('#cardholder-name').fill('Mobile Sandbox');
                const card = page.frameLocator('#card-element iframe').first();
                await card.locator('[name="cardnumber"]').fill(authentication ? '4000000000003220' : '4242424242424242');
                await card.locator('[name="exp-date"]').fill('1230');
                await card.locator('[name="cvc"]').fill('123');
                const postal = card.locator('[name="postal"]');
                if (await postal.isVisible()) await postal.fill('90210');
                let submissions = 0;
                page.on('request', request => {
                    if (request.method() === 'POST' && request.url().includes('/payments/process/response')) submissions++;
                });
                if (authentication) {
                    await tap(page.locator('#pay-now'));
                    await authenticate(page, false);
                    await expect(page.locator('#errors')).toBeVisible();
                    await expect(page.locator('#pay-now')).toBeEnabled();
                    expect((await getEntity<PortalEntity>(api.context, 'invoices', invoice.id)).balance).toBeGreaterThan(0);
                    expect(submissions).toBe(0);
                }
                const [posted] = await Promise.all([
                    page.waitForRequest(request => request.method() === 'POST' && request.url().includes('/payments/process/response'), { timeout: 60_000 }),
                    (async () => {
                        await tap(page.locator('#pay-now'));
                        if (authentication) await authenticate(page, true);
                    })(),
                ]);
                const payload = new URLSearchParams(posted.postData()!);
                const intent = JSON.parse(payload.get('gateway_response')!);
                expect(intent.livemode).toBe(false);
                expect(intent.status).toBe('succeeded');
                await stripe.assertPaymentSucceeded(page);
                await expect.poll(async () => (await getEntity<PortalEntity>(api.context, 'invoices', invoice.id)).balance, { timeout: 30_000 }).toBe(0);
                const paymentId = new URL(page.url()).pathname.split('/').pop()!;
                api.trackEntity('payments', paymentId);
                const payment = await getEntity(api.context, 'payments', paymentId);
                expect(Number(payment.status_id)).toBe(4);
                expect(submissions).toBe(1);
                await testInfo.attach('payment-result', { body: JSON.stringify({ flow, authentication, invoiceId: invoice.id, paymentId, intentId: intent.id, liveMode: intent.livemode, status: intent.status, balance: 0, submissions }), contentType: 'application/json' });
            } finally {
                if (providerFailures.length) await testInfo.attach('provider-network-failures', { body: JSON.stringify(providerFailures), contentType: 'application/json' });
                await stripe.restoreExclusiveGateway();
            }
        });
    }
}
