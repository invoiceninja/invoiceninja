<?php

namespace App\Ninja\Tickets\Actions;

use App\Constants\Domain;
use App\Libraries\Utils;
use App\Models\Account;
use App\Models\AccountTicketSettings;
use App\Models\Ticket;
use App\Ninja\Mailers\TicketMailer;
use Illuminate\Support\Facades\Log;

/**
 * Class TicketOverdue
 * @package App\Ninja\Tickets\Actions
 */
class TicketOverdue extends BaseAction
{

    /**
     * @var
     */
    protected $ticket;

    /**
     * fires the sequence for this ticket action
     */
    public function fire(Ticket $ticket) : void
    {

        $account = $ticket->account;
        $accountTicketSettings = $account->account_ticket_settings;

        Log::error($accountTicketSettings->alert_ticket_overdue_agent);
        Log::error($ticket->agent_id);
        Log::error($ticket->ticket_number);


        if($accountTicketSettings->alert_ticket_overdue_agent > 0 && $ticket->agent_id > 0)
        {
        Log::error('inside!');
            $toEmail = $ticket->agent->email;

            $fromEmail = $this->buildFromLocalAddress($account, $accountTicketSettings);

            $fromName = $accountTicketSettings->from_name;

            $subject = trans('texts.ticket_overdue_template_subject', ['ticket_number' => $ticket->ticket_number]);

            $view = 'ticket_template';

            $data = [
                'bccEmail' => $accountTicketSettings->alert_ticket_assign_email,
                'body' => parent::buildTicketBodyResponse($ticket, $accountTicketSettings, $accountTicketSettings->alert_ticket_overdue_agent),
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

            $ticket->overdue_notification_sent = true;
            $ticket->save();

        }


    }


    /**
     * @param $account
     * @param $accountTicketSettings
     * @return string
     */
    public function buildFromLocalAddress($account, $accountTicketSettings) : string
    {

        $fromName = $accountTicketSettings->support_email_local_part;

        if(Utils::isNinjaProd())
            $domainName = Domain::getSupportDomainFromId($account->domain_id);
        else
            $domainName = config('ninja.tickets.ticket_support_domain');

        return "{$fromName}@{$domainName}";

    }


}