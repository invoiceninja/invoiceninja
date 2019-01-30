<?php

namespace App\Services;

use App\Ninja\Datatables\SubscriptionDatatable;
use App\Ninja\Repositories\SubscriptionRepository;

/**
 * Class SubscriptionService.
 */
class SubscriptionService extends BaseService
{
    /**
     * @var SubscriptionRepository
     */
    protected $subscriptionRepo;

    /**
     * @var DatatableService
     */
    protected $datatableService;

    /**
     * SubscriptionService constructor.
     *
     * @param SubscriptionRepository  $subscriptionRepo
     * @param DatatableService $datatableService
     */
    public function __construct(SubscriptionRepository $subscriptionRepo, DatatableService $datatableService)
    {
        $this->subscriptionRepo = $subscriptionRepo;
        $this->datatableService = $datatableService;
    }

    /**
     * @return SubscriptionRepository
     */
    protected function getRepo()
    {
        return $this->subscriptionRepo;
    }

    /**
     * @param $userId
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDatatable($accountId)
    {
        $datatable = new SubscriptionDatatable(false);
        $query = $this->subscriptionRepo->find($accountId);

        return $this->datatableService->createDatatable($datatable, $query);
    }
}
