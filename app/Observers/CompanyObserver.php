<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2025. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Observers;

use App\Events\Company\CompanyDocumentsDeleted;
use App\Models\Company;
use App\Utils\Ninja;

class CompanyObserver
{
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
}
