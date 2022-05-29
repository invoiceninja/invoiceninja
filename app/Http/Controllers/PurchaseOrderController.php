<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Controllers;


use App\Factory\PurchaseOrderFactory;
use App\Filters\PurchaseOrderFilters;
use App\Http\Requests\PurchaseOrder\CreatePurchaseOrderRequest;
use App\Http\Requests\PurchaseOrder\DestroyPurchaseOrderRequest;
use App\Http\Requests\PurchaseOrder\EditPurchaseOrderRequest;
use App\Http\Requests\PurchaseOrder\ShowPurchaseOrderRequest;
use App\Http\Requests\PurchaseOrder\StorePurchaseOrderRequest;
use App\Http\Requests\PurchaseOrder\UpdatePurchaseOrderRequest;
use App\Models\Client;
use App\Models\PurchaseOrder;
use App\Repositories\PurchaseOrderRepository;
use App\Transformers\PurchaseOrderTransformer;
use App\Utils\Traits\MakesHash;
use Illuminate\Http\Response;

class PurchaseOrderController extends BaseController
{
    use MakesHash;

    protected $entity_type = PurchaseOrder::class;
    protected $entity_transformer = PurchaseOrderTransformer::class;
    protected $purchase_order_repository;

    public function __construct(PurchaseOrderRepository $purchase_order_repository)
    {
        parent::__construct();

        $this->purchase_order_repository = $purchase_order_repository;
    }
    /**
     * Show the list of Purchase Orders.
     *
     * @param \App\Filters\PurchaseOrderFilters $filters  The filters
     *
     * @return Response
     *
     * @OA\Get(
     *      path="/api/v1/purchase_orders",
     *      operationId="getPurchaseOrders",
     *      tags={"purchase_orders"},
     *      summary="Gets a list of purchase orders",
     *      description="Lists purchase orders, search and filters allow fine grained lists to be generated.
     *
     *      Query parameters can be added to performed more fine grained filtering of the purchase orders, these are handled by the PurchaseOrderFilters class which defines the methods available",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Response(
     *          response=200,
     *          description="A list of purchase orders",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Credit"),
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
    public function index(PurchaseOrderFilters $filters)
    {
        $purchase_orders = PurchaseOrder::filter($filters);

        return $this->listResponse($purchase_orders);
    }
    /**
     * Show the form for creating a new resource.
     *
     * @param CreatePurchaseOrderRequest $request  The request
     *
     * @return Response
     *
     *
     * @OA\Get(
     *      path="/api/v1/purchase_orders/create",
     *      operationId="getPurchaseOrderCreate",
     *      tags={"purchase_orders"},
     *      summary="Gets a new blank purchase order object",
     *      description="Returns a blank object with default values",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Response(
     *          response=200,
     *          description="A blank purchase order object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Credit"),
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
    public function create(CreatePurchaseOrderRequest $request)
    {
        $purchase_order = PurchaseOrderFactory::create(auth()->user()->company()->id, auth()->user()->id);

        return $this->itemResponse($purchase_order);
    }
    /**
     * Store a newly created resource in storage.
     *
     * @param StorePurchaseOrderRequest $request  The request
     *
     * @return Response
     *
     *
     * @OA\Post(
     *      path="/api/v1/purchase_orders",
     *      operationId="storePurchaseOrder",
     *      tags={"purhcase_orders"},
     *      summary="Adds a purchase order",
     *      description="Adds an purchase order to the system",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the saved purchase order object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Credit"),
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
    public function store(StorePurchaseOrderRequest $request)
    {

        $client = Client::find($request->get('client_id'));

        $purchase_order = $this->purchase_order_repository->save($request->all(), PurchaseOrderFactory::create(auth()->user()->company()->id, auth()->user()->id));

        $purchase_order = $purchase_order->service()
            ->fillDefaults()
            ->save();

        return $this->itemResponse($purchase_order);
    }
    /**
     * Display the specified resource.
     *
     * @param ShowPurchaseOrderRequest $request  The request
     * @param PurchaseOrder $purchase_order  The purchase order
     *
     * @return Response
     *
     *
     * @OA\Get(
     *      path="/api/v1/purchase_orders/{id}",
     *      operationId="showPurchaseOrder",
     *      tags={"purchase_orders"},
     *      summary="Shows an purcase orders",
     *      description="Displays an purchase order by id",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The Purchase order Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the purchase order object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Credit"),
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
    public function show(ShowPurchaseOrderRequest $request, PurchaseOrder $purchase_order)
    {
        return $this->itemResponse($purchase_order);
    }
    /**
     * Show the form for editing the specified resource.
     *
     * @param EditPurchaseOrderRequest $request The request
     * @param PurchaseOrder $purchase_order The purchase order
     *
     * @return Response
     *
     * @OA\Get(
     *      path="/api/v1/purchase_orders/{id}/edit",
     *      operationId="editPurchaseOrder",
     *      tags={"purchase_orders"},
     *      summary="Shows an purchase order for editting",
     *      description="Displays an purchase order by id",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The purchase order Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the purchase order object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Invoice"),
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
    public function edit(EditPurchaseOrderRequest $request, PurchaseOrder $purchase_order)
    {
        return $this->itemResponse($purchase_order);
    }
    /**
     * Update the specified resource in storage.
     *
     * @param UpdatePurchaseOrderRequest $request The request
     * @param PurchaseOrder $purchase_order
     * @return Response
     *
     *
     * @throws \ReflectionException
     * @OA\Put(
     *      path="/api/v1/purchase_order/{id}",
     *      operationId="updatePurchaseOrder",
     *      tags={"purchase_orders"},
     *      summary="Updates an purchase order",
     *      description="Handles the updating of an purchase order by id",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The purchase order Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the purchase order object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Credit"),
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
    public function update(UpdatePurchaseOrderRequest $request, PurchaseOrder $purchase_order)
    {
        if ($request->entityIsDeleted($purchase_order)) {
            return $request->disallowUpdate();
        }

        $purchase_order = $this->purchase_order_repository->save($request->all(), $purchase_order);

        return $this->itemResponse($purchase_order);
    }
    /**
     * Remove the specified resource from storage.
     *
     * @param DestroyPurchaseOrderRequest $request
     * @param PurchaseOrder $purchase_order
     *
     * @return     Response
     *
     * @throws \Exception
     * @OA\Delete(
     *      path="/api/v1/purchase_orders/{id}",
     *      operationId="deletePurchaseOrder",
     *      tags={"purchase_orders"},
     *      summary="Deletes a purchase order",
     *      description="Handles the deletion of an purchase orders by id",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The purhcase order Hashed ID",
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
    public function destroy(DestroyPurchaseOrderRequest $request, PurchaseOrder $purchase_order)
    {
        $this->purchase_order_repository->delete($purchase_order);

        return $this->itemResponse($purchase_order->fresh());
    }
}
