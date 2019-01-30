<?php

namespace App\Listeners;

use App\Events\QuoteInvitationWasViewed;
use App\Events\QuoteWasEmailed;

/**
 * Class QuoteListener.
 */
class QuoteListener
{
    /**
     * @param QuoteInvitationWasViewed $event
     */
    public function viewedQuote(QuoteInvitationWasViewed $event)
    {
        $invitation = $event->invitation;
        $invitation->markViewed();
    }

    /**
     * @param InvoiceWasEmailed $event
     */
    public function emailedQuote(QuoteWasEmailed $event)
    {
        $quote = $event->quote;
        $quote->last_sent_date = date('Y-m-d');
        $quote->save();
    }

}
