<?php namespace App\Services;

use Utils;
use URL;
use Auth;
use App\Services\BaseService;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Credit;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\Task;
use App\Ninja\Repositories\ClientRepository;
use App\Ninja\Repositories\NinjaRepository;
use App\Ninja\Datatables\ClientDatatable;

class ClientService extends BaseService
{
    protected $clientRepo;
    protected $datatableService;

    public function __construct(ClientRepository $clientRepo, DatatableService $datatableService, NinjaRepository $ninjaRepo)
    {
        $this->clientRepo = $clientRepo;
        $this->ninjaRepo = $ninjaRepo;
        $this->datatableService = $datatableService;
    }

    protected function getRepo()
    {
        return $this->clientRepo;
    }

    public function save($data, $client = null)
    {
        if (Auth::user()->account->isNinjaAccount() && isset($data['plan'])) {
            $this->ninjaRepo->updatePlanDetails($data['public_id'], $data);
        }

        return $this->clientRepo->save($data, $client);
    }

    public function getDatatable($search, $userId)
    {
        $datatable = new ClientDatatable();

        $query = $this->clientRepo->find($search, $userId);

        return $this->datatableService->createDatatable($datatable, $query);
    }

}
