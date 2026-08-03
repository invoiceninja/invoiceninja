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

use App\Libraries\MultiDB;
use App\Models\Company;
use App\Models\TransactionEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class UpdateFranceEReportSubmissionStatus implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $tries = 1;

    /**
     * @param array<string, mixed> $input
     */
    public function __construct(private array $input) {}

    public function handle(): void
    {
        $tenantId = (string) ($this->input['tenant_id'] ?? '');
        $guid = (string) ($this->input['guid'] ?? '');

        if ($tenantId === '' || $guid === '') {
            return;
        }

        if (config('ninja.db.multi_db_enabled') && ! MultiDB::findAndSetDbByCompanyKey($tenantId)) {
            return;
        }

        $company = Company::query()->where('company_key', $tenantId)->first();

        if (! $company) {
            return;
        }

        $submission = TransactionEvent::query()
            ->where('company_id', $company->id)
            ->whereIn('event_id', array_merge(TransactionEvent::FR_REPORT_SUBMISSION_EVENTS, TransactionEvent::FR_PAYMENT_NOTIFICATION_EVENTS))
            ->get()
            ->first(fn (TransactionEvent $event): bool => data_get($event->payment_request, 'guid') === $guid);

        if (! $submission) {
            return;
        }

        $status = $this->statusForEvent((string) ($this->input['event'] ?? ''));
        DB::transaction(function () use ($submission, $status): void {
            $lockedSubmission = TransactionEvent::query()
                ->lockForUpdate()
                ->find($submission->id);

            if (! $lockedSubmission) {
                return;
            }

            $history = $lockedSubmission->payment_request['events'] ?? [];
            $history[] = [
                'event' => $this->input['event'] ?? null,
                'event_group' => $this->input['event_group'] ?? null,
                'received_at' => now()->toIso8601String(),
            ];

            if (! is_null($status)) {
                $lockedSubmission->payment_status = $status;
            }

            $lockedSubmission->payment_request = [
                ...($lockedSubmission->payment_request ?? []),
                'last_event' => $this->input['event'] ?? null,
                'last_event_group' => $this->input['event_group'] ?? null,
                'events' => $history,
            ];
            $lockedSubmission->save();

            if (is_null($status)) {
                return;
            }

            $sourceEventIds = $lockedSubmission->payment_request['source_event_ids'] ?? [];

            if (is_array($sourceEventIds) && $sourceEventIds !== []) {
                TransactionEvent::query()
                    ->where('company_id', $lockedSubmission->company_id)
                    ->whereIn('id', $sourceEventIds)
                    ->update(['payment_status' => $status]);
            }
        }, attempts: 3);
    }

    private function statusForEvent(string $event): ?int
    {
        return match ($event) {
            'succeeded', 'cleared', 'accepted' => TransactionEvent::FR_REPORTING_STATUS_SUBMITTED,
            'failed', 'rejected', 'no_action_taken' => TransactionEvent::FR_REPORTING_STATUS_FAILED,
            default => null,
        };
    }
}
