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

namespace Tests\Unit\Chart;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Independent PHP reference implementation for the chart summary calculations.
 *
 * The shim intentionally reads unaggregated rows and does not call ChartService or
 * ChartQueries. It exists only to qualify the MySQL implementations in tests.
 */
final class ChartSummaryPhpShim
{
    private int $companyCurrencyId;

    public function __construct(
        private Company $company,
        private User $user,
        private bool $isAdmin,
        private bool $includeDrafts
    ) {
        $this->companyCurrencyId = (int) $this->company->settings->currency_id;
    }

    /**
     * @return array<string|int, mixed>
     */
    public function calculate(string $startDate, string $endDate): array
    {
        /** @var Collection<int, stdClass> $clients */
        $clients = DB::table('clients')
            ->where('company_id', $this->company->id)
            ->get();

        /** @var Collection<int, stdClass> $vendors */
        $vendors = DB::table('vendors')
            ->where('company_id', $this->company->id)
            ->get();

        /** @var Collection<int, stdClass> $invoices */
        $invoices = DB::table('invoices')
            ->where('company_id', $this->company->id)
            ->get();

        /** @var Collection<int, stdClass> $payments */
        $payments = DB::table('payments')
            ->where('company_id', $this->company->id)
            ->get();

        /** @var Collection<int, stdClass> $expenses */
        $expenses = DB::table('expenses')
            ->where('company_id', $this->company->id)
            ->get();

        /** @var Collection<int, stdClass> $clientsById */
        $clientsById = $clients->keyBy(fn(stdClass $client): int => (int) $client->id);

        /** @var Collection<int, stdClass> $vendorsById */
        $vendorsById = $vendors->keyBy(fn(stdClass $vendor): int => (int) $vendor->id);

        $currencyIds = $this->currencyIds($clients, $expenses);
        $payload = [
            'start_date' => $startDate,
            'end_date' => $endDate,
        ];

        foreach ($currencyIds as $currencyId) {
            $payload[$currencyId] = [
                'invoices' => [],
                'outstanding' => [],
                'payments' => [],
                'expenses' => [],
            ];
        }

        /** @var array<int, array<string, BigDecimal>> $invoiceTotals */
        $invoiceTotals = [];
        /** @var array<int, array<string, BigDecimal>> $outstandingTotals */
        $outstandingTotals = [];
        /** @var array<int, array<string, BigDecimal>> $paymentTotals */
        $paymentTotals = [];
        /** @var array<int, array<string, BigDecimal>> $expenseTotals */
        $expenseTotals = [];
        /** @var array<string, BigDecimal> $aggregateInvoiceTotals */
        $aggregateInvoiceTotals = [];
        /** @var array<string, BigDecimal> $aggregateOutstandingTotals */
        $aggregateOutstandingTotals = [];
        /** @var array<string, BigDecimal> $aggregatePaymentTotals */
        $aggregatePaymentTotals = [];
        /** @var array<string, BigDecimal> $aggregateExpenseTotals */
        $aggregateExpenseTotals = [];

        foreach ($invoices as $invoice) {
            $client = $clientsById->get((int) $invoice->client_id);

            if (! $client || ! $this->invoiceIsVisible($invoice, $client, $startDate, $endDate)) {
                continue;
            }

            $currencyId = $this->clientCurrencyId($client);
            $date = (string) $invoice->date;
            $statusId = (int) $invoice->status_id;
            $invoiceStatuses = $this->includeDrafts
                ? [Invoice::STATUS_DRAFT, Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL, Invoice::STATUS_PAID]
                : [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL, Invoice::STATUS_PAID];
            $outstandingStatuses = $this->includeDrafts
                ? [Invoice::STATUS_DRAFT, Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL]
                : [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL];

            if (in_array($statusId, $invoiceStatuses, true)) {
                $amount = $this->decimal($invoice->amount);

                if (array_key_exists($currencyId, $payload)) {
                    $this->addCurrencyTotal($invoiceTotals, $currencyId, $date, $amount);
                }

                $this->addDateTotal(
                    $aggregateInvoiceTotals,
                    $date,
                    $amount->dividedBy($this->exchangeRate($invoice), 10, RoundingMode::HalfUp)
                );
            }

            if (in_array($statusId, $outstandingStatuses, true)) {
                $month = $this->lastDayOfMonth($date);
                $balance = $this->decimal($invoice->balance);

                if (array_key_exists($currencyId, $payload)) {
                    $this->addCurrencyTotal($outstandingTotals, $currencyId, $month, $balance);
                }

                $this->addDateTotal(
                    $aggregateOutstandingTotals,
                    $month,
                    $balance->dividedBy($this->exchangeRate($invoice), 10, RoundingMode::HalfUp)
                );
            }
        }

        foreach ($payments as $payment) {
            $client = $clientsById->get((int) $payment->client_id);

            if (! $client || ! $this->paymentIsVisible($payment, $client, $startDate, $endDate)) {
                continue;
            }

            $currencyId = $payment->currency_id === null
                ? $this->companyCurrencyId
                : (int) $payment->currency_id;
            $date = (string) $payment->date;
            $netAmount = $this->decimal($payment->amount)->minus($this->decimal($payment->refunded));

            if (array_key_exists($currencyId, $payload)) {
                $this->addCurrencyTotal($paymentTotals, $currencyId, $date, $netAmount);
            }

            $this->addDateTotal(
                $aggregatePaymentTotals,
                $date,
                $netAmount->multipliedBy($this->exchangeRate($payment))
            );
        }

        foreach ($expenses as $expense) {
            if (! $this->expenseIsVisible($expense, $clientsById, $vendorsById, $startDate, $endDate)) {
                continue;
            }

            $currencyId = $expense->currency_id === null
                ? $this->companyCurrencyId
                : (int) $expense->currency_id;
            $date = (string) $expense->date;
            $total = $this->expenseTotal($expense);

            if (array_key_exists($currencyId, $payload)) {
                $this->addCurrencyTotal($expenseTotals, $currencyId, $date, $total);
            }

            $aggregateTotal = $expense->currency_id !== null
                && (int) $expense->currency_id === $this->companyCurrencyId
                    ? $total
                    : $total->multipliedBy($this->exchangeRate($expense));

            $this->addDateTotal($aggregateExpenseTotals, $date, $aggregateTotal);
        }

        foreach ($currencyIds as $currencyId) {
            $payload[$currencyId]['invoices'] = $this->series($invoiceTotals[$currencyId] ?? [], 6);
            $payload[$currencyId]['outstanding'] = $this->cumulativeSeries($outstandingTotals[$currencyId] ?? [], 6);
            $payload[$currencyId]['payments'] = $this->series($paymentTotals[$currencyId] ?? [], 6);
            $payload[$currencyId]['expenses'] = $this->series($expenseTotals[$currencyId] ?? [], 16);
        }

        $payload[999] = [
            'invoices' => $this->series($aggregateInvoiceTotals, 10),
            'outstanding' => $this->cumulativeSeries($aggregateOutstandingTotals, 10),
            'payments' => $this->series($aggregatePaymentTotals, 12),
            'expenses' => $this->series($aggregateExpenseTotals, 22),
        ];

        return $payload;
    }

