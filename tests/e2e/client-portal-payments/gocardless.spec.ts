import type { Page } from '@playwright/test';
import { expect, test } from '../fixtures';
import {
    ensureCompanyGatewayTypeEnabled,
    type CompanyGatewayEntity,
} from '../api-helpers';
import type { PortalEntity } from '../portal-entity-helpers';
import { runArtisan } from '../artisan-helpers';
import { dismissCookieConsent } from '../client-portal-helpers';
import {
    navigateToPortalGatewayCheckout,
    openInvoicePaymentPage,
    openSmoothInvoicePaymentPage,
    preparePortalPaymentContext,
    completeRequiredClientInfoForm,
} from '../gateways/payment-flow-helpers';
import {
    GoCardlessPaymentGateway,
} from '../gateways/gocardless-payment-gateway';
import { type GatewayExclusiveSetupOptions } from '../gateways/base-payment-gateway';
import {
    GatewayType,
    PaymentType,
    type GatewayAvailability,
} from '../gateways/types';
import { decodePrimaryKey } from '../hash-helpers';

const goCardless = new GoCardlessPaymentGateway();
const goCardlessSandboxUpgrade = {
    autogiro:
        'GOCARDLESS_SANDBOX_UPGRADE: enable Verified Mandates and Autogiro BankID on the sandbox organisation',
    verifiedMandates:
        'GOCARDLESS_SANDBOX_UPGRADE: enable Protect+ Verified Mandates on the sandbox organisation',
} as const;

const directDebitMethods = [
    {
        name: 'ACH Direct Debit',
        gatewayTypeId: GatewayType.ACH,
        paymentTypeId: PaymentType.ACH,
        scheme: 'ach',
        countryCode: 'US',
        clientChanges: clientLocation('840', '1', 'New York', 'NY', '10001'),
    },
    {
        name: 'Bacs Direct Debit',
        gatewayTypeId: GatewayType.DIRECT_DEBIT,
        paymentTypeId: PaymentType.BACS,
        scheme: 'bacs',
        countryCode: 'GB',
        clientChanges: clientLocation('826', '2', 'London', '', 'SW1A 1AA'),
    },
    {
        name: 'BECS Direct Debit (Australia)',
        gatewayTypeId: GatewayType.DIRECT_DEBIT,
        paymentTypeId: PaymentType.BECS,
        scheme: 'becs',
        countryCode: 'AU',
        clientChanges: clientLocation('36', '12', 'Sydney', 'NSW', '2000'),
    },
    {
        name: 'BECS Direct Debit (New Zealand)',
        gatewayTypeId: GatewayType.DIRECT_DEBIT,
        paymentTypeId: PaymentType.DIRECT_DEBIT,
        scheme: 'becs_nz',
        countryCode: 'NZ',
        clientChanges: clientLocation('554', '15', 'Auckland', '', '1010'),
    },
    {
        name: 'Pre-Authorized Debit (Canada)',
        gatewayTypeId: GatewayType.DIRECT_DEBIT,
        paymentTypeId: PaymentType.ACSS,
        scheme: 'pad',
        countryCode: 'CA',
        clientChanges: clientLocation('124', '9', 'Toronto', 'ON', 'M5V 2T6'),
    },
    {
        name: 'Betalingsservice',
        gatewayTypeId: GatewayType.DIRECT_DEBIT,
        paymentTypeId: PaymentType.DIRECT_DEBIT,
        scheme: 'betalingsservice',
        countryCode: 'DK',
        clientChanges: clientLocation('208', '5', 'Copenhagen', '', '2100'),
    },
    {
        name: 'Autogiro',
        gatewayTypeId: GatewayType.DIRECT_DEBIT,
        paymentTypeId: PaymentType.DIRECT_DEBIT,
        scheme: 'autogiro',
        countryCode: 'SE',
        sandboxUpgrade: goCardlessSandboxUpgrade.autogiro,
        clientChanges: clientLocation('752', '7', 'Stockholm', '', '111 22'),
    },
    {
        name: 'SEPA Direct Debit',
        gatewayTypeId: GatewayType.SEPA,
        paymentTypeId: PaymentType.SEPA,
        scheme: 'sepa_core',
        countryCode: 'DE',
        clientChanges: clientLocation('276', '3', 'Berlin', '', '10115'),
    },
] as const;

