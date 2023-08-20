<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Controllers;

use App\Utils\Ninja;
use App\Models\Client;
use App\Models\Account;
use Illuminate\Http\Response;
use App\Factory\ClientFactory;
use App\Filters\ClientFilters;
use App\Utils\Traits\MakesHash;
use App\Utils\Traits\Uploadable;
use App\Utils\Traits\BulkOptions;
use App\Jobs\Client\UpdateTaxData;
use App\Utils\Traits\SavesDocuments;
use App\Repositories\ClientRepository;
use App\Events\Client\ClientWasCreated;
use App\Events\Client\ClientWasUpdated;
use App\Transformers\ClientTransformer;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\Client\BulkClientRequest;
use App\Http\Requests\Client\EditClientRequest;
use App\Http\Requests\Client\ShowClientRequest;
use App\Http\Requests\Client\PurgeClientRequest;
use App\Http\Requests\Client\StoreClientRequest;
use App\Http\Requests\Client\CreateClientRequest;
use App\Http\Requests\Client\UpdateClientRequest;
use App\Http\Requests\Client\UploadClientRequest;
use App\Http\Requests\Client\DestroyClientRequest;

/**
 * Class ClientController.
 * @covers App\Http\Controllers\ClientController
 */
class ClientController extends BaseController
{
    use MakesHash;
    use Uploadable;
    use BulkOptions;
    use SavesDocuments;

    protected $entity_type = Client::class;

    protected $entity_transformer = ClientTransformer::class;

    /**
     * @var ClientRepository
     */
    protected $client_repo;

    /**
     * ClientController constructor.
     * @param ClientRepository $client_repo
     */
    public function __construct(ClientRepository $client_repo)
    {
        parent::__construct();

        $this->client_repo = $client_repo;
    }

    /**
     * 
     * @param ClientFilters $filters
     * @return Response
     * 
     */
    public function index(ClientFilters $filters)
    {
        set_time_limit(45);

        $clients = Client::filter($filters);

        return $this->listResponse($clients);
    }

    /**
     * Display the specified resource.
     *
     * @param ShowClientRequest $request
     * @param Client $client
     * @return Response
     *
     */
    public function show(ShowClientRequest $request, Client $client)
    {
        return $this->itemResponse($client);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param EditClientRequest $request
     * @param Client $client
     * @return Response
     *
     */
    public function edit(EditClientRequest $request, Client $client)
    {
        return $this->itemResponse($client);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UpdateClientRequest $request
     * @param Client $client
     * @return Response
     *
     */
    public function update(UpdateClientRequest $request, Client $client)
    {
        if ($request->entityIsDeleted($client)) {
            return $request->disallowUpdate();
        }

        $client = $this->client_repo->save($request->all(), $client);

        $this->uploadLogo($request->file('company_logo'), $client->company, $client);

        event(new ClientWasUpdated($client, $client->company, Ninja::eventVars(auth()->user() ? auth()->user()->id : null)));

        return $this->itemResponse($client->fresh());
    }

    /**
     * Show the form for creating a new resource.
     *
     * @param CreateClientRequest $request
     * @return Response
     *
     */
    public function create(CreateClientRequest $request)
    {
        $client = ClientFactory::create(auth()->user()->company()->id, auth()->user()->id);

        return $this->itemResponse($client);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param StoreClientRequest $request
     * @return Response
     *
     */
    public function store(StoreClientRequest $request)
    {
        $client = $this->client_repo->save($request->all(), ClientFactory::create(auth()->user()->company()->id, auth()->user()->id));

        $client->load('contacts', 'primary_contact');

        /* Set the client country to the company if none is set */
        if (! $client->country_id && strlen($client->company->settings->country_id) > 1) {
            $client->update(['country_id' => $client->company->settings->country_id]);
        }

        $this->uploadLogo($request->file('company_logo'), $client->company, $client);

        event(new ClientWasCreated($client, $client->company, Ninja::eventVars(auth()->user() ? auth()->user()->id : null)));

        return $this->itemResponse($client);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param DestroyClientRequest $request
     * @param Client $client
     * @return Response
     *
     * @throws \Exception
     */
    public function destroy(DestroyClientRequest $request, Client $client)
    {
        $this->client_repo->delete($client);

        return $this->itemResponse($client->fresh());
    }

    /**
     * Perform bulk actions on the list view.
     *
     * @return Response
     *
     */
    public function bulk(BulkClientRequest $request)
    {
        $action = $request->action;

        /** @var \App\Models\User $user */
        $user = auth()->user();

        $clients = Client::withTrashed()
                         ->company()
                         ->whereIn('id', $request->ids)
                         ->cursor()
                         ->each(function ($client) use ($action, $user) {
                             if ($user->can('edit', $client)) {
                                 $this->client_repo->{$action}($client);
                             }
                         });

        return $this->listResponse(Client::withTrashed()->company()->whereIn('id', $request->ids));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UploadClientRequest $request
     * @param Client $client
     * @return Response
     *
     */
    public function upload(UploadClientRequest $request, Client $client)
    {
        if (! $this->checkFeature(Account::FEATURE_DOCUMENTS)) {
            return $this->featureFailure();
        }

        if ($request->has('documents')) {
            $this->saveDocuments($request->file('documents'), $client, $request->input('is_public', true));
        }

        return $this->itemResponse($client->fresh());
    }

    /**
     * Update the specified resource in storage.
     *
     * @param PurgeClientRequest $request
     * @param Client $client
     * @return \Illuminate\Http\JsonResponse
     *
     */
    public function purge(PurgeClientRequest $request, Client $client)
    {
        //delete all documents
        $client->documents->each(function ($document) {
            try {
                Storage::disk(config('filesystems.default'))->delete($document->url);
            } catch(\Exception $e) {
                nlog($e->getMessage());
            }
        });

        //force delete the client
        $this->client_repo->purge($client);

        return response()->json(['message' => 'Success'], 200);

        //todo add an event here using the client name as reference for purge event
    }

/**
     * Update the specified resource in storage.
     *
     * @param PurgeClientRequest $request
     * @param Client $client
     * @param string $mergeable_client
     * @return \Illuminate\Http\JsonResponse
     *
     */

    public function merge(PurgeClientRequest $request, Client $client, string $mergeable_client)
    {
        $m_client = Client::withTrashed()
                            ->where('id', $this->decodePrimaryKey($mergeable_client))
                            ->where('company_id', auth()->user()->company()->id)
                            ->first();

        if (!$m_client) {
            return response()->json(['message' => "Client not found"]);
        }

        $merged_client = $client->service()->merge($m_client)->save();

        return $this->itemResponse($merged_client);
    }
    
    /**
     * Updates the client's tax data
     *
     * @param  PurgeClientRequest $request
     * @param  Client $client
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateTaxData(PurgeClientRequest $request, Client $client)
    {
        (new UpdateTaxData($client, $client->company))->handle();
        
        return $this->itemResponse($client->fresh());
    }
}
