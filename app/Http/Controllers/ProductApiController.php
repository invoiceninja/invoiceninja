<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Product;
use App\Ninja\Repositories\ProductRepository;

/**
 * Class ProductApiController.
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
     * @SWG\Get(
     *   path="/products",
     *   summary="List of products",
     *   tags={"product"},
     *   @SWG\Response(
     *     response=200,
     *     description="A list with products",
     *      @SWG\Schema(type="array", @SWG\Items(ref="#/definitions/Product"))
     *   ),
     *   @SWG\Response(
     *     response="default",
     *     description="an ""unexpected"" error"
     *   )
     * )
     */
    public function index()
    {
        $products = Product::scope()
                        ->withTrashed()
                        ->orderBy('created_at', 'desc');

        return $this->listResponse($products);
    }

    /**
     * @SWG\Post(
     *   path="/products",
     *   tags={"product"},
     *   summary="Create a product",
     *   @SWG\Parameter(
     *     in="body",
     *     name="body",
     *     @SWG\Schema(ref="#/definitions/Product")
     *   ),
     *   @SWG\Response(
     *     response=200,
     *     description="New product",
     *      @SWG\Schema(type="object", @SWG\Items(ref="#/definitions/Product"))
     *   ),
     *   @SWG\Response(
     *     response="default",
     *     description="an ""unexpected"" error"
     *   )
     * )
     */
    public function store(CreateProductRequest $request)
    {
        $product = $this->productRepo->save($request->input());

        return $this->itemResponse($product);
    }

    /**
     * @SWG\Put(
     *   path="/products/{product_id}",
     *   tags={"product"},
     *   summary="Update a product",
     *   @SWG\Parameter(
     *     in="body",
     *     name="body",
     *     @SWG\Schema(ref="#/definitions/Product")
     *   ),
     *   @SWG\Response(
     *     response=200,
     *     description="Update product",
     *      @SWG\Schema(type="object", @SWG\Items(ref="#/definitions/Product"))
     *   ),
     *   @SWG\Response(
     *     response="default",
     *     description="an ""unexpected"" error"
     *   )
     * )
     *
     * @param mixed $publicId
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