const instantBankPaymentMethods = [
    {
        name: 'Faster Payments',
        countryCode: 'GB',
        clientChanges: clientLocation('826', '2', 'London', '', 'SW1A 1AA'),
    },
    {
        name: 'SEPA Credit Transfer',
        countryCode: 'DE',
        clientChanges: clientLocation('276', '3', 'Berlin', '', '10115'),
    },
    {
        name: 'SEPA Credit Transfer (France)',
        countryCode: 'FR',
        clientChanges: clientLocation('250', '3', 'Paris', '', '75001'),
    },
    {
        name: 'SEPA Credit Transfer (Ireland)',
        countryCode: 'IE',
        clientChanges: clientLocation('372', '3', 'Dublin', '', 'D02 X285'),
    },
] as const;

test.describe.configure({ timeout: 180_000 });

test.afterEach(async () => {
    await goCardless.restoreExclusiveGateway();
});

for (const paymentFlow of ['default', 'smooth'] as const) {
    test(`completes a new-account sandbox payment (${paymentFlow} flow)`, async ({
        api,
        page,
        notificationGuard,
    }) => {
        const availability = await prepareGoCardless(api);
        await notificationGuard.suppressPaymentEmails();
        const context = await preparePortalPaymentContext(
            api,
            page,
            availability.companyGateway!,
            paymentFlow
        );

        await navigateToPortalGatewayCheckout(
            page,
            context.companyGateway,
            goCardless.gatewayTypeId,
            paymentFlow,
            context.invoice
        );
        await completeRequiredClientInfoForm(page);
        await goCardless.assertCheckoutReady(page);
        await goCardless.completePayment(page);
        await goCardless.assertPaymentSucceeded(page);

        const tokens = clientMandates(
            context.client.id,
            context.companyGateway.id,
            goCardless.gatewayTypeId
        );

        expect(tokens).toHaveLength(1);
        expect(tokens[0]).toMatch(/^MD/);
    });
}

for (const method of directDebitMethods) {
    for (const paymentFlow of ['default', 'smooth'] as const) {
        test(`handles a ${method.name} sandbox payment (${paymentFlow} flow)`, async ({
            api,
            page,
            notificationGuard,
        }) => {
            if ('sandboxUpgrade' in method) {
                test.skip(true, method.sandboxUpgrade);
            }

            const availability = await prepareGoCardless(api, {
                gatewayTypeId: method.gatewayTypeId,
            });
            await notificationGuard.suppressPaymentEmails();
            const context = await preparePortalPaymentContext(
                api,
                page,
                availability.companyGateway!,
                paymentFlow,
                method.clientChanges
            );

            await navigateToPortalGatewayCheckout(
                page,
                context.companyGateway,
                method.gatewayTypeId,
                paymentFlow,
                context.invoice
            );
            await completeRequiredClientInfoForm(page);
            await goCardless.assertCheckoutReady(page);
            await goCardless.completePayment(page, method.countryCode);
            await goCardless.assertPaymentSucceeded(page);

            const payment = goCardlessPayment(
                context.invoice.id,
                context.companyGateway.id
            );

            expect(payment.scheme).toBe(method.scheme);
            expect(payment).toMatchObject({
                gateway_type_id: method.gatewayTypeId,
                type_id: method.paymentTypeId,
            });
            expect([1, 4]).toContain(payment.status_id);
        });
    }
}

