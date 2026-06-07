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
use App\Models\Credit;
use App\Models\Invoice;
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

class RecordFranceEReportingTransaction implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $deleteWhenMissingModels = true;

    public $tries = 1;

    public function __construct(
        private string $entity,
        private int $id,
        private string $db,
    ) {}

    public function handle(): void
    {
        if (! in_array($this->entity, [Invoice::class, Credit::class], true)) {
            return;
        }

        MultiDB::setDb($this->db);

        /** @var Invoice|Credit|null $document */
        $document = $this->entity::withTrashed()
            ->with(['client.country', 'client.company', 'company'])
            ->find($this->id);

        if (! $document || $document->is_deleted || ! $document->client || ! $document->company) {
            return;
        }

        if (! $document->client->reportableFrTransaction()) {
            return;
        }

        if (! $this->documentIsF10Reportable($document)) {
            return;
        }

        $eventId = $this->resolveEventId($document);
        $period = $this->resolvePeriodEnd($document, $eventId);

        if ($this->alreadyRecorded($document, $eventId)) {
            return;
        }

        TransactionEvent::create($this->transactionEventPayload($document, $eventId, $period));
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping($this->entity.$this->id.$this->db.'.fr-e-reporting-transaction'))
                ->releaseAfter(60)
                ->expireAfter(60),
        ];
    }

    private function documentIsF10Reportable(Invoice|Credit $document): bool
    {
        if (($document->client->classification ?? 'business') === 'individual') {
            return true;
        }

        return $document->client->country?->iso_3166_2 !== 'FR';
    }

    private function resolveEventId(Invoice|Credit $document): int
    {
        if (($document->client->classification ?? 'business') === 'individual') {
            return TransactionEvent::FR_B2C_TRANSACTION;
        }

        return TransactionEvent::FR_VAT_EXCLUDED_TRANSACTION;
    }

    private function resolvePeriodEnd(Invoice|Credit $document, int $eventId): string
    {
        $profile = match ($eventId) {
            TransactionEvent::FR_VAT_EXCLUDED_TRANSACTION => ReportingProfile::BiMonthly,
            default => ReportingProfile::tryFrom((string) $document->company->getSetting('france_reporting_schedule'))
                ?? ReportingProfile::TenDay,
        };

        return ReportingCalendar::currentPeriod(
            $profile,
            CarbonImmutable::parse($document->date ?: now()->toDateString()),
        )->end->toDateString();
    }

    private function alreadyRecorded(Invoice|Credit $document, int $eventId): bool
    {
        return TransactionEvent::query()
            ->where('company_id', $document->company_id)
            ->where('event_id', $eventId)
            ->when(
                $document instanceof Invoice,
                fn ($query) => $query->where('invoice_id', $document->id),
                fn ($query) => $query->where('credit_id', $document->id),
            )
            ->exists();
    }

    /**
     * @return array<string, mixed>
     */
    private function transactionEventPayload(Invoice|Credit $document, int $eventId, string $period): array
    {
        $isInvoice = $document instanceof Invoice;
        $reportingData = $this->reportingData($document, $eventId);

        return [
            'company_id' => $document->company_id,
            'client_id' => $document->client_id,
            'invoice_id' => $isInvoice ? $document->id : ($document->invoice_id ?? 0),
            'payment_id' => 0,
            'credit_id' => $isInvoice ? 0 : $document->id,
            'client_balance' => $document->client->balance,
            'client_paid_to_date' => $document->client->paid_to_date,
            'client_credit_balance' => $document->client->credit_balance,
            'invoice_balance' => $isInvoice ? ($document->balance ?? 0) : 0,
            'invoice_amount' => $isInvoice ? ($document->amount ?? 0) : 0,
            'invoice_partial' => $isInvoice ? ($document->partial ?? 0) : 0,
            'invoice_paid_to_date' => $isInvoice ? ($document->paid_to_date ?? 0) : 0,
            'invoice_status' => $isInvoice ? $document->status_id : null,
            'event_id' => $eventId,
            'timestamp' => now()->timestamp,
            'period' => $period,
            'credit_balance' => $isInvoice ? 0 : ($document->balance ?? 0),
            'credit_amount' => $isInvoice ? 0 : ($document->amount ?? 0),
            'credit_status' => $isInvoice ? null : $document->status_id,
            'payment_status' => TransactionEvent::FR_REPORTING_STATUS_PENDING,
            'reporting_data' => $reportingData,
        ];
    }

    private function reportingData(Invoice|Credit $document, int $eventId): ?ReportData
    {
        /** @var FranceReportEntryBuilder $builder */
        $builder = app(FranceReportEntryBuilder::class);

        return match ($eventId) {
            TransactionEvent::FR_VAT_EXCLUDED_TRANSACTION => ReportData::fromFRReportEntry(
                FRReportEntryData::fromB2BIInvoice($builder->b2biInvoice($document)),
            ),
            TransactionEvent::FR_B2C_TRANSACTION => ReportData::fromFRReportEntry(
                FRReportEntryData::fromB2CTransaction($builder->b2cTransaction($document)),
            ),
            default => null,
        };
    }
}
