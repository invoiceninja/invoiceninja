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

use App\Jobs\EDocument\UpdateFranceEReportSubmissionStatus;
use App\Models\Client;
use App\Models\Company;
use App\Models\TransactionEvent;
use Throwable;

class FranceSubmissionCallbackStore
{
    /** @param array<string, mixed> $payload */
    public function record(Company $company, string $guid, array $payload): ?TransactionEvent
    {
        if (! (bool) $company->getSetting('france_reporting_enabled')) {
            return null;
        }

        $clientId = Client::withTrashed()
            ->where('company_id', $company->id)
            ->orderBy('id')
            ->value('id');

        if (! $clientId) {
            return null;
        }

        $eventKey = hash('sha256', json_encode([
            'company_id' => $company->id,
            'guid' => $guid,
            'payload' => $payload,
        ], JSON_THROW_ON_ERROR));

        $existing = TransactionEvent::query()
            ->where('company_id', $company->id)
            ->whereIn('client_id', Client::withTrashed()
                ->select('id')
                ->where('company_id', $company->id))
            ->where('event_id', FranceReportingEventType::SubmissionCallback->value)
            ->where('payment_request->event_key', $eventKey)
            ->first();

        return $existing ?? TransactionEvent::create([
            'company_id' => $company->id,
            'client_id' => $clientId,
            'invoice_id' => 0,
            'payment_id' => 0,
            'credit_id' => 0,
            'event_id' => FranceReportingEventType::SubmissionCallback->value,
            'timestamp' => now()->timestamp,
            'period' => now('Europe/Paris')->toDateString(),
            'payment_status' => FranceReportingStatus::Pending->value,
            'reporting_data' => null,
            'payment_request' => [
                'schema_version' => 1,
                'role' => 'inbound_callback',
                'event_key' => $eventKey,
                'guid' => $guid,
                'payload' => $payload,
                'received_at' => now()->toIso8601String(),
            ],
        ]);
    }

    public function replay(Company $company, string $guid): void
    {
        if (! (bool) $company->getSetting('france_reporting_enabled')) {
            return;
        }

        TransactionEvent::query()
            ->where('company_id', $company->id)
            ->whereIn('client_id', Client::withTrashed()
                ->select('id')
                ->where('company_id', $company->id))
            ->where('event_id', FranceReportingEventType::SubmissionCallback->value)
            ->where('payment_status', FranceReportingStatus::Pending->value)
            ->where('payment_request->guid', $guid)
            ->orderBy('id')
            ->eachById(function (TransactionEvent $callback) use ($company, $guid): void {
                try {
                    $payload = data_get($callback->payment_request, 'payload');

                    if (! is_array($payload)) {
                        return;
                    }

                    app()->call([new UpdateFranceEReportSubmissionStatus($payload), 'handle']);

                    $submission = TransactionEvent::query()
                        ->where('company_id', $company->id)
                        ->whereIn('client_id', Client::withTrashed()
                            ->select('id')
                            ->where('company_id', $company->id))
                        ->whereIn('event_id', FranceReportingEventType::submissionValues())
                        ->where(function ($query) use ($guid): void {
                            $query->where('payment_request->guid', $guid)
                                ->orWhereJsonContains('payment_request->guids', $guid);
                        })
                        ->first();

                    if (! $submission) {
                        return;
                    }

                    $callback->delete();
                } catch (Throwable $exception) {
                    report($exception);
                }
            });
    }
}
