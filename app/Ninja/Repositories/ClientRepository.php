<?php namespace App\Ninja\Repositories;

use DB;
use Cache;
use App\Ninja\Repositories\BaseRepository;
use App\Models\Client;
use App\Models\Contact;
use App\Models\Activity;
use App\Events\ClientWasCreated;
use App\Events\ClientWasUpdated;

class ClientRepository extends BaseRepository
{
    public function getClassName()
    {
        return 'App\Models\Client';
    }

    public function all()
    {
        return Client::scope()
                ->with('user', 'contacts', 'country')
                ->withTrashed()
                ->where('is_deleted', '=', false)
                ->get();
    }

    public function find($filter = null)
    {
        $query = DB::table('clients')
                    ->join('accounts', 'accounts.id', '=', 'clients.account_id')
                    ->join('contacts', 'contacts.client_id', '=', 'clients.id')
                    ->where('clients.account_id', '=', \Auth::user()->account_id)
                    ->where('contacts.is_primary', '=', true)
                    ->where('contacts.deleted_at', '=', null)
                    ->select(
                        DB::raw('COALESCE(clients.currency_id, accounts.currency_id) currency_id'),
                        DB::raw('COALESCE(clients.country_id, accounts.country_id) country_id'),
                        'clients.public_id',
                        'clients.name',
                        'contacts.first_name',
                        'contacts.last_name',
                        'clients.balance',
                        'clients.last_login',
                        'clients.created_at',
                        'clients.work_phone',
                        'contacts.email',
                        'clients.deleted_at',
                        'clients.is_deleted',
                        'clients.user_id'
                    );

        if (!\Session::get('show_trash:client')) {
            $query->where('clients.deleted_at', '=', null);
        }

        if ($filter) {
            $query->where(function ($query) use ($filter) {
                $query->where('clients.name', 'like', '%'.$filter.'%')
                      ->orWhere('contacts.first_name', 'like', '%'.$filter.'%')
                      ->orWhere('contacts.last_name', 'like', '%'.$filter.'%')
                      ->orWhere('contacts.email', 'like', '%'.$filter.'%');
            });
        }

        return $query;
    }
    
    public function save($data, $client = null)
    {
        $publicId = isset($data['public_id']) ? $data['public_id'] : false;

        if ($client) {
           // do nothing
        } elseif (!$publicId || $publicId == '-1') {
            $client = Client::createNew();
        } else {
            $client = Client::scope($publicId)->with('contacts')->firstOrFail();
            \Log::warning('Entity not set in client repo save');
        }
        
        // convert currency code to id
        if (isset($data['currency_code'])) {
            $currencyCode = strtolower($data['currency_code']);
            $currency = Cache::get('currencies')->filter(function($item) use ($currencyCode) {
                return strtolower($item->code) == $currencyCode;
            })->first();
            if ($currency) {
                $data['currency_id'] = $currency->id;
            }
        }

        $client->fill($data);
        $client->save();

        /*
        if ( ! isset($data['contact']) && ! isset($data['contacts'])) {
            return $client;
        }
        */
        
        $first = true;
        $contacts = isset($data['contact']) ? [$data['contact']] : $data['contacts'];
        $contactIds = [];

        // If the primary is set ensure it's listed first
        usort($contacts, function ($left, $right) {
            return (isset($right['is_primary']) ? $right['is_primary'] : 1) - (isset($left['is_primary']) ? $left['is_primary'] : 0);
        });
        
        foreach ($contacts as $contact) {
            $contact = $client->addContact($contact, $first);
            $contactIds[] = $contact->public_id;
            $first = false;
        }

        foreach ($client->contacts as $contact) {
            if (!in_array($contact->public_id, $contactIds)) {
                $contact->delete();
            }
        }

        if (!$publicId || $publicId == '-1') {
            event(new ClientWasCreated($client));
        } else {
            event(new ClientWasUpdated($client));
        }

        return $client;
    }
}
