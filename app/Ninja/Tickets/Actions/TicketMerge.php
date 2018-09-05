<?php

namespace App\Ninja\Tickets\Actions;

use App\Models\Ticket;

class TicketMerge extends BaseAction
{
    /**
     * Handle notifications for when tickets are merged
     */

    public function fire(Ticket $ticket)
    {

        $handler = new TicketAgentClosed($ticket);
        $handler->fire();

    }
}