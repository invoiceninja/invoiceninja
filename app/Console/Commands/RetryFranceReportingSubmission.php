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

namespace App\Console\Commands;

use App\Jobs\EDocument\SubmitFranceEReport;
use App\Jobs\EDocument\SubmitFrancePaymentReceivedNotification;
use App\Libraries\MultiDB;
use App\Models\Company;
use App\Models\TransactionEvent;
use App\Services\EDocument\Standards\France\FranceReportingEventType;
use App\Services\EDocument\Standards\France\FranceReportingStatus;
use Illuminate\Console\Command;

class RetryFranceReportingSubmission extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'france-reporting:retry-submission
                            {submission : Transaction event ID}
                            {--database= : Database connection name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Retry one quarantined France reporting submission';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $database = trim((string) $this->option('database')) ?: (string) config('database.default');
        MultiDB::setDb($database);
        $submission = TransactionEvent::query()->find((int) $this->argument('submission'));

        if (! $submission
            || ! in_array((int) $submission->event_id, FranceReportingEventType::submissionValues(), true)
            || (int) $submission->payment_status !== FranceReportingStatus::RetryableFailure->value
            || ! data_get($submission->payment_request, 'retry_exhausted_at')) {
            $this->error('The event is not a quarantined France reporting submission.');

            return self::FAILURE;
        }

        $company = Company::query()->find($submission->company_id);

        if (! $company
            || ! (bool) $company->getSetting('france_reporting_enabled')) {
            return self::SUCCESS;
        }

        if ((int) $submission->event_id === FranceReportingEventType::ReportSubmission->value) {
            SubmitFranceEReport::dispatch($submission->id, $database, true);
        } else {
            SubmitFrancePaymentReceivedNotification::dispatch($submission->id, $database, true);
        }

        $this->info("France reporting submission {$submission->id} was queued for a forced retry.");

        return self::SUCCESS;
    }
}
