import { test, expect, uniqueName, type ApiFixture } from '../fixtures';
import {
    ensureCompanyGatewayTypeEnabled,
    findCompanyGatewayByKey,
    getEntity,
    listCompanyGateways,
    setCompanyGatewayFeeForKey,
    updateClient,
    type CompanyGatewayEntity,
    type FeesAndLimitsEntry,
} from '../api-helpers';
import { createAndLogInClient } from '../client-portal-helpers';
import { navigateToGatewayCheckout } from '../gateways/payment-flow-helpers';
import { GatewayType } from '../gateways/types';
import { createSentInvoice } from '../portal-entity-helpers';
import {
    completeFinancialConnections,
    createMandatedAchPaymentMethod,
    hasWebhookEndpoint,
    stripeGatewayKey,
    stripeGet,
    validatedStripeTestSecret,
} from '../gateways/stripe-ach-helpers';
import {
    gatewayFeeLedgerRows,
    registerStripeWebhook,
    storeAchToken,
} from '../artisan-helpers';

const invoiceCost = 42;
const provisionedFee = 4.99;

interface InvoiceLineItem {
    type_id?: string;
    unit_code?: string;
    cost?: number;
}

interface InvoiceEntity {
    id: string;
    number?: string;
    amount?: number;
    balance?: number;
    paid_to_date?: number;
    line_items?: InvoiceLineItem[];
}

function feeLines(invoice: InvoiceEntity): InvoiceLineItem[] {
    return (invoice.line_items ?? []).filter(
        (item) => item.type_id === '3' || item.type_id === '4',
    );
}

function readFee(
    gateway: CompanyGatewayEntity,
    gatewayTypeId: number,
): number {
    const entry = (gateway.fees_and_limits ?? {})[String(gatewayTypeId)] as
        | (FeesAndLimitsEntry & { fee_amount?: number })
        | undefined;

    return Number(entry?.fee_amount ?? 0);
}

/**
 * ACH is asynchronous: the debit is reported as processing first and settles or fails
 * minutes later. The gateway fee is confirmed onto the invoice at the processing step,
 * so the outcome that arrives afterwards decides whether it stays.
 *
 * These run against the Stripe sandbox for real: a real bank account is linked, a real
 * debit is submitted, and the outcome arrives as a real Stripe webhook. Stripe's test
 * mode settles in around half a minute.
 *
 * @see app/Services/Invoice/ConfirmGatewayFee.php
 * @see app/Services/Invoice/ReverseGatewayFee.php
 * @see https://docs.stripe.com/payments/ach-direct-debit/accept-a-payment#test-account-numbers
 */
