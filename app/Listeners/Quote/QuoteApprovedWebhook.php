<?php
/**
 * Quote Ninja (https://quoteninja.com).
 *
 * @link https://github.com/quoteninja/quoteninja source repository
 *
 * @copyright Copyright (c) 2022. Quote Ninja LLC (https://quoteninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Listeners\Quote;

use App\Jobs\Util\WebhookHandler;
use App\Libraries\MultiDB;
use App\Models\Webhook;
use Illuminate\Contracts\Queue\ShouldQueue;

class QuoteApprovedWebhook implements ShouldQueue
{
    public $delay = 5;

    public function __construct()
    {
    }

    /**
     * Handle the event.
     *
     * @param  object  $event
     * @return void
     */
    public function handle($event)
    {
        MultiDB::setDb($event->company->db);

        $quote = $event->quote;

        $subscriptions = Webhook::where('company_id', $quote->company_id)
                        ->where('event_id', Webhook::EVENT_APPROVE_QUOTE)
                        ->exists();

        if ($subscriptions) {
            WebhookHandler::dispatch(Webhook::EVENT_APPROVE_QUOTE, $quote, $quote->company);
        }
    }
}
