<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2025. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Controllers;

use App\Factory\ProductEquipmentFactory;
use App\Filters\ProductEquipmentFilters;
use App\Http\Requests\ProductEquipment\BulkProductEquipmentRequest;
use App\Http\Requests\ProductEquipment\CreateProductEquipmentRequest;
use App\Http\Requests\ProductEquipment\DestroyProductEquipmentRequest;
use App\Http\Requests\ProductEquipment\EditProductEquipmentRequest;
use App\Http\Requests\ProductEquipment\ShowProductEquipmentRequest;
use App\Http\Requests\ProductEquipment\StoreProductEquipmentRequest;
use App\Http\Requests\ProductEquipment\UpdateProductEquipmentRequest;
use App\Http\Requests\ProductEquipment\UploadProductEquipmentRequest;
use App\Models\Account;
use App\Models\ProductEquipment;
use App\Repositories\ProductEquipmentRepository;
use App\Transformers\ProductEquipmentTransformer;
use App\Utils\Traits\MakesHash;
use App\Utils\Traits\SavesDocuments;
use Illuminate\Http\Response;

class ProductEquipmentController extends BaseController
{
    use MakesHash;
    use SavesDocuments;

    protected $entity_type = ProductEquipment::class;

    protected $entity_transformer = ProductEquipmentTransformer::class;

    protected $product_allocation_repo;

    /**
     * ProductEquipmentController constructor.
     * @param ProductEquipmentRepository $product_allocation_repo
     */
    public function __construct(ProductEquipmentRepository $product_allocation_repo)
    {
        parent::__construct();

        $this->product_allocation_repo = $product_allocation_repo;
    }

