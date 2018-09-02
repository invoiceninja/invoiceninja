<?php

namespace App\Ninja\Tickets\Actions;

use App\Models\Ticket;
use Illuminate\Support\Facades\Log;

/**
 * Class TicketInboundNew
 * @package App\Ninja\Tickets\Actions
 */
class TicketInboundReply extends BaseAction
{

    /**
     * A inbound ticket could have several flavours:
     *
     * 1. Existing support reply... support+TICKET_HASH@support.invoiceninja.com ->
     *      Harvest TICKET_HASH -> get Ticket and apply notification sequence
     * 2. Existing internal ticket reply.... support+TICKET_NUMBER@support.invoiceninja.com ->
     *      Harvest TICKET_NUMBER - Determine replying agent and fire notification sequence
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

        $this->account = $ticket->account;

        $this->accountTicketSettings = $ticket->account->account_ticket_settings;

    }

    /**
     * Fire sequency for TICKET_INBOUND_NEW
     */
    public function fire()
    {

    }

}
