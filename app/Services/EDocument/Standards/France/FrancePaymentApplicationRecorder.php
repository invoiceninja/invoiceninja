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

namespace App\Services\EDocument\Standards\France;

use App\Jobs\EDocument\RecordFranceEReportingPayment;
use App\Models\Invoice;
use App\Models\Payment;

class FrancePaymentApplicationRecorder
{
    public function record(Payment $payment, Invoice $invoice): void
    {
        if (! $this->shouldRecord($payment, $invoice)) {
            return;
        }

        RecordFranceEReportingPayment::dispatch(
            $payment->id,
            $payment->company->db,
            $invoice->id,
        )->afterCommit();
    }

    private function shouldRecord(Payment $payment, Invoice $invoice): bool
    {
        if (! $payment->company || $payment->is_deleted || $invoice->is_deleted) {
            return false;
        }

        if (! in_array($payment->status_id, [Payment::STATUS_COMPLETED], true)) {
            return false;
        }

        if (! $this->invoiceIsPaidInFull($invoice)) {
            return false;
        }

        if (! $invoice->client) {
            return false;
        }

        if (! $invoice->client->relationLoaded('company')) {
            $invoice->client->setRelation('company', $payment->company);
        }

        return $invoice->client->reportableFrTransaction();
    }

    private function invoiceIsPaidInFull(Invoice $invoice): bool
    {
        $invoice = $invoice->exists ? ($invoice->fresh() ?? $invoice) : $invoice;

        return (int) $invoice->status_id === Invoice::STATUS_PAID
            || (float) $invoice->balance <= 0.0;
    }
}