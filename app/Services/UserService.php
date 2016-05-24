<?php namespace App\Services;

use URL;
use App\Services\BaseService;
use App\Ninja\Repositories\UserRepository;
use App\Ninja\Datatables\UserDatatable;

class UserService extends BaseService
{
    protected $userRepo;
    protected $datatableService;

    public function __construct(UserRepository $userRepo, DatatableService $datatableService)
    {
        $this->userRepo = $userRepo;
        $this->datatableService = $datatableService;
    }

    protected function getRepo()
    {
        return $this->userRepo;
    }

    public function getDatatable($accountId)
    {
        $datatable = new UserDatatable(false);
        $query = $this->userRepo->find($accountId);

        return $this->datatableService->createDatatable($datatable, $query);
    }

}
