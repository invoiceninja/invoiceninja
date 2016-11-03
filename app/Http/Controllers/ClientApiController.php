<?php namespace App\Http\Controllers;

use App\Http\Requests\ClientRequest;
use Response;
use Input;
use App\Models\Client;
use App\Ninja\Repositories\ClientRepository;
use App\Http\Requests\CreateClientRequest;
use App\Http\Requests\UpdateClientRequest;

class ClientApiController extends BaseAPIController
{
    protected $clientRepo;

    protected $entityType = ENTITY_CLIENT;

    public function __construct(ClientRepository $clientRepo)
    {
        parent::__construct();

        $this->clientRepo = $clientRepo;
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
            ->orderBy('created_at', 'desc')
            ->withTrashed();

        // Filter by email
        if ($email = Input::get('email')) {
            $clients = $clients->whereHas('contacts', function ($query) use ($email) {
                $query->where('email', $email);
            });
        }

        return $this->listResponse($clients);
    }

    /**
     * @SWG\Get(
     *   path="/clients/{client_id}",
     *   summary="Individual Client",
     *   tags={"client"},
     *   @SWG\Response(
     *     response=200,
     *     description="A single client",
     *      @SWG\Schema(type="object", @SWG\Items(ref="#/definitions/Client"))
     *   ),
     *   @SWG\Response(
     *     response="default",
     *     description="an ""unexpected"" error"
     *   )
     * )
     */

    public function show(ClientRequest $request)
    {
        return $this->itemResponse($request->entity());
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

        return $this->itemResponse($client);
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
        if ($request->action) {
            return $this->handleAction($request);
        }

        $data = $request->input();
        $data['public_id'] = $publicId;
        $client = $this->clientRepo->save($data, $request->entity());

        $client->load(['contacts']);

        return $this->itemResponse($client);
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

    public function destroy(UpdateClientRequest $request)
    {
        $client = $request->entity();

        $this->clientRepo->delete($client);

        return $this->itemResponse($client);
    }

}
