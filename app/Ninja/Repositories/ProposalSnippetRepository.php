<?php

namespace App\Ninja\Repositories;

use App\Models\ProposalSnippet;
use Auth;
use DB;
use Utils;

class ProposalSnippetRepository extends BaseRepository
{
    public function getClassName()
    {
        return 'App\Models\ProposalSnippet';
    }

    public function all()
    {
        return ProposalSnippet::scope()->get();
    }

    public function find($filter = null, $userId = false)
    {
        $query = DB::table('proposal_snippets')
                ->where('proposal_snippets.account_id', '=', Auth::user()->account_id)
                ->leftjoin('invoices', 'invoices.id', '=', 'proposal_snippets.quote_id')
                ->leftjoin('clients', 'clients.id', '=', 'invoices.client_id')
                ->leftJoin('contacts', 'contacts.client_id', '=', 'clients.id')
                ->where('clients.deleted_at', '=', null)
                ->where('contacts.deleted_at', '=', null)
                ->where('contacts.is_primary', '=', true)
                ->select(
                    'proposal_snippets.name as proposal',
                    'proposal_snippets.public_id',
                    'proposal_snippets.user_id',
                    'proposal_snippets.deleted_at',
                    'proposal_snippets.is_deleted',
                    'proposal_snippets.private_notes',
                    DB::raw("COALESCE(NULLIF(clients.name,''), NULLIF(CONCAT(contacts.first_name, ' ', contacts.last_name),''), NULLIF(contacts.email,'')) client_name"),
                    'clients.user_id as client_user_id',
                    'clients.public_id as client_public_id'
                );

        $this->applyFilters($query, ENTITY_PROPOSAL_SNIPPET);

        if ($filter) {
            $query->where(function ($query) use ($filter) {
                $query->where('clients.name', 'like', '%'.$filter.'%')
                      ->orWhere('contacts.first_name', 'like', '%'.$filter.'%')
                      ->orWhere('contacts.last_name', 'like', '%'.$filter.'%')
                      ->orWhere('contacts.email', 'like', '%'.$filter.'%')
                      ->orWhere('proposal_snippets.name', 'like', '%'.$filter.'%');
            });
        }

        if ($userId) {
            $query->where('proposal_snippets.user_id', '=', $userId);
        }

        return $query;
    }

    public function save($input, $proposal = false)
    {
        $publicId = isset($data['public_id']) ? $data['public_id'] : false;

        if (! $proposal) {
            $proposal = ProposalSnippet::createNew();
        }

        $proposal->fill($input);
        $proposal->save();

        return $proposal;
    }
}
