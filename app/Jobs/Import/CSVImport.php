<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Jobs\Import;

use App\Factory\ClientFactory;
use App\Factory\InvoiceFactory;
use App\Factory\PaymentFactory;
use App\Http\Requests\Invoice\StoreInvoiceRequest;
use App\Import\ImportException;
use App\Import\Transformers\BaseTransformer;
use App\Jobs\Mail\NinjaMailerJob;
use App\Jobs\Mail\NinjaMailerObject;
use App\Libraries\MultiDB;
use App\Mail\Import\ImportCompleted;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\Country;
use App\Models\Currency;
use App\Models\ExpenseCategory;
use App\Models\Invoice;
use App\Models\PaymentType;
use App\Models\Product;
use App\Models\Project;
use App\Models\TaxRate;
use App\Models\User;
use App\Models\Vendor;
use App\Repositories\BaseRepository;
use App\Repositories\ClientRepository;
use App\Repositories\InvoiceRepository;
use App\Repositories\PaymentRepository;
use App\Utils\Ninja;
use App\Utils\Traits\CleanLineItems;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use League\Csv\Reader;
use League\Csv\Statement;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;

class CSVImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, CleanLineItems;

    public $invoice;

    public $company;

    public $hash;

    public $import_type;

    public $skip_header;

    public $column_map;

    public $import_array;

    public $error_array = [];

    public $maps;

    public function __construct(array $request, Company $company)
    {
        $this->company = $company;
        $this->hash = $request['hash'];
        $this->import_type = $request['import_type'];
        $this->skip_header = $request['skip_header'] ?? null;
        $this->column_map =
            ! empty($request['column_map']) ?
                array_combine(array_keys($request['column_map']), array_column($request['column_map'], 'mapping')) : null;
    }

    /**
     * Execute the job.
     *
     *
     * @return void
     */
    public function handle()
    {
        MultiDB::setDb($this->company->db);

        Auth::login($this->company->owner(), true);

        auth()->user()->setCompany($this->company);

        $this->buildMaps();

        nlog('import '.$this->import_type);
        foreach (['client', 'product', 'invoice', 'payment', 'vendor', 'expense'] as $entityType) {
            $csvData = $this->getCsvData($entityType);

            if (! empty($csvData)) {
                $importFunction = 'import'.Str::plural(Str::title($entityType));
                $preTransformFunction = 'preTransform'.Str::title($this->import_type);

                if (method_exists($this, $preTransformFunction)) {
                    $csvData = $this->$preTransformFunction($csvData, $entityType);
                }

                if (empty($csvData)) {
                    continue;
                }

                if (method_exists($this, $importFunction)) {
                    // If there's an entity-specific import function, use that.
                    $this->$importFunction($csvData);
                } else {
                    // Otherwise, use the generic import function.
                    $this->importEntities($csvData, $entityType);
                }
            }
        }

        $data = [
            'errors'  => $this->error_array,
            'company' => $this->company,
        ];

        $nmo = new NinjaMailerObject;
        $nmo->mailable = new ImportCompleted($this->company, $data);
        $nmo->company = $this->company;
        $nmo->settings = $this->company->settings;
        $nmo->to_user = $this->company->owner();

        NinjaMailerJob::dispatch($nmo);
    }

    //////////////////////////////////////////////////////////////////////////////////////////////////////////////
    private function preTransformCsv($csvData, $entityType)
    {
        if (empty($this->column_map[$entityType])) {
            return false;
        }

        if ($this->skip_header) {
            array_shift($csvData);
        }

        //sort the array by key
        $keys = $this->column_map[$entityType];
        ksort($keys);

        $csvData = array_map(function ($row) use ($keys) {
            return array_combine($keys, array_intersect_key($row, $keys));
        }, $csvData);

        if ($entityType === 'invoice') {
            $csvData = $this->groupInvoices($csvData, 'invoice.number');
        }

        return $csvData;
    }

    private function preTransformFreshbooks($csvData, $entityType)
    {
        $csvData = $this->mapCSVHeaderToKeys($csvData);

        if ($entityType === 'invoice') {
            $csvData = $this->groupInvoices($csvData, 'Invoice #');
        }

        return $csvData;
    }

    private function preTransformInvoicely($csvData, $entityType)
    {
        $csvData = $this->mapCSVHeaderToKeys($csvData);

        return $csvData;
    }

    private function preTransformInvoice2go($csvData, $entityType)
    {
        $csvData = $this->mapCSVHeaderToKeys($csvData);

        return $csvData;
    }

    private function preTransformZoho($csvData, $entityType)
    {
        $csvData = $this->mapCSVHeaderToKeys($csvData);

        if ($entityType === 'invoice') {
            $csvData = $this->groupInvoices($csvData, 'Invoice Number');
        }

        return $csvData;
    }

    private function preTransformWaveaccounting($csvData, $entityType)
    {
        $csvData = $this->mapCSVHeaderToKeys($csvData);

        if ($entityType === 'invoice') {
            $csvData = $this->groupInvoices($csvData, 'Invoice Number');
        }

        return $csvData;
    }

    private function groupInvoices($csvData, $key)
    {
        // Group by invoice.
        $grouped = [];

        foreach ($csvData as $line_item) {
            if (empty($line_item[$key])) {
                $this->error_array['invoice'][] = ['invoice' => $line_item, 'error' => 'No invoice number'];
            } else {
                $grouped[$line_item[$key]][] = $line_item;
            }
        }

        return $grouped;
    }

    private function mapCSVHeaderToKeys($csvData)
    {
        $keys = array_shift($csvData);

        return array_map(function ($values) use ($keys) {
            return array_combine($keys, $values);
        }, $csvData);
    }

    //////////////////////////////////////////////////////////////////////////////////////////////////////////////
    private function importInvoices($invoices)
    {
        $invoice_transformer = $this->getTransformer('invoice');

        /** @var PaymentRepository $payment_repository */
        $payment_repository = app()->make(PaymentRepository::class);
        $payment_repository->import_mode = true;

        /** @var ClientRepository $client_repository */
        $client_repository = app()->make(ClientRepository::class);
        $client_repository->import_mode = true;

        $invoice_repository = new InvoiceRepository();
        $invoice_repository->import_mode = true;

        foreach ($invoices as $raw_invoice) {
            try {
                $invoice_data = $invoice_transformer->transform($raw_invoice);

                $invoice_data['line_items'] = $this->cleanItems($invoice_data['line_items'] ?? []);

                // If we don't have a client ID, but we do have client data, go ahead and create the client.
                if (empty($invoice_data['client_id']) && ! empty($invoice_data['client'])) {
                    $client_data = $invoice_data['client'];
                    $client_data['user_id'] = $this->getUserIDForRecord($invoice_data);

                    $client_repository->save(
                        $client_data,
                        $client = ClientFactory::create($this->company->id, $client_data['user_id'])
                    );
                    $invoice_data['client_id'] = $client->id;
                    unset($invoice_data['client']);
                }

                $validator = Validator::make($invoice_data, ( new StoreInvoiceRequest() )->rules());
                if ($validator->fails()) {
                    $this->error_array['invoice'][] =
                        ['invoice' => $invoice_data, 'error' => $validator->errors()->all()];
                } else {
                    $invoice = InvoiceFactory::create($this->company->id, $this->getUserIDForRecord($invoice_data));
                    if (! empty($invoice_data['status_id'])) {
                        $invoice->status_id = $invoice_data['status_id'];
                    }
                    $invoice_repository->save($invoice_data, $invoice);
                    $this->addInvoiceToMaps($invoice);

                    // If we're doing a generic CSV import, only import payment data if we're not importing a payment CSV.
                    // If we're doing a platform-specific import, trust the platform to only return payment info if there's not a separate payment CSV.
                    if ($this->import_type !== 'csv' || empty($this->column_map['payment'])) {
                        // Check for payment columns
                        if (! empty($invoice_data['payments'])) {
                            foreach ($invoice_data['payments'] as $payment_data) {
                                $payment_data['user_id'] = $invoice->user_id;
                                $payment_data['client_id'] = $invoice->client_id;
                                $payment_data['invoices'] = [
                                    [
                                        'invoice_id' => $invoice->id,
                                        'amount'     => $payment_data['amount'] ?? null,
                                    ],
                                ];

                                /* Make sure we don't apply any payments to invoices with a Zero Amount*/
                                if ($invoice->amount > 0) {
                                    $payment_repository->save(
                                        $payment_data,
                                        PaymentFactory::create($this->company->id, $invoice->user_id, $invoice->client_id)
                                    );
                                }
                            }
                        }
                    }

                    $this->actionInvoiceStatus($invoice, $invoice_data, $invoice_repository);
                }
            } catch (\Exception $ex) {
                if ($ex instanceof ImportException) {
                    $message = $ex->getMessage();
                } else {
                    report($ex);
                    $message = 'Unknown error';
                }

                $this->error_array['invoice'][] = ['invoice' => $raw_invoice, 'error' => $message];
            }
        }
    }

    private function actionInvoiceStatus($invoice, $invoice_data, $invoice_repository)
    {
        if (! empty($invoice_data['archived'])) {
            $invoice_repository->archive($invoice);
            $invoice->fresh();
        }

        if (! empty($invoice_data['viewed'])) {
            $invoice = $invoice->service()->markViewed()->save();
        }

        if ($invoice->status_id === Invoice::STATUS_DRAFT) {
        } elseif ($invoice->status_id === Invoice::STATUS_SENT) {
            $invoice = $invoice->service()->markSent()->save();
        } elseif ($invoice->status_id <= Invoice::STATUS_SENT && $invoice->amount > 0) {
            if ($invoice->balance <= 0) {
                $invoice->status_id = Invoice::STATUS_PAID;
                $invoice->save();
            } elseif ($invoice->balance != $invoice->amount) {
                $invoice->status_id = Invoice::STATUS_PARTIAL;
                $invoice->save();
            }
        }

        return $invoice;
    }

    private function importEntities($records, $entity_type)
    {
        $entity_type = Str::slug($entity_type, '_');
        $formatted_entity_type = Str::title($entity_type);

        $request_name = "\\App\\Http\\Requests\\${formatted_entity_type}\\Store${formatted_entity_type}Request";
        $repository_name = '\\App\\Repositories\\'.$formatted_entity_type.'Repository';
        $factoryName = '\\App\\Factory\\'.$formatted_entity_type.'Factory';

        /** @var BaseRepository $repository */
        $repository = app()->make($repository_name);
        $repository->import_mode = true;

        $transformer = $this->getTransformer($entity_type);

        foreach ($records as $record) {
            try {
                $entity = $transformer->transform($record);

                /** @var \App\Http\Requests\Request $request */
                // $request = new $request_name();
                // $request->prepareForValidation();

                // Pass entity data to request so it can be validated
                // $request->query = $request->request = new ParameterBag( $entity );
                // $validator = Validator::make( $entity, $request->rules() );
                $validator = $request_name::runFormRequest($entity);

                if ($validator->fails()) {
                    $this->error_array[$entity_type][] =
                        [$entity_type => $record, 'error' => $validator->errors()->all()];
                } else {
                    $entity =
                        $repository->save(
                            array_diff_key($entity, ['user_id' => false]),
                            $factoryName::create($this->company->id, $this->getUserIDForRecord($entity)));

                    $entity->save();
                    if (method_exists($this, 'add'.$formatted_entity_type.'ToMaps')) {
                        $this->{'add'.$formatted_entity_type.'ToMaps'}($entity);
                    }
                }
            } catch (\Exception $ex) {
                if ($ex instanceof ImportException) {
                    $message = $ex->getMessage();
                } else {
                    report($ex);
                    $message = 'Unknown error';
                }

                $this->error_array[$entity_type][] = [$entity_type => $record, 'error' => $message];
            }
        }
    }

    /**
     * @param $entity_type
     *
     * @return BaseTransformer
     */
    private function getTransformer($entity_type)
    {
        $formatted_entity_type = Str::title($entity_type);
        $formatted_import_type = Str::title($this->import_type);
        $transformer_name =
            '\\App\\Import\\Transformers\\'.$formatted_import_type.'\\'.$formatted_entity_type.'Transformer';

        return new $transformer_name($this->maps);
    }

    //////////////////////////////////////////////////////////////////////////////////////////////////////////////
    private function buildMaps()
    {
        $this->maps = [
            'company'            => $this->company,
            'client'             => [],
            'contact'            => [],
            'invoice'            => [],
            'invoice_client'     => [],
            'product'            => [],
            'countries'          => [],
            'countries2'         => [],
            'currencies'         => [],
            'client_ids'         => [],
            'invoice_ids'        => [],
            'vendors'            => [],
            'expense_categories' => [],
            'payment_types'      => [],
            'tax_rates'          => [],
            'tax_names'          => [],
        ];

        Client::where('company_id', $this->company->id)->cursor()->each(function ($client) {
            $this->addClientToMaps($client);
        });

        ClientContact::where('company_id', $this->company->id)->cursor()->each(function ($contact) {
            $this->addContactToMaps($contact);
        });

        Invoice::where('company_id', $this->company->id)->cursor()->each(function ($invoice) {
            $this->addInvoiceToMaps($invoice);
        });

        Product::where('company_id', $this->company->id)->cursor()->each(function ($product) {
            $this->addProductToMaps($product);
        });

        Project::where('company_id', $this->company->id)->cursor()->each(function ($project) {
            $this->addProjectToMaps($project);
        });

        Country::all()->each(function ($country) {
            $this->maps['countries'][strtolower($country->name)] = $country->id;
            $this->maps['countries2'][strtolower($country->iso_3166_2)] = $country->id;
        });

        Currency::all()->each(function ($currency) {
            $this->maps['currencies'][strtolower($currency->code)] = $currency->id;
        });

        PaymentType::all()->each(function ($payment_type) {
            $this->maps['payment_types'][strtolower($payment_type->name)] = $payment_type->id;
        });

        Vendor::where('company_id', $this->company->id)->cursor()->each(function ($vendor) {
            $this->addVendorToMaps($vendor);
        });

        ExpenseCategory::where('company_id', $this->company->id)->cursor()->each(function ($category) {
            $this->addExpenseCategoryToMaps($category);
        });

        TaxRate::where('company_id', $this->company->id)->cursor()->each(function ($taxRate) {
            $name = trim(strtolower($taxRate->name));
            $this->maps['tax_rates'][$name] = $taxRate->rate;
            $this->maps['tax_names'][$name] = $taxRate->name;
        });
    }

    /**
     * @param Invoice $invoice
     */
    private function addInvoiceToMaps(Invoice $invoice)
    {
        if ($number = strtolower(trim($invoice->number))) {
            $this->maps['invoices'][$number] = $invoice;
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
        if ($client->contacts->count()) {
            $contact = $client->contacts[0];
            if ($email = strtolower(trim($contact->email))) {
                $this->maps['client'][$email] = $client->id;
            }
            if ($name = strtolower(trim($contact->first_name.' '.$contact->last_name))) {
                $this->maps['client'][$name] = $client->id;
            }
            $this->maps['client_ids'][$client->public_id] = $client->id;
        }
    }

    /**
     * @param ClientContact $contact
     */
    private function addContactToMaps(ClientContact $contact)
    {
        if ($key = strtolower(trim($contact->email))) {
            $this->maps['contact'][$key] = $contact;
        }
    }

    /**
     * @param Product $product
     */
    private function addProductToMaps(Product $product)
    {
        if ($key = strtolower(trim($product->product_key))) {
            $this->maps['product'][$key] = $product;
        }
    }

    /**
     * @param Project $project
     */
    private function addProjectToMaps(Project $project)
    {
        if ($key = strtolower(trim($project->name))) {
            $this->maps['project'][$key] = $project;
        }
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

    private function getUserIDForRecord($record)
    {
        if (! empty($record['user_id'])) {
            return $this->findUser($record['user_id']);
        } else {
            return $this->company->owner()->id;
        }
    }

    private function findUser($user_hash)
    {
        $user = User::where('account_id', $this->company->account->id)
                    ->where(\DB::raw('CONCAT_WS(" ", first_name, last_name)'), 'like', '%'.$user_hash.'%')
                    ->first();

        if ($user) {
            return $user->id;
        } else {
            return $this->company->owner()->id;
        }
    }

    private function getCsvData($entityType)
    {
        $base64_encoded_csv = Cache::pull($this->hash.'-'.$entityType);
        if (empty($base64_encoded_csv)) {
            return null;
        }

        $csv = base64_decode($base64_encoded_csv);
        $csv = Reader::createFromString($csv);

        $stmt = new Statement();
        $data = iterator_to_array($stmt->process($csv));

        if (count($data) > 0) {
            $headers = $data[0];

            // Remove Invoice Ninja headers
            if (count($headers) && count($data) > 4 && $this->import_type === 'csv') {
                $firstCell = $headers[0];
                if (strstr($firstCell, config('ninja.app_name'))) {
                    array_shift($data); // Invoice Ninja...
                    array_shift($data); // <blank line>
                    array_shift($data); // Enitty Type Header
                }
            }
        }

        return $data;
    }
}
