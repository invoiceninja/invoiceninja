<?php

namespace App\Services;

use App\Ninja\Datatables\ProductDatatable;
use App\Ninja\Repositories\ProductRepository;
use Auth;
use Utils;

class ProductService extends BaseService
{
    /**
     * @var DatatableService
     */
    protected $datatableService;

    /**
     * @var ProductRepository
     */
    protected $productRepo;

    /**
     * ProductService constructor.
     *
     * @param DatatableService  $datatableService
     * @param ProductRepository $productRepo
     */
    public function __construct(DatatableService $datatableService, ProductRepository $productRepo)
    {
        $this->datatableService = $datatableService;
        $this->productRepo = $productRepo;
    }

    /**
     * @return ProductRepository
     */
    protected function getRepo()
    {
        return $this->productRepo;
    }

    /**
     * @param $accountId
     * @param mixed $search
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDatatable($accountId, $search)
    {
        $datatable = new ProductDatatable(true);
        $query = $this->productRepo->find($accountId, $search);

        if (! Utils::hasPermission('view_all')) {
            $query->where('products.user_id', '=', Auth::user()->id);
        }

        return $this->datatableService->createDatatable($datatable, $query);
    }
}
