<?php namespace App\Services;

use Utils;
use URL;
use Auth;
use App\Models\Vendor;
use App\Models\Expense;
use App\Services\BaseService;
use App\Ninja\Repositories\VendorRepository;
use App\Ninja\Repositories\NinjaRepository;

class VendorService extends BaseService
{
    protected $vendorRepo;
    protected $datatableService;

    public function __construct(VendorRepository $vendorRepo, DatatableService $datatableService, NinjaRepository $ninjaRepo)
    {
        $this->vendorRepo       = $vendorRepo;
        $this->ninjaRepo        = $ninjaRepo;
        $this->datatableService = $datatableService;
    }

    protected function getRepo()
    {
        return $this->vendorRepo;
    }

    public function save($data, $vendor = null)
    {
        if (Auth::user()->account->isNinjaAccount() && isset($data['plan'])) {
            $this->ninjaRepo->updatePlanDetails($data['public_id'], $data);
        }

        return $this->vendorRepo->save($data, $vendor);
    }

    public function getDatatable($search)
    {
        $query = $this->vendorRepo->find($search);

        if(!Utils::hasPermission('view_all')){
            $query->where('vendors.user_id', '=', Auth::user()->id);
        }

        return $this->datatableService->createDatatable(ENTITY_VENDOR, $query);
    }

}
