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

use App\Jobs\Util\SystemLogger;
use App\Models\Invoice;
use App\Models\PaymentHash;
use App\Models\SystemLog;
use App\Services\AbstractService;
use App\Utils\Ninja;

/**
 * Removes a confirmed gateway fee from an invoice when the payment that carried it never
 * settled.
 *
 * Async methods (ACH, SEPA, BECS) create a PENDING payment when the gateway reports the
 * debit as processing, and the fee is confirmed onto the invoice at that point. When the
 * debit later fails, the payment is unwound - and the surcharge has to come off with it,
 * or the client is billed a fee for a payment that never happened.
 *
 * Idempotent: the line is identified by the payment hash, so a redelivered failure
 * webhook finds nothing left to remove.
 *
 * @see ConfirmGatewayFee the mirror of this service
 */
class ReverseGatewayFee extends AbstractService
{
    /**
     * Removing from line_items is a read/modify/write, so a reversal contends with any
     * confirmation writing to the same invoice. Matches ConfirmGatewayFee's ceiling.
     */
    private const MAX_ATTEMPTS = 12;

    public function __construct(private PaymentHash $payment_hash)
    {
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

            $existing = collect($invoice->line_items)
                            ->first(fn ($item) => ($item->unit_code ?? '') === $this->payment_hash->hash);

            /**
             * Idempotency. Nothing to reverse: the failure webhook was redelivered, or the
             * attempt never got as far as confirming a fee.
             */
            if (! $existing) {
                return $invoice;
            }

            /**
             * The fee only comes off once the payment itself is off the invoice. Every
             * driver failure path deletes the pending payment before marking it failed; if
             * one has not, removing the fee would leave the invoice short by that amount.
             */
            if (! $this->paymentIsUnapplied($invoice)) {
                nlog("gateway fee reversal skipped on invoice {$invoice->id} hash {$this->payment_hash->hash} - the payment is still applied");

                return $invoice;
            }

            /** Invoice::$casts casts updated_at to a unix timestamp, discarding microseconds. */
            $observed_updated_at = $invoice->getRawOriginal('updated_at');
            $starting_amount = (float) $invoice->amount;

            $invoice->line_items = collect($invoice->line_items)
                                        ->reject(fn ($item) => ($item->unit_code ?? '') === $this->payment_hash->hash)
                                        ->values()
                                        ->all();

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
                nlog("gateway fee reversal claim lost on invoice {$invoice->id} attempt {$i}");

                /** Jittered backoff so contending writers de-synchronise instead of colliding again. */
                usleep(random_int(1000, 5000) * $i);

                continue;
            }

            return $this->afterCommit($invoice, $starting_amount, (float) $projected->amount);
        }

        /**
         * Never throw - this runs from a payment observer on the failure path, and throwing
         * would abort the failure handling rather than the reversal.
         */
        $invoice = Invoice::withTrashed()->find($this->payment_hash->fee_invoice_id);

        $this->alertFeeIsStranded(
            $invoice,
            "gateway fee reversal contended out after " . self::MAX_ATTEMPTS . " attempts on invoice {$this->payment_hash->fee_invoice_id} hash {$this->payment_hash->hash} - the fee is on the invoice for a payment that failed"
        );

        return $invoice;
    }

    /**
     * Raises the alert for a fee left on an invoice for a payment that never settled.
     *
     * Reported where it happens rather than left for log scraping: a system log the
     * company can see, and Sentry on hosted.
     */
    private function alertFeeIsStranded(?Invoice $invoice, string $message): void
    {
        nlog("ALERT {$message}");

        if (Ninja::isHosted()) {
            \Sentry\captureMessage($message);
        }

        if (! $invoice) {
            return;
        }

        SystemLogger::dispatch(
            [
                'message' => $message,
                'payment_hash' => $this->payment_hash->hash,
                'fee_total' => $this->payment_hash->fee_total,
                'invoice_id' => $invoice->hashed_id,
            ],
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_PAYMENT_RECONCILIATION_FAILURE,
            SystemLog::TYPE_FAILURE,
            $invoice->client,
            $invoice->company
        );
    }

    /**
     * Whether the failed payment has been taken off the invoice already.
     *
     * deletePayment() force deletes the paymentables, so a payment that has been unwound
     * has no live link to the invoice.
     */
    private function paymentIsUnapplied(Invoice $invoice): bool
    {
        $payment = $this->payment_hash->payment;

        if (! $payment) {
            return true;
        }

        return ! $payment->invoices()
                         ->where('invoices.id', $invoice->id)
                         ->exists();
    }

    /**
     * Side effects run only once the claim succeeds, so a retried attempt cannot post a
     * duplicate ledger row.
     */
    private function afterCommit(Invoice $invoice, float $starting_amount, float $new_amount): Invoice
    {
        $adjustment = round($new_amount - $starting_amount, $invoice->client->currency()->precision);

        if ($adjustment != 0) {
            $invoice->ledger()->updateInvoiceBalance($adjustment, 'Adjustment for reversing gateway fee');
            $invoice->client->service()->updateBalance($adjustment);
        }

        $invoice->service()->deleteEInvoice();

        return $invoice->fresh();
    }
}
