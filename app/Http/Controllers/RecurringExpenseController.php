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

use App\Events\RecurringExpense\RecurringExpenseWasCreated;
use App\Events\RecurringExpense\RecurringExpenseWasUpdated;
use App\Factory\RecurringExpenseFactory;
use App\Filters\RecurringExpenseFilters;
use App\Http\Requests\RecurringExpense\CreateRecurringExpenseRequest;
use App\Http\Requests\RecurringExpense\DestroyRecurringExpenseRequest;
use App\Http\Requests\RecurringExpense\EditRecurringExpenseRequest;
use App\Http\Requests\RecurringExpense\ShowRecurringExpenseRequest;
use App\Http\Requests\RecurringExpense\StoreRecurringExpenseRequest;
use App\Http\Requests\RecurringExpense\UpdateRecurringExpenseRequest;
use App\Http\Requests\RecurringExpense\UploadRecurringExpenseRequest;
use App\Models\Account;
use App\Models\RecurringExpense;
use App\Repositories\RecurringExpenseRepository;
use App\Transformers\RecurringExpenseTransformer;
use App\Utils\Ninja;
use App\Utils\Traits\BulkOptions;
use App\Utils\Traits\MakesHash;
use App\Utils\Traits\SavesDocuments;
use App\Utils\Traits\Uploadable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Class RecurringExpenseController.
 * @covers App\Http\Controllers\RecurringExpenseController
 */
class RecurringExpenseController extends BaseController
{
    use MakesHash;
    use Uploadable;
    use BulkOptions;
    use SavesDocuments;

    protected $entity_type = RecurringExpense::class;

    protected $entity_transformer = RecurringExpenseTransformer::class;

    /**
     * @var RecurringExpenseepository
     */
    protected $recurring_expense_repo;

    /**
     * RecurringExpenseController constructor.
     * @param RecurringExpenseRepository $recurring_expense_repo
     */
    public function __construct(RecurringExpenseRepository $recurring_expense_repo)
    {
        parent::__construct();

        $this->recurring_expense_repo = $recurring_expense_repo;
    }

    /**
     * @OA\Get(
     *      path="/api/v1/recurring_expenses",
     *      operationId="getRecurringExpenses",
     *      tags={"recurring_expenses"},
     *      summary="Gets a list of recurring_expenses",
     *      description="Lists recurring_expenses, search and filters allow fine grained lists to be generated.

    Query parameters can be added to performed more fine grained filtering of the recurring_expenses, these are handled by the RecurringExpenseFilters class which defines the methods available",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(ref="#/components/parameters/index"),
     *      @OA\Response(
     *          response=200,
     *          description="A list of recurring_expenses",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/RecurringExpense"),
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
     * @param RecurringExpenseFilters $filters
     * @return Response|mixed
     */
    public function index(RecurringExpenseFilters $filters)
    {
        $recurring_expenses = RecurringExpense::filter($filters);

        return $this->listResponse($recurring_expenses);
    }

