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

use App\DataMapper\FranceEReporting\FRReportData;
use App\DataMapper\ReportData;
use App\Libraries\MultiDB;
use App\Models\Account;
use App\Models\Company;
use App\Models\TransactionEvent;
use App\Services\EDocument\Gateway\Storecove\Storecove;
use App\Services\EDocument\Standards\France\FranceEReportCompiler;
use App\Services\EDocument\Standards\France\FranceEReportPayloadBuilder;
use App\Services\EDocument\Standards\France\FranceEReportVariant;
use App\Services\EDocument\Standards\France\FranceSubmissionClaim;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;
use Throwable;

class SubmitFranceEReport implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $deleteWhenMissingModels = true;

    public $tries = 1;

    public function __construct(
        private int $companyId,
        private int $submissionEventId,
        private string $periodEnd,
        private string $db,
        private ?string $variant = null,
    ) {}

    public function handle(
        Storecove $storecove,
        FranceEReportCompiler $compiler,
        FranceEReportPayloadBuilder $payloadBuilder,
    ): void {
        MultiDB::setDb($this->db);

        /** @var Company|null $company */
        $company = Company::query()->with('account')->find($this->companyId);
        $account = $company?->getRelation('account');

        if (! $company
            || $company->is_disabled
            || ! $account instanceof Account
            || $account->is_flagged
            || ! in_array($this->submissionEventId, TransactionEvent::FR_REPORT_SUBMISSION_EVENTS, true)) {
            return;
        }

        if (! (bool) $company->getSetting('france_reporting_enabled')) {
            return;
        }

        $reportVariant = $this->reportVariant($company, $compiler);

        if (is_null($reportVariant)) {
            return;
        }

        if (! $reportVariant->isStorecoveQualified()) {
            return;
        }

        $sourceEvents = $compiler->sourceEventsForVariant($company, $reportVariant, $this->periodEnd);

        if ($sourceEvents->isEmpty()) {
            return;
        }

        $sourceEventIds = $sourceEvents->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $claims = app(FranceSubmissionClaim::class);
        $claimToken = $claims->claim($sourceEventIds);

        if (! $claimToken) {
            return;
        }

        $claimCompleted = false;

        try {
            $sourceEvents = $compiler->sourceEventsForVariant($company, $reportVariant, $this->periodEnd);
            $claimedSourceEventIds = $sourceEvents->pluck('id')->map(fn ($id): int => (int) $id)->all();

            if ($claimedSourceEventIds !== $sourceEventIds) {
                return;
            }

            $issuedAt = CarbonImmutable::parse($this->periodEnd, 'Europe/Paris')->addDay()->startOfDay();
            $context = $compiler->contextForVariant($company, $reportVariant, $this->periodEnd, $issuedAt);
            /** @var TransactionEvent $sourceEvent */
            $sourceEvent = $sourceEvents->first();
            $report = null;
            $idempotencyGuid = null;

            try {
                $report = $compiler->compileVariantFromEvents($company, $reportVariant, $context, $sourceEvents);
                $idempotencyGuid = $this->idempotencyGuid($company, $reportVariant, $report);
                $payload = $payloadBuilder->build(
                    $company,
                    $context,
                    $report,
                    $idempotencyGuid,
                );
            } catch (Throwable $exception) {
                report($exception);

                $this->recordSubmissionAttempt(
                    company: $company,
                    report: $report,
                    sourceEvent: $sourceEvent,
                    sourceEventIds: $sourceEventIds,
                    variant: $reportVariant,
                    claimToken: $claimToken,
                    idempotencyGuid: $idempotencyGuid,
                    generatedAt: $issuedAt,
                    attemptedAt: CarbonImmutable::now($company->timezone()?->name ?: config('app.timezone')),
                    response: [],
                    error: [
                        'message' => $exception->getMessage(),
                        'class' => $exception::class,
                    ],
                    terminal: true,
                );
                $claimCompleted = true;

                return;
            }

            $attemptedAt = CarbonImmutable::now($company->timezone()?->name ?: config('app.timezone'));

            try {
                $response = $storecove->proxy
                    ->setCompany($company)
                    ->submitDocument([
                        ...$payload,
                        'legal_entity_id' => $payload['legalEntityId'],
                        'tenant_id' => $company->company_key,
                        'account_key' => $company->account->key,
                        'e_invoicing_token' => $company->account->e_invoicing_token,
                    ]);
            } catch (Throwable $exception) {
                report($exception);

                $this->recordSubmissionAttempt(
                    company: $company,
                    report: $report,
                    sourceEvent: $sourceEvent,
                    sourceEventIds: $sourceEventIds,
                    variant: $reportVariant,
                    claimToken: $claimToken,
                    idempotencyGuid: $idempotencyGuid,
                    generatedAt: $issuedAt,
                    attemptedAt: $attemptedAt,
                    response: [],
                    error: [
                        'message' => $exception->getMessage(),
                        'class' => $exception::class,
                    ],
                );
                $claimCompleted = true;

                return;
            }

            $this->recordSubmissionAttempt(
                company: $company,
                report: $report,
                sourceEvent: $sourceEvent,
                sourceEventIds: $sourceEventIds,
                variant: $reportVariant,
                claimToken: $claimToken,
                idempotencyGuid: $idempotencyGuid,
                generatedAt: $issuedAt,
                attemptedAt: $attemptedAt,
                response: $response,
            );
            $claimCompleted = true;
        } finally {
            if (! $claimCompleted) {
                $claims->release($sourceEventIds, $claimToken);
            }
        }
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping($this->companyId.$this->submissionEventId.$this->periodEnd.$this->db.($this->variant ?? 'legacy').'.fr-e-report-submit'))
                ->releaseAfter(60)
                ->expireAfter(60),
        ];
    }

    private function idempotencyGuid(
        Company $company,
        FranceEReportVariant $variant,
        FRReportData $report,
    ): string
    {
        $reportFingerprint = hash('sha256', json_encode(
            $report->toArray(),
            JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR,
        ));

        return Uuid::uuid5(
            Uuid::NAMESPACE_URL,
            implode('|', [
                'fr-e-report',
                (string) $company->company_key,
                (string) $company->id,
                $variant->value,
                $this->periodEnd,
                $reportFingerprint,
            ]),
        )->toString();
    }

    private function reportVariant(
        Company $company,
        FranceEReportCompiler $compiler,
    ): ?FranceEReportVariant {
        if (! is_null($this->variant)) {
            return FranceEReportVariant::tryFrom($this->variant);
        }

        $legacyEvents = $compiler->sourceEvents($company, $this->submissionEventId, $this->periodEnd);

        if ($legacyEvents->isEmpty()) {
            return null;
        }

        return $compiler->variantFromEvents($this->submissionEventId, $legacyEvents);
    }

    /**
     * @param array<int, int> $sourceEventIds
     * @param array<string, mixed> $response
     * @param array<string, mixed>|null $error
     */
    private function recordSubmissionAttempt(
        Company $company,
        ?FRReportData $report,
        TransactionEvent $sourceEvent,
        array $sourceEventIds,
        FranceEReportVariant $variant,
        string $claimToken,
        ?string $idempotencyGuid,
        CarbonImmutable $generatedAt,
        CarbonImmutable $attemptedAt,
        array $response,
        ?array $error = null,
        bool $terminal = false,
    ): void {
        $guid = $response['guid'] ?? null;
        $successful = is_null($error) && is_string($guid) && $guid !== '';
        $status = $successful
            ? TransactionEvent::FR_REPORTING_STATUS_SUBMITTED
            : TransactionEvent::FR_REPORTING_STATUS_FAILED;

        DB::transaction(function () use (
            $company,
            $sourceEvent,
            $sourceEventIds,
            $variant,
            $claimToken,
            $status,
            $report,
            $generatedAt,
            $attemptedAt,
            $guid,
            $idempotencyGuid,
            $successful,
            $error,
            $response,
            $terminal,
        ): void {
            $sourceEvents = TransactionEvent::query()
                ->whereIn('id', $sourceEventIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($sourceEvents->count() !== count($sourceEventIds)
                || $sourceEvents->contains(fn (TransactionEvent $event): bool => ! app(FranceSubmissionClaim::class)->isOwnedBy($event, $claimToken))) {
                throw new \RuntimeException('France report submission source claim was lost before persistence.');
            }

            TransactionEvent::create([
                'company_id' => $company->id,
                'client_id' => $sourceEvent->client_id,
                'invoice_id' => $sourceEvent->invoice_id,
                'payment_id' => $sourceEvent->payment_id,
                'credit_id' => $sourceEvent->credit_id,
                'event_id' => $this->submissionEventId,
                'timestamp' => now()->timestamp,
                'period' => $this->periodEnd,
                'payment_status' => $status,
                'reporting_data' => $report ? ReportData::fromFRReport($report) : null,
                'payment_request' => [
                    'source_event_ids' => $sourceEventIds,
                    'variant' => $variant->value,
                    'generated_at' => $generatedAt->toIso8601String(),
                    'attempted_at' => $attemptedAt->toIso8601String(),
                    'guid' => $guid,
                    'idempotency_guid' => $idempotencyGuid,
                    'submitted_at' => $successful ? now()->toIso8601String() : null,
                    'failed_at' => $successful ? null : now()->toIso8601String(),
                    'error' => $successful ? null : ($error ?? $response),
                    'skip_reason' => $terminal ? 'France e-report could not be compiled for Storecove.' : null,
                    'skipped_at' => $terminal ? now()->toIso8601String() : null,
                ],
            ]);

            foreach ($sourceEvents as $claimedSourceEvent) {
                $request = $claimedSourceEvent->payment_request ?? [];
                unset($request[FranceSubmissionClaim::TOKEN], $request[FranceSubmissionClaim::EXPIRES_AT]);

                if ($terminal) {
                    $request['error'] = $error;
                    $request['skip_reason'] = 'France e-report could not be compiled for Storecove.';
                    $request['skipped_at'] = now()->toIso8601String();
                }

                $claimedSourceEvent->payment_request = $request;
                $claimedSourceEvent->payment_status = $status;
                $claimedSourceEvent->save();
            }
        }, attempts: 3);
    }
}
