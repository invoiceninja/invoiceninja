<?php namespace App\Services;

use Utils;
use URL;
use Auth;
use App\Services\BaseService;
use App\Models\Client;
use App\Models\Payment;
use App\Ninja\Repositories\CreditRepository;
use App\Ninja\Datatables\CreditDatatable;

class CreditService extends BaseService
{
    protected $creditRepo;
    protected $datatableService;

    public function __construct(CreditRepository $creditRepo, DatatableService $datatableService)
    {
        $this->creditRepo = $creditRepo;
        $this->datatableService = $datatableService;
    }

    protected function getRepo()
    {
        return $this->creditRepo;
    }

    public function save($data)
    {
        return $this->creditRepo->save($data);
    }

    public function getDatatable($clientPublicId, $search)
    {
        // we don't support bulk edit and hide the client on the individual client page
        $datatable = new CreditDatatable( ! $clientPublicId, $clientPublicId);
        $query = $this->creditRepo->find($clientPublicId, $search);

        if(!Utils::hasPermission('view_all')){
            $query->where('credits.user_id', '=', Auth::user()->id);
        }

        return $this->datatableService->createDatatable($datatable, $query);
    }
}
