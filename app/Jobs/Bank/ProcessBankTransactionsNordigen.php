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
use Illuminate\Support\Facades\Cache;

class ProcessBankTransactionsNordigen implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    private const WAKE_COOLDOWN_SECONDS = 60 * 60 * 6;

    private const WAKE_PAUSE_SECONDS = 60 * 60 * 24;

    private const WAKE_LOCK_SECONDS = 30;

    private const WAKE_MAX_ATTEMPTS = 3;

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
    public function handle(): void
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
    private function updateAccount(): void
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

        if ($this->shouldSkipErrorAccountCheck()) {
            return;
        }

        $account_status = $this->nordigen->isAccountActive($this->bank_integration->nordigen_account_id);

        //Rate limited — leave the integration enabled and retry next cycle. Do not mutate state.
        if (($account_status['status'] ?? null) == 'RATE_LIMITED') {
            nlog("Nordigen: rate limited, awaiting retry for account: " . $this->bank_integration->nordigen_account_id);
            return;
        }

        if (($account_status['status'] ?? null) == 'ERROR') {
            $this->bank_integration->disabled_upstream = false;
            $this->bank_integration->bank_account_status = 'ERROR';
            $this->bank_integration->save();

            nlog("Nordigen: account entered ERROR: " . $this->bank_integration->nordigen_account_id);

            $this->attemptAccountWake();

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

        if ($this->bank_integration->nordigen_account_id) {
            $this->clearAccountWakeState($this->bank_integration->nordigen_account_id);
        }

    }

    private function shouldSkipErrorAccountCheck(): bool
    {
        $account_id = $this->bank_integration->nordigen_account_id;

        if (!$account_id || $this->bank_integration->bank_account_status != 'ERROR') {
            return false;
        }

        if (Cache::has($this->wakeCacheKey('paused', $account_id))) {
            nlog("Nordigen: account status check skipped; wake paused for account: {$account_id}");
            return true;
        }

        if (Cache::has($this->wakeCacheKey('last', $account_id))) {
            nlog("Nordigen: account status check skipped; wake cooldown active for account: {$account_id}");
            return true;
        }

        return false;
    }

    private function attemptAccountWake(): void
    {
        $account_id = $this->bank_integration->nordigen_account_id;

        if (!$account_id) {
            return;
        }

        if (Cache::has($this->wakeCacheKey('paused', $account_id))) {
            nlog("Nordigen: wake skipped; paused after max attempts for account: {$account_id}");
            return;
        }

        if (Cache::has($this->wakeCacheKey('last', $account_id))) {
            nlog("Nordigen: wake skipped; cooldown active for account: {$account_id}");
            return;
        }

        $lock = Cache::lock($this->wakeCacheKey('lock', $account_id), self::WAKE_LOCK_SECONDS);

        if (!$lock->get()) {
            nlog("Nordigen: wake skipped; lock held for account: {$account_id}");
            return;
        }

        try {
            Cache::put($this->wakeCacheKey('last', $account_id), true, self::WAKE_COOLDOWN_SECONDS);

            nlog("Nordigen: wake probe started for account: {$account_id}");

            $wake_status = $this->nordigen->wakeAccount($account_id, 'ERROR');
            $status = $wake_status['status'];

            if ($status == 'READY') {
                $this->clearAccountWakeState($account_id);

                $this->bank_integration->disabled_upstream = false;
                $this->bank_integration->bank_account_status = 'READY';
                $this->bank_integration->save();

                nlog("Nordigen: wake probe found account READY: {$account_id}");
                return;
            }

            if ($status == 'WAKE_PROBED') {
                $this->recordWakeAttempt($account_id, "Nordigen: wake probe completed for account: {$account_id}; awaiting READY metadata");
                return;
            }

            if ($status == 'WAKE_RATE_LIMITED') {
                $this->recordWakeAttempt($account_id, "Nordigen: wake probe rate limited for account: {$account_id}");
                return;
            }

            if (in_array($status, ['EXPIRED', 'SUSPENDED', 'Invalid Account ID'])) {
                $this->bank_integration->disabled_upstream = true;
                $this->bank_integration->bank_account_status = $status;
                $this->bank_integration->save();

                nlog("Nordigen: wake probe permanent failure ({$status}) for account: {$account_id}");

                if (in_array($status, ['EXPIRED', 'SUSPENDED'])) {
                    $this->nordigen->disabledAccountEmail($this->bank_integration);
                }

                return;
            }

            $this->recordWakeAttempt($account_id, "Nordigen: wake probe transient failure for account: {$account_id}");
        } finally {
            $lock->release();
        }
    }

    private function recordWakeAttempt(string $account_id, string $message): void
    {
        $attempts = ((int) Cache::get($this->wakeCacheKey('attempts', $account_id), 0)) + 1;
        Cache::put($this->wakeCacheKey('attempts', $account_id), $attempts, self::WAKE_PAUSE_SECONDS);

        nlog("{$message} (attempt {$attempts})");

        if ($attempts >= self::WAKE_MAX_ATTEMPTS) {
            Cache::put($this->wakeCacheKey('paused', $account_id), true, self::WAKE_PAUSE_SECONDS);
            nlog("Nordigen: wake probe paused after max attempts for account: {$account_id}");
        }
    }

    private function clearAccountWakeState(string $account_id): void
    {
        Cache::forget($this->wakeCacheKey('last', $account_id));
        Cache::forget($this->wakeCacheKey('attempts', $account_id));
        Cache::forget($this->wakeCacheKey('paused', $account_id));
    }

    private function wakeCacheKey(string $scope, string $account_id): string
    {
        return "nordigen:wake:{$scope}:{$account_id}";
    }

    private function processTransactions(): void
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
