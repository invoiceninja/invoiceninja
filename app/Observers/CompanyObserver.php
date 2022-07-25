<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
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
            //fire event to build new custom portal domain
            \Modules\Admin\Jobs\Domain\CustomDomain::dispatch($company->getOriginal('portal_domain'), $company)->onQueue('domain');
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
