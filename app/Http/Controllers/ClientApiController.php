<?php namespace App\Http\Controllers;

use Utils;
use Response;
use Input;
use App\Models\Client;
use App\Ninja\Repositories\ClientRepository;
use App\Http\Requests\CreateClientRequest;

class ClientApiController extends Controller
{
    protected $clientRepo;

    public function __construct(ClientRepository $clientRepo)
    {
        $this->clientRepo = $clientRepo;
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
                    ->with('country', 'contacts', 'industry', 'size', 'currency')
                    ->orderBy('created_at', 'desc')
                    ->get();
        $clients = Utils::remapPublicIds($clients);

        $response = json_encode($clients, JSON_PRETTY_PRINT);
        $headers = Utils::getApiHeaders(count($clients));

        return Response::make($response, 200, $headers);
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

        $client = Client::scope($client->public_id)->with('country', 'contacts', 'industry', 'size', 'currency')->first();
        $client = Utils::remapPublicIds([$client]);
        $response = json_encode($client, JSON_PRETTY_PRINT);
        $headers = Utils::getApiHeaders();

        return Response::make($response, 200, $headers);
    }
}
