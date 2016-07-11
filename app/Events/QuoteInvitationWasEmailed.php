<?php namespace App\Events;

use App\Models\Invitation;
use Illuminate\Queue\SerializesModels;

/**
 * Class QuoteInvitationWasEmailed
 */
class QuoteInvitationWasEmailed extends Event
{
    use SerializesModels;

    /**
     * @var Invitation
     */
    public $invitation;

    /**
     * Create a new event instance.
     *
     * @param Invitation $invitation
     */
    public function __construct(Invitation $invitation)
    {
        $this->invitation = $invitation;
    }

}
