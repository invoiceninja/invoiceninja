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
    $clients = Client::scope()->get()->toArray();
    
    return Response::json(array(
        'error' => false,
        'clients' => $clients),
        200
    );
  }
}