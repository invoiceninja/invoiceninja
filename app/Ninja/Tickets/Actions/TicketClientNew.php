<?php

namespace App\Ninja\Tickets\Actions;

use App\Libraries\Utils;
use App\Models\AccountTicketSettings;
use App\Models\Ticket;
use App\Ninja\Mailers\TicketMailer;
use Illuminate\Support\Facades\Log;

/**
 * Class TicketClientNew
 * @package App\Ninja\Tickets\Actions
 */
class TicketClientNew extends BaseAction
{
    /**
     * Check if a default agent to assign exists
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

        if($this->accountTicketSettings->alert_ticket_assign_agent > 0)
        {

            $ticketMailer = new TicketMailer();

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
                \Log::info("Sending email - To: {$toEmail} | Reply: {$fromEmail} | From: {$subject}");

            $ticketMailer->sendTo($toEmail, $fromEmail, $fromName, $subject, $view, $data);

        }

        /* We also need to fire a new_ticket_template action in case we need to send an autoreply to the client */

        $this->newTicketTemplateAction();

    }

    public function newTicketTemplateAction()
    {
        if($this->accountTicketSettings->new_ticket_template_id > 0)
        {

            $ticketMailer = new TicketMailer();

            $toEmail = $this->ticket->contact->email;

            $fromEmail = $this->buildFromAddress();

            $fromName = $this->accountTicketSettings->from_name;

            $subject = trans('texts.ticket_new_template_subject', ['ticket_number' => $this->ticket->ticket_number]);

            $view = 'ticket_template';

            $data = [
                'body' => parent::buildTicketBodyResponse($this->ticket, $this->accountTicketSettings, $this->accountTicketSettings->new_ticket_template_id),
                'account' => $this->account,
                'replyTo' => $this->ticket->getTicketEmailFormat(),
                'invitation' => $this->ticket->invitations->first()
            ];

            if (Utils::isSelfHost() && config('app.debug'))
                \Log::info("Sending email - To: {$toEmail} | Reply: {$fromEmail} | From: {$subject}");

            $ticketMailer->sendTo($toEmail, $fromEmail, $fromName, $subject, $view, $data);
        }

    }

    /**
     *
     */
    private function setDefaultAgent() : void
    {

        if( (bool) $this->accountTicketSettings->default_agent_id )
        {

            $this->ticket->agent_id = $this->accountTicketSettings->default_agent_id;
            $this->ticket->save();

        }

    }

    /**
     * @return array
     */
    private function buildNotificationData() : array
    {



        return $data;

    }


}