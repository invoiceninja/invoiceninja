<?php

namespace App\Events;

use App\Models\Invitation;
use App\Models\Invoice;
use Illuminate\Queue\SerializesModels;

class QuoteInvitationWasApproved extends Event
{
    use SerializesModels;

    public $quote;

    /**
     * @var Invitation
     */
    public $invitation;

    /**
     * Create a new event instance.
     *
     * @param $quote
     * @param Invoice    $invoice
     * @param Invitation $invitation
     */
    public function __construct(Invoice $quote, Invitation $invitation)
    {
        $this->quote = $quote;
        $this->invitation = $invitation;
    }
}
