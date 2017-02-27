<?php

namespace App\Services;

use App\Ninja\Datatables\CreditDatatable;
use App\Ninja\Repositories\CreditRepository;
use Auth;
use Utils;

/**
 * Class CreditService.
 */
class CreditService extends BaseService
{
    /**
     * @var CreditRepository
     */
    protected $creditRepo;

    /**
     * @var DatatableService
     */
    protected $datatableService;

    /**
     * CreditService constructor.
     *
     * @param CreditRepository $creditRepo
     * @param DatatableService $datatableService
     */
    public function __construct(CreditRepository $creditRepo, DatatableService $datatableService)
    {
        $this->creditRepo = $creditRepo;
        $this->datatableService = $datatableService;
    }

    /**
     * @return CreditRepository
     */
    protected function getRepo()
    {
        return $this->creditRepo;
    }

    /**
     * @param $data
     * @param null|mixed $credit
     *
     * @return mixed|null
     */
    public function save($data, $credit = null)
    {
        return $this->creditRepo->save($data, $credit);
    }

    /**
     * @param $clientPublicId
     * @param $search
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDatatable($clientPublicId, $search)
    {
        // we don't support bulk edit and hide the client on the individual client page
        $datatable = new CreditDatatable(true, $clientPublicId);
        $query = $this->creditRepo->find($clientPublicId, $search);

        if (! Utils::hasPermission('view_all')) {
            $query->where('credits.user_id', '=', Auth::user()->id);
        }

        return $this->datatableService->createDatatable($datatable, $query);
    }
}
