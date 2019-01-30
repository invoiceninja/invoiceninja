<?php

namespace App\Services;

use App\Models\Client;
use App\Ninja\Datatables\ProposalDatatable;
use App\Ninja\Repositories\ProposalRepository;

/**
 * Class ProposalService.
 */
class ProposalService extends BaseService
{
    /**
     * @var ProposalRepository
     */
    protected $proposalRepo;

    /**
     * @var DatatableService
     */
    protected $datatableService;

    /**
     * CreditService constructor.
     *
     * @param ProposalRepository $creditRepo
     * @param DatatableService  $datatableService
     */
    public function __construct(ProposalRepository $proposalRepo, DatatableService $datatableService)
    {
        $this->proposalRepo = $proposalRepo;
        $this->datatableService = $datatableService;
    }

    /**
     * @return CreditRepository
     */
    protected function getRepo()
    {
        return $this->proposalRepo;
    }

    /**
     * @param $data
     * @param mixed $proposal
     *
     * @return mixed|null
     */
    public function save($data, $proposal = false)
    {
        return $this->proposalRepo->save($data, $proposal);
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
        $datatable = new ProposalDatatable();

        $query = $this->proposalRepo->find($search, $userId);

        return $this->datatableService->createDatatable($datatable, $query);
    }
}
