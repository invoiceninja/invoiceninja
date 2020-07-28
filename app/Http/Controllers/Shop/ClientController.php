<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Http\Controllers\Shop;

use App\Events\Client\ClientWasCreated;
use App\Factory\ClientFactory;
use App\Http\Controllers\BaseController;
use App\Http\Requests\Client\StoreClientRequest;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\CompanyToken;
use App\Repositories\ClientRepository;
use App\Transformers\ClientTransformer;
use App\Utils\Ninja;
use App\Utils\Traits\MakesHash;
use Illuminate\Http\Request;

class ClientController extends BaseController
{
    use MakesHash;
    
    protected $entity_type = Client::class;

    protected $entity_transformer = ClientTransformer::class;

    /**
     * @var ClientRepository
     */
    protected $client_repo;

    /**
     * ClientController constructor.
     * @param ClientRepository $clientRepo
     */
    public function __construct(ClientRepository $client_repo)
    {
        parent::__construct();

        $this->client_repo = $client_repo;
    }

    public function show(Request $request, string $contact_key)
    {
        $company = Company::where('company_key', $request->header('X-API-COMPANY_KEY'))->first();

        if(!$company->enable_shop_api)
            return response()->json(['message' => 'Shop is disabled', 'errors' => []],403);

        $contact = ClientContact::with('client')
                            ->where('company_id', $company->id)
                            ->where('contact_key', $contact_key)
                            ->firstOrFail();

        return $this->itemResponse($contact->client);
    }

    public function store(StoreClientRequest $request)
    {
        $company = Company::where('company_key', $request->header('X-API-COMPANY_KEY'))->first();

        if(!$company->enable_shop_api)
            return response()->json(['message' => 'Shop is disabled', 'errors' => []],403);
        
        app('queue')->createPayloadUsing(function () use ($company) {
            return ['db' => $company->db];
        });

        $client = $this->client_repo->save($request->all(), ClientFactory::create($company->id, $company->owner()->id));

        $client->load('contacts', 'primary_contact');

        $this->uploadLogo($request->file('company_logo'), $company, $client);

        event(new ClientWasCreated($client, $company, Ninja::eventVars()));

        return $this->itemResponse($client);
    }
}
