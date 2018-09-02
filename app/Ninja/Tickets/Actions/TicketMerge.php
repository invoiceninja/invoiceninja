<?php

namespace App\Ninja\Tickets\Actions;

class TicketMerge extends BaseAction
{
    /**
     * Handle notifications for when tickets are merged
     */

    /**
     * @var Ticket
     */
    protected $ticket;

    /**
     * TicketMerge constructor.
     */
    public function __construct(Ticket $ticket)
    {

        $this->ticket = $ticket;

        $this->account = $ticket->account;

        $this->accountTicketSettings = $ticket->account->account_ticket_settings;

    }

    /**
     * Fire sequence for TICKET_MERGE
     */
    public function fire()
    {

        $handler = new TicketAgentClosed($this->ticket);
        $handler->fire();

    }
}