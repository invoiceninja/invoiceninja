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

use App\Models\Client;
use App\Models\Company;
use App\Libraries\MultiDB;
use Illuminate\Bus\Queueable;
use App\Jobs\Client\UpdateTaxData;
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

        if(!config('services.tax.zip_tax.key')) {
            return;
        }

        MultiDB::setDB($this->company->db);

        $tp = new TaxProvider($this->company);

        $tp->updateCompanyTaxData();

        $tp = null;

        Client::query()
              ->where('company_id', $this->company->id)
              ->where('is_deleted', false)
              ->where('country_id', 840)
              ->whereNotNull('postal_code')
              ->whereNull('tax_data')
              ->where('is_tax_exempt', false)
              ->cursor()
              ->each(function ($client) {
                
                  (new UpdateTaxData($client, $this->company))->handle();
                  
              });
        
    }

    public function middleware()
    {
        return [new WithoutOverlapping($this->company->id)];
    }

}