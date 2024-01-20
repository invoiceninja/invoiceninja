<?php
/**
 * Credit Ninja (https://creditninja.com).
 *
 * @link https://github.com/creditninja/creditninja source repository
 *
 * @copyright Copyright (c) 2022. Credit Ninja LLC (https://creditninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Observers;

use App\Jobs\Util\WebhookHandler;
use App\Models\Client;
use App\Models\Credit;
use App\Models\Webhook;

class CreditObserver
{
    public $afterCommit = true;

    /**
     * Handle the client "created" event.
     *
     * @param Credit $credit
     * @return void
     */
    public function created(Credit $credit)
    {
        $subscriptions = Webhook::where('company_id', $credit->company_id)
                                    ->where('event_id', Webhook::EVENT_CREATE_CREDIT)
                                    ->exists();

        if ($subscriptions) {
            WebhookHandler::dispatch(Webhook::EVENT_CREATE_CREDIT, $credit, $credit->company)->delay(0);
        }
    }

    /**
     * Handle the client "updated" event.
     *
     * @param Credit $credit
     * @return void
     */
    public function updated(Credit $credit)
    {
        $event = Webhook::EVENT_UPDATE_CREDIT;

        if ($credit->getOriginal('deleted_at') && !$credit->deleted_at) {
            $event = Webhook::EVENT_RESTORE_CREDIT;
        }

        if ($credit->is_deleted) {
            $event = Webhook::EVENT_DELETE_CREDIT;
        }

        $subscriptions = Webhook::where('company_id', $credit->company_id)
                                    ->where('event_id', $event)
                                    ->exists();

        if ($subscriptions) {
            WebhookHandler::dispatch($event, $credit, $credit->company)->delay(0);
        }
    }

    /**
     * Handle the client "deleted" event.
     *
     * @param Credit $credit
     * @return void
     */
    public function deleted(Credit $credit)
    {
        if ($credit->is_deleted) {
            return;
        }

        $subscriptions = Webhook::where('company_id', $credit->company_id)
                                    ->where('event_id', Webhook::EVENT_ARCHIVE_CREDIT)
                                    ->exists();

        if ($subscriptions) {
            WebhookHandler::dispatch(Webhook::EVENT_ARCHIVE_CREDIT, $credit, $credit->company)->delay(0);
        }
    }

    /**
     * Handle the client "restored" event.
     *
     * @param Credit $credit
     * @return void
     */
    public function restored(Credit $credit)
    {
        //
    }

    /**
     * Handle the client "force deleted" event.
     *
     * @param Credit $credit
     * @return void
     */
    public function forceDeleted(Credit $credit)
    {
        //
    }
    /**
     * Handle the client "archive" event.
     *
     * @param Credit $credit
     * @return void
     */
}
