<?php namespace App\Services;

use App\Services\BaseService;
use App\Ninja\Repositories\ClientRepository;


class ClientService extends BaseService
{
    protected $clientRepo;

    public function __construct(ClientRepository $clientRepo)
    {
        $this->clientRepo = $clientRepo;
    }

    protected function getRepo()
    {
        return $this->clientRepo;
    }

    public function save($data)
    {
        return $this->clientRepo->save($data);
    }
}