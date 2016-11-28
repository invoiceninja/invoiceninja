<?php namespace App\Services;

use App\Models\Product;
use stdClass;
use Excel;
use Cache;
use Exception;
use Auth;
use Utils;
use parsecsv;
use Session;
use League\Fractal\Manager;
use App\Ninja\Repositories\ContactRepository;
use App\Ninja\Repositories\ClientRepository;
use App\Ninja\Repositories\InvoiceRepository;
use App\Ninja\Repositories\PaymentRepository;
use App\Ninja\Repositories\ProductRepository;
use App\Ninja\Repositories\ExpenseRepository;
use App\Ninja\Repositories\VendorRepository;
use App\Ninja\Repositories\ExpenseCategoryRepository;
use App\Ninja\Serializers\ArraySerializer;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Vendor;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\EntityModel;

/**
 * Class ImportService
 */
class ImportService
{
    /**
     * @var
     */
    protected $transformer;

    /**
     * @var InvoiceRepository
     */
    protected $invoiceRepo;

    /**
     * @var ClientRepository
     */
    protected $clientRepo;

    /**
     * @var ContactRepository
     */
    protected $contactRepo;

    /**
     * @var ProductRepository
     */
    protected $productRepo;

    /**
     * @var array
     */
    protected $processedRows = [];

    /**
     * @var array
     */
    private $maps = [];

    /**
     * @var array
     */
    public $results = [];

    /**
     * @var array
     */
    public static $entityTypes = [
        IMPORT_JSON,
        ENTITY_CLIENT,
        ENTITY_CONTACT,
        ENTITY_INVOICE,
        ENTITY_PAYMENT,
        ENTITY_TASK,
        ENTITY_PRODUCT,
        ENTITY_EXPENSE,
    ];

    /**
     * @var array
     */
    public static $sources = [
        IMPORT_CSV,
        IMPORT_JSON,
        IMPORT_FRESHBOOKS,
        IMPORT_HIVEAGE,
        IMPORT_INVOICEABLE,
        IMPORT_NUTCACHE,
        IMPORT_RONIN,
        IMPORT_WAVE,
        IMPORT_ZOHO,
    ];

    /**
     * ImportService constructor.
     *
     * @param Manager $manager
     * @param ClientRepository $clientRepo
     * @param InvoiceRepository $invoiceRepo
     * @param PaymentRepository $paymentRepo
     * @param ContactRepository $contactRepo
     * @param ProductRepository $productRepo
     */
    public function __construct(
        Manager $manager,
        ClientRepository $clientRepo,
        InvoiceRepository $invoiceRepo,
        PaymentRepository $paymentRepo,
        ContactRepository $contactRepo,
        ProductRepository $productRepo,
        ExpenseRepository $expenseRepo,
        VendorRepository $vendorRepo,
        ExpenseCategoryRepository $expenseCategoryRepo
    )
    {
        $this->fractal = $manager;
        $this->fractal->setSerializer(new ArraySerializer());

        $this->clientRepo = $clientRepo;
        $this->invoiceRepo = $invoiceRepo;
        $this->paymentRepo = $paymentRepo;
        $this->contactRepo = $contactRepo;
        $this->productRepo = $productRepo;
        $this->expenseRepo = $expenseRepo;
        $this->vendorRepo = $vendorRepo;
        $this->expenseCategoryRepo = $expenseCategoryRepo;
    }

    /**
     * @param $file
     * @return array
     * @throws Exception
     */
    public function importJSON($file)
    {
        $this->init();

        $file = file_get_contents($file);
        $json = json_decode($file, true);
        $json = $this->removeIdFields($json);

        $this->checkClientCount(count($json['clients']));

        foreach ($json['clients'] as $jsonClient) {

            if (EntityModel::validate($jsonClient, ENTITY_CLIENT) === true) {
                $client = $this->clientRepo->save($jsonClient);
                $this->addSuccess($client);
            } else {
                $this->addFailure(ENTITY_CLIENT, $jsonClient);
                continue;
            }

            foreach ($jsonClient['invoices'] as $jsonInvoice) {
                $jsonInvoice['client_id'] = $client->id;
                if (EntityModel::validate($jsonInvoice, ENTITY_INVOICE) === true) {
                    $invoice = $this->invoiceRepo->save($jsonInvoice);
                    $this->addSuccess($invoice);
                } else {
                    $this->addFailure(ENTITY_INVOICE, $jsonInvoice);
                    continue;
                }

                foreach ($jsonInvoice['payments'] as $jsonPayment) {
                    $jsonPayment['invoice_id'] = $invoice->public_id;
                    if (EntityModel::validate($jsonPayment, ENTITY_PAYMENT) === true) {
                        $jsonPayment['client_id'] = $client->id;
                        $jsonPayment['invoice_id'] = $invoice->id;
                        $payment = $this->paymentRepo->save($jsonPayment);
                        $this->addSuccess($payment);
                    } else {
                        $this->addFailure(ENTITY_PAYMENT, $jsonPayment);
                        continue;
                    }
                }
            }
        }

        return $this->results;
    }

