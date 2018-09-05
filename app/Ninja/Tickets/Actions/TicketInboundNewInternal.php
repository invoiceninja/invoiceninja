<?php

namespace App\Ninja\Tickets\Actions;

use App\Models\Ticket;
use Illuminate\Support\Facades\Log;

/**
 * Class TicketInboundNewInternal
 * @package App\Ninja\Tickets\Actions
 */
class TicketInboundNewInternal extends BaseAction
{

    /**
     * A inbound ticket could have several flavours:
     *
     * 1. New support request...... support@support.invoiceninja.com
     * -> New Ticket Creation + Events
     *
     */

    /**
     * @var Ticket
     */
    protected $ticket;


    /**
     * TicketInboundNewInternal constructor.
     * @param Ticket $ticket
     */
    public function __construct(Ticket $ticket)
    {

        $this->ticket = $ticket;
        $this->account = $ticket->account;
        $this->accountTicketSettings = $this->account->accountTicketSettings;

    }

    /**
     * Fire sequence for TICKET_INBOUND_NEW_INTERNAL
     */
    public function fire()
    {

        $this->setDefaultAgent();

        if($this->alert_ticket_assign_agent() && $this->default_agent_id())
        {

            $toEmail = $this->ticket->agent->email;

            $fromEmail = $this->buildFromAddress();

            $fromName = $this->accountTicketSettings->from_name;

            $subject = trans('texts.ticket_assignment', ['ticket_number' => $this->ticket->ticket_number, 'agent' => $this->ticket->agent->getName()]);

            $view = 'ticket_template';

            $data = [
                'bccEmail' => $this->accountTicketSettings->alert_ticket_assign_email,
                'body' => parent::buildTicketBodyResponse($this->ticket, $this->accountTicketSettings, $this->accountTicketSettings->alert_ticket_assign_agent),
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
