<?php

namespace App\Ninja\Tickets\Actions;

use App\Libraries\Utils;
use App\Models\Ticket;
use App\Ninja\Mailers\TicketMailer;
use Illuminate\Support\Facades\Log;

/**
 * Class TicketInboundAgentReply
 * @package App\Ninja\Tickets\Actions
 */
class TicketInboundAgentReply extends BaseTicketAction
{

    /**
     * Handle a contact reply to an existing ticket
     */

    public function fire(Ticket $ticket)
    {

        $account = $ticket->account;
        $accountTicketSettings = $account->account_ticket_settings;

        if($accountTicketSettings->update_ticket_template_id > 0)
        {
            $toEmail = $ticket->contact->email;
            $fromEmail = $this->buildFromAddress($accountTicketSettings);
            $fromName = $accountTicketSettings->from_name;
            $subject = trans('texts.ticket_updated_template_subject', ['ticket_number' => $ticket->ticket_number]);

            $view = 'ticket_template';

            $data = [
                'bccEmail' => $accountTicketSettings->alert_new_comment_id_email,
                'body' => parent::buildTicketBodyResponse($ticket, $accountTicketSettings, $accountTicketSettings->update_ticket_template_id),
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

    }

}