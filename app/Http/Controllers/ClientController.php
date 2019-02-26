<?php

namespace App\Http\Controllers;

use App\DataMapper\ClientSettings;
use App\Datatables\ClientDatatable;
use App\Datatables\MakesActionMenu;
use App\Factory\ClientFactory;
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
use App\Utils\Traits\MakesMenu;
use App\Utils\Traits\UserSessionAttributes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;


/**
 * Class ClientController
 * @package App\Http\Controllers
 */
class ClientController extends Controller
{
    use UserSessionAttributes;
    use MakesHash;
    use MakesMenu;
    use MakesActionMenu;

    /**
     * @var ClientRepository
     */
    protected $clientRepo;

    /**
     * @var ClientDatatable
     */
    protected $clientDatatable;

    /**
     * ClientController constructor.
     * @param ClientRepository $clientRepo
     * @param ClientDatatable $clientDatatable
     */
    public function __construct(ClientRepository $clientRepo, ClientDatatable $clientDatatable)
    {

        $this->clientRepo = $clientRepo;

        $this->clientDatatable = $clientDatatable;

    }

    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Http\JsonResponse|\Illuminate\View\View
     */
    public function index()
    {
        if(request('page'))
            return $this->clientDatatable->query(request(), $this->getCurrentCompanyId());

        $data = [
            'datatable' => $this->clientDatatable->buildOptions(),
            'listaction' => $this->clientDatatable->listActions()
        ];

        return view('client.vue_list', $data);

    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(ShowClientRequest $request, Client $client)
    {

        $requested_view_statement_actions = [
            'create_invoice_client_id', 
            'create_task_client_id', 
            'create_quote_client_id', 
            'create_recurring_invoice_client_id', 
            'create_payment_client_id', 
            'create_expense_client_id'
        ];

       $data = [
            'client' => $client,
            'company' => auth()->user()->company(),
            'meta' => collect([
                'google_maps_api_key' => config('ninja.google_maps_api_key'),
                'edit_client_permission' => auth()->user()->can('edit', $client),
                'edit_client_route' => $this->processActionsForButton(['edit_client_client_id'], $client),
                'view_statement_permission' => auth()->user()->can('view', $client),
                'view_statement_route' => $this->processActionsForButton(['view_statement_client_id'], $client),
                'view_statement_actions' => $this->processActionsForButton($requested_view_statement_actions, $client)
            ])
        ];

        return view('client.show', $data);

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
        'settings' => collect(ClientSettings::buildClientSettings(auth()->user()->company()->settings_object, $client->client_settings_object)),
        'pills' => $this->makeEntityTabMenu(Client::class),
        'hashed_id' => $this->encodePrimarykey($client->id),
        'company' => auth()->user()->company(),
        'sizes' => Size::all(),
        ];

        return view('client.edit', $data);

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
        $client = ClientFactory::create($this->getCurrentCompanyId(), auth()->user()->id);

        $data = [
            'client' => $client,
            'hashed_id' => '',
            'countries' => Country::all()
        ];

        return view('client.create', $data);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreClientRequest $request)
    {

        $client = StoreClient::dispatchNow($request, new Client);

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
    public function destroy($id)
    {
        //
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
