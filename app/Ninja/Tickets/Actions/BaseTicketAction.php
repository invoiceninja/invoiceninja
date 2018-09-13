<?php

namespace App\Ninja\Tickets\Actions;

use App\Constants\Domain;
use App\Libraries\Utils;
use App\Models\AccountTicketSettings;
use App\Models\Ticket;
use App\Ninja\Mailers\TicketMailer;
use App\Services\TicketTemplateService;
use Illuminate\Support\Facades\Log;

/**
 * Class BaseTicketAction
 * @package App\Ninja\Tickets\Actions
 */
class BaseTicketAction
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
     * Builds the account support email address
     * @return string
     */
    public function buildFromAddress(AccountTicketSettings $accountTicketSettings) : string
    {

        $fromName = $accountTicketSettings->support_email_local_part;

        if(Utils::isNinjaProd())
            $domainName = Domain::getSupportDomainFromId($accountTicketSettings->account->domain_id);
        else
            $domainName = config('ninja.tickets.ticket_support_domain');

        return "{$fromName}@{$domainName}";

    }

    /**
     * fires new_ticket_template to client
     */
    public function newTicketTemplateAction(Ticket $ticket)
    {
        $account = $ticket->account;
        $accountTicketSettings = $account->account_ticket_settings;

        if($accountTicketSettings->new_ticket_template_id > 0)
        {

            $toEmail = $ticket->contact->email;
            $fromEmail = $this->buildFromAddress($accountTicketSettings);
            $fromName = $accountTicketSettings->from_name;
            $subject = trans('texts.ticket_new_template_subject', ['ticket_number' => $ticket->ticket_number]);
            $view = 'ticket_template';

            $data = [
                'body' => self::buildTicketBodyResponse($ticket, $accountTicketSettings, $accountTicketSettings->new_ticket_template_id),
                'account' => $account,
                'replyTo' => $ticket->getTicketEmailFormat(),
                'invitation' => $ticket->invitations->first()
            ];

            $ticketMailer = new TicketMailer();

            $msg = $ticketMailer->sendTo($toEmail, $fromEmail, $fromName, $subject, $view, $data);

            if (Utils::isSelfHost() && config('app.debug')) {
                \Log::info("Sending email - To: {$toEmail} | Reply: {$fromEmail} | From: {$subject}");
                \Log::info($msg);
            }
        }

    }

    /**
     * Sets the default agent to a ticket if exists
     *
     * @param $ticket
     * @param $accountTicketSettings
     */
    public function setDefaultAgent($ticket, $accountTicketSettings)
    {

        if($accountTicketSettings->default_agent_id > 0)
        {

            $ticket->agent_id = $accountTicketSettings->default_agent_id;
            $ticket->save();

        }

    }

}