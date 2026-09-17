<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Listeners\Invoice;

use App\DataMapper\TransactionEventMetadata;
use App\Models\Invoice;
use App\Models\Paymentable;
use App\Models\TransactionEvent;
use App\Services\Payment\PaymentApplicationDateResolver;
use App\Services\Report\TaxPeriod\SalesBreakdownCalculator;
use App\Services\Report\TaxPeriod\TaxClassificationCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Writes immutable cash-accounting source events for invoice payment applications.
 */
class InvoiceTransactionEventEntryCash
{
    public const SCHEMA_VERSION = 2;

    public function run(?Invoice $invoice, string $start_date, string $end_date): void
    {
        if (! $invoice) {
            return;
        }

        $invoice->loadMissing('company');
        $timezone = $invoice->company->timezone()?->name ?: config('app.timezone');

        $this->eligiblePaymentables($invoice, $start_date, $end_date, $timezone)
            ->each(fn (Paymentable $paymentable): ?TransactionEvent => $this->runForPaymentable($invoice, $paymentable));
    }

    /**
     * @param Collection<int, Paymentable> $paymentables
     */
    public function runForPaymentables(?Invoice $invoice, Collection $paymentables): void
    {
        if (! $invoice) {
            return;
        }

        $paymentables
            ->filter(fn (Paymentable $paymentable): bool => (bool) $paymentable->payment)
            ->each(fn (Paymentable $paymentable): ?TransactionEvent => $this->runForPaymentable($invoice, $paymentable));
    }

    public function runForPaymentable(
        ?Invoice $invoice,
        Paymentable $paymentable,
        ?string $effective_date = null,
    ): ?TransactionEvent {
        if (! $invoice) {
            return null;
        }

        return DB::transaction(function () use ($invoice, $paymentable, $effective_date): ?TransactionEvent {
            $locked_invoice = Invoice::withTrashed()->lockForUpdate()->find($invoice->id);

            if (! $locked_invoice) {
                return null;
            }

            $paymentable->loadMissing('payment');

            $existing = $this->findSourceEvent($locked_invoice->id, $paymentable->id);

            if ($existing) {
                return $existing;
            }

            $locked_invoice->loadMissing(['client', 'company']);
            $timezone = $locked_invoice->company->timezone()?->name ?: config('app.timezone');
            $effective_date ??= $this->paymentableDate($paymentable, $timezone);
            $period = CarbonImmutable::parse($effective_date, $timezone)->endOfMonth()->toDateString();

            if ($this->legacyPeriodExists($locked_invoice->id, $period)) {
                return null;
            }

            return TransactionEvent::create($this->sourceAttributes(
                $locked_invoice,
                $paymentable,
                $effective_date,
                $period,
            ));
        }, attempts: 3);
    }

    /**
     * @param array<int, int> $paymentable_ids
     */
    public function reconcileApplicationDateChange(
        int $invoice_id,
        int $payment_id,
        string $old_date,
        string $new_date,
        array $paymentable_ids,
    ): void {
        if ($old_date === $new_date) {
            return;
        }

        $invoice = Invoice::withTrashed()->find($invoice_id);

        if (! $invoice) {
            return;
        }

        Paymentable::withTrashed()
            ->with(['payment' => fn ($query) => $query->withTrashed()])
            ->where('payment_id', $payment_id)
            ->where('paymentable_type', 'invoices')
            ->where('paymentable_id', $invoice_id)
            ->whereIn('id', $paymentable_ids)
            ->get()
            ->each(function (Paymentable $paymentable) use ($invoice, $old_date, $new_date): void {
                $source = $this->findSourceEvent($invoice->id, $paymentable->id)
                    ?? $this->runForPaymentable($invoice, $paymentable, $old_date);

                if (! $source) {
                    return;
                }

                $correction_base = implode('|', [
                    'payment_application_date',
                    $paymentable->id,
                    $old_date,
                    $new_date,
                ]);

                $this->writeCorrection(
                    $source,
                    $old_date,
                    -1,
                    'payment_application_date',
                    sha1($correction_base.'|remove'),
                    [
                        'old_date' => $old_date,
                        'new_date' => $new_date,
                        'old_period' => CarbonImmutable::parse($old_date)->endOfMonth()->toDateString(),
                        'new_period' => CarbonImmutable::parse($new_date)->endOfMonth()->toDateString(),
                        'direction' => 'remove',
                    ],
                );
                $this->writeCorrection(
                    $source,
                    $new_date,
                    1,
                    'payment_application_date',
                    sha1($correction_base.'|apply'),
                    [
                        'old_date' => $old_date,
                        'new_date' => $new_date,
                        'old_period' => CarbonImmutable::parse($old_date)->endOfMonth()->toDateString(),
                        'new_period' => CarbonImmutable::parse($new_date)->endOfMonth()->toDateString(),
                        'direction' => 'apply',
                    ],
                );
            });
    }

