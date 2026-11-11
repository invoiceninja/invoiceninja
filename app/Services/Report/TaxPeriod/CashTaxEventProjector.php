<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://www.invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Report\TaxPeriod;

use App\DataMapper\TaxReport\PaymentHistory;
use App\DataMapper\TransactionEventMetadata;
use App\Models\TransactionEvent;
use Illuminate\Support\Collection;

final class CashTaxEventProjector
{
    /**
     * @param Collection<int, TransactionEvent> $events
     * @return Collection<int, TransactionEvent>
     */
    public function aggregateForFilingPeriod(Collection $events): Collection
    {
        $legacy = $events->filter(
            fn (TransactionEvent $event): bool => (int) data_get($event->payment_request, 'schema_version') !== 2,
        );
        $version_two = $events->filter(
            fn (TransactionEvent $event): bool => (int) data_get($event->payment_request, 'schema_version') === 2,
        );

        $aggregated = $version_two
            ->groupBy(fn (TransactionEvent $event): string => implode('|', [
                data_get($event->payment_request, 'source_paymentable_id'),
                $event->period->toDateString(),
            ]))
            ->flatMap(function (Collection $group): array {
                $source = $group->first(
                    fn (TransactionEvent $event): bool => ! data_get($event->payment_request, 'tax_correction_kind'),
                );

                if (! $source || $group->count() === 1) {
                    return $group->all();
                }

                $amount = (float) $group->sum('payment_applied');

                if (abs($amount) < 0.0001) {
                    return [];
                }

                $event = clone $source;
                $event->metadata = $this->combinedMetadata($group, $amount);
                $event->payment_amount = $amount;
                $event->payment_applied = $amount;
                $event->payment_refunded = 0;
                $event->invoice_paid_to_date = $amount;

                return [$event];
            });

        return $legacy->concat($aggregated)->sortBy([['period', 'asc'], ['timestamp', 'asc']])->values();
    }

    public function reportingEvent(TransactionEvent $event): TransactionEvent
    {
        if ((int) data_get($event->payment_request, 'schema_version') !== 2) {
            return $event;
        }

        $payment = $event->metadata->tax_report->payment_history?->first();
        $exchange_rate = (float) ($payment?->exchange_rate ?: 1);

        if ($exchange_rate === 1.0) {
            return $event;
        }

        $converted = clone $event;
        $metadata = $event->metadata->toArray();
        $tax_report = $metadata['tax_report'];
        $tax_report['tax_summary']['taxable_amount'] *= $exchange_rate;
        $tax_report['tax_summary']['tax_amount'] *= $exchange_rate;

        foreach (['tax_details', 'tax_details_by_classification'] as $key) {
            $rows = $tax_report[$key] ?? [];
            foreach ($rows as &$row) {
                $row['taxable_amount'] = (float) ($row['taxable_amount'] ?? 0) * $exchange_rate;
                $row['tax_amount'] = (float) ($row['tax_amount'] ?? 0) * $exchange_rate;
            }
            unset($row);
            $tax_report[$key] = $rows;
        }

        $sales_breakdown = $tax_report['sales_breakdown'] ?? [];
        foreach ($sales_breakdown as &$row) {
            foreach (['gross_sales', 'taxable_sales', 'exempt_sales', 'non_taxable_sales', 'zero_rated_sales', 'tax_amount'] as $key) {
                $row[$key] = (float) ($row[$key] ?? 0) * $exchange_rate;
            }
        }
        unset($row);
        $tax_report['sales_breakdown'] = $sales_breakdown;

        foreach ($tax_report['sales_totals'] ?? [] as $key => $amount) {
            $tax_report['sales_totals'][$key] = (float) $amount * $exchange_rate;
        }

        $payment_history = $tax_report['payment_history'] ?? [];
        foreach ($payment_history as &$row) {
            $row['amount'] = (float) ($row['amount'] ?? 0) * $exchange_rate;
            $row['refunded'] = (float) ($row['refunded'] ?? 0) * $exchange_rate;
        }
        unset($row);
        $tax_report['payment_history'] = $payment_history;

        $converted->metadata = new TransactionEventMetadata(['tax_report' => $tax_report]);
        $converted->payment_amount = (float) $event->payment_amount * $exchange_rate;
        $converted->payment_applied = (float) $event->payment_applied * $exchange_rate;
        $converted->payment_refunded = (float) $event->payment_refunded * $exchange_rate;

        return $converted;
    }

