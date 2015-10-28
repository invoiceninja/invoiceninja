<?php namespace App\Services;

use App\Services\BaseService;
use App\Ninja\Repositories\CreditRepository;


class CreditService extends BaseService
{
    protected $creditRepo;

    public function __construct(CreditRepository $creditRepo)
    {
        $this->creditRepo = $creditRepo;
    }

    protected function getRepo()
    {
        return $this->creditRepo;
    }

    public function save($data)
    {
        return $this->creditRepo->save($data);
    }
}