<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Jobs\Company;

use App\Models\Company;
use App\Libraries\MultiDB;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use App\Services\Tax\Providers\TaxProvider;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class CompanyTaxRate implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;

    /**
     * Create a new job instance.
     *
     * @param Company $company
     */
    public function __construct(public Company $company)
    {
    }

    public function handle()
    {
        MultiDB::setDB($this->company->db);

        $tp = new TaxProvider($this->company);
        
        $tp->updateCompanyTaxData();
        
    }

    public function middleware()
    {
        return [new WithoutOverlapping($this->company->id)];
    }

}