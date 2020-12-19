<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Jobs\Import;

use App\Factory\ClientFactory;
use App\Factory\InvoiceFactory;
use App\Factory\ProductFactory;
use App\Http\Requests\Client\StoreClientRequest;
use App\Http\Requests\Invoice\StoreInvoiceRequest;
use App\Http\Requests\Product\StoreProductRequest;
use App\Import\Transformers\ClientTransformer;
use App\Import\Transformers\InvoiceTransformer;
use App\Import\Transformers\ProductTransformer;
use App\Libraries\MultiDB;
use App\Models\Client;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\User;
use App\Repositories\ClientContactRepository;
use App\Repositories\ClientRepository;
use App\Repositories\InvoiceRepository;
use App\Repositories\ProductRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use League\Csv\Reader;
use League\Csv\Statement;

class CSVImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $invoice;

    public $company;

    public $hash;

    public $entity_type;

    public $skip_header;

    public $column_map;

    public $import_array;

    public $error_array;

    public $maps;

    public function __construct(array $request, Company $company)
    {
        $this->company = $company;

        $this->hash = $request['hash'];

        $this->entity_type = $request['entity_type'];

        $this->skip_header = $request['skip_header'];

        $this->column_map = $request['column_map'];
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

        $this->company->owner()->setCompany($this->company);
        Auth::login($this->company->owner(), true);

        $this->buildMaps();

        //sort the array by key
        ksort($this->column_map);

        info("import".ucfirst($this->entity_type));
        $this->{"import".ucfirst($this->entity_type)}();

    }

    public function failed($exception)
    {
    }

    //////////////////////////////////////////////////////////////////////////////////////////////////////////////
    
    private function importProduct()
    {
        info("importing products");
        $product_repository = new ProductRepository();
        $product_transformer = new ProductTransformer($this->maps);

        $records = $this->getCsvData();

        if ($this->skip_header) 
            array_shift($records);

        foreach ($records as $record) 
        {
            $keys = $this->column_map;
            $values = array_intersect_key($record, $this->column_map);
            
            $product_data = array_combine($keys, $values);

            $product = $product_transformer->transform($product_data);

            $validator = Validator::make($product, (new StoreProductRequest())->rules());

            if ($validator->fails()) {
                $this->error_array[] = ['product' => $product, 'error' => json_encode($validator->errors())];
            } else {
                $product = $product_repository->save($product, ProductFactory::create($this->company->id, $this->setUser($record)));

                $product->save();

                $this->maps['products'][] = $product->id;
            }
        }
    }

    private function importInvoice()
    {
        info("import invoices");

        $records = $this->getCsvData();

        $invoice_number_key = array_search('Invoice Number', reset($records));

        info("number key = {$invoice_number_key}");
        
        $unique_array_filter = array_unique(array_column($records, 'Invoice Number'));

info('unique array_filter');
info(print_r($unique_array_filter,1));

        $unique_invoices = array_intersect_key( $records, $unique_array_filter );

info("unique invoices");

info(print_r($unique_invoices,1));

        $invoice = $invoice_transformer->transform(reset($invoices));

        foreach($unique_invoices as $val) {

            $invoices = array_filter($arr, function($item) use ($val){
             return $item['Invoice Number'] == $val['Invoice Number'];
            });

            $this->processInvoice($invoices);
        }

    }

    private function processInvoices($invoices)
    {

        $invoice_repository = new InvoiceRepository();
        $invoice_transformer = new InvoiceTransformer($this->maps);

        $items = [];

info("invoice = ");
info(print_r($invoice,1));

        foreach($invoices as $record)
        {
            $keys = $this->column_map;

            $item_keys = [
                    36 => 'item.quantity',
                    37 => 'item.cost',
                    38 => 'item.product_key',
                    39 => 'item.notes',
                    40 => 'item.discount',
                    41 => 'item.is_amount_discount',
                    42 => 'item.tax_name1',
                    43 => 'item.tax_rate1',
                    44 => 'item.tax_name2',
                    45 => 'item.tax_rate2',
                    46 => 'item.tax_name3',
                    47 => 'item.tax_rate3',
                    48 => 'item.custom_value1',
                    49 => 'item.custom_value2',
                    50 => 'item.custom_value3',
                    51 => 'item.custom_value4',
                    52 => 'item.type_id',
                ];

            $values = array_intersect_key($record, $item_keys);
            
            $items[] = array_combine($keys, $values);

        }

info("items");
info(print_r($items,1));

        $invoice->line_items = $items;

            $validator = Validator::make($invoice, (new StoreInvoiceRequest())->rules());

            if ($validator->fails()) {
                $this->error_array[] = ['product' => $invoice, 'error' => json_encode($validator->errors())];
            } else {
                $invoice = $invoice_repository->save($invoice, InvoiceFactory::create($this->company->id, $this->setUser($record)));

                $invoice->save();

                $this->maps['invoices'][] = $invoice->id;

                $this->performInvoiceActions($invoice, $record, $invoice_repository);
            }
    }

    private function performInvoiceActions($invoice, $record, $invoice_repository)
    {

        $invoice = $this->actionInvoiceStatus($invoice, $record, $invoice_repository);
    }

    private function actionInvoiceStatus($invoice, $status, $invoice_repository)
    {
        switch ($status) {
            case 'Archived':
                $invoice_repository->archive($invoice);
                $invoice->fresh();
                break;
            case 'Sent':
                $invoice = $invoice->service()->markSent()->save();
                break;
            case 'Viewed';
                $invoice = $invoice->service()->markSent()->save();
                break;
            default:
                # code...
                break;
        }

        if($invoice->balance < $invoice->amount && $invoice->status_id <= Invoice::STATUS_SENT){
            $invoice->status_id = Invoice::STATUS_PARTIAL;
            $invoice->save();
        }

        return $invoice;
    }

    //todo limit client imports for hosted version
    private function importClient()
    {
        //clients
        $records = $this->getCsvData();

        $contact_repository = new ClientContactRepository();
        $client_repository = new ClientRepository($contact_repository);
        $client_transformer = new ClientTransformer($this->maps);

        if ($this->skip_header) 
            array_shift($records);

        foreach ($records as $record) {

            $keys = $this->column_map;
            $values = array_intersect_key($record, $this->column_map);

            $client_data = array_combine($keys, $values);

            $client = $client_transformer->transform($client_data);

            $validator = Validator::make($client, (new StoreClientRequest())->rules());

            if ($validator->fails()) {
                $this->error_array[] = ['client' => $client, 'error' => json_encode($validator->errors())];
            } else {
                $client = $client_repository->save($client, ClientFactory::create($this->company->id, $this->setUser($record)));

                if (array_key_exists('client.balance', $client_data)) {
                    $client->balance = preg_replace('/[^0-9,.]+/', '', $client_data['client.balance']);
                }

                if (array_key_exists('client.paid_to_date', $client_data)) {
                    $client->paid_to_date = preg_replace('/[^0-9,.]+/', '', $client_data['client.paid_to_date']);
                }

                $client->save();

                $this->maps['clients'][] = $client->id;
            }
        }
    }

    //////////////////////////////////////////////////////////////////////////////////////////////////////////////
    private function buildMaps()
    {
        $this->maps['currencies'] = Currency::all();
        $this->maps['users'] = $this->company->users;
        $this->maps['company'] = $this->company;
        $this->maps['clients'] = [];
        $this->maps['products'] = [];

        return $this;
    }


    private function setUser($record)
    {
        $user_key_exists = array_search('client.user_id', $this->column_map);

        if ($user_key_exists) {
            return $this->findUser($record[$user_key_exists]);
        } else {
            return $this->company->owner()->id;
        }
    }

    private function findUser($user_hash)
    {
        $user = User::where('company_id', $this->company->id)
                    ->where(\DB::raw('CONCAT_WS(" ", first_name, last_name)'), 'like', '%' . $user_hash . '%')
                    ->first();

        if ($user) {
            return $user->id;
        } else {
            return $this->company->owner()->id;
        }
    }

    private function getCsvData()
    {
        $base64_encoded_csv = Cache::get($this->hash);
        $csv = base64_decode($base64_encoded_csv);
        $csv = Reader::createFromString($csv);

        $stmt = new Statement();
        $data = iterator_to_array($stmt->process($csv));

        if (count($data) > 0) {
            $headers = $data[0];

            // Remove Invoice Ninja headers
            if (count($headers) && count($data) > 4) {
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
