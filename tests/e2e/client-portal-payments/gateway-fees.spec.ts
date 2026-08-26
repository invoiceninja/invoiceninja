import { test, expect, uniqueName } from '../fixtures';
import { paymentGateways } from './registry';
import {
    clickBulkPayNow,
    openInvoicePaymentPage,
    navigateToGatewayCheckout,
    selectGatewayFromDropdown,
    fillRequiredPaymentInformationIfPresent,
} from '../gateways/payment-flow-helpers';
import {
    bulkAction,
    ensureCompanyGatewayForKey,
    getEntity,
    getCompanyGateway,
    listCompanyGateways,
    setCompanyGatewayFeeForKey,
    updateClient,
    type ApiContext,
} from '../api-helpers';
import {
    type ClientEntity,
    type CompanyGatewayEntity,
    type FeesAndLimitsEntry,
} from '../api-helpers';
import {
    dismissCookieConsent,
    selectEntityTableRow,
} from '../client-portal-helpers';
import {
    createSentInvoice,
    markExistingInvoicePaid,
} from '../portal-entity-helpers';
import {
    gatewayFeeLedgerRows,
    reconfirmGatewayFee,
} from '../artisan-helpers';
import { gatewayBySlug } from '../gateways/gateway-registry';
import { type GatewayAvailability } from '../gateways/types';
import { type ApiFixture } from '../fixtures';
import { type Page } from '@playwright/test';

interface InvoiceLineItem {
    type_id?: string;
    unit_code?: string;
    cost?: number;
}

interface InvoiceEntity {
    id: string;
    amount?: number;
    balance?: number;
    paid_to_date?: number;
    status_id?: string | number;
    line_items?: InvoiceLineItem[];
}

/** Applied when the gateway carries no fee of its own, so the lane runs rather than skips. */
const provisionedFee = 4.99;

/**
 * Reads the flat fee configured for one payment method.
 *
 * Percentage fees vary with the invoice total; these tests assert a flat amount.
 */
function configuredFee(
    gateway: CompanyGatewayEntity,
    gatewayTypeId: number,
): number {
    const entry = (gateway.fees_and_limits ?? {})[String(gatewayTypeId)] as
        | (FeesAndLimitsEntry & { fee_amount?: number; fee_percent?: number })
        | undefined;

    if (!entry || Number(entry.fee_percent ?? 0) !== 0) {
        return 0;
    }

    return Number(entry.fee_amount ?? 0);
}

function feeLines(invoice: InvoiceEntity): InvoiceLineItem[] {
    return (invoice.line_items ?? []).filter(
        (item) => item.type_id === '3' || item.type_id === '4',
    );
}

async function readInvoice(
    api: ApiFixture,
    invoiceId: string,
): Promise<InvoiceEntity> {
    return getEntity<InvoiceEntity>(api.context, 'invoices', invoiceId);
}

/**
 * Waits for the server side outcome of a payment.
 *
 * BasePaymentGateway.assertPaymentSucceeded() waits for a /client/payments/ URL, which
 * the checkout page itself already matches - it cannot tell a completed payment from an
 * abandoned one.
 */
async function waitForFeeOnInvoice(
    api: ApiFixture,
    invoiceId: string,
): Promise<InvoiceEntity> {
    await expect
        .poll(
            async () => feeLines(await readInvoice(api, invoiceId)).length,
            {
                timeout: 90_000,
                message:
                    'the payment never reached the server, so no gateway fee was recorded',
            },
        )
        .toBe(1);

    return readInvoice(api, invoiceId);
}

/**
 * Leaves exactly one gateway active for the duration of a test.
 *
 * The portal offers every enabled gateway, and a company commonly carries several for
 * one key - so a test that does not isolate cannot know which gateway the checkout it
 * drove actually used.
 */
