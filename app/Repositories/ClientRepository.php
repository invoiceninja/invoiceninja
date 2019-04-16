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

    /**
     * @var ClientContactRepository
     */
    protected $contactRepo;

    /**
     * ClientController constructor.
     * @param ClientContactRepository $contactRepo
     */
    public function __construct(ClientContactRepository $contactRepo)
    {

        $this->contactRepo = $contactRepo;

    }

    public function getClassName()
    {
        return Client::class;
    }
    
	public function save(Request $request, Client $client) : ?Client
	{
        $client->fill($request->input());
        $client->save();

        $contacts = $this->contactRepo->save($request->input('contacts'), $client);

        return $client;
	}

}