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
    Log::error('firing actions');

        $this->setDefaultAgent();

        Log::error('default agent set');

        Log::error($this->accountTicketSettings->alert_ticket_assign_agent);

        if($this->alert_ticket_assign_agent() && $this->default_agent_id())
        {
        Log::error('inside alter agent code');

            $toEmail = $this->ticket->agent->email;
            Log::error("to {$toEmail}");

            $fromEmail = $this->buildFromAddress();

            $fromName = $this->accountTicketSettings->from_name;

            $subject = trans('texts.ticket_assignment', ['ticket_number' => $this->ticket->ticket_number, 'agent' => $this->ticket->agent->getName()]);

            $view = 'ticket_template';
            Log::error("Here we are {$toEmail} {$fromEmail} {$subject} {$view}");

            $data = [
                'bccEmail' => $this->accountTicketSettings->alert_ticket_assign_email,
                'body' => parent::buildTicketBodyResponse($this->ticket, $this->accountTicketSettings, $this->accountTicketSettings->alert_ticket_assign_agent),
                'account' => $this->account,
                'replyTo' => $this->ticket->getTicketEmailFormat(),
                'invitation' => $this->ticket->invitations->first()
            ];

            if (Utils::isSelfHost() && config('app.debug'))
                \Log::info("Sending email - To: {$toEmail} | Reply: {$fromEmail} | From: {$subject}");

Log::error('TIME TO SEND!!!');
            $ticketMailer = new TicketMailer();
            $msg = $ticketMailer->sendTo($toEmail, $fromEmail, $fromName, $subject, $view, $data);
            Log::error($msg);
        }

        /* We also need to fire a new_ticket_template action in case we need to send an autoreply to the client */

        $this->newTicketTemplateAction();

    }

    public function newTicketTemplateAction()
    {
        Log::error('outside new ticket template code');
        Log::error($this->accountTicketSettings->new_ticket_template_id);

        if($this->new_ticket_template_id())
        {
            Log::error('inside new ticket template code');


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

            Log::error('TIME TO SEND 2!!!');

            Log::error("{$toEmail} {$fromEmail} {$fromName} {$subject} {$view}");

            $ticketMailer = new TicketMailer();
            $msg = $ticketMailer->sendTo($toEmail, $fromEmail, $fromName, $subject, $view, $data);
            Log::error($msg);
        }

    }

    /**
     *
     */
    private function setDefaultAgent() : void
    {

        if($this->default_agent_id())
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