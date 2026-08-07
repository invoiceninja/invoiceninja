<?php

namespace App\Services\EDocument\Standards\France;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Paymentable;
use App\Models\TransactionEvent;

class FrancePaymentReceivedNotificationEligibility
{
    public function isEligible(TransactionEvent $event): bool
    {
        if ($event->event_id !== TransactionEvent::FR_B2B_PAYMENT_RECEIVED_NOTIFICATION) {
            return false;
        }

        $paymentableId = (int) data_get($event->payment_request, 'paymentable_id', 0);

        if ($paymentableId <= 0) {
            return false;
        }

        $payment = Payment::withTrashed()
            ->with(['client.country', 'client.company', 'company'])
            ->find($event->payment_id);
        $invoice = Invoice::withTrashed()
            ->with(['client.country', 'client.company', 'company'])
            ->find($event->invoice_id);
        $paymentable = Paymentable::withTrashed()->find($paymentableId);

        if (! $payment || ! $invoice || ! $paymentable) {
            return false;
        }

        if ($payment->is_deleted
            || (int) $payment->status_id !== Payment::STATUS_COMPLETED
            || $invoice->is_deleted
            || ! $this->invoiceIsPaidInFull($invoice)) {
            return false;
        }

        if ((int) $event->company_id !== (int) $payment->company_id
            || (int) $event->company_id !== (int) $invoice->company_id
            || (int) $event->client_id !== (int) $payment->client_id
            || (int) $event->client_id !== (int) $invoice->client_id) {
            return false;
        }

        if ($paymentable->trashed()
            || (int) $paymentable->payment_id !== (int) $payment->id
            || (int) $paymentable->paymentable_id !== (int) $invoice->id
            || $paymentable->paymentable_type !== 'invoices') {
            return false;
        }

        $eventGuid = trim((string) data_get($event->payment_request, 'original_document_guid', ''));
        $invoiceGuid = trim((string) ($invoice->backup->guid ?? ''));

        if ($eventGuid === '' || $eventGuid !== $invoiceGuid) {
            return false;
        }

        return $payment->client->reportableFrTransaction()
            && ($payment->client->classification ?? 'business') !== 'individual'
            && $payment->client->country?->iso_3166_2 === 'FR';
    }

    private function invoiceIsPaidInFull(Invoice $invoice): bool
    {
        return (int) $invoice->status_id === Invoice::STATUS_PAID
            || (float) ($invoice->balance ?? 0) <= 0.0;
    }
}