    /**
     * Display the specified resource.
     *
     * @param ShowRecurringExpenseRequest $request
     * @param RecurringExpense $recurring_expense
     * @return Response
     *
     *
     * @OA\Get(
     *      path="/api/v1/recurring_expenses/{id}",
     *      operationId="showRecurringExpense",
     *      tags={"recurring_expenses"},
     *      summary="Shows a client",
     *      description="Displays a client by id",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The RecurringExpense Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the recurring_expense object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/RecurringExpense"),
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
    public function show(ShowRecurringExpenseRequest $request, RecurringExpense $recurring_expense)
    {
        return $this->itemResponse($recurring_expense);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param EditRecurringExpenseRequest $request
     * @param RecurringExpense $recurring_expense
     * @return Response
     *
     *
     * @OA\Get(
     *      path="/api/v1/recurring_expenses/{id}/edit",
     *      operationId="editRecurringExpense",
     *      tags={"recurring_expenses"},
     *      summary="Shows a client for editting",
     *      description="Displays a client by id",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The RecurringExpense Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the client object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/RecurringExpense"),
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
    public function edit(EditRecurringExpenseRequest $request, RecurringExpense $recurring_expense)
    {
        return $this->itemResponse($recurring_expense);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UpdateRecurringExpenseRequest $request
     * @param RecurringExpense $recurring_expense
     * @return Response
     *
     *
     *
     * @OA\Put(
     *      path="/api/v1/recurring_expenses/{id}",
     *      operationId="updateRecurringExpense",
     *      tags={"recurring_expenses"},
     *      summary="Updates a client",
     *      description="Handles the updating of a client by id",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The RecurringExpense Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the client object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/RecurringExpense"),
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
    public function update(UpdateRecurringExpenseRequest $request, RecurringExpense $recurring_expense)
    {
        if ($request->entityIsDeleted($recurring_expense)) {
            return $request->disallowUpdate();
        }

        $recurring_expense = $this->recurring_expense_repo->save($request->all(), $recurring_expense);
        $recurring_expense->service()->triggeredActions($request)->save();

        $this->uploadLogo($request->file('company_logo'), $recurring_expense->company, $recurring_expense);

        event(new RecurringExpenseWasUpdated($recurring_expense, $recurring_expense->company, Ninja::eventVars(auth()->user() ? auth()->user()->id : null)));

        return $this->itemResponse($recurring_expense->fresh());
    }

    /**
     * Show the form for creating a new resource.
     *
     * @param CreateRecurringExpenseRequest $request
     * @return Response
     *
     *
     *
     * @OA\Get(
     *      path="/api/v1/recurring_expenses/create",
     *      operationId="getRecurringExpensesCreate",
     *      tags={"recurring_expenses"},
     *      summary="Gets a new blank client object",
     *      description="Returns a blank object with default values",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Response(
     *          response=200,
     *          description="A blank client object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/RecurringExpense"),
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
    public function create(CreateRecurringExpenseRequest $request)
    {
        $recurring_expense = RecurringExpenseFactory::create(auth()->user()->company()->id, auth()->user()->id);

        return $this->itemResponse($recurring_expense);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param StoreRecurringExpenseRequest $request
     * @return Response
     *
     *
     *
     * @OA\Post(
     *      path="/api/v1/recurring_expenses",
     *      operationId="storeRecurringExpense",
     *      tags={"recurring_expenses"},
     *      summary="Adds a client",
     *      description="Adds an client to a company",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the saved client object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/RecurringExpense"),
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
    public function store(StoreRecurringExpenseRequest $request)
    {
        $recurring_expense = $this->recurring_expense_repo->save($request->all(), RecurringExpenseFactory::create(auth()->user()->company()->id, auth()->user()->id));
        $recurring_expense->service()->triggeredActions($request)->save();

        event(new RecurringExpenseWasCreated($recurring_expense, $recurring_expense->company, Ninja::eventVars(auth()->user() ? auth()->user()->id : null)));

        return $this->itemResponse($recurring_expense);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param DestroyRecurringExpenseRequest $request
     * @param RecurringExpense $recurring_expense
     * @return Response
     *
     *
     * @throws \Exception
     * @OA\Delete(
     *      path="/api/v1/recurring_expenses/{id}",
     *      operationId="deleteRecurringExpense",
     *      tags={"recurring_expenses"},
     *      summary="Deletes a client",
     *      description="Handles the deletion of a client by id",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The RecurringExpense Hashed ID",
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
    public function destroy(DestroyRecurringExpenseRequest $request, RecurringExpense $recurring_expense)
    {
        $this->recurring_expense_repo->delete($recurring_expense);

        return $this->itemResponse($recurring_expense->fresh());
    }

    /**
     * Perform bulk actions on the list view.
     *
     * @return Response
     *
     *
     * @OA\Post(
     *      path="/api/v1/recurring_expenses/bulk",
     *      operationId="bulkRecurringExpenses",
     *      tags={"recurring_expenses"},
     *      summary="Performs bulk actions on an array of recurring_expenses",
     *      description="",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/index"),
     *      @OA\RequestBody(
     *         description="User credentials",
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
     *          description="The RecurringExpense User response",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/RecurringExpense"),
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
        $recurring_expenses = RecurringExpense::withTrashed()->find($this->transformKeys($ids));

        $recurring_expenses->each(function ($recurring_expense, $key) use ($action) {
            if (auth()->user()->can('edit', $recurring_expense)) {
                $this->performAction($recurring_expense, $action, true);
            }
        });

        return $this->listResponse(RecurringExpense::withTrashed()->whereIn('id', $this->transformKeys($ids)));
    }

    private function performAction(RecurringExpense $recurring_expense, string $action, $bulk = false)
    {
        switch ($action) {
            case 'archive':
                $this->recurring_expense_repo->archive($recurring_expense);

                if (! $bulk) {
                    return $this->listResponse($recurring_expense);
                }
                break;
            case 'restore':
                $this->recurring_expense_repo->restore($recurring_expense);

                if (! $bulk) {
                    return $this->listResponse($recurring_expense);
                }
                break;
            case 'delete':
                $this->recurring_expense_repo->delete($recurring_expense);

                if (! $bulk) {
                    return $this->listResponse($recurring_expense);
                }
                break;
            case 'email':
                //dispatch email to queue
                break;
            case 'start':
                $recurring_expense = $recurring_expense->service()->start()->save();

                if (! $bulk) {
                    $this->itemResponse($recurring_expense);
                }
                break;
            case 'stop':
                $recurring_expense = $recurring_expense->service()->stop()->save();

                if (! $bulk) {
                    $this->itemResponse($recurring_expense);
                }

                break;
            default:
                // code...
                break;
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UploadRecurringExpenseRequest $request
     * @param RecurringExpense $recurring_expense
     * @return Response
     *
     *
     *
     * @OA\Put(
     *      path="/api/v1/recurring_expenses/{id}/upload",
     *      operationId="uploadRecurringExpense",
     *      tags={"recurring_expense"},
     *      summary="Uploads a document to a recurring_expense",
     *      description="Handles the uploading of a document to a recurring_expense",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The RecurringExpense Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the RecurringExpense object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/RecurringExpense"),
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
    public function upload(UploadRecurringExpenseRequest $request, RecurringExpense $recurring_expense)
    {
        if (! $this->checkFeature(Account::FEATURE_DOCUMENTS)) {
            return $this->featureFailure();
        }

        if ($request->has('documents')) {
            $this->saveDocuments($request->file('documents'), $recurring_expense);
        }

        return $this->itemResponse($recurring_expense->fresh());
    }
}
