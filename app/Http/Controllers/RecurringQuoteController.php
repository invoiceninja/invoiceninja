<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Controllers;

use App\Factory\CloneRecurringQuoteFactory;
use App\Factory\CloneRecurringQuoteToQuoteFactory;
use App\Factory\RecurringQuoteFactory;
use App\Filters\RecurringQuoteFilters;
use App\Http\Requests\RecurringQuote\ActionRecurringQuoteRequest;
use App\Http\Requests\RecurringQuote\CreateRecurringQuoteRequest;
use App\Http\Requests\RecurringQuote\DestroyRecurringQuoteRequest;
use App\Http\Requests\RecurringQuote\EditRecurringQuoteRequest;
use App\Http\Requests\RecurringQuote\ShowRecurringQuoteRequest;
use App\Http\Requests\RecurringQuote\StoreRecurringQuoteRequest;
use App\Http\Requests\RecurringQuote\UpdateRecurringQuoteRequest;
use App\Models\Quote;
use App\Models\RecurringQuote;
use App\Repositories\RecurringQuoteRepository;
use App\Transformers\QuoteTransformer;
use App\Transformers\RecurringQuoteTransformer;
use App\Utils\Traits\MakesHash;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Class RecurringQuoteController.
 */
class RecurringQuoteController extends BaseController
{
    use MakesHash;

    protected $entity_type = RecurringQuote::class;

    protected $entity_transformer = RecurringQuoteTransformer::class;

    /**
     * @var RecurringQuoteRepository
     */
    protected $recurring_quote_repo;

    protected $base_repo;

    /**
     * RecurringQuoteController constructor.
     *
     * @param RecurringQuoteRepository $recurring_quote_repo  The RecurringQuote repo
     */
    public function __construct(RecurringQuoteRepository $recurring_quote_repo)
    {
        parent::__construct();

        $this->recurring_quote_repo = $recurring_quote_repo;
    }

