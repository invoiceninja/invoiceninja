<?php

namespace App\Listeners\Quote;

use App\Jobs\Quote\QuoteWorkflowSettings;
use App\Libraries\MultiDB;

class ReachWorkflowSettings
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
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

        QuoteWorkflowSettings::dispatchSync($event->quote);
    }
}
