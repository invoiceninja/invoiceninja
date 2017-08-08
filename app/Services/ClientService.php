<?php

namespace App\Services;

use App\Ninja\Datatables\ClientDatatable;
use App\Ninja\Repositories\ClientRepository;
use App\Ninja\Repositories\NinjaRepository;
use Auth;

/**
 * Class ClientService.
 */
class ClientService extends BaseService
{
    /**
     * @var ClientRepository
     */
    protected $clientRepo;

    /**
     * @var DatatableService
     */
    protected $datatableService;

    /**
     * ClientService constructor.
     *
     * @param ClientRepository $clientRepo
     * @param DatatableService $datatableService
     * @param NinjaRepository  $ninjaRepo
     */
    public function __construct(ClientRepository $clientRepo, DatatableService $datatableService, NinjaRepository $ninjaRepo)
    {
        $this->clientRepo = $clientRepo;
        $this->ninjaRepo = $ninjaRepo;
        $this->datatableService = $datatableService;
    }

    /**
     * @return ClientRepository
     */
    protected function getRepo()
    {
        return $this->clientRepo;
    }

    /**
     * @param $data
     * @param null $client
     *
     * @return mixed|null
     */
    public function save($data, $client = null)
    {
        if (Auth::user()->account->isNinjaAccount() && isset($data['plan'])) {
            $this->ninjaRepo->updatePlanDetails($data['public_id'], $data);
        }

        return $this->clientRepo->save($data, $client);
    }

    /**
     * @param $search
     * @param $userId
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDatatable($search, $userId)
    {
        $datatable = new ClientDatatable();

        $query = $this->clientRepo->find($search, $userId);

        return $this->datatableService->createDatatable($datatable, $query);
    }
}
