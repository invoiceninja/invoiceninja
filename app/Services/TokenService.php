<?php

namespace App\Services;

use App\Ninja\Datatables\TokenDatatable;
use App\Ninja\Repositories\TokenRepository;

/**
 * Class TokenService.
 */
class TokenService extends BaseService
{
    /**
     * @var TokenRepository
     */
    protected $tokenRepo;

    /**
     * @var DatatableService
     */
    protected $datatableService;

    /**
     * TokenService constructor.
     *
     * @param TokenRepository  $tokenRepo
     * @param DatatableService $datatableService
     */
    public function __construct(TokenRepository $tokenRepo, DatatableService $datatableService)
    {
        $this->tokenRepo = $tokenRepo;
        $this->datatableService = $datatableService;
    }

    /**
     * @return TokenRepository
     */
    protected function getRepo()
    {
        return $this->tokenRepo;
    }

    /**
     * @param $userId
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDatatable($userId)
    {
        $datatable = new TokenDatatable(false);
        $query = $this->tokenRepo->find($userId);

        return $this->datatableService->createDatatable($datatable, $query);
    }
}
