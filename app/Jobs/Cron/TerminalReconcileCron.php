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

namespace App\Jobs\Cron;

use App\Libraries\MultiDB;
use App\Models\TerminalPaymentIntent;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Auth;

/* Records in-person card payments Stripe charged but nobody recorded, and cancels abandoned ones */
class TerminalReconcileCron
{
    use Dispatchable;

    public function __construct()
    {
    }

    public function handle(): void
    {
        Auth::logout();

        if (! config('ninja.db.multi_db_enabled')) {
            $this->run(null);

            return;
        }

        foreach (MultiDB::$dbs as $db) {
            MultiDB::setDB($db);

            try {
                $this->run($db);
            } catch (\Throwable $e) {
                nlog("Error reconciling terminal payments: " . $e->getMessage());
                app('sentry')->captureException($e);
            }
        }
    }

    private function run(?string $db): void
    {
        TerminalPaymentIntent::query()
            ->whereIn('status_id', TerminalPaymentIntent::OPEN_STATUSES)
            ->where('created_at', '>', now()->subDays(30))
            ->select('id')
            ->lazyById()
            ->each(fn ($collection) => ReconcileTerminalPayment::dispatch($collection->id, $db));
    }
}
