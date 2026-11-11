import { execFileSync } from 'node:child_process';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { expect, type Page } from '@playwright/test';
import {
    getEntity,
    updateClient,
    type ApiEntity,
} from './api-helpers';
import {
    createAndLogInClient,
    dismissCookieConsent,
    fillLivewireInput,
} from './client-portal-helpers';
import { test } from './fixtures';
import { decodePrimaryKey } from './hash-helpers';
import {
    completeFinancialConnections,
    prepareStripeAchGateway,
    stripeGet,
    stripeList,
    validatedStripeTestSecret,
} from './gateways/stripe-ach-helpers';
import {
    completeRequiredClientInfoForm,
    navigateToGatewayCheckout,
} from './gateways/payment-flow-helpers';
import { GatewayType } from './gateways/types';
import { createSentInvoice } from './portal-entity-helpers';

const projectRoot = resolve(dirname(fileURLToPath(import.meta.url)), '../..');

interface ClientGatewayToken extends ApiEntity {
    id: string;
    token: string;
    gateway_customer_reference: string;
    gateway_type_id: number;
    meta: {
        state?: string;
        last4?: string;
        [key: string]: unknown;
    };
}

interface StripeSetupIntent {
    id: string;
    customer: string;
    mandate: string | null;
    payment_method: string;
    status: string;
}

interface StripePaymentIntent {
    id: string;
    customer: string;
    latest_charge: {
        payment_method_details: {
            us_bank_account: {
                mandate: string;
            };
        };
    } | null;
    payment_method: string;
    status: string;
}

interface StripePaymentMethod {
    id: string;
    billing_details: {
        address: {
            city: string | null;
            country: string | null;
            line1: string | null;
            line2: string | null;
            postal_code: string | null;
            state: string | null;
        };
    };
    customer: string;
    type: string;
}

interface StripeMandate {
    id: string;
    payment_method: string;
    status: string;
}

