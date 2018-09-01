<?php

namespace App\Ninja\Tickets\Actions;

use App\Constants\Domain;
use App\Libraries\Utils;
use App\Models\Ticket;
use App\Services\TicketTemplateService;

class BaseAction
{

    /**
     * @param Ticket $ticket
     * @param $accountTicketSettings
     * @param $templateId
     * @return string
     */
    public static function buildTicketBodyResponse(Ticket $ticket, $accountTicketSettings, $templateId) : string
    {
        $ticketVariables = TicketTemplateService::getVariables($ticket);

        $template = $ticket->getTicketTemplate($templateId);

        $ticketVariables = array_merge($ticketVariables,
            [
                'ticket_master' => $accountTicketSettings->ticket_master->getName(),
            ]);

        return str_replace(array_keys($ticketVariables), array_values($ticketVariables), $template->description);

    }

    public function __call($template, $arguments)
    {

        return (bool) $this->accountTicketSettings->$template;

    }

    public function buildFromAddress() : string
    {

        $fromName = $this->accountTicketSettings->support_email_local_part;

        if(Utils::isNinjaProd())
            $domainName = Domain::getSupportDomainFromId($this->account->domain_id);
        else
            $domainName = config('ninja.ticket_support_domain');


        return "{$fromName}@{$domainName}";

    }
}