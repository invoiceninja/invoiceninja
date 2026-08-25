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

namespace App\Console\Commands;

use App\Libraries\MultiDB;
use App\Models\Account;
use App\Models\Client;
use App\Models\Payment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Console\Command;

class ReferralPayoutReport extends Command
{
    /** @var array<int, int> */
    private const DEFAULT_REFERRER_IDS = [
        7726,
        48626,
        62266,
        63926,
        105431,
        112881,
        169380,
        191693,
    ];

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ninja:referral-payouts
                            {start_date : Inclusive payment start date (Y-m-d)}
                            {end_date : Inclusive payment end date (Y-m-d)}
                            {--user_id=* : Referrer user ID; repeat to override the default list}
                            {--commission-rate= : Optional payout percentage applied to net completed payments}
                            {--referral-database=db-ninja-01 : Database containing the referrer users}
                            {--billing-database=db-ninja-01 : Database containing Invoice Ninja billing clients and payments}
                            {--account-database=* : Account database to scan; repeat to override the configured shards}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Report completed payments associated with referred Invoice Ninja accounts.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $start_date = $this->dateArgument('start_date');
        $end_date = $this->dateArgument('end_date');

        if (! $start_date || ! $end_date) {
            return self::FAILURE;
        }

        if ($start_date->isAfter($end_date)) {
            $this->error('The start_date must be on or before the end_date.');

            return self::FAILURE;
        }

        $commission_rate = $this->commissionRate();

        if ($commission_rate === false) {
            return self::FAILURE;
        }

        $referral_database = trim((string) $this->option('referral-database'));
        $billing_database = trim((string) $this->option('billing-database'));
        $account_databases = $this->accountDatabases();
        $referrer_ids = $this->referrerIds();

        if ($referral_database === '' || $billing_database === '' || $account_databases === []) {
            $this->error('Referral, billing, and account databases must be specified.');

            return self::FAILURE;
        }

        $rows = [];
        $totals = [];
        $missing_billing_clients = [];
        $referrer_count = 0;
        $referred_account_count = 0;
        $billing_client_count = 0;

        $referrers = User::on($referral_database)
            ->without('passkey_credentials')
            ->select(['id', 'first_name', 'last_name', 'referral_code', 'referral_meta'])
            ->whereNotNull('referral_meta')
            ->whereNotNull('referral_code')
            ->where('referral_code', '<>', '')
            ->whereIn('id', $referrer_ids)
            ->orderBy('id')
            ->cursor();

