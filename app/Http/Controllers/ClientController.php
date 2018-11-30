<?php

namespace App\Http\Controllers;

use App\Http\Requests\Client\EditClientRequest;
use App\Http\Requests\Client\UpdateClientRequest;
use App\Jobs\Client\UpdateClient;
use App\Models\Client;
use App\Repositories\ClientRepository;
use App\Utils\Traits\MakesHash;
use App\Utils\Traits\UserSessionAttributes;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;
use Yajra\DataTables\Html\Builder;

class ClientController extends Controller
{
    use UserSessionAttributes;
    use MakesHash;

    protected $clientRepo;

    public function __construct(ClientRepository $clientRepo)
    {
        $this->clientRepo = $clientRepo;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Builder $builder)
    {
        if (request()->ajax()) {

            /*
            $clients = Client::query('clients.*', DB::raw("CONCAT(client_contacts.first_name,' ',client_contacts.last_name) as full_name"), 'client_contacts.email')
                ->leftJoin('client_contacts', function($leftJoin)
                {
                    $leftJoin->on('clients.id', '=', 'client_contacts.client_id')
                        ->where('client_contacts.is_primary', '=', true);
                });
            */

            $clients = Client::query()->where('company_id', '=', $this->getCurrentCompanyId());

            return DataTables::of($clients->get())
                ->addColumn('full_name', function ($clients) {
                    return $clients->contacts->where('is_primary', true)->map(function ($contact){
                        return $contact->first_name . ' ' . $contact->last_name;
                    })->all();
                })
                ->addColumn('email', function ($clients) {
                    return $clients->contacts->where('is_primary', true)->map(function ($contact){
                        return $contact->email;
                    })->all();
                })
                ->addColumn('action', function ($client) {
                    return '<a href="/clients/'. $client->present()->id .'/edit" class="btn btn-xs btn-primary"><i class="glyphicon glyphicon-edit"></i> Edit</a>';
                })
                ->addColumn('checkbox', function ($client){
                    return '<input type="checkbox" name="bulk" value="'. $client->id .'"/>';
                })
                ->rawColumns(['checkbox', 'action'])
                ->make(true);
        }

        $builder->addAction();
        $builder->addCheckbox();
        
        $html = $builder->columns([
            ['data' => 'checkbox', 'name' => 'checkbox', 'title' => '', 'searchable' => false, 'orderable' => false],
            ['data' => 'name', 'name' => 'name', 'title' => trans('texts.name'), 'visible'=> true],
            ['data' => 'full_name', 'name' => 'full_name', 'title' => trans('texts.contact'), 'visible'=> true],
            ['data' => 'email', 'name' => 'email', 'title' => trans('texts.email'), 'visible'=> true],
            ['data' => 'created_at', 'name' => 'created_at', 'title' => trans('texts.date_created'), 'visible'=> true],
            ['data' => 'last_login', 'name' => 'last_login', 'title' => trans('texts.last_login'), 'visible'=> true],
            ['data' => 'balance', 'name' => 'balance', 'title' => trans('texts.balance'), 'visible'=> true],
            ['data' => 'action', 'name' => 'action', 'title' => '', 'searchable' => false, 'orderable' => false],
        ]);

        $builder->ajax([
            'url' => route('clients.index'),
            'type' => 'GET',
            'data' => 'function(d) { d.key = "value"; }',
        ]);

        $data['html'] = $html;

        return view('client.list', $data);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $data = [
        'client' => [],
        'hashed_id' => ''
        ];

        return view('client.create', $data);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $client = Client::find(2);
        $client->load('contacts', 'primary_contact');

        return response()->json($client, 200);
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
        'hashed_id' => $this->encodePrimarykey($client->id)
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
        $client->load('contacts', 'primary_contact');

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

}
