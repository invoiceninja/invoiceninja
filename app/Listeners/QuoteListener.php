<?php namespace App\Listeners;

use Carbon;
use App\Events\QuoteWasEmailed;
use App\Events\QuoteInvitationWasViewed;
use App\Events\QuoteInvitationWasEmailed;

class QuoteListener
{
    public function viewedQuote(QuoteInvitationWasViewed $event)
    {
        $invitation = $event->invitation;
        $invitation->markViewed();
    }
}
