<?php

namespace App\Ninja\Repositories;

use App\Models\TicketStatus;
use Auth;
use DB;
use Utils;

class TicketStatusRepository extends BaseRepository
{
    public function getClassName()
    {
        return 'App\Models\TicketStatus';
    }

    public function all()
    {
        return TicketStatus::scope()->get();
    }

    public function find($filter = null, $userId = false)
    {
        $query = DB::table('ticket_statuses')
                ->where('ticket_statuses.account_id', '=', Auth::user()->account_id)
                ->leftjoin('clients', 'clients.id', '=', 'ticket_statuses.client_id')
                ->leftJoin('contacts', 'contacts.client_id', '=', 'clients.id')
                ->where('clients.deleted_at', '=', null)
                ->where('contacts.deleted_at', '=', null)
                ->where('contacts.is_primary', '=', true);

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
            $query->where('ticket_statuses.user_id', '=', $userId);
        }

        return $query;
    }

    public function save($input, $ticketStatus = false)
    {
        if (! $ticketStatus) {
            $ticketStatus = TicketStatus::createNew();
        }

        $ticketStatus->fill($input);

        $ticketStatus->save();

        return $ticketStatus;
    }

}
