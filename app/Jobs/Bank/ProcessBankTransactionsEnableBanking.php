<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2025. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Jobs\Bank;

use App\Helpers\Bank\EnableBanking\EnableBanking;
use App\Libraries\MultiDB;
use App\Models\BankIntegration;
use App\Models\BankTransaction;
use App\Models\Company;
use App\Notifications\Ninja\GenericNinjaAdminNotification;
use App\Services\Bank\BankMatchingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessBankTransactionsEnableBanking implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    private BankIntegration $bank_integration;

    private ?string $from_date;

    public Company $company;
    public EnableBanking $enablebanking;
    public bool $enablebanking_account = false;

    /**
     * Create a new job instance.
     */
    public function __construct(BankIntegration $bank_integration)
    {
        $this->bank_integration = $bank_integration;
        $this->from_date = $bank_integration->from_date ?: now()->subDays(90);
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
        if ($this->bank_integration->integration_type != BankIntegration::INTEGRATION_TYPE_ENABLEBANKING) {
            throw new \Exception("Invalid BankIntegration Type");
        }

        if (!(config('ninja.enablebanking.application_id') && config('ninja.enablebanking.key_path'))) {
            throw new \Exception("Missing credentials for bank_integration service enablebanking");
        }

        $this->enablebanking = new EnableBanking();

        set_time_limit(0);

        nlog("EnableBanking: Processing transactions for account: {$this->bank_integration->account->key}");

        // UPDATE ACCOUNT
        try {
            $this->updateAccount();
            $this->enablebanking_account = true;
        } catch (\Exception $e) {
            nlog("EnableBanking: {$this->bank_integration->enablebanking_account_id} - exited abnormally => " . $e->getMessage());

            $content = [
                "Processing transactions for account: {$this->bank_integration->enablebanking_account_id} failed",
                "Exception Details => ",
                $e->getMessage(),
            ];

            $this->bank_integration->company->notification(new GenericNinjaAdminNotification($content))->ninja();

            sleep(1);
            throw $e;
        }

        if (!$this->enablebanking_account) {
            return;
        }

        // UPDATE TRANSACTIONS
        try {
            $this->processTransactions();
        } catch (\Exception $e) {
            nlog("EnableBanking: {$this->bank_integration->enablebanking_account_id} - exited abnormally => " . $e->getMessage());

            $content = [
                "Processing transactions for account: {$this->bank_integration->enablebanking_account_id} failed",
                "Exception Details => ",
                $e->getMessage(),
            ];

            $this->bank_integration->company->notification(new GenericNinjaAdminNotification($content))->ninja();

            throw $e;
        }

        // Perform Matching
        BankMatchingService::dispatch($this->company->id, $this->company->db);
    }

    private function updateAccount()
    {
        $session_active = $this->bank_integration->enablebanking_session_expired_at > now() &&
            $this->enablebanking->isSessionActive($this->bank_integration->enablebanking_session_id);

        //Return early if the session is not active
        if (!$session_active) {
            $this->bank_integration->disabled_upstream = true;
            $this->bank_integration->bank_account_status = 'EXPIRED';
            $this->bank_integration->save();

            nlog("EnableBanking: session inactive: " . $this->bank_integration->enablebanking_session_id);

            // Session expired, send notification
            $this->enablebanking->disabledAccountEmail($this->bank_integration);

            return;
        }

        $this->enablebanking_account = true;
        $this->bank_integration->disabled_upstream = false;
        $this->bank_integration->bank_account_status = "READY";
        $this->bank_integration->save();

    }

    private function processTransactions()
    {
        //Get transactions
        $transactions = $this->enablebanking->getTransactions(
            $this->company,
            $this->bank_integration->enablebanking_account_id,
            $this->from_date
        );

        //if no transactions, update the from_date and move on
        if (count($transactions) == 0) {

            $this->bank_integration->from_date = now()->subDays(5);
            $this->bank_integration->disabled_upstream = false;
            $this->bank_integration->save();

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
            
            // Check if transaction already exists
            if (BankTransaction::where('enablebanking_transaction_id', $transaction['enablebanking_transaction_id'])
                ->where('company_id', $this->company->id)
                ->where('bank_integration_id', $this->bank_integration->id)
                ->where('is_deleted', 0)
                ->withTrashed()
                ->exists()) {
                continue;
            }

            //this should be much faster to insert than using ::create()
            \DB::table('bank_transactions')->insert(
                array_merge($transaction, [
                    'company_id' => $this->company->id,
                    'user_id' => $user_id,
                    'bank_integration_id' => $this->bank_integration->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
            );

        }

        $this->bank_integration->from_date = now()->subDays(5);
        $this->bank_integration->save();
    }
}
