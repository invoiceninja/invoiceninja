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
use App\Models\ClientGatewayToken;
use App\Models\CompanyGateway;
use App\Models\Gateway;
use App\Models\GatewayType;
use App\PaymentDrivers\StripePaymentDriver;
use Illuminate\Console\Command;
use Stripe\Exception\RateLimitException;

class StripeAchBillingAddressBackfill extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ninja:stripe-ach-billing-address
                            {--dry-run : Report what would change without calling Stripe}
                            {--company_gateway_id= : Restrict the run to a single company gateway}
                            {--limit= : Stop after this many tokens have been updated}
                            {--force : Re-sync tokens that have already been stamped}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill the client billing address onto existing Stripe ACH payment methods.';

    /**
     * @var array<string, int>
     */
    private array $counters = [];

    public function handle(): int
    {
        $current_db = config('database.default');

        if (! config('ninja.db.multi_db_enabled')) {
            $this->handleOnDb();
        } else {
            foreach (MultiDB::$dbs as $db) {
                MultiDB::setDB($db);

                $this->info("Database: {$db}");

                $this->handleOnDb();
            }

            MultiDB::setDB($current_db);
        }

        return 0;
    }

    private function handleOnDb(): void
    {
        $this->counters = [
            'scanned' => 0,
            'updated' => 0,
            'already_synced' => 0,
            'missing_address' => 0,
            'failed' => 0,
        ];

        $stripe_keys = [
            'd14dd26a37cecc30fdd65700bfb55b23',
            'd14dd26a47cecc30fdd65700bfb67b34',
        ];

        $company_gateways = CompanyGateway::query()
            ->withTrashed()
            ->whereIn('gateway_key', $stripe_keys)
            ->when($this->option('company_gateway_id'), function ($query, $company_gateway_id) {
                return $query->where('id', $company_gateway_id);
            })
            ->cursor();

        foreach ($company_gateways as $company_gateway) {
            if ($this->limitReached()) {
                break;
            }

            $this->handleCompanyGateway($company_gateway);
        }

        $this->report();
    }

    private function handleCompanyGateway(CompanyGateway $company_gateway): void
    {
        /**
         * Resolved once per gateway, both to avoid re-initialising the Stripe SDK
         * per token and so the run authenticates exactly as the payment paths do.
         *
         * @var StripePaymentDriver|false $driver
         */
        $driver = $company_gateway->driver();

        if (! $driver instanceof StripePaymentDriver) {
            return;
        }

        $driver->init();

        $tokens = ClientGatewayToken::query()
            ->withTrashed()
            ->where('company_gateway_id', $company_gateway->id)
            ->where('gateway_type_id', GatewayType::BANK_TRANSFER)
            ->where('is_deleted', 0)
            ->with('client.country')
            ->cursor();

        foreach ($tokens as $token) {
            if ($this->limitReached()) {
                return;
            }

            $this->handleToken($driver, $token);
        }
    }

    private function handleToken(StripePaymentDriver $driver, ClientGatewayToken $token): void
    {
        $this->counters['scanned']++;

        if (($token->meta->billing_address_synced ?? false) && ! $this->option('force')) {
            $this->counters['already_synced']++;

            return;
        }

        if (! $token->client || ! $driver->hasCompleteBillingAddress($token->client)) {
            $this->counters['missing_address']++;

            $this->line("  token {$token->id} skipped, client address is incomplete");

            return;
        }

        if ($this->option('dry-run')) {
            $this->counters['updated']++;

            return;
        }

        if ($this->updateAtStripe($driver, $token)) {
            $meta = $token->meta;
            $meta->billing_address_synced = true;
            $token->meta = $meta;
            $token->save();

            $this->counters['updated']++;
        }
    }

    private function updateAtStripe(StripePaymentDriver $driver, ClientGatewayToken $token, bool $retrying = false): bool
    {
        try {
            \Stripe\PaymentMethod::update(
                $token->token,
                ['billing_details' => ['address' => $driver->stripeBillingAddress($token->client)]],
                $driver->stripe_connect_auth
            );

            return true;
        } catch (RateLimitException $e) {
            if ($retrying) {
                $this->counters['failed']++;

                return false;
            }

            sleep(1);

            return $this->updateAtStripe($driver, $token, true);
        } catch (\Throwable $e) {
            $this->counters['failed']++;

            $prefix = substr((string) $token->token, 0, 3);

            $this->error("  token {$token->id} ({$prefix}) failed: {$e->getMessage()}");

            nlog("StripeAchBillingAddressBackfill: token {$token->id} failed - {$e->getMessage()}");

            return false;
        }
    }

    private function limitReached(): bool
    {
        $limit = $this->option('limit');

        return $limit && $this->counters['updated'] >= (int) $limit;
    }

    private function report(): void
    {
        $this->table(array_keys($this->counters), [array_values($this->counters)]);
    }
}