async function isolateCompanyGatewaysForKey(
    api: ApiContext,
    gatewayKey: string,
): Promise<CompanyGatewayEntity[]> {
    const gateways = await listCompanyGateways(api);
    const active = gateways.filter(
        (gateway) => !gateway.archived_at && !gateway.is_deleted,
    );

    const keep = active.filter((gateway) => gateway.gateway_key === gatewayKey);
    const archive = active
        .filter((gateway) => gateway.gateway_key !== gatewayKey)
        .map((gateway) => gateway.id);

    if (archive.length > 0) {
        await bulkAction(api, 'company_gateways', archive, 'archive');
    }

    return keep;
}

/**
 * Gateway fees are quoted when a payment is initiated and written to the invoice only
 * once the payment is confirmed. Nothing is persisted for an abandoned attempt.
 *
 * @see app/Services/Invoice/CalculateGatewayFee.php
 * @see app/Services/Invoice/ConfirmGatewayFee.php
 * @see docs/gateway-fee-resolution-plan.md
 */
test.describe('Client portal gateway fees', () => {
    for (const gateway of paymentGateways) {
        test.describe(gateway.displayName, () => {
            let availability: GatewayAvailability;
            let fee = 0;
            let restoreFee: (() => Promise<void>) | undefined;
            test.beforeEach(async ({ api }) => {
                /** A gateway that was never seeded is scaffolded from its env credentials. */
                if (gateway.isEnvConfigured()) {
                    await ensureCompanyGatewayForKey(
                        api.context,
                        gateway.gatewayKey,
                        gateway.envVar,
                    );
                }

                availability = await gateway.checkAvailability(api.context);
                gateway.skipUnlessAvailable(availability);

                /** One gateway offered in the portal, so the checkout under test is unambiguous. */
                await isolateCompanyGatewaysForKey(
                    api.context,
                    gateway.gatewayKey,
                );

                let companyGateway = await getCompanyGateway(
                    api.context,
                    availability.companyGateway!.id,
                );

                fee = configuredFee(companyGateway, gateway.gatewayTypeId);

                /**
                 * Seeded gateways carry no fee. Apply one for the duration of the test -
                 * a Stripe gateway update registers a webhook with Stripe, which fails
                 * when APP_URL is not publicly reachable.
                 */
                if (fee <= 0) {
                    try {
                        const applied = await setCompanyGatewayFeeForKey(
                            api.context,
                            gateway.gatewayKey,
                            gateway.gatewayTypeId,
                            provisionedFee,
                        );

                        restoreFee = applied.restore;
                        companyGateway =
                            applied.gateways.find(
                                (candidate) =>
                                    candidate.id === companyGateway.id,
                            ) ??
                            applied.gateways[0] ??
                            companyGateway;
                        fee = configuredFee(
                            companyGateway,
                            gateway.gatewayTypeId,
                        );
                    } catch (error) {
                        test.skip(
                            true,
                            `${gateway.displayName}: could not configure a gateway fee - ${(error as Error).message}`,
                        );
                    }
                }

                availability.companyGateway = companyGateway;

                test.skip(
                    fee <= 0,
                    `${gateway.displayName}: no flat fee on gateway type ${gateway.gatewayTypeId}`,
                );

                test.skip(
                    gateway.requiresStoredMandate,
                    `${gateway.displayName}: the portal offers no checkout until the client has an authorised mandate`,
                );
            });

            test.afterEach(async ({ api }) => {
                await restoreFee?.();
                restoreFee = undefined;
            });

            test('viewing and abandoning a payment leaves the invoice untouched', async ({
                api,
                page,
            }) => {
                test.setTimeout(120_000);

                const context = await gateway.preparePaymentContext(
                    api,
                    page,
                    availability,
                );

                const before = await readInvoice(api, context.invoice.id);

                expect(feeLines(before)).toHaveLength(0);

                /** Initiating a payment must not write a fee to the invoice. */
                await navigateToGatewayCheckout(
                    page,
                    context.companyGateway,
                    gateway.gatewayTypeId,
                    context.invoice,
                );
                await gateway.assertCheckoutReady(page);

                /**
                 * The fee is quoted, not read off the invoice - so the customer must still
                 * see it, and the total they are charged must include it.
                 */
                if (gateway.rendersFeeSummary) {
                    const summary = page.locator('body');

                    await expect(summary).toContainText(
                        new RegExp(`\\$\\s*${fee.toFixed(2)}`),
                    );
                    await expect(summary).toContainText(
                        new RegExp(
                            `\\$\\s*${(Number(before.amount) + fee).toFixed(2)}`,
                        ),
                    );
                }

                /** Abandon: walk away from checkout without paying. */
                await openInvoicePaymentPage(page, context.invoice);

                const after = await readInvoice(api, context.invoice.id);

                expect(
                    feeLines(after),
                    'an abandoned payment attempt left a fee on the invoice',
                ).toHaveLength(0);

                expect(Number(after.amount)).toBeCloseTo(
                    Number(before.amount),
                    2,
                );
                expect(Number(after.balance)).toBeCloseTo(
                    Number(before.balance),
                    2,
                );
                expect(
                    gatewayFeeLedgerRows(context.invoice.id),
                    'an abandoned attempt posted a ledger adjustment',
                ).toHaveLength(0);
            });

            test('a completed payment records the fee on the invoice exactly once', async ({
                api,
                page,
                notificationGuard,
            }) => {
                test.skip(
                    !gateway.supportsFullPayment,
                    `${gateway.displayName} does not implement full payment completion`,
                );

                test.setTimeout(180_000);

                await notificationGuard.suppressPaymentEmails();

                const context = await gateway.preparePaymentContext(
                    api,
                    page,
                    availability,
                );

                const before = await readInvoice(api, context.invoice.id);
                const startingAmount = Number(before.amount);

                await navigateToGatewayCheckout(
                    page,
                    context.companyGateway,
                    gateway.gatewayTypeId,
                    context.invoice,
                );
                await gateway.assertCheckoutReady(page);
                await gateway.completePayment(page);

                const after = await waitForFeeOnInvoice(
                    api,
                    context.invoice.id,
                );
                const fees = feeLines(after);

                expect(
                    fees,
                    'the confirmed payment did not record exactly one gateway fee',
                ).toHaveLength(1);

                expect(fees[0].type_id).toBe('4');
                expect(Number(fees[0].cost)).toBeCloseTo(fee, 2);

                /** The invoice must move by exactly the fee that was charged. */
                expect(Number(after.amount)).toBeCloseTo(
                    startingAmount + fee,
                    2,
                );
                expect(Number(after.balance)).toBeCloseTo(0, 2);
                expect(Number(after.paid_to_date)).toBeCloseTo(
                    startingAmount + fee,
                    2,
                );

                /** One adjustment per payment - the invoice amount moved once. */
                const ledgerRows = gatewayFeeLedgerRows(context.invoice.id);

                expect(
                    ledgerRows,
                    'the fee did not post exactly one ledger adjustment',
                ).toHaveLength(1);
                expect(Number(ledgerRows[0].adjustment)).toBeCloseTo(fee, 2);
            });
        });
    }
});

