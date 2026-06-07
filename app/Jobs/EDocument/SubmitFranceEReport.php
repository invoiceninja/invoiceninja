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
use App\Models\Company;
use App\Models\TransactionEvent;
use App\Services\EDocument\Gateway\Storecove\Storecove;
use App\Services\EDocument\Standards\France\FranceEReportCompiler;
use App\Services\EDocument\Standards\France\FranceEReportPayloadBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
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
    ) {}

    public function handle(
        Storecove $storecove,
        FranceEReportCompiler $compiler,
        FranceEReportPayloadBuilder $payloadBuilder,
    ): void {
        MultiDB::setDb($this->db);

        /** @var Company|null $company */
        $company = Company::query()->with('account')->find($this->companyId);

        if (! $company || ! in_array($this->submissionEventId, TransactionEvent::FR_REPORT_SUBMISSION_EVENTS, true)) {
            return;
        }

        if (! (bool) $company->getSetting('france_reporting_enabled')) {
            return;
        }

        $sourceEvents = $compiler->sourceEvents($company, $this->submissionEventId, $this->periodEnd);

        if ($sourceEvents->isEmpty()) {
            return;
        }

        $issuedAt = CarbonImmutable::now($company->timezone()?->name ?: config('app.timezone'));
        $report = $compiler->compileFromEvents($company, $this->submissionEventId, $this->periodEnd, $sourceEvents, $issuedAt);
        $payload = $payloadBuilder->build($company, $report);
        /** @var TransactionEvent $sourceEvent */
        $sourceEvent = $sourceEvents->first();
        $sourceEventIds = $sourceEvents->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $idempotencyGuid = $this->idempotencyGuid($company, $sourceEventIds);
        $attemptedAt = CarbonImmutable::now($company->timezone()?->name ?: config('app.timezone'));

        try {
            $response = $storecove->proxy
                ->setCompany($company)
                ->submitDocument([
                    ...$payload,
                    'legal_entity_id' => $payload['legalEntityId'],
                    'idempotencyGuid' => $idempotencyGuid,
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
                idempotencyGuid: $idempotencyGuid,
                generatedAt: $issuedAt,
                attemptedAt: $attemptedAt,
                response: [],
                error: [
                    'message' => $exception->getMessage(),
                    'class' => $exception::class,
                ],
            );

            return;
        }

        $this->recordSubmissionAttempt(
            company: $company,
            report: $report,
            sourceEvent: $sourceEvent,
            sourceEventIds: $sourceEventIds,
            idempotencyGuid: $idempotencyGuid,
            generatedAt: $issuedAt,
            attemptedAt: $attemptedAt,
            response: $response,
        );
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping($this->companyId.$this->submissionEventId.$this->periodEnd.$this->db.'.fr-e-report-submit'))
                ->releaseAfter(60)
                ->expireAfter(60),
        ];
    }

    /**
     * @param array<int, int> $sourceEventIds
     */
    private function idempotencyGuid(Company $company, array $sourceEventIds): string
    {
        $sourceEventIds = array_map('intval', $sourceEventIds);
        sort($sourceEventIds);

        return Uuid::uuid5(
            Uuid::NAMESPACE_URL,
            implode('|', [
                'fr-e-report',
                (string) $company->company_key,
                (string) $company->id,
                (string) $this->submissionEventId,
                $this->periodEnd,
                implode(',', $sourceEventIds),
            ]),
        )->toString();
    }

    /**
     * @param array<int, int> $sourceEventIds
     * @param array<string, mixed> $response
     * @param array<string, mixed>|null $error
     */
    private function recordSubmissionAttempt(
        Company $company,
        FRReportData $report,
        TransactionEvent $sourceEvent,
        array $sourceEventIds,
        string $idempotencyGuid,
        CarbonImmutable $generatedAt,
        CarbonImmutable $attemptedAt,
        array $response,
        ?array $error = null,
    ): void {
        $guid = $response['guid'] ?? null;
        $successful = is_null($error) && is_string($guid) && $guid !== '';
        $status = $successful
            ? TransactionEvent::FR_REPORTING_STATUS_SUBMITTED
            : TransactionEvent::FR_REPORTING_STATUS_FAILED;

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
            'reporting_data' => ReportData::fromFRReport($report),
            'payment_request' => [
                'source_event_ids' => $sourceEventIds,
                'generated_at' => $generatedAt->toIso8601String(),
                'attempted_at' => $attemptedAt->toIso8601String(),
                'guid' => $guid,
                'idempotency_guid' => $idempotencyGuid,
                'submitted_at' => $successful ? now()->toIso8601String() : null,
                'failed_at' => $successful ? null : now()->toIso8601String(),
                'error' => $successful ? null : ($error ?? $response),
            ],
        ]);

        TransactionEvent::query()
            ->whereIn('id', $sourceEventIds)
            ->update(['payment_status' => $status]);
    }
}
