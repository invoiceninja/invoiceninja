<?php namespace App\Http\Controllers;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Utils;
use Response;
use Input;
use Auth;
use App\Models\Client;
use App\Ninja\Repositories\ClientRepository;
use App\Http\Requests\CreateClientRequest;
use App\Http\Controllers\BaseAPIController;
use App\Ninja\Transformers\ClientTransformer;
use App\Services\ClientService;
use App\Http\Requests\UpdateClientRequest;

class ClientApiController extends BaseAPIController
{
    protected $clientRepo;
    protected $clientService;

    public function __construct(ClientRepository $clientRepo, ClientService $clientService)
    {
        parent::__construct();

        $this->clientRepo = $clientRepo;
        $this->clientService = $clientService;
    }

    public function ping()
    {
        $headers = Utils::getApiHeaders();

        return Response::make('', 200, $headers);
    }

    /**
     * @SWG\Get(
     *   path="/clients",
     *   summary="List of clients",
     *   tags={"client"},
     *   @SWG\Response(
     *     response=200,
     *     description="A list with clients",
     *      @SWG\Schema(type="array", @SWG\Items(ref="#/definitions/Client"))
     *   ),
     *   @SWG\Response(
     *     response="default",
     *     description="an ""unexpected"" error"
     *   )
     * )
     */
    public function index()
    {
        $clients = Client::scope()
            ->with($this->getIncluded())
            ->orderBy('created_at', 'desc')->withTrashed();

        // Filter by email
        if (Input::has('email')) {

            $email = Input::get('email');
            $clients = $clients->whereHas('contacts', function ($query) use ($email) {
                $query->where('email', $email);
            });

        }

        $clients = $clients->paginate();

        $transformer = new ClientTransformer(Auth::user()->account, Input::get('serializer'));
        $paginator = Client::scope()->withTrashed()->paginate();

        $data = $this->createCollection($clients, $transformer, ENTITY_CLIENT, $paginator);

        return $this->response($data);
    }

    /**
     * @SWG\Post(
     *   path="/clients",
     *   tags={"client"},
     *   summary="Create a client",
     *   @SWG\Parameter(
     *     in="body",
     *     name="body",
     *     @SWG\Schema(ref="#/definitions/Client")
     *   ),
     *   @SWG\Response(
     *     response=200,
     *     description="New client",
     *      @SWG\Schema(type="object", @SWG\Items(ref="#/definitions/Client"))
     *   ),
     *   @SWG\Response(
     *     response="default",
     *     description="an ""unexpected"" error"
     *   )
     * )
     */
    public function store(CreateClientRequest $request)
    {
        $client = $this->clientRepo->save($request->input());

        $client = Client::scope($client->public_id)
            ->with('country', 'contacts', 'industry', 'size', 'currency')
            ->first();

        $transformer = new ClientTransformer(Auth::user()->account, Input::get('serializer'));
        $data = $this->createItem($client, $transformer, ENTITY_CLIENT);

        return $this->response($data);
    }

    /**
     * @SWG\Put(
     *   path="/clients/{client_id}",
     *   tags={"client"},
     *   summary="Update a client",
     *   @SWG\Parameter(
     *     in="body",
     *     name="body",
     *     @SWG\Schema(ref="#/definitions/Client")
     *   ),
     *   @SWG\Response(
     *     response=200,
     *     description="Update client",
     *      @SWG\Schema(type="object", @SWG\Items(ref="#/definitions/Client"))
     *   ),
     *   @SWG\Response(
     *     response="default",
     *     description="an ""unexpected"" error"
     *   )
     * )
     */

    public function update(UpdateClientRequest $request, $publicId)
    {
        if ($request->action == ACTION_ARCHIVE) {

            $client = Client::scope($publicId)->withTrashed()->first();

            if(!$client)
                return $this->errorResponse(['message'=>'Client not found.']);


            $this->clientRepo->archive($client);

            $transformer = new ClientTransformer(Auth::user()->account, Input::get('serializer'));
            $data = $this->createItem($client, $transformer, ENTITY_CLIENT);

            return $this->response($data);
        }
        else if ($request->action == ACTION_RESTORE){

            $client = Client::scope($publicId)->withTrashed()->first();

            if(!$client)
                return $this->errorResponse(['message'=>'Client not found.'], 400);

            $this->clientRepo->restore($client);

            $transformer = new ClientTransformer(Auth::user()->account, Input::get('serializer'));
            $data = $this->createItem($client, $transformer, ENTITY_CLIENT);

            return $this->response($data);
        }

        $data = $request->input();
        $data['public_id'] = $publicId;
        $this->clientRepo->save($data);

        $client = Client::scope($publicId)
            ->with('country', 'contacts', 'industry', 'size', 'currency')
            ->first();

        if(!$client)
            return $this->errorResponse(['message'=>'Client not found.'],400);
        
        $transformer = new ClientTransformer(Auth::user()->account, Input::get('serializer'));
        $data = $this->createItem($client, $transformer, ENTITY_CLIENT);

        return $this->response($data);
    }


    /**
     * @SWG\Delete(
     *   path="/clients/{client_id}",
     *   tags={"client"},
     *   summary="Delete a client",
     *   @SWG\Parameter(
     *     in="body",
     *     name="body",
     *     @SWG\Schema(ref="#/definitions/Client")
     *   ),
     *   @SWG\Response(
     *     response=200,
     *     description="Delete client",
     *      @SWG\Schema(type="object", @SWG\Items(ref="#/definitions/Client"))
     *   ),
     *   @SWG\Response(
     *     response="default",
     *     description="an ""unexpected"" error"
     *   )
     * )
     */
    
    public function destroy($publicId)
    {

        $client = Client::scope($publicId)->withTrashed()->first();
        $this->clientRepo->delete($client);

        $client = Client::scope($publicId)
            ->with('country', 'contacts', 'industry', 'size', 'currency')
            ->withTrashed()
            ->first();

        $transformer = new ClientTransformer(Auth::user()->account, Input::get('serializer'));
        $data = $this->createItem($client, $transformer, ENTITY_CLIENT);

        return $this->response($data);

    }


}
