<?php

namespace App\Ninja\Tickets\Deltas;

use App\Libraries\Utils;
use App\Models\Ticket;
use App\Ninja\Mailers\TicketMailer;
use App\Services\TicketTemplateService;
use Illuminate\Support\Facades\Log;

/**
 * Class NewTicketDelta
 * @package App\Ninja\Tickets\Deltas
 */
class NewTicketDelta extends BaseDelta
{
    /**
     * @param Ticket $ticket
     */
    public static function handle(Ticket $newTicket)
    {
        $accountTicketSettings = $newTicket->account->account_ticket_settings;

        if($accountTicketSettings->new_ticket_template_id > 0) {

            $ticketMailer = new TicketMailer();
            //$agent = User::whereAccountId($accountTicketSettings->account->id)->whereId($updatedTicket->agent_id)->first();

            $data['bccEmail'] = $accountTicketSettings->alert_ticket_assign_email;
            $data['body'] = parent::buildTicketBodyResponse($newTicket, $accountTicketSettings, $accountTicketSettings->new_ticket_template_id);
            $data['account'] = $newTicket->account;
            $data['replyTo'] = $newTicket->getTicketEmailFormat();

            //$toEmail = strtolower($updatedTicket->agent->email); //todo else $agent->email
            $toEmail = 'david@romulus.com.au';
            $fromEmail = 'support@support.invoiceninja.com'; //todo need to inject client specific address
            $fromName = trans('texts.ticket_master');
            $subject = trans('texts.ticket_assignment', ['ticket_number' => $newTicket->ticket_number, 'agent' => $newTicket->agent->getName()]);

            $view = 'ticket_template';

            if (Utils::isSelfHost() && config('app.debug'))
                \Log::info("Sending email - To: {$toEmail} | Reply: {$fromEmail} | From: {$subject}");

            $ticketMailer->sendTo($toEmail, $fromEmail, $fromName, $subject, $view, $data);

        }

   }


}