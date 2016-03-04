<?php namespace App\Services;

use stdClass;
use Excel;
use Cache;
use Exception;
use Auth;
use parsecsv;
use Session;
use Validator;
use League\Fractal\Manager;
use App\Ninja\Repositories\ContactRepository;
use App\Ninja\Repositories\ClientRepository;
use App\Ninja\Repositories\InvoiceRepository;
use App\Ninja\Repositories\PaymentRepository;
use App\Ninja\Serializers\ArraySerializer;
use App\Models\Client;
use App\Models\Invoice;

class ImportService
{
    protected $transformer;
    protected $invoiceRepo;
    protected $clientRepo;
    protected $contactRepo;
    protected $processedRows = array();

    public static $entityTypes = [
        ENTITY_CLIENT,
        ENTITY_CONTACT,
        ENTITY_INVOICE,
        ENTITY_PAYMENT,
        ENTITY_TASK,
    ];

    public static $sources = [
        IMPORT_CSV,
        IMPORT_FRESHBOOKS,
        //IMPORT_HARVEST,
        IMPORT_HIVEAGE,
        IMPORT_INVOICEABLE,
        IMPORT_NUTCACHE,
        IMPORT_RONIN,
        IMPORT_WAVE,
        IMPORT_ZOHO,
    ];

    public function __construct(Manager $manager, ClientRepository $clientRepo, InvoiceRepository $invoiceRepo, PaymentRepository $paymentRepo, ContactRepository $contactRepo)
    {
        $this->fractal = $manager;
        $this->fractal->setSerializer(new ArraySerializer());

        $this->clientRepo = $clientRepo;
        $this->invoiceRepo = $invoiceRepo;
        $this->paymentRepo = $paymentRepo;
        $this->contactRepo = $contactRepo;
    }

    public function import($source, $files)
    {
        $results = [];
        $imported_files = null;

        foreach ($files as $entityType => $file) {
            $results[$entityType] = $this->execute($source, $entityType, $file);
        }

        return $results;
    }

    private function execute($source, $entityType, $file)
    {
        $results = [
            RESULT_SUCCESS => [],
            RESULT_FAILURE => [],
        ];

        // Convert the data
        $row_list = array();
        $maps = $this->createMaps();
        Excel::load($file, function ($reader) use ($source, $entityType, $maps, &$row_list, &$results) {
            $this->checkData($entityType, count($reader->all()));

            $reader->each(function ($row) use ($source, $entityType, $maps, &$row_list, &$results) {
                $data_index = $this->transformRow($source, $entityType, $row, $maps);

                if ($data_index !== false) {
                    if ($data_index !== true) {
                        // Wasn't merged with another row
                        $row_list[] = array('row' => $row, 'data_index' => $data_index);
                    }
                } else {
                    $results[RESULT_FAILURE][] = $row;
                }
            });
        });

        // Save the data
        foreach ($row_list as $row_data) {
            $result = $this->saveData($source, $entityType, $row_data['row'], $row_data['data_index'], $maps);
            if ($result) {
                $results[RESULT_SUCCESS][] = $result;
            } else {
                $results[RESULT_FAILURE][] = $row_data['row'];
            }
        }

        return $results;
    }

    private function transformRow($source, $entityType, $row, $maps)
    {
        $transformer = $this->getTransformer($source, $entityType, $maps);
        $resource = $transformer->transform($row, $maps);

        if (!$resource) {
            return false;
        }

        $data = $this->fractal->createData($resource)->toArray();

        // if the invoice number is blank we'll assign it
        if ($entityType == ENTITY_INVOICE && !$data['invoice_number']) {
            $account = Auth::user()->account;
            $invoice = Invoice::createNew();
            $data['invoice_number'] = $account->getNextInvoiceNumber($invoice);
        }

        if ($this->validate($source, $data, $entityType) !== true) {
            return false;
        }

        if ($entityType == ENTITY_INVOICE) {
            if (empty($this->processedRows[$data['invoice_number']])) {
                $this->processedRows[$data['invoice_number']] = $data;
            } else {
                // Merge invoice items
                $this->processedRows[$data['invoice_number']]['invoice_items'] = array_merge($this->processedRows[$data['invoice_number']]['invoice_items'], $data['invoice_items']);

                return true;
            }
        } else {
            $this->processedRows[] = $data;
        }

        end($this->processedRows);

        return key($this->processedRows);
    }

