<?php

namespace App\Ninja\Tickets\Inbound;

use App\Models\AccountTicketSettings;
use App\Models\Contact;
use App\Models\Ticket;
use App\Models\TicketInvitation;
use App\Models\User;
use App\Ninja\Repositories\DocumentRepository;
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
            $user = null;

            $ticketInvitation = TicketInvitation::whereTicketHash($ticket_hash)->first();

            if($ticketInvitation)
            {

                /** Contact based external ticket */
                $ticket = $ticketInvitation->ticket;
                $user = $ticket->user;
                $data['action'] = $this->getSender($ticket);
                $data['status_id'] = TICKET_STATUS_OPEN;
                $data['is_internal'] = 0;

                    return $this->processTicket($ticket, $data, $user);

            }

            /** Checking for Internal support reply */
            $explodeEmail = explode("@", $this->inboundTicketFactory->to());
            $explodeLocalPart = explode("+", $explodeEmail[0]); //ticket_number

            $localPart = $explodeLocalPart[0];
            $ticket_number = $this->inboundTicketFactory->mailboxHash();

            $accountTicketSettings = AccountTicketSettings::where('support_email_local_part', '=', $localPart)->first();

            if(!$accountTicketSettings)
                return;

            $ticket = Ticket::whereAccountId($accountTicketSettings->account_id)
                    ->whereTicketNumber($ticket_number)->first();


            if ($ticket)
            {

                /** Internal Ticket*/
                $user = $ticket->user;
                $data['is_internal'] = 1;
                $data['status_id'] = TICKET_STATUS_OPEN;
                $data['action'] = $this->getSender($ticket);

                    return $this->processTicket($ticket, $data, $user);
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

    private function processTicket(Ticket $ticket, array $data, $user) : Ticket
    {

        $data['description'] = $this->getMessage();

        foreach($this->inboundTicketFactory->attachments() as $attachment)
        {
            Log::error('inside attachments');
            Log::error('file name = '.$attachment->Name);
            //Log::error('file content = '.$attachment->Content);
            //Log::error('file encoded' .base64_encode(chunk_split($attachment->Content)));

            $doc = [];
            $doc['file'] = $attachment->download();
            $doc['filePath'] = sys_get_temp_dir().$attachment->Name;
            $doc['fileName'] = $attachment->Name;
            $doc['ticket_id'] = $ticket->id;
            $doc['user_id'] = $ticket->user_id;


            $documentRepo = new DocumentRepository();
            $documentRepo->inboundUpload($doc, $user->account);

        }

        $ticket = $this->ticketRepo->save($data, $ticket, $user);

        return $ticket;
    }

    private function getSender(Ticket $ticket) : string
    {
        if ($ticket->contact_key && $ticket->contact && ($ticket->contact->email == $this->inboundTicketFactory->fromEmail()))
            return INBOUND_CONTACT_REPLY;
        elseif($ticket->agent_id && $ticket->agent && ($ticket->agent->email == $this->inboundTicketFactory->fromEmail()))
            return INBOUND_AGENT_REPLY;
        elseif($ticket->user_id && $ticket->user && ($ticket->user->email == $this->inboundTicketFactory->fromEmail()))
            return INBOUND_ADMIN_REPLY;

    }

    /**
     * returns nothing or a $ticket
     * cannot define a nullable return type until we support PHP7.1
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
         */

        $from = $this->inboundTicketFactory->fromEmail();

        if($accountTicketSettings) {

            $contacts = Contact::whereAccountId($accountTicketSettings->account_id)
                                ->whereEmail($from)->get();


            if(count($contacts) == 1 && ($accountTicketSettings->allow_inbound_email_tickets_external == true)) {

                /**
                 * Most use cases will hit the following. A single contact sending in a support ticket request.
                 */

                return $this->createTicket($accountTicketSettings->ticket_master, $contacts[0]);

            }
            elseif(count($contacts) > 1 && ($accountTicketSettings->allow_inbound_email_tickets_external == true))
            {
                /**
                * Handle an edge case where one email address is registered across two different accounts.
                * Need to handle this by creating a modified ticket without client/contact
                * the contact email is stored in the contact_key field and a range of clients are harvested for selection when the
                * ticket master views the ticket
                */
                return $this->createClientlessTicket($accountTicketSettings->ticket_master, $from, $accountTicketSettings->account);
            }
            elseif(count($contacts) == 0)
            {
                /**
                 * No contacts found, new internal tickets not able to be created via email,so we die.
                 */

            }
            else {

                Log::info('No contacts or users with this email address are registered in the system - '.$from);
                return null;

            }
        }

    }

    /**
     * @param $user
     * @param $contact
     * @return $ticket
     */
    private function createTicket($user, $contact) : Ticket
    {

        $data = [
            'client_id' => $contact->client_id,
            'contact_key' => $contact->contact_key,
            //'agent_id' => $user->id,
            'priority_id' => TICKET_PRIORITY_LOW,
            'status_id' => TICKET_STATUS_NEW,
            'category_id' => 1,
            'subject' => $this->inboundTicketFactory->subject(),
            'description' => $this->getMessage(),
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
    private function createClientlessTicket($user, $contactEmail, $account) : Ticket
    {

        $data = [
            'contact_key' => $contactEmail,
            //'agent_id' => $user->id,
            'priority_id' => TICKET_PRIORITY_LOW,
            'status_id' => TICKET_STATUS_NEW,
            'category_id' => 1,
            'subject' => $this->inboundTicketFactory->subject(),
            'description' => $this->getMessage(),
            'action' => TICKET_SAVE_ONLY, //we cant send a ticket to someone we don't know!!
            'is_internal' => 0,
        ];

        return $this->ticketRepo->save($data, null, $user);

    }
/*
    private function createInternalTicket($accountTicketSettings, $user) : Ticket
    {
        $agentId = 0;

        if($accountTicketSettings->default_agent_id > 0)
            $agentId = $accountTicketSettings->default_agent_id;

        $data = [
            'user_id' => $user->id,
            'is_internal' => 1,
            'agent_id' => $agentId,
            'priority_id' => TICKET_PRIORITY_LOW,
            'status_id' => TICKET_STATUS_NEW,
            'category_id' => 1,
            'subject' => $this->inboundTicketFactory->subject(),
            'description' => $this->getMessage(),
            'action' => TICKET_INBOUND_NEW_INTERNAL,
        ];


        Log::error('createinternal ticket description = '.$this->inboundTicketFactory->StrippedTextReply());
        return $this->ticketRepo->save($data, null, $user);

    }
*/
    private function getMessage() : string {

        if(strlen($this->inboundTicketFactory->StrippedTextReply()) > 0)
            return $this->inboundTicketFactory->StrippedTextReply();
        else
            return $this->inboundTicketFactory->TextBody();
    }
}