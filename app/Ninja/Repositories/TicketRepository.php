<?php

namespace App\Ninja\Repositories;

use App\Jobs\Ticket\TicketAction;
use App\Models\Contact;
use App\Models\Document;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\TicketInvitation;
use App\Models\User;
use App\Ninja\Tickets\Actions\BaseTicketAction;
use Auth;
use DB;
use Illuminate\Support\Facades\Log;
use Utils;
use Illuminate\Foundation\Bus\DispatchesJobs;

/**
 * Class TicketRepository
 * @package App\Ninja\Repositories
 */
class TicketRepository extends BaseRepository
{

    use DispatchesJobs;

    /**
     * @return string
     */
    public function getClassName()
    {

        return 'App\Models\Ticket';

    }

    /**
     * @return mixed
     */
    public function all()
    {

        return Ticket::scope()->get();

    }

    /**
     * @param null $filter
     * @param bool $userId
     * @param string $entityType
     * @return mixed
     */
    public function find($filter = null, $userId = false, $entityType = ENTITY_TICKET)
    {

        $query = DB::table('tickets')
            ->where('tickets.account_id', '=', Auth::user()->account_id)
            ->leftJoin('clients', 'clients.id', '=', 'tickets.client_id')
            ->leftJoin('contacts', 'contacts.client_id', '=', 'clients.id')
            ->leftJoin('ticket_comments', function ($join) {
                $join->on('ticket_comments.ticket_id', '=', 'tickets.id');
                $join->where('ticket_comments.id', '=', DB::raw('(SELECT ticket_comments.id FROM ticket_comments where ticket_comments.ticket_id = tickets.id ORDER BY id DESC limit 1) '));
            })
            ->leftJoin('users', 'users.id', '=', 'tickets.agent_id')
            //->where('tickets.is_deleted', '=', false)
            ->where('clients.deleted_at', '=', null)
            ->where('contacts.deleted_at', '=', null)
            //->where('contacts.is_primary', '=', true)
            ->select(
                'ticket_comments.contact_key as lastContactByContactKey',
                'ticket_comments.id as commentId',
                'tickets.priority_id',
                'tickets.ticket_number',
                'tickets.due_date',
                'tickets.public_id',
                'tickets.agent_id',
                'tickets.client_id',
                'tickets.user_id',
                'tickets.deleted_at',
                'tickets.created_at',
                'tickets.is_deleted',
                'tickets.is_internal',
                'tickets.status_id',
                'tickets.private_notes',
                'tickets.subject',
                'tickets.contact_key',
                'tickets.merged_parent_ticket_id',
                DB::raw("COALESCE(NULLIF(clients.name,''), NULLIF(CONCAT(contacts.first_name, ' ', contacts.last_name),''), NULLIF(contacts.email,'')) client_name"),
                DB::raw("COALESCE(NULLIF(CONCAT(contacts.first_name, ' ', contacts.last_name), '')) contact_name"),
                DB::raw("COALESCE(NULLIF(clients.user_id,'')) client_user_id"),
                DB::raw("COALESCE(NULLIF(clients.public_id,'')) client_public_id"),
                DB::raw("NULLIF(CONCAT(users.first_name, ' ', users.last_name),'') agent_name")

            );

        $this->applyFilters($query, ENTITY_TICKET);

        if ($statuses = session('entity_status_filter:' . $entityType)) {

            $statuses = explode(',', $statuses);

            $query->where(function ($query) use ($statuses) {

                foreach ($statuses as $status) {

                    if (in_array($status, \App\Models\EntityModel::$statuses))
                        continue;

                    $query->orWhere('status_id', '=', $status);

                }

            });

        }


        if ($filter) {

            $query->where(function ($query) use ($filter) {

                $query->where('clients.name', 'like', '%'.$filter.'%')
                    ->orWhere('contacts.first_name', 'like', '%'.$filter.'%')
                    ->orWhere('contacts.last_name', 'like', '%'.$filter.'%')
                    ->orWhere('contacts.email', 'like', '%'.$filter.'%');

            });

        }

        if ($userId) {

            $query->where('tickets.user_id', '=', $userId)
                ->orWhere('tickets.agent_id', '=', Auth::user()->id);

        }

        if(!Auth::user()->can('view', ENTITY_TICKET))
            $query->where('tickets.agent_id', '=', Auth::user()->id);

        return $query;

    }

