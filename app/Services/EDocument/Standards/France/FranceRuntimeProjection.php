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

use App\DataMapper\FranceEReporting\FRReportEntryData;
use App\Models\Client;
use App\Models\Company;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\TransactionEvent;
use App\Utils\BcMath;

final readonly class FranceRuntimeProjection
{
    public function __construct(private FranceReportEntryBuilder $entryBuilder) {}

    /** @return array<int, FranceReportingSubject> */
    public function current(
        Company $company,
        FranceEReportVariant $variant,
        ReportingPeriod $period,
    ): array {
        return $variant->isTransaction()
            ? $this->transactions($company, $period)
            : $this->payments($company, $period);
    }

    /** @return array<int, FranceReportingSubject> */
    private function transactions(Company $company, ReportingPeriod $period): array
    {
        $relations = ['client.country', 'client.company', 'company'];
        $dateRange = [$period->start->toDateString(), $period->end->toDateString()];
        $subjects = [];

        $invoices = Invoice::withTrashed()
            ->with($relations)
            ->where('company_id', $company->id)
            ->whereBetween('date', $dateRange)
            ->where('is_deleted', false)
            ->whereIn('status_id', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL, Invoice::STATUS_PAID])
            ->orderBy('id')
            ->get();

        $credits = Credit::withTrashed()
            ->with($relations)
            ->where('company_id', $company->id)
            ->whereBetween('date', $dateRange)
            ->where('is_deleted', false)
            ->whereIn('status_id', [Credit::STATUS_SENT, Credit::STATUS_PARTIAL, Credit::STATUS_APPLIED])
            ->orderBy('id')
            ->get();
        $invoices->each(function (Invoice $invoice) use (&$subjects): void {
            $subject = $this->transactionSubject($invoice);

            if ($subject) {
                $subjects[] = $subject;
            }
        });

        $credits->each(function (Credit $credit) use (&$subjects): void {
            $subject = $this->transactionSubject($credit);

            if ($subject) {
                $subjects[] = $subject;
            }
        });

        return $subjects;
    }

    private function transactionSubject(Invoice|Credit $document): ?FranceReportingSubject
    {
        $client = $document->getRelation('client');

        if (! $client instanceof Client
            || ! $this->documentIsF10Reportable($client)
            || strtoupper((string) $client->currency()?->code) !== 'EUR'
            || ! $this->documentLifecycleAllowsReporting($document)) {
            return null;
        }

        $isInvoice = $document instanceof Invoice;

        if (($client->classification ?? 'business') === 'individual') {
            $transaction = $this->entryBuilder->b2cTransaction($document);

            return $transaction
                ? new FranceReportingSubject(
                    key: ($isInvoice ? 'invoice:' : 'credit:') . $document->id,
                    entry: FRReportEntryData::fromB2CTransaction($transaction),
                    clientId: (int) $document->client_id,
                    invoiceId: $isInvoice ? (int) $document->id : null,
                    creditId: $isInvoice ? null : (int) $document->id,
                )
                : null;
        }

        if ($document instanceof Credit) {
            return null;
        }

        return new FranceReportingSubject(
            key: ($isInvoice ? 'invoice:' : 'credit:') . $document->id,
            entry: FRReportEntryData::fromB2BIInvoice($this->entryBuilder->b2biInvoice($document)),
            clientId: (int) $document->client_id,
            invoiceId: $isInvoice ? (int) $document->id : null,
            creditId: $isInvoice ? null : (int) $document->id,
        );
    }

    /** @return array<int, FranceReportingSubject> */
    private function payments(Company $company, ReportingPeriod $period): array
    {
        $events = TransactionEvent::query()
            ->select([
                'id',
                'company_id',
                'client_id',
                'invoice_id',
                'payment_id',
                'event_id',
                'period',
                'payment_request',
            ])
            ->where('company_id', $company->id)
            ->whereIn('client_id', Client::withTrashed()
                ->select('id')
                ->where('company_id', $company->id))
            ->where('event_id', FranceReportingEventType::PaymentMovement->value)
            ->where('payment_request->reporting_path', 'f10')
            ->where('period', $period->end->toDateString())
            ->orderBy('id')
            ->get();

        $payments = Payment::withTrashed()
            ->with(['client.country', 'client.company', 'company', 'currency'])
            ->where('company_id', $company->id)
            ->whereIn('id', $events->pluck('payment_id')->filter()->unique())
            ->get()
            ->keyBy('id');
        $invoices = Invoice::withTrashed()
            ->with(['client.country', 'client.company', 'company'])
            ->where('company_id', $company->id)
            ->whereIn('id', $events->pluck('invoice_id')->filter()->unique())
            ->get()
            ->keyBy('id');
        $groups = [];

        foreach ($events as $event) {
            $key = (string) data_get(
                $event->payment_request,
                'subject_key',
                "payment:{$event->payment_id}:invoice:{$event->invoice_id}",
            );
            $group = $groups[$key] ?? [
                'amount' => '0',
                'payment_id' => (int) $event->payment_id,
                'invoice_id' => (int) $event->invoice_id,
                'date_balances' => [],
            ];
            $movementAmount = (string) data_get($event->payment_request, 'movement_amount', 0);
            $reportDate = (string) data_get($event->payment_request, 'report_date');
            $group['amount'] = BcMath::add(
                $group['amount'],
                $movementAmount,
                2,
            );
            $group['date_balances'] = $this->applyMovementToDateBalances(
                $group['date_balances'],
                $reportDate,
                $movementAmount,
            );
            $reportDates = array_keys(array_filter(
                $group['date_balances'],
                static fn(string $amount): bool => BcMath::greaterThan($amount, '0', 2),
            ));
            sort($reportDates);
            $group['report_date'] = $reportDates[0] ?? '';
            $groups[$key] = $group;
        }

        $subjects = [];

        foreach ($groups as $key => $group) {
            if (BcMath::isZero($group['amount'], 2)) {
                continue;
            }

            /** @var Payment|null $payment */
            $payment = $payments->get($group['payment_id']);
            /** @var Invoice|null $invoice */
            $invoice = $invoices->get($group['invoice_id']);

            if (! $payment
                || ! $invoice
                || ! in_array((int) $payment->status_id, [
                    Payment::STATUS_COMPLETED,
                    Payment::STATUS_PARTIALLY_REFUNDED,
                    Payment::STATUS_REFUNDED,
                ], true)
                || strtoupper((string) $invoice->client->currency()?->code) !== 'EUR'
                || ! $this->invoiceCanParticipate($invoice)
                || ! $this->invoiceIsCurrentlyPaid($invoice)
                || ! $this->documentLifecycleAllowsReporting($invoice)
                || ! $this->documentIsF10Reportable($invoice->client)) {
                continue;
            }

            if (($invoice->client->classification ?? 'business') === 'individual') {
                if ($this->entryBuilder->b2cSupplyCategory($invoice) !== 'TPS1') {
                    continue;
                }

                $entry = FRReportEntryData::fromB2CPayment($this->entryBuilder->b2cPayment(
                    $payment,
                    $invoice,
                    $group['amount'],
                    $group['report_date'],
                ));
            } else {
                $entry = FRReportEntryData::fromB2BIPayment($this->entryBuilder->b2biPayment(
                    $payment,
                    $invoice,
                    $group['amount'],
                    $group['report_date'],
                ));
            }

            $subjects[] = new FranceReportingSubject(
                key: $key,
                entry: $entry,
                clientId: (int) $invoice->client_id,
                invoiceId: (int) $invoice->id,
                paymentId: (int) $payment->id,
            );
        }

        return $subjects;
    }

    /**
     * @param array<string, string> $balances
     * @return array<string, string>
     */
    private function applyMovementToDateBalances(array $balances, string $reportDate, string $amount): array
    {
        if ($reportDate === '') {
            return $balances;
        }

        if (! BcMath::isNegative($amount, 2)) {
            $balances[$reportDate] = BcMath::add($balances[$reportDate] ?? '0', $amount, 2);

            return $balances;
        }

        $remaining = BcMath::abs($amount, 2);
        $otherDates = array_values(array_diff(array_keys($balances), [$reportDate]));
        sort($otherDates);

        foreach ([$reportDate, ...$otherDates] as $date) {
            $available = $balances[$date] ?? '0';

            if (! BcMath::greaterThan($available, '0', 2)) {
                continue;
            }

            $deduction = BcMath::lessThan($available, $remaining, 2)
                ? $available
                : $remaining;
            $balances[$date] = BcMath::sub($available, $deduction, 2);
            $remaining = BcMath::sub($remaining, $deduction, 2);

            if (BcMath::isZero($remaining, 2)) {
                break;
            }
        }

        return $balances;
    }

    private function documentIsF10Reportable(Client $client): bool
    {
        if (! $client->reportableFrTransaction()) {
            return false;
        }

        return ($client->classification ?? 'business') === 'individual'
            || $client->country?->iso_3166_2 !== 'FR';
    }

    private function invoiceIsCurrentlyPaid(Invoice $invoice): bool
    {
        return (int) $invoice->status_id === Invoice::STATUS_PAID
            || BcMath::lessThanOrEqual($invoice->balance ?? 0, '0', 2);
    }

    private function invoiceCanParticipate(Invoice $invoice): bool
    {
        return ! $invoice->is_deleted
            && ! in_array((int) $invoice->status_id, [Invoice::STATUS_CANCELLED, Invoice::STATUS_REVERSED], true);
    }

    private function documentLifecycleAllowsReporting(Invoice|Credit $document): bool
    {
        return strtolower(trim((string) ($document->backup->e_invoice_status ?? ''))) !== 'rejected';
    }
}
