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
use App\Import\ImportException;
use App\Import\Providers\BaseImport;
use App\Import\Providers\ImportInterface;
use App\Import\Transformer\Csv\ClientTransformer;
use App\Repositories\ClientRepository;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\ParameterBag;

class Csv extends BaseImport implements ImportInterface
{

    public function import(string $entity) 
    { 
    
        if(in_array($entity, [ 'client', 'product', 'invoice', 'payment', 'vendor', 'expense' ]))
            $this->{$entity};
    
    }
    
    private function client()
    {

        $entity_type = 'client';

        $data = $this->getCsvData($entity_type);

        $data = $this->preTransform($data);

        if(empty($data))
            return;

        $this->request_name = StoreClientRequest::class;
        $this->repository_name = ClientRepository::class;
        $this->factory_name = ClientFactory::class;

        $this->repository = app()->make( $this->repository_name );
        $this->repository->import_mode = true;

        $this->transformer = new ClientTransformer($this->company);

        $this->ingest($data, $entity_type);

    }





    public function preTransform(array $data) 
    { 


        if ( empty( $this->column_map[ 'client' ] ) ) {
            return false;
        }

        if ( $this->skip_header ) {
            array_shift( $data );
        }

        //sort the array by key
        $keys = $this->column_map[ 'client' ];
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