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
use App\Models\SystemLog;
use App\Models\TerminalPaymentIntent;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Auth;

/* Records in person card payments Stripe charged but nobody recorded, and cancels abandoned collections */
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
            $this->run();

            return;
        }

        foreach (MultiDB::$dbs as $db) {
            MultiDB::setDB($db);

            try {
                $this->run();
            } catch (\Throwable $e) {
                nlog("Error reconciling terminal payments: " . $e->getMessage());
                app('sentry')->captureException($e);
            }
        }
    }

    private function run(): void
    {
        TerminalPaymentIntent::query()
            ->whereIn('status', TerminalPaymentIntent::OPEN_STATUSES)
            ->where('created_at', '>', now()->subHours(48))
            ->lazyById()
            ->each(function (TerminalPaymentIntent $collection) {
                if (! $collection->payment_intent_id) {
                    if ($collection->isStale()) {
                        $collection->update(['status' => TerminalPaymentIntent::STATUS_FAILED]);
                    }

                    return;
                }

                try {
                    $card_present = $collection->company_gateway->driver($collection->invoice?->client)->cardPresent();
                    $result = $card_present->record($collection);

                    if (in_array($result['status'], ['recorded', 'unapplied'])) {
                        $card_present->log($collection, SystemLog::EVENT_PAYMENT_RECONCILIATION_SUCCESS, 'recorded by reconciliation');
                    } elseif ($collection->fresh()->isStale() && ! $collection->fresh()->isFinal()) {
                        $card_present->cancel($collection->fresh());
                    }
                } catch (\Throwable $e) {
                    nlog("Error reconciling terminal payment {$collection->payment_intent_id}: " . $e->getMessage());
                }
            });
    }
}