    private function saveData($source, $entityType, $row, $data_index, $maps)
    {
        $data = $this->processedRows[$data_index];
        $entity = $this->{"{$entityType}Repo"}->save($data);

        // if the invoice is paid we'll also create a payment record
        if ($entityType === ENTITY_INVOICE && isset($data['paid']) && $data['paid'] > 0) {
            $this->createPayment($source, $row, $maps, $data['client_id'], $entity->id);
        }

        return $entity;
    }

    private function checkData($entityType, $count)
    {
        if ($entityType === ENTITY_CLIENT) {
            $this->checkClientCount($count);
        }
    }

    private function checkClientCount($count)
    {
        $totalClients = $count + Client::scope()->withTrashed()->count();
        if ($totalClients > Auth::user()->getMaxNumClients()) {
            throw new Exception(trans('texts.limit_clients', ['count' => Auth::user()->getMaxNumClients()]));
        }
    }

    public static function getTransformerClassName($source, $entityType)
    {
        return 'App\\Ninja\\Import\\'.$source.'\\'.ucwords($entityType).'Transformer';
    }

    public static function getTransformer($source, $entityType, $maps)
    {
        $className = self::getTransformerClassName($source, $entityType);

        return new $className($maps);
    }

    private function createPayment($source, $data, $maps, $clientId, $invoiceId)
    {
        $paymentTransformer = $this->getTransformer($source, ENTITY_PAYMENT, $maps);

        $data->client_id = $clientId;
        $data->invoice_id = $invoiceId;

        if ($resource = $paymentTransformer->transform($data, $maps)) {
            $data = $this->fractal->createData($resource)->toArray();
            $this->paymentRepo->save($data);
        }
    }