        foreach ($referrers as $referrer) {
            if (($referrer->referral_meta?->pro ?? 0) < 1
                && ($referrer->referral_meta?->enterprise ?? 0) < 1) {
                continue;
            }

            $referrer_count++;
            $referrer_name = trim("{$referrer->first_name} {$referrer->last_name}");
            $processed_account_keys = [];

            foreach ($account_databases as $account_database) {
                $accounts = Account::on($account_database)
                    ->select(['id', 'key', 'plan', 'plan_expires', 'referral_code'])
                    ->where('referral_code', $referrer->referral_code)
                    ->whereNotNull('key')
                    ->where('key', '<>', '')
                    ->orderBy('id')
                    ->cursor();

                foreach ($accounts as $account) {
                    if (isset($processed_account_keys[$account->key])) {
                        continue;
                    }

                    $processed_account_keys[$account->key] = true;
                    $referred_account_count++;
                    $matched_billing_client = false;

                    $billing_clients = Client::on($billing_database)
                        ->without(['gateway_tokens', 'documents', 'contacts.company'])
                        ->withTrashed()
                        ->select(['id', 'company_id', 'name', 'custom_value2'])
                        ->where('custom_value2', $account->key)
                        ->where('is_deleted', false)
                        ->orderBy('id')
                        ->cursor();

                    foreach ($billing_clients as $billing_client) {
                        $matched_billing_client = true;
                        $billing_client_count++;

                        $payments = Payment::on($billing_database)
                            ->without('paymentables')
                            ->withTrashed()
                            ->select([
                                'id',
                                'company_id',
                                'client_id',
                                'number',
                                'transaction_reference',
                                'date',
                                'amount',
                                'refunded',
                                'currency_id',
                            ])
                            ->where('company_id', $billing_client->company_id)
                            ->where('client_id', $billing_client->id)
                            ->where('status_id', Payment::STATUS_COMPLETED)
                            ->where('is_deleted', false)
                            ->whereBetween('date', [
                                $start_date->format('Y-m-d'),
                                $end_date->format('Y-m-d'),
                            ])
                            ->orderBy('date')
                            ->orderBy('id')
                            ->cursor();

                        foreach ($payments as $payment) {
                            $gross_amount = (float) $payment->amount;
                            $refunded_amount = (float) $payment->refunded;
                            $net_amount = $gross_amount - $refunded_amount;
                            $payout_amount = is_float($commission_rate)
                                ? $net_amount * ($commission_rate / 100)
                                : null;
                            $currency_id = (int) $payment->currency_id;
                            $total_key = "{$referrer->id}:{$currency_id}";

                            $rows[] = $this->paymentRow(
                                $referrer,
                                $referrer_name,
                                $account_database,
                                $account,
                                $billing_client,
                                $payment,
                                $gross_amount,
                                $refunded_amount,
                                $net_amount,
                                $payout_amount,
                            );

                            $totals[$total_key] ??= [
                                'referrer_id' => (int) $referrer->id,
                                'referrer' => $referrer_name,
                                'currency_id' => $currency_id,
                                'payments' => 0,
                                'gross_amount' => 0.0,
                                'refunded_amount' => 0.0,
                                'net_amount' => 0.0,
                                'payout_amount' => 0.0,
                            ];
                            $totals[$total_key]['payments']++;
                            $totals[$total_key]['gross_amount'] += $gross_amount;
                            $totals[$total_key]['refunded_amount'] += $refunded_amount;
                            $totals[$total_key]['net_amount'] += $net_amount;
                            $totals[$total_key]['payout_amount'] += $payout_amount ?? 0.0;
                        }
                    }

                    if (! $matched_billing_client) {
                        $missing_billing_clients[] = [
                            $referrer->id,
                            $account_database,
                            $account->id,
                            $account->key,
                            $account->plan,
                        ];
                    }
                }
            }
        }

        $this->renderReport(
            $start_date,
            $end_date,
            $commission_rate,
            $rows,
            array_values($totals),
            $missing_billing_clients,
            $referrer_count,
            $referred_account_count,
            $billing_client_count,
        );