test.describe('Stripe ACH live sandbox', () => {
    test.describe.configure({ retries: 0 });

    let restoreStripeAchGateway: (() => Promise<void>) | undefined;

    test.afterEach(async () => {
        await restoreStripeAchGateway?.();
        restoreStripeAchGateway = undefined;
    });

    test('collects a new bank account during payment and persists the Stripe mandate', async ({
        api,
        page,
        notificationGuard,
    }) => {
        test.setTimeout(180_000);

        const stripeSecret = await validatedStripeTestSecret();

        if (!stripeSecret) {
            test.skip(
                true,
                'Set STRIPE_KEYS to a valid Stripe test-mode secret key to run live ACH tests.'
            );
            return;
        }

        await notificationGuard.suppressPaymentEmails();

        const { companyGateway, restore } = await prepareStripeAchGateway(
            api.context
        );
        restoreStripeAchGateway = restore;

        let client = await createAndLogInClient(api, page, {
            settings: {
                payment_flow: 'default',
                client_manual_payment_notification: false,
            },
        });

        client = await updateClient(api.context, client, {
            address1: '510 Townsend Street',
            city: 'San Francisco',
            state: 'CA',
            postal_code: '94103',
            country_id: '840',
            phone: '4155550100',
        });

        await createSentInvoice(api, client, {
            label: 'stripe-ach-new-bank-payment',
            cost: 43,
        });
        await navigateToGatewayCheckout(page, companyGateway, GatewayType.ACH);

        await expect(page.locator('#new-bank')).toBeVisible({
            timeout: 30_000,
        });
        await expect(page.locator('meta[name="address-1"]')).toHaveAttribute(
            'content',
            '510 Townsend Street'
        );

        const clientSecret = await page
            .locator('meta[name="client_secret"]')
            .getAttribute('content');
        const paymentIntentId = clientSecret?.split('_secret_')[0];
        expect(paymentIntentId).toMatch(/^pi_/);

        await page.locator('#accept-terms').check();
        await page.locator('#new-bank').click();
        await completeFinancialConnections(
            page,
            /\/client\/payments\/(?!process(?:\?|$)|response(?:\?|$))[^/?]+/
        );

        const paymentId = page.url().match(/\/client\/payments\/([^/?]+)/)?.[1];
        expect(paymentId).toBeTruthy();

        const payment = await getEntity<ApiEntity>(
            api.context,
            'payments',
            paymentId!
        );
        expect(payment.amount).toBe(43);
        expect(Number(payment.gateway_type_id)).toBe(GatewayType.ACH);
        expect(Number(payment.type_id)).toBe(4);
        expect(Number(payment.status_id)).toBe(1);
        expect(payment.transaction_reference).toBe(paymentIntentId);

        const paymentIntent = await stripeGet<StripePaymentIntent>(
            stripeSecret,
            `/v1/payment_intents/${paymentIntentId}`,
            {
                'expand[]': 'latest_charge',
            }
        );
        expect(['processing', 'succeeded']).toContain(paymentIntent.status);
        expect(paymentIntent.payment_method).toMatch(/^pm_/);

        const mandateId =
            paymentIntent.latest_charge?.payment_method_details.us_bank_account
                .mandate;
        expect(mandateId).toMatch(/^mandate_/);

        const mandate = await stripeGet<StripeMandate>(
            stripeSecret,
            `/v1/mandates/${mandateId}`
        );
        expect(mandate.status).toBe('active');
        expect(mandate.payment_method).toBe(paymentIntent.payment_method);

        const paymentMethod = await stripeGet<StripePaymentMethod>(
            stripeSecret,
            `/v1/payment_methods/${paymentIntent.payment_method}`
        );
        expect(paymentMethod.customer).toBe(paymentIntent.customer);
        expect(paymentMethod.billing_details.address).toMatchObject({
            line1: '510 Townsend Street',
            city: 'San Francisco',
            state: 'CA',
            postal_code: '94103',
            country: 'US',
        });
        expect(readLocalTokenState(paymentIntent.payment_method)).toBe(
            'authorized'
        );
    });

    test('collects a bank account, renews an inactive mandate, and completes payment', async ({
        api,
        page,
        notificationGuard,
    }) => {
        test.setTimeout(300_000);

        const stripeSecret = await validatedStripeTestSecret();

        if (!stripeSecret) {
            test.skip(
                true,
                'Set STRIPE_KEYS to a valid Stripe test-mode secret key to run live ACH tests.'
            );
            return;
        }

        await notificationGuard.suppressPaymentEmails();

        const { companyGateway, restore } = await prepareStripeAchGateway(
            api.context
        );
        restoreStripeAchGateway = restore;

        let client = await createAndLogInClient(api, page, {
            settings: {
                payment_flow: 'default',
                client_manual_payment_notification: false,
            },
        });

        client = await updateClient(api.context, client, {
            address1: 'Stale Billing Address',
            address2: 'Suite 100',
            city: 'Austin',
            state: 'TX',
            postal_code: '78701',
            country_id: '840',
            phone: '4155550100',
        });

        await page.goto('/client/payment_methods');
        await dismissCookieConsent(page);
        await page.locator('[data-cy="add-payment-method"]').click();
        await page.locator('[data-cy="add-bank-account-link"]').click();

        await expect(page).toHaveURL(/\/client\/payment_methods\/create/);
        await expect(
            page.locator('meta[name="stripe-client-secret"]')
        ).toHaveAttribute('content', /seti_.*_secret_/);
        await expect(page.locator('meta[name="address-1"]')).toHaveAttribute(
            'content',
            'Stale Billing Address'
        );
        await expect(page.locator('meta[name="address-2"]')).toHaveAttribute(
            'content',
            'Suite 100'
        );
        await expect(page.locator('meta[name="country"]')).toHaveAttribute(
            'content',
            'US'
        );

        await fillLivewireInput(
            page,
            '[name="client_address_line_1"]',
            '510 Townsend Street'
        );
        await fillLivewireInput(page, '[name="client_city"]', 'San Francisco');
        await fillLivewireInput(page, '[name="client_state"]', 'CA');
        await fillLivewireInput(page, '[name="client_postal_code"]', '94103');
        await completeRequiredClientInfoForm(page);
        await expect(page.locator('meta[name="address-1"]')).toHaveAttribute(
            'content',
            '510 Townsend Street'
        );
        await expect(page.locator('meta[name="city"]')).toHaveAttribute(
            'content',
            'San Francisco'
        );
        await expect(page.locator('meta[name="state"]')).toHaveAttribute(
            'content',
            'CA'
        );
        await expect(page.locator('meta[name="postal_code"]')).toHaveAttribute(
            'content',
            '94103'
        );
        await page.locator('#accept-terms').check();
        await page.locator('#save-button').click();
        await completeFinancialConnections(page);

        await expect(page).toHaveURL(
            /\/client\/payment_methods\/(?!create(?:\?|$))[^/?]+/,
            {
                timeout: 60_000,
            }
        );

        const tokenId = page
            .url()
            .match(/\/client\/payment_methods\/([^/?]+)/)?.[1];
        expect(tokenId).toBeTruthy();

        const token = await getEntity<ClientGatewayToken>(
            api.context,
            'client_gateway_tokens',
            tokenId!
        );

        expect(token.token).toMatch(/^pm_/);
        expect(Number(token.gateway_type_id)).toBe(GatewayType.ACH);
        expect(readLocalTokenState(token.token)).toBe('authorized');

        const paymentMethod = await stripeGet<StripePaymentMethod>(
            stripeSecret,
            `/v1/payment_methods/${token.token}`
        );

        expect(paymentMethod.type).toBe('us_bank_account');
        expect(paymentMethod.customer).toBe(token.gateway_customer_reference);
        expect(paymentMethod.billing_details.address).toMatchObject({
            line1: '510 Townsend Street',
            line2: 'Suite 100',
            city: 'San Francisco',
            state: 'CA',
            postal_code: '94103',
            country: 'US',
        });

        const setupIntentsBeforeRenewal = await stripeList<StripeSetupIntent>(
            stripeSecret,
            '/v1/setup_intents',
            { customer: token.gateway_customer_reference, limit: '20' }
        );
        const originalSetupIntent = setupIntentsBeforeRenewal.find(
            (intent) => intent.payment_method === token.token
        );

        expect(originalSetupIntent).toBeDefined();
        expect(originalSetupIntent?.status).toBe('succeeded');
        expect(originalSetupIntent?.mandate).toMatch(/^mandate_/);

        const originalMandate = await stripeGet<StripeMandate>(
            stripeSecret,
            `/v1/mandates/${originalSetupIntent?.mandate}`
        );

        expect(originalMandate.status).toBe('active');
        expect(originalMandate.payment_method).toBe(token.token);

        client = await updateClient(api.context, client, {
            address1: '500 Pike Street',
            address2: 'Floor 3',
            city: 'Seattle',
            state: 'WA',
            postal_code: '98101',
            country_id: '840',
        });

        setLocalTokenState(token.token, 'inactive');

        await page.reload();
        await expect(
            page.getByText('You must consent to ACH transactions.')
        ).toBeVisible();
        await page.getByRole('link', { name: 'Complete Verification' }).click();
        await expect(page).toHaveURL(
            /\/client\/payment_methods\/[^/?]+\/verification/
        );
        await completeRequiredClientInfoForm(page);

        const standaloneClientSecret = await page
            .locator('meta[name="stripe-client-secret"]')
            .getAttribute('content');
        const standaloneSetupIntentId =
            standaloneClientSecret?.split('_secret_')[0];
        expect(standaloneSetupIntentId).toMatch(/^seti_/);

        await page.locator('#accept-terms').check();
        await page.locator('#authorize-button').click();
        await expect(page).toHaveURL(
            /\/client\/payment_methods\/(?!create(?:\?|$))[^/?]+$/,
            {
                timeout: 60_000,
            }
        );
        expect(readLocalTokenState(token.token)).toBe('authorized');

        const standaloneSetupIntent = await stripeGet<StripeSetupIntent>(
            stripeSecret,
            `/v1/setup_intents/${standaloneSetupIntentId}`
        );
        expect(standaloneSetupIntent.status).toBe('succeeded');
        expect(standaloneSetupIntent.payment_method).toBe(token.token);
        expect(standaloneSetupIntent.mandate).toMatch(/^mandate_/);

        let mutatedPaymentMethod = await stripeGet<StripePaymentMethod>(
            stripeSecret,
            `/v1/payment_methods/${token.token}`
        );
        expect(mutatedPaymentMethod.billing_details.address).toMatchObject({
            line1: '500 Pike Street',
            line2: 'Floor 3',
            city: 'Seattle',
            state: 'WA',
            postal_code: '98101',
            country: 'US',
        });

        setLocalTokenState(token.token, 'inactive');

        const renewalInvoice = await createSentInvoice(api, client, {
            label: 'stripe-ach-mandate-renewal',
            cost: 42,
        });

        await navigateToGatewayCheckout(page, companyGateway, GatewayType.ACH);

        const storedBank = page.locator(
            `input[name="payment-type"][data-payment-method="${token.token}"]`
        );
        await expect(storedBank).toBeVisible({ timeout: 30_000 });
        await expect(storedBank).toHaveAttribute('data-state', 'inactive');
        await storedBank.check();
        await expect(page.locator('#mandate-authorization')).toBeVisible();
        await page.locator('#accept-mandate').check();

        const mandateClientSecret = await page
            .locator('meta[name="mandate_client_secret"]')
            .getAttribute('content');
        let renewalSetupIntentId = mandateClientSecret?.split('_secret_')[0];
        expect(renewalSetupIntentId).toMatch(/^seti_/);

        await submitPaymentFormWithSetupIntent(page, originalSetupIntent!.id);
        expect(readLocalTokenState(token.token)).toBe('inactive');

        const invoiceAfterReplay = await getEntity<ApiEntity>(
            api.context,
            'invoices',
            String(renewalInvoice.id)
        );
        expect(Number(invoiceAfterReplay.status_id)).toBe(2);

        await navigateToGatewayCheckout(page, companyGateway, GatewayType.ACH);
        const freshStoredBank = page.locator(
            `input[name="payment-type"][data-payment-method="${token.token}"]`
        );
        await expect(freshStoredBank).toHaveAttribute('data-state', 'inactive');
        await freshStoredBank.check();
        await page.locator('#accept-mandate').check();
        renewalSetupIntentId = (
            await page
                .locator('meta[name="mandate_client_secret"]')
                .getAttribute('content')
        )?.split('_secret_')[0];
        expect(renewalSetupIntentId).toMatch(/^seti_/);

        client = await updateClient(api.context, client, {
            address1: '1 Embarcadero Center',
            address2: 'Floor 8',
            city: 'San Francisco',
            state: 'CA',
            postal_code: '94111',
            country_id: '840',
        });

        await page.locator('#pay-now').click();

        await expect(page).toHaveURL(
            /\/client\/payments\/(?!process(?:\?|$)|response(?:\?|$))[^/?]+/,
            {
                timeout: 60_000,
            }
        );

        const paymentId = page.url().match(/\/client\/payments\/([^/?]+)/)?.[1];
        expect(paymentId).toBeTruthy();

        const payment = await getEntity<ApiEntity>(
            api.context,
            'payments',
            paymentId!
        );
        expect(payment.amount).toBe(42);
        expect(Number(payment.gateway_type_id)).toBe(GatewayType.ACH);
        expect(Number(payment.type_id)).toBe(4);
        expect(Number(payment.status_id)).toBe(1);
        expect(payment.transaction_reference).toMatch(/^py_/);

        expect(readLocalTokenState(token.token)).toBe('authorized');

        const renewedSetupIntent = await stripeGet<StripeSetupIntent>(
            stripeSecret,
            `/v1/setup_intents/${renewalSetupIntentId}`
        );
        expect(renewedSetupIntent.status).toBe('succeeded');
        expect(renewedSetupIntent.customer).toBe(
            token.gateway_customer_reference
        );
        expect(renewedSetupIntent.payment_method).toBe(token.token);
        expect(renewedSetupIntent.mandate).toMatch(/^mandate_/);
        expect(renewedSetupIntent.mandate).not.toBe(
            originalSetupIntent?.mandate
        );
        expect(renewedSetupIntent.mandate).not.toBe(
            standaloneSetupIntent.mandate
        );

        const renewedMandate = await stripeGet<StripeMandate>(
            stripeSecret,
            `/v1/mandates/${renewedSetupIntent.mandate}`
        );
        expect(renewedMandate.status).toBe('active');
        expect(renewedMandate.payment_method).toBe(token.token);

        const paymentIntents = await stripeList<StripePaymentIntent>(
            stripeSecret,
            '/v1/payment_intents',
            {
                customer: token.gateway_customer_reference,
                limit: '20',
                'expand[]': 'data.latest_charge',
            }
        );
        const paidIntent = paymentIntents.find(
            (intent) =>
                intent.payment_method === token.token &&
                intent.latest_charge?.payment_method_details.us_bank_account
                    .mandate === renewedSetupIntent.mandate
        );

        expect(paidIntent).toBeDefined();
        expect(['processing', 'succeeded']).toContain(paidIntent?.status);

        mutatedPaymentMethod = await stripeGet<StripePaymentMethod>(
            stripeSecret,
            `/v1/payment_methods/${token.token}`
        );
        expect(mutatedPaymentMethod.billing_details.address).toMatchObject({
            line1: '1 Embarcadero Center',
            line2: 'Floor 8',
            city: 'San Francisco',
            state: 'CA',
            postal_code: '94111',
            country: 'US',
        });

        const setupIntentsAfterRenewal = await stripeList<StripeSetupIntent>(
            stripeSecret,
            '/v1/setup_intents',
            { customer: token.gateway_customer_reference, limit: '20' }
        );

        await createSentInvoice(api, client, {
            label: 'stripe-ach-authorized-address-refresh',
            cost: 41,
        });
        await navigateToGatewayCheckout(page, companyGateway, GatewayType.ACH);

        const authorizedBank = page.locator(
            `input[name="payment-type"][data-payment-method="${token.token}"]`
        );
        await expect(authorizedBank).toHaveAttribute(
            'data-state',
            'authorized'
        );
        await authorizedBank.check();
        await expect(page.locator('#mandate-authorization')).toBeHidden();

        client = await updateClient(api.context, client, {
            address1: '1 Congress Street',
            address2: '',
            city: 'Boston',
            state: 'MA',
            postal_code: '02114',
            country_id: '840',
        });

        await page.locator('#pay-now').click();
        await expect(page).toHaveURL(
            /\/client\/payments\/(?!process(?:\?|$)|response(?:\?|$))[^/?]+/,
            { timeout: 60_000 }
        );

        expect(readLocalTokenState(token.token)).toBe('authorized');

        mutatedPaymentMethod = await stripeGet<StripePaymentMethod>(
            stripeSecret,
            `/v1/payment_methods/${token.token}`
        );
        expect(mutatedPaymentMethod.billing_details.address).toMatchObject({
            line1: '1 Congress Street',
            city: 'Boston',
            state: 'MA',
            postal_code: '02114',
            country: 'US',
        });
        expect(mutatedPaymentMethod.billing_details.address.line2).toBeNull();

        const setupIntentsAfterAuthorizedPayment =
            await stripeList<StripeSetupIntent>(
                stripeSecret,
                '/v1/setup_intents',
                { customer: token.gateway_customer_reference, limit: '20' }
            );
        expect(setupIntentsAfterAuthorizedPayment).toHaveLength(
            setupIntentsAfterRenewal.length
        );

        const autoBillInvoice = await createSentInvoice(api, client, {
            label: 'stripe-ach-auto-bill-eligibility',
            cost: 39,
        });
        expect(
            readEligibleAutoBillToken(
                decodePrimaryKey(String(autoBillInvoice.id)),
                39
            )
        ).toBe(token.token);

        setLocalTokenState(token.token, 'inactive');
        expect(
            readEligibleAutoBillToken(
                decodePrimaryKey(String(autoBillInvoice.id)),
                39
            )
        ).toBe('');

        const invoiceAfterInactiveFilter = await getEntity<ApiEntity>(
            api.context,
            'invoices',
            String(autoBillInvoice.id)
        );
        expect(Number(invoiceAfterInactiveFilter.status_id)).toBe(2);
    });
});