for (const method of directDebitMethods) {
    for (const paymentFlow of ['default', 'smooth'] as const) {
        test(`creates and pays with a stored ${method.name} mandate (${paymentFlow} flow)`, async ({
            api,
            page,
            notificationGuard,
        }) => {
            if ('sandboxUpgrade' in method) {
                test.skip(true, method.sandboxUpgrade);
            }

            const availability = await prepareGoCardless(api, {
                gatewayTypeId: method.gatewayTypeId,
            });
            await notificationGuard.suppressPaymentEmails();
            const context = await preparePortalPaymentContext(
                api,
                page,
                availability.companyGateway!,
                paymentFlow,
                method.clientChanges
            );
            const mandate = await addMandateFromPaymentMethods(
                page,
                context.client.id,
                context.companyGateway.id,
                method.gatewayTypeId
            );

            await navigateToPortalGatewayCheckout(
                page,
                context.companyGateway,
                method.gatewayTypeId,
                paymentFlow,
                context.invoice
            );
            await completeRequiredClientInfoForm(page);

            const storedAccount = page.locator(
                `.toggle-payment-with-token[data-token="${mandate}"]`
            );
            await expect(storedAccount).toBeVisible();
            await storedAccount.check();
            await dismissCookieConsent(page);
            await page.locator('#gocardless-payment-action #pay-now').click();
            await goCardless.assertPaymentSucceeded(page);

            const payment = goCardlessPayment(
                context.invoice.id,
                context.companyGateway.id
            );

            expect(payment).toMatchObject({
                gateway_type_id: method.gatewayTypeId,
                type_id: method.paymentTypeId,
                scheme: method.scheme,
                mandate,
            });
            expect([1, 4]).toContain(payment.status_id);
        });
    }
}

for (const method of instantBankPaymentMethods) {
    for (const paymentFlow of ['default', 'smooth'] as const) {
        test(`offers ${method.name} through Instant Bank Pay (${paymentFlow} flow)`, async ({
            api,
            page,
        }) => {
            const availability = await prepareGoCardless(api, {
                gatewayTypeId: GatewayType.INSTANT_BANK_PAY,
            });
            const context = await preparePortalPaymentContext(
                api,
                page,
                availability.companyGateway!,
                paymentFlow,
                method.clientChanges
            );

            await navigateToInstantBankPay(
                page,
                context.companyGateway,
                paymentFlow,
                context.invoice
            );
            await goCardless.completeHostedInstantBankPayment(
                page,
                method.countryCode
            );
            await expect
                .poll(
                    () =>
                        completeFulfilledBillingRequest(
                            context.invoice.id,
                            context.companyGateway.id
                        ),
                    { timeout: 60_000 }
                )
                .toBe(true);

            const payment = goCardlessPayment(
                context.invoice.id,
                context.companyGateway.id
            );

            expect(payment).toMatchObject({
                gateway_type_id: GatewayType.INSTANT_BANK_PAY,
                type_id: PaymentType.INSTANT_BANK_PAY,
            });
            expect([1, 4]).toContain(payment.status_id);
        });
    }
}

test('adds a mandate and offers both the stored and new account choices', async ({
    api,
    page,
}) => {
    const availability = await prepareGoCardless(api);
    const context = await preparePortalPaymentContext(
        api,
        page,
        availability.companyGateway!,
        'default'
    );

    await page.goto('/client/payment_methods');
    await dismissCookieConsent(page);
    await page.locator('[data-cy="add-payment-method"]').click();
    await page.locator('[data-cy="add-bank-account-link"]').click();
    await goCardless.completeHostedDirectDebit(page, 'US');
    await page.waitForURL(
        (url) =>
            /\/client\/payment_methods\/[^/]+$/.test(url.pathname) &&
            !url.pathname.endsWith('/create'),
        { timeout: 60_000 }
    );

    await navigateToPortalGatewayCheckout(
        page,
        context.companyGateway,
        goCardless.gatewayTypeId,
        'default',
        context.invoice
    );
    await completeRequiredClientInfoForm(page);

    const storedAccount = page.locator('.toggle-payment-with-token').first();
    const newAccount = page.locator(
        '#toggle-payment-with-new-gocardless-account'
    );
    await expect(storedAccount).toBeVisible();
    await expect(newAccount).toBeVisible();
    const mandate = await storedAccount.getAttribute('data-token');
    expect(mandate).not.toBeNull();
    await dismissCookieConsent(page);

    await newAccount.check();
    await expect(page.locator('input[name="source"]')).toHaveValue('');
    await expect(page.locator('#gocardless-payment-action')).toBeVisible();

    await storedAccount.check();
    await expect(page.locator('input[name="source"]')).toHaveValue(mandate!);
    await expect(page.locator('#gocardless-payment-action')).toBeVisible();
});

