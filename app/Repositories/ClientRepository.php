<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Repositories;

use App\Models\Client;
use App\Models\Company;
use App\Models\Location;
use App\Models\ClientContact;
use App\Factory\ClientFactory;
use App\Utils\Traits\SavesDocuments;
use App\Utils\Traits\GeneratesCounter;
use Illuminate\Database\QueryException;
use App\Jobs\Client\PurgeClientDocuments;

/**
 * ClientRepository.
 */
class ClientRepository extends BaseRepository
{
    use GeneratesCounter;
    use SavesDocuments;

    private bool $completed = true;

    /**
     * @var ClientContactRepository
     */
    protected $contact_repo;

    /**
     * ClientController constructor.
     * @param ClientContactRepository $contact_repo
     */
    public function __construct(ClientContactRepository $contact_repo)
    {
        $this->contact_repo = $contact_repo;
    }

    /**
     * Saves the client and its contacts.
     *
     * @param array $data The data
     * @param Client $client The client
     *
     * @return     Client|Client|null  Client Object
     *
     * @throws \Laracasts\Presenter\Exceptions\PresenterException
     */
    public function save(array $data, Client $client): ?Client
    {
        $contact_data = $data;
        unset($data['contacts']);

        /* When uploading documents, only the document array is sent, so we must return early*/
        if (array_key_exists('documents', $data) && count($data['documents']) >= 1) {
            $this->saveDocuments($data['documents'], $client);

            return $client;
        }

        $client->fill($data);

        if (array_key_exists('settings', $data)) {
            $client->settings = $client->saveSettings($data['settings'], $client);
        }

        if (! $client->country_id || $client->country_id == 0) {
            /** @var \App\Models\Company $company **/
            $company = Company::find($client->company_id);
            $client->country_id = $company->settings->country_id;
        }

        $client->save();

        if (! isset($client->number) || empty($client->number) || strlen($client->number ?? '') == 0) {//@phpstan-ignore-line
            $x = 1;

            do {
                try {
                    $client->number = $this->getNextClientNumber($client);
                    $client->saveQuietly();

                    $this->completed = false;
                } catch (QueryException $e) {
                    $x++;

                    if ($x > 10) {
                        $this->completed = false;
                    }
                }
            } while ($this->completed);
        }

        if (empty($data['name'])) {
            $data['name'] = $client->present()->name();
        }

        //24-01-2023 when a logo is uploaded, no other data is set, so we need to catch here and not update
        //the contacts array UNLESS there are no contacts and we need to maintain state.
        if (array_key_exists('contacts', $contact_data) || $client->contacts()->count() == 0) {
            $this->contact_repo->save($contact_data, $client);
        }


        return $client;
    }

    /**
     * Store clients in bulk.
     *
     * @param array $client
     * @return Client|null
     */
    public function create($client): ?Client
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        return $this->save(
            $client,
            ClientFactory::create($user->company()->id, $user->id)
        );
    }

    /**
     * Bulk assign clients to a group.
     *
     * @param  mixed $clients
     * @param  mixed $group_settings_id
     * @return void
     */
    public function assignGroup($clients, $group_settings_id): void
    {
        Client::query()
              ->company()
              ->whereIn('id', $clients->pluck('id'))
              ->update(['group_settings_id' => $group_settings_id]);
    }

    public function purge($client)
    {

        $purged_client = $client->present()->name();
        $purged_client_hash = $client->client_hash;

        $user = auth()->user() ?? $client->user;
        $company = $client->company;

        $event_vars = \App\Utils\Ninja::eventVars(auth()->user() ? auth()->user()->id : null);
        $event_vars['client_hash'] = $purged_client_hash;

        event(new \App\Events\Client\ClientWasPurged($purged_client, $user, $company, $event_vars));

        nlog("Purging client id => {$client->id} => {$client->number}");

        // Delete documents associated with client's related entities before deleting the entities
        $this->purgeClientDocuments($client);

        $client->contacts()->forceDelete();
        $client->tasks()->forceDelete();
        $client->invoices()->forceDelete();
        $client->ledger()->forceDelete();
        $client->gateway_tokens()->forceDelete();
        $client->projects()->forceDelete();
        $client->credits()->forceDelete();
        $client->quotes()->forceDelete();
        $client->purgeable_activities()->forceDelete();
        $client->recurring_invoices()->forceDelete();
        $client->expenses()->forceDelete();
        $client->recurring_expenses()->forceDelete();
        $client->system_logs()->forceDelete();
        // $client->documents()->forceDelete();
        $client->payments()->forceDelete();

        $client->unsearchable();

        $client->forceDelete();
    }

    /**
     * Purge all documents associated with client's related entities.
     * This ensures documents attached to invoices, quotes, payments, etc. are also deleted.
     */
    private function purgeClientDocuments($client)
    {
        // Get all entity IDs that belong to this client
        $data = [
            'invoices' => $client->invoices()->pluck('id')->toArray(),
            'App\Models\Quote' => $client->quotes()->pluck('id')->toArray(),
            'App\Models\Payment' => $client->payments()->pluck('id')->toArray(),
            'App\Models\Credit' => $client->credits()->pluck('id')->toArray(),
            'App\Models\Expense' => $client->expenses()->pluck('id')->toArray(),
            'App\Models\RecurringInvoice' => $client->recurring_invoices()->pluck('id')->toArray(),
            'App\Models\RecurringExpense' => $client->recurring_expenses()->pluck('id')->toArray(),
            'App\Models\Project' => $client->projects()->pluck('id')->toArray(),
            'App\Models\Task' => $client->tasks()->pluck('id')->toArray(),
            'App\Models\Client' => [$client->id],
        ];

        PurgeClientDocuments::dispatch($data, $client->company);

    }
    
    /**
     * clone/duplicate a client
     *
     * @param  Client $client
     * @return Client
     */
    public function clone(Client $client)
    {
        $clone_client = $client->replicate();
        $clone_client->name = $clone_client->name . ' clone ' . date('Y-m-d H:i:s');
        $clone_client->client_hash = \Illuminate\Support\Str::random(40);
        $clone_client->sync = null;
        $clone_client->number = null;
        $clone_client->balance = 0;
        $clone_client->paid_to_date = 0;
        $clone_client->credit_balance = 0;
        $clone_client->payment_balance = 0;
        $clone_client->save();

        $clone_client->service()->applyNumber()->save();
        
        $client->contacts->each(function (ClientContact $contact) use ($clone_client) {
            $clone_contact = $contact->replicate();
            $clone_contact->client_id = $clone_client->id;
            $clone_contact->contact_key = \Illuminate\Support\Str::random(32);
            $clone_contact->save();
        });

        $client->locations->each(function (Location $location) use ($clone_client) {
            $clone_location = $location->replicate();
            $clone_location->client_id = $clone_client->id;
            $clone_location->save();
        });

        return $clone_client;
    }


}
