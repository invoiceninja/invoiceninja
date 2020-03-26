<?php

namespace App\Services;

use App\Ninja\Datatables\AccountGatewayDatatable;
use App\Ninja\Repositories\AccountGatewayRepository;

/**
 * Class AccountGatewayService.
 */
class AccountGatewayService extends BaseService
{
    /**
     * @var AccountGatewayRepository
     */
    protected $accountGatewayRepo;

    /**
     * @var DatatableService
     */
    protected $datatableService;

    /**
     * AccountGatewayService constructor.
     *
     * @param AccountGatewayRepository $accountGatewayRepo
     * @param DatatableService         $datatableService
     */
    public function __construct(AccountGatewayRepository $accountGatewayRepo, DatatableService $datatableService)
    {
        $this->accountGatewayRepo = $accountGatewayRepo;
        $this->datatableService = $datatableService;
    }

    /**
     * @return AccountGatewayRepository
     */
    protected function getRepo()
    {
        return $this->accountGatewayRepo;
    }

    /**
     * @param $accountId
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDatatable($accountId)
    {
        $query = $this->accountGatewayRepo->find($accountId);

        return $this->datatableService->createDatatable(new AccountGatewayDatatable(false), $query);
    }
}