/**
 * What happens to a fee once it is on the invoice is the same for every gateway - the
 * fee is written by one service, keyed on the payment hash. These run against Stripe.
 *
 * @see app/Services/Invoice/ConfirmGatewayFee.php
 */
test.describe('Gateway fee lifecycle', () => {
    const gateway = gatewayBySlug('stripe')!;

    let availability: GatewayAvailability;
    let fee = 0;
    let restoreFee: (() => Promise<void>) | undefined;
    test.beforeEach(async ({ api, notificationGuard }) => {
        availability = await gateway.checkAvailability(api.context);
        gateway.skipUnlessAvailable(availability);

        await isolateCompanyGatewaysForKey(api.context, gateway.gatewayKey);

        let companyGateway = await getCompanyGateway(
            api.context,
            availability.companyGateway!.id,
        );

        fee = configuredFee(companyGateway, gateway.gatewayTypeId);

        if (fee <= 0) {
            try {
                const applied = await setCompanyGatewayFeeForKey(
                    api.context,
                    gateway.gatewayKey,
                    gateway.gatewayTypeId,
                    provisionedFee,
                );

                restoreFee = applied.restore;
                companyGateway =
                    applied.gateways.find(
                        (candidate) => candidate.id === companyGateway.id,
                    ) ??
                    applied.gateways[0] ??
                    companyGateway;
                fee = configuredFee(companyGateway, gateway.gatewayTypeId);
            } catch (error) {
                test.skip(
                    true,
                    `could not configure a gateway fee - ${(error as Error).message}`,
                );
            }
        }

        availability.companyGateway = companyGateway;

        test.skip(fee <= 0, 'no flat fee configured for Stripe credit card');

        await notificationGuard.suppressPaymentEmails();
    });

    test.afterEach(async ({ api }) => {
        await restoreFee?.();
        restoreFee = undefined;
    });

    test('a redelivered confirmation adds no second fee and no second ledger row', async ({
        api,
        page,
    }) => {
        test.setTimeout(180_000);

        const context = await gateway.preparePaymentContext(
            api,
            page,
            availability,
        );

        await navigateToGatewayCheckout(
            page,
            context.companyGateway,
            gateway.gatewayTypeId,
            context.invoice,
        );
        await gateway.assertCheckoutReady(page);
        await gateway.completePayment(page);

        const paid = await waitForFeeOnInvoice(api, context.invoice.id);
        const paymentHash = feeLines(paid)[0].unit_code!;

        expect(paymentHash, 'the fee line is not keyed on a payment hash').toBeTruthy();

        /** The gateway redelivers the webhook that confirmed the fee. */
        reconfirmGatewayFee(
            paymentHash,
            context.companyGateway.id,
            gateway.gatewayTypeId,
        );

        const after = await readInvoice(api, context.invoice.id);

        expect(
            feeLines(after),
            'the redelivered confirmation duplicated the surcharge',
        ).toHaveLength(1);
        expect(Number(after.amount)).toBeCloseTo(Number(paid.amount), 2);
        expect(Number(after.balance)).toBeCloseTo(0, 2);
        expect(
            gatewayFeeLedgerRows(context.invoice.id),
            'the redelivered confirmation posted a second ledger adjustment',
        ).toHaveLength(1);
    });

    test('marking a part paid invoice as paid keeps the confirmed fee', async ({
        api,
        page,
    }) => {
        test.setTimeout(180_000);

        const context = await gateway.preparePaymentContext(
            api,
            page,
            availability,
        );

        await allowUnderPayment(api, context.client.id);

        const before = await readInvoice(api, context.invoice.id);
        const partial = 10;

        await payFromBulkPage(
            page,
            [context.invoice],
            context.companyGateway,
            gateway.gatewayTypeId,
            { [String(context.invoice.number)]: partial },
        );
        await gateway.assertCheckoutReady(page);
        await gateway.completePayment(page);

        const partlyPaid = await waitForFeeOnInvoice(api, context.invoice.id);

        expect(Number(partlyPaid.amount)).toBeCloseTo(
            Number(before.amount) + fee,
            2,
        );
        expect(Number(partlyPaid.paid_to_date)).toBeCloseTo(partial + fee, 2);

        /** The fee was charged, so marking the rest paid must not strip it. */
        await markExistingInvoicePaid(api, context.invoice.id);

        const marked = await readInvoice(api, context.invoice.id);

        expect(
            feeLines(marked),
            'mark paid removed a gateway fee that was already charged',
        ).toHaveLength(1);
        expect(Number(marked.amount)).toBeCloseTo(Number(before.amount) + fee, 2);
        expect(Number(marked.balance)).toBeCloseTo(0, 2);
    });

    test('paying several invoices at once records one fee for the payment', async ({
        api,
        page,
    }) => {
        test.setTimeout(180_000);

        const context = await gateway.preparePaymentContext(
            api,
            page,
            availability,
        );
        const second = await createSentInvoice(api, context.client, {
            label: uniqueName('gateway-fee-bulk'),
            cost: 17,
        });

        const first = await readInvoice(api, context.invoice.id);
        const combined = Number(first.amount) + Number(second.amount ?? 0);

        await payFromBulkPage(
            page,
            [context.invoice, second],
            context.companyGateway,
            gateway.gatewayTypeId,
        );
        await gateway.assertCheckoutReady(page);
        await gateway.completePayment(page);

        await expect
            .poll(
                async () => {
                    const [a, b] = await Promise.all([
                        readInvoice(api, context.invoice.id),
                        readInvoice(api, second.id),
                    ]);

                    return feeLines(a).length + feeLines(b).length;
                },
                {
                    timeout: 90_000,
                    message: 'the bulk payment never reached the server',
                },
            )
            .toBe(1);

        const [firstAfter, secondAfter] = await Promise.all([
            readInvoice(api, context.invoice.id),
            readInvoice(api, second.id),
        ]);

        const carrier =
            feeLines(firstAfter).length === 1 ? firstAfter : secondAfter;
        const other = carrier === firstAfter ? secondAfter : firstAfter;

        expect(
            feeLines(other),
            'the fee was recorded on more than one invoice',
        ).toHaveLength(0);
        expect(Number(carrier.balance)).toBeCloseTo(0, 2);
        expect(Number(other.balance)).toBeCloseTo(0, 2);

        expect(
            Number(firstAfter.paid_to_date) + Number(secondAfter.paid_to_date),
        ).toBeCloseTo(combined + fee, 2);

        expect(gatewayFeeLedgerRows(carrier.id)).toHaveLength(1);
        expect(gatewayFeeLedgerRows(other.id)).toHaveLength(0);
    });
});

