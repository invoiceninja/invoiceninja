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
    
    /*
    $response = [
      'status' => 200,
      'error' => false,
      'clients' => $clients->toArray()
    ];
    */

    $response = json_encode($clients->toArray(), JSON_PRETTY_PRINT);
    $headers = [
      'Content-Type' => 'application/json',
      'Access-Control-Allow-Origin' => '*',
      'Access-Control-Allow-Methods' => 'GET',
      //'Access-Control-Allow-Headers' => 'Origin, Content-Type, Accept, Authorization, X-Requested-With',      
      //'Access-Control-Allow-Credentials' => 'true',
      //'X-Total-Count' => 0
      //'X-Rate-Limit-Limit' - The number of allowed requests in the current period
      //'X-Rate-Limit-Remaining' - The number of remaining requests in the current period
      //'X-Rate-Limit-Reset' - The number of seconds left in the current period,
    ];

    return Response::make($response, 200, $headers);
  }
}