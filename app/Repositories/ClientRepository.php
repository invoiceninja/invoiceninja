<?php

namespace App\Repositories;

use App\Repositories\ClientContactRepository;

/**
 * 
 */
class ClientRepository extends BaseRepository
{
	protected $clientContactRepository;
	
    public function __construct(ClientContactRepository $clientContactRepository)
    {
        $this->clientContactRepository = $clientContactRepository;
    }

	public function save($data)
	{
        $client->fill($request->all())->save();

	}

}