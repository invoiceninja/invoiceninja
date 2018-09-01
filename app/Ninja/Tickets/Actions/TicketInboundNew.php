<?php

namespace App\Ninja\Tickets\Actions;

use App\Models\Ticket;

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
     * @var Ticket
     */
    protected $ticket;

    /**
     * TicketInboundNew constructor.
     */
    public function __construct(Ticket $ticket)
    {

        $this->ticket = $ticket;

    }

    /**
     * Fire sequence for TICKET_INBOUND_NEW
     *
     * Curent scope there is no difference from
     * TICKET_CLIENT_NEW so we simply init that class and ->fire()
     */
    public function fire()
    {

        $handler = new TicketClientNew($this->ticket);

        $handler->fire();

    }

}