test.describe('Stripe ACH gateway fees', () => {
    test.describe.configure({ retries: 0 });

    let secret: string;
    let companyGateway: CompanyGatewayEntity;
    /** A company can carry several gateways for one key; the portal picks one of them. */
    let achGateways: CompanyGatewayEntity[] = [];
    let fee = 0;
    let restoreFee: (() => Promise<void>) | undefined;

    test.beforeEach(async ({ api, notificationGuard }) => {
        const stripeSecret = await validatedStripeTestSecret();

        if (!stripeSecret) {
            test.skip(
                true,
                'Set STRIPE_KEYS to a valid Stripe test mode secret key to run live ACH tests.',
            );

            return;
        }

        secret = stripeSecret;

        const gateway = findCompanyGatewayByKey(
            await listCompanyGateways(api.context),
            stripeGatewayKey,
            GatewayType.ACH,
        );

        test.skip(!gateway, 'no Stripe company gateway to run ACH against');

        await ensureCompanyGatewayTypeEnabled(
            api.context,
            gateway!,
            GatewayType.ACH,
        );

        const applied = await setCompanyGatewayFeeForKey(
            api.context,
            stripeGatewayKey,
            GatewayType.ACH,
            provisionedFee,
        );

        restoreFee = applied.restore;
        achGateways = applied.gateways;
        companyGateway =
            applied.gateways.find(
                (candidate) => candidate.id === gateway!.id,
            ) ?? applied.gateways[0];
        fee = readFee(companyGateway, GatewayType.ACH);

        test.skip(fee <= 0, 'no flat ACH fee could be configured');

        /**
         * The outcome of the debit only reaches the application by webhook. Without a
         * reachable endpoint the payment would sit pending for ever.
         */
        const webhookUrls = applied.gateways.map((candidate) =>
            registerStripeWebhook(candidate.id),
        );

        const reachable = await Promise.all(
            webhookUrls.map((url) => hasWebhookEndpoint(secret, url)),
        );

        test.skip(
            !reachable.some(Boolean),
            `Stripe cannot reach ${webhookUrls[0]} - serve the application on a public APP_URL to run ACH outcome tests`,
        );

        await notificationGuard.suppressPaymentEmails();
    });

    test.afterEach(async () => {
        await restoreFee?.();
        restoreFee = undefined;
    });

    test('a debit that starts processing records the fee once and keeps it when it settles', async ({
        api,
        page,
    }) => {
        test.setTimeout(300_000);

        const invoice = await createAchInvoice(api, page, 'ach-fee-settles');

        await navigateToGatewayCheckout(
            page,
            companyGateway,
            GatewayType.ACH,
            invoice,
        );

        await expect(page.locator('#new-bank')).toBeVisible({
            timeout: 30_000,
        });
        await page.locator('#accept-terms').check();
        await page.locator('#new-bank').click();
        await completeFinancialConnections(
            page,
            /\/client\/payments\/(?!process(?:\?|$)|response(?:\?|$))[^/?]+/,
        );

        /** The debit is processing, not settled: a pending payment carrying the fee. */
        const pending = await readInvoice(api, invoice.id);

        expect(
            feeLines(pending),
            'the fee was not recorded when the debit started processing',
        ).toHaveLength(1);
        expect(Number(pending.amount)).toBeCloseTo(invoiceCost + fee, 2);

        const paymentId = page.url().match(/\/client\/payments\/([^/?]+)/)?.[1];

        expect(paymentId, 'checkout did not create a payment').toBeTruthy();

        const payment = await getEntity<{ amount: number; status_id: string }>(
            api.context,
            'payments',
            paymentId!,
        );

        expect(Number(payment.amount)).toBeCloseTo(invoiceCost + fee, 2);
        expect(Number(payment.status_id)).toBe(1);

        /** Stripe settles the test debit and sends payment_intent.succeeded. */
        await expect
            .poll(
                async () =>
                    Number(
                        (
                            await getEntity<{ status_id: string }>(
                                api.context,
                                'payments',
                                paymentId!,
                            )
                        ).status_id,
                    ),
                {
                    timeout: 240_000,
                    intervals: [5_000],
                    message:
                        'Stripe never reported the debit as settled - check webhook delivery',
                },
            )
            .toBe(4);

        const settled = await readInvoice(api, invoice.id);

        expect(
            feeLines(settled),
            'settling the debit added a second fee',
        ).toHaveLength(1);
        expect(Number(settled.amount)).toBeCloseTo(invoiceCost + fee, 2);
        expect(Number(settled.balance)).toBeCloseTo(0, 2);
        expect(gatewayFeeLedgerRows(invoice.id)).toHaveLength(1);
    });

    test('a debit that fails after processing gives the fee back', async ({
        api,
        page,
    }) => {
        test.setTimeout(300_000);

        const invoice = await createAchInvoice(api, page, 'ach-fee-reversal');
        const clientId = await clientIdForInvoice(api, invoice.id);

        /**
         * Every bank account the Financial Connections test institution offers succeeds,
         * so the failing debit comes from Stripe's documented test payment method.
         */
        const bankAccount = await createMandatedAchPaymentMethod(secret);

        /** Stored on each candidate, because the portal chooses which one it offers. */
        for (const candidate of achGateways) {
            storeAchToken({
                clientHashedId: clientId,
                companyGatewayHashedId: candidate.id,
                paymentMethodId: bankAccount.paymentMethodId,
                customerId: bankAccount.customerId,
                last4: bankAccount.last4,
            });
        }

        await navigateToGatewayCheckout(
            page,
            companyGateway,
            GatewayType.ACH,
            invoice,
        );

        const storedAccount = page
            .locator('.toggle-payment-with-token')
            .first();

        await expect(storedAccount).toBeVisible({ timeout: 30_000 });
        await storedAccount.check();
        await page.locator('#pay-now').click();

        /** The debit is processing, so the fee is on the invoice. */
        await expect
            .poll(
                async () =>
                    Number((await readInvoice(api, invoice.id)).amount),
                {
                    timeout: 60_000,
                    message: 'the debit never reached the server',
                },
            )
            .toBeCloseTo(invoiceCost + fee, 2);

        const processing = await readInvoice(api, invoice.id);

        expect(feeLines(processing)).toHaveLength(1);
        expect(gatewayFeeLedgerRows(invoice.id)).toHaveLength(1);

        /**
         * Wait for Stripe itself to fail the debit first, so a slow sandbox is not
         * mistaken for a reversal that never happened. Test mode has taken anywhere from
         * half a minute to several minutes.
         */
        const reference = await paymentReference(api, invoice.id);

        await expect
            .poll(() => stripeChargeStatus(secret, reference), {
                timeout: 480_000,
                intervals: [10_000],
                message: `Stripe never failed the debit ${reference}`,
            })
            .toBe('failed');

        /** The failure webhook unwinds the payment, and the surcharge comes off with it. */
        await expect
            .poll(
                async () =>
                    feeLines(await readInvoice(api, invoice.id)).length,
                {
                    timeout: 180_000,
                    intervals: [5_000],
                    message:
                        'the failed debit left its gateway fee on the invoice',
                },
            )
            .toBe(0);

        const reversed = await readInvoice(api, invoice.id);

        expect(Number(reversed.amount)).toBeCloseTo(invoiceCost, 2);
        expect(Number(reversed.balance)).toBeCloseTo(invoiceCost, 2);
        expect(Number(reversed.paid_to_date)).toBeCloseTo(0, 2);

        const ledgerRows = gatewayFeeLedgerRows(invoice.id);

        expect(
            ledgerRows,
            'the fee should have been added once and reversed once',
        ).toHaveLength(2);
        expect(
            ledgerRows.reduce((sum, row) => sum + Number(row.adjustment), 0),
        ).toBeCloseTo(0, 2);
    });
});

