<?php namespace App\Ninja\Repositories;

use DB;
use Auth;
use Utils;
use Request;
use App\Models\Activity;
use App\Models\Client;

class ActivityRepository
{
    public function create($entity, $activityTypeId, $balanceChange = 0, $paidToDateChange = 0, $altEntity = null)
    {
        if ($entity instanceof Client) {
            $client = $entity;
        } elseif ($entity instanceof Invitation) {
            $client = $entity->invoice->client;
        } else {
            $client = $entity->client;
        }

        // init activity and copy over context
        $activity = self::getBlank($altEntity ?: $client);
        $activity = Utils::copyContext($activity, $entity);
        $activity = Utils::copyContext($activity, $altEntity);

        $activity->client_id = $client->id;
        $activity->activity_type_id = $activityTypeId;
        $activity->adjustment = $balanceChange;
        $activity->balance = $client->balance + $balanceChange;

        $keyField = $entity->getKeyField();
        $activity->$keyField = $entity->id;

        $activity->ip = Request::getClientIp();
        $activity->save();

        $client->updateBalances($balanceChange, $paidToDateChange);

        return $activity;
    }

    private function getBlank($entity)
    {
        $activity = new Activity();

        if (Auth::check() && Auth::user()->account_id == $entity->account_id) {
            $activity->user_id = Auth::user()->id;
            $activity->account_id = Auth::user()->account_id;
        } else {
            $activity->user_id = $entity->user_id;
            $activity->account_id = $entity->account_id;

            if ( ! $entity instanceof Invitation) {
                $activity->is_system = true;
            }
        }

        $activity->token_id = session('token_id');

        return $activity;
    }

    public function findByClientId($clientId)
    {
        return DB::table('activities')
                    ->join('accounts', 'accounts.id', '=', 'activities.account_id')
                    ->join('users', 'users.id', '=', 'activities.user_id')
                    ->join('clients', 'clients.id', '=', 'activities.client_id')
                    ->leftJoin('contacts', 'contacts.client_id', '=', 'clients.id')
                    ->leftJoin('invoices', 'invoices.id', '=', 'activities.invoice_id')
                    ->leftJoin('payments', 'payments.id', '=', 'activities.payment_id')
                    ->leftJoin('credits', 'credits.id', '=', 'activities.credit_id')
                    ->where('clients.id', '=', $clientId)
                    ->where('contacts.is_primary', '=', 1)
                    ->whereNull('contacts.deleted_at')
                    ->select(
                        DB::raw('COALESCE(clients.currency_id, accounts.currency_id) currency_id'),
                        DB::raw('COALESCE(clients.country_id, accounts.country_id) country_id'),
                        'activities.id',
                        'activities.created_at',
                        'activities.contact_id',
                        'activities.activity_type_id',
                        'activities.is_system',
                        'activities.balance',
                        'activities.adjustment',
                        'users.first_name as user_first_name',
                        'users.last_name as user_last_name',
                        'users.email as user_email',
                        'invoices.invoice_number as invoice',
                        'invoices.public_id as invoice_public_id',
                        'invoices.is_recurring',
                        'clients.name as client_name',
                        'clients.public_id as client_public_id',
                        'contacts.id as contact',
                        'contacts.first_name as first_name',
                        'contacts.last_name as last_name',
                        'contacts.email as email',
                        'payments.transaction_reference as payment',
                        'credits.amount as credit'
                    );
    }

}