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

namespace App\Listeners\Invoice;

use App\DataMapper\TransactionEventMetadata;
use App\Models\Invoice;
use App\Models\Paymentable;
use App\Models\TransactionEvent;
use App\Services\EDocument\Standards\France\FrancePaymentApplicationDateResolver;
use App\Services\Report\TaxPeriod\SalesBreakdownCalculator;
use App\Services\Report\TaxPeriod\TaxClassificationCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Handles entries for vanilla payments on an invoice.
 * Used for end of month aggregation of cash payments.
 */
class InvoiceTransactionEventEntryCash
{
    private Collection $payments;

    private float $paid_ratio;

    public function run(?Invoice $invoice, string $start_date, string $end_date): void
    {
        if (! $invoice) {
            return;
        }

        DB::transaction(function () use ($invoice, $start_date, $end_date): void {
            $lockedInvoice = Invoice::withTrashed()->lockForUpdate()->find($invoice->id);

            if (! $lockedInvoice) {
                return;
            }

            $exists = TransactionEvent::query()
                ->where('invoice_id', $lockedInvoice->id)
                ->where('event_id', TransactionEvent::PAYMENT_CASH)
                ->whereDate('period', $end_date)
                ->lockForUpdate()
                ->exists();

            if (! $exists) {
                $this->writePeriodSnapshot($lockedInvoice, $start_date, $end_date, false);
            }
        }, attempts: 3);
    }

    /**
     * @param array<int, int> $paymentableIds
     */
    public function reconcileApplicationDateChange(
        int $invoiceId,
        int $paymentId,
        string $oldDate,
        string $newDate,
        array $paymentableIds,
    ): void {
        DB::transaction(function () use ($invoiceId, $paymentId, $oldDate, $newDate, $paymentableIds): void {
            $invoice = Invoice::withTrashed()->lockForUpdate()->find($invoiceId);

            if (! $invoice) {
                return;
            }

            $invoice->loadMissing('company');
            $timezone = $invoice->company->timezone()?->name ?: config('app.timezone');
            $periods = collect([$oldDate, $newDate])
                ->merge(
                    Paymentable::withTrashed()
                        ->where('payment_id', $paymentId)
                        ->where('paymentable_type', 'invoices')
                        ->where('paymentable_id', $invoiceId)
                        ->whereIn('id', $paymentableIds)
                        ->get()
                        ->map(fn(Paymentable $paymentable): string => $this->paymentableDate($paymentable, $timezone)),
                )
                ->filter()
                ->map(fn(string $date): string => CarbonImmutable::parse($date, $timezone)->endOfMonth()->toDateString())
                ->unique()
                ->sort()
                ->values();

            $oldPeriod = CarbonImmutable::parse($oldDate, $timezone)->endOfMonth()->toDateString();
            $newPeriod = CarbonImmutable::parse($newDate, $timezone)->endOfMonth()->toDateString();
            $correction = $oldPeriod === $newPeriod
                ? null
                : $this->correctionProvenance($invoiceId, $paymentId, $oldPeriod, $newPeriod, $paymentableIds);

            foreach ($periods as $periodEnd) {
                $period = CarbonImmutable::parse($periodEnd, $timezone);

                $this->writePeriodSnapshot(
                    $invoice,
                    $period->startOfMonth()->toDateString(),
                    $period->endOfMonth()->toDateString(),
                    true,
                    $periodEnd === $newPeriod ? $correction : null,
                );
            }
        }, attempts: 3);
    }

    /**
     * @param array<string, mixed>|null $correction
     */
    private function writePeriodSnapshot(
        Invoice $invoice,
        string $startDate,
        string $endDate,
        bool $deleteWhenEmpty,
        ?array $correction = null,
    ): void {
        $invoice->loadMissing(['client', 'company']);
        $this->payments = $this->eligiblePayments($invoice, $startDate, $endDate);

        $events = TransactionEvent::query()
            ->where('invoice_id', $invoice->id)
            ->where('event_id', TransactionEvent::PAYMENT_CASH)
            ->whereDate('period', $endDate)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        if ($deleteWhenEmpty && $this->payments->isEmpty()) {
            $events->each->delete();

            return;
        }

        $this->setPaidRatio($invoice);
        $event = $events->shift() ?? new TransactionEvent();
        $request = $event->payment_request ?? [];

        if ($correction) {
            $request = [...$request, ...$correction];
        }

        $event->fill([
            'company_id' => $invoice->company_id,
            'invoice_id' => $invoice->id,
            'client_id' => $invoice->client_id,
            'client_balance' => $invoice->client->balance,
            'client_paid_to_date' => $invoice->client->paid_to_date,
            'client_credit_balance' => $invoice->client->credit_balance,
            'invoice_balance' => $invoice->balance ?? 0,
            'invoice_amount' => $invoice->amount ?? 0,
            'invoice_partial' => $invoice->partial ?? 0,
            'invoice_paid_to_date' => $invoice->paid_to_date ?? 0,
            'invoice_status' => $invoice->is_deleted ? 7 : $invoice->status_id,
            'payment_refunded' => $this->payments->sum('refunded'),
            'payment_applied' => $this->payments->sum('amount'),
            'payment_amount' => $this->payments->sum('amount'),
            'event_id' => TransactionEvent::PAYMENT_CASH,
            'timestamp' => now()->timestamp,
            'metadata' => $this->getMetadata($invoice),
            'payment_request' => $request ?: null,
            'period' => $endDate,
        ]);
        $event->save();
        $events->each->delete();
    }

