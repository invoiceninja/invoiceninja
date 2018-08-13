<?php

namespace App\Ninja\Tickets\Inbound;

use App\Models\AccountTicketSettings;
use App\Models\Contact;
use App\Models\Ticket;
use App\Models\TicketInvitation;

class InboundTicketService
{

    protected $inboundTicketFactory;

    /**
     * InboundTicketService constructor.
     */

    public function __construct(InboundTicketFactory $inboundTicketFactory)
    {
        $this->inboundTicketFactory = $inboundTicketFactory;
    }

    public function process()
    {
        if($ticket_hash = $this->inboundTicketFactory->mailboxHash()) {

            $ticketInvitation = TicketInvitation::whereTicketHash($ticket_hash)->first();

            if($ticketInvitation)
                return $ticketInvitation->ticket;
            else
                $this->checkSupportEmailAttempt();

        }

    }

    private function checkSupportEmailAttempt()
    {
        $to = $this->inboundTicketFactory->to();
        $parts = explode("@", $to);

        $from = $this->inboundTicketFactory->fromEmail();

        $accountTicketSettings = AccountTicketSettings::whereSupportEmailLocalPart($parts[0])->first();

        if($accountTicketSettings) {
            $contacts = Contact::whereAccountId($accountTicketSettings->account_id)
                                ->whereEmail($from)->get();


            if(count($contacts) == 1) {
                $this->createTicket($accountTicketSettings->ticket_master, $contacts[0]);
            }
            elseif(count($contacts > 1)){
                //what happens if we have multiple identical emails assigned to the same account? breakage.
            }
            else
                return null;
        }

    }

    public function createTicket($user, $contact)
    {
        $ticket = Ticket::createNew($user);
        $ticket->client_id = $contact->client_id;
        $ticket->agent_id = $user->id;
        $ticket->ticket_number = Ticket::getNextTicketNumber($contact->account->id);
        $ticket->priority_id = TICKET_PRIORITY_LOW;
        $ticket->save();
            return $ticket;
    }

}