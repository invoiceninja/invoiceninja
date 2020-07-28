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

    public function show(string $contact_key)
    {
        $company_token = CompanyToken::with(['company'])->whereRaw("BINARY `token`= ?", [$request->header('X-API-TOKEN')])->first();

        $contact = ClientContact::with('client')
                            ->where('company_id', $company_token->company->id)
                            ->where('contact_key', $contact_key)
                            ->firstOrFail();

        return $this->itemResponse($contact->client);
    }

    public function store(StoreClientRequest $request)
    {
        $company_token = CompanyToken::with(['company'])->whereRaw("BINARY `token`= ?", [$request->header('X-API-TOKEN')])->first();

        $client = $this->client_repo->save($request->all(), ClientFactory::create($company_token->company_id, $company_token->user_id));

        $client->load('contacts', 'primary_contact');

        $this->uploadLogo($request->file('company_logo'), $client->company, $client);

        event(new ClientWasCreated($client, $client->company, Ninja::eventVars()));

        return $this->itemResponse($client);
    }
}
