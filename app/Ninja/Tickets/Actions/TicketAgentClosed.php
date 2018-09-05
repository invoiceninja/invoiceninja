<?php

namespace App\Ninja\Tickets\Actions;

use App\Libraries\Utils;
use App\Models\Ticket;
use App\Ninja\Mailers\TicketMailer;
use Illuminate\Support\Facades\Log;

/**
 * Class InboundAgentReply
 * @package App\Ninja\Tickets\Actions
 */
class TicketAgentClosed extends BaseAction
{

    /**
     * Handle a contact reply to an existing ticket
     */

    public function fire(Ticket $ticket)
    {
        $account = $ticket->account;
        $accountTicketSettings = $account->accountTicketSettings;

        if($accountTicketSettings->close_ticket_template_id > 0)
        {
            $toEmail = $ticket->contact->email;
            $fromEmail = $this->buildFromAddress($accountTicketSettings);
            $fromName = $accountTicketSettings->from_name;
            $subject = trans('texts.ticket_closed_template_subject', ['ticket_number' => $ticket->ticket_number]);

            $view = 'ticket_template';

            $data = [
                'bccEmail' => $accountTicketSettings->alert_new_comment_email,
                'body' => parent::buildTicketBodyResponse($ticket, $accountTicketSettings, $accountTicketSettings->close_ticket_template_id),
                'account' => $account,
                'replyTo' => $ticket->getTicketEmailFormat(),
                'invitation' => $ticket->invitations->first()
            ];

            if (Utils::isSelfHost() && config('app.debug'))
                \Log::info("Sending email - To: {$toEmail} | Reply: {$ticket->getTicketEmailFormat()} | From: {$fromEmail}");

            $ticketMailer = new TicketMailer();
            Log::error("Sending email - To: {$toEmail} | Reply: {$ticket->getTicketEmailFormat()} | From: {$fromEmail}");

            $msg = $ticketMailer->sendTo($toEmail, $fromEmail, $fromName, $subject, $view, $data);
            Log::error($msg);
        }

    }

}
