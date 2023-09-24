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

use App\Models\Company;
use App\Libraries\MultiDB;
use Illuminate\Bus\Queueable;
use App\Models\BankIntegration;
use App\Models\BankTransaction;
use App\Helpers\Bank\Yodlee\Yodlee;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use App\Services\Bank\BankMatchingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use App\Notifications\Ninja\GenericNinjaAdminNotification;
use App\Helpers\Bank\Yodlee\Transformer\AccountTransformer;

class ProcessBankTransactions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private string $bank_integration_account_id;

    private BankIntegration $bank_integration;

    private ?string $from_date;

    private bool $stop_loop = true;

    private int $skip = 0;

    public Company $company;

    /**
     * Create a new job instance.
     */
    public function __construct(string $bank_integration_account_id, BankIntegration $bank_integration)
    {
        $this->bank_integration_account_id = $bank_integration_account_id;
        $this->bank_integration = $bank_integration;
        $this->from_date = $bank_integration->from_date;
        $this->company = $this->bank_integration->company;
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

        nlog("Processing transactions for account: {$this->bank_integration->account->key}");

        do {
            try {
                $this->processTransactions();
            } catch(\Exception $e) {
                nlog("{$this->bank_integration_account_id} - exited abnormally => ". $e->getMessage());

                $content = [
                    "Processing transactions for account: {$this->bank_integration->account->key} failed",
                    "Exception Details => ",
                    $e->getMessage(),
                ];

                $this->bank_integration->company->notification(new GenericNinjaAdminNotification($content))->ninja();
                return;
            }
        } while ($this->stop_loop);

        BankMatchingService::dispatch($this->company->id, $this->company->db);
    }


    private function processTransactions()
    {
        $yodlee = new Yodlee($this->bank_integration_account_id);

        if (!$yodlee->getAccount($this->bank_integration->bank_account_id)) {
            $this->bank_integration->disabled_upstream = true;
            $this->bank_integration->save();
            $this->stop_loop = false;
            return;
        }

        try {
            $account_summary = $yodlee->getAccountSummary($this->bank_integration->bank_account_id);

            if($account_summary) {

                $at = new AccountTransformer();
                $account = $at->transform($account_summary);

                if($account[0]['current_balance']) {
                    $this->bank_integration->balance = $account[0]['current_balance'];
                    $this->bank_integration->currency = $account[0]['account_currency'];
                    $this->bank_integration->bank_account_status = $account[0]['account_status'];
                    $this->bank_integration->save();
                }
                
            }
        }
        catch(\Exception $e) {
            nlog("YODLEE: unable to update account summary for {$this->bank_integration->bank_account_id} => ". $e->getMessage());
        }

        $data = [
            'top' => 500,
            'fromDate' => $this->from_date,
            'accountId' => $this->bank_integration->bank_account_id,
            'skip' => $this->skip,
        ];

        //Get transaction count object
        $transaction_count = $yodlee->getTransactionCount($data);

        //Get int count
        $count = $transaction_count->transaction->TOTAL->count;

        //get transactions array
        $transactions = $yodlee->getTransactions($data);

        //if no transactions, update the from_date and move on
        if (count($transactions) == 0) {
            $this->bank_integration->from_date = now()->subDays(2);
            $this->bank_integration->disabled_upstream = false;
            $this->bank_integration->save();
            $this->stop_loop = false;
            return;
        }

        //Harvest the company

        MultiDB::setDb($this->company->db);

        /*Get the user */
        $user_id = $this->company->owner()->id;
        
        /* Unguard the model to perform batch inserts */
        BankTransaction::unguard();

        $now = now();
        
        foreach ($transactions as $transaction) {
            if (BankTransaction::query()->where('transaction_id', $transaction['transaction_id'])->where('company_id', $this->company->id)->withTrashed()->exists()) {
                continue;
            }

            //this should be much faster to insert than using ::create()
            $bt = \DB::table('bank_transactions')->insert(
                array_merge($transaction, [
                    'company_id' => $this->company->id,
                    'user_id' => $user_id,
                    'bank_integration_id' => $this->bank_integration->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
            );
        }


        $this->skip = $this->skip + 500;

        if ($count < 500) {
            $this->stop_loop = false;
            $this->bank_integration->from_date = now()->subDays(2);
            $this->bank_integration->save();
        }
    }


    public function middleware()
    {
        return [new WithoutOverlapping($this->bank_integration_account_id)];
    }
    
    public function backoff()
    {
        return [rand(10, 15), rand(30, 40), rand(60, 79), rand(160, 200), rand(3000, 5000)];
    }
}
