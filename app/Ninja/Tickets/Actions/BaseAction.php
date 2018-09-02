<?php

namespace App\Ninja\Tickets\Actions;

use App\Constants\Domain;
use App\Libraries\Utils;
use App\Models\Ticket;
use App\Ninja\Mailers\TicketMailer;
use App\Services\TicketTemplateService;
use Illuminate\Support\Facades\Log;

/**
 * Class BaseAction
 * @package App\Ninja\Tickets\Actions
 */
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

    /**
     * @param $template
     * @param $args
     * @return bool
     */
    public function __call($template, $args)
    {

        return $this->accountTicketSettings->$template ? TRUE : FALSE ;

    }

    /**
     * @return string
     */
    public function buildFromAddress() : string
    {

        $fromName = $this->accountTicketSettings->support_email_local_part;

        if(Utils::isNinjaProd())
            $domainName = Domain::getSupportDomainFromId($this->account->domain_id);
        else
            $domainName = config('ninja.tickets.ticket_support_domain');

        return "{$fromName}@{$domainName}";

    }

    /**
     * fires new_ticket_template to client
     */
    public function newTicketTemplateAction() : void
    {

        if($this->new_ticket_template_id())
        {

            $toEmail = $this->ticket->contact->email;
            $fromEmail = $this->buildFromAddress();
            $fromName = $this->accountTicketSettings->from_name;
            $subject = trans('texts.ticket_new_template_subject', ['ticket_number' => $this->ticket->ticket_number]);
            $view = 'ticket_template';

            $data = [
                'body' => self::buildTicketBodyResponse($this->ticket, $this->accountTicketSettings, $this->accountTicketSettings->new_ticket_template_id),
                'account' => $this->account,
                'replyTo' => $this->ticket->getTicketEmailFormat(),
                'invitation' => $this->ticket->invitations->first()
            ];

            if (Utils::isSelfHost() && config('app.debug'))
                \Log::info("Sending email - To: {$toEmail} | Reply: {$fromEmail} | From: {$subject}");

            Log::error("Sending email - To: {$toEmail} | Reply: {$this->ticket->getTicketEmailFormat()} | From: {$fromEmail}");
            $ticketMailer = new TicketMailer();

            $msg = $ticketMailer->sendTo($toEmail, $fromEmail, $fromName, $subject, $view, $data);
            Log::error($msg);
        }

    }

    /**
     * Set default agent - if exists
     */
    public function setDefaultAgent() : void
    {

        if($this->default_agent_id())
        {

            $this->ticket->agent_id = $this->accountTicketSettings->default_agent_id;
            $this->ticket->save();

        }

    }
}