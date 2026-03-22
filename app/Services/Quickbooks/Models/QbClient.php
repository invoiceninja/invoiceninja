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

namespace App\Services\Quickbooks\Models;

use App\Models\Client;
use App\DataMapper\ClientSync;
use App\Factory\ClientFactory;
use App\Interfaces\SyncInterface;
use App\Factory\ClientContactFactory;
use App\Repositories\ClientRepository;
use App\Repositories\ClientContactRepository;
use App\Services\Quickbooks\QuickbooksService;
use App\Services\Quickbooks\Transformers\ClientTransformer;

class QbClient implements SyncInterface
{
    protected ClientTransformer $client_transformer;

    protected ClientRepository $client_repository;

    public function __construct(public QuickbooksService $service)
    {
        $this->client_transformer = new ClientTransformer($this->service->company);
        $this->client_repository = new ClientRepository(new ClientContactRepository());
    }

    /**
     * find
     *
     * Finds a client in QuickBooks by their ID.
     *
     * @param  string $id
     * @return mixed
     */
    public function find(string $id): mixed
    {
        return $this->service->sdk->FindById('Customer', $id);
    }

    /**
     * Sync clients from QuickBooks to Ninja.
     *
     * Resolves QB Term to payment_terms when present; find/create client and contact.
     *
     * @param  array $records
     * @return void
     */
    public function syncToNinja(array $records): void
    {
        $transformer = new ClientTransformer($this->service->company);

        foreach ($records as $record) {
            $ninja_data = $transformer->qbToNinja($record, $this->service);

            if (! empty($ninja_data[0]['terms'])) {
                $days = $this->service->findEntityById('Term', $ninja_data[0]['terms']);
                if ($days) {
                    $ninja_data[0]['settings']->payment_terms = (string) $days->DueDays;
                }
            }

            $client = $this->findClient($ninja_data[0]['id'], $ninja_data[0]['name'] ?? null, $ninja_data[1]['email'] ?? null);
            if (! $client) {
                continue;
            }

            $client->fill($ninja_data[0]);
            $client->service()->applyNumber()->save();

            $contact = $client->contacts()->where('email', $ninja_data[1]['email'])->first();

            if (! $contact) {
                $contact = ClientContactFactory::create($this->service->company->id, $this->service->company->owner()->id);
                $contact->client_id = $client->id;
                $contact->send_email = true;
                $contact->is_primary = true;
                $contact->fill($ninja_data[1]);
                $contact->saveQuietly();
            } else {
                $contact->fill($ninja_data[1]);
                $contact->saveQuietly();
            }
        }
    }

    /**
     * syncToForeign
     *
     * Accepts an array of clients and creates them in QuickBooks.
     *
     * @param  array $records
     * @return void
     */
    public function syncToForeign(array $records): void
    {
        foreach ($records as $client) {
            if (!$client instanceof Client) {
                continue;
            }

            $this->createQbClient($client);

        }
    }

    private function findClientIdByName(?string $name): mixed
    {
        return $this->service->sdk->Query("SELECT Id FROM Customer WHERE DisplayName = '{$name}'",1,1);
    }
    
    /**
     * createQbClient
     *
     * Creates a client in QuickBooks and returns the QB ID.
     *
     * @param  Client $client
     * @return string
     */
    public function createQbClient(Client $client): ?string
    {
        try {
            // Transform invoice to QuickBooks format
            $qb_client_data = $this->client_transformer->ninjaToQb($client, $this->service);

            // If updating, fetch SyncToken using existing find() method
            if (isset($client->sync->qb_id) && !empty($client->sync->qb_id)) {
                $existing_qb_client = $this->find($client->sync->qb_id);
                if ($existing_qb_client) {
                    $qb_client_data['SyncToken'] = $existing_qb_client->SyncToken ?? '0';
                    $qb_client_data['Id'] = $client->sync->qb_id;

                    nlog("updating client {$client->id} in QuickBooks");
                    $customer = \QuickBooksOnline\API\Facades\Customer::create($qb_client_data);
                    $result = $this->service->sdk->Update($customer);

                    return $client->sync->qb_id;
                }
            }
            else {
                $customers = $this->findClientIdByName($client->present()->name());
                if ($customers) {
                    // QB SDK can return a single object or an array; normalize to array
                    if (!is_array($customers)) {
                        $customers = [$customers];
                    }
                    
                    if (isset($customers[0])) {
                        $customer = $customers[0];
                        $qb_id = data_get($customer, 'Id') ?? data_get($customer, 'Id.value');

                        $sync = new \App\DataMapper\ClientSync();
                        $sync->qb_id = $qb_id;
                        $client->sync = $sync;
                        $client->saveQuietly();
                        
                        return $qb_id;
                    }
                }
            }

            $customer = \QuickBooksOnline\API\Facades\Customer::create($qb_client_data);
            $resulting_customer = $this->service->sdk->Add($customer);

            $qb_id = data_get($resulting_customer, 'Id') ?? data_get($resulting_customer, 'Id.value');

            // Store QB ID in client sync
            $sync = new \App\DataMapper\ClientSync();
            $sync->qb_id = $qb_id;
            $client->sync = $sync;
            $client->saveQuietly();

            nlog("QuickBooks: Auto-created client {$client->id} in QuickBooks (QB ID: {$qb_id})");

            return $qb_id;

        } catch (\Exception $e) {
            nlog("QuickBooks: Error pushing client {$client->id} to QuickBooks: {$e->getMessage()}");
            throw $e;
        }
    }

    public function sync(string $id, string $last_updated): void {}

    private function findClient(string $key, ?string $name = null, ?string $email = null): ?Client
    {
        $company_id = $this->service->company->id;

        // First, try to find by QB ID
        $search = Client::query()
                         ->withTrashed()
                         ->where('company_id', $company_id)
                         ->where('sync->qb_id', $key);

        if ($search->count() == 1) {
            return $search->first();
        }

        // If not found by QB ID, try to find by exact client name
        if ($search->count() == 0 && $name) {
            $name_match = Client::query()
                ->withTrashed()
                ->where('company_id', $company_id)
                ->where('name', $name)
                ->first();

            if ($name_match) {
                $sync = $name_match->sync ? clone $name_match->sync : new ClientSync();
                $sync->qb_id = $key;
                $name_match->sync = $sync;
                $name_match->saveQuietly();

                return $name_match;
            }
        }

        // If not found by name, try to find by contact email
        if ($search->count() == 0 && $email) {
            $email_match = Client::query()
                ->withTrashed()
                ->where('company_id', $company_id)
                ->whereHas('contacts', function ($query) use ($email) {
                    $query->where('email', $email);
                })
                ->first();

            if ($email_match) {
                $sync = $email_match->sync ? clone $email_match->sync : new ClientSync();
                $sync->qb_id = $key;
                $email_match->sync = $sync;
                $email_match->saveQuietly();

                return $email_match;
            }
        }

        // No match found - create a new client
        $client = ClientFactory::create($company_id, $this->service->company->owner()->id);

        $sync = new ClientSync();
        $sync->qb_id = $key;
        $client->sync = $sync;

        return $client;

    }
}