    /**
     * @param $array
     * @return mixed
     */
    public function removeIdFields($array)
    {
        foreach ($array as $key => $val) {
            if (is_array($val)) {
                $array[$key] = $this->removeIdFields($val);
            } elseif ($key === 'id') {
                unset($array[$key]);
            }
        }
        return $array;
    }

    /**
     * @param $source
     * @param $files
     * @return array
     */
    public function importFiles($source, $files)
    {
        $results = [];
        $imported_files = null;
        $this->initMaps();

        foreach ($files as $entityType => $file) {
            $results[$entityType] = $this->execute($source, $entityType, $file);
        }

        return $results;
    }

    /**
     * @param $source
     * @param $entityType
     * @param $file
     * @return array
     */
    private function execute($source, $entityType, $file)
    {
        $results = [
            RESULT_SUCCESS => [],
            RESULT_FAILURE => [],
        ];

        // Convert the data
        $row_list = [];

        Excel::load($file, function ($reader) use ($source, $entityType, &$row_list, &$results) {
            $this->checkData($entityType, count($reader->all()));

            $reader->each(function ($row) use ($source, $entityType, &$row_list, &$results) {
                $data_index = $this->transformRow($source, $entityType, $row);

                if ($data_index !== false) {
                    if ($data_index !== true) {
                        // Wasn't merged with another row
                        $row_list[] = ['row' => $row, 'data_index' => $data_index];
                    }
                } else {
                    $results[RESULT_FAILURE][] = $row;
                }
            });
        });

        // Save the data
        foreach ($row_list as $row_data) {
            $result = $this->saveData($source, $entityType, $row_data['row'], $row_data['data_index']);
            if ($result) {
                $results[RESULT_SUCCESS][] = $result;
            } else {
                $results[RESULT_FAILURE][] = $row_data['row'];
            }
        }

        return $results;
    }

