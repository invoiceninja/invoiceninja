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

class ProcessBankTransactionsNordigen implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    private BankIntegration $bank_integration;

    private ?string $from_date;

    public Company $company;
    public Nordigen $nordigen;
    public bool $nordigen_account = false;

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
        if ($this->bank_integration->integration_type != BankIntegration::INTEGRATION_TYPE_NORDIGEN) {
            throw new \Exception("Invalid BankIntegration Type");
        }

        if (!(config('ninja.nordigen.secret_id') && config('ninja.nordigen.secret_key'))) {
            throw new \Exception("Missing credentials for bank_integration service nordigen");
        }

        if (!isset($this->nordigen)) {
            $this->nordigen = new Nordigen();
        }

        set_time_limit(0);

        nlog("Nordigen: Processing transactions for account: {$this->bank_integration->account->key}");

        // UPDATE ACCOUNT
        try {
            $this->updateAccount();
        } catch (\Exception $e) {
            nlog("Nordigen: {$this->bank_integration->nordigen_account_id} - exited abnormally => " . $e->getMessage());

            $content = [
                "Processing transactions for account: {$this->bank_integration->nordigen_account_id} failed",
                "Exception Details => ",
                $e->getMessage(),
            ];

            $this->bank_integration->company->notification(new GenericNinjaAdminNotification($content))->ninja();

            sleep(1);
            throw $e;
        }

        if (!$this->nordigen_account) {
            return;
        }

        // UPDATE TRANSACTIONS
        try {
            $this->processTransactions();

            // Perform Matching
            BankMatchingService::dispatch($this->company->id, $this->company->db);

        } catch (\Exception $e) {
            nlog("Nordigen: {$this->bank_integration->nordigen_account_id} - exited abnormally => " . $e->getMessage());

            $content = [
                "Processing transactions for account: {$this->bank_integration->nordigen_account_id} failed",
                "Exception Details => ",
                $e->getMessage(),
            ];

            $this->bank_integration->company->notification(new GenericNinjaAdminNotification($content))->ninja();

            // throw $e;
        }

    }

    // const DISCOVERED = 'DISCOVERED';   // Account was discovered but not yet processed
    // const PROCESSING = 'PROCESSING';   // Initial processing of account
    // const READY = 'READY';            // Account ready to be accessed
    // const ERROR = 'ERROR';            // Error occurred during processing
    // const EXPIRED = 'EXPIRED';        // Access to account has expired
    // const SUSPENDED = 'SUSPENDED';     // Account access temporarily suspended
    // const FAILED = 'FAILED';          // Connection failed
    // const DELETED = 'DELETED';        // Account has been deleted
    private function updateAccount()
    {
        // Requisition pre-flight gate (cheap, separate rate limit). The requisition is the
        // authority on permanent failure (EX/SU/RJ). Running it before any rate-limited
        // account-data call avoids wasting the ~4/day quota on a dead connection.
        // Legacy rows (requisition_id == null) skip the gate and fall through to the status check.
        if ($this->bank_integration->requisition_id) {
            $requisition_status = $this->nordigen->requisitionStatus($this->bank_integration->requisition_id);

            // Only act on a DEFINITIVE terminal status. requisitionStatus() returns null when the
            // requisition endpoint could not be read (404/429/5xx/timeout all collapse to null), so we
            // must NOT disable on null — that would false-disable healthy accounts on transient upstream
            // failures. Anything non-terminal (null, LN, mid-flow) falls through to the account check.
            if (in_array($requisition_status, ['EX', 'SU', 'RJ'], true)) {
                $this->bank_integration->disabled_upstream = true;
                $this->bank_integration->bank_account_status = $requisition_status;
                $this->bank_integration->save();

                nlog("Nordigen: requisition '{$this->bank_integration->requisition_id}' invalid (status={$requisition_status}) for account: " . $this->bank_integration->nordigen_account_id);

                $this->nordigen->disabledAccountEmail($this->bank_integration);

                return;
            }
        }

        $account_status = $this->nordigen->isAccountActive($this->bank_integration->nordigen_account_id);

        //Rate limited — leave the integration enabled and retry next cycle. Do not mutate state.
        if (($account_status['status'] ?? null) == 'RATE_LIMITED') {
            nlog("Nordigen: rate limited, awaiting retry for account: " . $this->bank_integration->nordigen_account_id);
            return;
        }

        //Permanent failure — disable and notify (EXPIRED/SUSPENDED require a reconnect).
        if (isset($account_status['status']) && in_array($account_status['status'], ['EXPIRED', 'SUSPENDED', 'Invalid Account ID'])) {

            $this->bank_integration->disabled_upstream = true;
            $this->bank_integration->bank_account_status = $account_status['status'];
            $this->bank_integration->save();

            nlog("Nordigen: account inactive: " . $this->bank_integration->nordigen_account_id);

            if (in_array($account_status['status'], ['EXPIRED', 'SUSPENDED'])) {
                $this->nordigen->disabledAccountEmail($this->bank_integration);
            }

            return;

        } elseif (($account_status['status'] ?? null) != 'READY') {
            //Transient state (ERROR / PROCESSING / DISCOVERED / TRANSIENT_ERROR): leave enabled and
            //await retry. The requisition gate above disables it if the failure is actually permanent.
            nlog(($account_status['id'] ?? $this->bank_integration->nordigen_account_id) . " Nordigen account status == " . ($account_status['status'] ?? 'unknown'));
            return;

        }

        $this->nordigen_account = true;
        $this->bank_integration->disabled_upstream = false;
        $this->bank_integration->bank_account_status = "READY";
        // $this->bank_integration->bank_account_status = $account['account_status'];
        // $this->bank_integration->balance = $account['current_balance'];
        $this->bank_integration->save();

    }

    private function processTransactions()
    {
        //Get transaction count object
        $transactions = [];

        $transactions = $this->nordigen->getTransactions($this->company, $this->bank_integration->nordigen_account_id, $this->from_date);

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

            if (BankTransaction::where('nordigen_transaction_id', $transaction['nordigen_transaction_id'])
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

        BankTransaction::reguard();
    }
}
