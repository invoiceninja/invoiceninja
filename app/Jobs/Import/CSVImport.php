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
use App\Factory\ProductFactory;
use App\Http\Requests\Client\StoreClientRequest;
use App\Http\Requests\Product\StoreProductRequest;
use App\Import\Transformers\ClientTransformer;
use App\Import\Transformers\ProductTransformer;
use App\Libraries\MultiDB;
use App\Models\Client;
use App\Models\Company;
use App\Models\Currency;
use App\Models\User;
use App\Repositories\ClientContactRepository;
use App\Repositories\ClientRepository;
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

        $this->{"import".ucfirst($this->entity_type)}();
    }

    public function failed($exception)
    {
    }

    //////////////////////////////////////////////////////////////////////////////////////////////////////////////
    
    private function importProduct()
    {
        $product_repository = new ProductRepository();
        $product_transformer new ProductTransformer($this->maps);

        $records = $this->getCsvData();

        if ($this->skip_header) 
            array_shift($records);

        foreach ($records as $record) 
        {
            $keys = $this->column_map;
            $values = array_intersect_key($record, $this->column_map);

            $product_data = array_combine($keys, $values);

            $product = $product_transformer->transform($product_data);

            $validator = Validator::make($client, (new StoreProductRequest())->rules());

            if ($validator->fails()) {
                $this->error_array[] = ['product' => $product, 'error' => json_encode($validator->errors())];
            } else {
                $product = $product_repository->save($product, ProductFactory::create($this->company->id, $this->setUser($record)));

                $product->save();

                $this->maps['products'][] = $product->id;
            }
        }
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