    /**
     * @param $source
     * @param $entityType
     * @param $row
     * @return bool|mixed
     */
    private function transformRow($source, $entityType, $row)
    {
        $transformer = $this->getTransformer($source, $entityType, $this->maps);

        // Create expesnse category
        if ($entityType == ENTITY_EXPENSE) {
            if ( ! empty($row->expense_category)) {
                $categoryId = $transformer->getExpenseCategoryId($row->expense_category);
                if ( ! $categoryId) {
                    $category = $this->expenseCategoryRepo->save(['name' => $row->expense_category]);
                    $this->addExpenseCategoryToMaps($category);
                }
            }
            if ( ! empty($row->vendor)) {
                $vendorId = $transformer->getVendorId($row->vendor);
                if ( ! $vendorId) {
                    $vendor = $this->vendorRepo->save(['name' => $row->vendor, 'vendor_contact' => []]);
                    $this->addVendorToMaps($vendor);
                }
            }
        }

        $resource = $transformer->transform($row);

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

        if (EntityModel::validate($data, $entityType) !== true) {
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

    /**
     * @param $source
     * @param $entityType
     * @param $row
     * @param $data_index
     * @return mixed
     */
    private function saveData($source, $entityType, $row, $data_index)
    {
        $data = $this->processedRows[$data_index];
        $entity = $this->{"{$entityType}Repo"}->save($data);

        // update the entity maps
        $mapFunction = 'add' . ucwords($entity->getEntityType()) . 'ToMaps';
        $this->$mapFunction($entity);

        // if the invoice is paid we'll also create a payment record
        if ($entityType === ENTITY_INVOICE && isset($data['paid']) && $data['paid'] > 0) {
            $this->createPayment($source, $row, $data['client_id'], $entity->id);
        }

        return $entity;
    }

    /**
     * @param $entityType
     * @param $count
     * @throws Exception
     */
    private function checkData($entityType, $count)
    {
        if (Utils::isNinja() && $count > MAX_IMPORT_ROWS) {
            throw new Exception(trans('texts.limit_import_rows', ['count' => MAX_IMPORT_ROWS]));
        }

        if ($entityType === ENTITY_CLIENT) {
            $this->checkClientCount($count);
        }
    }

    /**
     * @param $count
     * @throws Exception
     */
    private function checkClientCount($count)
    {
        $totalClients = $count + Client::scope()->withTrashed()->count();
        if ($totalClients > Auth::user()->getMaxNumClients()) {
            throw new Exception(trans('texts.limit_clients', ['count' => Auth::user()->getMaxNumClients()]));
        }
    }

    /**
     * @param $source
     * @param $entityType
     * @return string
     */
    public static function getTransformerClassName($source, $entityType)
    {
        return 'App\\Ninja\\Import\\'.$source.'\\'.ucwords($entityType).'Transformer';
    }

    /**
     * @param $source
     * @param $entityType
     * @param $maps
     * @return mixed
     */
    public static function getTransformer($source, $entityType, $maps)
    {
        $className = self::getTransformerClassName($source, $entityType);

        return new $className($maps);
    }

    /**
     * @param $source
     * @param $data
     * @param $clientId
     * @param $invoiceId
     */
    private function createPayment($source, $data, $clientId, $invoiceId)
    {
        $paymentTransformer = $this->getTransformer($source, ENTITY_PAYMENT, $this->maps);

        $data->client_id = $clientId;
        $data->invoice_id = $invoiceId;

        if ($resource = $paymentTransformer->transform($data)) {
            $data = $this->fractal->createData($resource)->toArray();
            $this->paymentRepo->save($data);
        }
    }


    /**
     * @param array $files
     * @return array
     * @throws Exception
     */
    public function mapCSV(array $files)
    {
        $data = [];

        foreach ($files as $entityType => $filename) {
            $class = 'App\\Models\\' . ucwords($entityType);
            $columns = $class::getImportColumns();
            $map = $class::getImportMap();

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

    /**
     * @param $entityType
     * @param $filename
     * @param $columns
     * @param $map
     * @return array
     */
    public function mapFile($entityType, $filename, $columns, $map)
    {
        require_once app_path().'/Includes/parsecsv.lib.php';
        $csv = new parseCSV();
        $csv->heading = false;
        $csv->auto($filename);

        Session::put("{$entityType}-data", $csv->data);

        $headers = false;
        $hasHeaders = false;
        $mapped = [];

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

                foreach ($map as $search => $column) {
                    if ($this->checkForMatch($title, $search)) {
                        $hasHeaders = true;
                        $mapped[$i] = $column;
                        break;
                    }
                }
            }
        }

        $data = [
            'entityType' => $entityType,
            'data' => $csv->data,
            'headers' => $headers,
            'hasHeaders' => $hasHeaders,
            'columns' => $columns,
            'mapped' => $mapped,
        ];

        return $data;
    }

    /**
     * @param $column
     * @param $pattern
     * @return bool
     */
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

    /**
     * @param array $maps
     * @param $headers
     * @return array
     */
    public function importCSV(array $maps, $headers)
    {
        $results = [];

        foreach ($maps as $entityType => $map) {
            $results[$entityType] = $this->executeCSV($entityType, $map, $headers[$entityType]);
        }

        return $results;
    }

    /**
     * @param $entityType
     * @param $map
     * @param $hasHeaders
     * @return array
     */
    private function executeCSV($entityType, $map, $hasHeaders)
    {
        $results = [
            RESULT_SUCCESS => [],
            RESULT_FAILURE => [],
        ];
        $source = IMPORT_CSV;

        $data = Session::get("{$entityType}-data");
        $this->checkData($entityType, count($data));
        $this->initMaps();

        // Convert the data
        $row_list = [];
        foreach ($data as $row) {
            if ($hasHeaders) {
                $hasHeaders = false;
                continue;
            }

            $row = $this->convertToObject($entityType, $row, $map);
            $data_index = $this->transformRow($source, $entityType, $row);

            if ($data_index !== false) {
                if ($data_index !== true) {
                    // Wasn't merged with another row
                    $row_list[] = ['row' => $row, 'data_index' => $data_index];
                }
            } else {
                $results[RESULT_FAILURE][] = $row;
            }
        }

        // Save the data
        foreach ($row_list as $row_data) {
            $result = $this->saveData($source, $entityType, $row_data['row'], $row_data['data_index']);

            if ($result) {
                $results[RESULT_SUCCESS][] = $result;
            } else {
                $results[RESULT_FAILURE][] = $row;
            }
        }

        Session::forget("{$entityType}-data");

        return $results;
    }

    /**
     * @param $entityType
     * @param $data
     * @param $map
     * @return stdClass
     */
    private function convertToObject($entityType, $data, $map)
    {
        $obj = new stdClass();
        $class = 'App\\Models\\' . ucwords($entityType);
        $columns = $class::getImportColumns();

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

    /**
     * @param $entity
     */
    private function addSuccess($entity)
    {
        $this->results[$entity->getEntityType()][RESULT_SUCCESS][] = $entity;
    }

    /**
     * @param $entityType
     * @param $data
     */
    private function addFailure($entityType, $data)
    {
        $this->results[$entityType][RESULT_FAILURE][] = $data;
    }

    private function init()
    {
        EntityModel::$notifySubscriptions = false;

        foreach ([ENTITY_CLIENT, ENTITY_INVOICE, ENTITY_PAYMENT, ENTITY_QUOTE] as $entityType) {
            $this->results[$entityType] = [
                RESULT_SUCCESS => [],
                RESULT_FAILURE => [],
            ];
        }
    }

    private function initMaps()
    {
        $this->init();

        $this->maps = [
            'client' => [],
            'invoice' => [],
            'invoice_client' => [],
            'product' => [],
            'countries' => [],
            'countries2' => [],
            'currencies' => [],
            'client_ids' => [],
            'invoice_ids' => [],
            'vendors' => [],
            'expense_categories' => [],
        ];

        $clients = $this->clientRepo->all();
        foreach ($clients as $client) {
            $this->addClientToMaps($client);
        }

        $invoices = $this->invoiceRepo->all();
        foreach ($invoices as $invoice) {
            $this->addInvoiceToMaps($invoice);
        }

        $products = $this->productRepo->all();
        foreach ($products as $product) {
            $this->addProductToMaps($product);
        }

        $countries = Cache::get('countries');
        foreach ($countries as $country) {
            $this->maps['countries'][strtolower($country->name)] = $country->id;
            $this->maps['countries2'][strtolower($country->iso_3166_2)] = $country->id;
        }

        $currencies = Cache::get('currencies');
        foreach ($currencies as $currency) {
            $this->maps['currencies'][strtolower($currency->code)] = $currency->id;
        }

        $vendors = $this->vendorRepo->all();
        foreach ($vendors as $vendor) {
            $this->addVendorToMaps($vendor);
        }

        $expenseCaegories = $this->expenseCategoryRepo->all();
        foreach ($expenseCaegories as $category) {
            $this->addExpenseCategoryToMaps($category);
        }
    }

    /**
     * @param Invoice $invoice
     */
    private function addInvoiceToMaps(Invoice $invoice)
    {
        if ($number = strtolower(trim($invoice->invoice_number))) {
            $this->maps['invoice'][$number] = $invoice->id;
            $this->maps['invoice_client'][$number] = $invoice->client_id;
            $this->maps['invoice_ids'][$invoice->public_id] = $invoice->id;
        }
    }

    /**
     * @param Client $client
     */
    private function addClientToMaps(Client $client)
    {
        if ($name = strtolower(trim($client->name))) {
            $this->maps['client'][$name] = $client->id;
            $this->maps['client_ids'][$client->public_id] = $client->id;
        }
    }

    /**
     * @param Product $product
     */
    private function addProductToMaps(Product $product)
    {
        if ($key = strtolower(trim($product->product_key))) {
            $this->maps['product'][$key] = $product->id;
        }
    }

    private function addExpenseToMaps(Expense $expense)
    {
        // do nothing
    }

    private function addVendorToMaps(Vendor $vendor)
    {
        $this->maps['vendor'][strtolower($vendor->name)] = $vendor->id;
    }

    private function addExpenseCategoryToMaps(ExpenseCategory $category)
    {
        if ($name = strtolower($category->name)) {
            $this->maps['expense_category'][$name] = $category->id;
        }
    }
}
