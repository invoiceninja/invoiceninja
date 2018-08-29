<?php

namespace App\Ninja\Tickets\Inbound;

use App\Models\AccountTicketSettings;
use App\Models\Contact;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\TicketInvitation;
use Illuminate\Support\Facades\Log;

/**
 * Class InboundTicketService
 * @package App\Ninja\Tickets\Inbound
 */
class InboundTicketService
{

    /**
     * @var InboundTicketFactory
     */
    protected $inboundTicketFactory;

    /**
     * InboundTicketService constructor.
     */

    public function __construct(InboundTicketFactory $inboundTicketFactory)
    {
        $this->inboundTicketFactory = $inboundTicketFactory;
    }

    /**
     * @return mixed
     */
    public function process()
    {
        /* Attempt to parse the hash and harvest the $ticket */
        if($ticket_hash = $this->inboundTicketFactory->mailboxHash()) {

            $ticketInvitation = TicketInvitation::whereTicketHash($ticket_hash)->first();

            if($ticketInvitation)
                return $ticketInvitation->ticket;

        }
        else
            return $this->checkSupportEmailAttempt(); //if no valid hash exists - check if we can match via the custom local part

    }

    /**
     * @return null
     */
    private function checkSupportEmailAttempt()
    {
        $to = $this->inboundTicketFactory->to();
        $parts = explode("@", $to);

        $from = $this->inboundTicketFactory->fromEmail();

        $accountTicketSettings = AccountTicketSettings::where('support_email_local_part', $parts[0])->first();

        if($accountTicketSettings) {
            $contacts = Contact::whereAccountId($accountTicketSettings->account_id)
                                ->whereEmail($from)->get();


            if(count($contacts) == 1) { Log::error('found a contact - creating ticket');
                return $this->createTicket($accountTicketSettings->ticket_master, $contacts[0]);
            }
            elseif(count($contacts) > 1){
                //what happens if we have multiple identical emails assigned to the same account? breakage.
                Log::error('multiple contacts - could not determine which account this belongs to');
                return $this->createClientlessTicket($accountTicketSettings->ticket_master, $from, $accountTicketSettings->account);
            }
            else { Log::error('not sure what happened');
                return null;
            }
        }

    }

    /**
     * @param $user
     * @param $contact
     * @return mixed
     */
    private function createTicket($user, $contact)
    {
        $ticket = Ticket::createNew($user);
        $ticket->client_id = $contact->client_id;
        $ticket->contact_key = $contact->contact_key;
        $ticket->agent_id = $user->id;
        $ticket->ticket_number = Ticket::getNextTicketNumber($contact->account->id);
        $ticket->priority_id = TICKET_PRIORITY_LOW;
        $ticket->status_id = TICKET_STATUS_NEW;
        $ticket->subject = $this->inboundTicketFactory->subject();
        $ticket->category_id = 1;
        $ticket->save();

        $ticketComment = TicketComment::createNew($ticket);
        $ticketComment->description = $this->inboundTicketFactory->TextBody();
        $ticketComment->contact_key = $contact->contact_key;
        $ticket->comments()->save($ticketComment);

            return $ticket;
    }

    private function createClientlessTicket($user, $contactEmail, $account)
    {
        $ticket = Ticket::createNew($user, $contactEmail);
        $ticket->contact_key = $contactEmail;
        $ticket->ticket_number = Ticket::getNextTicketNumber($account->id);
        $ticket->priority_id = TICKET_PRIORITY_LOW;
        $ticket->status_id = TICKET_STATUS_NEW;
        $ticket->subject = $this->inboundTicketFactory->subject();
        $ticket->description = $this->inboundTicketFactory->TextBody();
        $ticket->category_id = 1;
        $ticket->save();

        $ticketComment = TicketComment::createNew($ticket);
        $ticketComment->description = $this->inboundTicketFactory->TextBody();
        $ticketComment->agent_id = $user->id;
        $ticket->comments()->save($ticketComment);

        return $ticket;
    }

}