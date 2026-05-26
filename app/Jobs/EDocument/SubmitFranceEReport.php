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
use Illuminate\Support\Str;

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

        $sourceEvents = $compiler->sourceEvents($company, $this->submissionEventId, $this->periodEnd);

        if ($sourceEvents->isEmpty()) {
            return;
        }

        $issuedAt = CarbonImmutable::now($company->timezone()?->name ?: config('app.timezone'));
        $report = $compiler->compileFromEvents($company, $this->submissionEventId, $this->periodEnd, $sourceEvents, $issuedAt);
        $submission = $this->createSubmissionEvent($company, $report, $sourceEvents->pluck('id')->all(), $issuedAt);
        $payload = $payloadBuilder->build($company, $report);
        $idempotencyGuid = Str::uuid()->toString();

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

        $this->recordSubmissionResponse($submission, $sourceEvents->pluck('id')->all(), $response, $idempotencyGuid);
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
    private function createSubmissionEvent(Company $company, mixed $report, array $sourceEventIds, CarbonImmutable $issuedAt): TransactionEvent
    {
        return TransactionEvent::create([
            'company_id' => $company->id,
            'client_id' => 0,
            'invoice_id' => 0,
            'payment_id' => 0,
            'credit_id' => 0,
            'event_id' => $this->submissionEventId,
            'timestamp' => now()->timestamp,
            'period' => $this->periodEnd,
            'payment_status' => TransactionEvent::FR_REPORTING_STATUS_COMPILED,
            'reporting_data' => ReportData::fromFRReport($report),
            'payment_request' => [
                'source_event_ids' => $sourceEventIds,
                'compiled_at' => $issuedAt->toIso8601String(),
            ],
        ]);
    }

    /**
     * @param array<int, int> $sourceEventIds
     * @param array<string, mixed> $response
     */
    private function recordSubmissionResponse(TransactionEvent $submission, array $sourceEventIds, array $response, string $idempotencyGuid): void
    {
        $guid = $response['guid'] ?? null;
        $successful = is_string($guid) && $guid !== '';
        $status = $successful
            ? TransactionEvent::FR_REPORTING_STATUS_SUBMITTED
            : TransactionEvent::FR_REPORTING_STATUS_FAILED;

        $submission->payment_status = $status;
        $submission->payment_request = [
            ...($submission->payment_request ?? []),
            'guid' => $guid,
            'idempotency_guid' => $idempotencyGuid,
            'submitted_at' => $successful ? now()->toIso8601String() : null,
            'error' => $successful ? null : $response,
        ];
        $submission->save();

        TransactionEvent::query()
            ->whereIn('id', $sourceEventIds)
            ->update(['payment_status' => $status]);
    }
}