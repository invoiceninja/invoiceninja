<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Http\Controllers;

use App\Factory\CloneRecurringInvoiceFactory;
use App\Factory\CloneRecurringInvoiceToQuoteFactory;
use App\Factory\RecurringInvoiceFactory;
use App\Filters\RecurringInvoiceFilters;
use App\Http\Requests\RecurringInvoice\ActionRecurringInvoiceRequest;
use App\Http\Requests\RecurringInvoice\CreateRecurringInvoiceRequest;
use App\Http\Requests\RecurringInvoice\DestroyRecurringInvoiceRequest;
use App\Http\Requests\RecurringInvoice\EditRecurringInvoiceRequest;
use App\Http\Requests\RecurringInvoice\ShowRecurringInvoiceRequest;
use App\Http\Requests\RecurringInvoice\StoreRecurringInvoiceRequest;
use App\Http\Requests\RecurringInvoice\UpdateRecurringInvoiceRequest;
use App\Jobs\Entity\ActionEntity;
use App\Models\RecurringInvoice;
use App\Repositories\BaseRepository;
use App\Repositories\RecurringInvoiceRepository;
use App\Transformers\RecurringInvoiceTransformer;
use App\Utils\Traits\MakesHash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Class RecurringInvoiceController.
 */
class RecurringInvoiceController extends BaseController
{
    use MakesHash;

    protected $entity_type = RecurringInvoice::class;

    protected $entity_transformer = RecurringInvoiceTransformer::class;

    /**
     * @var RecurringInvoiceRepository
     */
    protected $recurring_invoice_repo;

    protected $base_repo;

    /**
     * RecurringInvoiceController constructor.
     *
     * @param      \App\Repositories\RecurringInvoiceRepository  $recurring_invoice_repo  The RecurringInvoice repo
     */
    public function __construct(RecurringInvoiceRepository $recurring_invoice_repo)
    {
        parent::__construct();

        $this->recurring_invoice_repo = $recurring_invoice_repo;
    }

