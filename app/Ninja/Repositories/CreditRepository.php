<?php namespace App\Ninja\Repositories;

use App\Models\Credit;
use App\Models\Client;
use Utils;

class CreditRepository
{
    public function find($clientPublicId = null, $filter = null)
    {
        $query = \DB::table('credits')
                    ->join('clients', 'clients.id', '=', 'credits.client_id')
                    ->join('contacts', 'contacts.client_id', '=', 'clients.id')
                    ->where('clients.account_id', '=', \Auth::user()->account_id)
                    ->where('clients.deleted_at', '=', null)
                    ->where('contacts.is_primary', '=', true)
                    ->select('credits.public_id', 'clients.name as client_name', 'clients.public_id as client_public_id', 'credits.amount', 'credits.balance', 'credits.credit_date', 'clients.currency_id', 'contacts.first_name', 'contacts.last_name', 'contacts.email', 'credits.private_notes', 'credits.deleted_at', 'credits.is_deleted');

        if ($clientPublicId) {
            $query->where('clients.public_id', '=', $clientPublicId);
        }

        if (!\Session::get('show_trash:credit')) {
            $query->where('credits.deleted_at', '=', null);
        }

        if ($filter) {
            $query->where(function ($query) use ($filter) {
                $query->where('clients.name', 'like', '%'.$filter.'%');
            });
        }

        return $query;
    }

    public function save($publicId = null, $input)
    {
        if ($publicId) {
            $credit = Credit::scope($publicId)->firstOrFail();
        } else {
            $credit = Credit::createNew();
        }

        $credit->client_id = Client::getPrivateId($input['client']);
        $credit->credit_date = Utils::toSqlDate($input['credit_date']);
        $credit->amount = Utils::parseFloat($input['amount']);
        $credit->balance = Utils::parseFloat($input['amount']);
        $credit->private_notes = trim($input['private_notes']);
        $credit->save();

        return $credit;
    }

    public function bulk($ids, $action)
    {
        if (!$ids) {
            return 0;
        }

        $credits = Credit::withTrashed()->scope($ids)->get();

        foreach ($credits as $credit) {
            if ($action == 'restore') {
                $credit->restore();
            } else {
                if ($action == 'delete') {
                    $credit->is_deleted = true;
                    $credit->save();
                }

                $credit->delete();
            }
        }

        return count($credits);
    }
}
