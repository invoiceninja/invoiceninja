<?php
/**
 * Credit Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Credit Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Jobs\Bank;

use App\Helpers\Bank\Yodlee\Yodlee;
use App\Libraries\MultiDB;
use App\Models\BankIntegration;
use App\Models\BankTransaction;
use App\Services\Bank\BankService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessBankTransactions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private string $bank_integration_account_id;

    private BankIntegration $bank_integration;

    private string $from_date;

    /**
     * Create a new job instance.
     */
    public function __construct(string $bank_integration_account_id, BankIntegration $bank_integration, string $from_date = '2022-01-01')
    {
        $this->bank_integration_account_id = $bank_integration_account_id;
        $this->bank_integration = $bank_integration;
        $this->from_date = $from_date;
    }

    /**
     * Execute the job.
     *
     *
     * @return void
     */
    public function handle()
    {

        $yodlee = new Yodlee($this->bank_integration_account_id);

        $data = [
            'top' => 500,
            'fromDate' => $this->from_date, /// YYYY-MM-DD
            'accountId' => $this->bank_integration->bank_account_id,
        ];

        //expense transactions
        $transactions = $yodlee->getTransactions($data); 

        $company = $this->bank_integration->company;
        $user_id = $company->owner()->id;
        
        BankTransaction::unguard();

        foreach($transactions as $transaction)
        {

            if(BankTransaction::where('transaction_id', $transaction['transaction_id'])->where('company_id', $company->id)->withTrashed()->exists())
                continue;

            //this should be much faster to insert than using ::create()
            $bt = \DB::table('bank_transactions')->insert(
                array_merge($transaction,[
                    'company_id' => $company->id,
                    'user_id' => $user_id,
                    'bank_integration_id' => $this->bank_integration->id,
                ])
            );

        }

        BankService::dispatch($company->id, $company->db);

        MultiDB::setDb($company->db);

        $last_transaction = end($transactions);

        $this->bank_integration->from_date = isset($last_transaction['date']) ? \Carbon\Carbon::parse($last_transaction['date']) : now();
        $this->bank_integration->save();
        
    }

}