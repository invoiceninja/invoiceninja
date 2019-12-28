<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2019. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Jobs\Company;

use App\Factory\CompanyLedgerFactory;
use App\Libraries\MultiDB;
use App\Models\Company;
use App\Models\CompanyLedger;
use App\Models\Invoice;
use Illuminate\Foundation\Bus\Dispatchable;

class UpdateCompanyLedgerWithInvoice
{
    use Dispatchable;

    public $adjustment;

    public $invoice;

    private $company;

    /**
     * Create a new job instance.
     *
     * @return void
     */

    public function __construct(Invoice $invoice, float $adjustment, Company $company)
    {

        $this->invoice = $invoice;

        $this->adjustment = $adjustment;

        $this->company = $company;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle() 
    {
        MultiDB::setDB($this->company->db);

        $balance = 0;

        $ledger = CompanyLedger::whereClientId($this->invoice->client_id)
                                ->whereCompanyId($this->invoice->company_id)
                                ->orderBy('id', 'DESC')
                                ->first();

        if($ledger)
            $balance = $ledger->balance;

        $adjustment = $balance + $this->adjustment;
        
        $company_ledger = CompanyLedgerFactory::create($this->invoice->company_id, $this->invoice->user_id);
        $company_ledger->client_id = $this->invoice->client_id;
        $company_ledger->adjustment = $this->adjustment;
        $company_ledger->balance = $balance + $this->adjustment;
        $company_ledger->save();

        $this->invoice->company_ledger()->save($company_ledger);

    }
}
