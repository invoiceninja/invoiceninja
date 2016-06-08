<?php namespace App\Services;

use URL;
use App\Services\BaseService;
use App\Ninja\Repositories\TokenRepository;
use App\Ninja\Datatables\TokenDatatable;

class TokenService extends BaseService
{
    protected $tokenRepo;
    protected $datatableService;

    public function __construct(TokenRepository $tokenRepo, DatatableService $datatableService)
    {
        $this->tokenRepo = $tokenRepo;
        $this->datatableService = $datatableService;
    }

    protected function getRepo()
    {
        return $this->tokenRepo;
    }

    public function getDatatable($userId)
    {
        $datatable = new TokenDatatable(false);
        $query = $this->tokenRepo->find($userId);

        return $this->datatableService->createDatatable($datatable, $query);
    }

}
