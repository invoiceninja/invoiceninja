<?php

namespace App\Events;

use App\Models\Ticket;
use Illuminate\Queue\SerializesModels;

/**
 * Class TicketUserViewed.
 */
class TicketUserViewed extends Event
{
    use SerializesModels;

    /**
     * @var Ticket
     */
    public $ticket;

    /**
     * Create a new event instance.
     *
     * @param Ticket $ticket
     */
    public function __construct(Ticket $ticket)
    {
        $this->ticket = $ticket;
    }
}
