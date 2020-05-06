<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Observers;

use App\Jobs\Util\SubscriptionHandler;
use App\Models\Quote;
use App\Models\Subscription;

class QuoteObserver
{
    /**
     * Handle the quote "created" event.
     *
     * @param  \App\Models\Quote  $quote
     * @return void
     */
    public function created(Quote $quote)
    {
        SubscriptionHandler::dispatch(Subscription::EVENT_CREATE_QUOTE, $quote);
    }

    /**
     * Handle the quote "updated" event.
     *
     * @param  \App\Models\Quote  $quote
     * @return void
     */
    public function updated(Quote $quote)
    {
        SubscriptionHandler::dispatch(Subscription::EVENT_UPDATE_QUOTE, $quote);
    }

    /**
     * Handle the quote "deleted" event.
     *
     * @param  \App\Models\Quote  $quote
     * @return void
     */
    public function deleted(Quote $quote)
    {
        SubscriptionHandler::dispatch(Subscription::EVENT_DELETE_QUOTE, $quote);
    }

    /**
     * Handle the quote "restored" event.
     *
     * @param  \App\Models\Quote  $quote
     * @return void
     */
    public function restored(Quote $quote)
    {
        //
    }

    /**
     * Handle the quote "force deleted" event.
     *
     * @param  \App\Models\Quote  $quote
     * @return void
     */
    public function forceDeleted(Quote $quote)
    {
        //
    }
}