    /**
     * @param iterable<int, TransactionEvent> $events
     * @return array<int, array<string, mixed>>
     */
    public function project(iterable $events, ?string $start_date = null, ?string $end_date = null): array
    {
        $rows = [];

        foreach ($events as $event) {
            foreach ($this->eventComponents($event) as $component) {
                $effective_date = $component['effective_date'];

                if (($start_date && $effective_date < $start_date)
                    || ($end_date && $effective_date > $end_date)) {
                    continue;
                }

                $rows[] = $component;
            }
        }

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function eventComponents(TransactionEvent $event): array
    {
        $payment_history = $event->metadata->tax_report->payment_history ?? collect();
        $schema_version = (int) data_get($event->payment_request, 'schema_version');

        if ($schema_version === 2) {
            $payment = $payment_history->first();
            $effective_date = (string) data_get($event->payment_request, 'effective_date', $payment?->date);

            return [$this->component(
                $event,
                $effective_date,
                (float) ($payment?->exchange_rate ?: 1),
                1.0,
            )];
        }

        if ($event->event_id !== TransactionEvent::PAYMENT_CASH || $payment_history->isEmpty()) {
            return [$this->component($event, $event->period->toDateString(), 1.0, 1.0)];
        }

        $total = (float) $payment_history->sum(
            fn (PaymentHistory $payment): float => $payment->amount - $payment->refunded,
        );

        if (abs($total) < 0.0001) {
            return [];
        }

        return $payment_history
            ->map(function (PaymentHistory $payment) use ($event, $total): array {
                $amount = $payment->amount - $payment->refunded;

                return $this->component(
                    $event,
                    $payment->date,
                    $payment->exchange_rate ?: 1,
                    $amount / $total,
                );
            })
            ->all();
    }

    private function combinedMetadata(Collection $events, float $payment_amount): TransactionEventMetadata
    {
        /** @var TransactionEvent $first */
        $first = $events->first();
        $metadata = $first->metadata->toArray();
        $tax_report = $metadata['tax_report'];
        $tax_report['tax_summary']['taxable_amount'] = $events->sum(
            fn (TransactionEvent $event): float => (float) ($event->metadata->tax_report->tax_summary->taxable_amount ?? 0),
        );
        $tax_report['tax_summary']['tax_amount'] = $events->sum(
            fn (TransactionEvent $event): float => (float) ($event->metadata->tax_report->tax_summary->tax_amount ?? 0),
        );
        $tax_report['tax_summary']['status'] = $payment_amount >= 0 ? 'updated' : 'adjustment';

        foreach (['tax_details', 'tax_details_by_classification'] as $key) {
            $rows = $tax_report[$key] ?? [];

            foreach ($rows as $index => &$row) {
                foreach (['taxable_amount', 'tax_amount'] as $amount_key) {
                    $row[$amount_key] = $events->sum(function (TransactionEvent $event) use ($key, $index, $amount_key): float {
                        $event_rows = data_get($event->metadata->toArray(), "tax_report.{$key}", []);

                        return (float) data_get($event_rows, "{$index}.{$amount_key}", 0);
                    });
                }
            }
            unset($row);
            $tax_report[$key] = $rows;
        }

        $sales_breakdown = $tax_report['sales_breakdown'] ?? [];
        foreach ($sales_breakdown as $index => &$row) {
            foreach (['gross_sales', 'taxable_sales', 'exempt_sales', 'non_taxable_sales', 'zero_rated_sales', 'tax_amount'] as $amount_key) {
                $row[$amount_key] = $events->sum(function (TransactionEvent $event) use ($index, $amount_key): float {
                    $event_rows = data_get($event->metadata->toArray(), 'tax_report.sales_breakdown', []);

                    return (float) data_get($event_rows, "{$index}.{$amount_key}", 0);
                });
            }
        }
        unset($row);
        $tax_report['sales_breakdown'] = $sales_breakdown;

        foreach ($tax_report['sales_totals'] ?? [] as $key => $amount) {
            $tax_report['sales_totals'][$key] = $events->sum(
                fn (TransactionEvent $event): float => (float) data_get(
                    $event->metadata->toArray(),
                    "tax_report.sales_totals.{$key}",
                    0,
                ),
            );
        }

        $payment_history = $tax_report['payment_history'][0] ?? [];
        $payment_history['amount'] = $payment_amount;
        $payment_history['refunded'] = 0;
        $tax_report['payment_history'] = [$payment_history];

        return new TransactionEventMetadata(['tax_report' => $tax_report]);
    }

    /**
     * @return array<string, mixed>
     */
    private function component(
        TransactionEvent $event,
        string $effective_date,
        float $exchange_rate,
        float $ratio,
    ): array {
        $tax_summary = $event->metadata->tax_report->tax_summary;
        $tax_details = collect($event->metadata->tax_report->tax_details_by_classification
            ?? $event->metadata->tax_report->tax_details
            ?? [])
            ->map(function (array|object $detail) use ($exchange_rate, $ratio): array {
                $detail = is_array($detail) ? $detail : $detail->toArray();
                $detail['taxable_amount'] = (float) ($detail['taxable_amount'] ?? 0) * $ratio * $exchange_rate;
                $detail['tax_amount'] = (float) ($detail['tax_amount'] ?? 0) * $ratio * $exchange_rate;

                return $detail;
            })
            ->all();
        $sales_totals = collect($event->metadata->tax_report->sales_totals ?? [])
            ->map(fn (float|int $amount): float => (float) $amount * $ratio * $exchange_rate)
            ->all();

        return [
            'event_id' => $event->id,
            'invoice_id' => $event->invoice_id,
            'client_id' => $event->client_id,
            'payment_id' => $event->payment_id,
            'effective_date' => $effective_date,
            'period' => $event->period->toDateString(),
            'gross_amount' => (float) $event->payment_applied * $ratio * $exchange_rate,
            'taxable_amount' => (float) ($tax_summary->taxable_amount ?? 0) * $ratio * $exchange_rate,
            'tax_amount' => (float) ($tax_summary->tax_amount ?? 0) * $ratio * $exchange_rate,
            'tax_details' => $tax_details,
            'sales_breakdown' => $event->metadata->tax_report->sales_breakdown ?? [],
            'sales_totals' => $sales_totals,
            'exchange_rate' => $exchange_rate,
            'schema_version' => (int) data_get($event->payment_request, 'schema_version'),
            'correction_kind' => data_get($event->payment_request, 'tax_correction_kind'),
        ];
    }
}