        return self::SUCCESS;
    }

    private function dateArgument(string $name): ?CarbonImmutable
    {
        $value = trim((string) $this->argument($name));

        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $value);
        } catch (InvalidFormatException) {
            $date = null;
        }

        if (! $date || $date->format('Y-m-d') !== $value) {
            $this->error("The {$name} must be a valid date in Y-m-d format.");

            return null;
        }

        return $date;
    }

    private function commissionRate(): float|false|null
    {
        $value = $this->option('commission-rate');

        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        if (! is_numeric($value) || (float) $value < 0 || (float) $value > 100) {
            $this->error('The commission-rate must be a number between 0 and 100.');

            return false;
        }

        return (float) $value;
    }

    /** @return array<int, int> */
    private function referrerIds(): array
    {
        $user_ids = array_values(array_filter(
            array_map(static fn (mixed $id): int => (int) $id, (array) $this->option('user_id')),
            static fn (int $id): bool => $id > 0,
        ));

        return $user_ids !== [] ? array_values(array_unique($user_ids)) : self::DEFAULT_REFERRER_IDS;
    }

    /** @return array<int, string> */
    private function accountDatabases(): array
    {
        $databases = array_values(array_filter(
            array_map('trim', (array) $this->option('account-database')),
            static fn (string $database): bool => $database !== '',
        ));

        return $databases !== [] ? array_values(array_unique($databases)) : MultiDB::getDbs();
    }

    /**
     * @return array<int, int|string|null>
     */
    private function paymentRow(
        User $referrer,
        string $referrer_name,
        string $account_database,
        Account $account,
        Client $billing_client,
        Payment $payment,
        float $gross_amount,
        float $refunded_amount,
        float $net_amount,
        ?float $payout_amount,
    ): array {
        $row = [
            $referrer->id,
            $referrer_name,
            $account_database,
            $account->key,
            $account->plan,
            $account->plan_expires,
            $billing_client->id,
            $billing_client->name,
            $payment->id,
            $payment->date,
            $payment->number ?: $payment->transaction_reference,
            $payment->currency_id,
            $this->formatAmount($gross_amount),
            $this->formatAmount($refunded_amount),
            $this->formatAmount($net_amount),
        ];

        if ($payout_amount !== null) {
            $row[] = $this->formatAmount($payout_amount);
        }

        return $row;
    }

    /**
     * @param float|null $commission_rate
     * @param array<int, array<int, int|string|null>> $rows
     * @param array<int, array<string, float|int|string>> $totals
     * @param array<int, array<int, int|string|null>> $missing_billing_clients
     */
    private function renderReport(
        CarbonImmutable $start_date,
        CarbonImmutable $end_date,
        ?float $commission_rate,
        array $rows,
        array $totals,
        array $missing_billing_clients,
        int $referrer_count,
        int $referred_account_count,
        int $billing_client_count,
    ): void {
        $this->info(sprintf(
            'Completed referral payments from %s through %s (inclusive)',
            $start_date->format('Y-m-d'),
            $end_date->format('Y-m-d'),
        ));

        $headers = [
            'Referrer ID',
            'Referrer',
            'Account DB',
            'Account Key',
            'Plan',
            'Plan Expires',
            'Client ID',
            'Billing Client',
            'Payment ID',
            'Date',
            'Payment # / Reference',
            'Currency ID',
            'Gross',
            'Refunded',
            'Net',
        ];

        if ($commission_rate !== null) {
            $headers[] = sprintf('Payout (%.4g%%)', $commission_rate);
        }

        if ($rows !== []) {
            $this->table($headers, $rows);
        } else {
            $this->warn('No completed payments matched the supplied date range.');
        }

        if ($totals !== []) {
            $total_headers = ['Referrer ID', 'Referrer', 'Currency ID', 'Payments', 'Gross', 'Refunded', 'Net'];
            $total_rows = array_map(function (array $total) use ($commission_rate): array {
                $row = [
                    $total['referrer_id'],
                    $total['referrer'],
                    $total['currency_id'],
                    $total['payments'],
                    $this->formatAmount((float) $total['gross_amount']),
                    $this->formatAmount((float) $total['refunded_amount']),
                    $this->formatAmount((float) $total['net_amount']),
                ];

                if ($commission_rate !== null) {
                    $row[] = $this->formatAmount((float) $total['payout_amount']);
                }

                return $row;
            }, $totals);

            if ($commission_rate !== null) {
                $total_headers[] = sprintf('Payout (%.4g%%)', $commission_rate);
            }

            $this->newLine();
            $this->info('Totals by referrer and payment currency');
            $this->table($total_headers, $total_rows);
        }

        if ($missing_billing_clients !== []) {
            $this->newLine();
            $this->warn('Referred accounts without a matching billing client');
            $this->table(
                ['Referrer ID', 'Account DB', 'Account ID', 'Account Key', 'Plan'],
                $missing_billing_clients,
            );
        }

        $this->newLine();
        $this->line("Referrers: {$referrer_count}");
        $this->line("Referred accounts: {$referred_account_count}");
        $this->line("Matched billing clients: {$billing_client_count}");
        $this->line('Successful payments: ' . count($rows));
        $this->line('Accounts missing billing clients: ' . count($missing_billing_clients));

        if ($commission_rate === null) {
            $this->newLine();
            $this->comment('No commission rate supplied; net completed payment totals are shown without a payout calculation.');
        }
    }

    private function formatAmount(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }
}
