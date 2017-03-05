<?php

namespace App\Services;

use App\Ninja\Datatables\UserDatatable;
use App\Ninja\Repositories\UserRepository;

/**
 * Class UserService.
 */
class UserService extends BaseService
{
    /**
     * @var UserRepository
     */
    protected $userRepo;

    /**
     * @var DatatableService
     */
    protected $datatableService;

    /**
     * UserService constructor.
     *
     * @param UserRepository   $userRepo
     * @param DatatableService $datatableService
     */
    public function __construct(UserRepository $userRepo, DatatableService $datatableService)
    {
        $this->userRepo = $userRepo;
        $this->datatableService = $datatableService;
    }

    /**
     * @return UserRepository
     */
    protected function getRepo()
    {
        return $this->userRepo;
    }

    /**
     * @param $accountId
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDatatable($accountId)
    {
        $datatable = new UserDatatable(false);
        $query = $this->userRepo->find($accountId);

        return $this->datatableService->createDatatable($datatable, $query);
    }
}
