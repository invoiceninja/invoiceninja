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
use App\Models\PaymentHash;
use App\PaymentDrivers\Stripe\CardPresent;
use Illuminate\Console\Command;

class TerminalReconcile extends Command
{
    protected $signature = 'ninja:terminal-reconcile
                            {hash : The payment_hash in the Stripe PaymentIntent\'s metadata}
                            {--db= : Limit to one database connection name (default: all shards)}';

    protected $description = 'Record an in-person (Stripe Terminal) card payment that was charged but not recorded';

    public function handle(): int
    {
        $dbs = $this->option('db') ? [$this->option('db')] : (config('ninja.db.multi_db_enabled') ? MultiDB::$dbs : [config('database.default')]);

        foreach ($dbs as $db) {
            if (config('ninja.db.multi_db_enabled')) {
                MultiDB::setDB($db);
            }

            $payment_hash = PaymentHash::query()->where('hash', $this->argument('hash'))->first();

            if (isset($payment_hash?->data->terminal)) {
                $result = CardPresent::forCollection($payment_hash)->record($payment_hash->fee_invoice);
                $this->info("{$payment_hash->data->terminal->payment_intent_id}: {$result['status']}");

                return self::SUCCESS;
            }
        }

        $this->error($this->argument('hash') . ': no in-person collection');

        return self::FAILURE;
    }
}
