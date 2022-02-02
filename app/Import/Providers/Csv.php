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
use App\Factory\InvoiceFactory;
use App\Factory\ProductFactory;
use App\Http\Requests\Client\StoreClientRequest;
use App\Http\Requests\Invoice\StoreInvoiceRequest;
use App\Http\Requests\Product\StoreProductRequest;
use App\Import\ImportException;
use App\Import\Providers\BaseImport;
use App\Import\Providers\ImportInterface;
use App\Import\Transformer\Csv\ClientTransformer;
use App\Import\Transformer\Csv\InvoiceTransformer;
use App\Import\Transformer\Csv\ProductTransformer;
use App\Repositories\ClientRepository;
use App\Repositories\InvoiceRepository;
use App\Repositories\ProductRepository;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\ParameterBag;

class Csv extends BaseImport implements ImportInterface
{

    public array $entity_count = [];

    public function import(string $entity) 
    { 
    
        if(in_array($entity, [ 'client', 'product', 'invoice', 'payment', 'vendor', 'expense' ]))
            $this->{$entity}();
    
        //collate any errors
    }
    
    private function client()
    {

        $entity_type = 'client';

        $data = $this->getCsvData($entity_type);

        $data = $this->preTransform($data, $entity_type);

        if(empty($data)){

            $this->entity_count['clients'] = 0;
            return;
        }

        $this->request_name = StoreClientRequest::class;
        $this->repository_name = ClientRepository::class;
        $this->factory_name = ClientFactory::class;

        $this->repository = app()->make( $this->repository_name );
        $this->repository->import_mode = true;

        $this->transformer = new ClientTransformer($this->company);

        $client_count = $this->ingest($data, $entity_type);

        $this->entity_count['clients'] = $client_count;

    }

    private function product()
    {

        $entity_type = 'product';

        $data = $this->getCsvData($entity_type);

        $data = $this->preTransform($data, $entity_type);

        if(empty($data)){

            $this->entity_count['products'] = 0;
            return;
        }

        $this->request_name = StoreProductRequest::class;
        $this->repository_name = ProductRepository::class;
        $this->factory_name = ProductFactory::class;

        $this->repository = app()->make( $this->repository_name );
        $this->repository->import_mode = true;

        $this->transformer = new ProductTransformer($this->company);

        $product_count = $this->ingest($data, $entity_type);

        $this->entity_count['products'] = $product_count;

    }

    private function invoice()
    {

        $entity_type = 'invoice';

        $data = $this->getCsvData($entity_type);

        $data = $this->preTransform($data, $entity_type);

        if(empty($data)){

            $this->entity_count['invoices'] = 0;
            return;
        }

        $this->request_name = StoreInvoiceRequest::class;
        $this->repository_name = InvoiceRepository::class;
        $this->factory_name = InvoiceFactory::class;

        $this->repository = app()->make( $this->repository_name );
        $this->repository->import_mode = true;

        $this->transformer = new InvoiceTransformer($this->company);

        $invoice_count = $this->ingestInvoices($data, 'invoice.number');

        $this->entity_count['invoices'] = $invoice_count;

    }


    public function preTransform(array $data, $entity_type) 
    { 


        if ( empty( $this->column_map[ $entity_type ] ) ) {
            return false;
        }

        if ( $this->skip_header ) {
            array_shift( $data );
        }

        //sort the array by key
        $keys = $this->column_map[ $entity_type ];
        ksort( $keys );

        $data = array_map( function ( $row ) use ( $keys ) {
            return array_combine( $keys, array_intersect_key( $row, $keys ) );
        }, $data );


        return $data;


    }

    public function transform(array $data) 
    { 

    }

}