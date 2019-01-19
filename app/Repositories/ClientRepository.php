<?php

namespace App\Repositories;

use App\Models\Client;
use App\Repositories\ClientContactRepository;
use Illuminate\Http\Request;

/**
 * 
 */
class ClientRepository extends BaseRepository
{
	protected $clientContactRepo;
	
    public function __construct(ClientContactRepository $clientContactRepo)
    {
        $this->clientContactRepo = $clientContactRepo;
    }

    public function getClassName()
    {
        return Client::class;
    }
    
	public function save(Request $request, Client $client) : ?Client
	{
        $client->fill($request->input());
        $client->save();

        $this->clientContactRepo->save($request->input('contacts'), $client);

        return $client;
	}

}