    /**
     * @param Collection<int, stdClass> $clients
     * @param Collection<int, stdClass> $expenses
     * @return array<int, int>
     */
    private function currencyIds(Collection $clients, Collection $expenses): array
    {
        $candidateIds = [$this->companyCurrencyId];

        foreach ($clients as $client) {
            if ((int) $client->is_deleted !== 0) {
                continue;
            }

            if (! $this->isAdmin && (int) $client->user_id !== (int) $this->user->id) {
                continue;
            }

            $candidateIds[] = $this->clientCurrencyId($client);
        }

        foreach ($expenses as $expense) {
            if ((int) $expense->is_deleted !== 0) {
                continue;
            }

            if (! $this->isAdmin && (int) $expense->user_id !== (int) $this->user->id) {
                continue;
            }

            if ($expense->currency_id !== null) {
                $candidateIds[] = (int) $expense->currency_id;
            }
        }

        $candidateIds = array_values(array_unique($candidateIds));
        $currencyIds = [];

        foreach (app('currencies') as $currency) {
            if (in_array((int) $currency->id, $candidateIds, true)) {
                $currencyIds[] = (int) $currency->id;
            }
        }

        return $currencyIds;
    }

    private function invoiceIsVisible(stdClass $invoice, stdClass $client, string $startDate, string $endDate): bool
    {
        return (int) $invoice->is_deleted === 0
            && (int) $client->is_deleted === 0
            && ($this->isAdmin || (int) $client->user_id === (int) $this->user->id)
            && $this->dateIsIncluded((string) $invoice->date, $startDate, $endDate);
    }

