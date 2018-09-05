<?php

namespace App\Ninja\Tickets\Actions;

use App\Models\Ticket;
use Illuminate\Support\Facades\Log;

/**
 * Class TicketInboundNew
 * @package App\Ninja\Tickets\Actions
 */
class TicketInboundNew extends BaseAction
{

    /**
     * A inbound ticket could have several flavours:
     *
     * 1. New support request...... support@support.invoiceninja.com
     * -> New Ticket Creation + Events
     *
     */

    /**
     * Fire sequence for TICKET_INBOUND_NEW
     *
     * Curent scope there is no difference from
     * TICKET_CLIENT_NEW so we simply init that class and ->fire()
     */
    public function fire(Ticket $ticket)
    {
        Log::error('inside ticket inbound new and just about to fire action');
        $handler = new TicketClientNew($ticket);

        $handler->fire();

    }

}
