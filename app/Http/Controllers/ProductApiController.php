<?php namespace App\Http\Controllers;

use App\Models\Product;
use App\Ninja\Repositories\ProductRepository;
use App\Http\Requests\CreateProductRequest;
use App\Http\Requests\UpdateProductRequest;

/**
 * Class ProductApiController
 */
class ProductApiController extends BaseAPIController
{
    /**
     * @var string
     */
    protected $entityType = ENTITY_PRODUCT;

    /**
     * @var ProductRepository
     */
    protected $productRepo;

    /**
     * ProductApiController constructor.
     *
     * @param ProductRepository $productRepo
     */
    public function __construct(ProductRepository $productRepo)
    {
        parent::__construct();

        $this->productRepo = $productRepo;
    }

    /**
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $products = Product::scope()
                        ->withTrashed()
                        ->orderBy('created_at', 'desc');

        return $this->listResponse($products);
    }

    /**
     * @param CreateProductRequest $request
     * @return \Illuminate\Http\Response
     */
    public function store(CreateProductRequest $request)
    {
        $product = $this->productRepo->save($request->input());

        return $this->itemResponse($product);
    }

    /**
     * @param UpdateProductRequest $request
     * @param $publicId
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateProductRequest $request, $publicId)
    {
        if ($request->action) {
            return $this->handleAction($request);
        }
        
        $data = $request->input();
        $data['public_id'] = $publicId;
        $product = $this->productRepo->save($data, $request->entity());

        return $this->itemResponse($product);
    }
}
