<?php

namespace App\Services\EDocument\Standards\France;

use App\Models\Payment;
use App\Models\Paymentable;
use App\Services\Payment\PaymentApplicationDateResolver;

class FrancePaymentApplicationDateResolver extends PaymentApplicationDateResolver
{
    public function latestCompletedInvoiceApplication(int $invoice_id): ?Paymentable
    {
        $paymentable = Paymentable::query()
            ->with(['payment' => fn ($query) => $query->withTrashed()])
            ->where('paymentable_type', 'invoices')
            ->where('paymentable_id', $invoice_id)
            ->whereNull('deleted_at')
            ->whereHas('payment', fn ($query) => $query
                ->withTrashed()
                ->where('is_deleted', false)
                ->where('status_id', Payment::STATUS_COMPLETED))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        return $paymentable?->payment ? $paymentable : null;
    }

}
