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
    }

    /**
     *
     */
    public function fire() : void
    {

        $accountTicketSettings = $this->ticket->account->account_ticket_settings;

        $this->setDefaultAgent($accountTicketSettings);

        if($this->templateExists($accountTicketSettings))
        {

            $ticketMailer = new TicketMailer();

            $toEmail = $this->ticket->agent->email;

            $fromEmail = 'support@support.invoiceninja.com'; //todo need to inject client specific address

            $fromName = trans('texts.ticket_master');

            $subject = trans('texts.ticket_assignment', ['ticket_number' => $this->ticket->ticket_number, 'agent' => $this->ticket->agent->getName()]);

            $view = 'ticket_template';

            if (Utils::isSelfHost() && config('app.debug'))
                \Log::info("Sending email - To: {$toEmail} | Reply: {$fromEmail} | From: {$subject}");

            $ticketMailer->sendTo($toEmail, $fromEmail, $fromName, $subject, $view, $this->buildNotificationData($accountTicketSettings));

        }

    }

    /**
     * @param AccountTicketSettings $accountTicketSettings
     * @return bool
     */
    private function templateExists(AccountTicketSettings $accountTicketSettings) : bool
    {

        return (bool) $accountTicketSettings->alert_ticket_assign_agent;

    }

    /**
     * @param AccountTicketSettings $accountTicketSettings
     */
    private function setDefaultAgent(AccountTicketSettings $accountTicketSettings) : void
    {


        if( (bool) $accountTicketSettings->default_agent_id ) {

            $this->ticket->agent_id = $accountTicketSettings->default_agent_id;
            $this->ticket->save();

        }

    }

    /**
     * @param AccountTicketSettings $accountTicketSettings
     * @return array
     */
    private function buildNotificationData(AccountTicketSettings $accountTicketSettings) : array
    {

        $data = [
            'bccEmail' => $accountTicketSettings->alert_ticket_assign_email,
            'body' => parent::buildTicketBodyResponse($this->ticket, $accountTicketSettings, $accountTicketSettings->alert_ticket_assign_agent),
            'account' => $this->account,
            'replyTo' => $this->ticket->getTicketEmailFormat(),
            'invitation' => $this->ticket->invitations()
        ];

        return $data;

    }


}