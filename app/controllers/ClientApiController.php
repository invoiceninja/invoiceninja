<?php

use ninja\repositories\ClientRepository;
use Client;

class ClientApiController extends \BaseController {

  protected $clientRepo;

  public function __construct(ClientRepository $clientRepo)
  {
    parent::__construct();

    $this->clientRepo = $clientRepo;
  } 

  public function index()
  {    
    $clients = Client::scope()->get();
    
    $response = [
      'status' => 200,
      'error' => false,
      'clients' => $clients->toArray()
    ];

    $response = json_encode($response, JSON_PRETTY_PRINT);

    return Response::make($response, 200, ['Content-Type' => 'application/json']);
  }
}