async function readInvoice(
    api: ApiFixture,
    invoiceId: string,
): Promise<InvoiceEntity> {
    return getEntity<InvoiceEntity>(api.context, 'invoices', invoiceId);
}

async function clientIdForInvoice(
    api: ApiFixture,
    invoiceId: string,
): Promise<string> {
    const invoice = await getEntity<{ client_id: string }>(
        api.context,
        'invoices',
        invoiceId,
    );

    return invoice.client_id;
}

/** A US client with a complete address - ACH checkout requires one - and one invoice. */
async function createAchInvoice(
    api: ApiFixture,
    page: import('@playwright/test').Page,
    label: string,
): Promise<InvoiceEntity> {
    let client = await createAndLogInClient(api, page, {
        settings: {
            payment_flow: 'default',
            client_manual_payment_notification: false,
        },
    });

    client = (await updateClient(api.context, client, {
        address1: '510 Townsend Street',
        city: 'San Francisco',
        state: 'CA',
        postal_code: '94103',
        country_id: '840',
        phone: '4155550100',
    })) as typeof client;

    return (await createSentInvoice(api, client, {
        label: uniqueName(label),
        cost: invoiceCost,
    })) as InvoiceEntity;
}

/** The Stripe reference the payment was created against. */
async function paymentReference(
    api: ApiFixture,
    invoiceId: string,
): Promise<string> {
    const invoice = await getEntity<{ payments?: Array<{ transaction_reference?: string }> }>(
        api.context,
        'invoices',
        `${invoiceId}?include=payments`,
    );

    const reference = invoice.payments?.[0]?.transaction_reference ?? '';

    expect(reference, 'the debit created no payment').toBeTruthy();

    return reference;
}

/** Charge status for a payment intent or charge reference, or null while unknown. */
async function stripeChargeStatus(
    secret: string,
    reference: string,
): Promise<string | null> {
    try {
        if (reference.startsWith('pi_')) {
            const intent = await stripeGet<{
                status: string;
                latest_charge?: string;
            }>(secret, `/v1/payment_intents/${reference}`);

            if (!intent.latest_charge) {
                return intent.status === 'requires_payment_method'
                    ? 'failed'
                    : intent.status;
            }

            return stripeChargeStatus(secret, intent.latest_charge);
        }

        const charge = await stripeGet<{ status: string }>(
            secret,
            `/v1/charges/${reference}`,
        );

        return charge.status;
    } catch {
        return null;
    }
}
