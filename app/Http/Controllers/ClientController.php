<?php

namespace App\Http\Controllers;

use App\DataMapper\ClientSettings;
use App\Factory\ClientFactory;
use App\Filters\ClientFilters;
use App\Http\Requests\Client\CreateClientRequest;
use App\Http\Requests\Client\EditClientRequest;
use App\Http\Requests\Client\ShowClientRequest;
use App\Http\Requests\Client\StoreClientRequest;
use App\Http\Requests\Client\UpdateClientRequest;
use App\Jobs\Client\StoreClient;
use App\Jobs\Client\UpdateClient;
use App\Jobs\Entity\ActionEntity;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Size;
use App\Repositories\ClientRepository;
use App\Utils\Traits\MakesHash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Transformers\ClientTransformer;

/**
 * Class ClientController
 * @package App\Http\Controllers
 */
class ClientController extends BaseController
{
    use MakesHash;

    protected $entityType = Client::class;

    protected $entityTransformer = ClientTransformer::class;

    /**
     * @var ClientRepository
     */
    protected $clientRepo;

    /**
     * ClientController constructor.
     * @param ClientRepository $clientRepo
     */
    public function __construct(ClientRepository $clientRepo)
    {
        parent::__construct();

        $this->clientRepo = $clientRepo;

    }

    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Http\JsonResponse|\Illuminate\View\View
     */
    public function index(ClientFilters $filters)
    {
        
        $clients = Client::filter($filters);
        
        return $this->listResponse($clients);

    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(ShowClientRequest $request, Client $client)
    {

       $data = [
            'client' => $client,
            'company' => $client->company(),
            'meta' => collect([
                'google_maps_api_key' => config('ninja.google_maps_api_key')
            ])
        ];

        return redirect()->route('clients.edit', ['id' => $this->encodePrimarykey($client->id)]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(EditClientRequest $request, Client $client)
    {

        $data = [
        'client' => $client,
        'hashed_id' => $this->encodePrimarykey($client->id),
        'company' => $client->company(),
        'sizes' => Size::all(),
        ];

        return response()->json($data);

    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  App\Models\Client $client
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateClientRequest $request, Client $client)
    {

        $client = UpdateClient::dispatchNow($request, $client);

        return response()->json($client, 200);

    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(CreateClientRequest $request)
    {
        $client = ClientFactory::create(auth()->user()->company()->id, auth()->user()->id);

        $data = [
            'client' => $client,
            'hashed_id' => '',
            'countries' => Country::all()
        ];

        return response()->json($data);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreClientRequest $request)
    {

        $client = StoreClient::dispatchNow($request, ClientFactory::create(auth()->user()->company()->id, auth()->user()->id));

        $client->load('contacts', 'primary_contact');

        $client->hashed_id = $this->encodePrimarykey($client->id);

        return response()->json($client, 200);

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Client $client)
    {
        $client->delete();

        return response()->json([], 200);
    }

    /**
     * Perform bulk actions on the list view
     * 
     * @return Collection
     */
    public function bulk()
    {

        $action = request()->input('action');
        
        $ids = request()->input('ids');

        $clients = Client::withTrashed()->find($ids);

        $clients->each(function ($client, $key) use($action){

            if(auth()->user()->can('edit', $client))
                ActionEntity::dispatchNow($client, $action);

        });

        //todo need to return the updated dataset
        return response()->json('success', 200);
        
    }

    /**
     * Returns a client statement
     * 
     * @return [type] [description]
     */
    public function statement()
    {
        //todo
    }



}
