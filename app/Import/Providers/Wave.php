<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Import\Providers;

use App\Factory\ClientFactory;
use App\Http\Requests\Client\StoreClientRequest;
use App\Import\Transformer\Wave\ClientTransformer;
use App\Models\Client;
use App\Repositories\ClientRepository;

class Wave extends BaseImport implements ImportInterface
{

    public array $entity_count = [];

    public function import(string $entity)
    {
        if (
            in_array($entity, [
                'client',
                // 'product',
                // 'invoice',
                // 'payment',
                // 'vendor',
                // 'expense',
            ])
        ) {
            $this->{$entity}();
        }

        //collate any errors

        $this->finalizeImport();
    }

    public function client()
    {
        $entity_type = 'client';

        $data = $this->getCsvData($entity_type);
nlog($data);

        $data = $this->preTransform($data, $entity_type);
nlog($data);

        if (empty($data)) {
            $this->entity_count['clients'] = 0;
            return;
        }

        $this->request_name = StoreClientRequest::class;
        $this->repository_name = ClientRepository::class;
        $this->factory_name = ClientFactory::class;

        $this->repository = app()->make($this->repository_name);
        $this->repository->import_mode = true;

        $this->transformer = new ClientTransformer($this->company);

        $client_count = $this->ingest($data, $entity_type);

        $this->entity_count['clients'] = $client_count;

        nlog($this->entity_count);
    }

    public function transform(array $data){}

    public function product() {}

    public function invoice() {}

    public function payment() {}

    public function vendor() {}

    public function expense() {}

}
