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
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Paymentable;
use App\Models\TransactionEvent;
use Illuminate\Validation\ValidationException;

class FrancePaymentReportingMutationGuard
{
    public function assertInvoiceDeletionAllowed(Invoice $invoice): void
    {
        $violation = $this->invoiceDeletionViolation($invoice);

        if ($violation) {
            throw ValidationException::withMessages([
                'id' => [$violation],
            ]);
        }
    }

    public function invoiceDeletionViolation(Invoice $invoice): ?string
    {
        if (! $this->appliesToFrancePeppol($invoice->company)) {
            return null;
        }

        if (app(FranceSubmissionClaim::class)->hasActiveClaimForInvoice($invoice->id)) {
            return ctrans('texts.deletion_violation_regulatory');
        }

        $submitted = TransactionEvent::query()
            ->where('company_id', $invoice->company_id)
            ->where('invoice_id', $invoice->id)
            ->whereIn('event_id', array_merge(
                TransactionEvent::FR_REPORTING_EVENTS,
                TransactionEvent::FR_PAYMENT_NOTIFICATION_EVENTS,
            ))
            ->where('payment_status', TransactionEvent::FR_REPORTING_STATUS_SUBMITTED)
            ->exists();

        if ($submitted) {
            return ctrans('texts.deletion_violation_regulatory');
        }

        return null;
    }

    public function assertPaymentDateChangeAllowed(Payment $payment, string $newDate): void
    {
        if (! $this->appliesToFrancePeppol($payment->company)
            || ! $payment->exists
            || ! $payment->date
            || $payment->date === $newDate) {
            return;
        }

        $paymentableIds = Paymentable::withTrashed()
            ->where('payment_id', $payment->id)
            ->where('paymentable_type', 'invoices')
            ->get()
            ->filter(fn(Paymentable $paymentable): bool => $this->paymentableDate($paymentable, $payment) === $payment->date)
            ->pluck('id')
            ->map(fn($id): int => (int) $id)
            ->all();

        if ($this->hasActiveSubmissionClaim($payment, $paymentableIds)) {
            throw ValidationException::withMessages([
                'date' => ['The payment date cannot be changed while its France payment reporting is being submitted.'],
            ]);
        }

        if ($this->hasSubmittedReporting($payment, $paymentableIds)) {
            throw ValidationException::withMessages([
                'date' => ['The payment date cannot be changed because its France payment reporting has already been submitted.'],
            ]);
        }
    }

    public function assertUserDeletionAllowed(Payment $payment): void
    {
        $violation = $this->paymentDeletionViolation($payment);

        if ($violation) {
            throw ValidationException::withMessages([
                'id' => [$violation],
            ]);
        }
    }

    public function paymentDeletionViolation(Payment $payment): ?string
    {
        if (! $this->appliesToFrancePeppol($payment->company)) {
            return null;
        }

        $paymentableIds = Paymentable::withTrashed()
            ->where('payment_id', $payment->id)
            ->where('paymentable_type', 'invoices')
            ->pluck('id')
            ->map(fn($id): int => (int) $id)
            ->all();

        if ($this->hasActiveSubmissionClaim($payment, $paymentableIds)) {
            return 'The payment cannot be deleted while its France payment reporting is being submitted.';
        }

        if ($this->hasSubmittedReporting($payment, $paymentableIds)) {
            return 'The payment cannot be deleted because its France payment reporting has already been submitted.';
        }

        return null;
    }

    private function appliesToFrancePeppol(?Company $company): bool
    {
        return $company?->country()?->iso_3166_2 === 'FR'
            && $company->getSetting('e_invoice_type') === 'PEPPOL';
    }

    public function assertRefundAllowed(Payment $payment): void
    {
        if (! $this->appliesToFrancePeppol($payment->company)) {
            return;
        }

        $paymentableIds = Paymentable::withTrashed()
            ->where('payment_id', $payment->id)
            ->where('paymentable_type', 'invoices')
            ->pluck('id')
            ->map(fn($id): int => (int) $id)
            ->all();

        if ($this->hasActiveSubmissionClaim($payment, $paymentableIds)) {
            throw ValidationException::withMessages([
                'id' => ['The payment cannot be refunded while its France payment reporting is being submitted.'],
            ]);
        }
    }

    /**
     * @param array<int, int> $paymentableIds
     */
    private function hasActiveSubmissionClaim(Payment $payment, array $paymentableIds): bool
    {
        if ($paymentableIds === []) {
            return false;
        }

        $invoiceIds = Paymentable::withTrashed()
            ->whereIn('id', $paymentableIds)
            ->pluck('paymentable_id')
            ->map(fn($id): int => (int) $id)
            ->unique()
            ->all();

        return collect($invoiceIds)
            ->contains(fn(int $invoiceId): bool => app(FranceSubmissionClaim::class)->hasActiveClaimForInvoice($invoiceId));
    }

    /**
     * @param array<int, int> $paymentableIds
     */
    private function hasSubmittedReporting(Payment $payment, array $paymentableIds): bool
    {
        if ($paymentableIds === []) {
            return false;
        }

        $invoiceIds = Paymentable::withTrashed()
            ->whereIn('id', $paymentableIds)
            ->pluck('paymentable_id')
            ->map(fn($id): int => (int) $id)
            ->unique()
            ->all();

        $submittedNotificationExists = TransactionEvent::query()
            ->where('company_id', $payment->company_id)
            ->whereIn('invoice_id', $invoiceIds)
            ->where('event_id', TransactionEvent::FR_B2B_PAYMENT_RECEIVED_NOTIFICATION)
            ->where('payment_status', TransactionEvent::FR_REPORTING_STATUS_SUBMITTED)
            ->exists();

        if ($submittedNotificationExists) {
            return true;
        }

        $reportIds = TransactionEvent::query()
            ->where('company_id', $payment->company_id)
            ->where('payment_id', $payment->id)
            ->whereIn('invoice_id', $invoiceIds)
            ->whereIn('event_id', [TransactionEvent::FR_B2C_PAYMENT, TransactionEvent::FR_VAT_EXCLUDED_PAYMENT])
            ->orderBy('id')
            ->get()
            ->filter(fn(TransactionEvent $event): bool => data_get($event->payment_request, 'fr_kind') === RecordFranceEReportingPayment::KIND_MOVEMENT
                && in_array((int) data_get($event->payment_request, 'paymentable_id'), $paymentableIds, true))
            ->map(fn(TransactionEvent $event): int => (int) data_get($event->payment_request, 'report_event_id', 0))
            ->filter()
            ->unique()
            ->values();

        return $reportIds->isNotEmpty()
            && TransactionEvent::query()
                ->whereIn('id', $reportIds->all())
                ->where('payment_status', TransactionEvent::FR_REPORTING_STATUS_SUBMITTED)
                ->exists();
    }

    private function paymentableDate(Paymentable $paymentable, Payment $payment): string
    {
        $timezone = $payment->company->timezone()?->name ?: config('app.timezone');

        return app(FrancePaymentApplicationDateResolver::class)
            ->resolve($paymentable, $timezone)
            ?? '';
    }
}
