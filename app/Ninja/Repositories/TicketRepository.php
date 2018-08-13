<?php

namespace App\Ninja\Repositories;

use App\Jobs\Ticket\TicketDelta;
use App\Models\Contact;
use App\Models\Document;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\TicketInvitation;
use App\Models\TicketStatus;
use App\Models\User;
use Auth;
use DB;
use Illuminate\Support\Facades\Log;
use Utils;
use Illuminate\Foundation\Bus\DispatchesJobs;

class TicketRepository extends BaseRepository
{

    use DispatchesJobs;

    public function getClassName()
    {
        return 'App\Models\Ticket';
    }

    public function all()
    {
        return Ticket::scope()->get();
    }

    public function find($filter = null, $userId = false)
    {

        $query = DB::table('tickets')
            ->where('tickets.account_id', '=', Auth::user()->account_id)
            ->leftjoin('clients', 'clients.id', '=', 'tickets.client_id')
            ->leftJoin('contacts', 'contacts.client_id', '=', 'clients.id')
            ->leftJoin('ticket_statuses', 'ticket_statuses.id', '=', 'tickets.status_id')
            //->where('tickets.is_deleted', '=', false)
            ->where('clients.deleted_at', '=', null)
            ->where('contacts.deleted_at', '=', null)
            ->where('contacts.is_primary', '=', true)
            ->select(
                'tickets.ticket_number',
                'tickets.public_id',
                'tickets.user_id',
                'tickets.deleted_at',
                'tickets.created_at',
                'tickets.is_deleted',
                'tickets.private_notes',
                'tickets.subject',
                'ticket_statuses.name as status',
                'tickets.contact_key',
                'tickets.merged_parent_ticket_id',
                DB::raw("COALESCE(NULLIF(clients.name,''), NULLIF(CONCAT(contacts.first_name, ' ', contacts.last_name),''), NULLIF(contacts.email,'')) client_name"),
                'clients.user_id as client_user_id',
                'clients.public_id as client_public_id'
            );

        $this->applyFilters($query, ENTITY_TICKET);

        if ($filter) {
            $query->where(function ($query) use ($filter) {
                $query->where('clients.name', 'like', '%'.$filter.'%')
                      ->orWhere('contacts.first_name', 'like', '%'.$filter.'%')
                      ->orWhere('contacts.last_name', 'like', '%'.$filter.'%')
                      ->orWhere('contacts.email', 'like', '%'.$filter.'%');
            });
        }

        if ($userId) {
            $query->where('tickets.user_id', '=', $userId);
        }

        if(!Auth::user()->can('view', ENTITY_TICKET))
            $query->where('tickets.agent_id', '=', Auth::user()->id);


        return $query;
    }

    public function save($input, $ticket = false)
    {
        $contact = false;
        $oldTicket = $ticket;
        if(Auth::user())
            $user = Auth::user();
        elseif($contact = Contact::getContactIfLoggedIn())
            $user = User::where('id', '=', $contact->account->account_ticket_settings->ticket_master_id)->first();

        if (! $ticket) {

            if($contact) {
                //if client is creating the ticket, we need to harvest the ticket_master_user
                $ticket = Ticket::createNew($user);
                $ticket->client_id = $contact->client_id;
                $ticket->agent_id = $user->id;
                $ticket->ticket_number = Ticket::getNextTicketNumber($contact->account->id);
                $ticket->priority_id = TICKET_PRIORITY_LOW;
            }
            else
                $ticket = Ticket::createNew();
        }

        $ticket->fill($input);
        $changedAttributes = $ticket->getDirty();
        $ticket->save();

        $this->dispatch(new TicketDelta($changedAttributes, $oldTicket, $ticket));


        /* handle new comment */
        if(isset($input['description']) && strlen($input['description']) >=1) {
            $ticketComment = TicketComment::createNew($ticket);
            $ticketComment->description = $input['description'];

            if(!Auth::user())
                $ticketComment->contact_key = $input['contact_key'];

            $ticket->comments()->save($ticketComment);

        }

        /* if document IDs exist update ticket_id in document table */
        if (! empty($input['document_ids'])) {
            $document_ids = array_map('intval', $input['document_ids']);

            foreach ($document_ids as $document_id) {
                $document = Document::scope($document_id, $ticket->account_id)->first();
                if ($document) {
                //    if ($document && Auth::user()->can('edit', $document)) {

                    $document->ticket_id = $ticket->id;
                    $document->save();
                }
            }
        }

        //ticket invitations - create if none exists for primary contact
        $found = false;

        foreach($ticket->invitations as $invite) {

            if($invite->contact_id == $ticket->contact->id)
                $found = true;

        }

        if (! $found)
            $this->createTicketInvite($ticket, $ticket->contact->id, $user);


        /*
         * iterate through ticket ccs and ensure an invite exists for ticket CC's - todo v2.0

            foreach(explode(",", $ticket->ccs) as $ccKey) {

            $contact = Contact::where('contact_key', '=', $ccKey)->first();

            if($contact->id)
            }
        */

        return $ticket;
    }

    private function createTicketInvite($ticket, $contactId, $user) {

        $ticketInvitation = TicketInvitation::createNew($user);

        $ticketInvitation->ticket_id = $ticket->id;
        $ticketInvitation->contact_id = $contactId;
        $ticketInvitation->invitation_key = strtolower(str_random(RANDOM_KEY_LENGTH));
        $ticketInvitation->ticket_hash = strtolower(str_random(RANDOM_KEY_LENGTH));
        $ticketInvitation->save();

    }

    /**
     * @param $invitationKey
     *
     * @return Invitation|bool
     */
    public function findInvitationByKey($invitationKey)
    {
        // check for extra params at end of value (from website feature)
        list($invitationKey) = explode('&', $invitationKey);
        $invitationKey = substr($invitationKey, 0, RANDOM_KEY_LENGTH);

        /** @var \App\Models\Invitation $invitation */
        $invitation = TicketInvitation::where('invitation_key', '=', $invitationKey)->first();
        if (! $invitation) {
            return false;
        }

        $ticket = $invitation->ticket;

        if (! $ticket || $ticket->is_deleted)
            return false;


        $client = $ticket->client;

        if (! $client || $client->is_deleted)
            return false;

        return $invitation;
    }

}
