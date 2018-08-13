<?php

namespace App\Ninja\Tickets\Deltas;
use App\Libraries\Utils;
use App\Models\AccountTicketSettings;
use App\Models\User;
use App\Ninja\Mailers\TicketMailer;
use App\Services\TicketTemplateService;

/**
 *
 * Class AgentDelta
 * @package App\Ninja\Tickets\Deltas
 *
 * Handles attribute specific events on Model change.
 *
 */

class AgentDelta extends BaseDelta
{

    /**
     * @param Ticket $updatedTicket
     * @param Ticket $originalTicket
     */
    public static function handle(Ticket $updatedTicket, Ticket $originalTicket)
    {
       $accountTicketSettings = $updatedTicket->account->account_ticket_settings;

       //notify agent they have a new ticket
       if($accountTicketSettings->alert_ticket_assign_agent)
           sendAgentAssignmentNotificationEmail($updatedTicket, $accountTicketSettings);


    }

    /**
     * @param Ticket $updatedTicket
     * @param AccountTicketSettings $accountTicketSettings
     */
    public function sendAgentAssignmentNotificationEmail(Ticket $updatedTicket, AccountTicketSettings $accountTicketSettings, TicketMailer $ticketMailer)
    {
        //$ticketMailer = new TicketMailer();
        //$agent = User::whereAccountId($accountTicketSettings->account->id)->whereId($updatedTicket->agent_id)->first();

        $data['bccEmail'] = $accountTicketSettings->alert_ticket_assign_email;
        $data['text'] = $this->ticketData['comment'];
        $data['replyTo'] = $accountTicketSettings->ticket_master->email;
        //$toEmail = strtolower($updatedTicket->agent->email); //todo else $agent->email
        $toEmail = 'david@romulus.com.au';
        $fromEmail = $accountTicketSettings->ticket_master->email;
        $fromName = trans('texts.ticket_master');
        $subject = trans('texts.ticket_assignment', ['ticket_number' => $updatedTicket->ticket_number, 'agent' =>$updatedTicket->agent->getName()]);
        $view = 'ticket_template';

        if (Utils::isSelfHost() && config('app.debug'))
            \Log::info("Sending email - To: {$toEmail} | Reply: {$fromEmail} | From: {$subject}");

        $ticketMailer->sendTo($toEmail, $fromEmail, $fromName, $subject, $view, $data);
//todo need to generate the body of the ticket and pass it from here.
    }





}
