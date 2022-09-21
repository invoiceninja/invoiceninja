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

namespace App\Services\Bank;

use App\Libraries\MultiDB;
use App\Models\BankTransaction;
use App\Models\Company;
use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class BankService implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $company_id;

    private Company $company;

    private $db;

    private $invoices;

    public $deleteWhenMissingModels = true;

    public function __construct($company_id, $db)
    {
        $this->company_id = $company_id;
        $this->db = $db;
    }

    public function handle()
    {

        MultiDB::setDb($this->db);

        $this->company = Company::find($this->company_id);

        $this->invoices = Invoice::where('company_id', $this->company->id)
                                ->whereIn('status_id', [1,2,3])
                                ->where('is_deleted', 0)
                                ->get();

        $this->match();
    }

    private function match()
    {

        BankTransaction::where('company_id', $this->company->id)
                       ->where('status_id', BankTransaction::STATUS_UNMATCHED)
                       ->cursor()
                       ->each(function ($bt){
                        
                            $invoice = $this->invoices->first(function ($value, $key) use ($bt){

                                    return str_contains($bt->description, $value->number);
                                    
                                });

                            if($invoice)
                            {
                                $bt->invoice_ids = $invoice->hashed_id;
                                $bt->status_id = BankTransaction::STATUS_MATCHED;
                                $bt->save();   
                            }

                       });
    }


}
