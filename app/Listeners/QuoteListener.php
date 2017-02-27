<?php

namespace App\Listeners;

use App\Events\QuoteInvitationWasViewed;

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
}
