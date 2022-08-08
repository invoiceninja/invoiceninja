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

use App\Factory\CompanyTokenFactory;
use App\Filters\TokenFilters;
use App\Http\Requests\Token\CreateTokenRequest;
use App\Http\Requests\Token\DestroyTokenRequest;
use App\Http\Requests\Token\EditTokenRequest;
use App\Http\Requests\Token\ShowTokenRequest;
use App\Http\Requests\Token\StoreTokenRequest;
use App\Http\Requests\Token\UpdateTokenRequest;
use App\Models\CompanyToken;
use App\Repositories\TokenRepository;
use App\Transformers\CompanyTokenHashedTransformer;
use App\Transformers\CompanyTokenTransformer;
use App\Utils\Traits\ChecksEntityStatus;
use App\Utils\Traits\MakesHash;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Class TokenController.
 */
class TokenController extends BaseController
{
    use MakesHash;
    use ChecksEntityStatus;

    protected $entity_type = CompanyToken::class;

    protected $entity_transformer = CompanyTokenTransformer::class;

    public $token_repo;

    /**
     * TokenController constructor.
     * @param TokenRepository $token_repo
     */
    public function __construct(TokenRepository $token_repo)
    {
        parent::__construct();

        $this->token_repo = $token_repo;

        $this->middleware('password_protected')->only(['store', 'update']);
    }

    /**
     * @OA\Get(
     *      path="/api/v1/tokens",
     *      operationId="getTokens",
     *      tags={"tokens"},
     *      summary="Gets a list of company tokens",
     *      description="Lists company tokens.
     *
     *   Query parameters can be added to performed more fine grained filtering of the tokens, these are handled by the TokenFilters class which defines the methods available",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(ref="#/components/parameters/index"),
     *      @OA\Response(
     *          response=200,
     *          description="A list of tokens",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/CompanyToken"),
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
     * @param TokenFilters $filters
     * @return Response|mixed
     */
    public function index(TokenFilters $filters)
    {
        $this->entity_transformer = CompanyTokenHashedTransformer::class;

        $tokens = CompanyToken::filter($filters);

        return $this->listResponse($tokens);
    }

    /**
     * Display the specified resource.
     *
     * @param ShowTokenRequest $request
     * @param CompanyToken $token
     * @return Response
     *
     *
     * @OA\Get(
     *      path="/api/v1/tokens/{id}",
     *      operationId="showToken",
     *      tags={"tokens"},
     *      summary="Shows a token",
     *      description="Displays a token by id",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The Token Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the token object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/CompanyToken"),
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
    public function show(ShowTokenRequest $request, CompanyToken $token)
    {
        return $this->itemResponse($token);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param EditTokenRequest $request
     * @param CompanyToken $token
     * @return Response
     *
     *
     * @OA\Get(
     *      path="/api/v1/tokens/{id}/edit",
     *      operationId="editToken",
     *      tags={"tokens"},
     *      summary="Shows a token for editting",
     *      description="Displays a token by id",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The Token Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the token object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/CompanyToken"),
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
    public function edit(EditTokenRequest $request, CompanyToken $token)
    {
        $this->entity_transformer = CompanyTokenHashedTransformer::class;

        return $this->itemResponse($token);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UpdateTokenRequest $request
     * @param CompanyToken $token
     * @return Response
     *
     *
     *
     * @OA\Put(
     *      path="/api/v1/tokens/{id}",
     *      operationId="updateToken",
     *      tags={"tokens"},
     *      summary="Updates a token",
     *      description="Handles the updating of a token by id",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The Token Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the token object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/CompanyToken"),
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
    public function update(UpdateTokenRequest $request, CompanyToken $token)
    {
        if ($request->entityIsDeleted($token)) {
            return $request->disallowUpdate();
        }

        $this->entity_transformer = CompanyTokenHashedTransformer::class;

        $token = $this->token_repo->save($request->all(), $token);

        return $this->itemResponse($token->fresh());
    }

    /**
     * Show the form for creating a new resource.
     *
     * @param CreateTokenRequest $request
     * @return Response
     *
     *
     *
     * @OA\Get(
     *      path="/api/v1/tokens/create",
     *      operationId="getTokensCreate",
     *      tags={"tokens"},
     *      summary="Gets a new blank token object",
     *      description="Returns a blank object with default values",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Response(
     *          response=200,
     *          description="A blank token object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/CompanyToken"),
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
    public function create(CreateTokenRequest $request)
    {
        $token = CompanyTokenFactory::create(auth()->user()->company()->id, auth()->user()->id, auth()->user()->account_id);

        return $this->itemResponse($token);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param StoreTokenRequest $request
     * @return Response
     *
     *
     *
     * @OA\Post(
     *      path="/api/v1/tokens",
     *      operationId="storeToken",
     *      tags={"tokens"},
     *      summary="Adds a token",
     *      description="Adds an token to a company",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the saved token object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/CompanyToken"),
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
    public function store(StoreTokenRequest $request)
    {
        $company_token = CompanyTokenFactory::create(auth()->user()->company()->id, auth()->user()->id, auth()->user()->account_id);

        $token = $this->token_repo->save($request->all(), $company_token);

        return $this->itemResponse($token);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param DestroyTokenRequest $request
     * @param CompanyToken $token
     * @return Response
     *
     *
     * @throws \Exception
     * @OA\Delete(
     *      path="/api/v1/tokens/{id}",
     *      operationId="deleteToken",
     *      tags={"tokens"},
     *      summary="Deletes a token",
     *      description="Handles the deletion of a token by id",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The Token Hashed ID",
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
    public function destroy(DestroyTokenRequest $request, CompanyToken $token)
    {
        //may not need these destroy routes as we are using actions to 'archive/delete'
        $token->delete();

        $this->entity_transformer = CompanyTokenHashedTransformer::class;

        return $this->itemResponse($token);
    }

    /**
     * Perform bulk actions on the list view.
     *
     * @return Response
     *
     *
     * @OA\Post(
     *      path="/api/v1/tokens/bulk",
     *      operationId="bulkTokens",
     *      tags={"tokens"},
     *      summary="Performs bulk actions on an array of tokens",
     *      description="",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/index"),
     *      @OA\RequestBody(
     *         description="Token ids",
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
     *          description="The Token response",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/CompanyToken"),
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
        $this->entity_transformer = CompanyTokenHashedTransformer::class;

        $action = request()->input('action');

        $ids = request()->input('ids');
        $tokens = CompanyToken::withTrashed()->find($this->transformKeys($ids));

        $tokens->each(function ($token, $key) use ($action) {
            if (auth()->user()->can('edit', $token)) {
                $this->token_repo->{$action}($token);
            }
        });

        return $this->listResponse(CompanyToken::withTrashed()->whereIn('id', $this->transformKeys($ids)));
    }
}
