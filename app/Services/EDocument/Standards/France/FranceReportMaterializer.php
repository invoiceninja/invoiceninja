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
use App\DataMapper\ReportData;
use App\Models\Client;
use App\Models\Company;
use App\Models\GroupSetting;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Paymentable;
use App\Models\TransactionEvent;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

final readonly class FranceReportMaterializer
{
    public const PROJECTION_SCHEMA_VERSION = 3;

    public function __construct(
        private FranceRuntimeProjection $projection,
        private FranceReportingDeltaCalculator $deltaCalculator,
        private FranceEReportCompiler $compiler,
        private FranceEReportPayloadBuilder $payloadBuilder,
    ) {}

    public function materialize(
        Company $company,
        FranceEReportVariant $family,
        ReportingPeriod $period,
        ?CarbonImmutable $issuedAt = null,
    ): ?TransactionEvent {
        if (! (bool) $company->getSetting('france_reporting_enabled')) {
            return null;
        }

        if ($this->openSubmissionExists($company, $family, $period)) {
            return null;
        }

        $factWatermark = $this->factWatermark($company, $family, $period);
        $projectionDependencyWatermark = $this->projectionDependencyWatermark($company, $family, $period);
        $projectionSourceHash = $this->projectionSourceHash($company, $family, $period);
        $accepted = $this->acceptedBaseline($company, $family, $period);
        $current = $this->projection->current(
            $company,
            $family,
            $period,
        );
        $reportingContextHash = $this->reportingContextHash($company);
        $acceptedContextHash = $this->acceptedContextHash($company, $family, $period);
        $delta = $accepted !== []
            && $acceptedContextHash !== null
            && ! hash_equals($acceptedContextHash, $reportingContextHash)
                ? $this->deltaCalculator->replace($current, $accepted)
                : $this->deltaCalculator->calculate($current, $accepted);

        if ($delta->isEmpty()) {
            $this->acknowledgeFacts(
                $company,
                $family,
                $period,
                $factWatermark,
                $projectionDependencyWatermark,
                $projectionSourceHash,
            );

            return null;
        }

        $projectionHash = $this->projectionHash($delta, $reportingContextHash);

        if ($this->rejectedProjectionExists($company, $family, $period, $projectionHash)) {
            $this->acknowledgeFacts(
                $company,
                $family,
                $period,
                $factWatermark,
                $projectionDependencyWatermark,
                $projectionSourceHash,
            );

            return null;
        }

        $variant = $this->variantFor($family, $accepted !== []);

        $issuedAt ??= CarbonImmutable::now('Europe/Paris');
        $context = $this->compiler->contextForVariant(
            $company,
            $variant,
            $period->end->toDateString(),
            $issuedAt,
            $period->profile,
        );
        $report = $this->compiler->compileVariantFromEntries(
            $company,
            $variant,
            $context,
            $delta->entries,
        );
        $idempotencyGuid = $this->idempotencyGuid($company, $variant, $period, $report->toArray());
        $payload = $this->payloadBuilder->build($company, $context, $report, $idempotencyGuid);
        $payloadHash = hash('sha256', json_encode($payload, JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR));
        $baselineHash = $this->baselineHash($accepted);

        return DB::transaction(function () use (
            $company,
            $family,
            $period,
            $variant,
            $delta,
            $report,
            $payload,
            $payloadHash,
            $idempotencyGuid,
            $baselineHash,
            $projectionHash,
            $reportingContextHash,
            $factWatermark,
            $projectionDependencyWatermark,
            $projectionSourceHash,
        ): ?TransactionEvent {
            $currentCompany = Company::query()->findOrFail($company->id);
            $currentProfile = ReportingProfile::tryFrom(
                (string) $currentCompany->getSetting('france_reporting_schedule'),
            ) ?? ReportingProfile::TenDay;

            if (($family->isTransaction() && $currentProfile !== $period->profile)
                || ! hash_equals($reportingContextHash, $this->reportingContextHash($currentCompany))) {
                return null;
            }

            if ($this->openSubmissionExists($company, $family, $period)) {
                return null;
            }

            if ($factWatermark !== $this->factWatermark($company, $family, $period)) {
                return null;
            }

            if ($projectionDependencyWatermark !== $this->projectionDependencyWatermark($company, $family, $period)) {
                return null;
            }

            if (! hash_equals(
                $projectionSourceHash,
                $this->projectionSourceHash($company, $family, $period),
            )) {
                return null;
            }

            $lockedBaseline = $this->acceptedBaseline($company, $family, $period);

            if (! hash_equals($baselineHash, $this->baselineHash($lockedBaseline))) {
                return null;
            }

            if ($this->rejectedProjectionExists($company, $family, $period, $projectionHash)) {
                $this->resolveFactQuery(
                    $this->unhandledFactQuery($company, $family, $period)
                        ->where('id', '<=', $factWatermark),
                    FranceReportingStatus::Accepted,
                );

                return null;
            }

            $representative = $delta->snapshots[0];
            $factEventIds = $this->unhandledFactQuery($company, $family, $period)
                ->where('id', '<=', $factWatermark)
                ->pluck('id')
                ->map(static fn(mixed $id): int => (int) $id)
                ->all();

            $submission = TransactionEvent::create([
                'company_id' => $company->id,
                'client_id' => $representative->clientId,
                'invoice_id' => $representative->invoiceId ?? 0,
                'payment_id' => $representative->paymentId ?? 0,
                'credit_id' => $representative->creditId ?? 0,
                'event_id' => FranceReportingEventType::ReportSubmission->value,
                'timestamp' => now()->timestamp,
                'period' => $period->end->toDateString(),
                'payment_status' => FranceReportingStatus::Pending->value,
                'reporting_data' => ReportData::fromFRReport($report),
                'payment_request' => [
                    'schema_version' => 1,
                    'role' => 'submission',
                    'family' => $family->isTransaction() ? 'transaction' : 'payment',
                    'variant' => $variant->value,
                    'reporting_profile' => $period->profile->value,
                    'period_start' => $period->start->toDateString(),
                    'payload' => $payload,
                    'payload_hash' => $payloadHash,
                    'projection_hash' => $projectionHash,
                    'reporting_context_hash' => $reportingContextHash,
                    'projection_schema_version' => self::PROJECTION_SCHEMA_VERSION,
                    'idempotency_guid' => $idempotencyGuid,
                    'snapshot_event_ids' => [],
                    'fact_watermark' => $factWatermark,
                    'fact_event_ids' => $factEventIds,
                    'attempts' => [],
                ],
            ]);
            $snapshotIds = [];

            foreach ($delta->snapshots as $subject) {
                $snapshotIds[] = TransactionEvent::create([
                    'company_id' => $company->id,
                    'client_id' => $subject->clientId,
                    'invoice_id' => $subject->invoiceId ?? 0,
                    'payment_id' => $subject->paymentId ?? 0,
                    'credit_id' => $subject->creditId ?? 0,
                    'event_id' => $family->isTransaction()
                        ? FranceReportingEventType::TransactionSnapshot->value
                        : FranceReportingEventType::PaymentSnapshot->value,
                    'timestamp' => now()->timestamp,
                    'period' => $period->end->toDateString(),
                    'payment_status' => FranceReportingStatus::Pending->value,
                    'reporting_data' => $subject->entry
                        ? ReportData::fromFRReportEntry($subject->entry)
                        : null,
                    'payment_request' => [
                        'schema_version' => 1,
                        'role' => 'projection_snapshot',
                        'subject_key' => $subject->key,
                        'subject_hash' => $subject->hash(),
                        'reporting_context_hash' => $reportingContextHash,
                        'variant' => $variant->value,
                        'reporting_profile' => $period->profile->value,
                        'period_start' => $period->start->toDateString(),
                        'submission_event_id' => $submission->id,
                        'tombstone' => is_null($subject->entry),
                    ],
                ])->id;
            }

            $request = $submission->payment_request;
            $request['snapshot_event_ids'] = $snapshotIds;
            $submission->payment_request = $request;
            $submission->save();
            TransactionEvent::query()
                ->where('company_id', $company->id)
                ->whereIn('id', $factEventIds)
                ->update(['payment_status' => FranceReportingStatus::Pending->value]);

            return $submission;
        }, attempts: 3);
    }

    /** @return array<int, FranceReportingSubject> */
    public function acceptedBaseline(
        Company $company,
        FranceEReportVariant $family,
        ReportingPeriod $period,
    ): array {
        $eventType = $family->isTransaction()
            ? FranceReportingEventType::TransactionSnapshot
            : FranceReportingEventType::PaymentSnapshot;
        $subjects = [];

        TransactionEvent::query()
            ->where('company_id', $company->id)
            ->whereIn('client_id', Client::withTrashed()
                ->select('id')
                ->where('company_id', $company->id))
            ->where('event_id', $eventType->value)
            ->where('payment_status', FranceReportingStatus::Accepted->value)
            ->where('period', $period->end->toDateString())
            ->where('payment_request->reporting_profile', $period->profile->value)
            ->where('payment_request->period_start', $period->start->toDateString())
            ->orderBy('id')
            ->each(function (TransactionEvent $event) use (&$subjects): void {
                $key = (string) data_get($event->payment_request, 'subject_key');

                if ($key === '') {
                    return;
                }

                $subjects[$key] = new FranceReportingSubject(
                    key: $key,
                    entry: $event->reporting_data?->frReportEntry,
                    clientId: $event->client_id,
                    invoiceId: $event->invoice_id ?: null,
                    paymentId: $event->payment_id ?: null,
                    creditId: $event->credit_id ?: null,
                );
            });

        ksort($subjects);

        return array_values($subjects);
    }

    private function openSubmissionExists(
        Company $company,
        FranceEReportVariant $family,
        ReportingPeriod $period,
    ): bool {
        return TransactionEvent::query()
            ->where('company_id', $company->id)
            ->whereIn('client_id', Client::withTrashed()
                ->select('id')
                ->where('company_id', $company->id))
            ->where('event_id', FranceReportingEventType::ReportSubmission->value)
            ->where('period', $period->end->toDateString())
            ->where('payment_request->reporting_profile', $period->profile->value)
            ->where('payment_request->period_start', $period->start->toDateString())
            ->where('payment_request->family', $family->isTransaction() ? 'transaction' : 'payment')
            ->whereIn('payment_status', FranceReportingStatus::openValues())
            ->exists();
    }

    private function variantFor(FranceEReportVariant $family, bool $hasAcceptedBaseline): FranceEReportVariant
    {
        if ($family->isTransaction()) {
            return $hasAcceptedBaseline
                ? FranceEReportVariant::TransactionRectificative
                : FranceEReportVariant::TransactionInitial;
        }

        return $hasAcceptedBaseline
            ? FranceEReportVariant::PaymentRectificative
            : FranceEReportVariant::PaymentInitial;
    }

    private function rejectedProjectionExists(
        Company $company,
        FranceEReportVariant $family,
        ReportingPeriod $period,
        string $projectionHash,
    ): bool {
        return TransactionEvent::query()
            ->where('company_id', $company->id)
            ->whereIn('client_id', Client::withTrashed()
                ->select('id')
                ->where('company_id', $company->id))
            ->where('event_id', FranceReportingEventType::ReportSubmission->value)
            ->where('period', $period->end->toDateString())
            ->where('payment_request->reporting_profile', $period->profile->value)
            ->where('payment_request->period_start', $period->start->toDateString())
            ->where('payment_status', FranceReportingStatus::Rejected->value)
            ->whereNull('payment_request->local_disposition')
            ->where('payment_request->family', $family->isTransaction() ? 'transaction' : 'payment')
            ->where('payment_request->projection_hash', $projectionHash)
            ->exists();
    }

    private function projectionHash(FranceReportingDelta $delta, string $reportingContextHash): string
    {
        return hash('sha256', json_encode([
            'schema_version' => self::PROJECTION_SCHEMA_VERSION,
            'reporting_context_hash' => $reportingContextHash,
            'entries' => array_map(
                static fn(FRReportEntryData $entry): array => $entry->toArray(),
                $delta->entries,
            ),
        ], JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR));
    }

    private function acceptedContextHash(
        Company $company,
        FranceEReportVariant $family,
        ReportingPeriod $period,
    ): ?string {
        $hash = TransactionEvent::query()
            ->where('company_id', $company->id)
            ->whereIn('client_id', Client::withTrashed()
                ->select('id')
                ->where('company_id', $company->id))
            ->where('event_id', FranceReportingEventType::ReportSubmission->value)
            ->where('payment_status', FranceReportingStatus::Accepted->value)
            ->where('period', $period->end->toDateString())
            ->where('payment_request->family', $family->isTransaction() ? 'transaction' : 'payment')
            ->where('payment_request->reporting_profile', $period->profile->value)
            ->where('payment_request->period_start', $period->start->toDateString())
            ->orderByDesc('id')
            ->value('payment_request->reporting_context_hash');

        return is_string($hash) && $hash !== '' ? $hash : null;
    }

    public function reportingContextHash(Company $company): string
    {
        $idNumber = preg_replace('/\D+/', '', (string) $company->getSetting('id_number')) ?: '';

        return hash('sha256', json_encode([
            'legal_entity_id' => (int) $company->legal_entity_id,
            'declarant_name' => (string) ($company->settings->name ?: $company->present()->name()),
            'declarant_siren' => strlen($idNumber) === 14 ? substr($idNumber, 0, 9) : $idNumber,
            'vat_number' => (string) $company->getSetting('vat_number'),
            'address1' => (string) $company->getSetting('address1'),
            'address2' => (string) $company->getSetting('address2'),
            'city' => (string) $company->getSetting('city'),
            'postal_code' => (string) $company->getSetting('postal_code'),
            'country_id' => (string) $company->getSetting('country_id'),
            'phone' => (string) $company->getSetting('phone'),
            'currency_id' => (string) $company->getSetting('currency_id'),
            'e_invoice' => $company->e_invoice,
        ], JSON_THROW_ON_ERROR));
    }

    private function factWatermark(
        Company $company,
        FranceEReportVariant $family,
        ReportingPeriod $period,
    ): int {
        return (int) $this->unhandledFactQuery($company, $family, $period)->max('id');
    }

    private function projectionDependencyWatermark(
        Company $company,
        FranceEReportVariant $family,
        ReportingPeriod $period,
    ): int {
        if ($family->isTransaction()) {
            return 0;
        }

        $invoiceIds = TransactionEvent::query()
            ->where('company_id', $company->id)
            ->whereIn('client_id', Client::withTrashed()
                ->select('id')
                ->where('company_id', $company->id))
            ->where('event_id', FranceReportingEventType::PaymentMovement->value)
            ->where('payment_request->reporting_path', 'f10')
            ->where('period', $period->end->toDateString())
            ->select('invoice_id');

        return (int) TransactionEvent::query()
            ->where('company_id', $company->id)
            ->whereIn('client_id', Client::withTrashed()
                ->select('id')
                ->where('company_id', $company->id))
            ->where('event_id', FranceReportingEventType::PaymentMovement->value)
            ->where('payment_request->reporting_path', 'f10')
            ->whereIn('invoice_id', $invoiceIds)
            ->max('id');
    }

    private function acknowledgeFacts(
        Company $company,
        FranceEReportVariant $family,
        ReportingPeriod $period,
        int $factWatermark,
        int $projectionDependencyWatermark,
        string $projectionSourceHash,
    ): void {
        if ($factWatermark === 0) {
            return;
        }

        DB::transaction(function () use (
            $company,
            $family,
            $period,
            $factWatermark,
            $projectionDependencyWatermark,
            $projectionSourceHash,
        ): void {
            if ($factWatermark !== $this->factWatermark($company, $family, $period)) {
                return;
            }

            if ($projectionDependencyWatermark !== $this->projectionDependencyWatermark($company, $family, $period)) {
                return;
            }

            if (! hash_equals(
                $projectionSourceHash,
                $this->projectionSourceHash($company, $family, $period),
            )) {
                return;
            }

            $this->resolveFactQuery(
                $this->unhandledFactQuery($company, $family, $period)
                    ->where('id', '<=', $factWatermark),
                FranceReportingStatus::Accepted,
            );
        }, attempts: 3);
    }

    private function projectionSourceHash(
        Company $company,
        FranceEReportVariant $family,
        ReportingPeriod $period,
    ): string {
        if ($family->isTransaction()) {
            return '';
        }

        $invoiceIds = TransactionEvent::query()
            ->where('company_id', $company->id)
            ->whereIn('client_id', Client::withTrashed()
                ->select('id')
                ->where('company_id', $company->id))
            ->where('event_id', FranceReportingEventType::PaymentMovement->value)
            ->where('payment_request->reporting_path', 'f10')
            ->where('period', $period->end->toDateString())
            ->pluck('invoice_id')
            ->filter()
            ->unique()
            ->values();

        if ($invoiceIds->isEmpty()) {
            return hash('sha256', '[]');
        }

        $invoiceQuery = Invoice::withTrashed()
            ->where('company_id', $company->id)
            ->whereIn('id', $invoiceIds)
            ->orderBy('id');
        $paymentableQuery = Paymentable::withTrashed()
            ->where('paymentable_type', 'invoices')
            ->whereIn('paymentable_id', $invoiceIds)
            ->orderBy('id');

        $invoices = $invoiceQuery->get();
        $paymentables = $paymentableQuery->get();
        $paymentQuery = Payment::withTrashed()
            ->where('company_id', $company->id)
            ->whereIn('id', $paymentables->pluck('payment_id')->filter()->unique())
            ->orderBy('id');
        $payments = $paymentQuery->get();
        $clients = Client::withTrashed()
            ->where('company_id', $company->id)
            ->whereIn('id', $invoices->pluck('client_id')->filter()->unique())
            ->orderBy('id')
            ->get();
        $groupSettings = GroupSetting::query()
            ->where('company_id', $company->id)
            ->whereIn('id', $clients->pluck('group_settings_id')->filter()->unique())
            ->orderBy('id')
            ->get();

        return hash('sha256', json_encode([
            'invoices' => $invoices->map(static fn(Invoice $invoice): array => [
                'id' => (int) $invoice->id,
                'client_id' => (int) $invoice->client_id,
                'status_id' => (int) $invoice->status_id,
                'balance' => (string) $invoice->balance,
                'is_deleted' => (bool) $invoice->is_deleted,
                'deleted_at' => $invoice->getRawOriginal('deleted_at'),
                'updated_at' => $invoice->getRawOriginal('updated_at'),
                'e_invoice_status' => (string) ($invoice->backup->e_invoice_status ?? ''),
            ])->all(),
            'paymentables' => $paymentables->map(static fn(Paymentable $paymentable): array => [
                'id' => (int) $paymentable->id,
                'payment_id' => (int) $paymentable->payment_id,
                'invoice_id' => (int) $paymentable->paymentable_id,
                'amount' => (string) $paymentable->amount,
                'refunded' => (string) $paymentable->refunded,
                'deleted_at' => $paymentable->getRawOriginal('deleted_at'),
                'updated_at' => $paymentable->getRawOriginal('updated_at'),
            ])->all(),
            'payments' => $payments->map(static fn(Payment $payment): array => [
                'id' => (int) $payment->id,
                'status_id' => (int) $payment->status_id,
                'amount' => (string) $payment->amount,
                'applied' => (string) $payment->applied,
                'refunded' => (string) $payment->refunded,
                'type_id' => (int) $payment->type_id,
                'date' => (string) $payment->date,
                'is_deleted' => (bool) $payment->is_deleted,
                'deleted_at' => $payment->getRawOriginal('deleted_at'),
                'updated_at' => $payment->getRawOriginal('updated_at'),
            ])->all(),
            'clients' => $clients->map(static fn(Client $client): array => [
                'id' => (int) $client->id,
                'country_id' => (int) $client->country_id,
                'classification' => (string) $client->classification,
                'group_settings_id' => (int) $client->group_settings_id,
                'settings' => $client->settings,
                'updated_at' => $client->getRawOriginal('updated_at'),
            ])->all(),
            'group_settings' => $groupSettings->map(static fn(GroupSetting $settings): array => [
                'id' => (int) $settings->id,
                'settings' => $settings->settings,
                'updated_at' => $settings->getRawOriginal('updated_at'),
            ])->all(),
        ], JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR));
    }

    private function factQuery(
        Company $company,
        FranceEReportVariant $family,
        ReportingPeriod $period,
    ): Builder {
        return TransactionEvent::query()
            ->where('company_id', $company->id)
            ->whereIn('client_id', Client::withTrashed()
                ->select('id')
                ->where('company_id', $company->id))
            ->where('period', $period->end->toDateString())
            ->where('payment_request->reporting_profile', $period->profile->value)
            ->where('payment_request->period_start', $period->start->toDateString())
            ->where(function ($query) use ($family): void {
                if ($family->isTransaction()) {
                    $query
                        ->where('event_id', FranceReportingEventType::DocumentLifecycle->value)
                        ->where('payment_request->family', 'transaction');

                    return;
                }

                $query->where(function ($movementQuery): void {
                    $movementQuery
                        ->where('event_id', FranceReportingEventType::PaymentMovement->value)
                        ->where('payment_request->reporting_path', 'f10');
                })->orWhere(function ($lifecycleQuery): void {
                    $lifecycleQuery
                        ->where('event_id', FranceReportingEventType::DocumentLifecycle->value)
                        ->where('payment_request->family', 'payment');
                });
            });
    }

    private function unhandledFactQuery(
        Company $company,
        FranceEReportVariant $family,
        ReportingPeriod $period,
    ): Builder {
        return $this->factQuery($company, $family, $period)
            ->where(function ($query): void {
                $query->whereNull('payment_status')
                    ->orWhere(function ($gatedQuery): void {
                        $gatedQuery
                            ->whereNotNull('payment_request->projection_gate')
                            ->where(
                                'payment_request->projection_schema_version',
                                '<',
                                self::PROJECTION_SCHEMA_VERSION,
                            );
                    });
            });
    }

    public function resolveSubmissionFacts(
        TransactionEvent $submission,
        FranceReportingStatus $status,
    ): void {
        $request = $submission->payment_request ?? [];
        $family = (string) ($request['family'] ?? '');
        $factEventIds = array_values(array_filter(
            array_map('intval', $request['fact_event_ids'] ?? []),
            static fn(int $id): bool => $id > 0,
        ));

        if ($factEventIds === [] || ! in_array($family, ['transaction', 'payment'], true)) {
            return;
        }

        $query = TransactionEvent::query()
            ->where('company_id', $submission->company_id)
            ->whereIn('id', $factEventIds)
            ->where('payment_status', FranceReportingStatus::Pending->value);

        $this->resolveFactQuery($query, $status);

    }

    public function reopenSubmissionFacts(TransactionEvent $submission): void
    {
        $factEventIds = array_values(array_filter(
            array_map('intval', data_get($submission->payment_request, 'fact_event_ids', [])),
            static fn(int $id): bool => $id > 0,
        ));

        if ($factEventIds === []) {
            return;
        }

        TransactionEvent::query()
            ->where('company_id', $submission->company_id)
            ->whereIn('id', $factEventIds)
            ->where('payment_status', FranceReportingStatus::Pending->value)
            ->update(['payment_status' => null]);
    }

    /**
     * @param Builder<TransactionEvent> $query
     */
    private function resolveFactQuery(Builder $query, FranceReportingStatus $status): void
    {
        if ($status === FranceReportingStatus::Accepted) {
            (clone $query)
                ->whereNotNull('payment_request->projection_gate')
                ->each(function (TransactionEvent $event): void {
                    $request = $event->payment_request ?? [];
                    $request['projection_schema_version'] = self::PROJECTION_SCHEMA_VERSION;
                    $event->payment_request = $request;
                    $event->payment_status = FranceReportingStatus::Accepted->value;
                    $event->save();
                });
        }

        $query->update(['payment_status' => $status->value]);
    }

    /** @param array<int, FranceReportingSubject> $subjects */
    private function baselineHash(array $subjects): string
    {
        return hash('sha256', json_encode(array_map(
            static fn(FranceReportingSubject $subject): array => [$subject->key, $subject->hash()],
            $subjects,
        ), JSON_THROW_ON_ERROR));
    }

    /** @param array<string, mixed> $report */
    private function idempotencyGuid(
        Company $company,
        FranceEReportVariant $variant,
        ReportingPeriod $period,
        array $report,
    ): string {
        return Uuid::uuid5(Uuid::NAMESPACE_URL, implode('|', [
            'fr-e-report',
            (string) $company->company_key,
            (string) $company->id,
            $variant->value,
            $period->end->toDateString(),
            hash('sha256', json_encode($report, JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR)),
        ]))->toString();
    }
}