    private function validate($source, $data, $entityType)
    {
        // Harvest's contacts are listed separately
        if ($entityType === ENTITY_CLIENT && $source != IMPORT_HARVEST) {
            $rules = [
                'contacts' => 'valid_contacts',
            ];
        }
        if ($entityType === ENTITY_INVOICE) {
            $rules = [
                'client.contacts' => 'valid_contacts',
                'invoice_items' => 'valid_invoice_items',
                'invoice_number' => 'required|unique:invoices,invoice_number,,id,account_id,'.Auth::user()->account_id,
                'discount' => 'positive',
            ];
        } else {
            return true;
        }

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            $messages = $validator->messages();

            return $messages->first();
        } else {
            return true;
        }
    }

    private function createMaps()
    {
        $clientMap = [];
        $clients = $this->clientRepo->all();
        foreach ($clients as $client) {
            if ($name = strtolower(trim($client->name))) {
                $clientMap[$name] = $client->id;
            }
        }

        $invoiceMap = [];
        $invoiceClientMap = [];
        $invoices = $this->invoiceRepo->all();
        foreach ($invoices as $invoice) {
            if ($number = strtolower(trim($invoice->invoice_number))) {
                $invoiceMap[$number] = $invoice->id;
                $invoiceClientMap[$number] = $invoice->client_id;
            }
        }

        $countryMap = [];
        $countryMap2 = [];
        $countries = Cache::get('countries');
        foreach ($countries as $country) {
            $countryMap[strtolower($country->name)] = $country->id;
            $countryMap2[strtolower($country->iso_3166_2)] = $country->id;
        }

        $currencyMap = [];
        $currencies = Cache::get('currencies');
        foreach ($currencies as $currency) {
            $currencyMap[strtolower($currency->code)] = $currency->id;
        }

        return [
            ENTITY_CLIENT => $clientMap,
            ENTITY_INVOICE => $invoiceMap,
            ENTITY_INVOICE.'_'.ENTITY_CLIENT => $invoiceClientMap,
            'countries' => $countryMap,
            'countries2' => $countryMap2,
            'currencies' => $currencyMap,
        ];
    }

    public function mapCSV($files)
    {
        $data = [];

        foreach ($files as $entityType => $filename) {
            if ($entityType === ENTITY_CLIENT) {
                $columns = Client::getImportColumns();
                $map = Client::getImportMap();
            } else {
                $columns = Invoice::getImportColumns();
                $map = Invoice::getImportMap();
            }

            // Lookup field translations
            foreach ($columns as $key => $value) {
                unset($columns[$key]);
                $columns[$value] = trans("texts.{$value}");
            }
            array_unshift($columns, ' ');

            $data[$entityType] = $this->mapFile($entityType, $filename, $columns, $map);

            if ($entityType === ENTITY_CLIENT) {
                if (count($data[$entityType]['data']) + Client::scope()->count() > Auth::user()->getMaxNumClients()) {
                    throw new Exception(trans('texts.limit_clients', ['count' => Auth::user()->getMaxNumClients()]));
                }
            }
        }

        return $data;
    }

    public function mapFile($entityType, $filename, $columns, $map)
    {
        require_once app_path().'/Includes/parsecsv.lib.php';
        $csv = new parseCSV();
        $csv->heading = false;
        $csv->auto($filename);

        Session::put("{$entityType}-data", $csv->data);

        $headers = false;
        $hasHeaders = false;
        $mapped = array();

        if (count($csv->data) > 0) {
            $headers = $csv->data[0];
            foreach ($headers as $title) {
                if (strpos(strtolower($title), 'name') > 0) {
                    $hasHeaders = true;
                    break;
                }
            }

            for ($i = 0; $i<count($headers); $i++) {
                $title = strtolower($headers[$i]);
                $mapped[$i] = '';

                if ($hasHeaders) {
                    foreach ($map as $search => $column) {
                        if ($this->checkForMatch($title, $search)) {
                            $mapped[$i] = $column;
                            break;
                        }
                    }
                }
            }
        }

        $data = array(
            'entityType' => $entityType,
            'data' => $csv->data,
            'headers' => $headers,
            'hasHeaders' => $hasHeaders,
            'columns' => $columns,
            'mapped' => $mapped,
        );

        return $data;
    }

    private function checkForMatch($column, $pattern)
    {
        if (strpos($column, 'sec') === 0) {
            return false;
        }

        if (strpos($pattern, '^')) {
            list($include, $exclude) = explode('^', $pattern);
            $includes = explode('|', $include);
            $excludes = explode('|', $exclude);
        } else {
            $includes = explode('|', $pattern);
            $excludes = [];
        }

        foreach ($includes as $string) {
            if (strpos($column, $string) !== false) {
                $excluded = false;
                foreach ($excludes as $exclude) {
                    if (strpos($column, $exclude) !== false) {
                        $excluded = true;
                        break;
                    }
                }
                if (!$excluded) {
                    return true;
                }
            }
        }

        return false;
    }

    public function importCSV($maps, $headers)
    {
        $results = [];

        foreach ($maps as $entityType => $map) {
            $results[$entityType] = $this->executeCSV($entityType, $map, $headers[$entityType]);
        }

        return $results;
    }

    private function executeCSV($entityType, $map, $hasHeaders)
    {
        $results = [
            RESULT_SUCCESS => [],
            RESULT_FAILURE => [],
        ];
        $source = IMPORT_CSV;

        $data = Session::get("{$entityType}-data");
        $this->checkData($entityType, count($data));
        $maps = $this->createMaps();

        // Convert the data
        $row_list = array();
        foreach ($data as $row) {
            if ($hasHeaders) {
                $hasHeaders = false;
                continue;
            }

            $row = $this->convertToObject($entityType, $row, $map);
            $data_index = $this->transformRow($source, $entityType, $row, $maps);

            if ($data_index !== false) {
                if ($data_index !== true) {
                    // Wasn't merged with another row
                    $row_list[] = array('row' => $row, 'data_index' => $data_index);
                }
            } else {
                $results[RESULT_FAILURE][] = $row;
            }
        }

        // Save the data
        foreach ($row_list as $row_data) {
            $result = $this->saveData($source, $entityType, $row_data['row'], $row_data['data_index'], $maps);

            if ($result) {
                $results[RESULT_SUCCESS][] = $result;
            } else {
                $results[RESULT_FAILURE][] = $row;
            }
        }

        Session::forget("{$entityType}-data");

        return $results;
    }

    private function convertToObject($entityType, $data, $map)
    {
        $obj = new stdClass();

        if ($entityType === ENTITY_CLIENT) {
            $columns = Client::getImportColumns();
        } else {
            $columns = Invoice::getImportColumns();
        }

        foreach ($columns as $column) {
            $obj->$column = false;
        }

        foreach ($map as $index => $field) {
            if (! $field) {
                continue;
            }

            if (isset($obj->$field) && $obj->$field) {
                continue;
            }

            $obj->$field = $data[$index];
        }

        return $obj;
    }
}
