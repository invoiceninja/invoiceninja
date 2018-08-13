<?php

namespace App\Ninja\Tickets\Deltas;

use App\Models\Ticket;
use App\Services\TicketTemplateService;
use Illuminate\Support\Facades\Log;

class BaseDelta
{

    /**
     * @param Ticket $ticket
     * @param $accountTicketSettings
     * @return array
     */
    public static function buildTicketBodyResponse(Ticket $ticket, $accountTicketSettings, $templateId)
    {
        $ticketVariables = TicketTemplateService::getVariables($ticket);
        $template = $ticket->getTicketTemplate($templateId);

        $ticketVariables = array_merge($ticketVariables,
            [
                'ticket_master' => $accountTicketSettings->ticket_master->getName(),
            ]);

        return str_replace(array_keys($ticketVariables), array_values($ticketVariables), $template->description);

    }

}