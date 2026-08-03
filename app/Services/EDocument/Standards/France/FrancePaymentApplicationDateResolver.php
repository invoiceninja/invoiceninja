<?php

namespace App\Services\EDocument\Standards\France;

use App\Models\Payment;
use App\Models\Paymentable;
use Carbon\CarbonImmutable;

class FrancePaymentApplicationDateResolver
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

    public function resolve(?Paymentable $paymentable, ?string $paymentDate, string $timezone): ?string
    {
        if (! $paymentable?->created_at) {
            return null;
        }

        $applicationDate = is_numeric($paymentable->created_at)
            ? CarbonImmutable::createFromTimestamp((int) $paymentable->created_at, 'UTC')
            : CarbonImmutable::parse($paymentable->created_at, 'UTC');

        if ($paymentDate && $applicationDate->toDateString() === $paymentDate) {
            return $paymentDate;
        }

        return $applicationDate->setTimezone($timezone)->toDateString();
    }
}
