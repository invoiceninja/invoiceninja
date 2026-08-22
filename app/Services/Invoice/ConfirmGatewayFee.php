<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Invoice;

use App\Models\CompanyGateway;
use App\Models\Invoice;
use App\Models\PaymentHash;
use App\Services\AbstractService;

/**
 * Writes the gateway fee onto the invoice once the payment is confirmed.
 *
 * This is the only writer of gateway fee line items. It is idempotent on the payment
 * hash: several drivers confirm twice for one payment, and webhooks are redelivered.
 *
 * @see CalculateGatewayFee
 */
class ConfirmGatewayFee extends AbstractService
{
    /**
     * Appending to line_items is a read/modify/write, so concurrent confirmations
     * serialise one per round. The ceiling must exceed the number of attempts that can
     * realistically contend for one invoice, or a fee is dropped.
     */
    private const MAX_ATTEMPTS = 12;

    public function __construct(
        private PaymentHash $payment_hash,
        private ?CompanyGateway $company_gateway = null,
        private array $data = []
    ) {
    }

    public function run(): ?Invoice
    {
        if (! $this->payment_hash->fee_total || $this->payment_hash->fee_total == 0 || ! $this->payment_hash->fee_invoice_id) {
            return null;
        }

        for ($i = 1; $i <= self::MAX_ATTEMPTS; $i++) {

            /** @var \App\Models\Invoice|null $invoice */
            $invoice = Invoice::withTrashed()->find($this->payment_hash->fee_invoice_id);

            if (! $invoice) {
                return null;
            }

            /**
             * Idempotency. Mollie, Braintree, GoCardless and CheckoutCom all confirm
             * directly and again via createPayment(); Stripe redelivers webhooks.
             */
            if (collect($invoice->line_items)->contains('unit_code', $this->payment_hash->hash)) {
                return $invoice;
            }

            /**
             * A closed invoice still receives the fee - the payment about to be created is
             * for the fee inclusive amount, and recording one without the other manufactures
             * a discrepancy. Whether a closed invoice should receive a payment at all is a
             * decision for the driver, not for this service.
             */
            if ($invoice->is_deleted || in_array($invoice->status_id, [Invoice::STATUS_CANCELLED, Invoice::STATUS_REVERSED])) {
                nlog("gateway fee confirming onto a closed invoice {$invoice->id} status {$invoice->status_id}");
            }

            /** Invoice::$casts casts updated_at to a unix timestamp, discarding microseconds. */
            $observed_updated_at = $invoice->getRawOriginal('updated_at');
            $starting_amount = (float) $invoice->amount;

            $line_items = (array) $invoice->line_items;
            $line_items[] = CalculateGatewayFee::line(
                $this->company_gateway,
                isset($this->data['gateway_type_id']) ? (int) $this->data['gateway_type_id'] : null,
                $this->netFee($invoice),
                $this->payment_hash->hash,
                $invoice
            );

            $invoice->line_items = array_values($line_items);

            /** Pure calculation - getTempEntity() does not save. */
            $projected = $invoice->calc()->getTempEntity();

            $claimed = Invoice::withTrashed()
                ->where('id', $invoice->id)
                ->where('updated_at', $observed_updated_at)
                ->update([
                    'line_items' => json_encode($projected->line_items),
                    'amount' => $projected->amount,
                    'balance' => $projected->balance,
                    'total_taxes' => $projected->total_taxes,
                    'updated_at' => now()->format('Y-m-d H:i:s.u'),
                ]);

            if ($claimed !== 1) {
                nlog("gateway fee claim lost on invoice {$invoice->id} attempt {$i}");

                /** Jittered backoff so contending writers de-synchronise instead of colliding again. */
                usleep(random_int(1000, 5000) * $i);

                continue;
            }

            return $this->afterCommit($invoice, $starting_amount, (float) $projected->amount);
        }

        /**
         * Never throw - confirmGatewayFee() runs before the payment record is created, so
         * throwing would lose the payment rather than the fee. Alert on this log line.
         */
        nlog("ALERT gateway fee confirmation contended out after " . self::MAX_ATTEMPTS . " attempts on invoice {$this->payment_hash->fee_invoice_id} hash {$this->payment_hash->hash} - the fee was charged but is not on the invoice");

        return Invoice::withTrashed()->find($this->payment_hash->fee_invoice_id);
    }

    /**
     * Side effects run only once the claim succeeds, so a retried attempt cannot post a
     * duplicate ledger row.
     */
    private function afterCommit(Invoice $invoice, float $starting_amount, float $new_amount): Invoice
    {
        $adjustment = round($new_amount - $starting_amount, $invoice->client->currency()->precision);

        if ($adjustment != 0) {
            $invoice->ledger()->updateInvoiceBalance($adjustment, 'Adjustment for adding gateway fee');
            $invoice->client->service()->updateBalance($adjustment);
        }

        $invoice->service()->deleteEInvoice();

        return $invoice->fresh();
    }

    /**
     * The line item cost. Hashes created before the fee became a quote carry only the
     * tax inclusive total, so it has to be reduced back to a cost.
     */
    private function netFee(Invoice $invoice): float
    {
        if (isset($this->payment_hash->data->fee_net)) {
            return (float) $this->payment_hash->data->fee_net;
        }

        $fee_total = (float) $this->payment_hash->fee_total;

        if (! $invoice->uses_inclusive_taxes) {
            $fee_total = round($fee_total / (1 + (($invoice->tax_rate1 + $invoice->tax_rate2 + $invoice->tax_rate3) / 100)), 2);
        }

        return $fee_total;
    }
}
