<?php namespace App\Services;

use App\Ninja\Repositories\ProductRepository;
use App\Ninja\Datatables\ProductDatatable;

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
     * @param DatatableService $datatableService
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
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDatatable($accountId)
    {
        $datatable = new ProductDatatable(false);
        $query = $this->productRepo->find($accountId);

        return $this->datatableService->createDatatable($datatable, $query);
    }
}
