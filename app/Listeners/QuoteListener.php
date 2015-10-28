<?php namespace app\Listeners;

use App\Events\QuoteWasEmailed;
use App\Events\QuoteInvitationWasViewed;

class QuoteListener
{
    public function emailedQuote(QuoteWasEmailed $event)
    {
        $quote = $event->quote;
        $quote->markSent();
    }

    public function viewedQuote(QuoteInvitationWasViewed $event)
    {
        $invitation = $event->invitation;
        $invitation->markViewed();
    }
}
