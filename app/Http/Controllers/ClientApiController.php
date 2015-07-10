<?php namespace App\Http\Controllers;

use Utils;
use Response;
use Input;
use App\Models\Client;
use App\Ninja\Repositories\ClientRepository;

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

    public function store()
    {
        $data = Input::all();
        $error = $this->clientRepo->getErrors($data);

        if ($error) {
            $headers = Utils::getApiHeaders();

            return Response::make($error, 500, $headers);
        } else {
            $client = $this->clientRepo->save(isset($data['id']) ? $data['id'] : false, $data, false);
            $client = Client::scope($client->public_id)->with('country', 'contacts', 'industry', 'size', 'currency')->first();
            $client = Utils::remapPublicIds([$client]);
            $response = json_encode($client, JSON_PRETTY_PRINT);
            $headers = Utils::getApiHeaders();

            return Response::make($response, 200, $headers);
        }
    }
}