    /**
     * @param $input
     * @param bool $ticket
     * @param bool $harvestedUser
     * @return bool|mixed
     * @throws \Exception
     */
    public function save($input, $ticket = false, $harvestedUser = false)
    {

        $contact = false;
        $oldTicket = $ticket;

        if(Auth::user())
            $user = Auth::user();
        elseif($contact = Contact::getContactIfLoggedIn())
            $user = User::where('id', '=', $contact->account->account_ticket_settings->ticket_master_id)->first();
        elseif($harvestedUser)
            $user = $harvestedUser;
        else
            throw new \Exception(trans('texts.forbidden'));

        if (! $ticket) {

            if($contact) {

                //if client is creating the ticket, we need to harvest the ticket_master_user
                $ticket = Ticket::createNew($user);
                $ticket->client_id = $contact->client_id;
                $ticket->contact_key = $contact->contact_key;
                $ticket->ticket_number = Ticket::getNextTicketNumber($contact->account->id);
                $ticket->priority_id = TICKET_PRIORITY_LOW;

            }
            else {

                $ticket = Ticket::createNew($user);
                $ticket->ticket_number = Ticket::getNextTicketNumber($user->account->id);

            }

        }

        if(isset($input['client_id']) && $input['client_id'] < 1) //handle edge case where client _can_ be nullable
            $input = array_except($input, array('client_id'));

        $ticket->fill($input);
        $changedAttributes = $ticket->getDirty();

        $ticket->save();

        /** handle new comment */
        if(isset($input['description']) && strlen($input['description']) >=1)
        {

            if($ticket)
                $input['description'] = Ticket::buildTicketBody($ticket, $input['description']);

            /** don't change the status if it is a new ticket */
            if($ticket->status_id == 1 && !in_array($input['action'],[TICKET_CLIENT_NEW, TICKET_AGENT_NEW]))
            {
                $ticket->status_id = 2;
                $ticket->save();
            }

            $ticketComment = TicketComment::createNew($ticket);
            $ticketComment->description = $input['description'];

            if(in_array($input['action'], [TICKET_INBOUND_CONTACT_REPLY, TICKET_CLIENT_UPDATE, TICKET_CLIENT_NEW]))
                $ticketComment->contact_key = $ticket->contact_key;
            elseif(in_array($input['action'], [TICKET_INBOUND_ADMIN_REPLY, TICKET_INBOUND_AGENT_REPLY, TICKET_AGENT_UPDATE, TICKET_AGENT_NEW]))
                $ticketComment->agent_id = $ticket->agent_id ? $ticket->agent_id : Auth::user()->id;

            $ticket->comments()->save($ticketComment);

        }

        /* if document IDs exist update ticket_id in document table */
        if (! empty($input['document_ids'])) {

            $document_ids = array_map('intval', $input['document_ids']);

            foreach ($document_ids as $document_id) {

                $document = Document::scope($document_id, $ticket->account_id)->first();

                if ($document) {

                    $document->ticket_id = $ticket->id;
                    $document->save();

                }

            }

        }

        /** ticket invitations - create if none exists for primary contact */
        $found = false;

        foreach($ticket->invitations as $invite) {

            if($invite->contact_id == $ticket->contact->id)
                $found = true;

        }

        if(!$found && isset($input['is_internal']) && !$input['is_internal'] && $ticket->contact) {
            $this->createTicketInvite($ticket, $ticket->contact->id, $user);
        }

        /**
         * iterate through ticket ccs and ensure an invite exists for ticket CC's - todo v2.0

        foreach(explode(",", $ticket->ccs) as $ccKey) {

        $contact = Contact::where('contact_key', '=', $ccKey)->first();

        if($contact->id)
        }
         */

        /**
         *
         * Once we have saved the $ticket to the datastore we need to perform
         * various tasks on the ticket. We pass the changed attributes along
         * with the old and new ticket.
         *
         * Included in the payload will be an ACTION variable to provide
         * context for the various workflows.
         */

        if($input['action'] != TICKET_SAVE_ONLY)
            dispatch_now(new TicketAction($changedAttributes, $oldTicket, $ticket, $input['action']));

        return $ticket;

    }

    /**
     * @param $ticket
     * @param $contactId
     * @param $user
     */
    private function createTicketInvite($ticket, $contactId, $user)
    {
        $ticketInvitation = TicketInvitation::createNew($user);
        $ticketInvitation->ticket_id = $ticket->id;
        $ticketInvitation->contact_id = $contactId;
        $ticketInvitation->invitation_key = strtolower(str_random(RANDOM_KEY_LENGTH));
        $ticketInvitation->ticket_hash = strtolower(str_random(RANDOM_KEY_LENGTH));
        $ticketInvitation->save();
    }

    /**
     * @param $invitationKey
     * @return Invitation|bool
     */
    public function findInvitationByKey($invitationKey)
    {
        // check for extra params at end of value (from website feature)
        list($invitationKey) = explode('&', $invitationKey);

        $invitationKey = substr($invitationKey, 0, RANDOM_KEY_LENGTH);

        $invitation = TicketInvitation::where('invitation_key', '=', $invitationKey)->first();

        if (! $invitation)
            return false;

        $ticket = $invitation->ticket;

        if (! $ticket || $ticket->is_deleted)
            return false;

        $client = $ticket->client;

        if (! $client || $client->is_deleted)
            return false;

        return $invitation;

    }

}