    /**
     * @param array<string, mixed> $context
     */
    public function writeCorrection(
        TransactionEvent $source,
        string $effective_date,
        int $sign,
        string $kind,
        string $correction_key,
        array $context = [],
        ?float $amount = null,
    ): TransactionEvent {
        return DB::transaction(function () use ($source, $effective_date, $sign, $kind, $correction_key, $context, $amount): TransactionEvent {
            $event_id = match ($kind) {
                'payment_refunded' => TransactionEvent::PAYMENT_REFUNDED,
                'payment_deleted' => TransactionEvent::PAYMENT_DELETED,
                default => TransactionEvent::PAYMENT_CASH,
            };
            $candidates = TransactionEvent::query()
                ->where('invoice_id', $source->invoice_id)
                ->where('payment_id', $source->payment_id)
                ->lockForUpdate()
                ->get();
            $current_application_date = (string) data_get($source->payment_request, 'effective_date', '');
            $latest_date_change = $candidates
                ->filter(fn (TransactionEvent $event): bool => (int) data_get($event->payment_request, 'source_event_id') === (int) $source->id
                    && data_get($event->payment_request, 'tax_correction_kind') === 'payment_application_date'
                    && data_get($event->payment_request, 'direction') === 'apply')
                ->sortByDesc('id')
                ->first();

            if ($latest_date_change) {
                $current_application_date = (string) data_get($latest_date_change->payment_request, 'effective_date', $current_application_date);
            }

            if ($kind !== 'payment_application_date'
                && $current_application_date !== ''
                && $effective_date < $current_application_date) {
                $effective_date = $current_application_date;
            }

            $existing = $candidates->first(
                fn (TransactionEvent $event): bool => data_get($event->payment_request, 'correction_key') === $correction_key,
            );

            if ($existing) {
                return $existing;
            }

            $source_amount = abs((float) $source->payment_applied);
            $correction_amount = $amount === null ? $source_amount : min(abs($amount), $source_amount);
            $multiplier = $source_amount > 0 ? ($correction_amount / $source_amount) * $sign : 0.0;
            $metadata = $this->scaledMetadata($source, $effective_date, $multiplier, $correction_amount * $sign);
            $period = CarbonImmutable::parse($effective_date)->endOfMonth()->toDateString();

            return TransactionEvent::create([
                'company_id' => $source->company_id,
                'invoice_id' => $source->invoice_id,
                'client_id' => $source->client_id,
                'client_balance' => $source->client_balance,
                'client_paid_to_date' => $source->client_paid_to_date,
                'client_credit_balance' => $source->client_credit_balance,
                'invoice_balance' => $source->invoice_balance,
                'invoice_amount' => $source->invoice_amount,
                'invoice_partial' => $source->invoice_partial,
                'invoice_paid_to_date' => $correction_amount * $sign,
                'invoice_status' => $source->invoice_status,
                'payment_id' => $source->payment_id,
                'payment_amount' => $correction_amount * $sign,
                'payment_applied' => $correction_amount * $sign,
                'payment_refunded' => $kind === 'payment_refunded' ? $correction_amount : 0,
                'payment_status' => $source->payment_status,
                'event_id' => $event_id,
                'timestamp' => now('UTC')->timestamp,
                'metadata' => $metadata,
                'payment_request' => [
                    'schema_version' => self::SCHEMA_VERSION,
                    'source_paymentable_id' => data_get($source->payment_request, 'source_paymentable_id'),
                    'source_event_id' => $source->id,
                    'source_effective_date' => data_get($source->payment_request, 'effective_date'),
                    'source_period' => $source->period->toDateString(),
                    'effective_date' => $effective_date,
                    'tax_correction_kind' => $kind,
                    'correction_key' => $correction_key,
                    ...$context,
                ],
                'period' => $period,
            ]);
        }, attempts: 3);
    }

