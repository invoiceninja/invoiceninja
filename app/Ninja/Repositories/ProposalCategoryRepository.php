<?php

namespace App\Ninja\Repositories;

use App\Models\ProposalCategory;
use Auth;
use DB;
use Utils;

class ProposalCategoryRepository extends BaseRepository
{
    public function getClassName()
    {
        return 'App\Models\ProposalCategory';
    }

    public function all()
    {
        return ProposalCategory::scope()->get();
    }

    public function find($filter = null, $userId = false)
    {
        $query = DB::table('proposal_categories')
                ->where('proposal_categories.account_id', '=', Auth::user()->account_id)
                ->leftjoin('invoices', 'invoices.id', '=', 'proposal_categories.quote_id')
                ->leftjoin('clients', 'clients.id', '=', 'invoices.client_id')
                ->leftJoin('contacts', 'contacts.client_id', '=', 'clients.id')
                ->where('clients.deleted_at', '=', null)
                ->where('contacts.deleted_at', '=', null)
                ->where('contacts.is_primary', '=', true)
                ->select(
                    'proposal_categories.name as proposal',
                    'proposal_categories.public_id',
                    'proposal_categories.user_id',
                    'proposal_categories.deleted_at',
                    'proposal_categories.is_deleted',
                    'proposal_categories.private_notes',
                    DB::raw("COALESCE(NULLIF(clients.name,''), NULLIF(CONCAT(contacts.first_name, ' ', contacts.last_name),''), NULLIF(contacts.email,'')) client_name"),
                    'clients.user_id as client_user_id',
                    'clients.public_id as client_public_id'
                );

        $this->applyFilters($query, ENTITY_PROPOSAL_CATEGORY);

        if ($filter) {
            $query->where(function ($query) use ($filter) {
                $query->where('clients.name', 'like', '%'.$filter.'%')
                      ->orWhere('contacts.first_name', 'like', '%'.$filter.'%')
                      ->orWhere('contacts.last_name', 'like', '%'.$filter.'%')
                      ->orWhere('contacts.email', 'like', '%'.$filter.'%')
                      ->orWhere('proposal_categories.name', 'like', '%'.$filter.'%');
            });
        }

        if ($userId) {
            $query->where('proposal_categories.user_id', '=', $userId);
        }

        return $query;
    }

    public function save($input, $proposal = false)
    {
        $publicId = isset($input['public_id']) ? $input['public_id'] : false;

        if (! $proposal) {
            $proposal = ProposalCategory::createNew();
        }

        $proposal->fill($input);
        $proposal->save();

        return $proposal;
    }
}
