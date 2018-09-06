<?php

namespace App\Ninja\Tickets\Actions;

use App\Models\Ticket;
use Illuminate\Support\Facades\Log;

/**
 * Class TicketInboundNew
 * @package App\Ninja\Tickets\Actions
 */
class TicketInboundReply extends BaseTicketAction
{

    /**
     * A inbound ticket could have several flavours:
     *
     * 1. Existing support reply... support+TICKET_HASH@support.invoiceninja.com ->
     *      Harvest TICKET_HASH -> get Ticket and apply notification sequence
     * 2. Existing internal ticket reply.... support+TICKET_NUMBER@support.invoiceninja.com ->
     *      Harvest TICKET_NUMBER - Determine replying agent and fire notification sequence
     */

    public function fire(Ticket $ticket)
    {

        $account = $ticket->account;
        $accountTicketSettings = $account->account_ticket_settings;

    }

}