    /**
     * Show the list of recurring_invoices.
     *
     * @param RecurringQuoteFilters $filters  The filters
     *
     * @return Response
     *
     *
     * @OA\Get(
     *      path="/api/v1/recurring_quotes",
     *      operationId="getRecurringQuotes",
     *      tags={"recurring_quotes"},
     *      summary="Gets a list of recurring_quotes",
     *      description="Lists recurring_quotes, search and filters allow fine grained lists to be generated.

        Query parameters can be added to performed more fine grained filtering of the recurring_quotes, these are handled by the RecurringQuoteFilters class which defines the methods available",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Response(
     *          response=200,
     *          description="A list of recurring_quotes",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/RecurringQuote"),
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
    public function index(RecurringQuoteFilters $filters)
    {
        $recurring_quotes = RecurringQuote::filter($filters);

        return $this->listResponse($recurring_quotes);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @param CreateRecurringQuoteRequest $request  The request
     *
     * @return Response
     *
     *
     * @OA\Get(
     *      path="/api/v1/recurring_quotes/create",
     *      operationId="getRecurringQuotesCreate",
     *      tags={"recurring_quotes"},
     *      summary="Gets a new blank RecurringQuote object",
     *      description="Returns a blank object with default values",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Response(
     *          response=200,
     *          description="A blank RecurringQuote object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/RecurringQuote"),
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
    public function create(CreateRecurringQuoteRequest $request)
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $recurring_quote = RecurringQuoteFactory::create($user->company()->id, $user->id);

        return $this->itemResponse($recurring_quote);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param StoreRecurringQuoteRequest $request  The request
     *
     * @return Response
     *
     *
     * @OA\Post(
     *      path="/api/v1/recurring_quotes",
     *      operationId="storeRecurringQuote",
     *      tags={"recurring_quotes"},
     *      summary="Adds a RecurringQuote",
     *      description="Adds an RecurringQuote to the system",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the saved RecurringQuote object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/RecurringQuote"),
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
    public function store(StoreRecurringQuoteRequest $request)
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $recurring_quote = $this->recurring_quote_repo->save($request, RecurringQuoteFactory::create($user->company()->id, $user->id));

        return $this->itemResponse($recurring_quote);
    }

    /**
     * Display the specified resource.
     *
     * @param ShowRecurringQuoteRequest $request  The request
     * @param RecurringQuote $recurring_quote  The RecurringQuote
     *
     * @return Response
     *
     *
     * @OA\Get(
     *      path="/api/v1/recurring_quotes/{id}",
     *      operationId="showRecurringQuote",
     *      tags={"recurring_quotes"},
     *      summary="Shows an RecurringQuote",
     *      description="Displays an RecurringQuote by id",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The RecurringQuote Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the RecurringQuote object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/RecurringQuote"),
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
    public function show(ShowRecurringQuoteRequest $request, RecurringQuote $recurring_quote)
    {
        return $this->itemResponse($recurring_quote);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param EditRecurringQuoteRequest $request  The request
     * @param RecurringQuote $recurring_quote  The RecurringQuote
     *
     * @return Response
     *
     *
     * @OA\Get(
     *      path="/api/v1/recurring_quotes/{id}/edit",
     *      operationId="editRecurringQuote",
     *      tags={"recurring_quotes"},
     *      summary="Shows an RecurringQuote for editting",
     *      description="Displays an RecurringQuote by id",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The RecurringQuote Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the RecurringQuote object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/RecurringQuote"),
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
    public function edit(EditRecurringQuoteRequest $request, RecurringQuote $recurring_quote)
    {
        return $this->itemResponse($recurring_quote);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UpdateRecurringQuoteRequest $request  The request
     * @param RecurringQuote $recurring_quote  The RecurringQuote
     *
     * @return Response
     *
     *
     * @OA\Put(
     *      path="/api/v1/recurring_quotes/{id}",
     *      operationId="updateRecurringQuote",
     *      tags={"recurring_quotes"},
     *      summary="Updates an RecurringQuote",
     *      description="Handles the updating of an RecurringQuote by id",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The RecurringQuote Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the RecurringQuote object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/RecurringQuote"),
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
    public function update(UpdateRecurringQuoteRequest $request, RecurringQuote $recurring_quote)
    {
        if ($request->entityIsDeleted($recurring_quote)) {
            return $request->disallowUpdate();
        }

        $recurring_quote = $this->recurring_quote_repo->save(request(), $recurring_quote);

        return $this->itemResponse($recurring_quote);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param DestroyRecurringQuoteRequest $request
     * @param RecurringQuote $recurring_quote
     *
     * @return     Response
     *
     *
     * @throws \Exception
     * @OA\Delete(
     *      path="/api/v1/recurring_quotes/{id}",
     *      operationId="deleteRecurringQuote",
     *      tags={"recurring_quotes"},
     *      summary="Deletes a RecurringQuote",
     *      description="Handles the deletion of an RecurringQuote by id",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The RecurringQuote Hashed ID",
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
    public function destroy(DestroyRecurringQuoteRequest $request, RecurringQuote $recurring_quote)
    {
        $this->recurring_quote_repo->delete($recurring_quote);

        return $this->itemResponse($recurring_quote->fresh());
    }

    /**
     * Perform bulk actions on the list view.
     *
     * @return \Illuminate\Support\Collection
     *
     *
     * @OA\Post(
     *      path="/api/v1/recurring_quotes/bulk",
     *      operationId="bulkRecurringQuotes",
     *      tags={"recurring_quotes"},
     *      summary="Performs bulk actions on an array of recurring_quotes",
     *      description="",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/index"),
     *      @OA\RequestBody(
     *         description="Hashed ids",
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
     *          description="The RecurringQuote response",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/RecurringQuote"),
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
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $action = request()->input('action');

        $ids = request()->input('ids');

        $recurring_quotes = RecurringQuote::withTrashed()->find($this->transformKeys($ids));

        $recurring_quotes->each(function ($recurring_quote, $key) use ($action, $user) {
            if ($user->can('edit', $recurring_quote)) {
                $this->recurring_quote_repo->{$action}($recurring_quote);
            }
        });

        return $this->listResponse(RecurringQuote::withTrashed()->whereIn('id', $this->transformKeys($ids)));
    }

    /**
     * Recurring Quote Actions.
     *
     *
     *
     * @OA\Get(
     *      path="/api/v1/recurring_quotes/{id}/{action}",
     *      operationId="actionRecurringQuote",
     *      tags={"recurring_quotes"},
     *      summary="Performs a custom action on an RecurringQuote",
     *      description="Performs a custom action on an RecurringQuote.

    The current range of actions are as follows
    - clone_to_RecurringQuote
    - clone_to_quote
    - history
    - delivery_note
    - mark_paid
    - download
    - archive
    - delete
    - email",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The RecurringQuote Hashed ID",
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
     *          description="Returns the RecurringQuote object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/RecurringQuote"),
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
     * @param ActionRecurringQuoteRequest $request
     * @param RecurringQuote $recurring_quote
     * @param $action
     */
    public function action(ActionRecurringQuoteRequest $request, RecurringQuote $recurring_quote, $action)
    {
        switch ($action) {
            case 'clone_to_recurring_quote':
                //      $recurring_invoice = CloneRecurringQuoteFactory::create($recurring_invoice, auth()->user()->id);
                //      return $this->itemResponse($recurring_invoice);
                break;
            case 'clone_to_quote':
                // $quote = CloneRecurringQuoteToQuoteFactory::create($recurring_invoice, auth()->user()->id);
                // $this->entity_transformer = QuoteTransformer::class;
                // $this->entity_type = Quote::class;

                // return $this->itemResponse($quote);
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
