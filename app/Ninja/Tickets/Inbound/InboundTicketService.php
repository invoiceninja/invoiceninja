<?php

namespace App\Ninja\Tickets\Inbound;

use App\Models\AccountTicketSettings;
use App\Models\Contact;
use App\Models\Ticket;
use App\Models\TicketInvitation;
use App\Models\User;
use App\Ninja\Repositories\TicketRepository;
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
     * @var TicketRepository
     */
    protected $ticketRepo;

    /**
     * InboundTicketService constructor.
     */

    public function __construct(InboundTicketFactory $inboundTicketFactory, TicketRepository $ticketRepo)
    {

        $this->inboundTicketFactory = $inboundTicketFactory;

        $this->ticketRepo = $ticketRepo;

    }

    /**
     * @return mixed
     */
    public function process()
    {
        /** Attempt to parse the hash and harvest the $ticket */
        if($ticket_hash = $this->inboundTicketFactory->mailboxHash()) {

            $data = [];

            $ticketInvitation = TicketInvitation::whereTicketHash($ticket_hash)->first();

            if($ticketInvitation)
            {

                /** Contact based external ticket */
                $ticket = $ticketInvitation->ticket;

                $data['is_internal'] = 0;

            }
            elseif ($ticketExists = Ticket::scope($ticket_hash)->first())
            {
                /** Internal Ticket*/
                $ticket = $ticketExists;

                $data['is_internal'] = 1;

            }
                if($ticket)
                {

                    $data['action'] = TICKET_INBOUND_REPLY;

                    $data['description'] = $this->inboundTicketFactory->TextBody();

                    $ticket = $this->ticketRepo->save($data, $ticket);

                    return $ticket;

                }

        }
        /**
         * If no valid hash exists - check if we can match via the custom local part.
         *
         * This could be a new support request!!
         */
        else
            return $this->checkSupportEmailAttempt();
    }

    /**
     * @return $ticket
     */
    private function checkSupportEmailAttempt()
    {

        $to = $this->inboundTicketFactory->to();

        /*
         *  parts = 'local_part' @ 'domain.com'
         */

        $parts = explode("@", $to);

        $accountTicketSettings = AccountTicketSettings::where('support_email_local_part', $parts[0])->first();

        /**
         * harvest the contact using the account and contact email address
         *
         * what happens if it is the agent who is firing back a reply
         * how do we process this?
         */

        $from = $this->inboundTicketFactory->fromEmail();

        if($accountTicketSettings) {
            $contacts = Contact::whereAccountId($accountTicketSettings->account_id)
                                ->whereEmail($from)->get();


            if(count($contacts) == 1)
                return $this->createTicket($accountTicketSettings->ticket_master, $contacts[0]);
            elseif(count($contacts) > 1)
            {
                /*
                Handle an edge case where one email address is registered across two different accounts.
                Need to handle this by creating a modified ticket without client/contact
                the contact email is stored in the contact_key field and a range of clients are harvested for selection when the
                ticket master views the ticket
                */
                return $this->createClientlessTicket($accountTicketSettings->ticket_master, $from, $accountTicketSettings->account);
            }
            elseif(count($contacts) == 0)
            {

                /** Could be an internal user? */
                $user = User::whereEmail($from)->first();

                if($user)
                    return $this->createInternalTicket($accountTicketSettings->ticket_master, $user, $accountTicketSettings->account);
            }
            else {

                Log::error('No contacts with this email address are registered in the system - '.$from);
                return null;

            }
        }

    }

    /**
     * @param $user
     * @param $contact
     * @return $ticket
     */
    private function createTicket($user, $contact)
    {

        $data = [
            'client_id' => $contact->client_id,
            'contact_key' => $contact->contact_key,
            //'agent_id' => $user->id,
            'priority_id' => TICKET_PRIORITY_LOW,
            'status_id' => TICKET_STATUS_NEW,
            'category_id' => 1,
            'subject' => $this->inboundTicketFactory->subject(),
            'description' => $this->inboundTicketFactory->TextBody(),
            'action' => TICKET_INBOUND_NEW,
            'is_internal' => 0,
        ];

            return $this->ticketRepo->save($data, null, $user);

    }

    /**
     * @param $user
     * @param $contactEmail
     * @param $account
     * @return $ticket
     */
    private function createClientlessTicket($user, $contactEmail, $account)
    {

        $data = [
            'contact_key' => $contactEmail,
            //'agent_id' => $user->id,
            'priority_id' => TICKET_PRIORITY_LOW,
            'status_id' => TICKET_STATUS_NEW,
            'category_id' => 1,
            'subject' => $this->inboundTicketFactory->subject(),
            'description' => $this->inboundTicketFactory->TextBody(),
            'action' => TICKET_INBOUND_NEW,
            'is_internal' => 0,
        ];

        return $this->ticketRepo->save($data, null, $user);

    }

    private function createInternalTicket($ticketMaster, $user, $account)
    {

        $data = [
            'user_id' => $ticketMaster->id,
            'is_internal' => 1,
            'agent_id' => $user->id,
            'priority_id' => TICKET_PRIORITY_LOW,
            'status_id' => TICKET_STATUS_NEW,
            'category_id' => 1,
            'subject' => $this->inboundTicketFactory->subject(),
            'description' => $this->inboundTicketFactory->TextBody(),
            'action' => TICKET_INBOUND_NEW,
        ];

        return $this->ticketRepo->save($data, null, $user);

    }

}