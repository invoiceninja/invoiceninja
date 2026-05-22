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

namespace App\Jobs\EDocument;

use App\DataMapper\FranceEReporting\FRReportEntryData;
use App\DataMapper\ReportData;
use App\Libraries\MultiDB;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\TransactionEvent;
use App\Services\EDocument\Standards\France\FranceReportEntryBuilder;
use App\Services\EDocument\Standards\France\ReportingCalendar;
use App\Services\EDocument\Standards\France\ReportingProfile;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

class RecordFranceEReportingPayment implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $deleteWhenMissingModels = true;

    public $tries = 1;

    public function __construct(
        private int $paymentId,
        private string $db,
        private ?int $invoiceId = null,
    ) {}

    public function handle(): void
    {
        MultiDB::setDb($this->db);

        /** @var Payment|null $payment */
        $payment = Payment::withTrashed()
            ->with([
                'client.country',
                'client.company',
                'company',
                'currency',
                'invoices.client.country',
                'invoices.client.company',
                'invoices.company',
            ])
            ->find($this->paymentId);

        if (! $payment || $payment->is_deleted || ! $payment->company) {
            return;
        }

        if (! in_array($payment->status_id, [Payment::STATUS_COMPLETED], true)) {
            return;
        }

        $invoices = $payment->invoices;

        if (! is_null($this->invoiceId)) {
            $invoices = $invoices->where('id', $this->invoiceId);
        }

        foreach ($invoices as $invoice) {
            if (! $invoice instanceof Invoice || $invoice->is_deleted || ! $invoice->client || ! $invoice->client->reportableFrTransaction()) {
                continue;
            }

            if ((float) data_get($invoice, 'pivot.amount', 0) <= 0) {
                continue;
            }

            if (! $this->invoiceIsPaidInFull($invoice)) {
                continue;
            }

            $eventId = $this->resolveEventId($invoice);

            if ($this->alreadyRecorded($payment, $invoice, $eventId)) {
                continue;
            }

            TransactionEvent::create($this->transactionEventPayload(
                payment: $payment,
                invoice: $invoice,
                eventId: $eventId,
                period: $this->resolvePeriodEnd($payment, $invoice, $eventId),
            ));
        }
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping($this->paymentId.($this->invoiceId ?? 'all').$this->db.'.fr-e-reporting-payment'))
                ->releaseAfter(60)
                ->expireAfter(60),
        ];
    }

    private function invoiceIsPaidInFull(Invoice $invoice): bool
    {
        return (int) $invoice->status_id === Invoice::STATUS_PAID
            || (float) $invoice->balance <= 0.0;
    }

    private function resolveEventId(Invoice $invoice): int
    {
        if (($invoice->client->classification ?? 'business') === 'individual') {
            return TransactionEvent::FR_B2C_PAYMENT;
        }

        return TransactionEvent::FR_VAT_EXCLUDED_PAYMENT;
    }

    private function resolvePeriodEnd(Payment $payment, Invoice $invoice, int $eventId): string
    {
        $profile = match ($eventId) {
            TransactionEvent::FR_VAT_EXCLUDED_PAYMENT => ReportingProfile::BiMonthly,
            default => ReportingProfile::tryFrom((string) $payment->company->getSetting('france_reporting_schedule'))
                ?? ReportingProfile::TenDay,
        };

        return ReportingCalendar::currentPeriod(
            $profile,
            CarbonImmutable::parse($payment->date ?: $invoice->date ?: now()->toDateString()),
        )->end->toDateString();
    }

    private function alreadyRecorded(Payment $payment, Invoice $invoice, int $eventId): bool
    {
        return TransactionEvent::query()
            ->where('company_id', $payment->company_id)
            ->where('event_id', $eventId)
            ->where('payment_id', $payment->id)
            ->where('invoice_id', $invoice->id)
            ->exists();
    }

    /**
     * @return array<string, mixed>
     */
    private function transactionEventPayload(Payment $payment, Invoice $invoice, int $eventId, string $period): array
    {
        $appliedAmount = data_get($invoice, 'pivot.amount', $payment->applied ?: $payment->amount);

        return [
            'company_id' => $payment->company_id,
            'client_id' => $invoice->client_id,
            'invoice_id' => $invoice->id,
            'payment_id' => $payment->id,
            'credit_id' => 0,
            'client_balance' => $invoice->client->balance,
            'client_paid_to_date' => $invoice->client->paid_to_date,
            'client_credit_balance' => $invoice->client->credit_balance,
            'invoice_balance' => $invoice->balance ?? 0,
            'invoice_amount' => $invoice->amount ?? 0,
            'invoice_partial' => $invoice->partial ?? 0,
            'invoice_paid_to_date' => $invoice->paid_to_date ?? 0,
            'invoice_status' => $invoice->status_id,
            'payment_amount' => $payment->amount ?? 0,
            'payment_applied' => $appliedAmount,
            'payment_refunded' => $payment->refunded ?? 0,
            'payment_status' => TransactionEvent::FR_REPORTING_STATUS_PENDING,
            'event_id' => $eventId,
            'timestamp' => now()->timestamp,
            'period' => $period,
            'credit_balance' => 0,
            'credit_amount' => 0,
            'credit_status' => null,
            'reporting_data' => $this->reportingData($payment, $invoice, $eventId),
        ];
    }

    private function reportingData(Payment $payment, Invoice $invoice, int $eventId): ReportData
    {
        /** @var FranceReportEntryBuilder $builder */
        $builder = app(FranceReportEntryBuilder::class);

        return match ($eventId) {
            TransactionEvent::FR_VAT_EXCLUDED_PAYMENT => ReportData::fromFRReportEntry(
                FRReportEntryData::fromB2BIPayment($builder->b2biPayment($payment, $invoice)),
            ),
            TransactionEvent::FR_B2C_PAYMENT => ReportData::fromFRReportEntry(
                FRReportEntryData::fromB2CPayment($builder->b2cPayment($payment, $invoice)),
            ),
            default => throw new \InvalidArgumentException("Unsupported France payment event_id [{$eventId}]."),
        };
    }
}