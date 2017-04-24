<?php

namespace App\Services;

use App\Models\Client;
use App\Ninja\Datatables\ProjectDatatable;
use App\Ninja\Repositories\ProjectRepository;

/**
 * Class ProjectService.
 */
class ProjectService extends BaseService
{
    /**
     * @var ProjectRepository
     */
    protected $projectRepo;

    /**
     * @var DatatableService
     */
    protected $datatableService;

    /**
     * CreditService constructor.
     *
     * @param ProjectRepository $creditRepo
     * @param DatatableService  $datatableService
     */
    public function __construct(ProjectRepository $projectRepo, DatatableService $datatableService)
    {
        $this->projectRepo = $projectRepo;
        $this->datatableService = $datatableService;
    }

    /**
     * @return CreditRepository
     */
    protected function getRepo()
    {
        return $this->projectRepo;
    }

    /**
     * @param $data
     * @param mixed $project
     *
     * @return mixed|null
     */
    public function save($data, $project = false)
    {
        if (isset($data['client_id']) && $data['client_id']) {
            $data['client_id'] = Client::getPrivateId($data['client_id']);
        }

        return $this->projectRepo->save($data, $project);
    }

    /**
     * @param $clientPublicId
     * @param $search
     * @param mixed $userId
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDatatable($search, $userId)
    {
        // we don't support bulk edit and hide the client on the individual client page
        $datatable = new ProjectDatatable();

        $query = $this->projectRepo->find($search, $userId);

        return $this->datatableService->createDatatable($datatable, $query);
    }
}