test('completes a payment with bank account verification enabled', async ({
    api,
    page,
    notificationGuard,
}) => {
    const availability = await prepareGoCardless(api, {
        gatewayTypeId: GatewayType.DIRECT_DEBIT,
        configChanges: { verifyBankAccount: true },
    });
    await notificationGuard.suppressPaymentEmails();
    const context = await preparePortalPaymentContext(
        api,
        page,
        availability.companyGateway!,
        'default',
        clientLocation('826', '2', 'London', '', 'SW1A 1AA')
    );

    await navigateToPortalGatewayCheckout(
        page,
        context.companyGateway,
        GatewayType.DIRECT_DEBIT,
        'default',
        context.invoice
    );
    await completeRequiredClientInfoForm(page);
    await goCardless.assertCheckoutReady(page);
    await goCardless.completePayment(
        page,
        'GB',
        'protectplus_risky@gocardless.com'
    );
    await goCardless.assertPaymentSucceeded(page);

    const payment = goCardlessPayment(
        context.invoice.id,
        context.companyGateway.id
    );

    expect(payment).toMatchObject({
        gateway_type_id: GatewayType.DIRECT_DEBIT,
        type_id: PaymentType.BACS,
        scheme: 'bacs',
        verify: 'recommended',
    });
    test.skip(
        payment.verified_at === null,
        goCardlessSandboxUpgrade.verifiedMandates
    );
    expect(payment.verified_at).not.toBeNull();
    expect([1, 4]).toContain(payment.status_id);
});

test('shows only SEPA for an Austrian EUR client', async ({ api, page }) => {
    let availability = await prepareGoCardless(api, {
        gatewayTypeId: GatewayType.SEPA,
    });
    const companyGateway = await ensureCompanyGatewayTypeEnabled(
        api.context,
        availability.companyGateway!,
        GatewayType.DIRECT_DEBIT
    );
    availability = {
        ...availability,
        companyGateway: await ensureCompanyGatewayTypeEnabled(
            api.context,
            companyGateway,
            GatewayType.INSTANT_BANK_PAY
        ),
    };
    const context = await preparePortalPaymentContext(
        api,
        page,
        availability.companyGateway!,
        'default',
        clientLocation('40', '3', 'Vienna', '', '1010')
    );

    await openInvoicePaymentPage(page, context.invoice);

    await expect(
        page.locator(
            `[dusk="payment-methods-dropdown"] [data-gateway-key="${goCardless.gatewayKey}"][data-gateway-type-id="${GatewayType.SEPA}"]`
        )
    ).toHaveCount(1);
    await expect(
        page.locator(
            `[dusk="payment-methods-dropdown"] [data-gateway-key="${goCardless.gatewayKey}"][data-gateway-type-id="${GatewayType.DIRECT_DEBIT}"]`
        )
    ).toHaveCount(0);
    await expect(
        page.locator(
            `[dusk="payment-methods-dropdown"] [data-gateway-key="${goCardless.gatewayKey}"][data-gateway-type-id="${GatewayType.INSTANT_BANK_PAY}"]`
        )
    ).toHaveCount(0);
});

test('rejects a forced unsupported GoCardless method', async ({
    api,
    page,
}) => {
    const availability = await prepareGoCardless(api, {
        gatewayTypeId: GatewayType.INSTANT_BANK_PAY,
    });
    const context = await preparePortalPaymentContext(
        api,
        page,
        availability.companyGateway!,
        'default',
        clientLocation('40', '3', 'Vienna', '', '1010')
    );

    await openInvoicePaymentPage(page, context.invoice);
    await page.locator('#company_gateway_id').evaluate((input, value) => {
        (input as HTMLInputElement).value = String(value);
    }, decodePrimaryKey(context.companyGateway.id));
    await page.locator('#payment_method_id').evaluate((input, value) => {
        (input as HTMLInputElement).value = String(value);
    }, GatewayType.INSTANT_BANK_PAY);
    await page.locator('#payment-form').evaluate((form) => {
        (form as HTMLFormElement).submit();
    });
    await expect(page).toHaveURL(/\/client\/payments\/process/);
    await completeRequiredClientInfoForm(page);
    await expect(page.locator('main')).toContainText(
        /temporarily unavailable/i
    );
});

