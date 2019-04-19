<?php

namespace App\Observers;

use App\Models\Quote;

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
        //
    }

    /**
     * Handle the quote "updated" event.
     *
     * @param  \App\Models\Quote  $quote
     * @return void
     */
    public function updated(Quote $quote)
    {
        //
    }

    /**
     * Handle the quote "deleted" event.
     *
     * @param  \App\Models\Quote  $quote
     * @return void
     */
    public function deleted(Quote $quote)
    {
        //
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
