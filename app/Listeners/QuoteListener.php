<?php namespace App\Listeners;

use App\Events\QuoteInvitationWasViewed;
use Carbon;

class QuoteListener
{
    public function viewedQuote(QuoteInvitationWasViewed $event)
    {
        $invitation = $event->invitation;
        $invitation->markViewed();
    }
}
