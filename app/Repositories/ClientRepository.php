<?php

namespace App\Repositories;

use App\Models\Client;
use App\Repositories\ClientContactRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * 
 */
class ClientRepository extends BaseRepository
{

    public function getClassName()
    {
        return Client::class;
    }
    
	public function save(Request $request, Client $client) : ?Client
	{
        $client->fill($request->input());
        $client->save();

        return $client;
	}

}