    /**
     * @OA\Get(
     *      path="/api/v1/product_allocations",
     *      operationId="getProductEquipments",
     *      tags={"product_allocation"},
     *      summary="Gets a list of product_allocation",
     *      description="Lists product_allocation, search and filters allow fine grained lists to be generated.

     *  Query parameters can be added to performed more fine grained filtering of the product_allocation, these are handled by the ProductEquipmentFilters class which defines the methods available",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Response(
     *          response=200,
     *          description="A list of product_allocation",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/ProductEquipment"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     * @param ProductEquipmentFilters $filters
     * @return Response| \Illuminate\Http\JsonResponse|mixed
     */
    public function index(ProductEquipmentFilters $filters)
    {
        $productAllocation = ProductEquipment::filter($filters);

        return $this->listResponse($productAllocation);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @param CreateProductEquipmentRequest $request
     * @return Response| \Illuminate\Http\JsonResponse
     *
     *
     *
     * @OA\Get(
     *      path="/api/v1/product_allocations/create",
     *      operationId="getProductEquipmentsCreate",
     *      tags={"product_allocation"},
     *      summary="Gets a new blank ProductEquipment object",
     *      description="Returns a blank object with default values",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Response(
     *          response=200,
     *          description="A blank ProductEquipment object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/ProductEquipment"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     */
    public function create(CreateProductEquipmentRequest $request)
    {

        /** @var \App\Models\User $user */
        $user = auth()->user();
        $data = $request->all();

        $productAllocation = ProductEquipmentFactory::create($user->company()->id, $user->id, array_key_exists('product_id', $data) ? $data['product_id'] : 0);

        return $this->itemResponse($productAllocation);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param StoreProductEquipmentRequest $request
     * @return Response| \Illuminate\Http\JsonResponse
     *
     *
     *
     * @OA\Post(
     *      path="/api/v1/product_allocations",
     *      operationId="storeProductEquipment",
     *      tags={"product_allocation"},
     *      summary="Adds a ProductEquipment",
     *      description="Adds an ProductEquipment to the system",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the saved ProductEquipment object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/ProductEquipment"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     */
    public function store(StoreProductEquipmentRequest $request)
    {

        /** @var \App\Models\User $user */
        $user = auth()->user();

        $productAllocation = $this->product_allocation_repo->save($user->company()->id, $user->id, $request->all());

        return $this->itemResponse($productAllocation);
    }

    /**
     * Display the specified resource.
     *
     * @param ShowProductEquipmentRequest $request
     * @param ProductEquipment $productAllocation
     * @return Response| \Illuminate\Http\JsonResponse
     *
     *
     * @OA\Get(
     *      path="/api/v1/product_allocations/{id}",
     *      operationId="showProductEquipment",
     *      tags={"product_allocation"},
     *      summary="Shows an ProductEquipment",
     *      description="Displays an ProductEquipment by id",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The ProductEquipment Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the ProductEquipment object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/ProductEquipment"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     */
    public function show(ShowProductEquipmentRequest $request, ProductEquipment $productAllocation)
    {
        return $this->itemResponse($productAllocation);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param EditProductEquipmentRequest $request
     * @param ProductEquipment $productAllocation
     * @return Response| \Illuminate\Http\JsonResponse
     *
     * @OA\Get(
     *      path="/api/v1/product_allocations/{id}/edit",
     *      operationId="editProductEquipment",
     *      tags={"product_allocation"},
     *      summary="Shows an ProductEquipment for editting",
     *      description="Displays an ProductEquipment by id",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The ProductEquipment Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the ProductEquipment object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/ProductEquipment"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     */
    public function edit(EditProductEquipmentRequest $request, ProductEquipment $productAllocation)
    {
        return $this->itemResponse($productAllocation);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UpdateProductEquipmentRequest $request
     * @param ProductEquipment $productAllocation
     * @return Response| \Illuminate\Http\JsonResponse
     *
     *
     * @OA\Put(
     *      path="/api/v1/product_allocations/{id}",
     *      operationId="updateProductEquipment",
     *      tags={"product_allocation"},
     *      summary="Updates an ProductEquipment",
     *      description="Handles the updating of an ProductEquipment by id",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The ProductEquipment ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the ProductEquipment object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/ProductEquipment"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     */
    public function update(UpdateProductEquipmentRequest $request, ProductEquipment $productAllocation)
    {
        if ($request->entityIsDeleted($productAllocation)) {
            return $request->disallowUpdate();
        }

        $productAllocation = $this->product_allocation_repo->save($request->all(), $productAllocation);

        return $this->itemResponse($productAllocation);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param DestroyProductEquipmentRequest $request
     * @param ProductEquipment $productAllocation
     * @return Response| \Illuminate\Http\JsonResponse
     *
     *
     * @throws \Exception
     * @OA\Delete(
     *      path="/api/v1/product_allocations/{id}",
     *      operationId="deleteProductEquipment",
     *      tags={"product_allocation"},
     *      summary="Deletes a ProductEquipment",
     *      description="Handles the deletion of an ProductEquipment by id",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The ProductEquipment Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns a HTTP status",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     */
    public function destroy(DestroyProductEquipmentRequest $request, ProductEquipment $productAllocation)
    {
        $this->product_allocation_repo->delete($productAllocation);

        return $this->itemResponse($productAllocation->fresh());
    }

    /**
     * Perform bulk actions on the list view.
     *
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
     *
     *
     * @OA\Post(
     *      path="/api/v1/product_allocations/bulk",
     *      operationId="bulkProductEquipments",
     *      tags={"product_allocation"},
     *      summary="Performs bulk actions on an array of product_allocation",
     *      description="",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/index"),
     *      @OA\RequestBody(
     *         description="Hashed IDs",
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 type="array",
     *                 @OA\Items(
     *                     type="integer",
     *                     description="Array of hashed IDs to be bulk 'actioned",
     *                     example="[0,1,2,3]",
     *                 ),
     *             )
     *         )
     *     ),
     *      @OA\Response(
     *          response=200,
     *          description="The ProductEquipment response",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/ProductEquipment"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),

     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     */
    public function bulk(BulkProductEquipmentRequest $request)
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $action = $request->input('action');

        $ids = $request->input('ids');

        $productAllocation = ProductEquipment::withTrashed()->whereIn('id', $ids);

        $productAllocation->cursor()->each(function ($productAllocation, $key) use ($action, $user) {
            if ($user->can('edit', $productAllocation)) {
                $this->product_allocation_repo->{$action}($productAllocation);
            }
        });

        return $this->listResponse(ProductEquipment::withTrashed()->whereIn('id', $ids));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UploadProductEquipmentRequest $request
     * @param ProductEquipment $productAllocation
     * @return Response| \Illuminate\Http\JsonResponse
     *
     *
     *
     * @OA\Put(
     *      path="/api/v1/product_allocations/{productAllocation}/upload",
     *      operationId="uploadProductEquipment",
     *      tags={"product_allocation"},
     *      summary="Uploads a document to a product_allocation",
     *      description="Handles the uploading of a document to a product",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The ProductEquipment Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the ProductEquipment object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/ProductEquipment"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     */
    public function upload(UploadProductEquipmentRequest $request, ProductEquipment $productAllocation)
    {
        if (!$this->checkFeature(Account::FEATURE_DOCUMENTS)) {
            return $this->featureFailure();
        }

        if ($request->has('documents')) {
            $this->saveDocuments($request->file('documents'), $productAllocation, $request->input('is_public', true));
        }

        return $this->itemResponse($productAllocation->fresh());
    }
}
