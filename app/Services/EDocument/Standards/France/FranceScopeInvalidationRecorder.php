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

use App\Jobs\EDocument\RecordFranceEReportingScopeInvalidation;
use App\Models\Client;
use App\Models\Company;
use App\Models\TransactionEvent;
use App\Utils\Ninja;
use Illuminate\Support\Str;

class FranceScopeInvalidationRecorder
{
    public function recordCompanyConfigurationChange(
        Company $company,
        object $originalSettings,
    ): ?TransactionEvent {
        if (! Ninja::isHosted()) {
            return null;
        }

        $wasEnabled = (bool) data_get($originalSettings, 'france_reporting_enabled');
        $isEnabled = (bool) $company->getSetting('france_reporting_enabled');
        $scheduleChanged = (string) data_get($originalSettings, 'france_reporting_schedule')
            !== (string) $company->getSetting('france_reporting_schedule');

        if (! $isEnabled || ($wasEnabled === $isEnabled && ! $scheduleChanged)) {
            return null;
        }

        return $this->recordAndDispatch(
            company: $company,
            supersedeUnacceptedTransactionScopes: $scheduleChanged,
            initializeCurrentPeriods: ! $wasEnabled,
        );
    }

    /**
     * @param array<int, int> $clientIds
     */
    public function recordAndDispatch(
        Company $company,
        ?int $clientId = null,
        bool $supersedeUnacceptedTransactionScopes = false,
        bool $initializeCurrentPeriods = false,
        array $clientIds = [],
    ): ?TransactionEvent {
        if (! (bool) $company->getSetting('france_reporting_enabled')) {
            return null;
        }

        $representativeClientId = $clientId
            ?? collect($clientIds)->map(fn($id): int => (int) $id)->filter()->first()
            ?? Client::withTrashed()
                ->where('company_id', $company->id)
                ->orderBy('id')
                ->value('id');

        if (! $representativeClientId) {
            return null;
        }

        $invalidationKey = Str::uuid()->toString();
        $event = TransactionEvent::create([
            'company_id' => $company->id,
            'client_id' => $representativeClientId,
            'invoice_id' => 0,
            'payment_id' => 0,
            'credit_id' => 0,
            'event_id' => FranceReportingEventType::ScopeInvalidation->value,
            'timestamp' => now()->timestamp,
            'period' => now('Europe/Paris')->toDateString(),
            'payment_status' => null,
            'reporting_data' => null,
            'payment_request' => [
                'schema_version' => 1,
                'role' => 'scope_invalidation',
                'invalidation_key' => $invalidationKey,
                'client_id' => $clientId,
                'client_ids' => array_values(array_unique(array_map('intval', $clientIds))),
                'supersede_unaccepted_transaction_scopes' => $supersedeUnacceptedTransactionScopes,
                'initialize_current_periods' => $initializeCurrentPeriods,
                'recorded_at' => now()->toIso8601String(),
            ],
        ]);

        RecordFranceEReportingScopeInvalidation::dispatch(
            companyId: $company->id,
            db: $company->db,
            clientId: $clientId,
            invalidationKey: $invalidationKey,
            supersedeUnacceptedTransactionScopes: $supersedeUnacceptedTransactionScopes,
            initializeCurrentPeriods: $initializeCurrentPeriods,
            clientIds: $clientIds,
            invalidationEventId: $event->id,
        )->afterCommit();

        return $event;
    }
}