/** Under payment has to be on for the portal to expose an editable amount. */
async function allowUnderPayment(
    api: ApiFixture,
    clientId: string,
): Promise<void> {
    const client = await getEntity<ClientEntity>(
        api.context,
        'clients',
        clientId,
    );

    await updateClient(api.context, client, {
        settings: {
            client_portal_allow_under_payment: true,
            client_portal_under_payment_minimum: 1,
        },
    });
}

/**
 * Pays from the bulk payment page, which is the only portal flow that pays several
 * invoices at once and the only one with an editable amount.
 *
 * @param amounts amount to pay per invoice number; omit to pay the balance
 */
async function payFromBulkPage(
    page: Page,
    invoices: Array<{ id: string; number?: string }>,
    companyGateway: CompanyGatewayEntity,
    gatewayTypeId: number,
    amounts: Record<string, number> = {},
): Promise<void> {
    await page.goto('/client/invoices');
    await dismissCookieConsent(page);

    if (
        (await page.locator('button[name="action"][value="payment"]').count()) ===
        0
    ) {
        test.skip(true, 'the portal invoice table offers no bulk payment action');
    }

    for (const invoice of invoices) {
        await selectEntityTableRow(page, '.invoices-table', invoice.number ?? '');
    }

    await clickBulkPayNow(page);
    await expect(page).toHaveURL(/\/client\/invoices\/payment/);
    await dismissCookieConsent(page);

    for (const [index, invoice] of invoices.entries()) {
        const amount = amounts[String(invoice.number)];

        if (amount === undefined) {
            continue;
        }

        await page
            .locator(`input[name="payable_invoices[${index}][amount]"]`)
            .fill(amount.toFixed(2));
    }

    await selectGatewayFromDropdown(page, companyGateway, gatewayTypeId);
    await fillRequiredPaymentInformationIfPresent(page);
}
