<?php

namespace App\Ninja\Tickets\Actions;

use App\Libraries\Utils;
use App\Models\Ticket;
use App\Ninja\Mailers\TicketMailer;
use Illuminate\Support\Facades\Log;

/**
 * Class TicketClientNew
 * @package App\Ninja\Tickets\Actions
 */
class TicketAgentNew extends BaseTicketAction
{
    /**
     * Check if a default agent exists
     * Fire notification to agent / slack if configured
     * Fire notification to client if new_ticket_template exists
     */

    public function fire(Ticket $ticket) : void
    {

        $account = $ticket->account;
        $accountTicketSettings = $account->account_ticket_settings;

        $this->setDefaultAgent($ticket, $accountTicketSettings);

        if($accountTicketSettings->alert_ticket_assign_agent_id > 0 && $accountTicketSettings->default_agent_id > 0 && $ticket->agent_id > 0)
        {

            $toEmail = $ticket->agent->email;

            $fromEmail = $this->buildFromAddress($accountTicketSettings);

            $fromName = $accountTicketSettings->from_name;

            $subject = trans('texts.ticket_assignment', ['ticket_number' => $ticket->ticket_number, 'agent' => $ticket->agent->getName()]);

            $view = 'ticket_template';

            $data = [
                'bccEmail' => $accountTicketSettings->alert_ticket_assign_email,
                'body' => parent::buildTicketBodyResponse($ticket, $accountTicketSettings, $accountTicketSettings->alert_ticket_assign_agent_id),
                'account' => $account,
                'replyTo' => $ticket->getTicketEmailFormat(),
                'invitation' => $ticket->invitations->first()
            ];

            $ticketMailer = new TicketMailer();

            $msg = $ticketMailer->sendTo($toEmail, $fromEmail, $fromName, $subject, $view, $data);

            if (Utils::isSelfHost() && config('app.debug')) {
                \Log::info("Sending email - To: {$toEmail} | Reply: {$ticket->getTicketEmailFormat()} | From: {$fromEmail}");
                \Log::error($msg);
            }
        }

        $this->newTicketTemplateAction($ticket);

    }


}