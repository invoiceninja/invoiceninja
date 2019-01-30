<?php

namespace App\Ninja\Repositories;

use App\Models\ProposalSnippet;
use App\Models\ProposalCategory;
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
                ->leftjoin('proposal_categories', 'proposal_categories.id', '=', 'proposal_snippets.proposal_category_id')
                ->where('proposal_snippets.account_id', '=', Auth::user()->account_id)
                ->select(
                    'proposal_snippets.name',
                    'proposal_snippets.public_id',
                    'proposal_snippets.user_id',
                    'proposal_snippets.deleted_at',
                    'proposal_snippets.is_deleted',
                    'proposal_snippets.icon',
                    'proposal_snippets.private_notes',
                    'proposal_snippets.html as content',
                    'proposal_categories.name as category',
                    'proposal_categories.public_id as category_public_id',
                    'proposal_categories.user_id as category_user_id'
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