    private function paymentIsVisible(stdClass $payment, stdClass $client, string $startDate, string $endDate): bool
    {
        return (int) $payment->is_deleted === 0
            && (int) $client->is_deleted === 0
            && ($this->isAdmin || (int) $payment->user_id === (int) $this->user->id)
            && in_array((int) $payment->status_id, [
                Payment::STATUS_COMPLETED,
                Payment::STATUS_PARTIALLY_REFUNDED,
                Payment::STATUS_REFUNDED,
            ], true)
            && $this->dateIsIncluded((string) $payment->date, $startDate, $endDate);
    }

    /**
     * @param Collection<int, stdClass> $clientsById
     * @param Collection<int, stdClass> $vendorsById
     */
    private function expenseIsVisible(
        stdClass $expense,
        Collection $clientsById,
        Collection $vendorsById,
        string $startDate,
        string $endDate
    ): bool {
        if ((int) $expense->is_deleted !== 0
            || (! $this->isAdmin && (int) $expense->user_id !== (int) $this->user->id)
            || ! $this->dateIsIncluded((string) $expense->date, $startDate, $endDate)) {
            return false;
        }

        $client = $expense->client_id === null ? null : $clientsById->get((int) $expense->client_id);
        $vendor = $expense->vendor_id === null ? null : $vendorsById->get((int) $expense->vendor_id);

        return (! $client || (int) $client->is_deleted === 0)
            && (! $vendor || (int) $vendor->is_deleted === 0);
    }

    private function expenseTotal(stdClass $expense): BigDecimal
    {
        $amount = $this->decimal($expense->amount);

        if ((int) $expense->uses_inclusive_taxes !== 0) {
            return $amount;
        }

        $total = $amount
            ->plus($this->decimal($expense->tax_amount1))
            ->plus($this->decimal($expense->tax_amount2))
            ->plus($this->decimal($expense->tax_amount3));

        foreach ([$expense->tax_rate1, $expense->tax_rate2, $expense->tax_rate3] as $taxRate) {
            $total = $total->plus(
                $amount->multipliedBy($this->decimal($taxRate))->dividedByExact(100)
            );
        }

        return $total;
    }

    private function clientCurrencyId(stdClass $client): int
    {
        $settings = is_string($client->settings)
            ? json_decode($client->settings)
            : $client->settings;

        if (! is_object($settings) || ! property_exists($settings, 'currency_id') || $settings->currency_id === null) {
            return $this->companyCurrencyId;
        }

        return (int) $settings->currency_id;
    }

    private function exchangeRate(stdClass $row): BigDecimal
    {
        $rate = $this->decimal($row->exchange_rate);

        return $rate->isZero() ? BigDecimal::one() : $rate;
    }

    private function decimal(mixed $value): BigDecimal
    {
        return $value === null || $value === ''
            ? BigDecimal::zero()
            : BigDecimal::of((string) $value);
    }

    private function dateIsIncluded(string $date, string $startDate, string $endDate): bool
    {
        return $date >= $startDate && $date <= $endDate;
    }

    private function lastDayOfMonth(string $date): string
    {
        return (new \DateTimeImmutable($date))->modify('last day of this month')->format('Y-m-d');
    }

    /**
     * @param array<int, array<string, BigDecimal>> $totals
     */
    private function addCurrencyTotal(
        array &$totals,
        int $currencyId,
        string $date,
        BigDecimal $amount
    ): void {
        $totals[$currencyId][$date] = ($totals[$currencyId][$date] ?? BigDecimal::zero())->plus($amount);
    }

    /**
     * @param array<string, BigDecimal> $totals
     */
    private function addDateTotal(array &$totals, string $date, BigDecimal $amount): void
    {
        $totals[$date] = ($totals[$date] ?? BigDecimal::zero())->plus($amount);
    }

    /**
     * @param array<string, BigDecimal> $totals
     * @return array<int, array{total: string, date: string}>
     */
    private function series(array $totals, int $scale): array
    {
        ksort($totals);
        $series = [];

        foreach ($totals as $date => $total) {
            $series[] = [
                'total' => (string) $total->toScale($scale, RoundingMode::HalfUp),
                'date' => $date,
            ];
        }

        return $series;
    }

    /**
     * @param array<string, BigDecimal> $totals
     * @return array<int, array{total: string, date: string}>
     */
    private function cumulativeSeries(array $totals, int $scale): array
    {
        ksort($totals);
        $cumulative = BigDecimal::zero();
        $series = [];

        foreach ($totals as $date => $total) {
            $cumulative = $cumulative->plus($total);
            $series[] = [
                'total' => (string) $cumulative->toScale($scale, RoundingMode::HalfUp),
                'date' => $date,
            ];
        }

        return $series;
    }
}