async function submitPaymentFormWithSetupIntent(
    page: Page,
    setupIntentId: string
) {
    const submission = await page
        .locator('#server-response')
        .evaluate((form) => {
            const htmlForm = form as HTMLFormElement;

            return {
                action: htmlForm.action,
                fields: Object.fromEntries(new FormData(htmlForm).entries()),
            };
        });

    return page.request.post(submission.action, {
        maxRedirects: 0,
        form: {
            ...submission.fields,
            setup_intent_id: setupIntentId,
        },
    });
}

function readLocalTokenState(paymentMethodId: string): string {
    return runArtisan(
        '\\App\\Libraries\\MultiDB::setDb("db-ninja-01");' +
            `$token = \\App\\Models\\ClientGatewayToken::on("db-ninja-01")->where("token", ${JSON.stringify(paymentMethodId)})->firstOrFail();` +
            'echo $token->meta->state ?? "";'
    );
}

function setLocalTokenState(paymentMethodId: string, state: string): void {
    const result = runArtisan(
        '\\App\\Libraries\\MultiDB::setDb("db-ninja-01");' +
            `$token = \\App\\Models\\ClientGatewayToken::on("db-ninja-01")->where("token", ${JSON.stringify(paymentMethodId)})->firstOrFail();` +
            '$meta = $token->meta;' +
            `$meta->state = ${JSON.stringify(state)};` +
            '$token->meta = $meta;' +
            '$token->save();' +
            'echo $token->fresh()->meta->state;'
    );

    expect(result).toBe(state);
}

function readEligibleAutoBillToken(invoiceId: string, amount: number): string {
    return runArtisan(
        '\\App\\Libraries\\MultiDB::setDb("db-ninja-01");' +
            `$invoice = \\App\\Models\\Invoice::on("db-ninja-01")->findOrFail(${JSON.stringify(invoiceId)});` +
            '$service = new \\App\\Services\\Invoice\\AutoBillInvoice($invoice, "db-ninja-01");' +
            `$token = $service->getGateway(${JSON.stringify(amount)});` +
            'echo $token ? $token->token : "";'
    );
}

function runArtisan(phpCode: string): string {
    return execFileSync('php', ['artisan', 'tinker', '--execute', phpCode], {
        cwd: projectRoot,
        encoding: 'utf8',
        env: process.env,
    }).trim();
}