async function navigateToInstantBankPay(
    page: Page,
    company_gateway: CompanyGatewayEntity,
    payment_flow: 'default' | 'smooth',
    invoice: PortalEntity
): Promise<void> {
    if (payment_flow === 'smooth') {
        await openSmoothInvoicePaymentPage(page, invoice);
        const company_gateway_id = decodePrimaryKey(company_gateway.id);
        const method = page.locator(
            `button[wire\\:click*="handleSelect('${company_gateway_id}'"][wire\\:click*="'${GatewayType.INSTANT_BANK_PAY}'"]`
        );
        await expect(method).toBeVisible();
        await method.click();
        const next = page.getByRole('button', { name: 'Next', exact: true });
        await expect
            .poll(
                async () =>
                    page.url().includes('pay-sandbox.gocardless.com') ||
                    (await next.isVisible().catch(() => false)),
                { timeout: 30_000 }
            )
            .toBe(true);

        if (await next.isVisible().catch(() => false)) {
            await next.click();
        }
    } else {
        await openInvoicePaymentPage(page, invoice);
        await page.locator('#company_gateway_id').evaluate((input, value) => {
            (input as HTMLInputElement).value = String(value);
        }, decodePrimaryKey(company_gateway.id));
        await page.locator('#payment_method_id').evaluate((input, value) => {
            (input as HTMLInputElement).value = String(value);
        }, GatewayType.INSTANT_BANK_PAY);
        await page.locator('#payment-form').evaluate((form) => {
            (form as HTMLFormElement).submit();
        });
    }

    await page.waitForURL(/pay-sandbox\.gocardless\.com/, { timeout: 30_000 });
}

async function prepareGoCardless(
    api: import('../fixtures').ApiFixture,
    options: GatewayExclusiveSetupOptions = {},
): Promise<GatewayAvailability> {
    const setup = await goCardless.setupExclusiveTestEnvironment(
        api.context,
        options,
    );

    if (setup.skipReason) {
        test.skip(true, setup.skipReason);
    }

    return setup.availability;
}

function clientLocation(
    countryId: string,
    currencyId: string,
    city: string,
    state: string,
    postalCode: string
): Record<string, unknown> {
    return {
        city,
        state,
        postal_code: postalCode,
        country_id: countryId,
        shipping_city: city,
        shipping_state: state,
        shipping_postal_code: postalCode,
        shipping_country_id: countryId,
        settings: { currency_id: currencyId },
    };
}

function clientMandates(
    clientId: string,
    companyGatewayId: string,
    gatewayTypeId: number
): string[] {
    const raw = runArtisan(
        `$client_id = ${JSON.stringify(decodePrimaryKey(clientId))};` +
            `$company_gateway_id = ${JSON.stringify(decodePrimaryKey(companyGatewayId))};` +
            `$gateway_type_id = ${gatewayTypeId};` +
            '$tokens = \\App\\Models\\ClientGatewayToken::on("db-ninja-01")' +
            '->where("client_id", $client_id)' +
            '->where("company_gateway_id", $company_gateway_id)' +
            '->where("gateway_type_id", $gateway_type_id)' +
            '->pluck("token");' +
            'echo $tokens->toJson();'
    );

    return JSON.parse(raw) as string[];
}

async function addMandateFromPaymentMethods(
    page: Page,
    clientId: string,
    companyGatewayId: string,
    gatewayTypeId: number
): Promise<string> {
    await page.goto('/client/payment_methods');
    await dismissCookieConsent(page);
    await page.locator('[data-cy="add-payment-method"]').click();
    const link = page.locator('[data-cy="add-bank-account-link"]');
    await expect(link).toHaveAttribute(
        'href',
        new RegExp(`method=${gatewayTypeId}(?:&|$)`)
    );
    await link.click();
    await goCardless.completeHostedDirectDebit(page);

    await page.waitForURL(
        (url) =>
            /\/client\/payment_methods\/[^/]+$/.test(url.pathname) &&
            !url.pathname.endsWith('/create'),
        { timeout: 60_000 }
    );

    const mandates = clientMandates(clientId, companyGatewayId, gatewayTypeId);
    expect(mandates).toHaveLength(1);

    return mandates[0];
}

interface GoCardlessPayment {
    gateway_type_id: number | null;
    type_id: number | null;
    scheme: string;
    mandate: string | null;
    verified_at: string | null;
    verify: string | null;
    status_id: number;
}

