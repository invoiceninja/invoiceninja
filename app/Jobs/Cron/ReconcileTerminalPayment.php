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
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/* Records an in-person card payment Stripe charged but nobody recorded, or cancels an abandoned one */
class ReconcileTerminalPayment implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $tries = 1;

    public function __construct(public int $terminal_payment_intent_id, public ?string $db)
    {
    }

    public function handle(): void
    {
        if ($this->db) {
            MultiDB::setDb($this->db);
        }

        $collection = TerminalPaymentIntent::query()->find($this->terminal_payment_intent_id);

        if (!$collection || $collection->isFinal()) {
            return;
        }

        if (!$collection->payment_intent_id) {
            if ($collection->isStale()) {
                $collection->update(['status_id' => TerminalPaymentIntent::STATUS_FAILED]);
            }

            return;
        }

        $card_present = $collection->cardPresent();
        $result = $card_present->record($collection);

        if (in_array($result['status'], ['recorded', 'unapplied'])) {
            $card_present->log($collection, SystemLog::EVENT_PAYMENT_RECONCILIATION_SUCCESS, 'recorded by reconciliation');
        } elseif (!$collection->fresh()->isFinal() && $collection->isStale()) {
            $card_present->cancel($collection->fresh());
        }
    }
}
