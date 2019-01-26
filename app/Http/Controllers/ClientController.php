<?php

namespace App\Http\Controllers;

use App\Datatables\ClientDatatable;
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
use App\Repositories\ClientRepository;
use App\Utils\Traits\MakesHash;
use App\Utils\Traits\MakesMenu;
use App\Utils\Traits\UserSessionAttributes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Yajra\DataTables\Facades\DataTables;
use Yajra\DataTables\Html\Builder;

class ClientController extends Controller
{
    use UserSessionAttributes;
    use MakesHash;
    use MakesMenu;

    protected $clientRepo;

    protected $clientDatatable;

    public function __construct(ClientRepository $clientRepo, ClientDatatable $clientDatatable)
    {
        $this->clientRepo = $clientRepo;
        $this->clientDatatable = $clientDatatable;
    }

    public function index()
    {
;
        if(request('page'))
            return $this->clientDatatable->query(request(), $this->getCurrentCompanyId());

        $data = [
            'datatable' => $this->clientDatatable->buildOptions(),
            'listaction' => $this->clientDatatable->listActions()
        ];

        return view('client.vue_list', $data);

    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(CreateClientRequest $request)
    {
        $client = new Client;
        $client->name = '';
        $client->company_id = $this->getCurrentCompanyId();
        $client_contact = new ClientContact;
        $client_contact->first_name = "";
        $client_contact->user_id = auth()->user()->id;
        $client_contact->company_id = $this->getCurrentCompanyId();
        $client_contact->id = 0;

        $client->contacts->add($client_contact);

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

/*
        $data = [
        'client' => $client,
        'hashed_id' => $this->encodePrimarykey($client->id)
        ];

        Log::error(print_r($client,1));
*/
        return response()->json($client, 200);

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
            'company' => auth()->user()->company()
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
        'settings' => [],
        'pills' => $this->makeEntityTabMenu(Client::class),
        'hashed_id' => $this->encodePrimarykey($client->id),
        'countries' => Country::all(),
        'company' => auth()->user()->company()
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



}
