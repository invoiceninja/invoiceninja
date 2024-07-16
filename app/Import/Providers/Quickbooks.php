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

namespace App\Import\Providers;

use App\Factory\ProductFactory;
use App\Factory\ClientFactory;
use App\Factory\InvoiceFactory;
use App\Http\Requests\Client\StoreClientRequest;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Invoice\StoreInvoiceRequest;
use App\Import\Transformer\Quickbooks\ClientTransformer;
use App\Import\Transformer\Quickbooks\InvoiceTransformer;
use App\Import\Transformer\Quickbooks\ProductTransformer;
use App\Repositories\ClientRepository;
use App\Repositories\InvoiceRepository;
use App\Repositories\ProductRepository;

class Quickbooks extends BaseImport
{
    public array $entity_count = [];

    public function import(string $entity)
    {
        if (
            in_array($entity, [
                'client',
                'invoice',
                'product',
                'payment',
                // 'vendor',
                // 'expense',
            ])
        ) {
            $this->{$entity}();
        }

        //collate any errors

        // $this->finalizeImport();
    }

    public function client()
    {
        $entity_type = 'client';
        $data = $this->getData($entity_type);
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
    } 

    public function product()
    {
        $entity_type = 'product';
        $data = $this->getData($entity_type);
        if (empty($data)) {
            $this->entity_count['products'] = 0;

            return;
        }

        $this->request_name = StoreProductRequest::class;
        $this->repository_name = ProductRepository::class;
        $this->factory_name = ProductFactory::class;
        $this->repository = app()->make($this->repository_name);
        $this->repository->import_mode = true;
        $this->transformer = new ProductTransformer($this->company);
        $count = $this->ingest($data, $entity_type);
        $this->entity_count['products'] = $count;
    } 

    public function getData($type) {

        // get the data from cache? file? or api ?
        return [];
    }

    public function invoice()
    {
        //make sure we update and create products 
        $initial_update_products_value = $this->company->update_products;
        $this->company->update_products = true;

        $this->company->save();

        $entity_type = 'invoice';
        $data = $this->getData($entity_type);

        if (empty($data)) {
            $this->entity_count['invoices'] = 0;

            return;
        }

        $this->request_name = StoreInvoiceRequest::class;
        $this->repository_name = InvoiceRepository::class;
        $this->factory_name = InvoiceFactory::class;
        $this->repository = app()->make($this->repository_name);
        $this->repository->import_mode = true;
        $this->transformer = new InvoiceTransformer($this->company);
        $invoice_count = $this->ingestInvoices($data, 'Invoice #');
        $this->entity_count['invoices'] = $invoice_count;
        $this->company->update_products = $initial_update_products_value;
        $this->company->save();
    }
}
