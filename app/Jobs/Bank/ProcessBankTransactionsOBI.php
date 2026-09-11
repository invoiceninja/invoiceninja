<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Jobs\Bank;

use App\Helpers\Bank\OBI\OBI;
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

class ProcessBankTransactionsOBI implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    private BankIntegration $bank_integration;

    private ?string $from_date;

    public Company $company;

    public ?OBI $obi = null;

    private bool $obi_account = false;

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
     * @return void
     */
    public function handle()
    {
        if ($this->bank_integration->integration_type != BankIntegration::INTEGRATION_TYPE_OBI) {
            throw new \Exception("Invalid BankIntegration Type");
        }

        // Resolve the API key from the integration-level config JSON, falling
        // back to the app-wide ninja.obi.api_key config value.
        $api_key = $this->resolveApiKey();

        if ($api_key === '') {
            throw new \Exception("Missing credentials for bank_integration service open-banking.io");
        }

        if (!isset($this->obi)) {
            $this->obi = new OBI($api_key);
        }

        set_time_limit(0);

        nlog("OBI: Processing transactions for account: {$this->bank_integration->account->key}");

        // UPDATE ACCOUNT
        try {
            $this->updateAccount();
        } catch (\Exception $e) {
            nlog("OBI: {$this->bank_integration->nordigen_account_id} - exited abnormally => " . $e->getMessage());

            $content = [
                "Processing transactions for account: {$this->bank_integration->nordigen_account_id} failed",
                "Exception Details => ",
                $e->getMessage(),
            ];

            $this->bank_integration->company->notification(new GenericNinjaAdminNotification($content))->ninja();

            sleep(1);
            throw $e;
        }

        if (!$this->obi_account) {
            return;
        }

        // UPDATE TRANSACTIONS
        try {
            $this->processTransactions();

            // Perform Matching
            BankMatchingService::dispatch($this->company->id, $this->company->db);
        } catch (\Exception $e) {
            nlog("OBI: {$this->bank_integration->nordigen_account_id} - exited abnormally => " . $e->getMessage());

            $content = [
                "Processing transactions for account: {$this->bank_integration->nordigen_account_id} failed",
                "Exception Details => ",
                $e->getMessage(),
            ];

            $this->bank_integration->company->notification(new GenericNinjaAdminNotification($content))->ninja();
        }
    }

    /**
     * Resolve the per-integration API key from the config JSON column, falling
     * back to the application-wide config. Returns an empty string when neither
     * is set.
     */
    private function resolveApiKey(): string
    {
        $config = $this->bank_integration->config;

        if (is_string($config)) {
            $config = json_decode($config, true);
        }

        if (is_array($config) && !empty($config['api_key'])) {
            return (string) $config['api_key'];
        }

        return (string) (config('ninja.obi.api_key') ?? '');
    }

    private function updateAccount()
    {
        $account_id = $this->bank_integration->nordigen_account_id;

        $account_status = $this->obi->getAccountStatus($account_id);

        // Permanent failure — disable and notify. A reconnect is required.
        if (isset($account_status['status']) && in_array($account_status['status'], ['EXPIRED', 'SUSPENDED'], true)) {
            $this->bank_integration->disabled_upstream = true;
            $this->bank_integration->bank_account_status = $account_status['status'];
            $this->bank_integration->save();

            nlog("OBI: account inactive: {$account_id} (status={$account_status['status']})");

            return;
        }

        // Transient state (PROCESSING / unknown): leave enabled, await retry.
        if (($account_status['status'] ?? null) !== 'READY') {
            nlog("OBI: account {$account_id} not ready (status=" . ($account_status['status'] ?? 'unknown') . ")");
            return;
        }

        $this->obi_account = true;
        $this->bank_integration->disabled_upstream = false;
        $this->bank_integration->bank_account_status = 'READY';

        if (isset($account_status['balance'])) {
            $this->bank_integration->balance = (float) $account_status['balance'];
        }

        if (!empty($account_status['currency'])) {
            $this->bank_integration->currency = (string) $account_status['currency'];
        }

        $this->bank_integration->save();
    }

    private function processTransactions()
    {
        $account_id = $this->bank_integration->nordigen_account_id;

        $transactions = $this->obi->getTransactions($account_id, $this->from_date);

        // No new transactions — advance the window and exit.
        if (count($transactions) === 0) {
            $this->bank_integration->from_date = now()->subDays(5);
            $this->bank_integration->disabled_upstream = false;
            $this->bank_integration->save();

            return;
        }

        // Harvest the company
        MultiDB::setDb($this->company->db);

        /* Get the user */
        $user_id = $this->company->owner()->id;

        /* Unguard the model to perform batch inserts */
        BankTransaction::unguard();

        $now = now();

        foreach ($transactions as $transaction) {
            $provider_transaction_id = (string) ($transaction['transaction_id'] ?? '');

            // Unified dedup: provider_transaction_id scopes to this integration.
            if ($provider_transaction_id !== '' && BankTransaction::where('provider_transaction_id', $provider_transaction_id)
                            ->where('company_id', $this->company->id)
                            ->where('bank_integration_id', $this->bank_integration->id)
                            ->withTrashed()
                            ->exists()) {
                continue;
            }

            $row = $this->toRow($transaction);

            \DB::table('bank_transactions')->insert(
                array_merge($row, [
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

    /**
     * Map a normalized OBI transaction (see BankSyncProviderInterface) onto the
     * bank_transactions columns. Mirrors the Nordigen TransactionTransformer
     * output shape so the downstream matching service is unchanged.
     *
     * @param  array<string, mixed>  $transaction
     * @return array<string, mixed>
     */
    private function toRow(array $transaction): array
    {
        $amount = (float) ($transaction['amount'] ?? 0);

        return [
            'transaction_id' => 0,
            'provider_transaction_id' => (string) ($transaction['transaction_id'] ?? ''),
            'amount' => abs($amount),
            'currency_id' => $this->convertCurrency((string) ($transaction['currency'] ?? '')),
            'category_id' => null,
            'category_type' => '',
            'date' => (string) ($transaction['date'] ?? ''),
            'description' => (string) ($transaction['description'] ?? ''),
            'participant' => $transaction['counterparty'] ?? null,
            'participant_name' => $transaction['counterparty_name'] ?? null,
            'base_type' => (string) ($transaction['base_type'] ?? ($amount < 0 ? 'DEBIT' : 'CREDIT')),
        ];
    }

    /**
     * Convert a 3-letter ISO-4217 code to the internal currency id.
     * Falls back to 1 (USD) when the code is unknown, matching the
     * behaviour of the Nordigen transformer.
     */
    private function convertCurrency(string $code): int
    {
        if ($code === '') {
            return 1;
        }

        $currencies = app('currencies');

        $currency = $currencies->first(function ($item) use ($code) {
            /** @var \App\Models\Currency $item */
            return $item->code == $code;
        });

        /** @var \App\Models\Currency $currency */
        return $currency ? $currency->id : 1; //@phpstan-ignore-line
    }
}
