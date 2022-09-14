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
use Illuminate\Support\Carbon;

class ProcessBankTransactions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private string $bank_integration_account_id;

    private BankIntegration $bank_integration;

    private ?string $from_date;

    private bool $stop_loop = true;
    /**
     * Create a new job instance.
     */
    public function __construct(string $bank_integration_account_id, BankIntegration $bank_integration)
    {
        $this->bank_integration_account_id = $bank_integration_account_id;
        $this->bank_integration = $bank_integration;
        $this->from_date = $bank_integration->from_date;
    }

    /**
     * Execute the job.
     *
     *
     * @return void
     */
    public function handle()
    {

        set_time_limit(0);
        //Loop through everything until we are up to date

        $this->from_date = $this->from_date ?: '2021-01-01';

        do{

            $this->processTransactions();

        }
        while($this->stop_loop);

    }


    private function processTransactions()
    {
        $yodlee = new Yodlee($this->bank_integration_account_id);

        $data = [
            'top' => 500,
            'fromDate' => $this->from_date, 
            'toDate' => now()->format('Y-m-d'),
            'accountId' => $this->bank_integration->bank_account_id,
        ];

nlog($data);

        $transaction_count = $yodlee->getTransactionCount($data);

        $count = $transaction_count->transaction->TOTAL->count;

        //expense transactions
        $transactions = $yodlee->getTransactions($data); 

        if(count($transactions) == 0)
            return;

        $company = $this->bank_integration->company;

        MultiDB::setDb($company->db);

        $user_id = $company->owner()->id;
        
        BankTransaction::unguard();

nlog("transactions = " . count($transactions));

        foreach($transactions as $transaction)
        {

            if(BankTransaction::where('transaction_id', $transaction['transaction_id'])->where('company_id', $company->id)->withTrashed()->exists())
                continue;

        nlog("inserting");

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

        $last_transaction = reset($transactions);

nlog("last tranny = " . $last_transaction['id']);

        $this->bank_integration->from_date = isset($last_transaction['date']) ? \Carbon\Carbon::parse($last_transaction['date']) : now();
        
        $this->from_date = \Carbon\Carbon::parse($this->bank_integration->from_date)->format('Y-m-d');

        $this->bank_integration->save();

        if($count < 500)
            $this->stop_loop = false;

    }

}