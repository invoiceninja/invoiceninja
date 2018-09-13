<?php

namespace App\Ninja\Tickets\Actions;

use App\Constants\Domain;
use App\Libraries\Utils;
use App\Models\Account;
use App\Models\AccountTicketSettings;
use App\Models\Ticket;
use App\Ninja\Mailers\TicketMailer;
use App\Ninja\Repositories\TicketRepository;
use Illuminate\Support\Facades\Log;

/**
 * Class TicketOverdue
 * @package App\Ninja\Tickets\Actions
 */
class TicketOverdue extends BaseTicketAction
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

        /** Update priority status of ticket */
        $data['priority_id'] = TICKET_PRIORITY_HIGH;
        $data['action'] = TICKET_SAVE_ONLY;

        $ticketRepo = new TicketRepository();
        $ticketRepo->save($data, $ticket, $ticket->user);

        if($accountTicketSettings->alert_ticket_overdue_agent_id > 0 && $ticket->agent_id > 0)
        {

            $toEmail = $ticket->agent->email;
            $fromEmail = $this->buildFromLocalAddress($account, $accountTicketSettings);
            $fromName = $accountTicketSettings->from_name;
            $subject = trans('texts.ticket_overdue_template_subject', ['ticket_number' => $ticket->ticket_number]);
            $view = 'ticket_template';

            $data = [
                'bccEmail' => $accountTicketSettings->alert_ticket_assign_email,
                'body' => parent::buildTicketBodyResponse($ticket, $accountTicketSettings, $accountTicketSettings->alert_ticket_overdue_agent_id),
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