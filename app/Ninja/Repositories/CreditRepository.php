<?php namespace App\Ninja\Repositories;

use DB;
use Utils;
use App\Models\Credit;
use App\Models\Client;

class CreditRepository extends BaseRepository
{
    public function getClassName()
    {
        return 'App\Models\Credit';
    }

    public function find($clientPublicId = null, $filter = null)
    {
        $query = DB::table('credits')
                    ->join('accounts', 'accounts.id', '=', 'credits.account_id')
                    ->join('clients', 'clients.id', '=', 'credits.client_id')
                    ->join('contacts', 'contacts.client_id', '=', 'clients.id')
                    ->where('clients.account_id', '=', \Auth::user()->account_id)
                    ->where('contacts.deleted_at', '=', null)
                    ->where('contacts.is_primary', '=', true)
                    ->select(
                        DB::raw('COALESCE(clients.currency_id, accounts.currency_id) currency_id'),
                        DB::raw('COALESCE(clients.country_id, accounts.country_id) country_id'),
                        'credits.public_id',
                        DB::raw("COALESCE(NULLIF(clients.name,''), NULLIF(CONCAT(contacts.first_name, ' ', contacts.last_name),''), NULLIF(contacts.email,'')) client_name"),
                        'clients.public_id as client_public_id',
                        'clients.user_id as client_user_id',
                        'credits.amount',
                        'credits.balance',
                        'credits.credit_date',
                        'contacts.first_name',
                        'contacts.last_name',
                        'contacts.email',
                        'credits.private_notes',
                        'credits.deleted_at',
                        'credits.is_deleted',
                        'credits.user_id'
                    );

        if ($clientPublicId) {
            $query->where('clients.public_id', '=', $clientPublicId);
        } else {
            $query->whereNull('clients.deleted_at');
        }

        $this->applyFilters($query, ENTITY_CREDIT);

        if ($filter) {
            $query->where(function ($query) use ($filter) {
                $query->where('clients.name', 'like', '%'.$filter.'%');
            });
        }

        return $query;
    }

    public function getClientDatatable($clientId)
    {
        $query = DB::table('credits')
                    ->join('accounts', 'accounts.id', '=', 'credits.account_id')
                    ->join('clients', 'clients.id', '=', 'credits.client_id')
                    ->where('credits.client_id', '=', $clientId)
                    ->where('clients.deleted_at', '=', null)
                    ->where('credits.deleted_at', '=', null)
                    ->where('credits.balance', '>', 0)
                    ->select(
                        DB::raw('COALESCE(clients.currency_id, accounts.currency_id) currency_id'),
                        DB::raw('COALESCE(clients.country_id, accounts.country_id) country_id'),
                        'credits.amount',
                        'credits.balance',
                        'credits.credit_date'
                    );

        $table = \Datatable::query($query)
            ->addColumn('credit_date', function ($model) { return Utils::fromSqlDate($model->credit_date); })
            ->addColumn('amount', function ($model) { return Utils::formatMoney($model->amount, $model->currency_id, $model->country_id); })
            ->addColumn('balance', function ($model) { return Utils::formatMoney($model->balance, $model->currency_id, $model->country_id); })
            ->make();

        return $table;
    }

    public function save($input, $credit = null)
    {
        $publicId = isset($data['public_id']) ? $data['public_id'] : false;

        if ($credit) {
            // do nothing
        } elseif ($publicId) {
            $credit = Credit::scope($publicId)->firstOrFail();
            \Log::warning('Entity not set in credit repo save');
        } else {
            $credit = Credit::createNew();
            $credit->client_id = Client::getPrivateId($input['client']);
        }

        $credit->credit_date = Utils::toSqlDate($input['credit_date']);
        $credit->amount = Utils::parseFloat($input['amount']);
        $credit->balance = Utils::parseFloat($input['amount']);
        $credit->private_notes = trim($input['private_notes']);
        $credit->save();

        return $credit;
    }
}
