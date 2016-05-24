<?php namespace App\Services;

use Utils;
use DB;
use Auth;
use URL;
use App\Services\BaseService;
use App\Ninja\Repositories\ProductRepository;
use App\Ninja\Datatables\ProductDatatable;

class ProductService extends BaseService
{
    protected $datatableService;
    protected $productRepo;

    public function __construct(DatatableService $datatableService, ProductRepository $productRepo)
    {
        $this->datatableService = $datatableService;
        $this->productRepo = $productRepo;
    }

    protected function getRepo()
    {
        return $this->productRepo;
    }

    /*
    public function save()
    {
        return null;
    }
    */

    public function getDatatable($accountId)
    {
        $datatable = new ProductDatatable(false);
        $query = $this->productRepo->find($accountId);

        return $this->datatableService->createDatatable($datatable, $query);
    }

}