function goCardlessPayment(
    invoiceId: string,
    companyGatewayId: string
): GoCardlessPayment {
    const raw = runArtisan(
        `$invoice = \\App\\Models\\Invoice::on("db-ninja-01")->withTrashed()->findOrFail(${JSON.stringify(decodePrimaryKey(invoiceId))});` +
            `$company_gateway = \\App\\Models\\CompanyGateway::on("db-ninja-01")->withTrashed()->findOrFail(${JSON.stringify(decodePrimaryKey(companyGatewayId))});` +
            `$invoice_hashed_id = ${JSON.stringify(invoiceId)};` +
            '$payment_hash = \\App\\Models\\PaymentHash::on("db-ninja-01")->latest("id")->get()->first(fn($hash) => collect($hash->invoices())->contains(fn($item) => data_get($item, "invoice_id") === $invoice_hashed_id));' +
            'throw_unless($payment_hash, \\Illuminate\\Database\\Eloquent\\ModelNotFoundException::class);' +
            '$payment = $payment_hash->payment;' +
            '$driver = $company_gateway->driver($invoice->client)->init();' +
            '$billing_request_id = data_get($payment_hash->data, "gocardless.billing_request");' +
            '$billing_request = $billing_request_id ? $driver->gateway->billingRequests()->get($billing_request_id) : null;' +
            '$remote_payment = $payment ? $driver->gateway->payments()->get($payment->transaction_reference) : null;' +
            '$mandate_id = data_get($remote_payment, "links.mandate");' +
            '$mandate = $mandate_id ? $driver->gateway->mandates()->get($mandate_id) : null;' +
            '$scheme = data_get($billing_request, "mandate_request.scheme") ?? data_get($billing_request, "payment_request.scheme") ?? data_get($mandate, "scheme");' +
            'echo json_encode(["gateway_type_id" => $payment?->gateway_type_id, "type_id" => $payment?->type_id, "scheme" => $scheme, "mandate" => $mandate_id, "verified_at" => data_get($mandate, "verified_at"), "verify" => data_get($billing_request, "mandate_request.verify"), "status_id" => $payment?->status_id]);'
    );

    return JSON.parse(raw) as GoCardlessPayment;
}

function completeFulfilledBillingRequest(
    invoiceId: string,
    companyGatewayId: string
): boolean {
    const raw = runArtisan(
        `$invoice = \\App\\Models\\Invoice::on("db-ninja-01")->withTrashed()->findOrFail(${JSON.stringify(decodePrimaryKey(invoiceId))});` +
            `$company_gateway = \\App\\Models\\CompanyGateway::on("db-ninja-01")->withTrashed()->findOrFail(${JSON.stringify(decodePrimaryKey(companyGatewayId))});` +
            `$invoice_hashed_id = ${JSON.stringify(invoiceId)};` +
            '$payment_hash = \\App\\Models\\PaymentHash::on("db-ninja-01")->latest("id")->get()->first(fn($hash) => collect($hash->invoices())->contains(fn($item) => data_get($item, "invoice_id") === $invoice_hashed_id));' +
            'throw_unless($payment_hash, \\Illuminate\\Database\\Eloquent\\ModelNotFoundException::class);' +
            '$billing_request_id = data_get($payment_hash->data, "gocardless.billing_request");' +
            '$driver = $company_gateway->driver($invoice->client)->init();' +
            '$billing_request = $driver->gateway->billingRequests()->get($billing_request_id);' +
            'if (!$payment_hash->payment_id && $billing_request->status === "fulfilled") {' +
            '(new \\App\\PaymentDrivers\\GoCardless\\Jobs\\GoCardlessWebhook([[' +
            '"id" => "EV_PLAYWRIGHT_BILLING_REQUEST_FULFILLED",' +
            '"resource_type" => "billing_requests",' +
            '"action" => "fulfilled",' +
            '"links" => ["billing_request" => $billing_request_id],' +
            ']], $company_gateway->company->company_key, $company_gateway->id))->handle();' +
            '$payment_hash->refresh();' +
            '}' +
            'echo $payment_hash->payment_id ? "true" : "false";'
    );

    return raw.trim() === 'true';
}
