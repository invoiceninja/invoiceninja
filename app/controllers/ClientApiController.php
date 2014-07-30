<?php

use ninja\repositories\ClientRepository;
use Client;

class ClientApiController extends Controller {

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
    if (!Utils::isPro()) {
      return Redirect::to('/');
    }

    $clients = Client::scope()->with('contacts')->orderBy('created_at', 'desc')->get();
    $clients = Utils::remapPublicIds($clients->toArray());

    $response = json_encode($clients, JSON_PRETTY_PRINT);
    $headers = Utils::getApiHeaders(count($clients));
    return Response::make($response, 200, $headers);
  }

  public function store()
  {
    if (!Utils::isPro()) {
      return Redirect::to('/');
    }

    $data = Input::all();
    $error = $this->clientRepo->getErrors($data);
    
    if ($error)
    {
      $headers = Utils::getApiHeaders();
      return Response::make($error, 500, $headers);
    }
    else
    {
      $client = $this->clientRepo->save(false, $data, false);    
      $response = json_encode($client, JSON_PRETTY_PRINT);
      $headers = Utils::getApiHeaders();
      return Response::make($response, 200, $headers);
    }

  }
}