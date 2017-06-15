<?php

namespace App\Ninja\Repositories;

use App\Models\Client;
use App\Models\Credit;
use DB;
use Utils;

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
                        'credits.credit_date as credit_date_sql',
                        DB::raw("CONCAT(credits.credit_date, credits.created_at) as credit_date"),
                        'contacts.first_name',
                        'contacts.last_name',
                        'contacts.email',
                        'credits.private_notes',
                        'credits.public_notes',
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
                    ->select(
                        DB::raw('COALESCE(clients.currency_id, accounts.currency_id) currency_id'),
                        DB::raw('COALESCE(clients.country_id, accounts.country_id) country_id'),
                        'credits.amount',
                        'credits.balance',
                        'credits.credit_date',
                        'credits.public_notes'
                    );

        $table = \Datatable::query($query)
            ->addColumn('credit_date', function ($model) {
                return Utils::fromSqlDate($model->credit_date);
            })
            ->addColumn('amount', function ($model) {
                return Utils::formatMoney($model->amount, $model->currency_id, $model->country_id);
            })
            ->addColumn('balance', function ($model) {
                return Utils::formatMoney($model->balance, $model->currency_id, $model->country_id);
            })
            ->addColumn('public_notes', function ($model) {
                return $model->public_notes;
            })
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
            $credit->balance = Utils::parseFloat($input['amount']);
            $credit->client_id = Client::getPrivateId($input['client_id']);
            $credit->credit_date = date('Y-m-d');
        }

        $credit->fill($input);

        if (isset($input['credit_date'])) {
            $credit->credit_date = Utils::toSqlDate($input['credit_date']);
        }
        if (isset($input['amount'])) {
            $credit->amount = Utils::parseFloat($input['amount']);
        }
        if (isset($input['balance'])) {
            $credit->balance = Utils::parseFloat($input['balance']);
        }

        $credit->save();

        return $credit;
    }
}
