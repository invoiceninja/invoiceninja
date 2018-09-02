<?php

namespace App\Ninja\Tickets\Actions;

use App\Libraries\Utils;
use App\Models\Ticket;
use App\Ninja\Mailers\TicketMailer;
use Illuminate\Support\Facades\Log;

/**
 * Class TicketInboundNew
 * @package App\Ninja\Tickets\Actions
 */
class InboundContactReply extends BaseAction
{

    /**
     * Handle a contact reply to an existing ticket
     */

    /**
     * @var Ticket
     */
    protected $ticket;

    /**
     * TicketInboundNew constructor.
     */
    public function __construct(Ticket $ticket)
    {

        $this->ticket = $ticket;

        $this->account = $ticket->account;

        $this->accountTicketSettings = $ticket->account->account_ticket_settings;

    }

    /**
     * Fire sequence for INBOUND_CONTACT_REPLY
     */
    public function fire()
    {
        if($this->alert_new_comment())
        {
            $toEmail = $this->ticket->agent->email;

            $fromEmail = $this->buildFromAddress();

            $fromName = $this->accountTicketSettings->from_name;

            $subject = trans('texts.ticket_contact_reply', ['ticket_number' => $this->ticket->ticket_number, 'contact' => $this->ticket->getContactName()]);

            $view = 'ticket_template';

            $data = [
                'bccEmail' => $this->accountTicketSettings->alert_new_comment_email,
                'body' => parent::buildTicketBodyResponse($this->ticket, $this->accountTicketSettings, $this->accountTicketSettings->alert_new_comment),
                'account' => $this->account,
                'replyTo' => $this->ticket->getTicketEmailFormat(),
                'invitation' => $this->ticket->invitations->first()
            ];

            if (Utils::isSelfHost() && config('app.debug'))
                \Log::info("Sending email - To: {$toEmail} | Reply: {$this->ticket->getTicketEmailFormat()} | From: {$fromEmail}");

            $ticketMailer = new TicketMailer();
            Log::error("Sending email - To: {$toEmail} | Reply: {$this->ticket->getTicketEmailFormat()} | From: {$fromEmail}");

            $msg = $ticketMailer->sendTo($toEmail, $fromEmail, $fromName, $subject, $view, $data);
            Log::error($msg);
        }

    }

}