    /**
     * @return Collection<int, Paymentable>
     */
    private function eligiblePaymentables(
        Invoice $invoice,
        string $start_date,
        string $end_date,
        string $timezone,
    ): Collection {
        [$query_start, $query_end] = app(PaymentApplicationDateResolver::class)
            ->candidateBounds($start_date, $end_date, $timezone);

        return Paymentable::query()
            ->with(['payment' => fn ($query) => $query->withTrashed()])
            ->where('paymentable_type', 'invoices')
            ->where('paymentable_id', $invoice->id)
            ->whereNull('deleted_at')
            ->where('created_at', '>=', $query_start)
            ->where('created_at', '<', $query_end)
            ->whereHas('payment', fn ($query) => $query->withTrashed()->where('is_deleted', false))
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->filter(function (Paymentable $paymentable) use ($start_date, $end_date, $timezone): bool {
                $date = $this->paymentableDate($paymentable, $timezone);

                return $date >= $start_date && $date <= $end_date;
            })
            ->values();
    }

    public function findSourceEvent(int $invoice_id, int $paymentable_id): ?TransactionEvent
    {
        return TransactionEvent::query()
            ->where('invoice_id', $invoice_id)
            ->where('event_id', TransactionEvent::PAYMENT_CASH)
            ->get()
            ->first(fn (TransactionEvent $event): bool => (int) data_get($event->payment_request, 'schema_version') === self::SCHEMA_VERSION
                && (int) data_get($event->payment_request, 'source_paymentable_id') === $paymentable_id
                && ! data_get($event->payment_request, 'tax_correction_kind'));
    }

    private function legacyPeriodExists(int $invoice_id, string $period): bool
    {
        return TransactionEvent::query()
            ->where('invoice_id', $invoice_id)
            ->where('event_id', TransactionEvent::PAYMENT_CASH)
            ->whereDate('period', $period)
            ->get()
            ->contains(fn (TransactionEvent $event): bool => ! data_get($event->payment_request, 'schema_version'));
    }

    /**
     * @return array<string, mixed>
     */
    private function sourceAttributes(
        Invoice $invoice,
        Paymentable $paymentable,
        string $effective_date,
        string $period,
    ): array {
        $payment = $paymentable->payment;
        $payment_history = collect([[
            'paymentable_id' => (int) $paymentable->id,
            'number' => (string) $payment->number,
            'amount' => (float) $paymentable->amount,
            'refunded' => 0.0,
            'date' => $effective_date,
            'exchange_rate' => (float) ($payment->exchange_rate ?: 1),
            'currency_id' => (int) $payment->currency_id,
        ]]);

        return [
            'company_id' => $invoice->company_id,
            'invoice_id' => $invoice->id,
            'client_id' => $invoice->client_id,
            'client_balance' => $invoice->client->balance,
            'client_paid_to_date' => $invoice->client->paid_to_date,
            'client_credit_balance' => $invoice->client_credit_balance ?? $invoice->client->credit_balance,
            'invoice_balance' => $invoice->balance ?? 0,
            'invoice_amount' => $invoice->amount ?? 0,
            'invoice_partial' => $invoice->partial ?? 0,
            'invoice_paid_to_date' => $invoice->paid_to_date ?? 0,
            'invoice_status' => $invoice->is_deleted ? 7 : $invoice->status_id,
            'payment_id' => $payment->id,
            'payment_refunded' => 0,
            'payment_applied' => $paymentable->amount,
            'payment_amount' => $paymentable->amount,
            'payment_status' => $payment->status_id,
            'event_id' => TransactionEvent::PAYMENT_CASH,
            'timestamp' => now('UTC')->timestamp,
            'metadata' => $this->sourceMetadata($invoice, $paymentable, $payment_history),
            'payment_request' => [
                'schema_version' => self::SCHEMA_VERSION,
                'source_paymentable_id' => (int) $paymentable->id,
                'source_key' => 'payment_application:'.$paymentable->id,
                'effective_date' => $effective_date,
            ],
            'period' => $period,
        ];
    }

