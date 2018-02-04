<?php

namespace App\Ninja\Repositories;

use App\Models\Proposal;
use App\Models\Invoice;
use App\Models\ProposalTemplate;
use Auth;
use DB;
use Utils;

class ProposalRepository extends BaseRepository
{
    public function getClassName()
    {
        return 'App\Models\Proposal';
    }

    public function all()
    {
        return Proposal::scope()->get();
    }

    public function find($filter = null, $userId = false)
    {
        $query = DB::table('proposals')
                ->where('proposals.account_id', '=', Auth::user()->account_id)
                ->leftjoin('invoices', 'invoices.id', '=', 'proposals.quote_id')
                ->leftjoin('clients', 'clients.id', '=', 'invoices.client_id')
                ->leftJoin('contacts', 'contacts.client_id', '=', 'clients.id')
                ->leftJoin('proposal_templates', 'proposal_templates.id', '=', 'proposals.proposal_template_id')
                ->where('clients.deleted_at', '=', null)
                ->where('contacts.deleted_at', '=', null)
                ->where('contacts.is_primary', '=', true)
                ->select(
                    'proposals.public_id',
                    'proposals.user_id',
                    'proposals.deleted_at',
                    'proposals.created_at',
                    'proposals.is_deleted',
                    'proposals.private_notes',
                    DB::raw("COALESCE(NULLIF(clients.name,''), NULLIF(CONCAT(contacts.first_name, ' ', contacts.last_name),''), NULLIF(contacts.email,'')) client_name"),
                    'clients.user_id as client_user_id',
                    'clients.public_id as client_public_id',
                    'invoices.invoice_number as quote',
                    'invoices.invoice_number as quote_number',
                    'invoices.public_id as quote_public_id',
                    'invoices.user_id as quote_user_id',
                    'proposal_templates.name as template',
                    'proposal_templates.public_id as template_public_id',
                    'proposal_templates.user_id as template_user_id'
                );

        $this->applyFilters($query, ENTITY_PROPOSAL);

        if ($filter) {
            $query->where(function ($query) use ($filter) {
                $query->where('clients.name', 'like', '%'.$filter.'%')
                      ->orWhere('contacts.first_name', 'like', '%'.$filter.'%')
                      ->orWhere('contacts.last_name', 'like', '%'.$filter.'%')
                      ->orWhere('contacts.email', 'like', '%'.$filter.'%')
                      ->orWhere('invoices.invoice_number', 'like', '%'.$filter.'%');
            });
        }

        if ($userId) {
            $query->where('proposals.user_id', '=', $userId);
        }

        return $query;
    }

    public function save($input, $proposal = false)
    {
        if (! $proposal) {
            $proposal = Proposal::createNew();
        }

        $proposal->fill($input);

        if (isset($input['quote_id'])) {
            $proposal->quote_id = $input['quote_id'] ? Invoice::getPrivateId($input['quote_id']) : null;
        }

        if (isset($input['proposal_template_id'])) {
            $proposal->proposal_template_id = $input['proposal_template_id'] ? ProposalTemplate::getPrivateId($input['proposal_template_id']) : null;
        }

        $proposal->save();

        return $proposal;
    }
}
