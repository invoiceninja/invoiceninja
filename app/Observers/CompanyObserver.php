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

namespace App\Observers;

use App\Events\Company\CompanyDocumentsDeleted;
use App\Models\Client;
use App\Models\Company;
use App\Models\TransactionEvent;
use App\Services\EDocument\Standards\France\FranceReportingEventType;
use App\Services\EDocument\Standards\France\FranceReportingStatus;
use App\Services\EDocument\Standards\France\FranceScopeInvalidationRecorder;
use App\Utils\Ninja;
use Illuminate\Validation\ValidationException;

class CompanyObserver
{
    public function updating(Company $company): void
    {
        $wasEnabled = (bool) $this->originalSetting($company, 'france_reporting_enabled');
        $willBeEnabled = (bool) $company->getSetting('france_reporting_enabled');

        if ($wasEnabled && ! $willBeEnabled) {
            $hasReportingHistory = TransactionEvent::query()
                ->where('company_id', $company->id)
                ->whereIn('event_id', FranceReportingEventType::retainedValues())
                ->exists();

            if ($hasReportingHistory) {
                throw ValidationException::withMessages([
                    'france_reporting_enabled' => 'France reporting cannot be disabled after reporting history exists.',
                ]);
            }
        }

        $scheduleChanged = (string) $this->originalSetting($company, 'france_reporting_schedule')
            !== (string) $company->getSetting('france_reporting_schedule');
        $legalEntityChanged = $company->isDirty('legal_entity_id');

        if (($scheduleChanged || $legalEntityChanged)
            && $this->hasLockedReportContext($company)) {
            throw ValidationException::withMessages([
                $legalEntityChanged ? 'legal_entity_id' : 'france_reporting_schedule'
                    => $legalEntityChanged
                        ? 'The France reporting legal entity cannot change while a report is open or after one has been accepted.'
                        : 'The France reporting schedule cannot change while a report is open or after one has been accepted.',
            ]);
        }
    }

    /**
     * Handle the company "created" event.
     *
     * @param Company $company
     * @return void
     */
    public function created(Company $company)
    {
        //
    }

    /**
     * Handle the company "updated" event.
     *
     * @param Company $company
     * @return void
     */
    public function updated(Company $company)
    {
        $originalReportingEnabled = (bool) $this->originalSetting($company, 'france_reporting_enabled');
        $originalSchedule = (string) $this->originalSetting($company, 'france_reporting_schedule');
        $reportingEnabled = (bool) $company->getSetting('france_reporting_enabled');
        $reportingSchedule = (string) $company->getSetting('france_reporting_schedule');
        $scheduleChanged = $originalSchedule !== $reportingSchedule;

        if ($reportingEnabled
            && ($originalReportingEnabled !== $reportingEnabled || $scheduleChanged)) {
            app(FranceScopeInvalidationRecorder::class)->recordAndDispatch(
                company: $company,
                supersedeUnacceptedTransactionScopes: $scheduleChanged,
                initializeCurrentPeriods: ! $originalReportingEnabled,
            );
        }

        if (Ninja::isHosted() && $company->portal_mode == 'domain' && $company->isDirty('portal_domain')) {
            \Modules\Admin\Jobs\Domain\CustomDomain::dispatch($company->getOriginal('portal_domain'), $company);
        }

        if (Ninja::isHosted()) {

            $property = 'name';
            $original = data_get($company->getOriginal('settings'), $property);
            $current = data_get($company->settings, $property);

            if ($original !== $current) {
                try {
                    (new \Modules\Admin\Jobs\Account\FieldQuality())->checkCompanyName($current, $company);
                } catch (\Throwable $e) {
                    nlog(['company_name_check', $e->getMessage()]);
                }
            }

        }

    }

    /**
     * Handle the company "deleted" event.
     *
     * @param Company $company
     * @return void
     */
    public function deleted(Company $company)
    {
        event(new CompanyDocumentsDeleted($company));
    }

    /**
     * Handle the company "restored" event.
     *
     * @param Company $company
     * @return void
     */
    public function restored(Company $company)
    {
        //
    }

    /**
     * Handle the company "force deleted" event.
     *
     * @param Company $company
     * @return void
     */
    public function forceDeleted(Company $company)
    {
        //
    }

    private function originalSetting(Company $company, string $key): mixed
    {
        $settings = $company->getOriginal('settings');

        if (is_string($settings)) {
            $settings = json_decode($settings, true);
        }

        return data_get($settings, $key);
    }

    private function hasLockedReportContext(Company $company): bool
    {
        return TransactionEvent::query()
            ->where('company_id', $company->id)
            ->whereIn('client_id', Client::withTrashed()
                ->select('id')
                ->where('company_id', $company->id))
            ->where('event_id', FranceReportingEventType::ReportSubmission->value)
            ->whereIn('payment_status', [
                ...FranceReportingStatus::openValues(),
                FranceReportingStatus::Accepted->value,
            ])
            ->exists();
    }

}
