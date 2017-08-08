<?php

namespace App\Http\Controllers;

use App\Http\Requests\ClientRequest;
use App\Http\Requests\CreateClientRequest;
use App\Http\Requests\UpdateClientRequest;
use App\Models\Client;
use App\Ninja\Repositories\ClientRepository;
use Input;
use Response;

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
     *   summary="List clients",
     *   operationId="listClients",
     *   tags={"client"},
     *   @SWG\Response(
     *     response=200,
     *     description="A list of clients",
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

        if ($email = Input::get('email')) {
            $clients = $clients->whereHas('contacts', function ($query) use ($email) {
                $query->where('email', $email);
            });
        } elseif ($idNumber = Input::get('id_number')) {
            $clients = $clients->whereIdNumber($idNumber);
        }

        return $this->listResponse($clients);
    }

    /**
     * @SWG\Get(
     *   path="/clients/{client_id}",
     *   summary="Retrieve a client",
     *   operationId="getClient",
     *   tags={"client"},
     *   @SWG\Parameter(
     *     in="path",
     *     name="client_id",
     *     type="integer",
     *     required=true
     *   ),
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
     *   summary="Create a client",
     *   operationId="createClient",
     *   tags={"client"},
     *   @SWG\Parameter(
     *     in="body",
     *     name="client",
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
     *   summary="Update a client",
     *   operationId="updateClient",
     *   tags={"client"},
     *   @SWG\Parameter(
     *     in="path",
     *     name="client_id",
     *     type="integer",
     *     required=true
     *   ),
     *   @SWG\Parameter(
     *     in="body",
     *     name="client",
     *     @SWG\Schema(ref="#/definitions/Client")
     *   ),
     *   @SWG\Response(
     *     response=200,
     *     description="Updated client",
     *      @SWG\Schema(type="object", @SWG\Items(ref="#/definitions/Client"))
     *   ),
     *   @SWG\Response(
     *     response="default",
     *     description="an ""unexpected"" error"
     *   )
     * )
     *
     * @param mixed $publicId
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
     *   summary="Delete a client",
     *   operationId="deleteClient",
     *   tags={"client"},
     *   @SWG\Parameter(
     *     in="path",
     *     name="client_id",
     *     type="integer",
     *     required=true
     *   ),
     *   @SWG\Response(
     *     response=200,
     *     description="Deleted client",
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
