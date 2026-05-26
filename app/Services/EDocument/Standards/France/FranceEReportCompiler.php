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

use App\DataMapper\FranceEReporting\B2BIInvoiceData;
use App\DataMapper\FranceEReporting\B2BIPaymentData;
use App\DataMapper\FranceEReporting\B2CPaymentData;
use App\DataMapper\FranceEReporting\B2CTransactionData;
use App\DataMapper\FranceEReporting\DeclarantPartyData;
use App\DataMapper\FranceEReporting\FRReportData;
use App\DataMapper\FranceEReporting\PartyData;
use App\DataMapper\FranceEReporting\PaymentReportData;
use App\DataMapper\FranceEReporting\PublicIdentifierData;
use App\DataMapper\FranceEReporting\TransactionReportData;
use App\Jobs\EDocument\RecordFranceEReportingPayment;
use App\Models\Company;
use App\Models\TransactionEvent;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class FranceEReportCompiler
{
    public function compile(
        Company $company,
        int $submissionEventId,
        string $periodEnd,
        ?CarbonImmutable $issuedAt = null,
        ?string $documentId = null,
    ): FRReportData {
        $sourceEvents = $this->sourceEvents($company, $submissionEventId, $periodEnd);

        return $this->compileFromEvents($company, $submissionEventId, $periodEnd, $sourceEvents, $issuedAt, $documentId);
    }

    /**
     * @return Collection<int, TransactionEvent>
     */
    public function sourceEvents(Company $company, int $submissionEventId, string $periodEnd): Collection
    {
        return TransactionEvent::query()
            ->where('company_id', $company->id)
            ->where('period', $periodEnd)
            ->whereIn('event_id', $this->sourceEventIds($submissionEventId))
            ->whereNotNull('reporting_data')
            ->where(function ($query) {
                $query->whereNull('payment_status')
                    ->orWhere('payment_status', TransactionEvent::FR_REPORTING_STATUS_PENDING)
                    ->orWhere('payment_status', TransactionEvent::FR_REPORTING_STATUS_FAILED);
            })
            ->orderBy('id')
            ->get()
            ->filter(fn (TransactionEvent $event): bool => $this->isSourceEventForSubmission($event, $submissionEventId))
            ->values();
    }

    /**
     * @param iterable<int, TransactionEvent> $events
     */
    public function compileFromEvents(
        Company $company,
        int $submissionEventId,
        string $periodEnd,
        iterable $events,
        ?CarbonImmutable $issuedAt = null,
        ?string $documentId = null,
    ): FRReportData {
        $events = collect($events);
        $issuedAt ??= CarbonImmutable::now($this->companyTimezone($company));
        $documentId ??= $this->documentId($company, $submissionEventId, $periodEnd, $issuedAt);

        $b2biInvoices = [];
        $b2cTransactions = [];
        $b2biPayments = [];
        $b2cPayments = [];

        foreach ($events as $event) {
            $entry = $event->reporting_data?->frReportEntry;

            if (is_null($entry)) {
                continue;
            }

            match ($event->event_id) {
                TransactionEvent::FR_VAT_EXCLUDED_TRANSACTION => $b2biInvoices[] = $entry->b2biInvoice,
                TransactionEvent::FR_B2C_TRANSACTION => $b2cTransactions[] = $entry->b2cTransaction,
                TransactionEvent::FR_VAT_EXCLUDED_PAYMENT => $b2biPayments[] = $entry->b2biPayment,
                TransactionEvent::FR_B2C_PAYMENT => $b2cPayments[] = $entry->b2cPayment,
                default => null,
            };
        }

        $b2biInvoices = $this->filterInstances($b2biInvoices, B2BIInvoiceData::class);
        $b2cTransactions = $this->filterInstances($b2cTransactions, B2CTransactionData::class);
        $b2biPayments = $this->filterInstances($b2biPayments, B2BIPaymentData::class);
        $b2cPayments = $this->filterInstances($b2cPayments, B2CPaymentData::class);

        $transactionReport = ($b2biInvoices !== [] || $b2cTransactions !== [])
            ? new TransactionReportData(
                period: $this->periodLabel($company, $submissionEventId, $periodEnd, $events),
                b2biInvoices: $b2biInvoices,
                b2cTransactions: $b2cTransactions,
            )
            : null;

        $paymentReport = ($b2biPayments !== [] || $b2cPayments !== [])
            ? new PaymentReportData(
                period: $this->periodLabel($company, $submissionEventId, $periodEnd, $events),
                b2biPayments: $b2biPayments,
                b2cPayments: $b2cPayments,
            )
            : null;

        if (is_null($transactionReport) && is_null($paymentReport)) {
            throw new InvalidArgumentException('France e-report compilation requires at least one reportable source event.');
        }

        $typeCode = $submissionEventId === TransactionEvent::FR_REPORT_SUBMISSION_CORRECTIVE
            ? FRReportData::TYPE_RECTIFICATIVE
            : FRReportData::TYPE_INITIAL;

        return new FRReportData(
            typeCode: $typeCode,
            documentId: $documentId,
            issueDate: $issuedAt->toDateString(),
            issueTime: $issuedAt->format('H:i:s'),
            timeZone: $issuedAt->format('O'),
            declarantParty: $this->declarantParty($company),
            transactionReport: $transactionReport,
            paymentReport: $paymentReport,
        );
    }

    /**
     * @return array<int, int>
     */
    public function sourceEventIds(int $submissionEventId): array
    {
        return match ($submissionEventId) {
            TransactionEvent::FR_REPORT_SUBMISSION_B2C => [
                TransactionEvent::FR_B2C_TRANSACTION,
                TransactionEvent::FR_B2C_PAYMENT,
            ],
            TransactionEvent::FR_REPORT_SUBMISSION_VAT_EXCLUDED => [
                TransactionEvent::FR_VAT_EXCLUDED_TRANSACTION,
                TransactionEvent::FR_VAT_EXCLUDED_PAYMENT,
            ],
            TransactionEvent::FR_REPORT_SUBMISSION_CORRECTIVE => [
                TransactionEvent::FR_B2C_TRANSACTION,
                TransactionEvent::FR_B2C_PAYMENT,
                TransactionEvent::FR_VAT_EXCLUDED_TRANSACTION,
                TransactionEvent::FR_VAT_EXCLUDED_PAYMENT,
            ],
            default => throw new InvalidArgumentException("Unsupported France report submission event_id [{$submissionEventId}]."),
        };
    }


    private function isSourceEventForSubmission(TransactionEvent $event, int $submissionEventId): bool
    {
        if (data_get($event->payment_request, 'fr_kind') === RecordFranceEReportingPayment::KIND_MOVEMENT) {
            return false;
        }

        $reportKind = data_get($event->payment_request, 'fr_report_kind', RecordFranceEReportingPayment::REPORT_KIND_INITIAL);

        if ($submissionEventId === TransactionEvent::FR_REPORT_SUBMISSION_CORRECTIVE) {
            return $reportKind === RecordFranceEReportingPayment::REPORT_KIND_CORRECTIVE;
        }

        return $reportKind !== RecordFranceEReportingPayment::REPORT_KIND_CORRECTIVE;
    }

    /**
     * @param array<int, mixed> $entries
     * @return array<int, mixed>
     */
    private function filterInstances(array $entries, string $class): array
    {
        return array_values(array_filter($entries, static fn (mixed $entry): bool => $entry instanceof $class));
    }

    /**
     * @param Collection<int, TransactionEvent> $events
     */
    private function periodLabel(Company $company, int $submissionEventId, string $periodEnd, Collection $events): string
    {
        $profile = $this->profile($company, $submissionEventId, $events);
        $period = ReportingCalendar::currentPeriod($profile, CarbonImmutable::parse($periodEnd));

        return $period->start->toDateString().' - '.$period->end->toDateString();
    }

    /**
     * @param Collection<int, TransactionEvent> $events
     */
    private function profile(Company $company, int $submissionEventId, Collection $events): ReportingProfile
    {
        if ($submissionEventId === TransactionEvent::FR_REPORT_SUBMISSION_VAT_EXCLUDED) {
            return ReportingProfile::BiMonthly;
        }

        if ($submissionEventId === TransactionEvent::FR_REPORT_SUBMISSION_CORRECTIVE
            && $events->contains(fn (TransactionEvent $event): bool => $event->event_id === TransactionEvent::FR_VAT_EXCLUDED_PAYMENT)) {
            return ReportingProfile::BiMonthly;
        }

        return ReportingProfile::tryFrom((string) $company->getSetting('france_reporting_schedule'))
            ?? ReportingProfile::TenDay;
    }

    private function documentId(Company $company, int $submissionEventId, string $periodEnd, CarbonImmutable $issuedAt): string
    {
        $kind = match ($submissionEventId) {
            TransactionEvent::FR_REPORT_SUBMISSION_B2C => 'B2C',
            TransactionEvent::FR_REPORT_SUBMISSION_VAT_EXCLUDED => 'VAT-EXCLUDED',
            TransactionEvent::FR_REPORT_SUBMISSION_CORRECTIVE => 'CORRECTIVE',
            default => 'UNKNOWN',
        };

        return 'FR-F10-'.$company->id.'-'.$kind.'-'.str_replace('-', '', $periodEnd).'-'.$issuedAt->format('His');
    }

    private function declarantParty(Company $company): DeclarantPartyData
    {
        $identifier = $this->declarantIdentifier($company);

        return new DeclarantPartyData(
            party: new PartyData(
                companyName: $company->settings->name ?: $company->present()->name(),
                address: [
                    'country' => $company->country()?->iso_3166_2 ?: 'FR',
                ],
            ),
            publicIdentifiers: [$identifier],
        );
    }

    private function declarantIdentifier(Company $company): PublicIdentifierData
    {
        $idNumber = preg_replace('/\D+/', '', (string) $company->getSetting('id_number')) ?: '';

        if (strlen($idNumber) >= 9) {
            return new PublicIdentifierData('FR:SIRENE', substr($idNumber, 0, 9));
        }

        $vatNumber = preg_replace('/\s+/', '', (string) $company->getSetting('vat_number')) ?: '';

        if ($vatNumber !== '') {
            return new PublicIdentifierData('FR:VAT', $vatNumber);
        }

        return new PublicIdentifierData('FR:SIRENE', (string) $company->id);
    }

    private function companyTimezone(Company $company): string
    {
        return $company->timezone()?->name ?: config('app.timezone');
    }
}