    /**
     * @return Collection<int, array{number:string, amount:float, refunded:float, date:string}>
     */
    private function eligiblePayments(Invoice $invoice, string $startDate, string $endDate): Collection
    {
        $timezone = $invoice->company->timezone()?->name ?: config('app.timezone');
        $startUtc = CarbonImmutable::parse($startDate, $timezone)->startOfDay()->utc();
        $nextPeriodUtc = CarbonImmutable::parse($endDate, $timezone)->addDay()->startOfDay()->utc();
        $dateDerivedStartUtc = CarbonImmutable::parse($startDate, 'UTC')->startOfDay();
        $dateDerivedNextPeriodUtc = CarbonImmutable::parse($endDate, 'UTC')->addDay()->startOfDay();
        $queryStart = $startUtc->lessThan($dateDerivedStartUtc) ? $startUtc : $dateDerivedStartUtc;
        $queryEnd = $nextPeriodUtc->greaterThan($dateDerivedNextPeriodUtc) ? $nextPeriodUtc : $dateDerivedNextPeriodUtc;

        return Paymentable::query()
            ->with(['payment' => fn($query) => $query->withTrashed()])
            ->where('paymentable_type', 'invoices')
            ->where('paymentable_id', $invoice->id)
            ->whereNull('deleted_at')
            ->where('created_at', '>=', $queryStart)
            ->where('created_at', '<', $queryEnd)
            ->whereHas('payment', fn($query) => $query->withTrashed()->where('is_deleted', false))
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->filter(function (Paymentable $paymentable) use ($startDate, $endDate, $timezone): bool {
                $date = $this->paymentableDate($paymentable, $timezone);

                return $date >= $startDate && $date <= $endDate;
            })
            ->map(fn(Paymentable $paymentable): array => [
                'number' => (string) $paymentable->payment->number,
                'amount' => $paymentable->amount,
                'refunded' => $paymentable->refunded,
                'date' => $this->paymentableDate($paymentable, $timezone),
            ]);
    }

    private function paymentableDate(Paymentable $paymentable, string $timezone): string
    {
        return app(FrancePaymentApplicationDateResolver::class)
            ->resolve($paymentable, $paymentable->payment->date, $timezone)
            ?? throw new \RuntimeException('Payment application date is unavailable.');
    }

    /**
     * @param array<int, int> $paymentableIds
     * @return array<string, mixed>
     */
    private function correctionProvenance(
        int $invoiceId,
        int $paymentId,
        string $oldPeriod,
        string $newPeriod,
        array $paymentableIds,
    ): array {
        $paymentableIds = collect($paymentableIds)->map(fn($id): int => (int) $id)->sort()->values()->all();

        return [
            'tax_correction_kind' => 'payment_application_date',
            'old_period' => $oldPeriod,
            'new_period' => $newPeriod,
            'payment_id' => $paymentId,
            'paymentable_ids' => $paymentableIds,
            'correction_key' => sha1(implode('|', [
                $invoiceId,
                $paymentId,
                $oldPeriod,
                $newPeriod,
                implode(',', $paymentableIds),
            ])),
        ];
    }

    private function setPaidRatio(Invoice $invoice): self
    {
        if ($invoice->amount == 0) {
            $this->paid_ratio = 0;

            return $this;
        }

        $periodPaid = $this->payments->sum('amount') - $this->payments->sum('refunded');
        $this->paid_ratio = $periodPaid / $invoice->amount;

        return $this;
    }

    private function getMetadata(Invoice $invoice): TransactionEventMetadata
    {
        $calc = $invoice->calc();
        $details = [];
        $taxes = array_merge($calc->getTaxMap()->merge($calc->getTotalTaxMap())->toArray());

        foreach ($taxes as $tax) {
            $details[] = [
                'tax_name' => $tax['name'],
                'tax_rate' => $tax['tax_rate'],
                'taxable_amount' => ($tax['base_amount'] ?? $calc->getNetSubtotal()) * $this->paid_ratio,
                'tax_amount' => $tax['total'] * $this->paid_ratio,
                'line_total' => ($tax['base_amount'] ?? $calc->getNetSubtotal()),
                'total_tax' => $tax['total'],
                'postal_code' => $invoice->client->postal_code,
            ];
        }

        return new TransactionEventMetadata([
            'tax_report' => [
                'tax_details' => $details,
                'tax_details_by_classification' => TaxClassificationCalculator::calculate($invoice, $this->paid_ratio, $details),
                'sales_breakdown' => SalesBreakdownCalculator::calculate($invoice, $this->paid_ratio),
                'payment_history' => $this->payments->toArray(),
                'tax_summary' => [
                    'tax_amount' => $invoice->total_taxes * $this->paid_ratio,
                    'status' => 'updated',
                    'taxable_amount' => $calc->getNetSubtotal() * $this->paid_ratio,
                ],
            ],
        ]);
    }
}