    /**
     * Show the list of recurring_invoices.
     *
     * @param      \App\Filters\RecurringInvoiceFilters  $filters  The filters
     *
     * @return \Illuminate\Http\Response
     *
     *
     * @OA\Get(
     *      path="/api/v1/recurring_invoices",
     *      operationId="getRecurringInvoices",
     *      tags={"recurring_invoices"},
     *      summary="Gets a list of recurring_invoices",
     *      description="Lists recurring_invoices, search and filters allow fine grained lists to be generated.

        Query parameters can be added to performed more fine grained filtering of the recurring_invoices, these are handled by the RecurringInvoiceFilters class which defines the methods available",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Response(
     *          response=200,
     *          description="A list of recurring_invoices",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/RecurringInvoice"),
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
    public function index(RecurringInvoiceFilters $filters)
    {
        $recurring_invoices = RecurringInvoice::filter($filters);

        return $this->listResponse($recurring_invoices);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @param      \App\Http\Requests\RecurringInvoice\CreateRecurringInvoiceRequest  $request  The request
     *
     * @return \Illuminate\Http\Response
     *
     *
     *
     * @OA\Get(
     *      path="/api/v1/recurring_invoices/create",
     *      operationId="getRecurringInvoicesCreate",
     *      tags={"recurring_invoices"},
     *      summary="Gets a new blank RecurringInvoice object",
     *      description="Returns a blank object with default values",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Response(
     *          response=200,
     *          description="A blank RecurringInvoice object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/RecurringInvoice"),
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
    public function create(CreateRecurringInvoiceRequest $request)
    {
        $recurring_invoice = RecurringInvoiceFactory::create(auth()->user()->company()->id, auth()->user()->id);

        return $this->itemResponse($recurring_invoice);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param      \App\Http\Requests\RecurringInvoice\StoreRecurringInvoiceRequest  $request  The request
     *
     * @return \Illuminate\Http\Response
     *
     *
     *
     * @OA\Post(
     *      path="/api/v1/recurring_invoices",
     *      operationId="storeRecurringInvoice",
     *      tags={"recurring_invoices"},
     *      summary="Adds a RecurringInvoice",
     *      description="Adds an RecurringInvoice to the system",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the saved RecurringInvoice object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/RecurringInvoice"),
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
    public function store(StoreRecurringInvoiceRequest $request)
    {
        $recurring_invoice = $this->recurring_invoice_repo->save($request->all(), RecurringInvoiceFactory::create(auth()->user()->company()->id, auth()->user()->id));

        return $this->itemResponse($recurring_invoice);
    }

    /**
     * Display the specified resource.
     *
     * @param      \App\Http\Requests\RecurringInvoice\ShowRecurringInvoiceRequest  $request  The request
     * @param      \App\Models\RecurringInvoice                            $recurring_invoice  The RecurringInvoice
     *
     * @return \Illuminate\Http\Response
     *
     *
     * @OA\Get(
     *      path="/api/v1/recurring_invoices/{id}",
     *      operationId="showRecurringInvoice",
     *      tags={"recurring_invoices"},
     *      summary="Shows an RecurringInvoice",
     *      description="Displays an RecurringInvoice by id",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The RecurringInvoice Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the RecurringInvoice object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/RecurringInvoice"),
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
    public function show(ShowRecurringInvoiceRequest $request, RecurringInvoice $recurring_invoice)
    {
        return $this->itemResponse($recurring_invoice);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param      \App\Http\Requests\RecurringInvoice\EditRecurringInvoiceRequest  $request  The request
     * @param      \App\Models\RecurringInvoice                            $recurring_invoice  The RecurringInvoice
     *
     * @return \Illuminate\Http\Response
     *
     *
     * @OA\Get(
     *      path="/api/v1/recurring_invoices/{id}/edit",
     *      operationId="editRecurringInvoice",
     *      tags={"recurring_invoices"},
     *      summary="Shows an RecurringInvoice for editting",
     *      description="Displays an RecurringInvoice by id",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The RecurringInvoice Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the RecurringInvoice object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/RecurringInvoice"),
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
    public function edit(EditRecurringInvoiceRequest $request, RecurringInvoice $recurring_invoice)
    {
        return $this->itemResponse($recurring_invoice);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param      \App\Http\Requests\RecurringInvoice\UpdateRecurringInvoiceRequest  $request  The request
     * @param      \App\Models\RecurringInvoice                              $recurring_invoice  The RecurringInvoice
     *
     * @return \Illuminate\Http\Response
     *
     *
     * @OA\Put(
     *      path="/api/v1/recurring_invoices/{id}",
     *      operationId="updateRecurringInvoice",
     *      tags={"recurring_invoices"},
     *      summary="Updates an RecurringInvoice",
     *      description="Handles the updating of an RecurringInvoice by id",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The RecurringInvoice Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the RecurringInvoice object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/RecurringInvoice"),
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
    public function update(UpdateRecurringInvoiceRequest $request, RecurringInvoice $recurring_invoice)
    {
        if ($request->entityIsDeleted($recurring_invoice)) {
            return $request->disallowUpdate();
        }

        $recurring_invoice = $this->recurring_invoice_repo->save($request->all(), $recurring_invoice);

        return $this->itemResponse($recurring_invoice);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param      \App\Http\Requests\RecurringInvoice\DestroyRecurringInvoiceRequest  $request
     * @param      \App\Models\RecurringInvoice                               $recurring_invoice
     *
     * @return     \Illuminate\Http\Response
     *
     *
     * @OA\Delete(
     *      path="/api/v1/recurring_invoices/{id}",
     *      operationId="deleteRecurringInvoice",
     *      tags={"recurring_invoices"},
     *      summary="Deletes a RecurringInvoice",
     *      description="Handles the deletion of an RecurringInvoice by id",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The RecurringInvoice Hashed ID",
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
    public function destroy(DestroyRecurringInvoiceRequest $request, RecurringInvoice $recurring_invoice)
    {
        $recurring_invoice->delete();

        return response()->json([], 200);
    }

    /**
     * Perform bulk actions on the list view.
     *
     * @return Collection
     *
     *
     * @OA\Post(
     *      path="/api/v1/recurring_invoices/bulk",
     *      operationId="bulkRecurringInvoices",
     *      tags={"recurring_invoices"},
     *      summary="Performs bulk actions on an array of recurring_invoices",
     *      description="",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
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
     *          description="The RecurringInvoice response",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/RecurringInvoice"),
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
    public function bulk()
    {
        $action = request()->input('action');

        $ids = request()->input('ids');

        $recurring_invoices = RecurringInvoice::withTrashed()->find($this->transformKeys($ids));

        $recurring_invoices->each(function ($recurring_invoice, $key) use ($action) {
            if (auth()->user()->can('edit', $recurring_invoice)) {
                $this->recurring_invoice_repo->{$action}($recurring_invoice);
            }
        });

        return $this->listResponse(RecurringInvoice::withTrashed()->whereIn('id', $this->transformKeys($ids)));
    }

    /**
     * Recurring Invoice Actions.
     *
     *
     * @OA\Get(
     *      path="/api/v1/recurring_invoices/{id}/{action}",
     *      operationId="actionRecurringInvoice",
     *      tags={"recurring_invoices"},
     *      summary="Performs a custom action on an RecurringInvoice",
     *      description="Performs a custom action on an RecurringInvoice.

        The current range of actions are as follows
        - clone_to_RecurringInvoice
        - clone_to_quote
        - history
        - delivery_note
        - mark_paid
        - download
        - archive
        - delete
        - email",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The RecurringInvoice Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Parameter(
     *          name="action",
     *          in="path",
     *          description="The action string to be performed",
     *          example="clone_to_quote",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the RecurringInvoice object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/RecurringInvoice"),
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
    public function action(ActionRecurringInvoiceRequest $request, RecurringInvoice $recurring_invoice, $action)
    {
        switch ($action) {
            case 'clone_to_RecurringInvoice':
          //      $recurring_invoice = CloneRecurringInvoiceFactory::create($recurring_invoice, auth()->user()->id);
          //      return $this->itemResponse($recurring_invoice);
                break;
            case 'clone_to_quote':
            //    $recurring_invoice = CloneRecurringInvoiceToQuoteFactory::create($recurring_invoice, auth()->user()->id);
                // todo build the quote transformer and return response here
                break;
            case 'history':
                // code...
                break;
            case 'delivery_note':
                // code...
                break;
            case 'mark_paid':
                // code...
                break;
            case 'archive':
                // code...
                break;
            case 'delete':
                // code...
                break;
            case 'email':
                //dispatch email to queue
                break;

            default:
                // code...
                break;
        }
    }
}
