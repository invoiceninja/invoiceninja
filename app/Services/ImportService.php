<?php namespace App\Services;

use Excel;
use Cache;
use Exception;
use Auth;
use Utils;
use parsecsv;
use Session;
use Validator;
use League\Fractal\Manager;
use App\Ninja\Repositories\ClientRepository;
use App\Ninja\Repositories\InvoiceRepository;
use App\Ninja\Repositories\PaymentRepository;
use App\Ninja\Serializers\ArraySerializer;
use App\Models\Client;
use App\Models\Contact;

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

            if ($entityType === ENTITY_CLIENT) {
                $totalClients = count($reader->all()) + Client::scope()->withTrashed()->count();
                if ($totalClients > Auth::user()->getMaxNumClients()) {
                    throw new Exception(trans('texts.limit_clients', ['count' => Auth::user()->getMaxNumClients()]));
                }
            }

            $maps = $this->createMaps();
            
            $reader->each(function($row) use ($source, $entityType, $transformer, $maps) {
                if ($resource = $transformer->transform($row, $maps)) {
                    $data = $this->fractal->createData($resource)->toArray();

                    if ($this->validate($data, $entityType) !== true) {
                        return;
                    }

                    $entity = $this->{"{$entityType}Repo"}->save($data);

                    // if the invoice is paid we'll also create a payment record
                    if ($entityType === ENTITY_INVOICE && isset($data['paid']) && $data['paid']) {
                        $class = self::getTransformerClassName($source, ENTITY_PAYMENT);
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

    // looking for a better solution...
    // http://stackoverflow.com/questions/33781567/how-can-i-re-use-the-validation-code-in-my-laravel-formrequest-classes
    private function validate($data, $entityType)
    {
        if ($entityType === ENTITY_CLIENT) {
            $rules = [
                'contacts' => 'valid_contacts',
            ];
        } if ($entityType === ENTITY_INVOICE) {
            $rules = [
                'client.contacts' => 'valid_contacts',
                'invoice_items' => 'valid_invoice_items',
                'invoice_number' => 'required|unique:invoices,invoice_number,,id,account_id,'.Auth::user()->account_id,
                'discount' => 'positive',
            ];
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

    public function mapFile($filename)
    {
        require_once app_path().'/Includes/parsecsv.lib.php';
        $csv = new parseCSV();
        $csv->heading = false;
        $csv->auto($filename);

        if (count($csv->data) + Client::scope()->count() > Auth::user()->getMaxNumClients()) {
            throw new Exception(trans('texts.limit_clients', ['count' => Auth::user()->getMaxNumClients()]));
        }

        Session::put('data', $csv->data);

        $headers = false;
        $hasHeaders = false;
        $mapped = array();
        $columns = array('',
            Client::$fieldName,
            Client::$fieldPhone,
            Client::$fieldAddress1,
            Client::$fieldAddress2,
            Client::$fieldCity,
            Client::$fieldState,
            Client::$fieldPostalCode,
            Client::$fieldCountry,
            Client::$fieldNotes,
            Contact::$fieldFirstName,
            Contact::$fieldLastName,
            Contact::$fieldPhone,
            Contact::$fieldEmail,
        );

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
                    $map = array(
                        'first' => Contact::$fieldFirstName,
                        'last' => Contact::$fieldLastName,
                        'email' => Contact::$fieldEmail,
                        'mobile' => Contact::$fieldPhone,
                        'phone' => Client::$fieldPhone,
                        'name|organization' => Client::$fieldName,
                        'street|address|address1' => Client::$fieldAddress1,
                        'street2|address2' => Client::$fieldAddress2,
                        'city' => Client::$fieldCity,
                        'state|province' => Client::$fieldState,
                        'zip|postal|code' => Client::$fieldPostalCode,
                        'country' => Client::$fieldCountry,
                        'note' => Client::$fieldNotes,
                    );

                    foreach ($map as $search => $column) {
                        foreach (explode("|", $search) as $string) {
                            if (strpos($title, 'sec') === 0) {
                                continue;
                            }

                            if (strpos($title, $string) !== false) {
                                $mapped[$i] = $column;
                                break(2);
                            }
                        }
                    }
                }
            }
        }

        $data = array(
            'data' => $csv->data,
            'headers' => $headers,
            'hasHeaders' => $hasHeaders,
            'columns' => $columns,
            'mapped' => $mapped,
        );

        return $data;
    }

    public function importCSV($map, $hasHeaders)
    {
        $count = 0;
        $data = Session::get('data');
        $countries = Cache::get('countries');
        $countryMap = [];

        foreach ($countries as $country) {
            $countryMap[strtolower($country->name)] = $country->id;
        }

        foreach ($data as $row) {
            if ($hasHeaders) {
                $hasHeaders = false;
                continue;
            }

            $data = [
                'contacts' => [[]]
            ];

            foreach ($row as $index => $value) {
                $field = $map[$index];
                if ( ! $value = trim($value)) {
                    continue;
                }

                if ($field == Client::$fieldName) {
                    $data['name'] = $value;
                } elseif ($field == Client::$fieldPhone) {
                    $data['work_phone'] = $value;
                } elseif ($field == Client::$fieldAddress1) {
                    $data['address1'] = $value;
                } elseif ($field == Client::$fieldAddress2) {
                    $data['address2'] = $value;
                } elseif ($field == Client::$fieldCity) {
                    $data['city'] = $value;
                } elseif ($field == Client::$fieldState) {
                    $data['state'] = $value;
                } elseif ($field == Client::$fieldPostalCode) {
                    $data['postal_code'] = $value;
                } elseif ($field == Client::$fieldCountry) {
                    $value = strtolower($value);
                    $data['country_id'] = isset($countryMap[$value]) ? $countryMap[$value] : null;
                } elseif ($field == Client::$fieldNotes) {
                    $data['private_notes'] = $value;
                } elseif ($field == Contact::$fieldFirstName) {
                    $data['contacts'][0]['first_name'] = $value;
                } elseif ($field == Contact::$fieldLastName) {
                    $data['contacts'][0]['last_name'] = $value;
                } elseif ($field == Contact::$fieldPhone) {
                    $data['contacts'][0]['phone'] = $value;
                } elseif ($field == Contact::$fieldEmail) {
                    $data['contacts'][0]['email'] = strtolower($value);
                }
            }

            $rules = [
                'contacts' => 'valid_contacts',
            ];
            $validator = Validator::make($data, $rules);
            if ($validator->fails()) {
                continue;
            }

            $this->clientRepo->save($data);
            $count++;
        }

        Session::forget('data');

        return $count;
    }

}
