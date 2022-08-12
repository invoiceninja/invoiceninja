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

    private ?string $from_date;

    private string $default_date = '2022-01-01';

    /**
     * Create a new job instance.
     */
    public function __construct(string $bank_integration_account_id, BankIntegration $bank_integration, ?string $from_date)
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
        $yodlee->setTestMode();

        $data = [
            'baseType' => 'DEBIT', //CREDIT
            'top' => 500,
            'fromDate' => $this->from_date ?: $this->default_date, /// YYYY-MM-DD
            'accountId' => $this->bank_integration->bank_account_id,
        ];

        //expense transactions
        $transactions = $yodlee->getTransactions($data); 

        $company = $this->bank_integration->company;
        $user_id = $company->owner()->id;
        
        foreach($transactions as $transaction)
        {

            if(BankTransaction::where('transaction_id', $transaction['transaction_id'])->where('company_id', $company->id)->withTrashed()->exists())
                continue;

            $bt = BankTransaction::create(
                $transaction
            );

            $bt->company_id = $company->id;
            $bt->user_id = $user_id;
            $bt->base_type = 'DEBIT';
            $bt->save();
        }

        $data = [
            'baseType' => 'CREDIT', //CREDIT
            'top' => 500,
            'fromDate' => $this->from_date ?: $this->default_date, /// YYYY-MM-DD
            'accountId' => $this->bank_integration->bank_account_id,
        ];

        //income transactions
        $transactions = $yodlee->getTransactions($data); 

        foreach($transactions as $transaction)
        {

            if(BankTransaction::where('transaction_id', $transaction['transaction_id'])->where('company_id', $company->id)->withTrashed()->exists())
                continue;

            $bt = BankTransaction::create(
                $transaction
            );

            $bt->company_id = $company->id;
            $bt->user_id = $user_id;
            $bt->base_type = 'CREDIT';
            $bt->save();
        }

        BankService::dispatch($company->id, $company->db);

    }

}