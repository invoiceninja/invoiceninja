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

namespace Tests\Feature\FranceEReporting;

use App\Models\TransactionEvent;
use App\Services\EDocument\Standards\France\FranceReportingEventType;
use App\Services\EDocument\Standards\France\FranceReportingStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\MockAccountData;
use Tests\TestCase;

class RetryFranceReportingSubmissionTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();
    }

    public function test_command_dispatches_forced_retries_for_both_submission_types(): void
    {
        $report = $this->submission(FranceReportingEventType::ReportSubmission);
        $notification = $this->submission(FranceReportingEventType::PaymentNotificationSubmission);

        $this->artisan('france-reporting:retry-submission', [
            'submission' => $report->id,
            '--database' => $this->company->db,
        ])->expectsOutput("France reporting submission {$report->id} was queued for a forced retry.")
            ->assertSuccessful();
        $this->artisan('france-reporting:retry-submission', [
            'submission' => $notification->id,
            '--database' => $this->company->db,
        ])->expectsOutput("France reporting submission {$notification->id} was queued for a forced retry.")
            ->assertSuccessful();
    }

    public function test_command_rejects_an_event_that_is_not_quarantined(): void
    {
        $submission = $this->submission(FranceReportingEventType::ReportSubmission);
        $submission->payment_status = FranceReportingStatus::Pending->value;
        $submission->save();

        $this->artisan('france-reporting:retry-submission', [
            'submission' => $submission->id,
            '--database' => $this->company->db,
        ])->assertFailed();
    }

    private function submission(FranceReportingEventType $eventType): TransactionEvent
    {
        return TransactionEvent::create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'invoice_id' => 0,
            'payment_id' => 0,
            'credit_id' => 0,
            'event_id' => $eventType->value,
            'timestamp' => now()->timestamp,
            'period' => now()->toDateString(),
            'payment_status' => FranceReportingStatus::RetryableFailure->value,
            'reporting_data' => null,
            'payment_request' => [
                'role' => 'submission',
                'retry_exhausted_at' => now()->toIso8601String(),
            ],
        ]);
    }
}