    private function metadata(Invoice $invoice, float $ratio, Collection $payment_history): TransactionEventMetadata
    {
        $calc = $invoice->calc();
        $details = [];
        $taxes = array_merge($calc->getTaxMap()->merge($calc->getTotalTaxMap())->toArray());

        foreach ($taxes as $tax) {
            $details[] = [
                'tax_name' => $tax['name'],
                'tax_rate' => $tax['tax_rate'],
                'taxable_amount' => ($tax['base_amount'] ?? $calc->getNetSubtotal()) * $ratio,
                'tax_amount' => $tax['total'] * $ratio,
                'line_total' => ($tax['base_amount'] ?? $calc->getNetSubtotal()),
                'total_tax' => $tax['total'],
                'postal_code' => $invoice->client->postal_code,
            ];
        }

        return new TransactionEventMetadata([
            'tax_report' => [
                'tax_details' => $details,
                'tax_details_by_classification' => TaxClassificationCalculator::calculate($invoice, $ratio, $details),
                'sales_breakdown' => SalesBreakdownCalculator::calculate($invoice, $ratio),
                'sales_totals' => SalesBreakdownCalculator::summaryTotals($invoice, $ratio),
                'payment_history' => $payment_history->all(),
                'tax_summary' => [
                    'tax_amount' => $invoice->total_taxes * $ratio,
                    'status' => 'updated',
                    'taxable_amount' => $calc->getNetSubtotal() * $ratio,
                ],
            ],
        ]);
    }

