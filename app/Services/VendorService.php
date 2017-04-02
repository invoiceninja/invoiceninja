<?php

namespace App\Services;

use App\Models\Vendor;
use App\Ninja\Datatables\VendorDatatable;
use App\Ninja\Repositories\NinjaRepository;
use App\Ninja\Repositories\VendorRepository;
use Auth;
use Utils;

/**
 * Class VendorService.
 */
class VendorService extends BaseService
{
    /**
     * @var VendorRepository
     */
    protected $vendorRepo;

    /**
     * @var DatatableService
     */
    protected $datatableService;

    /**
     * VendorService constructor.
     *
     * @param VendorRepository $vendorRepo
     * @param DatatableService $datatableService
     * @param NinjaRepository  $ninjaRepo
     */
    public function __construct(
        VendorRepository $vendorRepo,
        DatatableService $datatableService,
        NinjaRepository $ninjaRepo
    ) {
        $this->vendorRepo = $vendorRepo;
        $this->ninjaRepo = $ninjaRepo;
        $this->datatableService = $datatableService;
    }

    /**
     * @return VendorRepository
     */
    protected function getRepo()
    {
        return $this->vendorRepo;
    }

    /**
     * @param array       $data
     * @param Vendor|null $vendor
     *
     * @return mixed|null
     */
    public function save(array $data, Vendor $vendor = null)
    {
        return $this->vendorRepo->save($data, $vendor);
    }

    /**
     * @param $search
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDatatable($search)
    {
        $datatable = new VendorDatatable();
        $query = $this->vendorRepo->find($search);

        if (! Utils::hasPermission('view_all')) {
            $query->where('vendors.user_id', '=', Auth::user()->id);
        }

        return $this->datatableService->createDatatable($datatable, $query);
    }
}
