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

use App\Jobs\Cron\TerminalReconcileCron;
use App\Libraries\MultiDB;
use App\Models\TerminalPaymentIntent;
use Illuminate\Console\Command;

class TerminalReconcile extends Command
{
    protected $signature = 'ninja:terminal-reconcile
                            {--pi= : A Stripe PaymentIntent id (pi_...) to record}
                            {--db= : Limit to one database connection name (default: all shards)}';

    protected $description = 'Record in-person (Stripe Terminal) card payments that were charged but not recorded';

    public function handle(): int
    {
        if (! $this->option('pi')) {
            if ($this->option('db')) {
                MultiDB::setDB($this->option('db'));
            }

            (new TerminalReconcileCron())->handle();

            return self::SUCCESS;
        }

        $dbs = $this->option('db') ? [$this->option('db')] : (config('ninja.db.multi_db_enabled') ? MultiDB::$dbs : [config('database.default')]);

        foreach ($dbs as $db) {
            if (config('ninja.db.multi_db_enabled')) {
                MultiDB::setDB($db);
            }

            $collection = TerminalPaymentIntent::query()->where('payment_intent_id', $this->option('pi'))->first();

            if ($collection) {
                $result = $collection->cardPresent()->record($collection);
                $this->info($this->option('pi') . ": {$result['status']}");

                return self::SUCCESS;
            }
        }

        $this->error($this->option('pi') . ': not found');

        return self::FAILURE;
    }
}
