<?php namespace App\Services;

use URL;
use App\Services\BaseService;
use App\Ninja\Repositories\AccountGatewayRepository;
use App\Ninja\Datatables\AccountGatewayDatatable;

class AccountGatewayService extends BaseService
{
    protected $accountGatewayRepo;
    protected $datatableService;

    public function __construct(AccountGatewayRepository $accountGatewayRepo, DatatableService $datatableService)
    {
        $this->accountGatewayRepo = $accountGatewayRepo;
        $this->datatableService = $datatableService;
    }

    protected function getRepo()
    {
        return $this->accountGatewayRepo;
    }

    public function getDatatable($accountId)
    {
        $query = $this->accountGatewayRepo->find($accountId);

        return $this->datatableService->createDatatable(new AccountGatewayDatatable(false), $query);
    }

}