    private function sourceMetadata(
        Invoice $invoice,
        Paymentable $paymentable,
        Collection $payment_history,
    ): TransactionEventMetadata {
        $ratio = (float) $invoice->amount !== 0.0
            ? (float) $paymentable->amount / (float) $invoice->amount
            : 0.0;
        $metadata = $this->metadata($invoice, $ratio, $payment_history);
        $existing_sources = TransactionEvent::query()
            ->where('invoice_id', $invoice->id)
            ->where('event_id', TransactionEvent::PAYMENT_CASH)
            ->get()
            ->filter(fn (TransactionEvent $event): bool => (int) data_get($event->payment_request, 'schema_version') === self::SCHEMA_VERSION
                && ! data_get($event->payment_request, 'tax_correction_kind'));
        $cumulative_amount = (float) $existing_sources->sum('payment_applied') + (float) $paymentable->amount;

        if (abs($cumulative_amount - (float) $invoice->amount) > 0.005) {
            return $metadata;
        }

        $full = $this->metadata($invoice, 1.0, $payment_history)->toArray();
        $tax_report = $full['tax_report'];
        $tax_report['tax_summary']['taxable_amount'] -= $existing_sources->sum(
            fn (TransactionEvent $event): float => (float) ($event->metadata->tax_report->tax_summary->taxable_amount ?? 0),
        );
        $tax_report['tax_summary']['tax_amount'] -= $existing_sources->sum(
            fn (TransactionEvent $event): float => (float) ($event->metadata->tax_report->tax_summary->tax_amount ?? 0),
        );

        foreach (['tax_details', 'tax_details_by_classification'] as $key) {
            $rows = $tax_report[$key] ?? [];
            foreach ($rows as $index => &$row) {
                foreach (['taxable_amount', 'tax_amount'] as $amount_key) {
                    $row[$amount_key] = (float) ($row[$amount_key] ?? 0) - $existing_sources->sum(
                        fn (TransactionEvent $event): float => (float) data_get(
                            $event->metadata->toArray(),
                            "tax_report.{$key}.{$index}.{$amount_key}",
                            0,
                        ),
                    );
                }
            }
            unset($row);
            $tax_report[$key] = $rows;
        }

        $sales_breakdown = $tax_report['sales_breakdown'] ?? [];
        foreach ($sales_breakdown as $index => &$row) {
            foreach (['gross_sales', 'taxable_sales', 'exempt_sales', 'non_taxable_sales', 'zero_rated_sales', 'tax_amount'] as $amount_key) {
                $row[$amount_key] = (float) ($row[$amount_key] ?? 0) - $existing_sources->sum(
                    fn (TransactionEvent $event): float => (float) data_get(
                        $event->metadata->toArray(),
                        "tax_report.sales_breakdown.{$index}.{$amount_key}",
                        0,
                    ),
                );
            }
        }
        unset($row);
        $tax_report['sales_breakdown'] = $sales_breakdown;

        foreach ($tax_report['sales_totals'] ?? [] as $key => $amount) {
            $tax_report['sales_totals'][$key] = (float) $amount - $existing_sources->sum(
                fn (TransactionEvent $event): float => (float) data_get(
                    $event->metadata->toArray(),
                    "tax_report.sales_totals.{$key}",
                    0,
                ),
            );
        }
        $tax_report['payment_history'] = $payment_history->all();

        return new TransactionEventMetadata(['tax_report' => $tax_report]);
    }

    private function scaledMetadata(
        TransactionEvent $source,
        string $effective_date,
        float $multiplier,
        float $payment_amount,
    ): TransactionEventMetadata {
        $metadata = $source->metadata->toArray();
        $tax_report = $metadata['tax_report'];
        $tax_report['tax_summary']['taxable_amount'] *= $multiplier;
        $tax_report['tax_summary']['tax_amount'] *= $multiplier;
        $tax_report['tax_summary']['status'] = 'adjustment';

        foreach (['tax_details', 'tax_details_by_classification'] as $key) {
            $rows = $tax_report[$key] ?? [];
            foreach ($rows as &$row) {
                $row['taxable_amount'] = (float) ($row['taxable_amount'] ?? 0) * $multiplier;
                $row['tax_amount'] = (float) ($row['tax_amount'] ?? 0) * $multiplier;
            }
            unset($row);
            $tax_report[$key] = $rows;
        }

        $sales_breakdown = $tax_report['sales_breakdown'] ?? [];
        foreach ($sales_breakdown as &$row) {
            foreach (['gross_sales', 'taxable_sales', 'exempt_sales', 'non_taxable_sales', 'zero_rated_sales', 'tax_amount'] as $key) {
                $row[$key] = (float) ($row[$key] ?? 0) * $multiplier;
            }
        }
        unset($row);
        $tax_report['sales_breakdown'] = $sales_breakdown;

        foreach ($tax_report['sales_totals'] ?? [] as $key => $amount) {
            $tax_report['sales_totals'][$key] = (float) $amount * $multiplier;
        }

        $payment_history = $tax_report['payment_history'][0] ?? [];
        $payment_history['date'] = $effective_date;
        $payment_history['amount'] = $payment_amount;
        $payment_history['refunded'] = 0.0;
        $tax_report['payment_history'] = [$payment_history];

        return new TransactionEventMetadata(['tax_report' => $tax_report]);
    }

    private function paymentableDate(Paymentable $paymentable, string $timezone): string
    {
        return app(PaymentApplicationDateResolver::class)->resolve($paymentable, $timezone)
            ?? throw new \RuntimeException('Payment application date is unavailable.');
    }
}
