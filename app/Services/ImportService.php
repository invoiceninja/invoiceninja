<?php namespace App\Services;

use Excel;
use Cache;
use Exception;
use League\Fractal\Manager;
use App\Ninja\Repositories\ClientRepository;
use App\Ninja\Repositories\InvoiceRepository;
use App\Ninja\Repositories\PaymentRepository;
use App\Ninja\Serializers\ArraySerializer;

class ImportService
{
    protected $transformer;
    protected $invoiceRepo;
    protected $clientRepo;

    public static $entityTypes = [
        ENTITY_CLIENT,
        ENTITY_INVOICE,
        ENTITY_TASK,
    ];

    public static $sources = [
        IMPORT_CSV,
        IMPORT_FRESHBOOKS,
        //IMPORT_HARVEST,
        //IMPORT_HIVEAGE,
        //IMPORT_INVOICEABLE,
        //IMPORT_NUTCACHE,
        //IMPORT_RONIN,
        //IMPORT_WAVE,
        //IMPORT_ZOHO,
    ];

    /**
     * FreshBooksDataImporterService constructor.
     */
    public function __construct(Manager $manager, ClientRepository $clientRepo, InvoiceRepository $invoiceRepo, PaymentRepository $paymentRepo)
    {
        $this->fractal = $manager;
        $this->fractal->setSerializer(new ArraySerializer());

        $this->clientRepo = $clientRepo;
        $this->invoiceRepo = $invoiceRepo;
        $this->paymentRepo = $paymentRepo;
    }

    public function import($source, $files)
    {
        $imported_files = null;
        
        foreach ($files as $entityType => $file) {
            $this->execute($source, $entityType, $file);
        }
    }

    private function execute($source, $entityType, $file)
    {
        $transformerClassName = $this->getTransformerClassName($source, $entityType);
        $transformer = new $transformerClassName;

        Excel::load($file, function($reader) use ($source, $entityType, $transformer) {
            
            $maps = $this->createMaps();
            
            $reader->each(function($row) use ($source, $entityType, $transformer, $maps) {
                if ($resource = $transformer->transform($row, $maps)) {
                    $data = $this->fractal->createData($resource)->toArray();
                    $entity = $this->{"{$entityType}Repo"}->save($data);

                    // if the invoice is paid we'll also create a payment record
                    if ($entityType === ENTITY_INVOICE && isset($data['paid']) && $data['paid']) {
                        $class = self::getTransformerClassName($source, 'payment');
                        $paymentTransformer = new $class;
                        $row->client_id = $data['client_id'];
                        $row->invoice_id = $entity->public_id;
                        if ($resource = $paymentTransformer->transform($row, $maps)) {
                            $data = $this->fractal->createData($resource)->toArray();
                            $this->paymentRepo->save($data);
                        }
                    }
                }
            });
        });
    }

    private function createMaps()
    {
        $clientMap = [];
        $clients = $this->clientRepo->all();
        foreach ($clients as $client) {
            $clientMap[$client->name] = $client->public_id;
        }

        $invoiceMap = [];
        $invoices = $this->invoiceRepo->all();
        foreach ($invoices as $invoice) {
            $invoiceMap[$invoice->invoice_number] = $invoice->public_id;
        }

        $countryMap = [];
        $countries = Cache::get('countries');
        foreach ($countries as $country) {
            $countryMap[$country->name] = $country->id;
        }

        return [
            ENTITY_CLIENT => $clientMap,
            ENTITY_INVOICE => $invoiceMap,
            'countries' => $countryMap,
        ];
    }

    public static function getTransformerClassName($source, $entityType)
    {
        return 'App\\Ninja\\Import\\' . $source . '\\' . ucwords($entityType) . 'Transformer';
    }
}
