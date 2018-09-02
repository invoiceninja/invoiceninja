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
class TicketAgentNew extends BaseAction
{
    /**
     * Check if a default agent exists
     * Fire notification to agent / slack if configured
     * Fire notification to client if new_ticket_template exists
     */

    protected $ticket;

    /**
     * TicketClientNew constructor.
     */
    public function __construct(Ticket $ticket)
    {

        $this->ticket = $ticket;

        $this->account = $ticket->account;

        $this->accountTicketSettings = $ticket->account->account_ticket_settings;

    }

    /**
     * fires the sequence for this ticket action
     */
    public function fire() : void
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

        $this->newTicketTemplateAction();

    }



}