<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Quickbooks\Models;

use App\DataMapper\ClientSync;
use App\Services\Quickbooks\QuickbooksService;
use App\Models\Client;
use App\Factory\ClientFactory;
use App\Services\Quickbooks\Transformers\ClientTransformer;
use App\Interfaces\SyncInterface;

class QbClient implements SyncInterface
{
    public function __construct(public QuickbooksService $service)
    {
    }

    public function find(string $id): mixed
    {
        return $this->service->sdk->FindById('Customer', $id);
    }

    public function syncToNinja(array $records): void
    {

        $transformer = new ClientTransformer($this->service->company);

        foreach ($records as $record) {

            $ninja_data = $transformer->qbToNinja($record);

            if ($client = $this->findClient($ninja_data['id'])) {
                $client->fill($ninja_data);
                $client->save();
            }
        }

    }

    public function syncToForeign(array $records): void
    {
    }

    private function findClient(string $key): ?Client
    {
        $search = Client::query()
                         ->withTrashed()
                         ->where('company_id', $this->service->company->id)
                         ->where('sync->qb_id', $key);

        if ($search->count() == 0) {

            $client = ClientFactory::create($this->service->company->id, $this->service->company->owner()->id);
            
            $sync = new ClientSync();
            $sync->qb_id = $key;
            $client->sync = $sync;

            return $client;

        } elseif ($search->count() == 1) {
            return $this->service->settings->client->update_record ? $search->first() : null;
        }

        return null;


    }
}
