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

use App\Helpers\Bank\Nordigen\Nordigen;
use App\Libraries\MultiDB;
use App\Models\Account;
use App\Models\BankIntegration;
use App\Models\BankTransaction;
use App\Models\Company;
use App\Services\Bank\BankMatchingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessBankTransactionsNordigen implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private Account $account;

    private BankIntegration $bank_integration;

    private ?string $from_date;

    private bool $stop_loop = true;

    private int $skip = 0;

    public Company $company;

    /**
     * Create a new job instance.
     */
    public function __construct(Account $account, BankIntegration $bank_integration)
    {
        $this->account = $account;
        $this->bank_integration = $bank_integration;
        $this->from_date = $bank_integration->from_date;
        $this->company = $this->bank_integration->company;

        if ($this->bank_integration->integration_type != BankIntegration::INTEGRATION_TYPE_NORDIGEN)
            throw new \Exception("Invalid BankIntegration Type");

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

        do {

            try {
                $this->processTransactions();
            } catch (\Exception $e) {
                nlog("{$this->account->bank_integration_nordigen_secret_id} - exited abnormally => " . $e->getMessage());
                return;
            }

        }
        while ($this->stop_loop);

        BankMatchingService::dispatch($this->company->id, $this->company->db);

    }


    private function processTransactions()
    {

        $nordigen = new Nordigen($this->account->bank_integration_nordigen_secret_id, $this->account->bank_integration_nordigen_secret_key); // TODO: maybe implement credentials

        if (!$nordigen->isAccountActive($this->bank_integration->bank_account_id)) {
            $this->bank_integration->disabled_upstream = true;
            $this->bank_integration->save();
            $this->stop_loop = false;
            // @turbo124 @todo send email for expired account
            return;
        }

        //Get transaction count object
        $transactions = $nordigen->getTransactions($this->bank_integration->bank_account_id, $this->from_date);

        //Get int count
        $count = sizeof($transactions);

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

            if (BankTransaction::where('transaction_id', $transaction['transaction_id'])->where('company_id', $this->company->id)->withTrashed()->exists())
                continue;

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
}
