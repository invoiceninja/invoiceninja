<?php

namespace App\Ninja\Tickets\Actions;

use App\Models\Ticket;
use App\Services\TicketTemplateService;

class BaseAction
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