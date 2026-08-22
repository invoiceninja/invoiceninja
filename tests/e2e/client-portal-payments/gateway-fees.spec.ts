import { test, expect } from '../fixtures';
import { paymentGateways } from './registry';
import {
    openInvoicePaymentPage,
    navigateToGatewayCheckout,
} from '../gateways/payment-flow-helpers';
import { getEntity, getCompanyGateway } from '../api-helpers';
import {
    type CompanyGatewayEntity,
    type FeesAndLimitsEntry,
} from '../api-helpers';
import { type GatewayAvailability } from '../gateways/types';

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

/**
 * Reads the flat fee configured for one payment method.
 *
 * The fee is not applied here: creating or updating a Stripe company gateway through the
 * API makes the driver register a webhook, which Stripe rejects for a non public APP_URL.
 * Configure the fee on the gateway (admin portal or seeder) to exercise these tests.
 */
function configuredFee(
    gateway: CompanyGatewayEntity,
    gatewayTypeId: number,
): number {
    const entry = (gateway.fees_and_limits ?? {})[String(gatewayTypeId)] as
        | (FeesAndLimitsEntry & { fee_amount?: number; fee_percent?: number })
        | undefined;

    if (!entry) {
        return 0;
    }

    /** Percentage fees vary with the invoice total; these tests assert a flat amount. */
    if (Number(entry.fee_percent ?? 0) !== 0) {
        return 0;
    }

    return Number(entry.fee_amount ?? 0);
}

function feeLines(invoice: InvoiceEntity): InvoiceLineItem[] {
    return (invoice.line_items ?? []).filter(
        (item) => item.type_id === '3' || item.type_id === '4',
    );
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

            test.beforeEach(async ({ api }) => {
                availability = await gateway.checkAvailability(api.context);
                gateway.skipUnlessAvailable(availability);

                const companyGateway = await getCompanyGateway(
                    api.context,
                    availability.companyGateway!.id,
                );

                fee = configuredFee(companyGateway, gateway.gatewayTypeId);

                test.skip(
                    fee <= 0,
                    `${gateway.displayName}: configure a flat fee_amount on gateway type ${gateway.gatewayTypeId} to run gateway fee tests`,
                );
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

                const before = await getEntity<InvoiceEntity>(
                    api.context,
                    'invoices',
                    context.invoice.id,
                );

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
                const summary = page.locator('body');

                await expect(summary).toContainText(
                    new RegExp(`\\$\\s*${fee.toFixed(2)}`),
                );
                await expect(summary).toContainText(
                    new RegExp(
                        `\\$\\s*${(Number(before.amount) + fee).toFixed(2)}`,
                    ),
                );

                /** Abandon: walk away from checkout without paying. */
                await openInvoicePaymentPage(page, context.invoice);

                const after = await getEntity<InvoiceEntity>(
                    api.context,
                    'invoices',
                    context.invoice.id,
                );

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

                const before = await getEntity<InvoiceEntity>(
                    api.context,
                    'invoices',
                    context.invoice.id,
                );
                const startingAmount = Number(before.amount);

                await navigateToGatewayCheckout(
                    page,
                    context.companyGateway,
                    gateway.gatewayTypeId,
                    context.invoice,
                );
                await gateway.assertCheckoutReady(page);
                await gateway.completePayment(page);

                /**
                 * BasePaymentGateway.assertPaymentSucceeded() waits for a /client/payments/
                 * URL, which the checkout page itself already matches. Poll the API for the
                 * server side outcome instead.
                 */
                let after: InvoiceEntity | undefined;

                await expect
                    .poll(
                        async () => {
                            after = await getEntity<InvoiceEntity>(
                                api.context,
                                'invoices',
                                context.invoice.id,
                            );

                            return feeLines(after).length;
                        },
                        {
                            timeout: 90_000,
                            message:
                                'the payment never reached the server, so no gateway fee was recorded',
                        },
                    )
                    .toBe(1);

                after = await getEntity<InvoiceEntity>(
                    api.context,
                    'invoices',
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
            });
        });
    }
});
